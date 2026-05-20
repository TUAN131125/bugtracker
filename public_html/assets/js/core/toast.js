/**
 * BugTracker - Toast Notification Manager
 */
const Toast = {
    container: null,

    init() {
        this.container = document.querySelector('.toast-container');
    },

    show(message, type = 'success', duration = 4000) {
        if (!this.container) this.init();
        if (!this.container) return;

        // Xác định icon và tiêu đề dựa trên loại
        let icon = '✅';
        let title = 'Thành công';
        if (type === 'danger') { icon = '❌'; title = 'Lỗi'; }
        if (type === 'warning') { icon = '⚠️'; title = 'Cảnh báo'; }
        if (type === 'info') { icon = 'ℹ️'; title = 'Thông tin'; }

        // Khởi tạo thẻ HTML của Toast
        const toastEl = document.createElement('div');
        toastEl.className = `toast toast-${type} show`;
        toastEl.setAttribute('role', 'alert');
        
        toastEl.innerHTML = `
            <div class="toast-header">
                <strong class="mr-auto">${icon} ${title}</strong>
                <button type="button" class="ml-2 mb-1 close" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="toast-body">
                ${Utils.escapeHtml(message)}
            </div>
        `;

        // Lắng nghe sự kiện nút X để tắt
        toastEl.querySelector('.close').addEventListener('click', () => this.dismiss(toastEl));

        // Thêm vào DOM
        this.container.appendChild(toastEl);

        // Tự động tắt sau khoảng thời gian duration
        setTimeout(() => {
            this.dismiss(toastEl);
        }, duration);
    },

    dismiss(toastEl) {
        if (toastEl && toastEl.parentNode) {
            toastEl.classList.remove('show');
            toastEl.classList.add('hiding');
            // Chờ animation hoàn tất rồi gỡ khỏi DOM
            setTimeout(() => {
                toastEl.remove();
            }, 300);
        }
    }
};

// Khởi tạo tự động khi script được tải
document.addEventListener('DOMContentLoaded', () => Toast.init());