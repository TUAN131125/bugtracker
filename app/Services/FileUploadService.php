<?php
// /app/Services/FileUploadService.php

declare(strict_types=1);

namespace App\Services;

use App\Core\Logger;

/**
 * FileUploadService
 *
 * Xử lý toàn bộ vòng đời của file đính kèm:
 * validate → store (ngoài webroot) → serve qua PHP proxy
 *
 * WHY không dùng thư mục public_html:
 * Nếu file lưu trong public_html, user có thể truy cập trực tiếp qua URL
 * mà bypass hoàn toàn kiểm tra quyền của PHP. Đặt ngoài webroot buộc
 * mọi request phải đi qua FileController::serve() để kiểm tra permission.
 */
class FileUploadService
{
    private Logger $logger;

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

    // =========================================================================
    // PUBLIC: store()
    // Validate + move file từ /tmp vào /storage/attachments/{ws_id}/{issue_id}/
    // =========================================================================

    /**
     * @param array  $file        Phần tử từ $_FILES (đã qua Request object)
     * @param int    $workspaceId Dùng để tạo đường dẫn phân cấp
     * @param int    $issueId     Dùng để tạo đường dẫn phân cấp
     * @return array{
     *   original_name: string,
     *   stored_name: string,
     *   file_path: string,
     *   mime_type: string,
     *   file_size: int
     * }
     * @throws \RuntimeException Khi file không hợp lệ hoặc không thể lưu
     */
    public function store(array $file, int $workspaceId, int $issueId): array
    {
        // --- Bước 1: Kiểm tra lỗi upload từ PHP ---
        $this->validateUploadError($file);

        // --- Bước 2: Validate kích thước ---
        $this->validateFileSize($file['size']);

        // --- Bước 3: Validate MIME type thực sự bằng finfo ---
        // WHY dùng finfo_file thay vì $file['type']:
        // $_FILES['type'] do BROWSER cung cấp, có thể bị giả mạo.
        // finfo_file đọc magic bytes thực sự của file để xác định MIME.
        $realMime = $this->detectMimeType($file['tmp_name']);
        $this->validateMimeType($realMime);

        // --- Bước 4: Tạo thư mục đích nếu chưa có ---
        $targetDir = $this->ensureDirectory($workspaceId, $issueId);

        // --- Bước 5: Tạo tên file an toàn (không giữ tên gốc) ---
        // WHY rename: tên gốc có thể chứa ký tự nguy hiểm, path traversal
        // ('../../../etc/passwd'), hoặc trùng tên gây ghi đè file cũ.
        $extension   = ALLOWED_MIME_TYPES[$realMime] ?? 'bin';
        $storedName  = $this->generateStoredName($extension);
        $targetPath  = $targetDir . '/' . $storedName;

        // --- Bước 6: Move file từ /tmp vào storage ---
        // WHY move_uploaded_file thay vì rename/copy:
        // move_uploaded_file() chỉ hoạt động với file đến từ HTTP upload,
        // giúp chặn một số tấn công local file inclusion.
        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            $this->logger->error(
                'Không thể move file upload vào storage',
                'FileUploadService',
                "target: {$targetPath}, is_writable: " . (is_writable($targetDir) ? 'yes' : 'no')
            );
            throw new \RuntimeException(
                'Không thể lưu file đính kèm. Vui lòng thử lại.'
            );
        }

        // --- Bước 7: Tạo thumbnail nếu là ảnh (tiết kiệm Inode: 1 folder chung) ---
        // WHY tạo thumbnail: Phục vụ preview trong gallery mà không load ảnh full-size
        // Giảm bandwidth trên InfinityFree.
        if (in_array($realMime, ['image/jpeg', 'image/png', 'image/gif'])) {
            $this->createThumbnail($targetPath, $targetDir, $storedName, $realMime);
        }

        // Relative path từ /storage/ — lưu vào DB, không lưu absolute path
        $relativePath = "attachments/{$workspaceId}/{$issueId}/{$storedName}";

        return [
            'original_name' => $this->sanitizeOriginalName($file['name']),
            'stored_name'   => $storedName,
            'file_path'     => $relativePath,
            'mime_type'     => $realMime,
            'file_size'     => (int) $file['size'],
        ];
    }

    // =========================================================================
    // PUBLIC: serve()
    // Đọc file từ storage và stream ra browser SAU KHI đã kiểm tra quyền
    // (Việc kiểm tra quyền thực hiện ở FileController, không phải ở đây)
    // =========================================================================

    /**
     * @param string $relativePath  Đường dẫn tương đối lưu trong DB
     *                              VD: "attachments/1/5/abc123.pdf"
     * @param string $originalName  Tên file gốc để set Content-Disposition
     * @param string $mimeType      MIME type lưu trong DB
     */
    public function serve(string $relativePath, string $originalName, string $mimeType): void
    {
        $absolutePath = STORAGE_PATH . '/' . $relativePath;

        // --- Security: Ngăn Path Traversal Attack ---
        // WHY: Nếu $relativePath = "../../config/.env", realpath() sẽ resolve
        // ra đường dẫn thật. Ta kiểm tra nó có bắt đầu bằng STORAGE_PATH không.
        $realPath = realpath($absolutePath);
        if ($realPath === false || !str_starts_with($realPath, realpath(STORAGE_PATH))) {
            http_response_code(403);
            exit('Truy cập bị từ chối.');
        }

        if (!file_exists($realPath)) {
            http_response_code(404);
            exit('File không tồn tại.');
        }

        // --- Stream file ra browser ---
        $safeName = rawurlencode($originalName);

        header('Content-Type: ' . $mimeType);
        header('Content-Length: ' . filesize($realPath));
        // WHY inline cho ảnh và PDF: hiện thị ngay trong tab thay vì download
        // WHY attachment cho các loại khác: buộc download, an toàn hơn
        if (in_array($mimeType, ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'])) {
            header("Content-Disposition: inline; filename=\"{$safeName}\"");
        } else {
            header("Content-Disposition: attachment; filename=\"{$safeName}\"");
        }
        // Không cache file attachment — tránh trình duyệt hiện file cũ sau khi xóa
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');

        // WHY readfile() thay vì echo file_get_contents():
        // readfile() stream trực tiếp từ disk → browser mà không load toàn bộ
        // vào RAM. Quan trọng trên InfinityFree vì giới hạn memory_limit.
        readfile($realPath);
        exit;
    }

    // =========================================================================
    // PUBLIC: delete()
    // Xóa file vật lý khỏi storage (gọi khi soft-delete attachment trong DB)
    // =========================================================================

    public function delete(string $relativePath): bool
    {
        $absolutePath = STORAGE_PATH . '/' . $relativePath;
        $realPath = realpath($absolutePath);

        // Security check tương tự serve()
        if ($realPath === false || !str_starts_with($realPath, realpath(STORAGE_PATH))) {
            $this->logger->warning('Cố xóa file ngoài storage path', 'FileUploadService', $relativePath);
            return false;
        }

        if (!file_exists($realPath)) {
            // File đã không còn — coi như xóa thành công
            return true;
        }

        // Xóa thumbnail nếu có (tên: {storedName_without_ext}_thumb.jpg)
        $thumbPath = $this->getThumbnailPath($realPath);
        if ($thumbPath && file_exists($thumbPath)) {
            @unlink($thumbPath);
        }

        return unlink($realPath);
    }

    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================

    private function validateUploadError(array $file): void
    {
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE   => 'File vượt quá giới hạn upload_max_filesize của server.',
            UPLOAD_ERR_FORM_SIZE  => 'File vượt quá giới hạn MAX_FILE_SIZE trong form.',
            UPLOAD_ERR_PARTIAL    => 'File chỉ được upload một phần. Vui lòng thử lại.',
            UPLOAD_ERR_NO_FILE    => 'Không có file nào được gửi lên.',
            UPLOAD_ERR_NO_TMP_DIR => 'Thư mục tạm của server bị thiếu.',
            UPLOAD_ERR_CANT_WRITE => 'Không thể ghi file vào disk server.',
            UPLOAD_ERR_EXTENSION  => 'Một PHP extension đã chặn upload.',
        ];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $msg = $errorMessages[$file['error']] ?? 'Lỗi upload không xác định.';
            throw new \RuntimeException($msg);
        }
    }

    private function validateFileSize(int $size): void
    {
        if ($size > MAX_FILE_SIZE) {
            $maxMB = MAX_FILE_SIZE / 1024 / 1024;
            throw new \RuntimeException("File vượt quá giới hạn {$maxMB}MB.");
        }

        if ($size === 0) {
            throw new \RuntimeException('File rỗng (0 bytes) không được phép.');
        }
    }

    private function detectMimeType(string $tmpPath): string
    {
        // WHY finfo: đây là cách duy nhất đáng tin cậy để biết loại file thật
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($tmpPath);

        if ($mime === false) {
            throw new \RuntimeException('Không thể xác định loại file.');
        }

        return $mime;
    }

    private function validateMimeType(string $mime): void
    {
        if (!array_key_exists($mime, ALLOWED_MIME_TYPES)) {
            throw new \RuntimeException(
                "Loại file '{$mime}' không được hỗ trợ. " .
                "Chỉ chấp nhận: JPG, PNG, GIF, PDF, TXT, ZIP."
            );
        }
    }

    /**
     * Tạo thư mục {ws_id}/{issue_id} nếu chưa tồn tại
     * WHY 0755: owner có full quyền, group/other chỉ read+execute.
     *           Đủ để PHP web process (www-data) ghi file.
     */
    private function ensureDirectory(int $workspaceId, int $issueId): string
    {
        $dir = ATTACHMENTS_PATH . "/{$workspaceId}/{$issueId}";

        if (!is_dir($dir)) {
            if (!mkdir($dir, 0755, true)) {
                $this->logger->error(
                    'Không thể tạo thư mục storage',
                    'FileUploadService',
                    $dir
                );
                throw new \RuntimeException('Không thể tạo thư mục lưu trữ.');
            }
        }

        return $dir;
    }

    /**
     * Sinh tên file ngẫu nhiên an toàn
     * Format: {timestamp}_{random8chars}.{ext}
     * WHY thêm timestamp: dễ debug khi cần biết file được upload lúc nào
     */
    private function generateStoredName(string $extension): string
    {
        $random = bin2hex(random_bytes(8)); // 16 ký tự hex
        $ts     = time();
        return "{$ts}_{$random}.{$extension}";
    }

    /**
     * Sanitize tên file gốc để lưu vào DB (chỉ dùng cho display, không dùng làm path)
     */
    private function sanitizeOriginalName(string $name): string
    {
        // Giữ lại: chữ cái, số, dấu gạch ngang, gạch dưới, dấu chấm
        // Loại bỏ: ký tự đặc biệt, path traversal
        $name = basename($name); // Loại bỏ path component
        $name = preg_replace('/[^\w\s.\-]/u', '', $name);
        $name = trim($name);

        return $name ?: 'attachment';
    }

    /**
     * Tạo thumbnail 200x150 cho ảnh bằng GD Library
     * WHY GD: có sẵn trên InfinityFree, không cần install thêm gì
     * WHY thumbnail cùng folder: tiết kiệm Inode so với tạo folder /thumbnails/ riêng
     */
    private function createThumbnail(
        string $sourcePath,
        string $targetDir,
        string $storedName,
        string $mimeType
    ): void {
        // Nếu GD không available thì bỏ qua, không throw exception
        if (!extension_loaded('gd')) {
            return;
        }

        try {
            $thumbWidth  = 200;
            $thumbHeight = 150;

            // Load ảnh gốc theo MIME type
            $source = match ($mimeType) {
                'image/jpeg' => imagecreatefromjpeg($sourcePath),
                'image/png'  => imagecreatefrompng($sourcePath),
                'image/gif'  => imagecreatefromgif($sourcePath),
                default      => null,
            };

            if ($source === null || $source === false) {
                return;
            }

            [$origWidth, $origHeight] = getimagesize($sourcePath);

            // Tính tỷ lệ để resize giữ aspect ratio
            $ratio     = min($thumbWidth / $origWidth, $thumbHeight / $origHeight);
            $newWidth  = (int) ($origWidth * $ratio);
            $newHeight = (int) ($origHeight * $ratio);

            $thumb = imagecreatetruecolor($newWidth, $newHeight);

            // WHY: Giữ transparency cho PNG và GIF
            if (in_array($mimeType, ['image/png', 'image/gif'])) {
                imagealphablending($thumb, false);
                imagesavealpha($thumb, true);
                $transparent = imagecolorallocatealpha($thumb, 255, 255, 255, 127);
                imagefilledrectangle($thumb, 0, 0, $newWidth, $newHeight, $transparent);
            }

            imagecopyresampled($thumb, $source, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);

            // Tên thumbnail: {storedName_without_ext}_thumb.jpg
            $nameWithoutExt = pathinfo($storedName, PATHINFO_FILENAME);
            $thumbPath      = $targetDir . '/' . $nameWithoutExt . '_thumb.jpg';

            // Lưu thumbnail dạng JPEG (nhỏ hơn PNG, đủ cho preview)
            imagejpeg($thumb, $thumbPath, 80);

            imagedestroy($source);
            imagedestroy($thumb);
        } catch (\Throwable $e) {
            // Lỗi thumbnail không nên làm fail cả upload
            $this->logger->warning(
                'Tạo thumbnail thất bại: ' . $e->getMessage(),
                'FileUploadService',
                $sourcePath
            );
        }
    }

    private function getThumbnailPath(string $absoluteFilePath): ?string
    {
        $dir            = dirname($absoluteFilePath);
        $nameWithoutExt = pathinfo($absoluteFilePath, PATHINFO_FILENAME);
        $thumbPath      = $dir . '/' . $nameWithoutExt . '_thumb.jpg';

        return file_exists($thumbPath) ? $thumbPath : null;
    }
}