<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Core\Database;

/**
 * SlugGenerator – URL-friendly Slug Generator
 *
 * Chuyển đổi chuỗi tiếng Việt có dấu thành slug URL-friendly.
 * Hỗ trợ kiểm tra unique slug trong DB và tự động thêm suffix số.
 *
 * @package App\Helpers
 * @version 1.0.0
 * @see     Task Assignment v1.0.0 – D1-016
 */
class SlugGenerator
{
    /**
     * Bảng chuyển đổi ký tự tiếng Việt có dấu → không dấu.
     *
     * @var array<string, string>
     */
    private static array $vietnameseMap = [
        'à' => 'a', 'á' => 'a', 'ả' => 'a', 'ã' => 'a', 'ạ' => 'a',
        'ă' => 'a', 'ằ' => 'a', 'ắ' => 'a', 'ẳ' => 'a', 'ẵ' => 'a', 'ặ' => 'a',
        'â' => 'a', 'ầ' => 'a', 'ấ' => 'a', 'ẩ' => 'a', 'ẫ' => 'a', 'ậ' => 'a',
        'è' => 'e', 'é' => 'e', 'ẻ' => 'e', 'ẽ' => 'e', 'ẹ' => 'e',
        'ê' => 'e', 'ề' => 'e', 'ế' => 'e', 'ể' => 'e', 'ễ' => 'e', 'ệ' => 'e',
        'ì' => 'i', 'í' => 'i', 'ỉ' => 'i', 'ĩ' => 'i', 'ị' => 'i',
        'ò' => 'o', 'ó' => 'o', 'ỏ' => 'o', 'õ' => 'o', 'ọ' => 'o',
        'ô' => 'o', 'ồ' => 'o', 'ố' => 'o', 'ổ' => 'o', 'ỗ' => 'o', 'ộ' => 'o',
        'ơ' => 'o', 'ờ' => 'o', 'ớ' => 'o', 'ở' => 'o', 'ỡ' => 'o', 'ợ' => 'o',
        'ù' => 'u', 'ú' => 'u', 'ủ' => 'u', 'ũ' => 'u', 'ụ' => 'u',
        'ư' => 'u', 'ừ' => 'u', 'ứ' => 'u', 'ử' => 'u', 'ữ' => 'u', 'ự' => 'u',
        'ỳ' => 'y', 'ý' => 'y', 'ỷ' => 'y', 'ỹ' => 'y', 'ỵ' => 'y',
        'đ' => 'd',
        // Uppercase
        'À' => 'a', 'Á' => 'a', 'Ả' => 'a', 'Ã' => 'a', 'Ạ' => 'a',
        'Ă' => 'a', 'Ằ' => 'a', 'Ắ' => 'a', 'Ẳ' => 'a', 'Ẵ' => 'a', 'Ặ' => 'a',
        'Â' => 'a', 'Ầ' => 'a', 'Ấ' => 'a', 'Ẩ' => 'a', 'Ẫ' => 'a', 'Ậ' => 'a',
        'È' => 'e', 'É' => 'e', 'Ẻ' => 'e', 'Ẽ' => 'e', 'Ẹ' => 'e',
        'Ê' => 'e', 'Ề' => 'e', 'Ế' => 'e', 'Ể' => 'e', 'Ễ' => 'e', 'Ệ' => 'e',
        'Ì' => 'i', 'Í' => 'i', 'Ỉ' => 'i', 'Ĩ' => 'i', 'Ị' => 'i',
        'Ò' => 'o', 'Ó' => 'o', 'Ỏ' => 'o', 'Õ' => 'o', 'Ọ' => 'o',
        'Ô' => 'o', 'Ồ' => 'o', 'Ố' => 'o', 'Ổ' => 'o', 'Ỗ' => 'o', 'Ộ' => 'o',
        'Ơ' => 'o', 'Ờ' => 'o', 'Ớ' => 'o', 'Ở' => 'o', 'Ỡ' => 'o', 'Ợ' => 'o',
        'Ù' => 'u', 'Ú' => 'u', 'Ủ' => 'u', 'Ũ' => 'u', 'Ụ' => 'u',
        'Ư' => 'u', 'Ừ' => 'u', 'Ứ' => 'u', 'Ử' => 'u', 'Ữ' => 'u', 'Ự' => 'u',
        'Ỳ' => 'y', 'Ý' => 'y', 'Ỷ' => 'y', 'Ỹ' => 'y', 'Ỵ' => 'y',
        'Đ' => 'd',
    ];

    /**
     * Chuyển đổi chuỗi thành URL slug.
     *
     * Ví dụ:
     *   'Công ty TNHH ABC' → 'cong-ty-tnhh-abc'
     *   'BugTracker v2.0'  → 'bugtracker-v2-0'
     *
     * @param  string $text
     * @param  int    $maxLength Độ dài tối đa của slug.
     * @return string
     */
    public static function make(string $text, int $maxLength = 150): string
    {
        // Bước 1: Chuyển tiếng Việt có dấu → không dấu
        $text = strtr($text, self::$vietnameseMap);

        // Bước 2: Lowercase
        $text = strtolower($text);

        // Bước 3: Thay thế ký tự không phải alphanumeric bằng dash
        $text = preg_replace('/[^a-z0-9\-]/', '-', $text);

        // Bước 4: Xóa multiple dashes liên tiếp
        $text = preg_replace('/-{2,}/', '-', $text);

        // Bước 5: Trim dashes ở đầu và cuối
        $text = trim($text, '-');

        // Bước 6: Giới hạn độ dài
        if (strlen($text) > $maxLength) {
            $text = substr($text, 0, $maxLength);
            $text = rtrim($text, '-');
        }

        return $text ?: 'untitled';
    }

    /**
     * Tạo slug unique trong bảng DB.
     * Nếu slug đã tồn tại → tự động thêm suffix: -2, -3, -4...
     *
     * @param  string $text         Chuỗi gốc cần tạo slug.
     * @param  string $table        Tên bảng DB cần kiểm tra.
     * @param  string $column       Tên cột chứa slug. Mặc định: 'slug'.
     * @param  int|null $excludeId  ID bản ghi hiện tại (khi edit — bỏ qua chính nó).
     * @return string               Slug unique.
     */
    public static function makeUnique(
        string $text,
        string $table,
        string $column = 'slug',
        ?int $excludeId = null
    ): string {
        $baseSlug = self::make($text);
        $slug     = $baseSlug;
        $counter  = 2;

        $db = Database::getInstance();

        while (true) {
            $sql    = "SELECT COUNT(*) as cnt FROM `{$table}` WHERE `{$column}` = :slug AND deleted_at IS NULL";
            $params = [':slug' => $slug];

            if ($excludeId !== null) {
                $sql   .= ' AND id != :exclude_id';
                $params[':exclude_id'] = $excludeId;
            }

            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetch();

            if ((int) $result['cnt'] === 0) {
                break; // Slug unique — dừng vòng lặp
            }

            // Thêm suffix và thử lại
            $slug = $baseSlug . '-' . $counter;
            $counter++;

            // Giới hạn vòng lặp để tránh infinite loop
            if ($counter > 999) {
                $slug = $baseSlug . '-' . bin2hex(random_bytes(3));
                break;
            }
        }

        return $slug;
    }
}