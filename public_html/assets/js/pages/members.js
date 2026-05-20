/**
 * BugTracker - Members Controller
 * Xử lý giao diện trang Quản lý Thành viên và Lời mời
 */
const Members = {
    init() {
        this.cacheDOM();
        this.bindEvents();
    },

    cacheDOM() {
        this.inviteForm = document.getElementById('inviteForm');
        this.roleSelects = document.querySelectorAll('select[name="new_role"]');
    },

    bindEvents() {
        // 1. Xử lý Form Mời Thành viên bằng AJAX để tránh block UI khi gửi Email SMTP
        if (this.inviteForm) {
            this.inviteForm.addEventListener('submit', (e) => this.handleInviteSubmit(e));
        }

        // 2. Thêm hiệu ứng loading nhỏ khi Owner/Admin thay đổi quyền (Role) của ai đó
        if (this.roleSelects) {
            this.roleSelects.forEach(select => {
                select.addEventListener('change', (e) => {
                    // Làm mờ dòng table hiện tại để báo hiệu đang xử lý
                    const tr = e.target.closest('tr');
                    if (tr) {
                        tr.style.opacity = '0.5';
                        tr.style.pointerEvents = 'none';
                    }
                    // Form tự động submit (đã code trong HTML onchange="this.form.submit()")
                });
            });
        }
    },

    /**
     * Xử lý gửi lời mời qua API
     */
    async handleInviteSubmit(e) {
        // Form này đã được validator.js kiểm tra. 
        // Chúng ta cần setTimeout nhẹ để đảm bảo validator.js chạy xong và không đánh cờ lỗi
        e.preventDefault();

        setTimeout(async () => {
            const hasErrors = this.inviteForm.querySelectorAll('.is-invalid').length > 0;
            if (hasErrors) return; // Nếu có lỗi (như sai định dạng email), dừng xử lý

            const submitBtn = this.inviteForm.querySelector('button[type="submit"]');
            const emailInput = document.getElementById('invite_email');
            const roleInput = document.getElementById('invite_role');

            // Khóa nút Submit & Đổi trạng thái sang Loading
            const originalBtnText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spin-anim mr-2">⏳</span> Đang gửi email...';
            
            // Khóa luôn form để không cho người dùng sửa dữ liệu trong lúc đợi
            emailInput.disabled = true;
            roleInput.disabled = true;

            try {
                // Gọi API tạo lời mời và gửi email
                const response = await Api.post('/workspace/invitations/store', {
                    email: emailInput.value.trim(),
                    role: roleInput.value
                });

                if (response && response.success) {
                    Toast.show('Đã gửi lời mời và email hướng dẫn thành công!', 'success');
                    
                    // Đóng Modal ngay lập tức
                    const modal = this.inviteForm.closest('.modal');
                    if (modal && typeof Modal !== 'undefined') {
                        Modal.close(modal);
                    }

                    // Tải lại trang sau 1.5 giây để cập nhật danh sách "Lời mời chờ xác nhận"
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    Toast.show(response.message || 'Không thể gửi lời mời.', 'danger');
                    this.resetInviteForm(submitBtn, originalBtnText, emailInput, roleInput);
                }

            } catch (error) {
                // Lỗi HTTP hoặc mất mạng
                Toast.show(error.message || 'Lỗi kết nối. Vui lòng thử lại sau.', 'danger');
                this.resetInviteForm(submitBtn, originalBtnText, emailInput, roleInput);
            }
        }, 10);
    },

    /**
     * Khôi phục trạng thái form nếu có lỗi xảy ra
     */
    resetInviteForm(submitBtn, originalBtnText, emailInput, roleInput) {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalBtnText;
        emailInput.disabled = false;
        roleInput.disabled = false;
        emailInput.focus();
    }
};

// Khởi chạy khi DOM đã load xong
document.addEventListener('DOMContentLoaded', () => Members.init());