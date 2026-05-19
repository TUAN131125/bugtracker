/**
 * dashboard.js – Dashboard Page Module
 * /public_html/assets/js/pages/dashboard.js
 *
 * Chỉ được khởi tạo khi data-page="dashboard" (app.js kiểm tra trước khi import).
 * Phụ thuộc:
 *   - Chart.js 4.x (load có điều kiện trong base.php khi pageId==='dashboard')
 *   - api.js (Fetch wrapper – tự động gắn CSRF header)
 *   - toast.js (hiển thị thông báo lỗi)
 *   - utils.js (debounce, formatDate)
 *
 * QUY TẮC:
 *   - KHÔNG dùng fetch() trực tiếp – mọi AJAX qua API module (ViewLayer Guide 8.2)
 *   - Dừng polling khi tab ẩn (ViewLayer Guide 7.1 – tiết kiệm tài nguyên InfinityFree)
 *   - Chart data đọc từ <script type="application/json"> – KHÔNG AJAX riêng
 *
 * @see ViewLayer Implementation Guide v1.0.0 – Phần 6.2, 7.1
 * @see Task Assignment v1.0.0               – D3-026 (Ngày 6)
 * @see DashboardController                  – D1-028
 *
 * @author  Dev 3
 * @version 1.0.0
 */

import { API } from "../core/api.js";
import { Toast } from "../core/toast.js";

// ============================================================
// CONSTANTS – đọc từ DOM/PHP inject, không hardcode
// ============================================================

/**
 * Poll interval (ms) – đọc từ <meta name="poll-interval"> được PHP inject.
 * PHP constant: NOTIFICATION_POLL_INTERVAL (config.php Section 9)
 * Fallback: 60000ms nếu meta tag không tồn tại.
 */
const POLL_INTERVAL_MS = (() => {
  const meta = document.querySelector('meta[name="poll-interval"]');
  const val = parseInt(meta?.content ?? "60", 10);
  return (isNaN(val) || val < 10 ? 60 : val) * 1000;
})();

// ============================================================
// CHART COLOR MAP – đồng bộ với CSS variables (ViewLayer Guide 6.2)
// Đọc từ computed styles để tự động sync khi design token thay đổi
// ============================================================

/**
 * Đọc CSS custom property từ :root.
 * @param {string} varName – VD: '--color-primary-600'
 * @returns {string}
 */
function getCssVar(varName) {
  return getComputedStyle(document.documentElement)
    .getPropertyValue(varName)
    .trim();
}

/** Map status DB value → màu Chart.js (đồng bộ ViewLayer Guide Phần 2.5) */
const STATUS_COLOR_MAP = {
  open: () => getCssVar("--color-primary-600"),
  in_triage: () => getCssVar("--color-info-500"),
  in_progress: () => getCssVar("--color-warning-500"),
  resolved: () => getCssVar("--color-success-500"),
  closed: "#14532D",
  reopened: () => getCssVar("--color-danger-500"),
  wont_fix: () => getCssVar("--color-neutral-400"),
  duplicate: () => getCssVar("--color-neutral-400"),
};

/**
 * Resolve màu từ map – hỗ trợ cả string và function.
 * @param {string} status
 * @returns {string}
 */
function resolveStatusColor(status) {
  const entry = STATUS_COLOR_MAP[status] ?? getCssVar("--color-neutral-300");
  return typeof entry === "function" ? entry() : entry;
}

// ============================================================
// MODULE STATE
// ============================================================

let _pollingInterval = null; // setInterval ID – dừng khi tab ẩn
let _donutChart = null; // Chart.js instance – giữ ref để destroy nếu cần
let _barChart = null; // Chart.js instance

// ============================================================
// CHART INITIALIZATION
// ============================================================

/**
 * Đọc chart data từ <script type="application/json" id="dashboard-chart-data">
 * PHP inject trong dashboard/index.php – ViewLayer Guide Phần 6.2.
 *
 * @returns {{ status_counts: Array, project_counts: Array } | null}
 */
function readChartData() {
  const el = document.getElementById("dashboard-chart-data");
  if (!el) return null;

  try {
    return JSON.parse(el.textContent);
  } catch (err) {
    console.error("[Dashboard] chart data parse failed:", err);
    return null;
  }
}

/**
 * Khởi tạo Donut Chart – phân phối Issue theo status.
 *
 * @param {Array} statusCounts – [{ status, count, label }]
 */
function initDonutChart(statusCounts) {
  const canvas = document.getElementById("status-donut-chart");
  if (!canvas || !statusCounts?.length) return;

  // Chart.js global defaults – minimal style
  Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
  Chart.defaults.font.size = 12;
  Chart.defaults.color = getCssVar("--color-neutral-700");

  const labels = statusCounts.map((d) => d.label);
  const data = statusCounts.map((d) => parseInt(d.count, 10));
  const colors = statusCounts.map((d) => resolveStatusColor(d.status));

  _donutChart = new Chart(canvas, {
    type: "doughnut",
    data: {
      labels,
      datasets: [
        {
          data,
          backgroundColor: colors,
          borderColor: "var(--color-surface, #ffffff)",
          borderWidth: 3,
          hoverBorderWidth: 3,
          hoverOffset: 4,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: true,
      cutout: "68%",
      animation: {
        animateRotate: true,
        duration: 600,
        easing: "easeInOutQuart",
      },
      plugins: {
        legend: {
          display: false, // Custom legend bên dưới canvas
        },
        tooltip: {
          callbacks: {
            label(ctx) {
              const total = ctx.dataset.data.reduce((a, b) => a + b, 0);
              const pct =
                total > 0 ? ((ctx.parsed / total) * 100).toFixed(1) : 0;
              return ` ${ctx.label}: ${ctx.parsed} (${pct}%)`;
            },
          },
          padding: 10,
          cornerRadius: 6,
          displayColors: true,
          boxWidth: 10,
          boxHeight: 10,
        },
      },
    },
  });

  // Render custom legend vào #donut-legend
  renderDonutLegend(statusCounts, colors);
}

/**
 * Render custom legend HTML cho Donut Chart.
 * Không dùng Chart.js built-in legend để kiểm soát layout tốt hơn.
 *
 * @param {Array}  statusCounts
 * @param {Array}  colors
 */
function renderDonutLegend(statusCounts, colors) {
  const container = document.getElementById("donut-legend");
  if (!container) return;

  container.innerHTML = statusCounts
    .map(
      (d, i) => `
        <div class="chart-legend__item">
            <span class="chart-legend__dot"
                  style="background:${escapeAttr(colors[i])}"
                  aria-hidden="true"></span>
            <span>${escapeHtml(d.label)}: <strong>${parseInt(d.count, 10)}</strong></span>
        </div>
    `,
    )
    .join("");
}

/**
 * Khởi tạo Bar Chart – số Issue theo từng Project.
 *
 * @param {Array} projectCounts – [{ project_name, project_key, count }]
 */
function initBarChart(projectCounts) {
  const canvas = document.getElementById("project-bar-chart");
  if (!canvas || !projectCounts?.length) return;

  const labels = projectCounts.map((d) => d.project_key);
  const data = projectCounts.map((d) => parseInt(d.count, 10));
  const color = getCssVar("--color-primary-600");
  const hover = getCssVar("--color-primary-700");

  _barChart = new Chart(canvas, {
    type: "bar",
    data: {
      labels,
      datasets: [
        {
          label: "Số Issue",
          data,
          backgroundColor: color,
          hoverBackgroundColor: hover,
          borderRadius: 4,
          borderSkipped: false,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      animation: {
        duration: 500,
        easing: "easeInOutQuart",
      },
      scales: {
        x: {
          grid: { display: false },
          ticks: {
            font: { size: 11 },
            color: getCssVar("--color-neutral-400"),
          },
          border: { display: false },
        },
        y: {
          beginAtZero: true,
          grid: {
            color: getCssVar("--color-neutral-100"),
          },
          ticks: {
            precision: 0,
            font: { size: 11 },
            color: getCssVar("--color-neutral-400"),
          },
          border: { display: false, dash: [4, 4] },
        },
      },
      plugins: {
        legend: { display: false },
        tooltip: {
          callbacks: {
            title: (items) => {
              const idx = items[0]?.dataIndex;
              const proj = projectCounts[idx];
              return proj ? `${proj.project_name} (${proj.project_key})` : "";
            },
            label: (ctx) => ` ${ctx.parsed.y} issues`,
          },
          padding: 10,
          cornerRadius: 6,
          displayColors: false,
        },
      },
    },
  });
}

// ============================================================
// COUNT-UP ANIMATION (Stat Cards)
// ============================================================

/**
 * Animate số từ 0 → target trong duration ms.
 * Chỉ chạy nếu user không prefer-reduced-motion.
 *
 * @param {HTMLElement} el
 * @param {number}      target
 * @param {number}      [duration=600]
 */
function animateCountUp(el, target, duration = 600) {
  if (!el) return;

  // Tôn trọng prefers-reduced-motion (Accessibility)
  if (window.matchMedia("(prefers-reduced-motion: reduce)").matches) {
    el.textContent = target.toLocaleString("vi-VN");
    return;
  }

  const start = performance.now();
  const startVal = 0;

  function tick(now) {
    const elapsed = now - start;
    const progress = Math.min(elapsed / duration, 1);
    // Ease out quart
    const eased = 1 - Math.pow(1 - progress, 4);
    const current = Math.round(startVal + (target - startVal) * eased);

    el.textContent = current.toLocaleString("vi-VN");

    if (progress < 1) {
      requestAnimationFrame(tick);
    }
  }

  requestAnimationFrame(tick);
}

/**
 * Chạy count-up cho tất cả stat card values.
 */
function runCountUpAnimations() {
  const targets = {
    "stat-total": parseInt(
      document.getElementById("stat-total")?.textContent ?? "0",
      10,
    ),
    "stat-open": parseInt(
      document.getElementById("stat-open")?.textContent ?? "0",
      10,
    ),
    "stat-in-progress": parseInt(
      document.getElementById("stat-in-progress")?.textContent ?? "0",
      10,
    ),
    "stat-overdue": parseInt(
      document.getElementById("stat-overdue")?.textContent ?? "0",
      10,
    ),
  };

  for (const [id, value] of Object.entries(targets)) {
    const el = document.getElementById(id);
    if (el) animateCountUp(el, value, 700);
  }
}

// ============================================================
// NOTIFICATION POLLING (ViewLayer Guide Phần 7.1)
// ============================================================

/**
 * Cập nhật badge số notification chưa đọc trên bell icon.
 *
 * @param {number} count
 */
function updateNotificationBadge(count) {
  const badge = document.getElementById("notif-badge");
  if (!badge) return;

  const num = parseInt(count, 10) || 0;

  badge.textContent = num > 99 ? "99+" : String(num);
  badge.hidden = num === 0;
  badge.setAttribute("aria-label", `${num} thông báo chưa đọc`);
}

/**
 * Gọi API lấy số notification chưa đọc.
 * Silent fail – không hiện toast khi polling thất bại (ViewLayer Guide 7.1).
 */
async function fetchNotificationCount() {
  try {
    const data = await API.get("/api/notifications");
    updateNotificationBadge(data?.unread_count ?? 0);

    // Cập nhật "Cập nhật lúc..."
    const el = document.getElementById("last-updated");
    if (el) {
      const now = new Date();
      el.textContent = now.toLocaleTimeString("vi-VN", {
        hour: "2-digit",
        minute: "2-digit",
      });
    }
  } catch {
    // Silent – polling failure không phải lỗi nghiêm trọng
    console.warn("[Dashboard] Notification poll failed, will retry.");
  }
}

/**
 * Bắt đầu polling.
 * Fetch ngay lập tức lần đầu, sau đó theo POLL_INTERVAL_MS.
 */
function startPolling() {
  fetchNotificationCount(); // Fetch ngay
  _pollingInterval = setInterval(fetchNotificationCount, POLL_INTERVAL_MS);
}

/**
 * Dừng polling – gọi khi tab bị ẩn (visibilitychange).
 */
function stopPolling() {
  if (_pollingInterval !== null) {
    clearInterval(_pollingInterval);
    _pollingInterval = null;
  }
}

// ============================================================
// VISIBILITY CHANGE – tiết kiệm request khi tab ẩn
// ViewLayer Guide Phần 7.1
// ============================================================

function handleVisibilityChange() {
  if (document.hidden) {
    stopPolling();
  } else {
    startPolling();
  }
}

// ============================================================
// SECURITY HELPERS (dùng trong renderDonutLegend)
// Đồng bộ với utils.js nhưng khai báo local để không phụ thuộc import
// ============================================================

function escapeHtml(str) {
  return String(str)
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#039;");
}

function escapeAttr(str) {
  return String(str).replace(/[^#0-9a-zA-Z,.()\s%]/g, "");
}

// ============================================================
// INIT – Entry point, được gọi từ app.js
// ============================================================

/**
 * Khởi tạo toàn bộ Dashboard module.
 * app.js gọi hàm này khi document.body.dataset.page === 'dashboard'.
 */
export function init() {
  // ── 1. Đọc chart data từ DOM ──────────────────────────────
  const chartData = readChartData();

  // ── 2. Khởi tạo Charts (sau khi Chart.js CDN đã load) ────
  //    Chart.js load với defer – đảm bảo DOM ready trước khi gọi
  if (typeof Chart !== "undefined") {
    if (chartData?.status_counts?.length) {
      initDonutChart(chartData.status_counts);
    }
    if (chartData?.project_counts?.length) {
      initBarChart(chartData.project_counts);
    }
  } else {
    // Chart.js chưa load (hiếm xảy ra với defer) – retry sau 500ms
    console.warn("[Dashboard] Chart.js not ready, retrying...");
    setTimeout(() => {
      if (chartData?.status_counts?.length)
        initDonutChart(chartData.status_counts);
      if (chartData?.project_counts?.length)
        initBarChart(chartData.project_counts);
    }, 500);
  }

  // ── 3. Count-up animation cho stat cards ──────────────────
  runCountUpAnimations();

  // ── 4. Notification polling ───────────────────────────────
  startPolling();
  document.addEventListener("visibilitychange", handleVisibilityChange);

  // ── 5. Cleanup khi navigate away (nếu dùng SPA-style) ────
  window.addEventListener("beforeunload", () => {
    stopPolling();
    document.removeEventListener("visibilitychange", handleVisibilityChange);
  });
}
