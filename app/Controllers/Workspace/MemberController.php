<?php

declare(strict_types=1);

namespace App\Controllers\Workspace;

use PDO;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Helpers\Csrf;
use App\Services\RbacService;
use App\Models\WorkspaceMember;
use App\Models\WorkspaceInvitation;
use App\Models\User;

/**
 * MemberController
 *
 * Xử lý toàn bộ quản lý thành viên trong Workspace:
 *   index()      – Danh sách thành viên + pending invitations
 *   updateRole() – Thay đổi role thành viên (AJAX)
 *   remove()     – Kick thành viên khỏi Workspace (AJAX)
 *
 * Lưu ý phân quyền theo SRS Phần 1.2 (RBAC Matrix):
 *   - Chỉ Owner mới có thể promote thành viên lên Admin
 *   - Admin có thể kick Member/Guest nhưng KHÔNG kick được Owner
 *   - Owner không thể bị kick bởi bất kỳ ai
 *
 * Routes (routes.php):
 *   GET    /workspace/members           → index()
 *   PUT    /workspace/members/{id}/role → updateRole()
 *   DELETE /workspace/members/{id}      → remove()
 *
 * Contract với Dev 3 (Task Assignment Phần 5.1):
 *   index() truyền vào View:
 *     $members             – array: id, name, email, avatar_path, role, joined_at
 *     $pending_invitations – array: id, email, role, expires_at, invited_by_name
 *
 * @package App\Controllers\Workspace
 * @version 1.0.0
 * @see     SRS v1.0.0 – UC-008, UC-009, UC-014, Phần 1.3 (RBAC Matrix)
 * @see     TDD Backend v1.0.0 – Phần 2.2.2 (workspace_members, workspace_invitations)
 * @see     Task Assignment v1.0.0 – D2-021
 */
class MemberController
{
    private WorkspaceMember     $member_model;
    private WorkspaceInvitation $invitation_model;
    private RbacService         $rbac_service;
    private User                $user_model;
    private PDO                 $db;

    public function __construct()
    {
        $this->member_model     = new WorkspaceMember();
        $this->invitation_model = new WorkspaceInvitation();
        $this->rbac_service     = new RbacService();
        $this->user_model       = new User();
        $this->db               = Database::getInstance();
    }

    // =========================================================================
    // index() – GET /workspace/members
    // =========================================================================

    /**
     * Hiển thị danh sách thành viên và lời mời đang chờ.
     *
     * Lazy cleanup: xóa invitation hết hạn trước khi query
     * theo TDD Phần 2.4 — thay thế Cronjob trên InfinityFree.
     *
     * @param  Request $request  Inject từ Router.
     * @return void
     */
    public function index(Request $request): void
    {
        $workspace_id = Session::getActiveWorkspaceId();
        $user_id      = Session::getUserId();
        $current_role = Session::get('current_role', 'guest');

        // Lazy cleanup: xóa invitation hết hạn của workspace này
        // Giới hạn 100 bản ghi/lần tránh timeout (TDD Phần 2.4)
        $this->cleanupExpiredInvitations($workspace_id);

        // --- Lấy danh sách thành viên ---
        // JOIN users để lấy thông tin đầy đủ
        // Sắp xếp: owner → admin → member → guest, sau đó theo joined_at
        $members = $this->getMembersWithInfo($workspace_id);

        // --- Lấy danh sách invitation đang pending ---
        // Chỉ Admin/Owner mới xem được pending invitations
        $pending_invitations = [];
        if (in_array($current_role, ['owner', 'admin'], true)) {
            $pending_invitations = $this->getPendingInvitations($workspace_id);
        }

        Response::view('members/index', [
            'pageTitle'           => 'Quản lý thành viên',
            'members'             => $members,
            'pending_invitations' => $pending_invitations,
            'current_role'        => $current_role,
            'current_user_id'     => $user_id,
            'csrfToken'           => Csrf::generateToken(),
        ]);
    }

    // =========================================================================
    // updateRole() – PUT /workspace/members/{id}/role
    // =========================================================================

    /**
     * Thay đổi role của một thành viên trong Workspace.
     * AJAX endpoint — trả về JSON.
     *
     * Ràng buộc theo SRS Phần 1.2:
     *   - Chỉ Owner mới promote lên Admin (Admin không tự phong Admin mới)
     *   - Không thể thay đổi role của Owner
     *   - Không thể tự thay đổi role của chính mình
     *
     * @param  Request $request
     * @param  int     $id       workspace_members.id (không phải user_id)
     * @return void
     */
    public function updateRole(Request $request, int $id): void
    {
        if (!$request->isAjax()) {
            Response::json(['success' => false, 'message' => 'Không hợp lệ.'], 400);
            return;
        }

        Csrf::validateOrFail($request->post('csrf_token', ''));

        $workspace_id = Session::getActiveWorkspaceId();
        $actor_id     = Session::getUserId();
        $actor_role   = Session::get('current_role', 'guest');

        // Chỉ Owner và Admin mới vào được route này (RbacMiddleware đã chặn)
        // Nhưng vẫn double-check tại đây vì đây là thao tác nhạy cảm
        if (!in_array($actor_role, ['owner', 'admin'], true)) {
            Response::json(['success' => false, 'message' => 'Bạn không có quyền thay đổi role.'], 403);
            return;
        }

        // Lấy new_role từ request body (AJAX gửi JSON)
        $new_role = trim($request->post('role', ''));

        // Validate role hợp lệ theo ENUM trong schema
        $allowed_roles = ['admin', 'member', 'guest'];
        if (!in_array($new_role, $allowed_roles, true)) {
            Response::json([
                'success' => false,
                'message' => "Role '{$new_role}' không hợp lệ. Chỉ chấp nhận: admin, member, guest.",
            ], 422);
            return;
        }

        // Lấy thông tin membership cần thay đổi
        $target_membership = $this->member_model->findMembershipById($id, $workspace_id);

        if (!$target_membership) {
            Response::json(['success' => false, 'message' => 'Thành viên không tồn tại.'], 404);
            return;
        }

        $target_user_id   = (int) $target_membership['user_id'];
        $target_role      = $target_membership['role'];

        // Không thể thay đổi role của chính mình
        if ($target_user_id === $actor_id) {
            Response::json([
                'success' => false,
                'message' => 'Bạn không thể tự thay đổi role của chính mình.',
            ], 403);
            return;
        }

        // Owner không bao giờ bị thay đổi role (SRS Phần 1.2)
        if ($target_role === 'owner') {
            Response::json([
                'success' => false,
                'message' => 'Không thể thay đổi role của Owner. Dùng chức năng Chuyển giao quyền Owner.',
            ], 403);
            return;
        }

        // Chỉ Owner mới promote lên Admin — Admin không tự phong Admin mới
        // SRS Phần 1.2.2: "Admin không thể tự phong một Member lên thành Admin"
        if ($new_role === 'admin' && $actor_role !== 'owner') {
            Response::json([
                'success' => false,
                'message' => 'Chỉ Owner mới có thể gán quyền Admin cho thành viên khác.',
            ], 403);
            return;
        }

        // Thực hiện update
        $this->member_model->updateRole($workspace_id, $target_user_id, $new_role);

        // Ghi Activity Log
        $this->logActivity(
            $workspace_id,
            $actor_id,
            'workspace',
            $workspace_id,
            'member_role_changed',
            [
                'user_id'    => $target_user_id,
                'from'       => $target_role,
                'to'         => $new_role,
            ]
        );

        Response::json([
            'success' => true,
            'message' => 'Role đã được cập nhật thành công.',
            'new_role' => $new_role,
        ]);
    }

    // =========================================================================
    // remove() – DELETE /workspace/members/{id}
    // =========================================================================

    /**
     * Kick một thành viên khỏi Workspace.
     * AJAX endpoint — trả về JSON.
     *
     * Ràng buộc theo SRS Phần 1.2:
     *   - Owner không thể bị kick bởi bất kỳ ai
     *   - Admin chỉ kick được Member và Guest
     *   - Không thể tự kick chính mình
     *
     * Lưu ý: Sau khi kick, nếu user đang active trong workspace này,
     * WorkspaceMiddleware sẽ phát hiện và redirect họ về onboarding
     * ở request tiếp theo (TDD Phần 3.4).
     *
     * @param  Request $request
     * @param  int     $id  workspace_members.id (không phải user_id)
     * @return void
     */
    public function remove(Request $request, int $id): void
    {
        if (!$request->isAjax()) {
            Response::json(['success' => false, 'message' => 'Không hợp lệ.'], 400);
            return;
        }

        Csrf::validateOrFail($request->post('csrf_token', ''));

        $workspace_id = Session::getActiveWorkspaceId();
        $actor_id     = Session::getUserId();
        $actor_role   = Session::get('current_role', 'guest');

        // Double-check quyền (RbacMiddleware đã chặn non-admin)
        if (!in_array($actor_role, ['owner', 'admin'], true)) {
            Response::json(['success' => false, 'message' => 'Bạn không có quyền xóa thành viên.'], 403);
            return;
        }

        // Lấy thông tin membership cần xóa
        $target_membership = $this->member_model->findMembershipById($id, $workspace_id);

        if (!$target_membership) {
            Response::json(['success' => false, 'message' => 'Thành viên không tồn tại.'], 404);
            return;
        }

        $target_user_id = (int) $target_membership['user_id'];
        $target_role    = $target_membership['role'];

        // Không thể tự kick chính mình
        if ($target_user_id === $actor_id) {
            Response::json([
                'success' => false,
                'message' => 'Bạn không thể tự xóa chính mình khỏi Workspace. '
                           . 'Hãy chuyển giao quyền Owner trước nếu bạn là Owner.',
            ], 403);
            return;
        }

        // Owner không thể bị kick bởi bất kỳ ai (SRS Phần 1.2.1)
        if ($target_role === 'owner') {
            Response::json([
                'success' => false,
                'message' => 'Owner không thể bị xóa khỏi Workspace.',
            ], 403);
            return;
        }

        // Admin chỉ kick được Member và Guest — không kick được Admin khác
        // (SRS Phần 1.2.2: Admin xóa thành viên "trừ Owner")
        // Tuy nhiên Admin không kick được Admin khác — chỉ Owner mới làm được
        if ($actor_role === 'admin' && $target_role === 'admin') {
            Response::json([
                'success' => false,
                'message' => 'Admin không thể xóa Admin khác. Chỉ Owner mới có thể thực hiện.',
            ], 403);
            return;
        }

        // Thực hiện xóa
        $this->member_model->remove($workspace_id, $target_user_id);

        // Ghi Activity Log
        $this->logActivity(
            $workspace_id,
            $actor_id,
            'workspace',
            $workspace_id,
            'member_kicked',
            [
                'user_id'   => $target_user_id,
                'user_role' => $target_role,
            ]
        );

        Response::json([
            'success' => true,
            'message' => 'Đã xóa thành viên khỏi Workspace.',
        ]);
    }

    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================

    /**
     * Lấy danh sách thành viên kèm thông tin user đầy đủ.
     *
     * Sắp xếp theo thứ bậc role: owner → admin → member → guest,
     * sau đó theo joined_at ASC để hiển thị thành viên lâu nhất lên đầu.
     *
     * Kết quả:
     * [
     *   [
     *     'membership_id' => 1,
     *     'user_id'       => 5,
     *     'name'          => 'Nguyen Van A',
     *     'email'         => 'a@example.com',
     *     'avatar_path'   => null,
     *     'role'          => 'admin',
     *     'joined_at'     => '2026-05-01 10:00:00',
     *   ],
     * ]
     *
     * @param  int   $workspace_id
     * @return array<int, array<string, mixed>>
     */
    private function getMembersWithInfo(int $workspace_id): array
    {
        $stmt = $this->db->prepare(
            "SELECT wm.id           AS membership_id,
                    wm.user_id,
                    wm.role,
                    wm.joined_at,
                    u.name,
                    u.email,
                    u.avatar_path
             FROM workspace_members wm
             JOIN users u ON u.id = wm.user_id
             WHERE wm.workspace_id = :workspace_id
               AND u.deleted_at IS NULL
             ORDER BY
               FIELD(wm.role, 'owner', 'admin', 'member', 'guest'),
               wm.joined_at ASC"
        );
        $stmt->execute([':workspace_id' => $workspace_id]);

        return $stmt->fetchAll();
    }

    /**
     * Lấy danh sách invitation đang pending trong Workspace.
     *
     * JOIN users (invited_by) để hiển thị tên người gửi lời mời.
     * Chỉ lấy invitation chưa hết hạn và status=pending.
     *
     * Kết quả:
     * [
     *   [
     *     'id'              => 3,
     *     'email'           => 'b@example.com',
     *     'role'            => 'member',
     *     'expires_at'      => '2026-05-17 10:00:00',
     *     'invited_by_name' => 'Nguyen Van A',
     *     'created_at'      => '2026-05-10 10:00:00',
     *   ],
     * ]
     *
     * @param  int   $workspace_id
     * @return array<int, array<string, mixed>>
     */
    private function getPendingInvitations(int $workspace_id): array
    {
        $stmt = $this->db->prepare(
            'SELECT wi.id,
                    wi.email,
                    wi.role,
                    wi.expires_at,
                    wi.created_at,
                    u.name AS invited_by_name
             FROM workspace_invitations wi
             JOIN users u ON u.id = wi.invited_by
             WHERE wi.workspace_id = :workspace_id
               AND wi.status       = :status
               AND wi.expires_at   > NOW()
             ORDER BY wi.created_at DESC'
        );
        $stmt->execute([
            ':workspace_id' => $workspace_id,
            ':status'       => 'pending',
        ]);

        return $stmt->fetchAll();
    }

    /**
     * Lazy cleanup: xóa invitation hết hạn của workspace.
     * Giới hạn 100 bản ghi/lần để tránh timeout — thay thế Cronjob (TDD Phần 2.4).
     * Giữ lại invitation đã accepted/revoked để audit trail.
     *
     * @param  int $workspace_id
     * @return void
     */
    private function cleanupExpiredInvitations(int $workspace_id): void
    {
        try {
            $stmt = $this->db->prepare(
                "UPDATE workspace_invitations
                 SET status = 'expired'
                 WHERE workspace_id = :workspace_id
                   AND status       = 'pending'
                   AND expires_at   < NOW()
                 LIMIT 100"
            );
            $stmt->execute([':workspace_id' => $workspace_id]);
        } catch (\PDOException $e) {
            // Silent fail — cleanup không ảnh hưởng luồng chính
            error_log(sprintf(
                '[MemberController::cleanupExpiredInvitations] Failed | Workspace: %d | Error: %s',
                $workspace_id,
                $e->getMessage()
            ));
        }
    }

    /**
     * Ghi Activity Log cho hành động quản lý thành viên.
     *
     * TODO: Replace bằng ActivityLogService::log() sau khi
     * D2-016 hoàn thành (Ngày 4).
     *
     * @param  int                  $workspace_id
     * @param  int                  $user_id       Người thực hiện.
     * @param  string               $entity_type
     * @param  int                  $entity_id
     * @param  string               $action_type
     * @param  array<string, mixed> $metadata
     * @return void
     */
    private function logActivity(
        int    $workspace_id,
        int    $user_id,
        string $entity_type,
        int    $entity_id,
        string $action_type,
        array  $metadata = []
    ): void {
        try {
            $stmt = $this->db->prepare(
                'INSERT INTO activity_logs
                    (workspace_id, user_id, action_type, entity_type, entity_id, metadata, created_at)
                 VALUES
                    (:workspace_id, :user_id, :action_type, :entity_type, :entity_id, :metadata, NOW())'
            );
            $stmt->execute([
                ':workspace_id' => $workspace_id,
                ':user_id'      => $user_id,
                ':action_type'  => $action_type,
                ':entity_type'  => $entity_type,
                ':entity_id'    => $entity_id,
                ':metadata'     => json_encode($metadata, JSON_UNESCAPED_UNICODE),
            ]);
        } catch (\PDOException $e) {
            // Silent fail — Activity Log không được phá vỡ luồng chính
            error_log(sprintf(
                '[MemberController::logActivity] Failed | Action: %s | Error: %s',
                $action_type,
                $e->getMessage()
            ));
        }
    }
}