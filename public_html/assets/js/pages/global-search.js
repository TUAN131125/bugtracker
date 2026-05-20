/**
 * BugTracker - Global Search Controller
 * Xử lý thanh tìm kiếm toàn cục trên Header
 */
const GlobalSearch = {
    init() {
        this.cacheDOM();
        if (!this.searchInput) return;
        
        this.bindEvents();
    },

    cacheDOM() {
        this.searchInput = document.getElementById('globalSearchInput');
        this.searchResults = document.getElementById('globalSearchResults');
    },

    bindEvents() {
        // 1. Lắng nghe sự kiện gõ phím với Debounce (chờ 400ms sau khi ngừng gõ mới gọi API)
        this.searchInput.addEventListener('input', Utils.debounce((e) => {
            const query = e.target.value.trim();
            if (query.length >= 2) {
                this.performSearch(query);
            } else {
                this.closeDropdown();
            }
        }, 400));

        // 2. Phím tắt '/' để focus nhanh vào ô tìm kiếm
        document.addEventListener('keydown', (e) => {
            // Chỉ bắt phím '/' khi người dùng không đang gõ trong một input/textarea khác
            if (e.key === '/' && !['INPUT', 'TEXTAREA'].includes(document.activeElement.tagName)) {
                e.preventDefault(); // Ngăn việc in ký tự '/' vào ô input
                this.searchInput.focus();
            }
            
            // Nhấn ESC để đóng dropdown tìm kiếm
            if (e.key === 'Escape') {
                this.closeDropdown();
                this.searchInput.blur();
            }
        });

        // 3. Click ra ngoài vùng tìm kiếm thì đóng Dropdown
        document.addEventListener('click', (e) => {
            if (!this.searchInput.contains(e.target) && !this.searchResults.contains(e.target)) {
                this.closeDropdown();
            }
        });
        
        // 4. Click lại vào ô tìm kiếm khi đã có chữ thì hiện lại kết quả
        this.searchInput.addEventListener('focus', () => {
            if (this.searchInput.value.trim().length >= 2 && this.searchResults.innerHTML !== '') {
                this.openDropdown();
            }
        });
    },

    /**
     * Gọi API tìm kiếm lên Server
     */
    async performSearch(query) {
        this.openDropdown();
        this.searchResults.innerHTML = '<div class="p-3 text-center text-muted"><span class="spin-anim mr-2">⏳</span> Đang tìm kiếm...</div>';

        try {
            // Gọi Fetch API, truyền query string
            const response = await Api.get(`/api/search?q=${encodeURIComponent(query)}`);

            if (response && response.success) {
                this.renderResults(response.data, query);
            } else {
                this.searchResults.innerHTML = `<div class="p-3 text-center text-danger">Lỗi: ${Utils.escapeHtml(response?.message || 'Không thể tải dữ liệu')}</div>`;
            }
        } catch (error) {
            this.searchResults.innerHTML = '<div class="p-3 text-center text-danger">Mất kết nối đến máy chủ.</div>';
        }
    },

    /**
     * Hiển thị kết quả trả về ra giao diện (HTML)
     */
    renderResults(data, query) {
        let html = '';
        let totalResults = 0;

        // Render danh sách Issues
        if (data.issues && data.issues.length > 0) {
            totalResults += data.issues.length;
            html += `<div class="dropdown-header bg-light font-weight-bold text-uppercase text-small border-bottom py-1">Lỗi & Công việc</div>`;
            data.issues.forEach(issue => {
                html += `
                    <a href="/issues/${Utils.escapeHtml(issue.issue_key)}" class="dropdown-item d-flex align-items-center py-2 border-bottom">
                        <span class="badge status-${Utils.escapeHtml(issue.status)} mr-2 text-smaller" style="width: 70px;">${Utils.escapeHtml(issue.status.replace('_', ' '))}</span>
                        <div class="text-truncate">
                            <span class="text-primary font-weight-bold mr-1">[${Utils.escapeHtml(issue.issue_key)}]</span>
                            <span class="text-dark">${this.highlightMatch(Utils.escapeHtml(issue.title), query)}</span>
                        </div>
                    </a>
                `;
            });
        }

        // Render danh sách Projects
        if (data.projects && data.projects.length > 0) {
            totalResults += data.projects.length;
            html += `<div class="dropdown-header bg-light font-weight-bold text-uppercase text-small border-bottom py-1">Dự án</div>`;
            data.projects.forEach(project => {
                html += `
                    <a href="/projects/${Utils.escapeHtml(project.id)}" class="dropdown-item py-2 border-bottom">
                        <span class="text-dark font-weight-bold mr-1">[${Utils.escapeHtml(project.key)}]</span>
                        <span class="text-muted">${this.highlightMatch(Utils.escapeHtml(project.name), query)}</span>
                    </a>
                `;
            });
        }

        // Render danh sách Members
        if (data.members && data.members.length > 0) {
            totalResults += data.members.length;
            html += `<div class="dropdown-header bg-light font-weight-bold text-uppercase text-small border-bottom py-1">Thành viên</div>`;
            data.members.forEach(member => {
                html += `
                    <a href="/workspace/members" class="dropdown-item d-flex align-items-center py-2">
                        <img src="${Utils.escapeHtml(member.avatar_path || '/assets/img/avatar-default.svg')}" class="avatar-xs mr-2 border">
                        <span class="text-dark">${this.highlightMatch(Utils.escapeHtml(member.name), query)}</span>
                        <span class="text-muted text-small ml-2">(${Utils.escapeHtml(member.email)})</span>
                    </a>
                `;
            });
        }

        // Xử lý trạng thái rỗng
        if (totalResults === 0) {
            html = `<div class="p-4 text-center text-muted">Không tìm thấy kết quả nào cho "<strong>${Utils.escapeHtml(query)}</strong>"</div>`;
        } else {
            // Thêm nút Xem tất cả ở cuối
            html += `
                <a href="/search?q=${encodeURIComponent(query)}" class="dropdown-item text-center text-primary font-weight-bold py-2 bg-light">
                    Xem toàn bộ kết quả →
                </a>
            `;
        }

        this.searchResults.innerHTML = html;
    },

    /**
     * Highlight từ khóa tìm kiếm trong chuỗi kết quả
     */
    highlightMatch(text, query) {
        if (!query) return text;
        const regex = new RegExp(`(${query})`, 'gi');
        return text.replace(regex, '<mark class="bg-warning p-0">$1</mark>');
    },

    openDropdown() {
        this.searchResults.classList.remove('d-none');
    },

    closeDropdown() {
        this.searchResults.classList.add('d-none');
    }
};

// Khởi chạy khi DOM đã load xong
document.addEventListener('DOMContentLoaded', () => GlobalSearch.init());