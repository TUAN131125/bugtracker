<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

/**
 * RbacMiddleware – Kiểm tra Role-Based Access Control
 *
 * Chạy cuối cùng trong middleware chain.
 * Đọc role từ session (đã được WorkspaceMiddleware cache)
 * và so sánh với role tối thiểu yêu cầu của route.
 *
 * Cách đăng ký trong routes.php:
 *   'rbac:admin'  → chỉ admin và owner được vào
 *   'rbac:member' → member, admin, owner được vào
 *   'rbac:owner'  → chỉ owner được vào
 *
 * @package App\Middleware
 * @version 1.0.0
 * @see     TDD Backend v1.0.0 – Phần 3.4
 * @see     SRS v1.0.0 – Phần 1.3 (RBAC Matrix)
 * @see     Task Assignment v1.0.0 – D1-011
 */
class RbacMiddleware
{
    /**
     * Thứ tự phân cấp role — số càng cao quyền càng lớn.
     * Dùng để so sánh: role hiện tại >= role yêu cầu.
     *
     * @var array<string, int>
     */
    private array $roleHierarchy = [
        'guest'  => 1,
        'member' => 2,
        'admin'  => 3,
        'owner'  => 4,
    ];

    /**
     * @param  Request     $request
     * @param  string|null $param   Role tối thiểu yêu cầu.
     *                              VD: 'admin', 'member', 'owner'
     * @return void
     */
    public function handle(Request $request, ?string $param = null): void
    {
        // Không có param → route không yêu cầu role cụ thể
        if ($param === null) {
            return;
        }

        $requiredRole = strtolower($param);
        $currentRole  = strtolower(Session::get('current_role', 'guest'));

        // Kiểm tra role hợp lệ
        if (!isset($this->roleHierarchy[$requiredRole])) {
            error_log("[RbacMiddleware] Invalid required role: '{$requiredRole}'");
            $this->denyAccess($request);
            return;
        }

        $currentLevel  = $this->roleHierarchy[$currentRole]  ?? 0;
        $requiredLevel = $this->roleHierarchy[$requiredRole];

        if ($currentLevel < $requiredLevel) {
            // Ghi log cảnh báo — Dev 1 sẽ replace bằng Logger::warning ở Ngày 3
            error_log(sprintf(
                '[RbacMiddleware] Access denied. User ID: %d | Required: %s | Has: %s | URI: %s',
                Session::getUserId() ?? 0,
                $requiredRole,
                $currentRole,
                $request->uri()
            ));

            $this->denyAccess($request);
        }
    }

    /**
     * Từ chối truy cập — trả về 403 hoặc JSON error cho AJAX.
     *
     * @param  Request $request
     * @return never
     */
    private function denyAccess(Request $request): never
    {
        if ($request->isAjax()) {
            Response::json([
                'success' => false,
                'message' => 'Bạn không có quyền thực hiện hành động này.',
                'code'    => 403,
            ], 403);
        }

        Response::status(403);

        $viewPath = dirname(__DIR__) . '/Views/errors/403.php';
        if (file_exists($viewPath)) {
            include $viewPath;
        } else {
            echo '<h1>403 – Không có quyền truy cập</h1>';
        }

        exit();
    }
}