<?php

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Models\EmailQueue;
use App\Services\EmailService;
use App\Core\Logger;

/**
 * EmailQueueController
 *
 * Trang quản trị nội bộ để xem và retry các email bị lỗi.
 * Thay thế cho Cronjob trên InfinityFree (không có Cronjob).
 * Chỉ truy cập được với role Owner hoặc Admin (enforce bởi RbacMiddleware).
 *
 * Route:
 *   GET  /admin/email-queue          → index()
 *   POST /admin/email-queue/{id}/retry → retry()
 *   POST /admin/email-queue/retry-all  → retryAll()
 *   POST /admin/email-queue/cleanup    → cleanup()
 *
 * @author  Dev 1
 * @version 1.0.0
 */
class EmailQueueController
{
    private EmailQueue   $email_queue;
    private EmailService $email_service;

    // Batch size mỗi lần retry-all để tránh timeout trên InfinityFree
    private const BATCH_SIZE = 10;

    // Số ngày giữ lại email failed trước khi cleanup
    private const CLEANUP_DAYS = 7;

    public function __construct()
    {
        $this->email_queue   = new EmailQueue();
        $this->email_service = new EmailService();
    }

    /**
     * Hiển thị danh sách email thất bại trong hàng đợi.
     */
    public function index(Request $request, Response $response): void
    {
        $page    = max(1, (int) $request->get('page', 1));
        $per_page = 20;
        $offset  = ($page - 1) * $per_page;

        $failed_emails = $this->email_queue->getFailedEmails($per_page, $offset);
        $total         = $this->email_queue->countFailed();

        $response->view('admin/email-queue', [
            'page_title'    => 'Quản lý Email Queue',
            'page_id'       => 'admin-email-queue',
            'failed_emails' => $failed_emails,
            'total'         => $total,
            'page'          => $page,
            'per_page'      => $per_page,
        ]);
    }

    /**
     * Gửi lại một email cụ thể theo ID.
     *
     * POST /admin/email-queue/{id}/retry
     */
    public function retry(Request $request, Response $response, int $id): void
    {
        $record = $this->email_queue->findById($id);

        if ($record === null) {
            $response->setFlash('error', 'Email không tồn tại trong hàng đợi.');
            $response->redirect('/admin/email-queue');
            return;
        }

        $success = $this->attemptSend($record);

        if ($success) {
            $response->setFlash('success', "Email #{$id} đã được gửi lại thành công.");
        } else {
            $response->setFlash('error', "Không thể gửi lại email #{$id}. Kiểm tra System Log để biết thêm.");
        }

        $response->redirect('/admin/email-queue');
    }

    /**
     * Gửi lại tối đa BATCH_SIZE email thất bại cùng lúc.
     * Giới hạn batch để tránh vượt max_execution_time trên InfinityFree.
     *
     * POST /admin/email-queue/retry-all
     */
    public function retryAll(Request $request, Response $response): void
    {
        $records = $this->email_queue->getFailedEmails(self::BATCH_SIZE, 0);

        $success_count = 0;
        $fail_count    = 0;

        foreach ($records as $record) {
            if ($this->attemptSend($record)) {
                $success_count++;
            } else {
                $fail_count++;
            }
        }

        $response->setFlash(
            'success',
            "Kết quả retry: {$success_count} thành công, {$fail_count} thất bại (batch tối đa " . self::BATCH_SIZE . ")."
        );
        $response->redirect('/admin/email-queue');
    }

    /**
     * Xóa các email thất bại cũ hơn CLEANUP_DAYS ngày.
     * Theo TDD Phần 2.4: dùng LIMIT để tránh timeout.
     *
     * POST /admin/email-queue/cleanup
     */
    public function cleanup(Request $request, Response $response): void
    {
        $deleted = $this->email_queue->deleteOldFailed(self::CLEANUP_DAYS, 200);

        $response->setFlash('success', "Đã xóa {$deleted} email lỗi cũ hơn " . self::CLEANUP_DAYS . " ngày.");
        $response->redirect('/admin/email-queue');
    }

    // -------------------------------------------------------------------------
    // Private helper
    // -------------------------------------------------------------------------

    /**
     * Thực hiện gửi email từ bản ghi queue, cập nhật trạng thái tương ứng.
     *
     * @param  array $record Bản ghi từ bảng email_queue
     * @return bool  true nếu gửi thành công
     */
    private function attemptSend(array $record): bool
    {
        try {
            $this->email_service->send(
                $record['to_email'],
                $record['to_name'] ?? '',
                $record['subject'],
                $record['body_html']
            );

            $this->email_queue->markAsSent((int) $record['id']);
            return true;

        } catch (\Exception $e) {
            $this->email_queue->markAsFailed((int) $record['id'], $e->getMessage());
            Logger::error(
                'EmailQueue retry failed',
                'EmailQueueController',
                $e->getTraceAsString()
            );
            return false;
        }
    }
}