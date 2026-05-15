<?php

declare(strict_types=1);

/**
 * routes.php – Application Route Definitions
 *
 * File này là nguồn duy nhất định nghĩa URL → Controller mapping.
 *
 * ┌─────────────────────────────────────────────────────────────────┐
 * │  QUY ƯỚC CHO TEAM                                              │
 * │  Dev 1 : sở hữu file này, tạo structure và Auth/Core routes    │
 * │  Dev 2 : THÊM routes vào đúng section có nhãn [DEV 2]          │
 * │          KHÔNG xóa / sửa routes của Dev 1                      │
 * │  Dev 3 : KHÔNG sửa file này                                    │
 * └─────────────────────────────────────────────────────────────────┘
 *
 * NAMING CONVENTION (RESTful):
 *   GET    /resources            → index()   – danh sách
 *   GET    /resources/create     → create()  – form tạo mới
 *   POST   /resources            → store()   – lưu mới
 *   GET    /resources/{id}       → show()    – xem chi tiết
 *   GET    /resources/{id}/edit  → edit()    – form chỉnh sửa
 *   PUT    /resources/{id}       → update()  – lưu chỉnh sửa
 *   DELETE /resources/{id}       → destroy() – xóa
 *
 * MIDDLEWARE KÝ HIỆU:
 *   'auth'            → AuthMiddleware       (session user_id hợp lệ)
 *   'onboarding'      → OnboardingMiddleware (đã có ít nhất 1 workspace)
 *   'workspace'       → WorkspaceMiddleware  (active_workspace_id hợp lệ)
 *   'rbac:owner'      → RbacMiddleware       (chỉ Owner)
 *   'rbac:admin'      → RbacMiddleware       (Owner hoặc Admin)
 *   'rbac:member'     → RbacMiddleware       (Owner, Admin hoặc Member)
 *   'authenticated'   → group shorthand: auth + onboarding + workspace
 *
 * @var \App\Core\Router $router  Được inject từ public_html/index.php
 * @see TDD Backend v1.0.0  – Phần 3.2 (cấu trúc thư mục), Phần 3.3 (request lifecycle)
 * @see Task Assignment     – D1-010 (Dev 1 tạo file), D2-* (Dev 2 thêm routes)
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
use App\Controllers\Public\LandingController;


// ----------------------------------------------------------------
// Middleware Groups – shorthand cho các nhóm hay dùng
// ----------------------------------------------------------------

/**
 * 'authenticated': Bộ 3 middleware bắt buộc cho mọi trang sau login.
 * Thứ tự quan trọng: Auth → Onboarding → Workspace (theo TDD Phần 3.4).
 */
$router->middlewareGroup('authenticated', [
    'auth',        // AuthMiddleware       – kiểm tra session user_id
    'onboarding',  // OnboardingMiddleware – redirect /onboarding nếu chưa có WS
    'workspace',   // WorkspaceMiddleware  – validate active_workspace_id vs DB
]);

// ================================================================
// SECTION 1: PUBLIC ROUTES (Không cần đăng nhập)
// Chủ sở hữu: Dev 1 – D1-013, D1-014, D1-020
// ================================================================
// --- Landing Page ---
$router->get('/',       [LandingController::class, 'index']);
$router->get('/terms',  [LandingController::class, 'terms']);

// ----------------------------------------------------------------
// Authentication – Dev 1
// ----------------------------------------------------------------
$router->get('/login',  [LoginController::class, 'showForm']);
$router->post('/login', [LoginController::class, 'login']);

// Logout cần 'auth' để đảm bảo session tồn tại trước khi destroy
$router->post('/logout', [LoginController::class, 'logout'], ['auth']);

$router->get('/register',  [RegisterController::class, 'showForm']);
$router->post('/register', [RegisterController::class, 'store']);

// ----------------------------------------------------------------
// Email Verification – Dev 1 (D1-013, D1-025)
// ----------------------------------------------------------------

// GET: user bấm link trong email → verifyEmail($token)
$router->get('/verify-email/{token}', [RegisterController::class, 'verifyEmail']);

// POST: gửi lại email xác minh (form ở trang login)
// Không cần 'auth' vì user có thể chưa đăng nhập khi cần gửi lại
$router->post('/resend-verification', [RegisterController::class, 'resendVerification']);

// ----------------------------------------------------------------
// Password Reset – Dev 1 (D1-020)
// ----------------------------------------------------------------
$router->get('/forgot-password',         [PasswordResetController::class, 'showForgotForm']);
$router->post('/forgot-password',        [PasswordResetController::class, 'sendResetLink']);
$router->get('/reset-password/{token}',  [PasswordResetController::class, 'showResetForm']);
$router->post('/reset-password',         [PasswordResetController::class, 'resetPassword']);

// ----------------------------------------------------------------
// Workspace Invitation – Accept/Decline qua link email (public)
// Dev 2 implement InvitationController (D2-022)
// Public vì người nhận có thể chưa đăng nhập khi bấm link
// ----------------------------------------------------------------
$router->get('/invite/{token}',        [InvitationController::class, 'accept']);
$router->get('/invite/{token}/decline',[InvitationController::class, 'decline']);

// ================================================================
// SECTION 2: ONBOARDING
// Cần đăng nhập, nhưng CHƯA qua OnboardingMiddleware & WorkspaceMiddleware
// vì đây chính là trang để hoàn thành onboarding.
// Chủ sở hữu: Dev 2 – D2-006, D2-007
// ================================================================
$router->get('/onboarding',        [WorkspaceController::class, 'onboarding'],  ['auth']);
$router->get('/workspace/create',  [WorkspaceController::class, 'create'],      ['auth']);
$router->post('/workspace/create', [WorkspaceController::class, 'store'],       ['auth']);

// ================================================================
// SECTION 3: AUTHENTICATED ROUTES
// Tất cả routes bên dưới đều yêu cầu middleware group 'authenticated'
// (auth + onboarding + workspace) – TDD Phần 3.4
// ================================================================

// ----------------------------------------------------------------
// Dashboard – Dev 1 (D1-028, Ngày 6)
// ----------------------------------------------------------------
$router->get('/dashboard', [DashboardController::class, 'index'], ['authenticated']);

// ----------------------------------------------------------------
// Workspace Management
// [DEV 2] – D2-005, D2-006, D2-007, D2-021, D2-022
// ----------------------------------------------------------------
$router->group('/workspace', ['authenticated'], function ($r) {

    // Cài đặt Workspace – chỉ Owner/Admin (SRS Phần 1.3)
    $r->get('/settings',  [WorkspaceController::class, 'settings']);
    $r->put('/settings',  [WorkspaceController::class, 'update'],  ['rbac:admin']);

    // Chuyển đổi Workspace active (SRS Phần 2.4 – switchWorkspace validation)
    $r->post('/switch/{slug}', [WorkspaceController::class, 'switchTo']);

    // Xóa Workspace – chỉ Owner (SRS Phần 1.2.1)
    $r->delete('/delete', [WorkspaceController::class, 'destroy'], ['rbac:owner']);

    // ── Members ──────────────────────────────────────────────────
    // [DEV 2] – D2-021
    $r->get('/members',               [MemberController::class, 'index']);
    // Đổi role: chỉ Owner mới promote lên Admin (SRS Phần 1.2.2)
    $r->put('/members/{id}/role',     [MemberController::class, 'updateRole'], ['rbac:owner']);
    // Kick member: Owner/Admin kick được, nhưng không kick được Owner (SRS Phần 1.3)
    $r->delete('/members/{id}',       [MemberController::class, 'remove'],     ['rbac:admin']);

    // ── Invitations ───────────────────────────────────────────────
    // [DEV 2] – D2-022
    // Gửi lời mời: Owner/Admin (SRS UC-008)
    $r->post('/invite',               [InvitationController::class, 'invite'],  ['rbac:admin']);
    // Gửi lại lời mời, gia hạn 7 ngày (TDD Phần 1.5 – kịch bản 4)
    $r->post('/invite/{id}/resend',   [InvitationController::class, 'resend'],  ['rbac:admin']);
    // Thu hồi lời mời đang pending
    $r->delete('/invite/{id}',        [InvitationController::class, 'revoke'],  ['rbac:admin']);
});

// ----------------------------------------------------------------
// Projects & Milestones
// [DEV 2] – D2-009, D2-010, D2-011
// ----------------------------------------------------------------
$router->group('/projects', ['authenticated'], function ($r) {

    $r->get('/',               [ProjectController::class, 'index']);
    // Tạo Project: chỉ Owner/Admin (SRS Phần 1.3)
    $r->get('/create',         [ProjectController::class, 'create'],  ['rbac:admin']);
    $r->post('/',              [ProjectController::class, 'store'],   ['rbac:admin']);
    $r->get('/{key}',          [ProjectController::class, 'show']);
    $r->get('/{key}/edit',     [ProjectController::class, 'edit'],   ['rbac:admin']);
    $r->put('/{key}',          [ProjectController::class, 'update'], ['rbac:admin']);
    // Archive: set project.status = archived, Issues → read-only (SRS Phần 3.2.2)
    $r->post('/{key}/archive', [ProjectController::class, 'archive'],['rbac:admin']);

    // ── Milestones (nested dưới Project) ─────────────────────────
    $r->get('/{key}/milestones',         [MilestoneController::class, 'index']);
    $r->post('/{key}/milestones',        [MilestoneController::class, 'store'],   ['rbac:admin']);
    $r->put('/{key}/milestones/{id}',    [MilestoneController::class, 'update'],  ['rbac:admin']);
    $r->delete('/{key}/milestones/{id}', [MilestoneController::class, 'destroy'], ['rbac:admin']);
});

// ----------------------------------------------------------------
// Issues & Sub-resources (Comments, Attachments)
// [DEV 2] – D2-013, D2-015, D2-017, D2-019
// ----------------------------------------------------------------
$router->group('/issues', ['authenticated'], function ($r) {

    // ── CRUD Issue ────────────────────────────────────────────────
    $r->get('/',                 [IssueController::class, 'index']);
    $r->get('/create',           [IssueController::class, 'create']);
    $r->post('/',                [IssueController::class, 'store']);
    $r->get('/{issueKey}',       [IssueController::class, 'show']);
    $r->get('/{issueKey}/edit',  [IssueController::class, 'edit']);
    $r->put('/{issueKey}',       [IssueController::class, 'update']);
    // Xóa Issue: chỉ Owner/Admin (SRS Phần 1.3)
    $r->delete('/{issueKey}',    [IssueController::class, 'destroy'], ['rbac:admin']);

    // ── AJAX – State Machine & Assignment ────────────────────────
    // POST vì thay đổi state (TDD Phần 4.3 – updateStatus là AJAX endpoint)
    $r->post('/{issueKey}/status', [IssueController::class, 'updateStatus']);
    $r->post('/{issueKey}/assign', [IssueController::class, 'assign']);

    // ── Issue Links (SRS Phần 3.4.8) ─────────────────────────────
    $r->post('/{issueKey}/links',             [IssueController::class, 'addLink']);
    $r->delete('/{issueKey}/links/{linkId}',  [IssueController::class, 'removeLink']);

    // ── Comments (SRS UC-026, UC-027) ────────────────────────────
    // [DEV 2] – D2-019 (CommentController)
    $r->post('/{issueKey}/comments',               [CommentController::class, 'store']);
    $r->put('/{issueKey}/comments/{id}',           [CommentController::class, 'update']);
    $r->delete('/{issueKey}/comments/{id}',        [CommentController::class, 'destroy']);
    // Reaction AJAX – toggle emoji (SRS Phần 3.4.1)
    $r->post('/{issueKey}/comments/{id}/reaction', [CommentController::class, 'reaction']);

    // ── Attachments (SRS UC-029) ──────────────────────────────────
    // Dev 1 – D1-026, D1-027
    $r->post('/{issueKey}/attachments',        [AttachmentController::class, 'store']);
    $r->delete('/{issueKey}/attachments/{id}', [AttachmentController::class, 'destroy']);
});

// ----------------------------------------------------------------
// File Serve – Dev 1 (D1-027)
// Nằm ngoài group /issues vì URL format khác (/files/... không phải /issues/...)
// PHP script trung gian để kiểm tra quyền trước khi readfile()
// File lưu ngoài webroot – KHÔNG truy cập URL trực tiếp được (TDD Phần 3.1)
// ----------------------------------------------------------------
$router->get(
    '/files/{workspaceId}/{issueId}/{filename}',
    [AttachmentController::class, 'serve'],
    ['authenticated']
);

// ================================================================
// SECTION 4: ADMIN PANEL
// Chủ sở hữu: Dev 1 (D1-024, Ngày 4)
//
// WHY 'rbac:admin' thay vì 'rbac:owner':
//   Task D1-024 ghi rõ "check role=owner hoặc admin".
//   Admin cũng cần xem và retry email queue trong vận hành hàng ngày.
//   Chỉ những thao tác nguy hiểm (xóa WS, chuyển Owner) mới cần 'rbac:owner'.
// ================================================================
$router->group('/admin', ['authenticated', 'rbac:admin'], function ($r) {

    // Xem danh sách email thất bại (phân trang 20/trang)
    $r->get('/email-queue', [EmailQueueController::class, 'index']);

    // Retry đơn lẻ một email theo ID
    $r->post('/email-queue/{id}/retry', [EmailQueueController::class, 'retry']);

    // Retry hàng loạt (batch 10 cái/lần – tránh timeout InfinityFree)
    $r->post('/email-queue/retry-all',  [EmailQueueController::class, 'retryAll']);

    // Dọn dẹp email lỗi cũ hơn 7 ngày (LIMIT 200/lần – TDD Phần 2.4)
    // WHY có route này: thay thế Cronjob trên InfinityFree (không có Cronjob)
    $r->post('/email-queue/cleanup',    [EmailQueueController::class, 'cleanup']);
});

// ================================================================
// SECTION 5: API ENDPOINTS (JSON – dành cho AJAX calls từ JS)
// Tất cả trả về JSON, không render HTML.
// Dev 3 gọi qua api.js wrapper (ViewLayer Guide Phần 8.2).
// ================================================================
$router->group('/api', ['authenticated'], function ($r) {

    // ── Notifications – Dev 2 (D2-023, Ngày 6) ───────────────────
    // GET: Dev 3 poll mỗi 60s để cập nhật badge (ViewLayer Guide Phần 7.1)
    $r->get('/notifications',              [NotificationApiController::class, 'index']);
    $r->post('/notifications/{id}/read',   [NotificationApiController::class, 'markRead']);
    $r->post('/notifications/read-all',    [NotificationApiController::class, 'markAllRead']);

    // ── Search – Dev 1 (D1-029, Ngày 6) ──────────────────────────
    // GET /api/search?q={term}
    // Debounce 300ms phía client (global-search.js) – TDD Phần D1-029
    // Giới hạn 20 kết quả, q tối thiểu 2 ký tự
    $r->get('/search', [SearchApiController::class, 'search']);
});