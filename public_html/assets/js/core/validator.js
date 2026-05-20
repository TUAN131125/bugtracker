/**
 * BugTracker - Client-side Form Validation Engine
 */
const Validator = {
    init() {
        // Áp dụng cho mọi form có thuộc tính novalidate (ngăn HTML5 tooltip mặc định)
        const forms = document.querySelectorAll('form[novalidate]');
        
        forms.forEach(form => {
            form.addEventListener('submit', (e) => {
                if (!this.validateForm(form)) {
                    e.preventDefault(); // Dừng submit nếu có lỗi
                    Toast.show('Vui lòng kiểm tra lại các trường dữ liệu bị lỗi.', 'danger');
                }
            });

            // Lắng nghe sự kiện input để xóa trạng thái lỗi khi người dùng sửa
            const inputs = form.querySelectorAll('input, select, textarea');
            inputs.forEach(input => {
                input.addEventListener('input', () => {
                    this.clearError(input);
                });
            });
        });
    },

    validateForm(form) {
        let isValid = true;
        const inputs = form.querySelectorAll('input, select, textarea');

        inputs.forEach(input => {
            // Bỏ qua input ẩn hoặc disabled
            if (input.type === 'hidden' || input.disabled) return;

            let errorMsg = null;

            // 1. Kiểm tra trường bắt buộc
            if (input.hasAttribute('required') && !input.value.trim()) {
                errorMsg = 'Trường này không được để trống.';
            } 
            // 2. Kiểm tra định dạng Email
            else if (input.type === 'email' && input.value.trim()) {
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(input.value)) {
                    errorMsg = 'Định dạng email không hợp lệ.';
                }
            }
            // 3. Kiểm tra Pattern (Regex) - Dùng cho Project Key
            else if (input.hasAttribute('pattern') && input.value.trim()) {
                const regex = new RegExp(`^${input.getAttribute('pattern')}$`);
                if (!regex.test(input.value)) {
                    errorMsg = input.getAttribute('title') || 'Dữ liệu không đúng định dạng.';
                }
            }
            // 4. Kiểm tra độ dài tối thiểu (Mật khẩu)
            else if (input.type === 'password' && input.value.length > 0 && input.value.length < 8) {
                errorMsg = 'Mật khẩu phải chứa ít nhất 8 ký tự.';
            }
            // 5. Kiểm tra Xác nhận mật khẩu khớp nhau
            else if (input.id === 'password_confirm') {
                const passInput = form.querySelector('#password');
                if (passInput && input.value !== passInput.value) {
                    errorMsg = 'Mật khẩu xác nhận không trùng khớp.';
                }
            }

            // Xử lý hiển thị
            if (errorMsg) {
                this.showError(input, errorMsg);
                isValid = false;
            } else {
                this.clearError(input);
            }
        });

        return isValid;
    },

    showError(input, message) {
        input.classList.add('is-invalid');
        
        // Kiểm tra xem đã có thẻ div.invalid-feedback chưa
        let feedback = input.parentNode.querySelector('.invalid-feedback');
        if (!feedback) {
            feedback = document.createElement('div');
            feedback.className = 'invalid-feedback';
            // Cắm thẻ lỗi ngay dưới input
            input.parentNode.insertBefore(feedback, input.nextSibling);
        }
        feedback.textContent = message;
        feedback.style.display = 'block';
    },

    clearError(input) {
        input.classList.remove('is-invalid');
        const feedback = input.parentNode.querySelector('.invalid-feedback');
        if (feedback) {
            feedback.style.display = 'none';
        }
    }
};

document.addEventListener('DOMContentLoaded', () => Validator.init());