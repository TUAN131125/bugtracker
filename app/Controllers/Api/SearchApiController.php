<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use PDO;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

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
 *     "issues":   [ { "issue_key", "title", "status", "priority", "project_name" } ],
 *     "projects": [ { "key", "name", "status", "open_issue_count" } ],
 *     "members":  [ { "name", "email" (masked), "avatar_path", "role" } ]
 *   }
 * }
 *
 * Response ERROR:
 * { "success": false, "message": "Mô tả lỗi" }
 *
 * @author  Dev 1
 * @version 1.0.0
 * @see     SRS v1.0.0 – Phần 3.4.7 (Global Search)
 * @see     Task Assignment v1.0.0 – D1-029
 */
class SearchApiController
{
    private PDO $db;

    /**
     * Số kết quả tối đa mỗi nhóm.
     * Giá trị này được định nghĩa trong SRS v1.0.0 Phần 3.4.7.
     * KHÔNG thay đổi mà không cập nhật SRS và global-search.js.
     */
    private const MAX_RESULTS_PER_GROUP = 5;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Thực hiện tìm kiếm Issue, Project, Member trong Workspace đang active.
     * Chỉ tìm trong phạm vi workspace_id từ SESSION – không tin query param.
     *
     * @param  Request $request  Inject từ Router (nhất quán với toàn bộ codebase).
     * @return void
     */
    public function search(Request $request): void
    {
        // ----------------------------------------------------------------
        // 1. Bắt buộc là AJAX request
        // ----------------------------------------------------------------
        if (!$request->isAjax()) {
            Response::json(['success' => false, 'message' => 'Không hợp lệ'], 400);
            return;
        }

        // ----------------------------------------------------------------
        // 2. Lấy workspace_id từ Session class – nguồn tin cậy duy nhất.
        //    WorkspaceMiddleware đã validate active_workspace_id hợp lệ.
        //    Tuyệt đối KHÔNG dùng $_GET['workspace_id'] hay $_SESSION trực tiếp.
        // ----------------------------------------------------------------
        $workspaceId = Session::getActiveWorkspaceId();

        if (!$workspaceId) {
            Response::json([
                'success' => false,
                'message' => 'Phiên làm việc không hợp lệ',
            ], 401);
            return;
        }

        // ----------------------------------------------------------------
        // 3. Validate query string q
        // ----------------------------------------------------------------
        $q = trim($request->get('q', ''));

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
        // 4. Thực hiện tìm kiếm – 3 nhóm, mỗi nhóm 1 query
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
            // TODO: Replace bằng Logger::error() sau khi D1-021 hoàn thành (Ngày 3)
            error_log(sprintf(
                '[SearchApiController] Search failed | Workspace: %d | Query: %s | Error: %s',
                $workspaceId,
                $q,
                $e->getMessage()
            ));

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
     * @param  string $q
     * @param  int    $workspaceId
     * @return array<int, array<string, mixed>>
     */
    private function searchIssues(string $q, int $workspaceId): array
    {
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
               CASE WHEN i.issue_key LIKE :q_exact_order THEN 0 ELSE 1 END,
               i.updated_at DESC
             LIMIT :limit"
        );

        $stmt->bindValue(':workspace_id',    $workspaceId,                   PDO::PARAM_INT);
        $stmt->bindValue(':q_exact',         $likeParam,                     PDO::PARAM_STR);
        $stmt->bindValue(':q_like',          $likeParam,                     PDO::PARAM_STR);
        $stmt->bindValue(':q_exact_order',   $likeParam,                     PDO::PARAM_STR);
        $stmt->bindValue(':limit',           self::MAX_RESULTS_PER_GROUP,    PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Tìm Project theo tên hoặc key trong Workspace.
     * Chỉ trả về project đang active (không archive).
     *
     * @param  string $q
     * @param  int    $workspaceId
     * @return array<int, array<string, mixed>>
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

        $stmt->bindValue(':workspace_id', $workspaceId,                PDO::PARAM_INT);
        $stmt->bindValue(':q_name',       $likeParam,                  PDO::PARAM_STR);
        $stmt->bindValue(':q_key',        $likeParam,                  PDO::PARAM_STR);
        $stmt->bindValue(':limit',        self::MAX_RESULTS_PER_GROUP, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Tìm Member theo tên hoặc email trong Workspace.
     * Email được mask tại tầng DB để bảo vệ privacy.
     *
     * @param  string $q
     * @param  int    $workspaceId
     * @return array<int, array<string, mixed>>
     */
    private function searchMembers(string $q, int $workspaceId): array
    {
        $likeParam = '%' . $this->escapeLikeWildcards($q) . '%';

        $stmt = $this->db->prepare(
            "SELECT u.name,
                    -- Mask email: nguyen.van.a@gmail.com → n***@gmail.com
                    CONCAT(
                        LEFT(SUBSTRING_INDEX(u.email, '@', 1), 1),
                        '***@',
                        SUBSTRING_INDEX(u.email, '@', -1)
                    ) AS email,
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

        $stmt->bindValue(':workspace_id', $workspaceId,                PDO::PARAM_INT);
        $stmt->bindValue(':q_name',       $likeParam,                  PDO::PARAM_STR);
        $stmt->bindValue(':q_email',      $likeParam,                  PDO::PARAM_STR);
        $stmt->bindValue(':limit',        self::MAX_RESULTS_PER_GROUP, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Escape ký tự đặc biệt trong LIKE để tránh kết quả sai do wildcard.
     *
     * @param  string $value
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