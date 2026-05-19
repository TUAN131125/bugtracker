/**
 * app.js – Entry Point
 *
 * Page router: đọc data-page attribute, khởi tạo đúng module.
 * Không load tất cả JS cho mọi trang — tiết kiệm parse time
 * trên InfinityFree (ViewLayer Guide Phần 9.2).
 */

"use strict";

// ============================================================
// Global dropdown handler – dùng cho mọi dropdown trong app
// ============================================================
function initDropdowns() {
  document.addEventListener("click", (e) => {
    const trigger = e.target.closest('[aria-haspopup="true"]');

    // Đóng tất cả dropdown khác trước
    document.querySelectorAll('[aria-haspopup="true"]').forEach((btn) => {
      if (btn === trigger) return;
      const targetId = btn.getAttribute("aria-controls");
      const menu = targetId ? document.getElementById(targetId) : null;
      if (menu) {
        menu.hidden = true;
        btn.setAttribute("aria-expanded", "false");
      }
    });

    if (!trigger) return;

    const targetId = trigger.getAttribute("aria-controls");
    const menu = targetId ? document.getElementById(targetId) : null;
    if (!menu) return;

    const isOpen = !menu.hidden;
    menu.hidden = isOpen;
    trigger.setAttribute("aria-expanded", String(!isOpen));
  });

  // Đóng dropdown khi click ra ngoài
  document.addEventListener("click", (e) => {
    if (
      !e.target.closest("[aria-haspopup]") &&
      !e.target.closest('[role="menu"]')
    ) {
      document.querySelectorAll('[role="menu"]').forEach((menu) => {
        menu.hidden = true;
      });
      document.querySelectorAll("[aria-haspopup]").forEach((btn) => {
        btn.setAttribute("aria-expanded", "false");
      });
    }
  });
}

// ============================================================
// Sidebar toggle (mobile)
// ============================================================
function initSidebarToggle() {
  const toggleBtn = document.getElementById("sidebar-toggle-btn");
  const sidebar = document.getElementById("app-sidebar");
  const overlay = document.querySelector(".js-sidebar-overlay");

  if (!toggleBtn || !sidebar) return;

  // Show hamburger button on mobile
  const mq = window.matchMedia("(max-width: 1024px)");
  const toggleVisibility = () => {
    toggleBtn.style.display = mq.matches ? "flex" : "none";
  };
  toggleVisibility();
  mq.addEventListener("change", toggleVisibility);

  toggleBtn.addEventListener("click", () => {
    const isOpen = sidebar.classList.toggle("is-open");
    toggleBtn.setAttribute("aria-expanded", String(isOpen));

    if (overlay) {
      overlay.classList.toggle("is-visible", isOpen);
      overlay.setAttribute("aria-hidden", String(!isOpen));
    }
  });

  // Đóng sidebar khi click overlay
  overlay?.addEventListener("click", () => {
    sidebar.classList.remove("is-open");
    toggleBtn.setAttribute("aria-expanded", "false");
    overlay.classList.remove("is-visible");
  });

  // Đóng sidebar khi nhấn Escape
  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape" && sidebar.classList.contains("is-open")) {
      sidebar.classList.remove("is-open");
      toggleBtn.setAttribute("aria-expanded", "false");
      overlay?.classList.remove("is-visible");
      toggleBtn.focus();
    }
  });
}

// ============================================================
// Relative timestamps – data-relative-time attribute
// ============================================================
function initRelativeTimestamps() {
  const elements = document.querySelectorAll("[data-relative-time]");
  if (!elements.length) return;

  function formatRelative(dateStr) {
    const date = new Date(dateStr.replace(" ", "T"));
    const now = new Date();
    const diff = Math.floor((now - date) / 1000);

    if (diff < 60) return "vừa xong";
    if (diff < 3600) return `${Math.floor(diff / 60)} phút trước`;
    if (diff < 86400) return `${Math.floor(diff / 3600)} giờ trước`;
    if (diff < 604800) return `${Math.floor(diff / 86400)} ngày trước`;

    return date.toLocaleDateString("vi-VN");
  }

  elements.forEach((el) => {
    const datetime = el.getAttribute("datetime") || el.textContent.trim();
    el.textContent = formatRelative(datetime);
  });
}

// ============================================================
// PAGE ROUTER – Khởi tạo module theo data-page
// ============================================================
document.addEventListener("DOMContentLoaded", () => {
  // Global modules – chạy trên mọi trang authenticated
  initDropdowns();
  initSidebarToggle();
  initRelativeTimestamps();

  const page = document.documentElement.dataset.page;

  switch (page) {
    case "landing":
    case "terms":
      import("./pages/landing.js").then((m) => m.initLanding?.());
      break;

    // Trong switch(page) của app.js, thêm case:
    case "onboarding":
      import("./pages/auth.js").then((m) => m.initOnboarding?.());
      break;

    case "dashboard":
      import("./pages/dashboard.js").then((m) => m.initDashboard?.());
      break;

    case "issue-list":
      import("./pages/issue-list.js").then((m) => m.initIssueList?.());
      break;

    case "issue-detail":
      import("./pages/issue-detail.js").then((m) => m.initIssueDetail?.());
      break;

    case "issue-form":
      import("./pages/issue-form.js").then((m) => m.initIssueForm?.());
      break;

    case "members":
      import("./pages/members.js").then((m) => m.initMembers?.());
      break;
  }

  // Global search – chạy trên mọi trang có #global-search
  if (document.getElementById("global-search")) {
    import("./pages/global-search.js").then((m) => m.initGlobalSearch?.());
  }
});
