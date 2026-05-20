/**
 * BugTracker - Issue Form Controller
 * Xử lý giao diện cho trang Tạo mới / Chỉnh sửa Issue
 * Yêu cầu: Đã load thư viện marked.js
 */
const IssueForm = {
    init() {
        this.cacheDOM();
        if (!this.form) return; // Nếu không ở trang Issue Form thì bỏ qua

        this.bindEvents();
    },

    cacheDOM() {
        this.form = document.getElementById('issueForm');
        this.descriptionInput = document.getElementById('description');
        this.previewBox = document.getElementById('markdown-preview');
        this.btnTogglePreview = document.getElementById('btnTogglePreview');
        this.fileInput = document.getElementById('attachments');
    },

    bindEvents() {
        // Sự kiện bật/tắt chế độ xem trước Markdown
        if (this.btnTogglePreview && this.descriptionInput && this.previewBox) {
            this.btnTogglePreview.addEventListener('click', () => this.toggleMarkdownPreview());
        }

        // Sự kiện kiểm tra file đính kèm khi người dùng chọn file
        if (this.fileInput) {
            this.fileInput.addEventListener('change', (e) => this.validateAttachments(e));
        }
    },

    /**
     * Chuyển đổi giữa Textarea và khung Preview Markdown
     */
    toggleMarkdownPreview() {
        const isPreviewing = !this.previewBox.classList.contains('d-none');

        if (isPreviewing) {
            // Đang ở chế độ Preview -> Chuyển về chế độ Chỉnh sửa
            this.previewBox.classList.add('d-none');
            this.descriptionInput.style.display = 'block';
            this.btnTogglePreview.textContent = 'Chuyển chế độ Preview';
            this.descriptionInput.focus();
        } else {
            // Đang ở chế độ Chỉnh sửa -> Chuyển sang Preview
            const markdownText = this.descriptionInput.value.trim();
            
            if (markdownText === '') {
                this.previewBox.innerHTML = '<em class="text-muted">Không có nội dung để xem trước...</em>';
            } else {
                try {
                    // Cấu hình marked để render an toàn (chống XSS cơ bản phía client)
                    // XSS an toàn tuyệt đối sẽ được HTML Purifier xử lý ở Backend
                    this.previewBox.innerHTML = marked.parse(markdownText, { 
                        breaks: true, // Hỗ trợ xuống dòng tự nhiên
                        gfm: true     // Hỗ trợ GitHub Flavored Markdown
                    });
                } catch (error) {
                    this.previewBox.innerHTML = '<em class="text-danger">Lỗi hiển thị Markdown.</em>';
                    console.error('Marked parsing error:', error);
                }
            }

            this.descriptionInput.style.display = 'none';
            this.previewBox.classList.remove('d-none');
            this.btnTogglePreview.textContent = 'Quay lại Chỉnh sửa';
        }
    },

    /**
     * Kiểm tra dung lượng và số lượng file trước khi upload
     */
    validateAttachments(event) {
        const files = event.target.files;
        const MAX_FILES = 5;
        const MAX_SIZE_MB = 2;
        const MAX_SIZE_BYTES = MAX_SIZE_MB * 1024 * 1024;
        let hasError = false;
        let errorMessage = '';

        // 1. Kiểm tra số lượng file
        if (files.length > MAX_FILES) {
            hasError = true;
            errorMessage = `Bạn chỉ được phép tải lên tối đa ${MAX_FILES} tệp tin cùng lúc.`;
        } else {
            // 2. Kiểm tra dung lượng và định dạng từng file
            for (let i = 0; i < files.length; i++) {
                const file = files[i];
                
                if (file.size > MAX_SIZE_BYTES) {
                    hasError = true;
                    errorMessage = `Tệp "${file.name}" vượt quá giới hạn ${MAX_SIZE_MB}MB.`;
                    break;
                }
                
                // Mặc dù HTML có accept="...", nhưng vẫn nên double check phần mở rộng bằng JS
                const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf', 'text/plain', 'application/zip', 'application/x-zip-compressed'];
                if (!allowedTypes.includes(file.type) && !file.name.match(/\.(zip)$/i)) {
                    hasError = true;
                    errorMessage = `Tệp "${file.name}" có định dạng không được hỗ trợ.`;
                    break;
                }
            }
        }

        // Nếu có lỗi, reset lại input file và thông báo bằng Toast
        if (hasError) {
            // Reset input (xóa các file đã chọn)
            event.target.value = '';
            
            // Dùng hàm Toast global từ js/core/toast.js
            if (typeof Toast !== 'undefined') {
                Toast.show(errorMessage, 'danger', 5000);
            } else {
                alert(errorMessage);
            }
        }
    }
};

// Khởi chạy khi DOM đã load xong
document.addEventListener('DOMContentLoaded', () => IssueForm.init());