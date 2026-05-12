<?php
// /app/Controllers/Api/SearchApiController.php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Core\Database;
use App\Core\Logger;

/**
 * SearchApiController
 *
 * JSON API endpoint cho Global Search (thanh tìm kiếm ở header).
 * Được gọi bởi global-search.js qua AJAX sau 300ms debounce.
 *
 * Route: GET /api/search?q={term}
 * Middleware: AuthMiddleware, WorkspaceMiddleware
 *
 * Contract với Dev 3 (global-search.js):
 * Response SUCCESS:
 * {
 *   "success": true,
 *   "data": {
 *     "issues":   [ { "issue_key", "title", "status", "project_name" } ],
 *     "projects": [ { "key", "name" } ],
 *     "members":  [ { "name", "email", "avatar_path" } ]
 *   }
 * }
 *
 * Response ERROR:
 * { "success": false, "message": "Mô tả lỗi" }
 *
 * @author  Dev 1
 * @version 1.0.0
 */
class SearchApiController
{
    private \PDO $db;

    // Số kết quả tối đa mỗi nhóm – theo SRS Phần 3.4.7
    private const MAX_RESULTS_PER_GROUP = 5;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * search()
     *
     * Thực hiện tìm kiếm Issue, Project, Member trong Workspace đang active.
     * Chỉ tìm trong phạm vi workspace_id từ SESSION – không tin query param.
     *
     * @return void  (Output JSON trực tiếp qua Response::json())
     */
    public function search(): void
    {
        // ----------------------------------------------------------------
        // 1. Bắt buộc là AJAX request (kiểm tra header X-Requested-With)
        //    Middleware không chặn được endpoint /api/* nếu gọi trực tiếp từ browser
        // ----------------------------------------------------------------
        if (!Request::isAjax()) {
            Response::json(['success' => false, 'message' => 'Không hợp lệ'], 400);
            return;
        }

        // ----------------------------------------------------------------
        // 2. Lấy workspace_id từ SESSION – đây là nguồn tin cậy duy nhất
        //    WorkspaceMiddleware đã validate active_workspace_id hợp lệ
        //    Tuyệt đối KHÔNG dùng $_GET['workspace_id'] trực tiếp
        // ----------------------------------------------------------------
        $workspaceId = (int) ($_SESSION['active_workspace_id'] ?? 0);

        if ($workspaceId === 0) {
            Response::json(['success' => false, 'message' => 'Phiên làm việc không hợp lệ'], 401);
            return;
        }

        // ----------------------------------------------------------------
        // 3. Validate query string q
        //    - Phải tồn tại
        //    - Tối thiểu 2 ký tự (theo Task Assignment D1-029)
        //    - Tối đa 100 ký tự (chống abuse)
        // ----------------------------------------------------------------
        $q = trim(Request::get('q') ?? '');

        if (mb_strlen($q) < 2) {
            Response::json([
                'success' => false,
                'message' => 'Từ khóa tìm kiếm cần ít nhất 2 ký tự',
            ], 422);
            return;
        }

        if (mb_strlen($q) > 100) {
            Response::json([
                'success' => false,
                'message' => 'Từ khóa tìm kiếm quá dài',
            ], 422);
            return;
        }

        // ----------------------------------------------------------------
        // 4. Thực hiện tìm kiếm – 3 nhóm song song, mỗi nhóm 1 query
        //    Dùng LIKE %q% – chấp nhận được với data volume nhỏ/vừa
        //    trên InfinityFree (SRS Phần 3.4.7 đã confirm chiến lược này)
        //    FULLTEXT index trên issues.title nếu cần performance tốt hơn
        // ----------------------------------------------------------------
        try {
            $results = [
                'issues'   => $this->searchIssues($q, $workspaceId),
                'projects' => $this->searchProjects($q, $workspaceId),
                'members'  => $this->searchMembers($q, $workspaceId),
            ];

            Response::json([
                'success' => true,
                'data'    => $results,
            ]);

        } catch (\PDOException $e) {
            Logger::error(
                'Search API query failed: ' . $e->getMessage(),
                'SearchApiController',
                $e->getTrace()
            );
            Response::json([
                'success' => false,
                'message' => 'Tìm kiếm thất bại. Vui lòng thử lại.',
            ], 500);
        }
    }

    // ================================================================
    // PRIVATE – Mỗi method tìm kiếm một nhóm entity
    // ================================================================

    /**
     * Tìm Issue theo issue_key HOẶC title.
     *
     * Ưu tiên: Match issue_key trước (BT-001), sau đó title LIKE.
     * Dev 3 dùng để hiển thị dropdown kết quả trong global-search.js.
     *
     * @param string $q           Từ khóa tìm kiếm (đã trim)
     * @param int    $workspaceId
     * @return array
     */
    private function searchIssues(string $q, int $workspaceId): array
    {
        // Wrap keyword cho LIKE – PDO không cho phép bind trực tiếp trong LIKE
        $likeParam = '%' . $this->escapeLikeWildcards($q) . '%';

        $stmt = $this->db->prepare(
            "SELECT i.issue_key,
                    i.title,
                    i.status,
                    i.priority,
                    p.name AS project_name
             FROM issues i
             JOIN projects p ON p.id = i.project_id
             WHERE i.workspace_id = :workspace_id
               AND i.deleted_at   IS NULL
               AND p.deleted_at   IS NULL
               AND (
                   i.issue_key LIKE :q_exact
                OR i.title     LIKE :q_like
               )
             ORDER BY
               -- Ưu tiên match issue_key (gõ BT-001 ra ngay)
               CASE WHEN i.issue_key LIKE :q_exact_order THEN 0 ELSE 1 END,
               i.updated_at DESC
             LIMIT :limit"
        );

        $stmt->bindValue(':workspace_id',    $workspaceId,   \PDO::PARAM_INT);
        $stmt->bindValue(':q_exact',         $likeParam,     \PDO::PARAM_STR);
        $stmt->bindValue(':q_like',          $likeParam,     \PDO::PARAM_STR);
        $stmt->bindValue(':q_exact_order',   $likeParam,     \PDO::PARAM_STR);
        $stmt->bindValue(':limit',           self::MAX_RESULTS_PER_GROUP, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Tìm Project theo tên hoặc key trong Workspace.
     *
     * Chỉ trả về project đang active (không archive).
     *
     * @param string $q
     * @param int    $workspaceId
     * @return array
     */
    private function searchProjects(string $q, int $workspaceId): array
    {
        $likeParam = '%' . $this->escapeLikeWildcards($q) . '%';

        $stmt = $this->db->prepare(
            "SELECT p.key,
                    p.name,
                    p.status,
                    COUNT(i.id) AS open_issue_count
             FROM projects p
             LEFT JOIN issues i
                    ON i.project_id   = p.id
                   AND i.status NOT IN ('closed','wont_fix','duplicate')
                   AND i.deleted_at IS NULL
             WHERE p.workspace_id = :workspace_id
               AND p.deleted_at   IS NULL
               AND p.status       = 'active'
               AND (p.name LIKE :q_name OR p.key LIKE :q_key)
             GROUP BY p.id, p.key, p.name, p.status
             ORDER BY p.name ASC
             LIMIT :limit"
        );

        $stmt->bindValue(':workspace_id', $workspaceId, \PDO::PARAM_INT);
        $stmt->bindValue(':q_name',       $likeParam,   \PDO::PARAM_STR);
        $stmt->bindValue(':q_key',        $likeParam,   \PDO::PARAM_STR);
        $stmt->bindValue(':limit',        self::MAX_RESULTS_PER_GROUP, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Tìm Member theo tên hoặc email trong Workspace.
     *
     * JOIN workspace_members để đảm bảo chỉ trả về member của workspace hiện tại.
     * Không trả về email đầy đủ – chỉ trả về masked version để bảo vệ privacy.
     *
     * @param string $q
     * @param int    $workspaceId
     * @return array
     */
    private function searchMembers(string $q, int $workspaceId): array
    {
        $likeParam = '%' . $this->escapeLikeWildcards($q) . '%';

        $stmt = $this->db->prepare(
            "SELECT u.name,
                    u.email,
                    u.avatar_path,
                    wm.role
             FROM users u
             JOIN workspace_members wm
                    ON wm.user_id      = u.id
                   AND wm.workspace_id = :workspace_id
             WHERE u.deleted_at IS NULL
               AND (u.name LIKE :q_name OR u.email LIKE :q_email)
             ORDER BY u.name ASC
             LIMIT :limit"
        );

        $stmt->bindValue(':workspace_id', $workspaceId, \PDO::PARAM_INT);
        $stmt->bindValue(':q_name',       $likeParam,   \PDO::PARAM_STR);
        $stmt->bindValue(':q_email',      $likeParam,   \PDO::PARAM_STR);
        $stmt->bindValue(':limit',        self::MAX_RESULTS_PER_GROUP, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Escape ký tự đặc biệt trong LIKE để tránh SQL injection qua wildcard.
     *
     * WHY: Nếu user gõ "50%" hoặc "user_name", ký tự % và _ sẽ bị MySQL
     * hiểu là wildcard → kết quả sai. Cần escape trước khi wrap vào %...%.
     *
     * @param string $value
     * @return string
     */
    private function escapeLikeWildcards(string $value): string
    {
        return str_replace(
            ['\\', '%', '_'],
            ['\\\\', '\\%', '\\_'],
            $value
        );
    }
}