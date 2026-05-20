<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Logger;

/**
 * FileUploadService
 *
 * Xử lý toàn bộ vòng đời của file đính kèm:
 * validate → store (ngoài webroot) → serve qua PHP proxy → delete
 *
 * WHY không dùng thư mục public_html:
 *   Nếu file lưu trong public_html, user có thể truy cập trực tiếp qua URL
 *   mà bypass hoàn toàn kiểm tra quyền của PHP. Đặt ngoài webroot buộc
 *   mọi request phải đi qua AttachmentController::serve() để kiểm tra
 *   permission trước.
 *
 * WHY Logger tự khởi tạo bên trong (không inject từ ngoài):
 *   Toàn bộ codebase dùng pattern new Logger() trực tiếp trong từng class
 *   (xem Attachment.php, các Model khác). Inject Logger qua constructor
 *   buộc mọi caller phải new Logger() trước khi new FileUploadService(),
 *   tạo boilerplate không cần thiết và không nhất quán với phần còn lại
 *   của project.
 *
 * @author  Dev 1
 * @version 1.0.1
 * @see     TDD Backend v1.0.0 – Phần 3.1 (Bảo mật thư mục)
 * @see     Task Assignment v1.0.0 – D1-026, D1-027
 * @see     SRS v1.0.0 – UC-019 (File upload), UC-029, UC-030
 */
class FileUploadService
{
    private Logger $logger;

    public function __construct()
    {
        // WHY tự khởi tạo Logger thay vì inject:
        // Nhất quán với pattern của toàn bộ codebase. Logger không có
        // external dependency phức tạp cần mock trong test.
        $this->logger = new Logger();
    }

    // =========================================================================
    // PUBLIC: store()
    // Validate + move file từ /tmp vào /storage/attachments/{ws_id}/{issue_id}/
    // =========================================================================

    /**
     * Validate file upload và lưu vào storage ngoài webroot.
     *
     * @param  array  $file         Phần tử từ $_FILES (đã qua Request object)
     * @param  int    $workspaceId  Dùng để tạo đường dẫn phân cấp
     * @param  int    $issueId      Dùng để tạo đường dẫn phân cấp
     * @return array{
     *   original_name: string,
     *   stored_name:   string,
     *   file_path:     string,
     *   mime_type:     string,
     *   file_size:     int
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
        //   $_FILES['type'] do BROWSER cung cấp, có thể bị giả mạo.
        //   finfo_file đọc magic bytes thực sự của file để xác định MIME.
        $realMime = $this->detectMimeType($file['tmp_name']);
        $this->validateMimeType($realMime);

        // --- Bước 4: Tạo thư mục đích nếu chưa có ---
        $targetDir = $this->ensureDirectory($workspaceId, $issueId);

        // --- Bước 5: Tạo tên file an toàn (không giữ tên gốc) ---
        // WHY rename: tên gốc có thể chứa ký tự nguy hiểm, path traversal
        // ('../../../etc/passwd'), hoặc trùng tên gây ghi đè file cũ.
        //
        // WHY dùng UPLOAD_ALLOWED_EXTENSIONS thay vì ALLOWED_MIME_TYPES:
        //   UPLOAD_ALLOWED_EXTENSIONS là constant đúng tên trong config.php
        //   (Section 4). Map: mime_type → extension an toàn.
        $extension  = UPLOAD_ALLOWED_EXTENSIONS[$realMime] ?? 'bin';
        $storedName = $this->generateStoredName($extension);
        $targetPath = $targetDir . '/' . $storedName;

        // --- Bước 6: Move file từ /tmp vào storage ---
        // WHY move_uploaded_file thay vì rename/copy:
        //   move_uploaded_file() chỉ hoạt động với file đến từ HTTP upload,
        //   giúp chặn một số tấn công local file inclusion.
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

        // --- Bước 7: Tạo thumbnail nếu là ảnh ---
        // WHY tạo thumbnail: Phục vụ preview trong gallery mà không load ảnh
        //   full-size. Giảm bandwidth trên InfinityFree.
        // WHY cùng folder: tiết kiệm Inode so với tạo folder /thumbnails/ riêng
        //   (TDD Phần 9.5 – Inode Management).
        if (in_array($realMime, IMAGE_MIME_TYPES, true)) {
            $this->createThumbnail($targetPath, $targetDir, $storedName, $realMime);
        }

        // Relative path từ /storage/ — lưu vào DB, không lưu absolute path.
        // WHY relative: absolute path thay đổi giữa local và production server.
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
    // Stream file ra browser SAU KHI đã kiểm tra quyền ở Controller
    // =========================================================================

    /**
     * Đọc file từ storage và stream ra browser.
     *
     * Việc kiểm tra quyền (isMember, findByStoredName) thực hiện ở
     * AttachmentController::serve() trước khi gọi method này.
     *
     * @param  string $relativePath  Đường dẫn tương đối lưu trong DB
     *                               VD: "attachments/1/5/abc123.pdf"
     * @param  string $originalName  Tên file gốc để set Content-Disposition
     * @param  string $mimeType      MIME type lưu trong DB
     * @return void
     */
    public function serve(string $relativePath, string $originalName, string $mimeType): void
    {
        $absolutePath = STORAGE_PATH . '/' . $relativePath;

        // --- Security: Ngăn Path Traversal Attack ---
        // WHY: Nếu $relativePath = "../../config/.env", realpath() sẽ resolve
        //   ra đường dẫn thật. Ta kiểm tra nó có bắt đầu bằng STORAGE_PATH
        //   không. Nếu không → từ chối.
        $realPath        = realpath($absolutePath);
        $realStoragePath = realpath(STORAGE_PATH);

        if ($realPath === false || $realStoragePath === false
            || !str_starts_with($realPath, $realStoragePath)
        ) {
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

        // WHY inline cho ảnh và PDF: hiển thị ngay trong tab thay vì download
        // WHY attachment cho các loại khác: buộc download, an toàn hơn
        if (in_array($mimeType, ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'], true)) {
            header("Content-Disposition: inline; filename=\"{$safeName}\"");
        } else {
            header("Content-Disposition: attachment; filename=\"{$safeName}\"");
        }

        // Không cache file attachment — tránh trình duyệt hiện file cũ sau khi xóa
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');

        // WHY readfile() thay vì echo file_get_contents():
        //   readfile() stream trực tiếp từ disk → browser mà không load toàn
        //   bộ vào RAM. Quan trọng trên InfinityFree vì giới hạn memory_limit.
        readfile($realPath);
        exit;
    }

    // =========================================================================
    // PUBLIC: delete()
    // Xóa file vật lý khỏi storage (gọi sau khi soft-delete attachment trong DB)
    // =========================================================================

    /**
     * Xóa file vật lý và thumbnail tương ứng.
     *
     * WHY best-effort (không throw exception):
     *   Caller (AttachmentController::destroy) đã commit soft-delete vào DB.
     *   Nếu xóa file vật lý fail thì log warning nhưng không rollback DB —
     *   orphan file trên disk ít nguy hiểm hơn inconsistent DB state.
     *
     * @param  string $relativePath  Đường dẫn tương đối lưu trong DB
     * @return bool   true nếu xóa thành công hoặc file không còn tồn tại
     */
    public function delete(string $relativePath): bool
    {
        $absolutePath    = STORAGE_PATH . '/' . $relativePath;
        $realPath        = realpath($absolutePath);
        $realStoragePath = realpath(STORAGE_PATH);

        // Security check tương tự serve()
        if ($realPath === false || $realStoragePath === false
            || !str_starts_with($realPath, $realStoragePath)
        ) {
            $this->logger->warning(
                'Cố xóa file ngoài storage path',
                'FileUploadService',
                $relativePath
            );
            return false;
        }

        if (!file_exists($realPath)) {
            // File đã không còn — coi như xóa thành công
            return true;
        }

        // Xóa thumbnail nếu có (tên: {storedName_without_ext}_thumb.jpg)
        $thumbPath = $this->getThumbnailPath($realPath);
        if ($thumbPath !== null && file_exists($thumbPath)) {
            @unlink($thumbPath);
        }

        return unlink($realPath);
    }

    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================

    /**
     * Kiểm tra lỗi upload từ PHP $_FILES['error'].
     *
     * @throws \RuntimeException
     */
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

    /**
     * Kiểm tra kích thước file theo constant UPLOAD_MAX_FILE_SIZE từ config.php.
     *
     * WHY dùng UPLOAD_MAX_FILE_SIZE (không phải MAX_FILE_SIZE):
     *   UPLOAD_MAX_FILE_SIZE là tên đúng được define trong config.php Section 4.
     *
     * @throws \RuntimeException
     */
    private function validateFileSize(int $size): void
    {
        if ($size === 0) {
            throw new \RuntimeException('File rỗng (0 bytes) không được phép.');
        }

        if ($size > UPLOAD_MAX_FILE_SIZE) {
            $maxMB = round(UPLOAD_MAX_FILE_SIZE / 1024 / 1024, 1);
            throw new \RuntimeException("File vượt quá giới hạn {$maxMB}MB.");
        }
    }

    /**
     * Xác định MIME type thực sự của file bằng finfo (đọc magic bytes).
     *
     * @throws \RuntimeException
     */
    private function detectMimeType(string $tmpPath): string
    {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($tmpPath);

        if ($mime === false) {
            throw new \RuntimeException('Không thể xác định loại file.');
        }

        return $mime;
    }

    /**
     * Validate MIME type theo whitelist UPLOAD_ALLOWED_EXTENSIONS từ config.php.
     *
     * WHY dùng array_key_exists trên UPLOAD_ALLOWED_EXTENSIONS:
     *   UPLOAD_ALLOWED_EXTENSIONS là map mime → extension (array keys = mime types).
     *   Kiểm tra key tồn tại = kiểm tra mime type được phép.
     *
     * @throws \RuntimeException
     */
    private function validateMimeType(string $mime): void
    {
        if (!array_key_exists($mime, UPLOAD_ALLOWED_EXTENSIONS)) {
            throw new \RuntimeException(
                "Loại file '{$mime}' không được hỗ trợ. "
                . 'Chỉ chấp nhận: JPG, PNG, GIF, PDF, TXT, ZIP.'
            );
        }
    }

    /**
     * Tạo thư mục lưu file nếu chưa tồn tại.
     *
     * WHY dùng ATTACHMENTS_DIR (không phải ATTACHMENTS_PATH):
     *   ATTACHMENTS_DIR là tên đúng được define trong config.php Section 4.
     *
     * WHY 0755: owner có full quyền, group/other chỉ read+execute.
     *   Đủ để PHP web process (www-data) ghi file trên InfinityFree.
     *
     * @throws \RuntimeException
     */
    private function ensureDirectory(int $workspaceId, int $issueId): string
    {
        $dir = ATTACHMENTS_DIR . "/{$workspaceId}/{$issueId}";

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
     * Sinh tên file ngẫu nhiên an toàn để lưu trên server.
     *
     * Format: {timestamp}_{16_hex_chars}.{ext}
     * WHY timestamp: dễ debug biết file upload lúc nào mà không cần query DB.
     * WHY random_bytes: đảm bảo tên file không đoán được, tránh enumeration attack.
     */
    private function generateStoredName(string $extension): string
    {
        $random = bin2hex(random_bytes(8)); // 16 ký tự hex
        $ts     = time();

        return "{$ts}_{$random}.{$extension}";
    }

    /**
     * Sanitize tên file gốc để lưu vào DB.
     *
     * Chỉ dùng cho display (original_name column), KHÔNG dùng làm đường dẫn.
     * File path thực tế luôn dùng stored_name (generated, không từ user input).
     */
    private function sanitizeOriginalName(string $name): string
    {
        $name = basename($name);                       // Loại bỏ path component
        $name = preg_replace('/[^\w\s.\-]/u', '', $name); // Chỉ giữ safe chars
        $name = trim($name);

        return $name !== '' ? $name : 'attachment';
    }

    /**
     * Tạo thumbnail 200×150 cho ảnh bằng GD Library.
     *
     * WHY GD: có sẵn trên InfinityFree, không cần install thêm extension.
     * WHY cùng folder với file gốc: tiết kiệm Inode (TDD Phần 2.4).
     * WHY không throw exception: lỗi thumbnail không nên fail cả upload.
     */
    private function createThumbnail(
        string $sourcePath,
        string $targetDir,
        string $storedName,
        string $mimeType
    ): void {
        // Nếu GD không available thì bỏ qua
        if (!extension_loaded('gd')) {
            return;
        }

        try {
            // WHY dùng THUMBNAIL_WIDTH/HEIGHT từ config.php thay vì hardcode:
            //   Cho phép thay đổi kích thước thumbnail qua .env mà không sửa code.
            $thumbWidth  = THUMBNAIL_WIDTH;
            $thumbHeight = THUMBNAIL_HEIGHT;

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

            // Tính tỷ lệ để resize giữ aspect ratio (không crop, không stretch)
            $ratio     = min($thumbWidth / $origWidth, $thumbHeight / $origHeight);
            $newWidth  = (int) ($origWidth  * $ratio);
            $newHeight = (int) ($origHeight * $ratio);

            $thumb = imagecreatetruecolor($newWidth, $newHeight);

            // WHY: Giữ transparency cho PNG và GIF
            if (in_array($mimeType, ['image/png', 'image/gif'], true)) {
                imagealphablending($thumb, false);
                imagesavealpha($thumb, true);
                $transparent = imagecolorallocatealpha($thumb, 255, 255, 255, 127);
                imagefilledrectangle($thumb, 0, 0, $newWidth, $newHeight, $transparent);
            }

            imagecopyresampled(
                $thumb, $source,
                0, 0, 0, 0,
                $newWidth, $newHeight,
                $origWidth, $origHeight
            );

            // Tên thumbnail: {storedName_without_ext}_thumb.jpg
            $nameWithoutExt = pathinfo($storedName, PATHINFO_FILENAME);
            $thumbPath      = $targetDir . '/' . $nameWithoutExt . '_thumb.jpg';

            // Lưu thumbnail dạng JPEG (nhỏ hơn PNG, đủ cho preview gallery)
            imagejpeg($thumb, $thumbPath, 80);

            imagedestroy($source);
            imagedestroy($thumb);

        } catch (\Throwable $e) {
            // Lỗi thumbnail không nên làm fail cả upload – chỉ log warning
            $this->logger->warning(
                'Tạo thumbnail thất bại: ' . $e->getMessage(),
                'FileUploadService',
                $sourcePath
            );
        }
    }

    /**
     * Lấy đường dẫn thumbnail tương ứng với file gốc.
     * Trả về null nếu thumbnail không tồn tại.
     */
    private function getThumbnailPath(string $absoluteFilePath): ?string
    {
        $dir            = dirname($absoluteFilePath);
        $nameWithoutExt = pathinfo($absoluteFilePath, PATHINFO_FILENAME);
        $thumbPath      = $dir . '/' . $nameWithoutExt . '_thumb.jpg';

        return file_exists($thumbPath) ? $thumbPath : null;
    }
}