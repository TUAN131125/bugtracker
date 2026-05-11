<?php
 
namespace App\Models;
 
use App\Core\Database;
 
/**
 * User Model
 * 
 * Auth methods (Dev 1): findByEmail, create, updateVerified, softDelete, password hashing
 * Business methods (Dev 2): findById, getWorkspaces
 */
class User
{
    private \PDO $db;
 
    public function __construct()
    {
        $this->db = Database::getInstance();
    }
 
    // =========================================================
    // AUTH METHODS – Dev 1 phụ trách
    // =========================================================
 
    /**
     * Tìm user theo email
     */
    public function findByEmail(string $email): array|false
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM users WHERE email = ? AND deleted_at IS NULL LIMIT 1"
        );
        $stmt->execute([$email]);
        return $stmt->fetch();
    }
 
    /**
     * Tạo user mới – password phải được hash TRƯỚC khi truyền vào
     * Dùng: password_hash($pass, PASSWORD_BCRYPT, ['cost' => 12])
     */
    public function create(array $data): int|false
    {
        $stmt = $this->db->prepare(
            "INSERT INTO users (name, email, password_hash, created_at, updated_at)
             VALUES (:name, :email, :password_hash, NOW(), NOW())"
        );
 
        $result = $stmt->execute([
            ':name'          => $data['name'],
            ':email'         => $data['email'],
            ':password_hash' => $data['password_hash'], // đã bcrypt từ Controller/Service
        ]);
 
        return $result ? (int) $this->db->lastInsertId() : false;
    }
 
    /**
     * Đánh dấu email đã xác thực
     */
    public function updateVerified(int $userId): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE users SET email_verified_at = NOW(), updated_at = NOW()
             WHERE id = ?"
        );
        return $stmt->execute([$userId]);
    }
 
    /**
     * Soft delete user
     */
    public function softDelete(int $userId): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE users SET deleted_at = NOW(), updated_at = NOW()
             WHERE id = ?"
        );
        return $stmt->execute([$userId]);
    }
 
    // =========================================================
    // BUSINESS METHODS – Dev 2 phụ trách
    // =========================================================
 
    /**
     * Tìm user theo ID
     * Trả về array thông tin user hoặc false nếu không tìm thấy
     */
    public function findById(int $id): array|false
    {
        $stmt = $this->db->prepare(
            "SELECT id, name, email, avatar_url, email_verified_at, created_at, updated_at
             FROM users
             WHERE id = ? AND deleted_at IS NULL
             LIMIT 1"
        );
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
 
    /**
     * Lấy tất cả Workspace mà user đang tham gia
     * Trả về array các workspace kèm role của user trong từng workspace
     * 
     * @return array [['id', 'name', 'slug', 'avatar_url', 'role', 'joined_at'], ...]
     */
    public function getWorkspaces(int $userId): array
    {
        $stmt = $this->db->prepare(
            "SELECT
                w.id,
                w.name,
                w.slug,
                w.avatar_url,
                wm.role,
                wm.created_at AS joined_at
             FROM workspaces w
             INNER JOIN workspace_members wm
                ON w.id = wm.workspace_id
             WHERE wm.user_id = ?
               AND w.deleted_at IS NULL
             ORDER BY wm.created_at ASC"
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }
}