// ============================================================
// ONBOARDING – Thêm vào cuối pages/auth.js
// Toggle inline form khi click card trigger
// ============================================================

/**
 * initOnboarding()
 *
 * Xử lý toggle 2 card option trên trang Onboarding.
 * Khi click một card → mở panel của nó, đóng card kia.
 * Đọc data-target để tìm panel tương ứng.
 * Đọc data-group để tìm các trigger cùng nhóm (đóng nhau).
 */
export function initOnboarding() {
  const triggers = document.querySelectorAll(".js-onboarding-trigger");
  if (!triggers.length) return;

  triggers.forEach((trigger) => {
    trigger.addEventListener("click", () => {
      const targetId = trigger.dataset.target;
      const group = trigger.dataset.group;
      const panel = document.getElementById(targetId);
      const isOpen = trigger.getAttribute("aria-expanded") === "true";

      // Đóng tất cả triggers cùng group và panels của chúng
      document
        .querySelectorAll(`.js-onboarding-trigger[data-group="${group}"]`)
        .forEach((t) => {
          const otherId = t.dataset.target;
          const otherPanel = document.getElementById(otherId);
          t.setAttribute("aria-expanded", "false");
          if (otherPanel) otherPanel.hidden = true;
        });

      // Toggle panel hiện tại
      if (!isOpen && panel) {
        trigger.setAttribute("aria-expanded", "true");
        panel.hidden = false;

        // Focus vào input đầu tiên trong panel
        const firstInput = panel.querySelector("input, textarea");
        if (firstInput) {
          // Delay nhỏ để animation kịp chạy
          setTimeout(() => firstInput.focus(), 80);
        }
      }
    });
  });

  // Slug auto-generate từ tên Workspace
  initSlugAutoGenerate();

  // Mở lại card đang có lỗi (nếu trang reload sau submit lỗi)
  autoOpenErrorCard();
}

/**
 * Auto-generate URL slug từ tên Workspace khi user gõ.
 * Dùng SlugGenerator JS tương tự PHP SlugGenerator::make().
 */
function initSlugAutoGenerate() {
  const nameInput = document.getElementById("workspace-name");
  const slugInput = document.querySelector(".js-ws-slug");
  if (!nameInput || !slugInput) return;

  let userEditedSlug = slugInput.value.trim().length > 0;

  // Nếu user tự sửa slug → dừng auto-generate
  slugInput.addEventListener("input", () => {
    userEditedSlug = true;
  });

  nameInput.addEventListener("input", () => {
    if (userEditedSlug) return;
    slugInput.value = makeSlug(nameInput.value);
  });
}

/**
 * Chuyển chuỗi tiếng Việt thành slug URL-friendly.
 * Đồng bộ với PHP SlugGenerator::make() (D1-016).
 *
 * @param {string} text
 * @returns {string}
 */
function makeSlug(text) {
  const vietnameseMap = {
    à: "a",
    á: "a",
    ả: "a",
    ã: "a",
    ạ: "a",
    ă: "a",
    ằ: "a",
    ắ: "a",
    ẳ: "a",
    ẵ: "a",
    ặ: "a",
    â: "a",
    ầ: "a",
    ấ: "a",
    ẩ: "a",
    ẫ: "a",
    ậ: "a",
    è: "e",
    é: "e",
    ẻ: "e",
    ẽ: "e",
    ẹ: "e",
    ê: "e",
    ề: "e",
    ế: "e",
    ể: "e",
    ễ: "e",
    ệ: "e",
    ì: "i",
    í: "i",
    ỉ: "i",
    ĩ: "i",
    ị: "i",
    ò: "o",
    ó: "o",
    ỏ: "o",
    õ: "o",
    ọ: "o",
    ô: "o",
    ồ: "o",
    ố: "o",
    ổ: "o",
    ỗ: "o",
    ộ: "o",
    ơ: "o",
    ờ: "o",
    ớ: "o",
    ở: "o",
    ỡ: "o",
    ợ: "o",
    ù: "u",
    ú: "u",
    ủ: "u",
    ũ: "u",
    ụ: "u",
    ư: "u",
    ừ: "u",
    ứ: "u",
    ử: "u",
    ữ: "u",
    ự: "u",
    ỳ: "y",
    ý: "y",
    ỷ: "y",
    ỹ: "y",
    ỵ: "y",
    đ: "d",
  };

  return text
    .toLowerCase()
    .split("")
    .map((c) => vietnameseMap[c] ?? c)
    .join("")
    .replace(/[^a-z0-9\s-]/g, "")
    .trim()
    .replace(/[\s]+/g, "-")
    .replace(/-{2,}/g, "-")
    .slice(0, 150);
}

/**
 * Nếu server trả về lỗi và reload trang,
 * tự động mở lại card đang có form lỗi.
 */
function autoOpenErrorCard() {
  // Card A có lỗi nếu input tên hoặc slug có class form-group--error
  const createPanel = document.getElementById("form-create");
  const joinPanel = document.getElementById("form-join");

  if (createPanel) {
    const hasCreateError = createPanel.querySelector(".form-group--error");
    if (hasCreateError) {
      const trigger = document.querySelector('[data-target="form-create"]');
      if (trigger) {
        trigger.setAttribute("aria-expanded", "true");
        createPanel.hidden = false;
      }
    }
  }

  if (joinPanel) {
    const hasJoinError = joinPanel.querySelector(".form-group--error");
    if (hasJoinError) {
      const trigger = document.querySelector('[data-target="form-join"]');
      if (trigger) {
        trigger.setAttribute("aria-expanded", "true");
        joinPanel.hidden = false;
      }
    }
  }
}

// ============================================================
// AUTO-INIT – Auth Layout Page Router
//
// WHY cần block này:
//   Auth layout (auth.php) load auth.js TRỰC TIẾP nhưng KHÔNG
//   load app.js (app.js chỉ có trong app layout cho trang sau login).
//   Do đó page router trong app.js không bao giờ chạy trên auth pages.
//   Block này thay thế vai trò page router cho auth layout.
// ============================================================
document.addEventListener("DOMContentLoaded", () => {
  const page = document.documentElement.dataset.page;

  switch (page) {
    case "onboarding":
      initOnboarding();
      break;

    // Thêm các case khác cho auth pages nếu cần trong tương lai
    // case "login":
    //   initLogin();
    //   break;
  }
});
