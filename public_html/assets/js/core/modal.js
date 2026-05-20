/**
 * BugTracker - Modal & Dialog Manager
 */
const Modal = {
    init() {
        // Lắng nghe click trên toàn bộ Document
        document.addEventListener('click', (e) => {
            
            // 1. Mở Modal khi click vào phần tử có data-toggle="modal"
            const toggleBtn = e.target.closest('[data-toggle="modal"]');
            if (toggleBtn) {
                e.preventDefault();
                const targetId = toggleBtn.getAttribute('data-target');
                if (targetId) this.open(targetId);
            }

            // 2. Đóng Modal khi click vào nút data-dismiss="modal" hoặc class .close-modal
            const dismissBtn = e.target.closest('[data-dismiss="modal"], .close-modal');
            if (dismissBtn) {
                e.preventDefault();
                const modal = dismissBtn.closest('.modal');
                if (modal) this.close(modal);
            }

            // 3. Đóng Modal khi click ra khoảng trống bên ngoài (overlay)
            if (e.target.classList.contains('modal')) {
                this.close(e.target);
            }
        });

        // Đóng Modal bằng phím ESC
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                const openModal = document.querySelector('.modal[style*="display: block"]');
                if (openModal) this.close(openModal);
            }
        });
    },

    open(targetId) {
        const modal = document.querySelector(targetId);
        if (modal) {
            modal.style.display = 'block';
            document.body.style.overflow = 'hidden'; // Ngăn cuộn trang
            // Kích hoạt animation nếu có (cần add class show)
            setTimeout(() => modal.classList.add('show'), 10);
        }
    },

    close(modalElement) {
        modalElement.classList.remove('show');
        setTimeout(() => {
            modalElement.style.display = 'none';
            document.body.style.overflow = ''; // Phục hồi cuộn trang
        }, 300); // Khớp với thời gian transition CSS
    }
};

document.addEventListener('DOMContentLoaded', () => Modal.init());