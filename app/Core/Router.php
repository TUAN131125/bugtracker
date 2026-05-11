<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Router – URL Dispatcher
 *
 * Đăng ký routes và dispatch request đến đúng Controller::method.
 * Hỗ trợ route parameters dạng {param} trong URL pattern.
 * Middleware chain được đăng ký per-route hoặc per-group.
 *
 * Cách dùng trong /app/Config/routes.php:
 *   $router->get('/issues/{id}', [IssueController::class, 'show'], ['auth', 'workspace']);
 *   $router->post('/issues',     [IssueController::class, 'store'], ['auth', 'workspace', 'rbac:member']);
 *
 * @package App\Core
 * @version 1.0.0
 * @see     TDD Backend v1.0.0 – Phần 3.3 (Request Lifecycle)
 * @see     Task Assignment v1.0.0 – D1-006
 */
class Router
{
    /**
     * Danh sách routes đã đăng ký.
     * Cấu trúc mỗi phần tử:
     * [
     *   'method'      => 'GET',
     *   'pattern'     => '/issues/{id}',
     *   'regex'       => '/^\/issues\/([^\/]+)$/i',
     *   'params'      => ['id'],
     *   'handler'     => [IssueController::class, 'show'],
     *   'middlewares' => ['auth', 'workspace'],
     * ]
     *
     * @var array<int, array<string, mixed>>
     */
    private array $routes = [];

    /**
     * Middleware groups dùng chung (shorthand).
     * VD: 'auth' => ['auth', 'workspace', 'onboarding']
     *
     * @var array<string, array<string>>
     */
    private array $middlewareGroups = [];

    /**
     * Prefix URL đang active (dùng khi group routes).
     *
     * @var string
     */
    private string $currentPrefix = '';

    /**
     * Middlewares đang active cho group hiện tại.
     *
     * @var array<string>
     */
    private array $currentGroupMiddlewares = [];

    // ----------------------------------------------------------------
    // Registration Methods
    // ----------------------------------------------------------------

    /**
     * Đăng ký route GET.
     *
     * @param  string                  $pattern     URL pattern. VD: '/issues/{id}'
     * @param  array{0: string, 1: string} $handler [ControllerClass::class, 'methodName']
     * @param  array<string>           $middlewares Danh sách middleware key. VD: ['auth', 'workspace']
     * @return self Trả về $this để hỗ trợ method chaining.
     */
    public function get(string $pattern, array $handler, array $middlewares = []): self
    {
        return $this->addRoute('GET', $pattern, $handler, $middlewares);
    }

    /**
     * Đăng ký route POST.
     *
     * @param  string                      $pattern
     * @param  array{0: string, 1: string} $handler
     * @param  array<string>               $middlewares
     * @return self
     */
    public function post(string $pattern, array $handler, array $middlewares = []): self
    {
        return $this->addRoute('POST', $pattern, $handler, $middlewares);
    }

    /**
     * Đăng ký route PUT.
     *
     * @param  string                      $pattern
     * @param  array{0: string, 1: string} $handler
     * @param  array<string>               $middlewares
     * @return self
     */
    public function put(string $pattern, array $handler, array $middlewares = []): self
    {
        return $this->addRoute('PUT', $pattern, $handler, $middlewares);
    }

    /**
     * Đăng ký route DELETE.
     *
     * @param  string                      $pattern
     * @param  array{0: string, 1: string} $handler
     * @param  array<string>               $middlewares
     * @return self
     */
    public function delete(string $pattern, array $handler, array $middlewares = []): self
    {
        return $this->addRoute('DELETE', $pattern, $handler, $middlewares);
    }

    /**
     * Nhóm các route có chung prefix URL và/hoặc middleware.
     *
     * Ví dụ:
     *   $router->group('/workspace/{slug}', ['auth', 'workspace'], function($r) {
     *       $r->get('/issues',       [IssueController::class, 'index']);
     *       $r->post('/issues',      [IssueController::class, 'store']);
     *       $r->get('/issues/{id}',  [IssueController::class, 'show']);
     *   });
     *
     * @param  string          $prefix      URL prefix cho cả group.
     * @param  array<string>   $middlewares Middleware áp dụng cho toàn group.
     * @param  callable        $callback    Closure nhận Router instance.
     * @return void
     */
    public function group(string $prefix, array $middlewares, callable $callback): void
    {
        // Lưu lại state hiện tại để restore sau khi group kết thúc
        $previousPrefix      = $this->currentPrefix;
        $previousMiddlewares = $this->currentGroupMiddlewares;

        // Cộng dồn prefix và middlewares
        $this->currentPrefix             = $previousPrefix . $prefix;
        $this->currentGroupMiddlewares   = array_merge($previousMiddlewares, $middlewares);

        // Gọi callback để đăng ký routes bên trong group
        $callback($this);

        // Restore state sau khi group kết thúc (hỗ trợ nested group)
        $this->currentPrefix             = $previousPrefix;
        $this->currentGroupMiddlewares   = $previousMiddlewares;
    }

    /**
     * Đăng ký middleware group shorthand.
     * VD: $router->middlewareGroup('authenticated', ['auth', 'workspace', 'onboarding']);
     *
     * @param  string        $name
     * @param  array<string> $middlewares
     * @return void
     */
    public function middlewareGroup(string $name, array $middlewares): void
    {
        $this->middlewareGroups[$name] = $middlewares;
    }

    // ----------------------------------------------------------------
    // Dispatch
    // ----------------------------------------------------------------

    /**
     * Dispatch request hiện tại đến Controller::method phù hợp.
     *
     * Luồng xử lý (theo TDD Phần 3.3):
     *   1. Lấy HTTP method và URI từ Request object
     *   2. Match URI với danh sách routes đã đăng ký
     *   3. Nếu match: chạy middleware chain → gọi Controller
     *   4. Nếu không match: trả về 404
     *
     * @param  Request $request
     * @return void
     */
    public function dispatch(Request $request): void
    {
        $method = $request->method();
        $uri    = $this->normalizeUri($request->uri());

        foreach ($this->routes as $route) {
            // Bước 1: So khớp HTTP method
            if ($route['method'] !== $method) {
                continue;
            }

            // Bước 2: So khớp URI bằng regex đã compile sẵn
            if (!preg_match($route['regex'], $uri, $matches)) {
                continue;
            }

            // Bước 3: Extract route parameters
            // $matches[0] là full match, từ [1] trở đi là capture groups
            $routeParams = [];
            foreach ($route['params'] as $index => $paramName) {
                $routeParams[$paramName] = $matches[$index + 1] ?? '';
            }

            // Bước 4: Inject route params vào Request để Controller đọc
            $request->setRouteParams($routeParams);

            // Bước 5: Chạy middleware chain
            // Nếu bất kỳ middleware nào fail → redirect/response và exit()
            $this->runMiddlewares($route['middlewares'], $request);

            // Bước 6: Khởi tạo Controller và gọi method
            $this->callHandler($route['handler'], $request, $routeParams);

            return; // Route đã được xử lý — dừng vòng lặp
        }

        // Không có route nào match → 404
        $this->handleNotFound();
    }

    // ----------------------------------------------------------------
    // Private Helpers
    // ----------------------------------------------------------------

    /**
     * Thêm route vào danh sách đã đăng ký.
     * Compile URL pattern thành regex ngay lúc đăng ký
     * để tránh compile nhiều lần khi dispatch.
     *
     * @param  string                      $method
     * @param  string                      $pattern
     * @param  array{0: string, 1: string} $handler
     * @param  array<string>               $middlewares
     * @return self
     */
    private function addRoute(
        string $method,
        string $pattern,
        array $handler,
        array $middlewares
    ): self {
        // Cộng dồn prefix của group đang active
        $fullPattern = $this->currentPrefix . $pattern;

        // Merge middleware của group + middleware riêng của route
        $allMiddlewares = array_merge($this->currentGroupMiddlewares, $middlewares);

        // Expand middleware group shorthand
        // VD: 'authenticated' → ['auth', 'workspace', 'onboarding']
        $expandedMiddlewares = $this->expandMiddlewareGroups($allMiddlewares);

        // Extract tên params từ pattern: /issues/{id} → ['id']
        $params = [];
        preg_match_all('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', $fullPattern, $paramMatches);
        if (!empty($paramMatches[1])) {
            $params = $paramMatches[1];
        }

        // Compile pattern thành regex
        // {id} → ([^/]+) — match mọi ký tự trừ dấu /
        $regex = preg_replace('/\{[a-zA-Z_][a-zA-Z0-9_]*\}/', '([^/]+)', $fullPattern);
        $regex = '@^' . $regex . '$@i';

        $this->routes[] = [
            'method'      => strtoupper($method),
            'pattern'     => $fullPattern,
            'regex'       => $regex,
            'params'      => $params,
            'handler'     => $handler,
            'middlewares' => $expandedMiddlewares,
        ];

        return $this;
    }

    /**
     * Chạy middleware chain theo thứ tự đăng ký.
     * Mỗi middleware được resolve từ MiddlewareResolver.
     *
     * Middleware key format:
     *   'auth'           → AuthMiddleware::handle()
     *   'workspace'      → WorkspaceMiddleware::handle()
     *   'rbac:admin'     → RbacMiddleware::handle() với param 'admin'
     *
     * @param  array<string> $middlewares
     * @param  Request       $request
     * @return void
     */
    private function runMiddlewares(array $middlewares, Request $request): void
    {
        foreach ($middlewares as $middlewareKey) {
            // Parse middleware key và optional parameter
            // VD: 'rbac:admin' → key='rbac', param='admin'
            $parts          = explode(':', $middlewareKey, 2);
            $key            = $parts[0];
            $param          = $parts[1] ?? null;

            $middlewareClass = $this->resolveMiddleware($key);

            if ($middlewareClass === null) {
                // Middleware không tồn tại — log warning nhưng không crash
                // Dev 1 sẽ replace bằng Logger::warning() ở Ngày 3
                error_log("[Router] Unknown middleware key: '{$key}'");
                continue;
            }

            // Khởi tạo và gọi middleware
            // Middleware::handle() phải gọi exit() hoặc redirect nếu fail
            $instance = new $middlewareClass();
            $instance->handle($request, $param);
        }
    }

    /**
     * Map middleware key → class name đầy đủ.
     *
     * @param  string      $key
     * @return string|null Class name hoặc null nếu không tìm thấy.
     */
    private function resolveMiddleware(string $key): ?string
    {
        $map = [
            'auth'        => \App\Middleware\AuthMiddleware::class,
            'workspace'   => \App\Middleware\WorkspaceMiddleware::class,
            'rbac'        => \App\Middleware\RbacMiddleware::class,
            'onboarding'  => \App\Middleware\OnboardingMiddleware::class,
        ];

        return $map[$key] ?? null;
    }

    /**
     * Khởi tạo Controller và gọi method tương ứng.
     * Route params được inject vào method dưới dạng arguments.
     *
     * @param  array{0: string, 1: string} $handler       [ControllerClass, 'methodName']
     * @param  Request                     $request
     * @param  array<string, string>       $routeParams   Extracted route parameters.
     * @return void
     */
    private function callHandler(
        array $handler,
        Request $request,
        array $routeParams
    ): void {
        [$controllerClass, $method] = $handler;

        if (!class_exists($controllerClass)) {
            error_log("[Router] Controller not found: {$controllerClass}");
            $this->handleServerError("Controller '{$controllerClass}' không tồn tại.");
            return;
        }

        $controller = new $controllerClass();

        if (!method_exists($controller, $method)) {
            error_log("[Router] Method not found: {$controllerClass}::{$method}");
            $this->handleServerError("Method '{$method}' không tồn tại trong {$controllerClass}.");
            return;
        }

        // Inject route params theo thứ tự khai báo trong method signature
        // Controller::show($request, $id) → $id = $routeParams['id']
        call_user_func_array(
            [$controller, $method],
            array_merge([$request], array_values($routeParams))
        );
    }

    /**
     * Expand middleware group shorthand thành danh sách middleware đầy đủ.
     *
     * @param  array<string> $middlewares
     * @return array<string>
     */
    private function expandMiddlewareGroups(array $middlewares): array
    {
        $expanded = [];

        foreach ($middlewares as $middleware) {
            $key = explode(':', $middleware, 2)[0];

            if (isset($this->middlewareGroups[$key])) {
                // Expand group: thay thế key bằng danh sách middlewares của group
                foreach ($this->middlewareGroups[$key] as $groupMiddleware) {
                    $expanded[] = $groupMiddleware;
                }
            } else {
                $expanded[] = $middleware;
            }
        }

        return $expanded;
    }

    /**
     * Normalize URI: xóa query string, đảm bảo bắt đầu bằng /
     * VD: '/issues/BT-001?tab=comments' → '/issues/BT-001'
     *
     * @param  string $uri
     * @return string
     */
    private function normalizeUri(string $uri): string
    {
        // Xóa query string
        $uri = strtok($uri, '?') ?: '/';

        // Đảm bảo bắt đầu bằng /
        if (!str_starts_with($uri, '/')) {
            $uri = '/' . $uri;
        }

        // Xóa trailing slash (trừ root /)
        if ($uri !== '/') {
            $uri = rtrim($uri, '/');
        }

        return $uri;
    }

    /**
     * Xử lý 404 Not Found.
     *
     * @return void
     */
    private function handleNotFound(): void
    {
        http_response_code(404);

        $viewPath = dirname(__DIR__) . '/Views/errors/404.php';

        if (file_exists($viewPath)) {
            include $viewPath;
        } else {
            echo '<h1>404 – Trang không tìm thấy</h1>';
        }

        exit();
    }

    /**
     * Xử lý 500 Server Error (chỉ khi Router gặp lỗi nội bộ).
     *
     * @param  string $message
     * @return void
     */
    private function handleServerError(string $message): void
    {
        http_response_code(500);

        $viewPath = dirname(__DIR__) . '/Views/errors/500.php';

        if (file_exists($viewPath)) {
            include $viewPath;
        } else {
            echo '<h1>500 – Lỗi máy chủ nội bộ</h1>';
        }

        exit();
    }

    /**
     * Debug helper: trả về danh sách routes đã đăng ký.
     * CHỈ dùng trong môi trường development.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }
}