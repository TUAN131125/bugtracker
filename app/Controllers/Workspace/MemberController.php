<?php

namespace App\Controllers\Workspace;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Helpers\Csrf;
use App\Models\WorkspaceMember;
use App\Models\Workspace;
use App\Services\RbacService;

class MemberController
{
    private WorkspaceMember $memberModel;
    private Workspace $workspaceModel;
    private RbacService $rbac;

    public function __construct()
    {
        $this->memberModel    = new WorkspaceMember();
        $this->workspaceModel = new Workspace();
        $this->rbac           = new RbacService();
    }

    // GET /ws/{slug}/members
    public function index(string $slug): void
    {
        $userId    = Session::get('user_id');
        $workspace = $this->workspaceModel->findBySlug($slug);

        if (!$workspace) {
            Response::redirect('/onboarding');
            exit();
        }

        $workspaceId = $workspace['id'];

        if (!$this->memberModel->isMember($workspaceId, $userId)) {
            Response::redirect('/onboarding');
            exit();
        }

        $members         = $this->memberModel->listMembers($workspaceId);
        $currentUserRole = $this->memberModel->getRole($workspaceId, $userId);

        $invitationModel    = new \App\Models\WorkspaceInvitation();
        $pendingInvitations = $invitationModel->listPending($workspaceId);

        Response::view('members/index', [
            'workspace'           => $workspace,
            'members'             => $members,
            'pending_invitations' => $pendingInvitations,
            'current_user_id'     => $userId,
            'current_user_role'   => $currentUserRole,
        ]);
    }

    // POST /ws/{slug}/members/{memberId}/role  (AJAX)
    public function updateRole(string $slug, int $memberId): void
    {
        $token = Request::post('csrf_token') ?? '';
        if (!Csrf::validateToken($token)) {
            Response::json(['success' => false, 'message' => 'Invalid CSRF token.'], 403);
            exit();
        }

        $actorId   = Session::get('user_id');
        $workspace = $this->workspaceModel->findBySlug($slug);

        if (!$workspace) {
            Response::json(['success' => false, 'message' => 'Workspace không tồn tại.'], 404);
            exit();
        }

        $workspaceId = $workspace['id'];
        $actorRole   = $this->memberModel->getRole($workspaceId, $actorId);

        // Tìm target member
        $members      = $this->memberModel->listMembers($workspaceId);
        $targetMember = null;
        foreach ($members as $m) {
            if ((int)$m['id'] === $memberId) {
                $targetMember = $m;
                break;
            }
        }

        if (!$targetMember) {
            Response::json(['success' => false, 'message' => 'Thành viên không tồn tại.'], 404);
            exit();
        }

        $newRole      = Request::post('role') ?? '';
        $allowedRoles = ['admin', 'member', 'guest'];

        if (!in_array($newRole, $allowedRoles, true)) {
            Response::json(['success' => false, 'message' => 'Vai trò không hợp lệ.'], 400);
            exit();
        }

        // Không được đổi role của Owner
        if ($targetMember['role'] === 'owner') {
            Response::json(['success' => false, 'message' => 'Không thể thay đổi vai trò của Owner.'], 403);
            exit();
        }

        // Chỉ Owner mới set role=admin
        if ($newRole === 'admin' && $actorRole !== 'owner') {
            Response::json(['success' => false, 'message' => 'Chỉ Owner mới có thể phong Admin.'], 403);
            exit();
        }

        // Actor phải là owner hoặc admin
        if (!in_array($actorRole, ['owner', 'admin'], true)) {
            Response::json(['success' => false, 'message' => 'Bạn không có quyền thực hiện thao tác này.'], 403);
            exit();
        }

        $this->memberModel->updateRole($workspaceId, $targetMember['user_id'], $newRole);

        Response::json([
            'success'  => true,
            'message'  => 'Cập nhật vai trò thành công.',
            'new_role' => $newRole,
        ]);
    }

    // POST /ws/{slug}/members/{memberId}/remove  (AJAX)
    public function remove(string $slug, int $memberId): void
    {
        $token = Request::post('csrf_token') ?? '';
        if (!Csrf::validateToken($token)) {
            Response::json(['success' => false, 'message' => 'Invalid CSRF token.'], 403);
            exit();
        }

        $actorId   = Session::get('user_id');
        $workspace = $this->workspaceModel->findBySlug($slug);

        if (!$workspace) {
            Response::json(['success' => false, 'message' => 'Workspace không tồn tại.'], 404);
            exit();
        }

        $workspaceId = $workspace['id'];
        $actorRole   = $this->memberModel->getRole($workspaceId, $actorId);

        if (!in_array($actorRole, ['owner', 'admin'], true)) {
            Response::json(['success' => false, 'message' => 'Bạn không có quyền xóa thành viên.'], 403);
            exit();
        }

        $members      = $this->memberModel->listMembers($workspaceId);
        $targetMember = null;
        foreach ($members as $m) {
            if ((int)$m['id'] === $memberId) {
                $targetMember = $m;
                break;
            }
        }

        if (!$targetMember) {
            Response::json(['success' => false, 'message' => 'Thành viên không tồn tại.'], 404);
            exit();
        }

        // Không được kick Owner
        if ($targetMember['role'] === 'owner') {
            Response::json(['success' => false, 'message' => 'Không thể xóa Owner khỏi Workspace.'], 403);
            exit();
        }

        // Admin không kick được Admin khác
        if ($actorRole === 'admin' && $targetMember['role'] === 'admin') {
            Response::json(['success' => false, 'message' => 'Admin không thể xóa Admin khác.'], 403);
            exit();
        }

        $this->memberModel->remove($workspaceId, $targetMember['user_id']);

        Response::json(['success' => true, 'message' => 'Đã xóa thành viên khỏi Workspace.']);
    }
}