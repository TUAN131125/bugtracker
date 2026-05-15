/**
 * BugTracker API Wrapper
 * Quản lý toàn bộ request gửi lên server.
 * Tự động gắn CSRF token và tự động hiển thị lỗi qua Toast.
 */
const API = {
  /**
   * Hàm gọi API chung (Base Request)
   */
  async request(endpoint, options = {}) {
    // Cấu hình Header mặc định báo cho Backend biết đây là AJAX request
    const headers = {
      "Content-Type": "application/json",
      Accept: "application/json",
      "X-Requested-With": "XMLHttpRequest",
      ...options.headers,
    };

    // Lấy CSRF Token để chống hacker giả mạo form
    // Ưu tiên lấy từ thẻ <meta>, nếu không có thì tìm trong hidden input
    const csrfToken =
      document
        .querySelector('meta[name="csrf-token"]')
        ?.getAttribute("content") ||
      document.querySelector('input[name="csrf_token"]')?.value;

    // Gắn CSRF Token vào Header với các method làm thay đổi dữ liệu
    if (
      csrfToken &&
      options.method &&
      !["GET", "HEAD", "OPTIONS"].includes(options.method.toUpperCase())
    ) {
      headers["X-CSRF-TOKEN"] = csrfToken;
    }

    try {
      // Thực hiện gọi API
      const response = await fetch(endpoint, {
        ...options,
        headers,
      });

      // Parse dữ liệu Backend trả về (mong đợi chuẩn JSON)
      let data;
      try {
        data = await response.json();
      } catch (parseError) {
        // Nếu backend văng lỗi PHP (HTML) thay vì JSON
        throw new Error(
          "Máy chủ trả về dữ liệu không hợp lệ (Không phải JSON).",
        );
      }

      // Nếu Backend trả về mã lỗi (4xx, 5xx)
      if (!response.ok) {
        // Tự động hiển thị lỗi bằng Toast nếu Dev 1 đã đính kèm thuộc tính 'message'
        const errorMessage =
          data.message || data.error || "Có lỗi xảy ra từ máy chủ.";
        if (typeof Toast !== "undefined") {
          Toast.show(errorMessage, "error");
        }
        throw new Error(errorMessage); // Ném lỗi ra để màn hình gọi API tự xử lý thêm (nếu cần)
      }

      return data;
    } catch (error) {
      console.error("[API Error]:", error);

      // Bắt lỗi mất kết nối mạng hoặc server sập hẳn
      if (
        error.message.includes("Failed to fetch") &&
        typeof Toast !== "undefined"
      ) {
        Toast.show(
          "Không thể kết nối đến máy chủ. Vui lòng kiểm tra mạng.",
          "error",
        );
      }

      throw error;
    }
  },

  // Các hàm viết tắt (Shorthand methods) cho tiện gọi
  get(endpoint) {
    return this.request(endpoint, { method: "GET" });
  },

  post(endpoint, body = {}) {
    return this.request(endpoint, {
      method: "POST",
      body: JSON.stringify(body),
    });
  },

  put(endpoint, body = {}) {
    return this.request(endpoint, {
      method: "PUT",
      body: JSON.stringify(body),
    });
  },

  delete(endpoint, body = {}) {
    return this.request(endpoint, {
      method: "DELETE",
      body: JSON.stringify(body),
    });
  },
};
