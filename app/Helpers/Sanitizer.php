<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Sanitizer – XSS Prevention Helper
 *
 * Cung cấp các hàm escape/sanitize output trước khi render HTML.
 * Dev 3 PHẢI dùng Sanitizer::escape() cho MỌI biến PHP render ra HTML.
 *
 * Quy tắc bất biến (ViewLayer Guide Phần 8.1):
 *   KHÔNG bao giờ dùng echo $var trực tiếp trong view.
 *   LUÔN dùng echo Sanitizer::escape($var).
 *
 * @package App\Helpers
 * @version 1.0.0
 * @see     TDD Backend v1.0.0 – Phần 4.7 (Security Checklist)
 * @see     ViewLayer Implementation Guide v1.0.0 – Phần 8.1
 * @see     Task Assignment v1.0.0 – D1-016
 */
class Sanitizer
{
    /**
     * Escape HTML special characters để chống XSS.
     * Dùng cho mọi output từ PHP ra HTML text content hoặc attribute.
     *
     * @param  mixed  $value  Giá trị cần escape. Tự động convert sang string.
     * @return string         Chuỗi đã escape, an toàn để echo ra HTML.
     */
    public static function escape(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        return htmlspecialchars(
            (string) $value,
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        );
    }

    /**
     * Escape cho JSON data được nhúng trong thẻ <script type="application/json">.
     * Dùng json_encode với JSON_HEX_TAG để tránh XSS qua </script> trong data.
     *
     * @param  mixed $data  Data cần encode.
     * @return string       JSON string an toàn.
     */
    public static function escapeJson(mixed $data): string
    {
        return json_encode(
            $data,
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        ) ?: '{}';
    }

    /**
     * Escape cho URL parameter.
     * Dùng khi build URL từ user input.
     *
     * @param  string $value
     * @return string
     */
    public static function escapeUrl(string $value): string
    {
        return urlencode($value);
    }

    /**
     * Strip toàn bộ HTML tags khỏi string.
     * Dùng khi cần hiển thị plain text từ content có thể chứa HTML.
     *
     * @param  string $value
     * @return string
     */
    public static function stripHtml(string $value): string
    {
        return strip_tags($value);
    }

    /**
     * Sanitize hex color value (dùng cho dynamic CSS — ngoại lệ duy nhất).
     * Theo ViewLayer Guide Phần 8.1: CSS value (dynamic color).
     *
     * @param  string $color  Giá trị hex color. VD: '#2563EB'
     * @param  string $default Giá trị mặc định nếu không hợp lệ.
     * @return string          Hex color hợp lệ hoặc $default.
     */
    public static function sanitizeHexColor(string $color, string $default = '#000000'): string
    {
        $cleaned = preg_replace('/[^#0-9a-fA-F]/', '', $color);

        // Validate: phải là #RGB hoặc #RRGGBB
        if (preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $cleaned)) {
            return strtolower($cleaned);
        }

        return $default;
    }

    /**
     * Sanitize filename — xóa ký tự nguy hiểm.
     * Dùng trước khi lưu tên file vào DB hoặc filesystem.
     *
     * @param  string $filename
     * @return string
     */
    public static function sanitizeFilename(string $filename): string
    {
        // Chỉ giữ: alphanumeric, dash, underscore, dot
        $filename = preg_replace('/[^a-zA-Z0-9\-_\.]/', '_', $filename);

        // Xóa multiple dots liên tiếp (chống path traversal ../../etc/passwd)
        $filename = preg_replace('/\.{2,}/', '.', $filename);

        // Giới hạn độ dài
        return mb_substr($filename, 0, 100);
    }

    /**
     * Truncate text dài và thêm "..." ở cuối.
     * Dùng trong Issue card title preview.
     *
     * @param  string $text
     * @param  int    $maxLength
     * @param  string $suffix
     * @return string
     */
    public static function truncate(string $text, int $maxLength = 100, string $suffix = '...'): string
    {
        if (mb_strlen($text) <= $maxLength) {
            return $text;
        }

        return mb_substr($text, 0, $maxLength - mb_strlen($suffix)) . $suffix;
    }
}