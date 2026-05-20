<?php

declare(strict_types=1);

namespace App\Controllers\Public;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

/**
 * LandingController
 *
 * Xử lý trang chủ công khai (Landing Page).
 * Không yêu cầu đăng nhập — không có middleware Auth.
 *
 * Nếu user đã đăng nhập → redirect thẳng vào Dashboard
 * để tránh hiển thị Landing Page cho người dùng đã có tài khoản.
 *
 * Routes:
 * GET / → index()
 *
 * @package App\Controllers\Public
 * @version 1.0.0
 */
class LandingController
{
    /**
     * Hiển thị Landing Page.
     * GET /
     *
     * @param  Request $request  Inject từ Router.
     * @return void
     */
    public function index(Request $request): void
    {
        // Nếu đã đăng nhập → không cần xem Landing Page
        if (Session::get('user_id')) { // Dùng Session::get cho chắc chắn theo chuẩn file Core
            Response::redirect('/dashboard');
            return; // Ngăn chặn thực thi tiếp
        }

        Response::view('landing/index', [
            'pageTitle'   => 'BugTracker – Hệ Thống Quản Lý Lỗi Làm Việc Nhóm',
            'pageId'      => 'landing',
            'metaDesc'    => 'BugTracker giúp nhóm phát triển phần mềm theo dõi, '
                           . 'phân loại và giải quyết lỗi hiệu quả. '
                           . 'Miễn phí, dễ dùng, không cần cài đặt.',
        ]);
    }

    /**
     * Hiển thị trang Điều khoản sử dụng.
     * GET /terms
     *
     * @param  Request $request
     * @return void
     */
    public function terms(Request $request): void
    {
        Response::view('landing/terms', [
            'pageTitle' => 'Điều khoản sử dụng – BugTracker',
            'pageId'    => 'terms',
        ]);
    }
}