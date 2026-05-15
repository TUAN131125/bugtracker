/**
 * landing.js
 * Script cho Landing Page — Scroll animations, Sticky navbar, Mobile menu.
 *
 * Không dùng jQuery hay thư viện nặng.
 * Tuân thủ ViewLayer Guide: không inline script trong .php file.
 * Module này được load bởi app.js khi data-page="landing".
 */

"use strict";

// ============================================================
// 1. STICKY NAVBAR — thêm class is-scrolled khi scroll xuống
// ============================================================

function initStickyNavbar() {
  const navbar = document.getElementById("landing-navbar");
  if (!navbar) return;

  const SCROLL_THRESHOLD = 60;

  function onScroll() {
    if (window.scrollY > SCROLL_THRESHOLD) {
      navbar.classList.add("is-scrolled");
    } else {
      navbar.classList.remove("is-scrolled");
    }
  }

  // Passive listener để không block scroll performance
  window.addEventListener("scroll", onScroll, { passive: true });

  // Kiểm tra ngay khi load (trường hợp reload trang đang ở giữa)
  onScroll();
}

// ============================================================
// 2. MOBILE MENU TOGGLE
// ============================================================

function initMobileMenu() {
  const hamburger = document.querySelector(".js-hamburger");
  const mobileMenu = document.getElementById("mobile-menu");
  if (!hamburger || !mobileMenu) return;

  hamburger.addEventListener("click", () => {
    const isOpen = !mobileMenu.hidden;

    if (isOpen) {
      // Đóng menu
      mobileMenu.hidden = true;
      mobileMenu.setAttribute("aria-hidden", "true");
      hamburger.setAttribute("aria-expanded", "false");
      hamburger.setAttribute("aria-label", "Mở menu");
    } else {
      // Mở menu
      mobileMenu.hidden = false;
      mobileMenu.setAttribute("aria-hidden", "false");
      hamburger.setAttribute("aria-expanded", "true");
      hamburger.setAttribute("aria-label", "Đóng menu");
    }
  });

  // Đóng menu khi click vào link bên trong
  mobileMenu.querySelectorAll("a").forEach((link) => {
    link.addEventListener("click", () => {
      mobileMenu.hidden = true;
      mobileMenu.setAttribute("aria-hidden", "true");
      hamburger.setAttribute("aria-expanded", "false");
      hamburger.setAttribute("aria-label", "Mở menu");
    });
  });

  // Đóng menu khi click ra ngoài
  document.addEventListener("click", (event) => {
    const navbar = document.getElementById("landing-navbar");
    if (navbar && !navbar.contains(event.target) && !mobileMenu.hidden) {
      mobileMenu.hidden = true;
      mobileMenu.setAttribute("aria-hidden", "true");
      hamburger.setAttribute("aria-expanded", "false");
      hamburger.setAttribute("aria-label", "Mở menu");
    }
  });
}

// ============================================================
// 3. SMOOTH SCROLL — cho các anchor link #features, #pricing...
// ============================================================

function initSmoothScroll() {
  document.querySelectorAll('a[href^="#"]').forEach((anchor) => {
    anchor.addEventListener("click", (event) => {
      const href = anchor.getAttribute("href");
      const target = document.querySelector(href);

      if (!target) return;

      event.preventDefault();

      // Offset để tránh bị navbar che khuất
      const navbarHeight =
        document.getElementById("landing-navbar")?.offsetHeight ?? 64;
      const targetTop =
        target.getBoundingClientRect().top + window.scrollY - navbarHeight - 16;

      window.scrollTo({
        top: targetTop,
        behavior: "smooth",
      });
    });
  });
}

// ============================================================
// 4. SCROLL ANIMATION — Intersection Observer cho js-animate-on-scroll
// ============================================================

function initScrollAnimations() {
  // Fallback nếu browser không hỗ trợ IntersectionObserver
  if (!("IntersectionObserver" in window)) {
    document.querySelectorAll(".js-animate-on-scroll").forEach((el) => {
      el.classList.add("is-visible");
    });
    return;
  }

  const observer = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          entry.target.classList.add("is-visible");

          // Unobserve sau khi đã animate để tiết kiệm memory
          observer.unobserve(entry.target);
        }
      });
    },
    {
      // Element được coi là "vào viewport" khi hiển thị 15%
      threshold: 0.15,
      // Bắt đầu observe sớm hơn 50px trước khi vào viewport
      rootMargin: "0px 0px -50px 0px",
    },
  );

  document.querySelectorAll(".js-animate-on-scroll").forEach((el) => {
    observer.observe(el);
  });
}

// ============================================================
// 5. ACTIVE NAV LINK — highlight link khi scroll đến section
// ============================================================

function initActiveNavHighlight() {
  const sections = document.querySelectorAll("section[id]");
  const navLinks = document.querySelectorAll(
    '.landing-navbar__link[href^="/#"]',
  );

  if (!sections.length || !navLinks.length) return;

  const observer = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          const id = entry.target.getAttribute("id");

          navLinks.forEach((link) => {
            const isActive = link.getAttribute("href") === `/#${id}`;
            link.classList.toggle("is-active", isActive);
          });
        }
      });
    },
    {
      // Section được coi là active khi chiếm 40% viewport
      threshold: 0.4,
    },
  );

  sections.forEach((section) => observer.observe(section));
}

// ============================================================
// INIT — Gọi tất cả modules khi DOM sẵn sàng
// app.js sẽ gọi hàm này khi data-page="landing"
// ============================================================

export function initLanding() {
  initStickyNavbar();
  initMobileMenu();
  initSmoothScroll();
  initScrollAnimations();
  initActiveNavHighlight();
}
