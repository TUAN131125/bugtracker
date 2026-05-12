<?php

namespace App\Controllers\Api;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

class SearchApiController
{
    private \PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // GET /api/search?q={term}&workspace_id={id}
    public function search(): void
    {
        $workspaceId = Session::get('active_workspace_id');
        $term        = Request::get('q');

        // Validate
        if (!$term || strlen(trim($term)) < 2) {
            Response::json([
                'success' => false,
                'message' => 'Từ khóa phải có ít nhất 2 ký tự.',
            ], 400);
        }

        $stmt = $this->db->prepare("
            SELECT issue_key AS id, title, status
            FROM issues
            WHERE workspace_id = ?
              AND title LIKE ?
              AND deleted_at IS NULL
            ORDER BY updated_at DESC
            LIMIT 20
        ");
        $stmt->execute([$workspaceId, '%' . $term . '%']);
        $results = $stmt->fetchAll();

        Response::json([
            'success' => true,
            'data'    => $results,
        ]);
    }
}
