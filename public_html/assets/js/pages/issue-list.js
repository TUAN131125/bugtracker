/**
 * BugTracker - Issue List Controller
 * Xử lý các tương tác trên trang Danh sách Lỗi & Công việc
 */
const IssueList = {
    init() {
        this.cacheDOM();
        if (!this.filterForm) return;

        this.bindEvents();
    },

    cacheDOM() {
        this.filterForm = document.querySelector('.filter-form');
        this.searchInput = document.getElementById('search');
        this.tableBody = document.querySelector('.data-table tbody');
        this.clickableRows = document.querySelectorAll('.data-table tbody tr.cursor-pointer');
    },

    bindEvents() {
        // 1. Tự động submit form tìm kiếm khi người dùng ngừng gõ (Debounce)
        if (this.searchInput) {
            this.searchInput.addEventListener('input', Utils.debounce((e) => {
                const val = e.target.value.trim();
                
                // Chỉ tự động lọc nếu người dùng gõ từ 2 ký tự trở lên, hoặc xóa trắng ô tìm kiếm
                if (val.length >= 2 || val.length === 0) {
                    this.showTableLoader();
                    this.filterForm.submit();
                }
            }, 600)); // Chờ 600ms để chắc chắn người dùng đã gõ xong
        }

        // 2. Xử lý sự kiện Submit của Form (Khi người dùng đổi Select box hoặc bấm nút Lọc)
        this.filterForm.addEventListener('submit', () => {
            this.showTableLoader();
        });

        // 3. Xử lý click an toàn trên các dòng của bảng (Row click)
        // Lưu ý: Ngăn chặn việc click nhầm vào các thẻ a, button bên trong dòng
        this.clickableRows.forEach(row => {
            row.addEventListener('click', (e) => {
                // Kiểm tra xem người dùng có đang click vào một thành phần tương tác khác không
                const isInteractiveElement = e.target.closest('a, button, input, select, .badge');
                
                if (isInteractiveElement) {
                    // Nếu có, dừng sự kiện click của dòng (tránh chuyển trang ngoài ý muốn)
                    e.stopPropagation();
                    return;
                }
                
                // Nếu thẻ tr sử dụng thuộc tính data-href thay vì onclick inline
                const url = row.getAttribute('data-href');
                if (url) {
                    window.location.href = url;
                }
            });
        });
    },

    /**
     * Hiệu ứng làm mờ bảng khi đang chờ Server trả kết quả lọc
     */
    showTableLoader() {
        if (this.tableBody) {
            this.tableBody.style.opacity = '0.5';
            this.tableBody.style.pointerEvents = 'none';
            this.tableBody.style.transition = 'opacity 0.2s ease';
        }
        
        // Vô hiệu hóa nút Lọc để tránh click đúp
        const submitBtn = this.filterForm.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spin-anim mr-1">⏳</span> Đang lọc...';
        }
    }
};

// Khởi chạy khi DOM đã load xong
document.addEventListener('DOMContentLoaded', () => IssueList.init());