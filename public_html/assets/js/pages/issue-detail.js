/**
 * BugTracker - Issue Detail Controller
 * Xử lý các tương tác trên trang chi tiết Issue
 */
const IssueDetail = {
    init() {
        this.cacheDOM();
        this.bindEvents();
    },

    cacheDOM() {
        // Form cập nhật trạng thái
        this.statusSelect = document.getElementById('new_status');
        this.noteGroup = document.getElementById('resolutionNoteGroup');
        this.noteInput = document.getElementById('resolution_note');
        
        // Các nút thả cảm xúc
        this.reactionButtons = document.querySelectorAll('.btn-reaction');
        
        // Khu vực chỉnh sửa bình luận nhanh
        this.editCommentButtons = document.querySelectorAll('.btn-edit-comment');
    },

    bindEvents() {
        // 1. Lắng nghe thay đổi dropdown trạng thái
        if (this.statusSelect) {
            this.statusSelect.addEventListener('change', (e) => this.handleStatusChange(e.target.value));
            
            // Kích hoạt ngay lúc vừa load trang phòng trường hợp trình duyệt lưu cache form
            this.handleStatusChange(this.statusSelect.value);
        }

        // 2. Lắng nghe sự kiện click vào nút Reaction
        this.reactionButtons.forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                this.toggleReaction(btn);
            });
        });

        // 3. (Mở rộng) Lắng nghe sự kiện click nút Edit Comment
        this.editCommentButtons.forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const commentId = btn.getAttribute('data-comment-id');
                this.enableCommentEditMode(commentId);
            });
        });
    },

    /**
     * Hiển thị/Ẩn ô Ghi chú giải quyết dựa trên trạng thái
     */
    handleStatusChange(newStatus) {
        if (!this.noteGroup || !this.noteInput) return;

        // Nếu trạng thái là Resolved hoặc Closed thì bắt buộc nhập Note
        if (newStatus === 'resolved' || newStatus === 'closed') {
            this.noteGroup.classList.remove('d-none');
            this.noteGroup.classList.add('slide-up'); // Thêm hiệu ứng CSS từ _animations.css
            this.noteInput.setAttribute('required', 'required');
        } else {
            this.noteGroup.classList.add('d-none');
            this.noteGroup.classList.remove('slide-up');
            this.noteInput.removeAttribute('required');
        }
    },

    /**
     * Gửi AJAX xử lý thả cảm xúc (Thêm/Bớt)
     */
    async toggleReaction(btn) {
        const commentId = btn.getAttribute('data-comment-id');
        const emojiType = btn.getAttribute('data-emoji');
        const countSpan = btn.querySelector('.count');
        
        // Khóa nút tạm thời để tránh user click liên tục (spam)
        if (btn.disabled) return;
        btn.disabled = true;

        try {
            // Gọi Fetch API (đã tự động đính kèm CSRF Token ở core/api.js)
            const response = await Api.post(`/issues/comment/${commentId}/react`, {
                emoji: emojiType
            });

            // Nếu server xử lý thành công, cập nhật giao diện
            if (response && response.success) {
                // Cập nhật số lượng
                countSpan.textContent = response.data.new_count;
                
                // Đổi màu nền của nút nếu user đang React
                if (response.data.is_active) {
                    btn.classList.remove('btn-light');
                    btn.classList.add('btn-primary', 'text-white');
                    countSpan.classList.remove('text-muted');
                    countSpan.classList.add('text-white');
                } else {
                    btn.classList.remove('btn-primary', 'text-white');
                    btn.classList.add('btn-light');
                    countSpan.classList.remove('text-white');
                    countSpan.classList.add('text-muted');
                }
            } else {
                Toast.show(response.message || 'Không thể xử lý thao tác này.', 'danger');
            }
        } catch (error) {
            // Lỗi mạng hoặc HTTP Error sẽ được Api.request ném ra đây
            Toast.show('Đã xảy ra lỗi khi kết nối với máy chủ.', 'danger');
        } finally {
            // Mở khóa nút
            btn.disabled = false;
        }
    },

    /**
     * Biến vùng text hiển thị comment thành form chỉnh sửa trực tiếp (Inline Edit)
     */
    enableCommentEditMode(commentId) {
        const commentContainer = document.getElementById(`comment-${commentId}`);
        if (!commentContainer) return;

        const contentArea = commentContainer.querySelector('.content-area');
        
        // Lấy nội dung text nguyên thủy chưa qua render (Lý tưởng nhất là gọi API lấy raw markdown)
        // Trong trường hợp basic, ta có thể dùng prompt tạm thời hoặc thay thế DOM bằng thẻ <textarea>
        const currentContent = contentArea.innerText.trim();
        
        const editFormHtml = `
            <form action="/issues/comment/update/${commentId}" method="POST" class="mt-2">
                <input type="hidden" name="csrf_token" value="${Api.getCSRFToken()}">
                <textarea name="content" class="form-control mb-2" rows="3" required>${currentContent}</textarea>
                <div class="d-flex justify-content-end">
                    <button type="button" class="btn btn-sm btn-link text-muted mr-2 btn-cancel-edit">Hủy</button>
                    <button type="submit" class="btn btn-sm btn-primary">Lưu thay đổi</button>
                </div>
            </form>
        `;

        // Lưu lại DOM cũ để restore nếu user bấm Hủy
        const originalHtml = contentArea.innerHTML;
        contentArea.innerHTML = editFormHtml;

        // Xử lý nút Hủy
        const btnCancel = contentArea.querySelector('.btn-cancel-edit');
        btnCancel.addEventListener('click', () => {
            contentArea.innerHTML = originalHtml;
        });
    }
};

// Khởi chạy khi DOM đã load xong
document.addEventListener('DOMContentLoaded', () => IssueDetail.init());