<?php

declare(strict_types=1);

namespace App\Controllers\Auth;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Helpers\Csrf;
use App\Helpers\Sanitizer;
use App\Models\User;
use App\Services\EmailService;
use App\Core\Logger;

/**
 * PasswordResetController – Xử lý luồng quên mật khẩu.
 *
 * Luồng: showForgotForm → sendResetLink → showResetForm → resetPassword
 *
 * Security notes:
 * - Token: bin2hex(random_bytes(32)) = 64 chars, entropy 256 bits (TDD 1.4.1)
 * - TTL: 1 giờ (TOKEN_TTL_PASSWORD_RESET trong config)
 * - Single-use: ghi used_at ngay sau khi dùng, giữ audit trail (không xóa)
 * - So sánh token: hash_equals() – chống timing attack (TDD 1.4.3)
 * - Chống user enumeration: Luôn hiển thị "check email" dù email có tồn tại hay không
 *
 * @version 1.0.1
 *
 * CHANGELOG v1.0.1:
 *   - FIX: validatePassword() dùng !== thay vì hash_equals() để so sánh password confirm.
 *     hash_equals() dành để so sánh secret token từ DB (chống timing attack trên secret).
 *     Password confirm là input user nhập trong cùng 1 request — không phải secret,
 *     không cần constant-time comparison, dùng !== là đúng và rõ ràng hơn.
 */
class PasswordResetController
{
    private Request $request;
    private Response $response;
    private User $userModel;
    private EmailService $emailService;
    private Logger $logger;

    public function __construct(
        Request $request,
        Response $response,
        User $userModel,
        EmailService $emailService,
        Logger $logger
    ) {
        $this->request      = $request;
        $this->response     = $response;
        $this->userModel    = $userModel;
        $this->emailService = $emailService;
        $this->logger       = $logger;
    }

    // =========================================================================
    // BƯỚC 1: Hiển thị form nhập email
    // =========================================================================

    /**
     * GET /forgot-password
     */
    public function showForgotForm(): void
    {
        $this->response->view('auth/forgot-password', [
            'pageId'     => 'forgot-password',
            'pageTitle'  => 'Quên mật khẩu',
            'csrf_token' => Csrf::generateToken(),
        ]);
    }

    // =========================================================================
    // BƯỚC 2: Xử lý yêu cầu gửi email reset
    // =========================================================================

    /**
     * POST /forgot-password
     *
     * WHY luôn trả về cùng một thông báo dù email có tồn tại hay không:
     * Chống user enumeration attack — kẻ tấn công không biết email nào đã đăng ký.
     */
    public function sendResetLink(): void
    {
        // 1. Validate CSRF
        if (!Csrf::validateToken($this->request->post('csrf_token'))) {
            $this->response->setFlash('error', 'Phiên làm việc hết hạn. Vui lòng thử lại.');
            $this->response->redirect('/forgot-password');
            return;
        }

        $email = trim($this->request->post('email', ''));

        // 2. Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->response->setFlash('error', 'Địa chỉ email không hợp lệ.');
            $this->response->redirect('/forgot-password');
            return;
        }

        // 3. Hiển thị thông báo thành công NGAY (chống user enumeration)
        $this->response->setFlash(
            'success',
            'Nếu địa chỉ email này tồn tại trong hệ thống, bạn sẽ nhận được hướng dẫn đặt lại mật khẩu trong vài phút.'
        );

        // 4. Tìm user — nếu không có thì kết thúc im lặng
        $user = $this->userModel->findByEmail($email);
        if (!$user) {
            $this->response->redirect('/forgot-password');
            return;
        }

        // 5. Sinh token an toàn (TDD 1.4.1)
        $token    = bin2hex(random_bytes(32)); // 64 chars hex
        $tokenTtl = (int) ($_ENV['TOKEN_TTL_PASSWORD_RESET'] ?? 3600); // 1 giờ

        // 6. Xóa token cũ của user này (nếu có) + lưu token mới
        $this->userModel->deletePasswordResetByUserId($user['id']);
        $this->userModel->createPasswordResetToken(
            userId: $user['id'],
            token: $token,
            expiresAt: date('Y-m-d H:i:s', time() + $tokenTtl)
        );

        // 7. Render template và gửi email
        $resetUrl = rtrim($_ENV['APP_URL'], '/') . '/reset-password?token=' . $token;
        $htmlBody = $this->emailService->renderTemplate('password-reset', [
            'user_name'     => $user['name'],
            'reset_url'     => $resetUrl,
            'expires_hours' => (int) ($tokenTtl / 3600),
        ]);

        $this->emailService->send(
            to:       $user['email'],
            toName:   $user['name'],
            subject:  '[BugTracker] Đặt lại mật khẩu của bạn',
            htmlBody: $htmlBody
        );

        $this->logger->info(
            "Password reset link sent for user_id={$user['id']}",
            'PasswordResetController'
        );

        $this->response->redirect('/forgot-password');
    }

    // =========================================================================
    // BƯỚC 3: Hiển thị form đặt mật khẩu mới
    // =========================================================================

    /**
     * GET /reset-password?token={token}
     */
    public function showResetForm(): void
    {
        $token = trim($this->request->get('token', ''));

        if (empty($token)) {
            $this->response->setFlash('error', 'Link đặt lại mật khẩu không hợp lệ.');
            $this->response->redirect('/forgot-password');
            return;
        }

        $resetRecord = $this->userModel->findValidPasswordResetToken($token);

        if (!$resetRecord) {
            $this->response->setFlash(
                'error',
                'Link đặt lại mật khẩu không hợp lệ hoặc đã hết hạn. Vui lòng yêu cầu link mới.'
            );
            $this->response->redirect('/forgot-password');
            return;
        }

        $this->response->view('auth/reset-password', [
            'pageId'     => 'reset-password',
            'pageTitle'  => 'Đặt lại mật khẩu',
            'token'      => Sanitizer::escape($token),
            'csrf_token' => Csrf::generateToken(),
        ]);
    }

    // =========================================================================
    // BƯỚC 4: Xử lý đặt mật khẩu mới
    // =========================================================================

    /**
     * POST /reset-password
     */
    public function resetPassword(): void
    {
        // 1. Validate CSRF
        if (!Csrf::validateToken($this->request->post('csrf_token'))) {
            $this->response->setFlash('error', 'Phiên làm việc hết hạn. Vui lòng thử lại.');
            $this->response->redirect('/forgot-password');
            return;
        }

        $token           = trim($this->request->post('token', ''));
        $password        = $this->request->post('password', '');
        $passwordConfirm = $this->request->post('password_confirm', '');

        // 2. Validate token (sơ bộ)
        if (empty($token) || strlen($token) !== 64) {
            $this->response->setFlash('error', 'Token không hợp lệ.');
            $this->response->redirect('/forgot-password');
            return;
        }

        // 3. Validate password strength (SRS 3.1.2)
        $passwordError = $this->validatePassword($password, $passwordConfirm);
        if ($passwordError !== null) {
            $this->response->setFlash('error', $passwordError);
            $this->response->redirect('/reset-password?token=' . urlencode($token));
            return;
        }

        // 4. Tìm token hợp lệ trong DB (chưa dùng, chưa hết hạn)
        $resetRecord = $this->userModel->findValidPasswordResetToken($token);

        if (!$resetRecord) {
            $this->response->setFlash(
                'error',
                'Link đặt lại mật khẩu không hợp lệ hoặc đã hết hạn.'
            );
            $this->response->redirect('/forgot-password');
            return;
        }

        // 5. So sánh token bằng hash_equals() – CHỐNG TIMING ATTACK (TDD 1.4.3)
        // WHY hash_equals() ở đây (không phải bước 3):
        //   Token này là SECRET lấy từ DB — kẻ tấn công có thể đo thời gian phản hồi
        //   để đoán token từng ký tự nếu dùng ===. hash_equals() so sánh constant-time.
        if (!hash_equals($resetRecord['token'], $token)) {
            $this->logger->warning(
                "Token mismatch attempt for user_id={$resetRecord['user_id']}",
                'PasswordResetController'
            );
            $this->response->setFlash('error', 'Token không hợp lệ.');
            $this->response->redirect('/forgot-password');
            return;
        }

        // 6. Hash mật khẩu mới (bcrypt cost=12 theo TDD)
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

        // 7. Cập nhật mật khẩu user
        $this->userModel->updatePassword($resetRecord['user_id'], $hashedPassword);

        // 8. Đánh dấu token đã dùng – SINGLE-USE (TDD 1.4.2)
        // WHY ghi used_at thay vì xóa: Giữ audit trail, token không thể dùng lại
        $this->userModel->markPasswordResetTokenUsed($resetRecord['id']);

        $this->logger->info(
            "Password reset successful for user_id={$resetRecord['user_id']}",
            'PasswordResetController'
        );

        $this->response->setFlash(
            'success',
            'Mật khẩu đã được đặt lại thành công. Vui lòng đăng nhập.'
        );
        $this->response->redirect('/login');
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    /**
     * Validate mật khẩu mới theo quy tắc SRS 3.1.1:
     * - Tối thiểu 8 ký tự
     * - Có ít nhất 1 chữ hoa
     * - Có ít nhất 1 chữ số
     * - Hai lần nhập phải khớp
     *
     * WHY dùng !== thay vì hash_equals() để so sánh password confirm:
     *   hash_equals() dành cho việc so sánh SECRET TOKEN từ DB (chống timing attack
     *   khi kẻ tấn công đo thời gian phản hồi từ ngoài vào).
     *   Password confirm là dữ liệu user tự nhập trong cùng 1 request — không phải
     *   secret, không có timing oracle, nên dùng !== là đúng và rõ ràng hơn.
     *
     * @return string|null null nếu hợp lệ, message lỗi nếu không
     */
    private function validatePassword(string $password, string $confirm): ?string
    {
        if (strlen($password) < 8) {
            return 'Mật khẩu phải có ít nhất 8 ký tự.';
        }

        if (!preg_match('/[A-Z]/', $password)) {
            return 'Mật khẩu phải có ít nhất 1 chữ hoa.';
        }

        if (!preg_match('/[0-9]/', $password)) {
            return 'Mật khẩu phải có ít nhất 1 chữ số.';
        }

        // FIX v1.0.1: Dùng !== thay hash_equals() — password confirm không phải secret
        if ($password !== $confirm) {
            return 'Hai mật khẩu không khớp nhau.';
        }

        return null;
    }
}