# BugTracker – CONVENTIONS.md

> **Mục đích:** Tài liệu này là nguồn sự thật duy nhất về quy tắc hệ thống BugTracker.  
> Mọi AI hoặc developer khi làm việc với codebase này **PHẢI** đọc và tuân thủ toàn bộ nội dung trước khi viết bất kỳ dòng code nào.  
> Vi phạm bất kỳ quy tắc nào dưới đây sẽ phá vỡ tính nhất quán và bảo mật của hệ thống.

---

## MỤC LỤC

1. [Tech Stack & Môi Trường](#1-tech-stack--môi-trường)
2. [Cấu Trúc Thư Mục](#2-cấu-trúc-thư-mục)
3. [Naming Conventions](#3-naming-conventions)
4. [PHP – Kiến Trúc MVC](#4-php--kiến-trúc-mvc)
5. [PHP – Bảo Mật Bắt Buộc](#5-php--bảo-mật-bắt-buộc)
6. [PHP – Database & Query](#6-php--database--query)
7. [PHP – Session & Core Classes](#7-php--session--core-classes)
8. [PHP – Controller Rules](#8-php--controller-rules)
9. [PHP – Model Rules](#9-php--model-rules)
10. [PHP – Service Layer Rules](#10-php--service-layer-rules)
11. [View Layer – PHP Templates](#11-view-layer--php-templates)
12. [View Layer – Layout System](#12-view-layer--layout-system)
13. [CSS Rules](#13-css-rules)
14. [JavaScript Rules](#14-javascript-rules)
15. [Multi-Tenant Data Isolation](#15-multi-tenant-data-isolation)
16. [File Upload & Storage](#16-file-upload--storage)
17. [Email & SMTP](#17-email--smtp)
18. [Error Handling & Logging](#18-error-handling--logging)
19. [RBAC & Phân Quyền](#19-rbac--phân-quyền)
20. [Bản Đồ File Theo Trang](#20-bản-đồ-file-theo-trang)
21. [Checklist Trước Khi Commit](#21-checklist-trước-khi-commit)

---

## 1. Tech Stack & Môi Trường

```
Backend  : PHP 8.0+ (MVC thuần, không dùng framework)
Database : MySQL 8.x
Frontend : HTML5 + CSS3 (custom) + Vanilla JS ES6+
CSS      : CSS Custom Properties (_variables.css) + class semantic
JS CDN   : Tailwind CSS 3.x (chỉ dùng trong layout, không dùng utility class inline)
Deploy   : InfinityFree Shared Hosting – FTP only, không SSH, không Cronjob
Composer : phpmailer/phpmailer + vlucas/phpdotenv
```

### Ràng buộc InfinityFree quan trọng
- **Không có SSH** – deploy bằng FTP (FileZilla)
- **Không có Cronjob** – dùng lazy cleanup + manual retry thay thế
- **Giới hạn Inode** – tránh tạo quá nhiều file nhỏ. Khi thao tác với file vật lý (upload/delete attachments), bắt buộc phải có cơ chế `unlink()` file ngay lập tức khi bản ghi bị xóa để tiết kiệm Inode trên InfinityFree.
- **`mail()` bị chặn** – bắt buộc dùng PHPMailer + SMTP Gmail
- **`max_execution_time` giới hạn** – SMTP timeout phải ≤ 10 giây
- **Không có WebSocket** – dùng polling mỗi 60 giây thay thế

---

## 2. Cấu Trúc Thư Mục

```
bugtracker/                    ← Git root (NGOÀI webroot)
├── .env                       ← KHÔNG commit lên Git
├── .env.example               ← Commit – template không có giá trị thật
├── composer.json
├── database/
│   └── schema.sql
├── vendor/                    ← KHÔNG commit (gitignore)
├── storage/                   ← NGOÀI public_html
│   ├── attachments/{ws_id}/{issue_id}/
│   └── logs/app-YYYY-MM.log
├── app/                       ← PHP core – KHÔNG public
│   ├── Config/
│   │   ├── config.php         ← Load .env, định nghĩa constants
│   │   └── routes.php         ← Tất cả routes định nghĩa ở đây
│   ├── Core/
│   │   ├── Database.php       ← PDO Singleton
│   │   ├── Router.php
│   │   ├── Request.php        ← Wrap $_GET, $_POST, $_FILES
│   │   ├── Response.php       ← Static: redirect(), json(), view()
│   │   ├── Session.php        ← Static methods
│   │   └── Logger.php         ← Instance class (KHÔNG gọi static)
│   ├── Controllers/
│   ├── Models/
│   ├── Services/
│   ├── Middleware/
│   ├── Helpers/
│   │   ├── Csrf.php           ← Static methods
│   │   └── Sanitizer.php      ← Static: escape(), escapeJson()
│   └── Views/
│       ├── layouts/           ← auth.php, app.php, landing.php
│       ├── partials/          ← _sidebar.php, _header.php, ...
│       ├── auth/
│       ├── dashboard/
│       ├── issues/
│       ├── projects/
│       ├── members/
│       ├── workspace/
│       ├── landing/
│       ├── emails/            ← Email templates (không có layout)
│       └── errors/            ← 403, 404, 500 (không có layout)
└── public_html/               ← Webroot duy nhất
    ├── index.php              ← Front Controller duy nhất
    ├── .htaccess
    └── assets/
        ├── css/
        │   ├── app.css        ← Load mọi trang
        │   ├── _variables.css ← Design tokens
        │   ├── _auth.css      ← Chỉ auth pages
        │   └── _landing.css   ← Chỉ landing page
        └── js/
            ├── core/          ← utils.js, api.js, toast.js, modal.js, validator.js
            ├── pages/         ← auth.js, dashboard.js, issue-*.js, ...
            └── app.js         ← Entry point – page router
```

---

## 3. Naming Conventions

### PHP
```
Class name    : PascalCase          → WorkspaceController, IssueService
Method name   : camelCase           → findByEmail(), updateStatus()
Variable name : snake_case          → $workspace_id, $current_user
File name     : PascalCase.php      → LoginController.php, User.php
Namespace     : App\{Layer}\{Group} → App\Controllers\Auth\LoginController
```

### Database
```
Table name    : snake_case plural   → workspace_members, activity_logs
Column name   : snake_case          → created_at, is_verified, workspace_id
```

### CSS
```
Class name    : BEM: block__element--modifier
                → .issue-card, .issue-card__title, .badge--open
State class   : .is-{state}        → .is-active, .is-loading, .is-open
JS hook       : .js-{name}         → .js-toggle-password, .js-sidebar-toggle
                Không style .js-* class, chỉ dùng cho JS selector
```

### JavaScript
```
Variable/Function : camelCase      → initDashboard(), formatRelative()
Export function   : named export   → export function initPage() {}
Constant          : UPPER_SNAKE    → MAX_RESULTS, DEBOUNCE_MS
```

### Controller Data Keys (truyền vào View)
```
Bắt buộc camelCase:
  pageId      ← PHẢI có, dùng cho data-page attribute
  pageTitle   ← Tiêu đề tab
  csrfToken   ← CSRF token

KHÔNG dùng: page_id, page_title, csrf_token (snake_case)
```

---

## 4. PHP – Kiến Trúc MVC

### Thứ tự xử lý request
```
Browser → .htaccess → public_html/index.php → Router → Middleware Chain
→ Controller → Service → Model → DB
→ Controller → Response::view() hoặc Response::json()
→ View template → Layout inject $content → HTML ra browser
```

### Phân chia trách nhiệm – TUYỆT ĐỐI tuân thủ

| Layer | Được phép | KHÔNG được phép |
|-------|-----------|-----------------|
| **Controller** | Nhận Request, gọi Service, gọi Response | Viết logic DB, viết SQL, gọi Model trực tiếp |
| **Service** | Business logic, gọi Model, gọi EmailService | Gọi DB trực tiếp, gọi Request, xử lý HTTP |
| **Model** | Viết SQL, PDO queries, trả về array/bool | Business logic, gọi Service khác |
| **View** | Render HTML, echo biến đã escape | Gọi DB, gọi Model, gọi Service, viết logic |
| **Middleware** | Validate session/role, redirect nếu fail | Business logic, gọi Service |

### Constructor pattern – KHÔNG có DI Container
```php
// ✅ ĐÚNG – Router gọi new Controller() không có arguments
public function __construct()
{
    $this->db      = Database::getInstance();
    $this->service = new SomeService();
}

// ❌ SAI – PHP sẽ crash vì Router không truyền arguments
public function __construct(
    private SomeService $service,
    private Database $db
) {}
```

### Khai báo bắt buộc đầu mỗi PHP file
```php
<?php

declare(strict_types=1);

namespace App\Controllers\Auth;

use PDO;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
```

---

## 5. PHP – Bảo Mật Bắt Buộc

### CSRF Token
```php
// Mọi form POST PHẢI có:
<input type="hidden" name="csrf_token"
       value="<?= htmlspecialchars($csrf_token ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>">

// Mọi Controller nhận POST PHẢI validate đầu tiên:
Csrf::validateOrFail($request->post('csrf_token', ''));
```

### XSS Prevention
```php
// ✅ ĐÚNG – mọi output PHP ra HTML
<?= htmlspecialchars($var, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>

// ✅ Hoặc dùng Sanitizer helper
<?= Sanitizer::escape($var) ?>

// ❌ SAI – không bao giờ echo trực tiếp
<?= $var ?>
echo $var;
```

### Password Hashing
```php
// ✅ ĐÚNG – bcrypt cost=12
$hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

// ✅ Verify
password_verify($input, $hash);

// ❌ SAI – không bao giờ dùng
md5($password);
sha1($password);
```

### Security Token Generation
```php
// ✅ ĐÚNG – CSPRNG entropy 256 bits
$token = bin2hex(random_bytes(32)); // 64 chars hex

// ❌ SAI – entropy thấp, dễ brute-force
md5(time());
sha1(uniqid());
```

### Token Comparison
```php
// ✅ ĐÚNG – constant time, chống timing attack
if (hash_equals($stored_token, $submitted_token)) { ... }

// ❌ SAI – dễ bị timing attack
if ($stored_token === $submitted_token) { ... }
if ($stored_token == $submitted_token) { ... }
```

### Session Security
```php
// PHẢI gọi sau khi đăng nhập thành công – chống Session Fixation
Session::regenerate(); // Gọi session_regenerate_id(true) bên trong

// KHÔNG bao giờ truy cập $_SESSION trực tiếp trong Controller/Service
// ✅ ĐÚNG
$user_id      = Session::getUserId();
$workspace_id = Session::getActiveWorkspaceId();

// ❌ SAI
$user_id      = $_SESSION['user_id'];
$workspace_id = (int) ($_SESSION['active_workspace_id'] ?? 0);
```

### File Upload Security
```php
// ✅ Validate MIME type bằng finfo_file() – KHÔNG tin extension
$finfo     = new \finfo(FILEINFO_MIME_TYPE);
$mime_type = $finfo->file($tmp_path);

// ✅ Lưu NGOÀI public_html
/storage/attachments/{workspace_id}/{issue_id}/

// ✅ Serve qua PHP proxy – không direct URL
GET /files/{workspaceId}/{issueId}/{filename} → FileController::serve()

// ❌ SAI – không lưu trong webroot
/public_html/uploads/
```

---

## 6. PHP – Database & Query

### PDO Prepared Statements – TUYỆT ĐỐI
```php
// ✅ ĐÚNG – luôn dùng named parameters
$stmt = $this->db->prepare(
    'SELECT * FROM issues WHERE workspace_id = :ws AND status = :status'
);
$stmt->execute([':ws' => $workspace_id, ':status' => $status]);

// ❌ SAI – không bao giờ nối chuỗi vào SQL
$sql = "SELECT * FROM issues WHERE workspace_id = $workspace_id";
$sql = "SELECT * FROM issues WHERE status = '" . $status . "'";
```

### Soft Delete – mọi query phải filter
```php
// ✅ ĐÚNG – luôn thêm điều kiện này
WHERE deleted_at IS NULL

// Khi soft delete – ghi timestamp, không xóa record
UPDATE users SET deleted_at = NOW() WHERE id = :id
```

### Transaction cho multi-step operations
```php
$this->db->beginTransaction();
try {
    // ... multiple queries
    $this->db->commit();
} catch (\PDOException $e) {
    $this->db->rollBack();
    // log error
}
```

### bindValue() cho LIMIT – tránh SQL injection qua LIMIT
```php
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
```

### PDO import đúng cách
```php
// ✅ ĐÚNG
use PDO;
private PDO $db;

// ❌ SAI – thiếu use statement
private \PDO $db;
```

### Tránh N+1 Query
```php
// ✅ ĐÚNG – 1 query với JOIN
SELECT i.*, u.name AS assignee_name, p.name AS project_name
FROM issues i
JOIN users u ON u.id = i.assignee_id
JOIN projects p ON p.id = i.project_id
WHERE i.workspace_id = :ws

// ❌ SAI – N+1 queries trong vòng lặp
foreach ($issues as $issue) {
    $user = $this->user_model->findById($issue['assignee_id']); // N queries!
}
```

### Quy tắc Multi-tenant – BẮT BUỘC (SRS Phần 1.1)

> Dự án thiết kế theo kiến trúc **Multi-tenant**: nhiều Workspace hoàn toàn độc lập, dùng chung một database (Single Database, Shared Tables).  
> Nếu query thiếu `workspace_id`, dữ liệu của Workspace này sẽ bị lộ sang Workspace khác — **đây là lỗi bảo mật nghiêm trọng**.

```php
// ✅ ĐÚNG – mọi query liên quan đến Issue, Project, Member, Comment,
//           Attachment, Notification, ActivityLog, Tag... PHẢI có workspace_id
$stmt = $this->db->prepare(
    'SELECT * FROM issues
     WHERE id           = :id
       AND workspace_id = :workspace_id  -- BẮT BUỘC
       AND deleted_at IS NULL'
);

// ✅ ĐÚNG – UPDATE và DELETE cũng phải có workspace_id
$stmt = $this->db->prepare(
    'UPDATE issues
     SET status = :status
     WHERE id           = :id
       AND workspace_id = :workspace_id  -- BẮT BUỘC'
);

// ❌ SAI – thiếu workspace_id → lộ/ghi nhầm dữ liệu Workspace khác
$stmt = $this->db->prepare('SELECT * FROM issues WHERE id = :id');

// Các bảng KHÔNG cần workspace_id (system global):
// users, user_tokens, login_attempts, email_verifications,
// password_resets, email_queue, system_logs
```

---

## 7. PHP – Session & Core Classes

### Session class – tất cả methods là STATIC
```php
// ✅ ĐÚNG – gọi static
Session::start();
Session::getUserId();
Session::getActiveWorkspaceId();
Session::setActiveWorkspace($id);
Session::set('key', $value);
Session::get('key', $default);
Session::remove('key');
Session::regenerate();
Session::destroy();

// ❌ SAI – Session KHÔNG phải instance
$this->session->get('user_id');    // crash
new Session();                      // không cần
```

### Response class – tất cả methods là STATIC
```php
// ✅ ĐÚNG
Response::redirect('/dashboard');
Response::json(['success' => true], 200);
Response::view('dashboard/index', $data);
Response::setFlash('success', 'Đã lưu!');
Response::getFlash('success');

// ❌ SAI
$this->response->json([...]);      // crash
```

### Request class – phải nhận từ Router (KHÔNG tự new)
```php
// ✅ ĐÚNG – Router inject qua method parameter
public function index(Request $request): void
public function show(Request $request, string $issueKey): void

// ✅ Lấy data từ instance
$request->post('name', '');
$request->get('page', 1);
$request->file('attachments');
$request->isAjax();
$request->isPost();
$request->ip();

// ❌ SAI – không tự khởi tạo trong method
$request = new Request();           // sai kiến trúc

// ❌ SAI – Request::post() là instance method, không phải static
Request::post('name');              // crash
```

### Csrf class – static
```php
Csrf::generateToken();              // Sinh và lưu vào session
Csrf::getToken();                   // Lấy token hiện tại
Csrf::getHiddenInput();             // Render <input hidden>
Csrf::validateOrFail($token);       // Validate hoặc 403
```

### Logger class – là INSTANCE class (KHÔNG static)
```php
// ✅ ĐÚNG – tạo instance hoặc dùng error_log tạm thời
// (Logger::error() chưa implement đến Ngày 3 – D1-021)
// TODO: Replace bằng Logger instance sau khi D1-021 hoàn thành
error_log('[ClassName] message: ' . $e->getMessage());

// ❌ SAI – Logger::error() không tồn tại (không phải static)
Logger::error('message', 'context', $trace);  // crash
```

---

## 8. PHP – Controller Rules

### Method signature bắt buộc
```php
// Request PHẢI là argument đầu tiên – Router luôn inject
public function index(Request $request): void
public function show(Request $request, string $issueKey): void
public function update(Request $request, string $projectKey, int $id): void

// ❌ SAI – thiếu Request parameter, Router sẽ truyền sai arguments
public function index(): void
public function update(int $id): void
```

### Controller chỉ được làm
```php
public function store(Request $request): void
{
    // 1. Validate CSRF
    Csrf::validateOrFail($request->post('csrf_token', ''));

    // 2. Lấy data từ Request (KHÔNG từ $_POST)
    $name = $request->post('name', '');

    // 3. Lấy context từ Session (KHÔNG từ $_SESSION)
    $workspace_id = Session::getActiveWorkspaceId();

    // 4. Gọi Service (KHÔNG gọi Model trực tiếp)
    $result = $this->some_service->create($workspace_id, ['name' => $name]);

    // 5. Trả về Response (static)
    if ($result['success']) {
        Response::setFlash('success', 'Đã tạo thành công!');
        Response::redirect('/dashboard');
    }
}
```

---

## 9. PHP – Model Rules

### Chỉ viết SQL trong Model
```php
public function findById(int $id, int $workspace_id): array|false
{
    $stmt = $this->db->prepare(
        'SELECT * FROM issues
         WHERE id = :id
           AND workspace_id = :workspace_id
           AND deleted_at IS NULL
         LIMIT 1'
    );
    $stmt->execute([':id' => $id, ':workspace_id' => $workspace_id]);
    return $stmt->fetch(); // PDO::FETCH_ASSOC (đã set trong Database.php)
}
```

### Quy tắc Soft Delete – TUYỆT ĐỐI không dùng DELETE vật lý (TDD Phụ Lục)

> Hệ thống sử dụng **Soft Delete** cho toàn bộ dữ liệu nghiệp vụ.  
> Lệnh `DELETE FROM` chỉ được phép với các bảng kỹ thuật (login_attempts, email_queue, user_tokens, notifications cũ).  
> Xóa record nghiệp vụ bằng `DELETE FROM` sẽ phá vỡ Activity Log, foreign key, và audit trail.

```php
// ✅ ĐÚNG – Soft delete: ghi timestamp, giữ record trong DB
public function delete(int $id): bool
{
    $stmt = $this->db->prepare(
        'UPDATE issues
         SET deleted_at = NOW()
         WHERE id = :id
           AND deleted_at IS NULL'
    );
    $stmt->execute([':id' => $id]);
    return $stmt->rowCount() > 0;
}

// ✅ ĐÚNG – Mọi SELECT phải lọc record đã xóa
$stmt = $this->db->prepare(
    'SELECT * FROM issues
     WHERE workspace_id = :workspace_id
       AND deleted_at IS NULL'   -- BẮT BUỘC trong mọi query đọc
);

// ❌ SAI – xóa vật lý mất dữ liệu vĩnh viễn, phá audit trail
$stmt = $this->db->prepare('DELETE FROM issues WHERE id = :id');

// Các bảng ĐƯỢC PHÉP dùng DELETE vật lý (kỹ thuật, không phải nghiệp vụ):
// login_attempts     → cleanup rate limiting
// email_queue        → cleanup email cũ (Admin thủ công)
// user_tokens        → revoke Remember Me
// notifications      → cleanup đã đọc > 30 ngày (lazy cleanup)
// email_verifications → cleanup sau khi verify xong
```

---

## 10. PHP – Service Layer Rules

### Service trả về array với success flag
```php
// ✅ Pattern chuẩn
return ['success' => true,  'errors' => [],    'data'   => $result];
return ['success' => false, 'errors' => [...], 'data'   => null];
```

### Email – Graceful Degradation bắt buộc
```php
try {
    $this->email_service->send($to, $name, $subject, $html);
} catch (\Exception $e) {
    // KHÔNG throw lên – luồng chính KHÔNG bị phá vỡ
    error_log('[Service] Email failed: ' . $e->getMessage());
    $queue = new EmailQueue();
    $queue->insert($to, $name, $subject, $html, 'failed');
}
```

### Activity Log sau mỗi action quan trọng
```php
// Ghi log sau khi tạo/sửa/xóa entity
$this->logActivity($workspace_id, $user_id, 'issue', $issue_id, 'issue_created', [
    'issue_key' => $issue_key,
    'title'     => $title,
]);
```

---

## 11. View Layer – PHP Templates

### Dòng đầu tiên PHẢI khai báo layout
```php
<?php
$layout = 'auth';   // hoặc 'app', 'landing'
// ... rest of file
?>
```

> **Nếu thiếu dòng này:** layout không load → trang không có CSS và JS.

### Mapping layout theo nhóm trang

| Layout | Dùng cho |
|--------|---------|
| `auth` | login, register, forgot-password, reset-password, onboarding |
| `app` | dashboard, issues, projects, members, workspace/settings |
| `landing` | landing/index, landing/terms |
| _(không có)_ | errors/403, errors/404, errors/500, emails/* |

### Escape tất cả output
```php
// ✅ ĐÚNG
<?= htmlspecialchars($var, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>

// ✅ JSON data cho JS
<script type="application/json" id="page-data">
    <?= json_encode($data, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>
</script>

// ❌ SAI
<?= $var ?>
```

### KHÔNG được có trong view file
```php
// ❌ Không có <html>, <head>, <body>
// ❌ Không có <link rel="stylesheet">
// ❌ Không có <script> (trừ type="application/json")
// ❌ Không có inline style="" trong HTML
// ❌ Không gọi DB, Model, Service
// ❌ Không hardcode URL (dùng url() helper)
// ❌ Không hardcode màu sắc
```

---

## 12. View Layer – Layout System

### Tailwind CDN – dùng `<script>` KHÔNG phải `<link>`
```html
<!-- ✅ ĐÚNG -->
<script src="https://cdn.tailwindcss.com"></script>

<!-- ❌ SAI -->
<link href="https://cdn.tailwindcss.com" rel="stylesheet">
```

### Script ES6 Module – KHÔNG thêm defer khi đã có type="module"
```html
<!-- ✅ ĐÚNG – module tự defer -->
<script type="module" src="<?= asset('js/app.js') ?>"></script>

<!-- ❌ SAI – defer thừa -->
<script type="module" src="<?= asset('js/app.js') ?>" defer></script>
```

### Thứ tự load JS (core trước, page-specific sau)
```html
<script src="<?= asset('js/core/utils.js') ?>" defer></script>
<script src="<?= asset('js/core/api.js') ?>" defer></script>
<script src="<?= asset('js/core/toast.js') ?>" defer></script>
<script src="<?= asset('js/core/modal.js') ?>" defer></script>
<script src="<?= asset('js/core/validator.js') ?>" defer></script>
<script type="module" src="<?= asset('js/app.js') ?>"></script>
```

### Conditional CSS loading
```php
// Chỉ load _auth.css trong layouts/auth.php
<link rel="stylesheet" href="<?= asset('css/_auth.css') ?>">

// Chỉ load Chart.js trên Dashboard
<?php if (($pageId ?? '') === 'dashboard'): ?>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js" defer></script>
<?php endif; ?>

// Chỉ load marked.js trên issue form/detail
<?php if (in_array($pageId ?? '', ['issue-form', 'issue-detail'], true)): ?>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/marked/9.1.6/marked.min.js" defer></script>
<?php endif; ?>
```

### pageId bắt buộc – Controller phải truyền
```php
// ✅ ĐÚNG
Response::view('dashboard/index', [
    'pageId'    => 'dashboard',   // bắt buộc
    'pageTitle' => 'Dashboard',
    'csrfToken' => Csrf::generateToken(),
]);

// ❌ SAI – thiếu pageId, conditional CSS/JS không hoạt động
Response::view('dashboard/index', ['pageTitle' => 'Dashboard']);
```

---

## 13. CSS Rules

### KHÔNG bao giờ hardcode giá trị màu
```css
/* ✅ ĐÚNG – dùng CSS variable từ _variables.css */
color: var(--color-primary-600);
background-color: var(--color-neutral-50);
border: 1px solid var(--color-neutral-200);

/* ❌ SAI – hardcode hex */
color: #2563eb;
background-color: #f8fafc;
```

### KHÔNG dùng `!important`
```css
/* ❌ SAI */
color: red !important;
```

### KHÔNG duplicate token từ `_variables.css`
```css
/* ❌ SAI – đã có trong _variables.css */
:root {
    --color-primary-600: #2563eb;  /* duplicate! */
}
```

### KHÔNG viết inline style trong PHP/HTML
```html
<!-- ❌ SAI -->
<div style="color: #2563eb; margin: 16px;">

<!-- ✅ ĐÚNG – dùng class -->
<div class="text-primary card-spacing">
```

### Design tokens reference – _variables.css
```css
/* Màu */
var(--color-primary-600)    /* Blue #2563EB – CTA, links */
var(--color-success-500)    /* Green #22C55E */
var(--color-danger-500)     /* Red #EF4444 */
var(--color-warning-500)    /* Amber #F59E0B */
var(--color-neutral-900)    /* Text chính */
var(--color-neutral-500)    /* Text phụ */
var(--color-surface)        /* White – card background */

/* Sidebar */
var(--sidebar-bg)           /* #0F172A – dark */
var(--sidebar-width)        /* 240px */

/* Spacing */
var(--space-1)  /* 4px */  var(--space-2)  /* 8px */
var(--space-3)  /* 12px */ var(--space-4)  /* 16px */
var(--space-6)  /* 24px */ var(--space-8)  /* 32px */

/* Typography */
var(--text-xs) var(--text-sm) var(--text-base)
var(--text-lg) var(--text-xl) var(--text-2xl) var(--text-3xl)
var(--font-weight-medium)    /* 500 */
var(--font-weight-semibold)  /* 600 */
var(--font-weight-bold)      /* 700 */

/* Shape */
var(--radius-sm) var(--radius-md) var(--radius-lg) var(--radius-full)
var(--shadow-xs) var(--shadow-sm) var(--shadow-md) var(--shadow-lg)

/* Animation */
var(--transition-fast)   /* 150ms */
var(--transition-normal) /* 250ms */
var(--transition-slow)   /* 400ms */
```

---

## 14. JavaScript Rules

### Mọi AJAX phải qua `api.js` – KHÔNG dùng `fetch()` trực tiếp
```javascript
// ✅ ĐÚNG
import { API } from '../core/api.js';
const result = await API.post('/api/issues', data);
const data   = await API.get('/api/notifications');

// ❌ SAI – fetch trực tiếp bỏ qua CSRF header tự động
fetch('/api/issues', { method: 'POST', body: JSON.stringify(data) });
```

### KHÔNG dùng `innerHTML` với user input – XSS
```javascript
// ✅ ĐÚNG
element.textContent = userInput;

// ✅ Nếu cần HTML, sanitize trước
element.innerHTML = DOMPurify.sanitize(htmlContent);

// ❌ SAI
element.innerHTML = userInput;
```

### KHÔNG viết logic trong file `.php`
```php
<!-- ✅ Ngoại lệ DUY NHẤT được phép – truyền data từ PHP sang JS -->
<script type="application/json" id="chart-data">
    <?= json_encode($chart_data, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>
</script>

<!-- ❌ SAI – logic trong PHP file -->
<script>
    const userId = <?= $user['id'] ?>;
    fetch('/api/...');
</script>
```

### Module export pattern
```javascript
// ✅ ĐÚNG – named export, app.js sẽ import
export function initDashboard() {
    // ...
}

// app.js import theo page
case 'dashboard':
    import('./pages/dashboard.js').then(m => m.initDashboard?.());
    break;
```

### Đọc data từ JSON script tag
```javascript
// ✅ ĐÚNG – đọc data PHP truyền sang
const el   = document.getElementById('chart-data');
const data = el ? JSON.parse(el.textContent) : null;
```

### Notification polling – dừng khi tab ẩn
```javascript
// ✅ ĐÚNG – tiết kiệm tài nguyên InfinityFree
document.addEventListener('visibilitychange', () => {
    if (document.hidden) {
        clearInterval(pollingInterval);
    } else {
        startPolling();
    }
});
```

---

## 15. Multi-Tenant Data Isolation

> **QUAN TRỌNG NHẤT trong toàn hệ thống.**  
> BugTracker dùng Single Database Shared Tables.  
> Vi phạm data isolation = data leak giữa các Workspace.

### Mọi query PHẢI có `workspace_id`
```php
// ✅ ĐÚNG – luôn filter theo workspace
SELECT * FROM issues
WHERE workspace_id = :workspace_id
  AND deleted_at IS NULL

// ❌ SAI – không filter workspace, lộ dữ liệu workspace khác
SELECT * FROM issues WHERE id = :id
```

### `workspace_id` phải lấy từ Session – KHÔNG từ URL/POST
```php
// ✅ ĐÚNG – WorkspaceMiddleware đã validate
$workspace_id = Session::getActiveWorkspaceId();

// ❌ SAI – user có thể giả mạo
$workspace_id = $request->get('workspace_id');
$workspace_id = $request->post('workspace_id');
```

### IDOR Prevention – luôn verify ownership
```php
// ✅ ĐÚNG – tìm kèm workspace_id để chặn IDOR
$issue = $this->issue_model->findById($id, $workspace_id);
if (!$issue) {
    Response::json(['success' => false, 'message' => 'Không tìm thấy.'], 404);
    return;
}

// ❌ SAI – user có thể xem/sửa issue của workspace khác
$issue = $this->issue_model->findById($id);
```

### Middleware chain thứ tự bắt buộc
```
AuthMiddleware → OnboardingMiddleware → WorkspaceMiddleware → RbacMiddleware
```

---

## 16. File Upload & Storage

```php
// File lưu NGOÀI webroot
/storage/attachments/{workspace_id}/{issue_id}/{stored_name}

// Giới hạn theo SRS
UPLOAD_MAX_FILES = 5          // max file/Issue
UPLOAD_MAX_SIZE  = 2097152    // 2MB per file
UPLOAD_ALLOWED_TYPES = jpg,jpeg,png,gif,pdf,txt,zip

// Validate MIME bằng finfo_file() – KHÔNG tin extension
$finfo     = new \finfo(FILEINFO_MIME_TYPE);
$mime_type = $finfo->file($tmp_path);

// Rename file khi lưu – tránh path traversal
$stored_name = bin2hex(random_bytes(16)) . '.' . $ext;

// Serve qua PHP proxy
Route: GET /files/{workspaceId}/{issueId}/{filename}
       → AttachmentController::serve()
       → Kiểm tra quyền → readfile()
```

---

## 17. Email & SMTP

```php
// KHÔNG dùng mail() built-in – InfinityFree block
// PHẢI dùng PHPMailer + Gmail SMTP

// Config từ .env (KHÔNG hardcode)
SMTP_HOST, SMTP_PORT, SMTP_USER, SMTP_PASS, SMTP_TIMEOUT=10

// Graceful degradation – KHÔNG throw lên caller
try {
    $this->email_service->send(...);
} catch (\Exception $e) {
    error_log('[Service] Email failed: ' . $e->getMessage());
    // Insert vào email_queue để Admin retry thủ công
    $queue->insert($to, $name, $subject, $html, 'failed');
    // Flow chính KHÔNG bị ngắt
}

// Token TTL từ constants (KHÔNG hardcode số giây)
TOKEN_TTL_EMAIL_VERIFY    = 86400   // 24h
TOKEN_TTL_PASSWORD_RESET  = 3600    // 1h
TOKEN_TTL_INVITATION      = 604800  // 7d
```

---

## 18. Error Handling & Logging

### Exception handling theo layer
```php
// Model – bắt PDOException
try {
    $stmt = $this->db->prepare(...);
    $stmt->execute(...);
} catch (\PDOException $e) {
    error_log('[Model] Query failed: ' . $e->getMessage());
    throw new \RuntimeException('DB error', 0, $e);
}

// Service – bắt mọi exception, không để bubble lên Controller
try {
    $this->email_service->send(...);
} catch (\Exception $e) {
    error_log('[Service] Email failed: ' . $e->getMessage());
    // Graceful degradation
}

// Controller – bắt exception từ Service
try {
    $result = $this->service->create($data);
} catch (\RuntimeException $e) {
    error_log('[Controller] ' . $e->getMessage());
    Response::view('errors/500', [], 500);
    return;
}
```

### Logger – dùng `error_log()` tạm thời đến khi Logger implement
```php
// ✅ Pattern chuẩn khi Logger chưa sẵn
// TODO: Replace bằng Logger instance sau khi D1-021 hoàn thành
error_log(sprintf(
    '[ClassName::method] Message | Workspace: %d | Error: %s | Trace: %s',
    $workspace_id,
    $e->getMessage(),
    substr($e->getTraceAsString(), 0, 2000)  // Giới hạn 2000 chars
));

// ❌ SAI – Logger là instance class, không phải static
Logger::error('message', 'context', $trace);
```

### KHÔNG lộ thông tin nhạy cảm ra response
```php
// ✅ ĐÚNG – user thấy thông báo thân thiện
Response::view('errors/500', ['error_id' => $error_id], 500);

// ❌ SAI – lộ stack trace/SQL ra browser
echo $e->getMessage();
echo $e->getTraceAsString();
```

---

## 19. RBAC & Phân Quyền

### 4 roles theo thứ tự quyền tăng dần
```
guest (1) → member (2) → admin (3) → owner (4)
```

### Quy tắc RBAC không thể thay đổi (SRS Phần 1.2)
```
Owner:
  - Chỉ có 1 Owner tại mọi thời điểm
  - Không thể bị kick bởi bất kỳ ai
  - Chỉ Owner mới promote Member lên Admin
  - Chỉ Owner mới xóa Workspace

Admin:
  - Không thể kick Owner
  - Không thể kick Admin khác
  - Không thể tự phong Admin mới (chỉ Owner làm được)
  - Có thể kick Member và Guest

Member:
  - Không thể tạo Project
  - Không thể mời thành viên
  - Chỉ update trạng thái Issue được giao

Guest:
  - Chỉ tạo Issue và comment
  - Không xem Activity Log
  - Không xem Dashboard
```

### Double-check trong Controller (không chỉ dựa Middleware)
```php
// ✅ ĐÚNG – check lại trong method nhạy cảm
$actor_role = Session::get('current_role', 'guest');
if ($actor_role !== 'owner') {
    Response::json(['success' => false, 'message' => 'Chỉ Owner mới làm được.'], 403);
    return;
}
```

---

## 20. Bản Đồ File Theo Trang

| Trang | Layout | View | CSS | JS |
|-------|--------|------|-----|-----|
| Landing | `layouts/landing.php` | `landing/index.php` | `_landing.css` | `pages/landing.js` |
| Login | `layouts/auth.php` | `auth/login.php` | `_auth.css` | `pages/auth.js` |
| Register | `layouts/auth.php` | `auth/register.php` | `_auth.css` | `pages/auth.js` |
| Forgot Password | `layouts/auth.php` | `auth/forgot-password.php` | `_auth.css` | `pages/auth.js` |
| Onboarding | `layouts/auth.php` | `auth/onboarding.php` | `_auth.css` | `pages/auth.js` |
| Dashboard | `layouts/app.php` | `dashboard/index.php` | `app.css` | `pages/dashboard.js` |
| Issue List | `layouts/app.php` | `issues/list.php` | `app.css` | `pages/issue-list.js` |
| Issue Detail | `layouts/app.php` | `issues/detail.php` | `app.css` | `pages/issue-detail.js` |
| Issue Form | `layouts/app.php` | `issues/form.php` | `app.css` | `pages/issue-form.js` |
| Members | `layouts/app.php` | `members/index.php` | `app.css` | `pages/members.js` |
| WS Settings | `layouts/app.php` | `workspace/settings.php` | `app.css` | – |
| Error 404/403/500 | _(none)_ | `errors/{code}.php` | `app.css` | – |
| Email templates | _(none)_ | `emails/{name}.php` | inline style | – |

### Muốn sửa giao diện → vào file nào

| Mục tiêu | File cần sửa |
|-----------|-------------|
| Màu sắc toàn hệ thống | `_variables.css` |
| Font, typography | `_variables.css` |
| Sidebar HTML | `partials/_sidebar.php` |
| Header HTML | `partials/_header.php` |
| Sidebar/Header style | `_layout.css` |
| Button, Card, Badge | `_components.css` |
| Form input, validation | `_forms.css` |
| Toast notification | `_toast.css` |
| Skeleton loading | `_skeleton.css` |
| Auth card, strength bar | `_auth.css` |
| Landing hero, CTA | `_landing.css` |
| Dashboard widgets | `_dashboard.css` |

---

## 21. Checklist Trước Khi Commit

### PHP
- [ ] `declare(strict_types=1)` ở đầu mỗi file
- [ ] Mọi class có `use` statement thay vì `\FullPath`
- [ ] `use PDO;` thay vì `private \PDO $db`
- [ ] Constructor không có parameters (không dùng DI injection qua constructor)
- [ ] Mọi Controller method có `Request $request` là parameter đầu tiên
- [ ] Mọi form POST bắt đầu bằng `Csrf::validateOrFail()`
- [ ] KHÔNG truy cập `$_SESSION`, `$_POST`, `$_GET` trực tiếp
- [ ] KHÔNG gọi `Logger::error()` kiểu static (Logger là instance class)
- [ ] KHÔNG gọi `Request::post()` kiểu static (Request là instance class)
- [ ] Mọi output PHP ra HTML qua `htmlspecialchars()` hoặc `Sanitizer::escape()`
- [ ] Mọi query có `workspace_id` filter + `deleted_at IS NULL`
- [ ] Mọi query dùng Prepared Statement (KHÔNG nối chuỗi SQL)
- [ ] IDOR check – verify ownership trước khi action
- [ ] Password hash bằng `password_hash($pass, PASSWORD_BCRYPT, ['cost' => 12])`
- [ ] Token sinh bằng `bin2hex(random_bytes(32))`
- [ ] Token so sánh bằng `hash_equals()`
- [ ] File upload lưu ngoài webroot, validate MIME bằng `finfo_file()`
- [ ] Email trong try-catch, graceful degradation, không throw lên caller

### View / Frontend
- [ ] View file có `$layout = 'tên-layout'` ở dòng đầu tiên
- [ ] KHÔNG có `<html>`, `<head>`, `<body>`, `<link>`, `<script>` trong view file
- [ ] KHÔNG có `<style>` hay `<script>` inline trong view (trừ `type="application/json"`)
- [ ] Tailwind CDN dùng `<script>` không phải `<link>`
- [ ] Script module KHÔNG thêm `defer`
- [ ] Controller truyền `pageId` vào data array
- [ ] `pageId` dùng camelCase key (không phải `page_id`)

### CSS
- [ ] Không hardcode màu hex – dùng `var(--color-*)`
- [ ] Không dùng `!important`
- [ ] Không duplicate token đã có trong `_variables.css`
- [ ] Không inline style trong HTML

### JavaScript
- [ ] Mọi AJAX qua `api.js` – không dùng `fetch()` trực tiếp
- [ ] Không `innerHTML = userInput` trực tiếp
- [ ] Không logic trong file `.php` (chỉ `type="application/json"` được phép)
- [ ] Export named function, không export default

### Git
- [ ] `.env` không được commit (có trong `.gitignore`)
- [ ] `vendor/` không được commit
- [ ] `storage/attachments/*` không được commit (chỉ `.gitkeep`)

---

## PHỤ LỤC – Quick Reference

### HTTP Method → Controller Method
```
GET    /resources           → index()
GET    /resources/create    → create()
POST   /resources           → store()
GET    /resources/{id}      → show()
GET    /resources/{id}/edit → edit()
PUT    /resources/{id}      → update()
DELETE /resources/{id}      → destroy()
```

### AJAX Response format chuẩn
```json
{
    "success": true,
    "message": "Mô tả kết quả",
    "data": {}
}
```

### Issue State Machine
```
open → in_triage, in_progress, wont_fix, duplicate
in_triage → in_progress, wont_fix, duplicate
in_progress → resolved
resolved → closed (QA), reopened (QA)
reopened → in_progress
closed → reopened
wont_fix → (terminal)
duplicate → (terminal)
```

### Token TTL
```
EMAIL_VERIFY     = 86400   (24 giờ)
PASSWORD_RESET   = 3600    (1 giờ)
INVITATION       = 604800  (7 ngày)
REMEMBER_ME      = 30 ngày (cookie)
```

### Upload Limits
```
MAX_FILES = 5 file/Issue
MAX_SIZE  = 2MB (2,097,152 bytes) per file
ALLOWED   = jpg, jpeg, png, gif, pdf, txt, zip
```

---

*CONVENTIONS.md – BugTracker v1.0.0 | Tháng 5, 2026*  
*Tài liệu này phải được đọc trước khi bắt đầu bất kỳ task nào.*
