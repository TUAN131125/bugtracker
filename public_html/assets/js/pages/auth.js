/**
 * BugTracker - Auth & Onboarding Controller
 * Quản lý các tương tác đặc thù cho luồng xác thực người dùng
 */
const Auth = {
    init() {
        this.initPasswordToggle();
        this.initSubmitLoaders();
        this.initInviteCodeFormatter();
    },

    /**
     * 1. Tự động chèn nút Ẩn/Hiện (Mắt) vào tất cả các trường mật khẩu
     */
    initPasswordToggle() {
        const passwordInputs = document.querySelectorAll('input[type="password"]');
        
        passwordInputs.forEach(input => {
            // Tạo một thẻ div bọc ngoài để set position relative
            const wrapper = document.createElement('div');
            wrapper.style.position = 'relative';
            wrapper.style.display = 'block';
            wrapper.style.width = '100%';
            
            // Đưa input vào trong wrapper
            input.parentNode.insertBefore(wrapper, input);
            wrapper.appendChild(input);

            // Thêm padding bên phải cho input để chữ không đè lên icon
            input.style.paddingRight = '40px';

            // Tạo nút Toggle
            const toggleBtn = document.createElement('button');
            toggleBtn.type = 'button';
            toggleBtn.className = 'btn-link text-muted';
            toggleBtn.style.position = 'absolute';
            toggleBtn.style.right = '10px';
            toggleBtn.style.top = '50%';
            toggleBtn.style.transform = 'translateY(-50%)';
            toggleBtn.style.border = 'none';
            toggleBtn.style.background = 'transparent';
            toggleBtn.style.cursor = 'pointer';
            toggleBtn.style.padding = '0';
            toggleBtn.style.fontSize = '16px';
            toggleBtn.innerHTML = '👁️'; 
            toggleBtn.title = 'Hiện mật khẩu';
            toggleBtn.tabIndex = -1; // Không cho focus bằng phím tab

            wrapper.appendChild(toggleBtn);

            // Bắt sự kiện Click để chuyển đổi type (password <-> text)
            toggleBtn.addEventListener('click', () => {
                const isPassword = input.getAttribute('type') === 'password';
                input.setAttribute('type', isPassword ? 'text' : 'password');
                
                // Đổi icon
                toggleBtn.innerHTML = isPassword ? '🙈' : '👁️';
                toggleBtn.title = isPassword ? 'Ẩn mật khẩu' : 'Hiện mật khẩu';
            });
        });
    },

    /**
     * 2. Vô hiệu hóa nút Submit khi Form đang gửi lên Server
     * Tránh lỗi người dùng spam click gây quá tải Database hoặc gửi Email trùng lặp
     */
    initSubmitLoaders() {
        // Lấy tất cả các form thuộc nhóm xác thực và onboarding
        const authForms = document.querySelectorAll('.auth-form, .onboarding-inline-form');
        
        authForms.forEach(form => {
            form.addEventListener('submit', (e) => {
                // Đợi 10ms để file validator.js (nếu có) chạy trước
                setTimeout(() => {
                    // Kiểm tra xem form có bị validator đánh lỗi (class .is-invalid) không
                    const hasErrors = form.querySelectorAll('.is-invalid').length > 0;
                    
                    // Nếu form hợp lệ thì tiến hành khóa nút Submit
                    if (!hasErrors) {
                        const submitBtn = form.querySelector('button[type="submit"]');
                        if (submitBtn) {
                            // Cất text cũ vào data attribute để dự phòng
                            if (!submitBtn.hasAttribute('data-original-text')) {
                                submitBtn.setAttribute('data-original-text', submitBtn.innerHTML);
                            }
                            
                            submitBtn.disabled = true;
                            // Dùng class spin-anim từ file _animations.css
                            submitBtn.innerHTML = '<span class="spin-anim mr-2">⏳</span> Đang xử lý...';
                            submitBtn.classList.add('disabled');
                        }
                    }
                }, 10);
            });
        });
    },

    /**
     * 3. Tự động định dạng Mã mời (Invite Code)
     */
    initInviteCodeFormatter() {
        const inviteInput = document.getElementById('invite_code');
        
        if (inviteInput) {
            inviteInput.addEventListener('input', (e) => {
                // Chuyển toàn bộ thành chữ in hoa và xóa mọi khoảng trắng
                e.target.value = e.target.value.toUpperCase().replace(/\s/g, '');
            });
        }
    }
};

// Khởi chạy khi DOM đã load xong
document.addEventListener('DOMContentLoaded', () => Auth.init());