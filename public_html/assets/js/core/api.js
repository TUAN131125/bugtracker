/**
 * BugTracker - API Wrapper
 */
const Api = {
  // Trích xuất CSRF Token từ form trên giao diện
  getCSRFToken: () => {
    const tokenInput = document.querySelector('input[name="csrf_token"]');
    return tokenInput ? tokenInput.value : "";
  },

  // Hàm gọi API cốt lõi
  async request(endpoint, method = "GET", data = null) {
    const headers = {
      Accept: "application/json",
      "X-Requested-With": "XMLHttpRequest", // Báo cho Backend biết đây là AJAX
      "X-CSRF-TOKEN": this.getCSRFToken(),
    };

    const options = {
      method: method.toUpperCase(),
      headers: headers,
    };

    if (data && (method === "POST" || method === "PUT" || method === "PATCH")) {
      headers["Content-Type"] = "application/json";
      options.body = JSON.stringify(data);
    }

    try {
      const response = await fetch(endpoint, options);
      const responseData = await response.json().catch(() => null);

      if (!response.ok) {
        // Ném lỗi ra để khối catch bên ngoài (ở file page.js) xử lý
        throw {
          status: response.status,
          message: responseData?.message || "Đã xảy ra lỗi hệ thống.",
          errors: responseData?.errors || {},
        };
      }

      return responseData;
    } catch (error) {
      // Nếu lỗi không phải từ server trả về (VD: mất mạng)
      if (!error.status) {
        console.error("Network Error:", error);
        Toast.show(
          "Không thể kết nối đến máy chủ. Vui lòng kiểm tra mạng.",
          "danger",
        );
      }
      throw error;
    }
  },

  get(endpoint) {
    return this.request(endpoint, "GET");
  },
  post(endpoint, data) {
    return this.request(endpoint, "POST", data);
  },
  put(endpoint, data) {
    return this.request(endpoint, "PUT", data);
  },
  delete(endpoint) {
    return this.request(endpoint, "DELETE");
  },
};
