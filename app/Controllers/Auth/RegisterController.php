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
 * @version 1.0.1
 * @see     SRS v1.0.0 – UC-001, UC-002, UC-042
 * @see     Task Assignment v1.0.0 – D1-013
 *
 * CHANGELOG v1.0.1:
 *   - FIX: Bỏ auto-login cho luồng đăng ký thường — user phải verify email
 *     trước khi có session, tránh vi phạm SRS UC-003 (is_verified=1 mới được login).
 *   - FIX: Chỉ loginUser() trong nhánh invite (email đã chứng minh qua link mời).
 *   - IMPROVE: verifyEmail() kiểm tra is_verified trước khi xử lý lại.
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
            // Prefill email nếu đến từ invite link (Nhánh C trong InvitationController)
            'invite_email' => $request->get('invite', ''),
        ]);

        Session::remove('_validation_errors');
    }

    /**
     * Xử lý submit form đăng ký.
     * POST /register
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
                // is_verified=0 mặc định — phải verify email trước khi login
            ]);

            // Bước 6: Kịch bản Đăng ký qua Link mời (Invite Flow)
            //
            // WHY loginUser() CHỈ ở đây, không ở luồng thường:
            //   - Nhánh invite: email đã được chứng minh qua link mời → is_verified
            //     sẽ được set=1 bởi processAcceptance() → an toàn để login ngay.
            //   - Nhánh thường: is_verified=0, user PHẢI verify email trước.
            //     Login với is_verified=0 vi phạm SRS UC-003.
            //
            // processPendingInvitation() sẽ:
            //   1. Thêm user vào workspace_members
            //   2. Set is_verified=1 và onboarding_completed=true
            //   3. Gọi Response::redirect('/dashboard') (exit())
            $pendingToken = (string) Session::get('pending_invite_token', '');

            if (!empty($pendingToken)) {
                // Có pending invite → login trước, sau đó xử lý invite
                $user = $this->userModel->findById($userId);
                Session::loginUser($user);

                $invitationController = new \App\Controllers\Workspace\InvitationController();
                $hasProcessedInvite   = $invitationController->processPendingInvitation($userId, $email);

                if ($hasProcessedInvite) {
                    return; // processAcceptance() đã redirect → exit()
                }

                // Nếu invite fail (hết hạn, sai email...) → xóa session, fallback bình thường
                // Flash error đã được set bởi processPendingInvitation()
                Session::destroy();
            }

            // Bước 7: Luồng đăng ký thường — KHÔNG loginUser() ở đây
            // Gửi email xác minh, yêu cầu verify trước khi đăng nhập
            $this->sendVerificationEmail($userId, $email, $name);

            Response::setFlash(
                'success',
                'Đăng ký thành công! Vui lòng kiểm tra email <strong>' . htmlspecialchars($email) . '</strong>'
                . ' để xác minh tài khoản trước khi đăng nhập.'
            );
            Response::redirect('/login');

        } catch (\PDOException $e) {
            error_log('[RegisterController] Create user failed: ' . $e->getMessage());
            Response::setFlash('error', 'Đã xảy ra lỗi. Vui lòng thử lại.');
            Response::redirect('/register');
        }
    }

    /**
     * Xử lý click link xác minh email.
     * GET /verify-email/{token}
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

        // IMPROVE: Kiểm tra user đã verify chưa — tránh thông báo confusing khi click link lần 2
        // (token đã bị xóa sau lần verify đầu → findVerificationToken trả null → rơi vào lỗi "hết hạn"
        //  Nhánh này chỉ chạy khi token vẫn còn trong DB nhưng user đã verified bởi cơ chế khác)
        $user = $this->userModel->findById((int) $record['user_id']);
        if ($user && (bool) $user['is_verified']) {
            // Xóa token thừa nếu còn sót
            $this->userModel->deleteVerificationToken($token);
            Response::setFlash('info', 'Tài khoản của bạn đã được xác minh rồi. Vui lòng đăng nhập.');
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