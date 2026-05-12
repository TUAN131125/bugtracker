<?php

namespace App\Controllers\Workspace;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Helpers\Csrf;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Models\WorkspaceInvitation;
use App\Models\User;
use App\Services\InvitationService;
use App\Services\RbacService;

class InvitationController
{
    private InvitationService  $invitationService;
    private Workspace          $workspaceModel;
    private WorkspaceMember    $memberModel;
    private RbacService        $rbac;

    public function __construct()
    {
        $this->invitationService = new InvitationService();
        $this->workspaceModel    = new Workspace();
        $this->memberModel       = new WorkspaceMember();
        $this->rbac              = new RbacService();
    }

    // POST /ws/{slug}/invite  (AJAX)
    public function invite(string $slug): void
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

        if (!$this->rbac->canManageMembers($actorId, $workspaceId)) {
            Response::json(['success' => false, 'message' => 'Bạn không có quyền mời thành viên.'], 403);
            exit();
        }

        $email = trim(Request::post('email') ?? '');
        $role  = Request::post('role') ?? 'member';

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Response::json(['success' => false, 'message' => 'Email không hợp lệ.'], 400);
            exit();
        }

        // Admin chỉ mời member/guest, Owner mới mời được admin
        $actorRole    = $this->memberModel->getRole($workspaceId, $actorId);
        $allowedRoles = ['member', 'guest'];
        if ($actorRole === 'owner') {
            $allowedRoles[] = 'admin';
        }

        if (!in_array($role, $allowedRoles, true)) {
            Response::json(['success' => false, 'message' => 'Vai trò không hợp lệ.'], 400);
            exit();
        }

        try {
            $result = $this->invitationService->invite($workspaceId, $email, $role, $actorId);
            Response::json(['success' => true, 'message' => $result['message']]);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 400);
        }
        exit();
    }

    // GET /invite/{token}
    public function accept(string $token): void
    {
        $invitationModel = new WorkspaceInvitation();
        $invitation      = $invitationModel->findByToken($token);

        if (!$invitation
            || $invitation['status'] !== 'pending'
            || strtotime($invitation['expires_at']) < time()
        ) {
            Session::set('flash_error', 'Lời mời không hợp lệ hoặc đã hết hạn.');
            Response::redirect('/login');
            exit();
        }

        $userId = Session::get('user_id');

        // Chưa đăng nhập
        if (!$userId) {
            $userModel    = new User();
            $existingUser = $userModel->findByEmail($invitation['email']);

            if ($existingUser) {
                Session::set('redirect_after_login', '/invite/' . $token);
                Session::set('flash_info', 'Vui lòng đăng nhập để chấp nhận lời mời.');
                Response::redirect('/login');
            } else {
                Session::set('invite_token', $token);
                Session::set('invite_email', $invitation['email']);
                Response::redirect('/register?invite_token=' . urlencode($token));
            }
            exit();
        }

        // Đã đăng nhập – kiểm tra email khớp
        $userModel = new User();
        $user      = $userModel->findById($userId);

        if (strtolower($user['email']) !== strtolower($invitation['email'])) {
            Session::set('flash_error', 'Lời mời này không dành cho tài khoản của bạn.');
            Response::redirect('/dashboard');
            exit();
        }

        // Đã là member rồi?
        if ($this->memberModel->isMember($invitation['workspace_id'], $userId)) {
            Session::set('flash_info', 'Bạn đã là thành viên của Workspace này.');
            $workspace = $this->workspaceModel->findById($invitation['workspace_id']);
            Response::redirect('/ws/' . $workspace['slug'] . '/dashboard');
            exit();
        }

        // Accept
        try {
            $invitationModel->accept($token);
            $this->memberModel->add($invitation['workspace_id'], $userId, $invitation['role']);

            Session::set('active_workspace_id', $invitation['workspace_id']);
            $workspace = $this->workspaceModel->findById($invitation['workspace_id']);
            Session::set('flash_success', 'Chào mừng bạn đến với Workspace ' . $workspace['name'] . '!');
            Response::redirect('/ws/' . $workspace['slug'] . '/dashboard');
        } catch (\Exception $e) {
            Session::set('flash_error', 'Đã xảy ra lỗi: ' . $e->getMessage());
            Response::redirect('/dashboard');
        }
        exit();
    }

    // GET /invite/{token}/decline
    public function decline(string $token): void
    {
        $invitationModel = new WorkspaceInvitation();
        $invitation      = $invitationModel->findByToken($token);

        if (!$invitation || $invitation['status'] !== 'pending') {
            Session::set('flash_error', 'Lời mời không hợp lệ.');
            Response::redirect('/dashboard');
            exit();
        }

        $db   = \App\Core\Database::getInstance();
        $stmt = $db->prepare(
            "UPDATE workspace_invitations SET status = 'declined', updated_at = NOW() WHERE token = ?"
        );
        $stmt->execute([$token]);

        Session::set('flash_info', 'Bạn đã từ chối lời mời.');
        Response::redirect('/dashboard');
        exit();
    }

    // POST /ws/{slug}/invitations/{invitationId}/resend  (AJAX)
    public function resend(string $slug, int $invitationId): void
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

        if (!$this->rbac->canManageMembers($actorId, $workspace['id'])) {
            Response::json(['success' => false, 'message' => 'Bạn không có quyền.'], 403);
            exit();
        }

        try {
            $invitationModel = new WorkspaceInvitation();
            $invitationModel->resend($invitationId);
            Response::json(['success' => true, 'message' => 'Đã gửi lại email mời.']);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 400);
        }
        exit();
    }
}