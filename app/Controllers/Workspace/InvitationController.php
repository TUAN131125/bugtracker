<?php

declare(strict_types=1);

namespace App\Controllers\Workspace;

use PDO;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Helpers\Csrf;
use App\Models\WorkspaceInvitation;
use App\Models\WorkspaceMember;
use App\Models\Workspace;
use App\Models\User;
use App\Services\EmailService;
use App\Services\RbacService;

/**
 * InvitationController
 *
 * Xử lý toàn bộ luồng mời thành viên vào Workspace.
 *
 * 4 kịch bản theo TDD Phần 1.5:
 *   1. Email đã có Account & chưa trong Workspace  → tạo invitation + gửi email xác nhận
 *   2. Email chưa có Account                       → tạo invitation is_pre_registered=1 + gửi email đăng ký
 *   3. Email đã là thành viên Workspace            → báo lỗi, không làm gì
 *   4. Email đã có invitation pending              → hỏi gia hạn, resend nếu đồng ý
 *
 * WHY bỏ InvitationService dependency:
 *   InvitationService chưa được implement (Dev 2 – Ngày 2).
 *   Import undefined class gây fatal error khi PHP load file.
 *   Logic invite/resend được xử lý trực tiếp tại đây theo đúng 4 kịch bản TDD,
 *   sau khi InvitationService hoàn thành sẽ refactor để delegate sang Service.
 *
 * Routes (routes.php):
 *   POST /workspace/members/invite          → invite()
 *   GET  /invite/{token}                    → accept()
 *   GET  /invite/{token}/decline            → decline()
 *   POST /workspace/invitations/{id}/resend → resend()
 *
 * @package App\Controllers\Workspace
 * @version 1.0.3
 * @see     SRS v1.0.0 – UC-008, UC-009, Phần 2.3 (Invitation Flow)
 * @see     TDD Backend v1.0.0 – Phần 1.4 (Token Security), Phần 1.5 (4 kịch bản)
 *
 * CHANGELOG v1.0.3:
 *   - NOTE: Thêm ghi chú về stale session role — nếu role của user bị thay đổi
 *     bởi Owner trong khi user đang online, current_role trong session có thể
 *     không phản ánh đúng quyền hiện tại cho đến khi session refresh.
 *     Giải pháp triệt để là verify role từ DB trong RbacMiddleware, nhưng
 *     đó là cải tiến ở tầng Middleware (ngoài scope controller này).
 *   - IMPROVE: Thêm double-check quyền từ DB trong invite() và resend()
 *     để giảm thiểu rủi ro stale session role.
 */
class InvitationController
{
    private RbacService         $rbac_service;
    private WorkspaceInvitation $invitation_model;
    private WorkspaceMember     $member_model;
    private Workspace           $workspace_model;
    private User                $user_model;
    private EmailService        $email_service;
    private PDO                 $db;

    /** TTL mặc định của invitation token: 7 ngày (TDD Phần 1.4.1) */
    private const INVITATION_TTL_DAYS = 7;

    public function __construct()
    {
        $this->rbac_service     = new RbacService();
        $this->invitation_model = new WorkspaceInvitation();
        $this->member_model     = new WorkspaceMember();
        $this->workspace_model  = new Workspace();
        $this->user_model       = new User();
        $this->email_service    = new EmailService();
        $this->db               = Database::getInstance();
    }

    // =========================================================================
    // invite() – POST /workspace/members/invite
    // =========================================================================

    /**
     * Gửi lời mời thành viên mới vào Workspace.
     * AJAX endpoint — Dev 3 gọi từ invite modal trong members.js.
     *
     * Xử lý 4 kịch bản theo TDD Phần 1.5.
     *
     * NOTE về stale session role:
     *   Quyền actor_role lấy từ session — có thể stale nếu Owner vừa demote
     *   actor trong khi actor đang online. Để giảm thiểu rủi ro này,
     *   ta double-check bằng cách verify từ DB qua RbacService thay vì
     *   chỉ dựa vào session value.
     */
    public function invite(Request $request): void
    {
        if (!$request->isAjax()) {
            Response::json(['success' => false, 'message' => 'Không hợp lệ.'], 400);
            return;
        }

        Csrf::validateOrFail($request->post('csrf_token', ''));

        $workspace_id = Session::getActiveWorkspaceId();
        $actor_id     = Session::getUserId();

        // IMPROVE v1.0.3: Double-check quyền từ DB thay vì chỉ session
        // Tránh stale session role khi actor bị demote trong khi đang online.
        // canInviteMembers() query trực tiếp bảng workspace_members.
        if (!$this->rbac_service->canInviteMembers($actor_id, $workspace_id)) {
            Response::json(['success' => false, 'message' => 'Bạn không có quyền mời thành viên.'], 403);
            return;
        }

        // Lấy role thực tế của actor từ DB (không dùng session để tránh stale)
        $actor_role = $this->rbac_service->getRoleInWorkspace($actor_id, $workspace_id);

        // Validate input
        $email    = trim(strtolower($request->post('email', '')));
        $new_role = trim($request->post('role', 'member'));

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Response::json(['success' => false, 'message' => 'Địa chỉ email không hợp lệ.'], 422);
            return;
        }

        $allowed_roles = ['admin', 'member', 'guest'];
        if (!in_array($new_role, $allowed_roles, true)) {
            Response::json(['success' => false, 'message' => "Role '{$new_role}' không hợp lệ."], 422);
            return;
        }

        // Admin KHÔNG được mời với role=admin (SRS Phần 1.2.2)
        if ($new_role === 'admin' && $actor_role !== 'owner') {
            Response::json([
                'success' => false,
                'message' => 'Chỉ Owner mới có thể mời thành viên với quyền Admin.',
            ], 403);
            return;
        }

        try {
            // --- Kịch bản 3: Email đã là member ---
            $existing_user = $this->user_model->findByEmail($email);

            if ($existing_user !== null
                && $this->member_model->isMember($workspace_id, (int) $existing_user['id'])
            ) {
                Response::json([
                    'success' => false,
                    'message' => 'Email này đã là thành viên của Workspace.',
                    'data'    => ['scenario' => 'already_member'],
                ], 422);
                return;
            }

            // --- Kịch bản 4: Đã có invitation pending ---
            $pending = $this->invitation_model->findPendingByEmail($email, $workspace_id);

            if ($pending !== null) {
                Response::json([
                    'success' => false,
                    'message' => 'Đã có lời mời đang chờ xử lý cho email này.',
                    'data'    => [
                        'scenario'      => 'pending_exists',
                        'invitation_id' => (int) $pending['id'],
                        'expires_at'    => $pending['expires_at'],
                    ],
                ], 409);
                return;
            }

            // --- Kịch bản 1 & 2: Tạo invitation mới ---
            // $existing_user === null → email chưa có account → is_pre_registered = true
            $is_pre_registered = ($existing_user === null); // bool

            $token = bin2hex(random_bytes(32)); // 64 chars – TDD 1.4.1

            $this->invitation_model->create(
                workspaceId:     $workspace_id,
                email:           $email,
                role:            $new_role,
                token:           $token,
                invitedBy:       $actor_id,
                isPreRegistered: $is_pre_registered
            );

            // Gửi email mời
            $workspace  = $this->workspace_model->findById($workspace_id);
            $actor_info = $this->user_model->findById($actor_id);

            $invite_url = rtrim($_ENV['APP_URL'], '/') . '/invite/' . $token;
            $html_body  = $this->email_service->renderTemplate('workspace-invite', [
                'inviter_name'   => (string) ($actor_info['name'] ?? 'Quản trị viên'),
                'workspace_name' => (string) ($workspace['name'] ?? 'Workspace'),
                'role'           => $new_role,
                'invite_url'     => $invite_url,
                'expires_days'   => self::INVITATION_TTL_DAYS,
            ]);

            $this->email_service->send(
                to:       $email,
                toName:   '',
                subject:  '[BugTracker] Bạn được mời tham gia ' . ($workspace['name'] ?? 'Workspace'),
                htmlBody: $html_body
            );

            $scenario = $is_pre_registered ? 'invited_new' : 'invited_existing';

            $this->logActivity(
                workspaceId: $workspace_id,
                userId:      $actor_id,
                entityType:  'workspace',
                entityId:    $workspace_id,
                actionType:  'member_invited',
                metadata:    ['email' => $email, 'role' => $new_role, 'scenario' => $scenario]
            );

            Response::json([
                'success' => true,
                'message' => "Đã gửi lời mời đến {$email}.",
                'data'    => ['scenario' => $scenario],
            ]);

        } catch (\Exception $e) {
            error_log(sprintf(
                '[InvitationController::invite] Failed | Workspace: %d | Email: %s | Error: %s',
                $workspace_id,
                $email,
                $e->getMessage()
            ));
            Response::json(['success' => false, 'message' => 'Gửi lời mời thất bại. Vui lòng thử lại.'], 500);
        }
    }

    // =========================================================================
    // accept() – GET /invite/{token}
    // =========================================================================

    /**
     * Xử lý khi người nhận bấm link mời từ email.
     *
     * Nhánh A: Đã đăng nhập + email khớp     → join ngay
     * Nhánh B: Chưa đăng nhập + email có sẵn → redirect login, lưu token vào session
     * Nhánh C: Email chưa có account          → redirect register với email prefill
     */
    public function accept(Request $request, string $token): void
    {
        // Sanitize: chỉ giữ hex, đúng 64 ký tự
        $token = preg_replace('/[^a-f0-9]/i', '', $token);

        if (strlen($token) !== 64) {
            Response::setFlash('error', 'Link mời không hợp lệ.');
            Response::redirect('/login');
            return;
        }

        // Validate token trong DB
        $invitation = $this->invitation_model->findByToken($token);

        if ($invitation === null) {
            Response::setFlash('error', 'Link mời không hợp lệ hoặc đã hết hạn.');
            Response::redirect('/login');
            return;
        }

        if ($invitation['status'] !== 'pending') {
            $message = match ($invitation['status']) {
                'accepted' => 'Link mời này đã được sử dụng.',
                'revoked'  => 'Link mời này đã bị thu hồi.',
                'expired'  => 'Link mời đã hết hạn. Vui lòng liên hệ quản trị viên để được gửi lại.',
                default    => 'Link mời không còn hợp lệ.',
            };
            Response::setFlash('error', $message);
            Response::redirect('/login');
            return;
        }

        if (strtotime($invitation['expires_at']) < time()) {
            // Lazy update status=expired
            $this->invitation_model->markExpired((int) $invitation['id']);
            Response::setFlash('error', 'Link mời đã hết hạn. Vui lòng liên hệ quản trị viên Workspace để được gửi lại.');
            Response::redirect('/login');
            return;
        }

        // hash_equals() — chống timing attack (TDD 1.4.3)
        if (!hash_equals((string) $invitation['token'], $token)) {
            Response::setFlash('error', 'Link mời không hợp lệ.');
            Response::redirect('/login');
            return;
        }

        $invited_email = (string) $invitation['email'];

        // NHÁNH A: Đã đăng nhập
        if (Session::isLoggedIn()) {
            $current_user  = $this->user_model->findById(Session::getUserId());
            $current_email = (string) ($current_user['email'] ?? '');

            if (!hash_equals($current_email, $invited_email)) {
                Response::setFlash('error',
                    'Lời mời này dành cho email khác. Vui lòng đăng nhập bằng: '
                    . $this->maskEmail($invited_email)
                );
                Response::redirect('/dashboard');
                return;
            }

            $this->processAcceptance($invitation);
            return;
        }

        // Lưu token vào session — an toàn hơn URL param (tránh bị server/proxy log)
        Session::set('pending_invite_token', $token);

        $existing_user = $this->user_model->findByEmail($invited_email);

        if ($existing_user !== null) {
            // NHÁNH B: Có account, chưa đăng nhập
            Response::setFlash('info', 'Vui lòng đăng nhập để chấp nhận lời mời vào Workspace.');
            Response::redirect('/login');
        } else {
            // NHÁNH C: Chưa có account — prefill email qua query param (read-only trong form)
            Response::setFlash('info', 'Bạn cần tạo tài khoản để chấp nhận lời mời. Email đã được điền sẵn.');
            Response::redirect('/register?invite=' . urlencode($invited_email));
        }
    }

    // =========================================================================
    // processPendingInvitation() – Gọi từ LoginController và RegisterController
    // =========================================================================

    /**
     * Kiểm tra và xử lý invitation đang chờ trong session.
     *
     * Được gọi từ LoginController và RegisterController sau khi
     * user đăng nhập/đăng ký thành công, nếu session có pending_invite_token.
     *
     * @param  int    $userId     User vừa đăng nhập/đăng ký.
     * @param  string $userEmail  Email của user (lowercase).
     * @return bool   true nếu có invitation và đã xử lý.
     */
    public function processPendingInvitation(int $userId, string $userEmail): bool
    {
        $token = (string) Session::get('pending_invite_token', '');

        if (empty($token)) {
            return false;
        }

        Session::remove('pending_invite_token');

        $token = preg_replace('/[^a-f0-9]/i', '', $token);

        if (strlen($token) !== 64) {
            return false;
        }

        $invitation = $this->invitation_model->findByToken($token);

        if ($invitation === null
            || $invitation['status'] !== 'pending'
            || strtotime($invitation['expires_at']) < time()
        ) {
            Response::setFlash('warning', 'Lời mời đã hết hạn hoặc không còn hợp lệ.');
            return false;
        }

        if (!hash_equals((string) $invitation['email'], strtolower($userEmail))) {
            Response::setFlash('error', 'Lời mời này không dành cho email của bạn.');
            return false;
        }

        $this->processAcceptance($invitation, $userId);
        return true;
    }

    // =========================================================================
    // decline() – GET /invite/{token}/decline
    // =========================================================================

    /**
     * Từ chối lời mời — revoke token, không join workspace.
     *
     * WHY dùng GET: Người dùng bấm link từ email, email client không gửi POST.
     */
    public function decline(Request $request, string $token): void
    {
        $token = preg_replace('/[^a-f0-9]/i', '', $token);

        if (strlen($token) !== 64) {
            Response::setFlash('error', 'Link không hợp lệ.');
            Response::redirect('/login');
            return;
        }

        $invitation = $this->invitation_model->findByToken($token);

        if ($invitation === null || $invitation['status'] !== 'pending') {
            Response::setFlash('info', 'Lời mời này không còn hiệu lực.');

            if (Session::isLoggedIn()) {
                Response::redirect('/dashboard');
            } else {
                Response::redirect('/login');
            }
            return;
        }

        $this->invitation_model->revoke((int) $invitation['id']);

        $workspace = $this->workspace_model->findById((int) $invitation['workspace_id']);
        $ws_name   = (string) ($workspace['name'] ?? 'Workspace');

        Response::setFlash('info', "Bạn đã từ chối lời mời tham gia \"{$ws_name}\".");

        if (Session::isLoggedIn()) {
            Response::redirect('/dashboard');
        } else {
            Response::redirect('/login');
        }
    }

    // =========================================================================
    // resend() – POST /workspace/invitations/{id}/resend
    // =========================================================================

    /**
     * Gia hạn và gửi lại lời mời.
     * AJAX endpoint.
     *
     * NOTE về stale session role: Tương tự invite(), double-check quyền
     * từ DB thay vì chỉ dựa vào session.
     */
    public function resend(Request $request, int $id): void
    {
        if (!$request->isAjax()) {
            Response::json(['success' => false, 'message' => 'Không hợp lệ.'], 400);
            return;
        }

        Csrf::validateOrFail($request->post('csrf_token', ''));

        $workspace_id = Session::getActiveWorkspaceId();
        $actor_id     = Session::getUserId();

        // IMPROVE v1.0.3: Double-check quyền từ DB thay vì chỉ session
        if (!$this->rbac_service->canInviteMembers($actor_id, $workspace_id)) {
            Response::json(['success' => false, 'message' => 'Bạn không có quyền thực hiện thao tác này.'], 403);
            return;
        }

        $invitation = $this->invitation_model->findById($id);

        if ($invitation === null) {
            Response::json(['success' => false, 'message' => 'Lời mời không tồn tại.'], 404);
            return;
        }

        // Guard IDOR: đảm bảo invitation thuộc workspace đang active
        if ((int) $invitation['workspace_id'] !== $workspace_id) {
            Response::json(['success' => false, 'message' => 'Lời mời không tồn tại.'], 404);
            return;
        }

        if ($invitation['status'] !== 'pending') {
            Response::json([
                'success' => false,
                'message' => 'Chỉ có thể gửi lại lời mời đang chờ xử lý (pending).',
            ], 422);
            return;
        }

        try {
            // extendExpiry() tự tính DATE_ADD(NOW(), INTERVAL 7 DAY) trong SQL
            $this->invitation_model->extendExpiry($id);

            // Gửi lại email
            $workspace  = $this->workspace_model->findById($workspace_id);
            $actor_info = $this->user_model->findById($actor_id);
            $token      = (string) $invitation['token'];

            $invite_url = rtrim($_ENV['APP_URL'], '/') . '/invite/' . $token;
            $html_body  = $this->email_service->renderTemplate('workspace-invite', [
                'inviter_name'   => (string) ($actor_info['name'] ?? 'Quản trị viên'),
                'workspace_name' => (string) ($workspace['name'] ?? 'Workspace'),
                'role'           => (string) $invitation['role'],
                'invite_url'     => $invite_url,
                'expires_days'   => self::INVITATION_TTL_DAYS,
            ]);

            $this->email_service->send(
                to:       (string) $invitation['email'],
                toName:   '',
                subject:  '[BugTracker] Lời mời tham gia ' . ($workspace['name'] ?? 'Workspace'),
                htmlBody: $html_body
            );

            $new_expires_display = date('d/m/Y', time() + (self::INVITATION_TTL_DAYS * 86400));

            $this->logActivity(
                workspaceId: $workspace_id,
                userId:      $actor_id,
                entityType:  'workspace',
                entityId:    $workspace_id,
                actionType:  'member_invited',
                metadata:    [
                    'email'  => (string) $invitation['email'],
                    'role'   => (string) $invitation['role'],
                    'action' => 'resent',
                ]
            );

            Response::json([
                'success' => true,
                'message' => "Đã gửi lại lời mời đến {$invitation['email']}. Hạn mới: {$new_expires_display}.",
                'data'    => ['new_expires_at' => $new_expires_display],
            ]);

        } catch (\Exception $e) {
            error_log(sprintf(
                '[InvitationController::resend] Failed | Invitation: %d | Error: %s',
                $id,
                $e->getMessage()
            ));
            Response::json(['success' => false, 'message' => 'Không thể gửi lại lời mời. Vui lòng thử lại.'], 500);
        }
    }

    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================

    /**
     * Thực hiện join workspace sau khi token đã được validate đầy đủ.
     *
     * Dùng DB transaction để đảm bảo 3 thao tác là atomic:
     *   1. Tạo workspace_members record
     *   2. Cập nhật invitation status=accepted + used_at
     *   3. Nếu is_pre_registered=1: set is_verified=1, mark onboarding completed
     *
     * WHY atomic: Nếu add member thành công nhưng update invitation fail
     * → token vẫn pending → user dùng link lần 2 được (vi phạm single-use).
     *
     * @param  array<string, mixed> $invitation  Row từ workspace_invitations.
     * @param  int|null             $userId      null = lấy từ session hiện tại.
     */
    private function processAcceptance(array $invitation, ?int $userId = null): void
    {
        if ($userId === null) {
            $userId = Session::getUserId();
        }

        $workspace_id = (int) $invitation['workspace_id'];
        $role         = (string) $invitation['role'];

        $workspace = $this->workspace_model->findById($workspace_id);
        $ws_name   = (string) ($workspace['name'] ?? 'Workspace');

        $this->db->beginTransaction();

        try {
            // Edge case: double-click link — idempotent check
            if (!$this->member_model->isMember($workspace_id, $userId)) {
                $this->member_model->add($workspace_id, $userId, $role);
            }

            // Đánh dấu token đã dùng — SINGLE-USE (TDD Phần 1.4.2)
            $this->invitation_model->accept((string) $invitation['token']);

            // Nếu là pre-registered: auto verify + bypass onboarding (SRS UC-009 Nhánh C)
            if ((bool) $invitation['is_pre_registered']) {
                $this->user_model->updateVerified($userId);
                $this->user_model->markOnboardingCompleted($userId);
            }

            $this->logActivity(
                workspaceId: $workspace_id,
                userId:      $userId,
                entityType:  'workspace',
                entityId:    $workspace_id,
                actionType:  'member_invited',
                metadata:    [
                    'email'  => (string) $invitation['email'],
                    'role'   => $role,
                    'action' => 'accepted',
                ]
            );

            $this->db->commit();

        } catch (\PDOException $e) {
            $this->db->rollBack();

            error_log(sprintf(
                '[InvitationController::processAcceptance] Transaction failed | '
                . 'Invitation: %d | User: %d | Error: %s',
                (int) $invitation['id'],
                $userId,
                $e->getMessage()
            ));

            Response::setFlash('error', 'Không thể xử lý lời mời. Vui lòng thử lại.');
            Response::redirect('/dashboard');
            return;
        }

        // Cập nhật session
        Session::set('active_workspace_id', $workspace_id);
        Session::set('onboarding_completed', true);

        Response::setFlash('success', "Chào mừng bạn đến với Workspace \"{$ws_name}\"! Vai trò của bạn: {$role}.");
        Response::redirect('/dashboard');
    }

    /**
     * Mask email để tránh lộ thông tin trong thông báo lỗi.
     * nguyen.van.a@gmail.com → n***@gmail.com
     */
    private function maskEmail(string $email): string
    {
        $parts  = explode('@', $email, 2);
        $local  = $parts[0] ?? '';
        $domain = $parts[1] ?? '';

        return substr($local, 0, 1) . '***@' . $domain;
    }

    /**
     * Ghi Activity Log cho hành động liên quan đến Invitation.
     *
     * TODO: Replace bằng ActivityLogService::log() sau khi D2-016 hoàn thành (Ngày 4).
     *
     * @param  int                  $workspaceId
     * @param  int                  $userId       Người thực hiện.
     * @param  string               $entityType
     * @param  int                  $entityId
     * @param  string               $actionType
     * @param  array<string, mixed> $metadata
     */
    private function logActivity(
        int    $workspaceId,
        int    $userId,
        string $entityType,
        int    $entityId,
        string $actionType,
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
                ':workspace_id' => $workspaceId,
                ':user_id'      => $userId,
                ':action_type'  => $actionType,
                ':entity_type'  => $entityType,
                ':entity_id'    => $entityId,
                ':metadata'     => (string) json_encode($metadata, JSON_UNESCAPED_UNICODE) ?: '{}',
            ]);
        } catch (\PDOException $e) {
            error_log(sprintf(
                '[InvitationController::logActivity] Failed | Action: %s | Error: %s',
                $actionType,
                $e->getMessage()
            ));
        }
    }
}