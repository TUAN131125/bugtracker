/**
 * validator.js – Core Form Validation & Submit Handler
 *
 * Phiên bản viết lại hoàn toàn:
 *   - KHÔNG dùng async trong submit handler (tránh silent abort)
 *   - KHÔNG dùng event delegation trên body (gắn trực tiếp vào mỗi form)
 *   - KHÔNG disable button trước khi validation xong
 *   - Có console.log debug tại mọi checkpoint
 *   - Có alert() xác nhận trước khi gửi form (theo yêu cầu kiểm thử)
 *
 * Selector: form[data-form-validate]
 * Button:   [data-submit-btn] bên trong form
 */

(function () {
  "use strict";

  console.log("[Validator] Script loaded. Waiting for DOM...");

  /**
   * Validate một input dựa trên data-validate và required.
   * Trả về null nếu hợp lệ, string lỗi nếu không hợp lệ.
   *
   * @param {HTMLElement} input
   * @returns {string|null}
   */
  function validateInput(input) {
    // Bỏ qua input hidden (CSRF token, v.v.)
    if (input.type === "hidden") return null;

    var value = input.value.trim();
    var rules = input.dataset.validate
      ? input.dataset.validate.split("|")
      : [];

    // Nếu input có thuộc tính required nhưng chưa khai báo rule required
    if (input.hasAttribute("required") && rules.indexOf("required") === -1) {
      rules.push("required");
    }

    for (var i = 0; i < rules.length; i++) {
      var rule = rules[i];

      if (rule === "required" && !value) {
        return "Trường này không được để trống.";
      }

      // Chỉ check các rule khác nếu có giá trị
      if (value) {
        if (rule.indexOf("min:") === 0) {
          var min = parseInt(rule.split(":")[1], 10);
          if (value.length < min) {
            return "Vui lòng nhập ít nhất " + min + " ký tự.";
          }
        }

        if (rule.indexOf("max:") === 0) {
          var max = parseInt(rule.split(":")[1], 10);
          if (value.length > max) {
            return "Vui lòng nhập không quá " + max + " ký tự.";
          }
        }

        if (rule.indexOf("pattern:") === 0) {
          var pattern = new RegExp(rule.substring(8));
          if (!pattern.test(value)) {
            return "Định dạng không hợp lệ.";
          }
        }
      }
    }

    return null;
  }

  /**
   * Hiển thị lỗi dưới input (viền đỏ + text lỗi).
   *
   * @param {HTMLElement} input
   * @param {string} message
   */
  function showError(input, message) {
    var group = input.closest(".form-group");
    if (!group) return;

    group.classList.add("form-group--error");
    input.setAttribute("aria-invalid", "true");

    var errEl = document.createElement("p");
    errEl.className = "form-error";
    errEl.setAttribute("role", "alert");
    errEl.innerHTML =
      '<svg aria-hidden="true" viewBox="0 0 20 20" fill="currentColor">' +
      '<path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>' +
      "</svg> " +
      message;
    group.appendChild(errEl);
  }

  /**
   * Xóa tất cả lỗi cũ bên trong một form.
   *
   * @param {HTMLFormElement} form
   */
  function clearErrors(form) {
    var errorGroups = form.querySelectorAll(".form-group--error");
    for (var i = 0; i < errorGroups.length; i++) {
      errorGroups[i].classList.remove("form-group--error");
    }

    var errorMsgs = form.querySelectorAll(".form-error");
    for (var j = 0; j < errorMsgs.length; j++) {
      errorMsgs[j].parentNode.removeChild(errorMsgs[j]);
    }

    var invalidInputs = form.querySelectorAll("[aria-invalid]");
    for (var k = 0; k < invalidInputs.length; k++) {
      invalidInputs[k].setAttribute("aria-invalid", "false");
    }
  }

  /**
   * Khóa nút bấm + hiển thị spinner.
   * CHỈ ĐƯỢC GỌI SAU KHI VALIDATION HOÀN TẤT VÀ isValid === true.
   *
   * @param {HTMLElement} btn
   */
  function lockButton(btn) {
    if (!btn) return;
    btn.disabled = true;
    var btnText = btn.querySelector(".btn__text");
    var btnSpinner = btn.querySelector(".btn__spinner");
    if (btnText) btnText.hidden = true;
    if (btnSpinner) btnSpinner.hidden = false;
  }

  /**
   * Mở khóa nút bấm (dùng khi AJAX thất bại).
   *
   * @param {HTMLElement} btn
   */
  function unlockButton(btn) {
    if (!btn) return;
    btn.disabled = false;
    var btnText = btn.querySelector(".btn__text");
    var btnSpinner = btn.querySelector(".btn__spinner");
    if (btnText) btnText.hidden = false;
    if (btnSpinner) btnSpinner.hidden = true;
  }

  /**
   * Xử lý sự kiện submit cho một form cụ thể.
   * Gắn trực tiếp vào form, KHÔNG dùng event delegation.
   *
   * @param {Event} e
   */
  function handleSubmit(e) {
    var form = e.currentTarget;

    console.log("[Validator] Submit event fired for form:", form.action);

    // Ngăn browser submit mặc định để tự xử lý validation
    e.preventDefault();
    e.stopPropagation();

    console.log("[Validator] Default prevented. Starting validation...");

    // ── Bước 1: Dọn dẹp lỗi cũ (scoped trong form này) ──
    clearErrors(form);

    // ── Bước 2: Validate từng input BÊN TRONG form này ──
    var inputs = form.querySelectorAll("input, textarea, select");
    var isValid = true;
    var firstInvalidInput = null;

    console.log("[Validator] Found " + inputs.length + " inputs to validate.");

    for (var i = 0; i < inputs.length; i++) {
      var input = inputs[i];
      var errorMsg = validateInput(input);

      if (errorMsg) {
        isValid = false;
        showError(input, errorMsg);
        if (!firstInvalidInput) {
          firstInvalidInput = input;
        }
        console.log(
          "[Validator] INVALID: " + (input.name || input.id) + " → " + errorMsg
        );
      }
    }

    // ── Bước 3: Nếu không hợp lệ, focus vào input lỗi đầu tiên ──
    if (!isValid) {
      console.log("[Validator] Validation FAILED. Aborting submit.");
      if (firstInvalidInput) {
        firstInvalidInput.focus();
      }
      // KHÔNG disable nút, KHÔNG return im lặng mà đã hiển thị lỗi ở trên
      return;
    }

    console.log("[Validator] Validation PASSED. Preparing to submit...");

    // ── Bước 4: Khóa nút SAU KHI validation thành công ──
    var submitBtn = form.querySelector("[data-submit-btn]");
    lockButton(submitBtn);

    // ── Bước 5: Gửi form ──
    if (form.hasAttribute("data-ajax")) {
      // ── Kịch bản AJAX ──
      console.log("[Validator] AJAX mode. Sending via fetch...");
      var formData = new FormData(form);
      var jsonData = {};
      formData.forEach(function (value, key) {
        jsonData[key] = value;
      });

      fetch(form.action, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
          "X-Requested-With": "XMLHttpRequest",
        },
        body: JSON.stringify(jsonData),
      })
        .then(function (response) {
          return response.json();
        })
        .then(function (data) {
          if (data && data.success) {
            window.location.href = data.redirect || "/dashboard";
          } else {
            throw new Error(data.message || "Xử lý thất bại.");
          }
        })
        .catch(function (error) {
          console.error("[Validator] AJAX Error:", error);
          unlockButton(submitBtn);
        });
    } else {
      // ── Kịch bản Form truyền thống (Onboarding dùng cách này) ──
      console.log("[Validator] Traditional mode. Calling native form.submit()...");

      // DEBUG: Alert để kiểm chứng trực quan JS đã chạy đến đây
      alert("Validator hợp lệ! Trình duyệt đang gửi dữ liệu lên Backend...");

      // Gọi hàm submit gốc của HTMLFormElement prototype
      // để bypass mọi override và event listener khác
      HTMLFormElement.prototype.submit.call(form);
    }
  }

  // ============================================================
  // KHỞI TẠO: Gắn submit listener trực tiếp vào từng form
  // ============================================================
  function init() {
    var forms = document.querySelectorAll("form[data-form-validate]");

    console.log(
      "[Validator] Init: Found " + forms.length + " form(s) with [data-form-validate]."
    );

    for (var i = 0; i < forms.length; i++) {
      forms[i].addEventListener("submit", handleSubmit);
      console.log("[Validator] Attached submit handler to form: " + forms[i].action);
    }
  }

  // Đợi DOM sẵn sàng
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    // DOM đã load xong (module script luôn deferred)
    init();
  }
})();
