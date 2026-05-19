<?php

declare(strict_types=1);

namespace App\Controllers\Auth;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Helpers\Csrf;
use App\Helpers\Sanitizer;
use App\Models\User;
use App\Helper\Functions;

/**
 * RegisterController – Đăng ký tài khoản mới
 *
 * Xử lý UC-001 (Đăng ký) và UC-002 (Xác minh email).
 * Validation server-side đầy đủ, không tin tưởng client-side.
 *
 * @package App\Controllers\Auth
 * @version 1.0.0
 * @see     SRS v1.0.0 – UC-001, UC-002, UC-042
 * @see     Task Assignment v1.0.0 – D1-013
 */
class RegisterController
{
    private User $userModel;

    public function __construct()
    {
        $this->userModel = new User();
    }

    /**
     * Hiển thị form đăng ký.
     * GET /register
     *
     * @param  Request $request
     * @return void
     */
    public function showForm(Request $request): void
    {
        // Nếu đã đăng nhập → redirect dashboard
        if (Session::isLoggedIn()) {
            Response::redirect('/dashboard');
        }

        Response::view('auth/register', [
            'pageId'    => 'register',
            'pageTitle' => 'Đăng ký tài khoản',
            'csrfToken' => Csrf::generateToken(),
            'old_input' => Response::getOldInput(),
            'errors'    => Session::get('_validation_errors', []),
        ]);

        Session::remove('_validation_errors');
    }

    /**
     * Xử lý submit form đăng ký.
     * POST /register
     *
     * @param  Request $request
     * @return void
     */
    public function store(Request $request): void
    {
        // Bước 1: Validate CSRF
        Csrf::validateOrFail($request->post('csrf_token', ''));

        // Bước 2: Lấy và sanitize input
        $name            = trim($request->post('name', ''));
        $email           = trim(strtolower($request->post('email', '')));
        $password        = $request->post('password', '');
        $passwordConfirm = $request->post('password_confirm', '');

        // Bước 3: Validate server-side
        $errors = $this->validateRegistration($name, $email, $password, $passwordConfirm);

        if (!empty($errors)) {
            // Lưu errors và old input → redirect back
            Session::set('_validation_errors', $errors);
            Response::setOldInput([
                'name'  => $name,
                'email' => $email,
                // KHÔNG lưu password trong old input
            ]);
            Response::redirect('/register');
        }

        // Bước 4: Kiểm tra email đã tồn tại chưa
        if ($this->userModel->findByEmail($email)) {
            Session::set('_validation_errors', [
                'email' => 'Email này đã được đăng ký. Vui lòng đăng nhập hoặc dùng email khác.',
            ]);
            Response::setOldInput(['name' => $name, 'email' => $email]);
            Response::redirect('/register');
        }

        // Bước 5: Hash password và tạo user
        // bcrypt cost=12 theo TDD Backend Phần 1.4
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

        try {
            $userId = $this->userModel->create([
                'name'     => $name,
                'email'    => $email,
                'password' => $hashedPassword,
            ]);

            // Bước 6: Auto-Login
            // Lấy lại thông tin user để lưu session
            $user = $this->userModel->findById($userId);
            Session::loginUser($user);

            // Bước 7: Kịch bản Đăng ký qua Link mời (Invite Link)
            // Nếu có pending token trong session, InvitationController sẽ tự động:
            // 1. Thêm user vào workspace_members
            // 2. Set is_verified = 1 và onboarding_completed = true
            // 3. Gọi Response::redirect('/dashboard') (Lệnh này sẽ gọi exit() ngắt luồng)
            $invitationController = new \App\Controllers\Workspace\InvitationController();
            $hasProcessedInvite = $invitationController->processPendingInvitation($userId, $email);

            if ($hasProcessedInvite) {
                // Return để đảm bảo an toàn nếu quá trình redirect trong controller kia không exit
                return;
            }

            // Kịch bản Đăng ký tự nhiên (Normal Registration)
            // Bước 8: Tạo và gửi email xác minh (chạy nền, không block user)
            $this->sendVerificationEmail($userId, $email, $name);

            // Bước 9: Điều hướng thẳng sang Onboarding thay vì Login
            Response::redirect('/onboarding');

        } catch (\PDOException $e) {
            error_log('[RegisterController] Create user failed: ' . $e->getMessage());
            Response::setFlash('error', 'Đã xảy ra lỗi. Vui lòng thử lại.');
            Response::redirect('/register');
        }
    }

    /**
     * Xử lý click link xác minh email.
     * GET /verify-email/{token}
     *
     * @param  Request $request
     * @param  string  $token
     * @return void
     */
    public function verifyEmail(Request $request, string $token): void
    {
        // Sanitize token — chỉ giữ hex characters
        $token = preg_replace('/[^a-f0-9]/i', '', $token);

        if (strlen($token) !== 64) {
            Response::setFlash('error', 'Link xác minh không hợp lệ.');
            Response::redirect('/login');
        }

        $record = $this->userModel->findVerificationToken($token);

        if (!$record) {
            Response::setFlash(
                'error',
                'Link xác minh đã hết hạn hoặc không hợp lệ. '
                . '<a href="/resend-verification">Gửi lại email xác minh</a>'
            );
            Response::redirect('/login');
        }

        // Cập nhật is_verified = 1
        $this->userModel->updateVerified((int) $record['user_id']);

        // Xóa token (single-use)
        $this->userModel->deleteVerificationToken($token);

        Response::setFlash('success', 'Xác minh email thành công! Bạn có thể đăng nhập ngay bây giờ.');
        Response::redirect('/login');
    }

    /**
     * Gửi lại email xác minh.
     * POST /resend-verification
     *
     * @param  Request $request
     * @return void
     */
    public function resendVerification(Request $request): void
    {
        Csrf::validateOrFail($request->post('csrf_token', ''));

        $email = trim(strtolower($request->post('email', '')));

        // Không tiết lộ email có tồn tại hay không (chống user enumeration)
        $user = $this->userModel->findByEmail($email);

        if ($user && !(bool) $user['is_verified']) {
            $this->sendVerificationEmail(
                (int) $user['id'],
                $user['email'],
                $user['name']
            );
        }

        // Luôn hiển thị thông báo này dù email có tồn tại hay không
        Response::setFlash(
            'info',
            'Nếu email tồn tại và chưa được xác minh, '
            . 'chúng tôi đã gửi lại link xác minh.'
        );
        Response::redirect('/login');
    }

    // ----------------------------------------------------------------
    // Private Helpers
    // ----------------------------------------------------------------

    /**
     * Validate dữ liệu đăng ký.
     *
     * @param  string $name
     * @param  string $email
     * @param  string $password
     * @param  string $passwordConfirm
     * @return array<string, string>  Mảng errors. Rỗng nếu hợp lệ.
     */
    private function validateRegistration(
        string $name,
        string $email,
        string $password,
        string $passwordConfirm
    ): array {
        $errors = [];

        // Name
        if (empty($name)) {
            $errors['name'] = 'Họ tên không được để trống.';
        } elseif (mb_strlen($name) < 2) {
            $errors['name'] = 'Họ tên phải có ít nhất 2 ký tự.';
        } elseif (mb_strlen($name) > 150) {
            $errors['name'] = 'Họ tên không được vượt quá 150 ký tự.';
        }

        // Email
        if (empty($email)) {
            $errors['email'] = 'Email không được để trống.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Địa chỉ email không hợp lệ.';
        } elseif (mb_strlen($email) > 255) {
            $errors['email'] = 'Email không được vượt quá 255 ký tự.';
        }

        // Password — theo SRS UC-001: min 8 ký tự, có chữ hoa, có số
        if (empty($password)) {
            $errors['password'] = 'Mật khẩu không được để trống.';
        } elseif (strlen($password) < 8) {
            $errors['password'] = 'Mật khẩu phải có ít nhất 8 ký tự.';
        } elseif (!preg_match('/[A-Z]/', $password)) {
            $errors['password'] = 'Mật khẩu phải chứa ít nhất 1 chữ cái viết hoa.';
        } elseif (!preg_match('/[0-9]/', $password)) {
            $errors['password'] = 'Mật khẩu phải chứa ít nhất 1 chữ số.';
        }

        // Password confirm
        if (empty($errors['password']) && $password !== $passwordConfirm) {
            $errors['password_confirm'] = 'Xác nhận mật khẩu không khớp.';
        }

        return $errors;
    }

    /**
     * Tạo token và gửi email xác minh.
     * Graceful degradation: nếu gửi mail lỗi, user vẫn được tạo.
     *
     * @param  int    $userId
     * @param  string $email
     * @param  string $name
     * @return void
     */
    private function sendVerificationEmail(int $userId, string $email, string $name): void
    {
        $token = bin2hex(random_bytes(32));
        $this->userModel->createVerificationToken($userId, $token);
        $verifyUrl = url('verify-email/' . $token);

        try {
            $emailService = new \App\Services\EmailService();

            $htmlBody = $emailService->renderTemplate('verify-email', [
                'user_name'     => $name,
                'verify_url'    => $verifyUrl,
                'expires_hours' => 24,
            ]);

            $emailService->send(
                to:       $email,
                toName:   $name,
                subject:  '[BugTracker] Xác minh địa chỉ email của bạn',
                htmlBody: $htmlBody
            );

        } catch (\Throwable $e) {
            // Graceful degradation: email lỗi không được crash luồng đăng ký
            // User vẫn được tạo thành công, chỉ không nhận được email
            error_log('[RegisterController] Failed to send verification email: ' . $e->getMessage());
        }
    }
}