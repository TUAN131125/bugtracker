<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

/**
 * WorkspaceInvitation Model
 *
 * Quản lý vòng đời lời mời tham gia Workspace.
 *
 * Trạng thái token theo TDD Phần 1.4.2:
 *   pending  → chưa dùng, còn hạn
 *   accepted → đã chấp nhận (single-use)
 *   revoked  → bị thu hồi thủ công
 *   expired  → hết hạn (lazy-mark, không tự xóa)
 *
 * WHY dùng named params trong create():
 *   Controller gọi với named arguments để tránh nhầm thứ tự khi có nhiều param.
 *   Model phải khai báo tên tham số khớp với tên Controller truyền vào.
 *
 * @author  Dev 2
 * @version 1.0.1 – bổ sung findPendingByEmail, markExpired, revoke,
 *                  findById, extendExpiry; sửa named args và kiểu dữ liệu
 * @see     TDD Backend v1.0.0 Phần 1.4, Phần 1.5
 * @see     Task Assignment D2-008
 */
class WorkspaceInvitation
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // =========================================================================
    // WRITE
    // =========================================================================

    /**
     * Tạo lời mời mới với thời hạn 7 ngày.
     *
     * WHY tách $workspaceId thành named param riêng (không gộp vào array):
     * Controller gọi create(workspaceId: ..., email: ...) – named argument PHP 8.
     * Khai báo tên tham số phải khớp chính xác để tránh P1044.
     *
     * WHY $isPreRegistered là bool không phải int:
     * PHP 8 strict_types=1 yêu cầu kiểu nhất quán. PDO tự cast bool → 0/1.
     *
     * @param  int    $workspaceId    ID Workspace
     * @param  string $email          Email người được mời
     * @param  string $role           'admin' | 'member' | 'guest'
     * @param  string $token          Raw token 64 ký tự (bin2hex(random_bytes(32)))
     * @param  int    $invitedBy      user_id của người gửi lời mời
     * @param  bool   $isPreRegistered true nếu email chưa có Account (TDD Phần 1.5)
     * @return bool
     */
    public function create(
        int    $workspaceId,
        string $email,
        string $role,
        string $token,
        int    $invitedBy,
        bool   $isPreRegistered = false,
    ): bool {
        $stmt = $this->db->prepare(
            'INSERT INTO workspace_invitations
                (workspace_id, email, role, token, invited_by,
                 status, is_pre_registered, expires_at, created_at)
             VALUES
                (:workspace_id, :email, :role, :token, :invited_by,
                 \'pending\', :is_pre_registered,
                 DATE_ADD(NOW(), INTERVAL 7 DAY), NOW())'
        );

        return $stmt->execute([
            ':workspace_id'      => $workspaceId,
            ':email'             => strtolower(trim($email)),
            ':role'              => $role,
            ':token'             => $token,
            ':invited_by'        => $invitedBy,
            ':is_pre_registered' => (int) $isPreRegistered,  // PDO cần int cho TINYINT
        ]);
    }

    /**
     * Chấp nhận lời mời: set status = accepted, ghi used_at.
     * Token trở thành single-use sau khi gọi method này (TDD Phần 1.4.2).
     *
     * @param  string $token
     * @return bool
     */
    public function accept(string $token): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE workspace_invitations
             SET    status  = \'accepted\',
                    used_at = NOW()
             WHERE  token   = :token
               AND  status  = \'pending\''  // Chỉ accept khi đang pending
        );

        return $stmt->execute([':token' => $token]);
    }

    /**
     * Thu hồi lời mời (Admin/Owner hủy thủ công).
     * Sau khi revoke, token không thể dùng kể cả còn hạn (TDD Phần 1.4.2).
     *
     * @param  int  $id  ID bản ghi invitation
     * @return bool
     */
    public function revoke(int $id): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE workspace_invitations
             SET    status = \'revoked\'
             WHERE  id     = :id
               AND  status = \'pending\''  // Chỉ revoke khi đang pending
        );

        return $stmt->execute([':id' => $id]);
    }

    /**
     * Đánh dấu invitation đã hết hạn (lazy-mark).
     *
     * WHY lazy-mark thay vì xóa: giữ audit trail, tránh xóa hàng loạt gây
     * timeout trên InfinityFree (TDD Phần 2.4).
     *
     * @param  int  $id
     * @return bool
     */
    public function markExpired(int $id): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE workspace_invitations
             SET    status = \'expired\'
             WHERE  id     = :id
               AND  status = \'pending\''
        );

        return $stmt->execute([':id' => $id]);
    }

    /**
     * Gia hạn thêm 7 ngày cho invitation đang pending (TDD Phần 1.5 – kịch bản 4).
     *
     * Gọi khi Admin chọn "Gửi lại và gia hạn" cho invitation đang pending.
     * Không tạo bản ghi mới – cập nhật expires_at của bản ghi hiện tại.
     *
     * @param  int  $id
     * @return bool
     */
    public function extendExpiry(int $id): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE workspace_invitations
             SET    expires_at = DATE_ADD(NOW(), INTERVAL 7 DAY)
             WHERE  id         = :id
               AND  status     = \'pending\''
        );

        return $stmt->execute([':id' => $id]);
    }

    /**
     * Alias của extendExpiry() – giữ tên cũ để không break code đã dùng resend().
     *
     * @param  int  $id
     * @return bool
     */
    public function resend(int $id): bool
    {
        return $this->extendExpiry($id);
    }

    // =========================================================================
    // READ
    // =========================================================================

    /**
     * Tìm invitation theo token (dùng khi user bấm link mời).
     *
     * Trả về bản ghi bất kể status để Controller tự xử lý từng trường hợp
     * (pending / accepted / revoked / expired) theo SRS UC-009.
     *
     * @param  string     $token
     * @return array|null
     */
    public function findByToken(string $token): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM workspace_invitations
             WHERE  token = :token
             LIMIT  1'
        );

        $stmt->execute([':token' => $token]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    /**
     * Tìm invitation theo ID.
     * Dùng khi Admin thao tác (resend, revoke) theo ID từ danh sách.
     *
     * @param  int        $id
     * @return array|null
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM workspace_invitations
             WHERE  id = :id
             LIMIT  1'
        );

        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    /**
     * Tìm invitation đang pending của một email trong một Workspace.
     *
     * WHY cần method này: kiểm tra kịch bản 4 trong TDD Phần 1.5 –
     * "Email đã có invitation pending" trước khi tạo invitation mới.
     * InvitationService gọi method này để quyết định tạo mới hay gia hạn.
     *
     * Điều kiện: status = pending VÀ expires_at > NOW()
     * (invitation hết hạn nhưng chưa markExpired không tính là pending thực sự)
     *
     * @param  string     $email
     * @param  int        $workspaceId
     * @return array|null  Bản ghi invitation hoặc null nếu không có
     */
    public function findPendingByEmail(string $email, int $workspaceId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM workspace_invitations
             WHERE  email        = :email
               AND  workspace_id = :workspace_id
               AND  status       = \'pending\'
               AND  expires_at   > NOW()
             ORDER  BY created_at DESC
             LIMIT  1'
        );

        $stmt->execute([
            ':email'        => strtolower(trim($email)),
            ':workspace_id' => $workspaceId,
        ]);

        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Lấy danh sách invitation đang pending của một Workspace.
     * Dùng để hiển thị tab "Pending Invitations" trong trang Members.
     *
     * WHY lọc expires_at > NOW(): invitation hết hạn không nên hiển thị
     * trong danh sách pending – tránh gây nhầm lẫn cho Admin.
     *
     * @param  int   $workspaceId
     * @return array
     */
    public function listPending(int $workspaceId): array
    {
        $stmt = $this->db->prepare(
            'SELECT   wi.*,
                      u.name  AS inviter_name,
                      u.email AS inviter_email
             FROM     workspace_invitations wi
             LEFT JOIN users u ON u.id = wi.invited_by
             WHERE    wi.workspace_id = :workspace_id
               AND    wi.status       = \'pending\'
               AND    wi.expires_at   > NOW()
             ORDER BY wi.created_at DESC'
        );

        $stmt->execute([':workspace_id' => $workspaceId]);
        return $stmt->fetchAll();
    }

    /**
     * Dọn dẹp invitation hết hạn theo kiểu lazy batch (TDD Phần 2.4).
     *
     * Gọi khi Admin load trang Members – không gọi trong mọi request.
     * LIMIT 100 để tránh timeout trên InfinityFree.
     *
     * @param  int $workspaceId  Chỉ dọn của workspace này, không dọn toàn bộ
     * @return int Số bản ghi đã đánh dấu expired
     */
    public function markBatchExpired(int $workspaceId): int
    {
        $stmt = $this->db->prepare(
            'UPDATE workspace_invitations
             SET    status = \'expired\'
             WHERE  workspace_id = :workspace_id
               AND  status       = \'pending\'
               AND  expires_at  <= NOW()
             LIMIT  100'
        );

        $stmt->execute([':workspace_id' => $workspaceId]);
        return $stmt->rowCount();
    }
}