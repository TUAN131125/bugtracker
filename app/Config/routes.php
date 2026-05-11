<?php

declare(strict_types=1);

/**
 * routes.php – Application Route Definitions
 *
 * File này là nguồn duy nhất định nghĩa URL → Controller mapping.
 *
 * QUY ƯỚC CHO TEAM:
 *   - Dev 1: sở hữu file này, tạo structure và Auth routes
 *   - Dev 2: THÊM routes business vào đúng section, KHÔNG xóa/sửa routes của Dev 1
 *   - Dev 3: KHÔNG sửa file này
 *
 * NAMING CONVENTION (RESTful):
 *   GET    /resources          → index()   – danh sách
 *   GET    /resources/create   → create()  – form tạo mới
 *   POST   /resources          → store()   – lưu mới
 *   GET    /resources/{id}     → show()    – xem chi tiết
 *   GET    /resources/{id}/edit → edit()   – form chỉnh sửa
 *   PUT    /resources/{id}     → update()  – lưu chỉnh sửa
 *   DELETE /resources/{id}     → destroy() – xóa
 *
 * @var \App\Core\Router $router  Được inject từ index.php
 * @see TDD Backend v1.0.0 – Phần 3.2, Phần 3.3
 * @see Task Assignment v1.0.0 – D1-010
 */

use App\Controllers\Auth\LoginController;
use App\Controllers\Auth\RegisterController;
use App\Controllers\Auth\PasswordResetController;
use App\Controllers\Workspace\WorkspaceController;
use App\Controllers\Workspace\MemberController;
use App\Controllers\Workspace\InvitationController;
use App\Controllers\Project\ProjectController;
use App\Controllers\Project\MilestoneController;
use App\Controllers\Issue\IssueController;
use App\Controllers\Issue\CommentController;
use App\Controllers\Issue\AttachmentController;
use App\Controllers\Dashboard\DashboardController;
use App\Controllers\Admin\EmailQueueController;
use App\Controllers\Api\NotificationApiController;
use App\Controllers\Api\SearchApiController;

// ----------------------------------------------------------------
// Middleware Groups – shorthand cho các nhóm hay dùng
// ----------------------------------------------------------------
$router->middlewareGroup('guest_only', []);         // Trang public (login, register)
$router->middlewareGroup('authenticated', [         // Mọi trang cần đăng nhập
    'auth',
    'onboarding',
    'workspace',
]);

// ================================================================
// SECTION 1: PUBLIC ROUTES (Không cần đăng nhập)
// Dev 1 – D1-013, D1-014, D1-020
// ================================================================

// --- Trang chủ → redirect về login hoặc dashboard ---
$router->get('/', [LoginController::class, 'index']);

// --- Authentication ---
$router->get('/login',    [LoginController::class, 'showForm']);
$router->post('/login',   [LoginController::class, 'login']);
$router->post('/logout',  [LoginController::class, 'logout'], ['auth']);

$router->get('/register', [RegisterController::class, 'showForm']);
$router->post('/register',[RegisterController::class, 'store']);

// Email verification
$router->get('/verify-email/{token}', [RegisterController::class, 'verifyEmail']);
$router->post('/resend-verification', [RegisterController::class, 'resendVerification']);

// Password reset
$router->get('/forgot-password',        [PasswordResetController::class, 'showForgotForm']);
$router->post('/forgot-password',       [PasswordResetController::class, 'sendResetLink']);
$router->get('/reset-password/{token}', [PasswordResetController::class, 'showResetForm']);
$router->post('/reset-password',        [PasswordResetController::class, 'resetPassword']);

// --- Workspace Invitation (public – nhận từ email) ---
// Dev 2 sẽ implement InvitationController (D2-022)
$router->get('/invite/{token}', [InvitationController::class, 'accept']);

// ================================================================
// SECTION 2: ONBOARDING (Cần đăng nhập, chưa có Workspace)
// Dev 2 – D2-006, D2-007
// ================================================================

$router->get('/onboarding',         [WorkspaceController::class, 'onboarding'],        ['auth']);
$router->get('/workspace/create',   [WorkspaceController::class, 'create'],             ['auth']);
$router->post('/workspace/create',  [WorkspaceController::class, 'store'],              ['auth']);

// ================================================================
// SECTION 3: AUTHENTICATED ROUTES
// Tất cả routes bên dưới đều yêu cầu: auth + onboarding + workspace
// ================================================================

// ----------------------------------------------------------------
// Dashboard – Dev 1 (D1-028, Ngày 6)
// ----------------------------------------------------------------
$router->get('/dashboard', [DashboardController::class, 'index'], ['authenticated']);

// ----------------------------------------------------------------
// Workspace Management – Dev 2
// ----------------------------------------------------------------
$router->group('/workspace', ['authenticated'], function ($r) {
    $r->get('/settings',            [WorkspaceController::class, 'settings']);
    $r->put('/settings',            [WorkspaceController::class, 'update']);
    $r->post('/switch/{slug}',      [WorkspaceController::class, 'switchTo']);
    $r->delete('/delete',           [WorkspaceController::class, 'destroy'],    ['rbac:owner']);

    // Members – Dev 2 (D2-021)
    $r->get('/members',             [MemberController::class, 'index']);
    $r->put('/members/{id}/role',   [MemberController::class, 'updateRole'],    ['rbac:owner']);
    $r->delete('/members/{id}',     [MemberController::class, 'remove'],        ['rbac:admin']);

    // Invitations – Dev 2 (D2-022)
    $r->post('/invite',             [InvitationController::class, 'invite'],    ['rbac:admin']);
    $r->post('/invite/{id}/resend', [InvitationController::class, 'resend'],    ['rbac:admin']);
    $r->delete('/invite/{id}',      [InvitationController::class, 'revoke'],    ['rbac:admin']);
});

// ----------------------------------------------------------------
// Projects – Dev 2 (D2-009, D2-011)
// ----------------------------------------------------------------
$router->group('/projects', ['authenticated'], function ($r) {
    $r->get('/',                [ProjectController::class, 'index']);
    $r->get('/create',          [ProjectController::class, 'create'],   ['rbac:admin']);
    $r->post('/',               [ProjectController::class, 'store'],    ['rbac:admin']);
    $r->get('/{key}',           [ProjectController::class, 'show']);
    $r->get('/{key}/edit',      [ProjectController::class, 'edit'],     ['rbac:admin']);
    $r->put('/{key}',           [ProjectController::class, 'update'],   ['rbac:admin']);
    $r->post('/{key}/archive',  [ProjectController::class, 'archive'],  ['rbac:admin']);

    // Milestones – Dev 2 (D2-010)
    $r->get('/{key}/milestones',        [MilestoneController::class, 'index']);
    $r->post('/{key}/milestones',       [MilestoneController::class, 'store'],   ['rbac:admin']);
    $r->put('/{key}/milestones/{id}',   [MilestoneController::class, 'update'],  ['rbac:admin']);
    $r->delete('/{key}/milestones/{id}',[MilestoneController::class, 'destroy'], ['rbac:admin']);
});

// ----------------------------------------------------------------
// Issues – Dev 2 (D2-013, D2-015)
// ----------------------------------------------------------------
$router->group('/issues', ['authenticated'], function ($r) {
    $r->get('/',                [IssueController::class, 'index']);
    $r->get('/create',          [IssueController::class, 'create']);
    $r->post('/',               [IssueController::class, 'store']);
    $r->get('/{issueKey}',      [IssueController::class, 'show']);
    $r->get('/{issueKey}/edit', [IssueController::class, 'edit']);
    $r->put('/{issueKey}',      [IssueController::class, 'update']);
    $r->delete('/{issueKey}',   [IssueController::class, 'destroy'],        ['rbac:admin']);

    // AJAX endpoints
    $r->post('/{issueKey}/status',   [IssueController::class, 'updateStatus']);
    $r->post('/{issueKey}/assign',   [IssueController::class, 'assign']);
    $r->post('/{issueKey}/links',    [IssueController::class, 'addLink']);
    $r->delete('/{issueKey}/links/{linkId}', [IssueController::class, 'removeLink']);

    // Comments – Dev 2 (D2-019)
    $r->post('/{issueKey}/comments',         [CommentController::class, 'store']);
    $r->put('/{issueKey}/comments/{id}',     [CommentController::class, 'update']);
    $r->delete('/{issueKey}/comments/{id}',  [CommentController::class, 'destroy']);
    $r->post('/{issueKey}/comments/{id}/reaction', [CommentController::class, 'reaction']);

    // Attachments – Dev 1 (D1-027)
    $r->post('/{issueKey}/attachments',       [AttachmentController::class, 'store']);
    $r->delete('/{issueKey}/attachments/{id}',[AttachmentController::class, 'destroy']);
});

// File serve – Dev 1 (D1-027)
// Không nằm trong group /issues vì URL format khác
$router->get(
    '/files/{workspaceId}/{issueId}/{filename}',
    [AttachmentController::class, 'serve'],
    ['authenticated']
);

// ----------------------------------------------------------------
// Admin Panel – Dev 1 (D1-024, Ngày 4)
// Chỉ Owner mới truy cập được
// ----------------------------------------------------------------
$router->group('/admin', ['authenticated', 'rbac:owner'], function ($r) {
    $r->get('/email-queue',         [EmailQueueController::class, 'index']);
    $r->post('/email-queue/{id}/retry', [EmailQueueController::class, 'retry']);
    $r->post('/email-queue/retry-all',  [EmailQueueController::class, 'retryAll']);
});

// ================================================================
// SECTION 4: API ENDPOINTS (JSON – cho AJAX)
// ================================================================

$router->group('/api', ['authenticated'], function ($r) {
    // Notifications – Dev 2 (D2-023, Ngày 6)
    $r->get('/notifications',               [NotificationApiController::class, 'index']);
    $r->post('/notifications/{id}/read',    [NotificationApiController::class, 'markRead']);
    $r->post('/notifications/read-all',     [NotificationApiController::class, 'markAllRead']);

    // Search – Dev 1 (D1-029, Ngày 6)
    $r->get('/search', [SearchApiController::class, 'search']);
});