// Ayurvedic Wellness House - Main JavaScript
// GSAP animations and scroll effects

import gsap from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';

// Register GSAP plugins
gsap.registerPlugin(ScrollTrigger);

// Check for reduced motion preference
const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

// ============================================
// Navbar Scroll Effects
// ============================================
function initNavbarScroll() {
  const navbar = document.getElementById('navbar');
  if (!navbar) return;

  if (prefersReducedMotion.matches) {
    window.addEventListener('scroll', () => {
      if (window.scrollY > 50) {
        navbar.classList.add('scrolled');
      } else {
        navbar.classList.remove('scrolled');
      }
    }, { passive: true });
    return;
  }

  // GSAP-powered scroll effects
  ScrollTrigger.create({
    start: 'top -80',
    onUpdate: (self) => {
      if (self.direction === 1 && self.progress > 0) {
        navbar.classList.add('scrolled');
      } else if (self.progress === 0) {
        navbar.classList.remove('scrolled');
      }
    }
  });
}

// ============================================
// Scroll Animations
// ============================================
function initScrollAnimations() {
  if (prefersReducedMotion.matches) return;

  // Fade in up animation for sections
  gsap.utils.toArray('.section').forEach((section) => {
    gsap.from(section, {
      opacity: 0,
      y: 30,
      duration: 0.8,
      ease: 'power2.out',
      scrollTrigger: {
        trigger: section,
        start: 'top 85%',
        toggleActions: 'play none none reverse'
      }
    });
  });

  // Stagger animation for cards
  gsap.utils.toArray('.treatment-card, .package-card, .profile-card').forEach((card, index) => {
    gsap.from(card, {
      opacity: 0,
      y: 40,
      duration: 0.6,
      delay: index * 0.1,
      ease: 'power2.out',
      scrollTrigger: {
        trigger: card,
        start: 'top 90%',
        toggleActions: 'play none none reverse'
      }
    });
  });

  // Fade in for hero content
  const heroContent = document.querySelector('.hero-content');
  if (heroContent) {
    gsap.from(heroContent.children, {
      opacity: 0,
      y: 30,
      duration: 0.8,
      stagger: 0.15,
      ease: 'power2.out',
      delay: 0.3
    });
  }

  // Scale animation for section dividers
  gsap.utils.toArray('.section-divider').forEach((divider) => {
    gsap.from(divider, {
      scaleX: 0,
      duration: 1,
      ease: 'power2.inOut',
      scrollTrigger: {
        trigger: divider,
        start: 'top 95%',
        toggleActions: 'play none none reverse'
      }
    });
  });
}

// ============================================
// Floating WhatsApp Button
// ============================================
function initWhatsAppButton() {
  const waBtn = document.getElementById('wa-float-btn');
  if (!waBtn) return;
  if (prefersReducedMotion.matches) return;
  // idle pulse handled via CSS
}

// ============================================
// Smooth Scroll for Anchor Links
// ============================================
function initSmoothScroll() {
  document.querySelectorAll('a[href^="#"]').forEach((anchor) => {
    anchor.addEventListener('click', (e) => {
      const href = anchor.getAttribute('href');
      if (href === '#') return;
      const target = document.querySelector(href);
      if (target) {
        e.preventDefault();
        target.scrollIntoView({
          behavior: prefersReducedMotion.matches ? 'auto' : 'smooth',
          block: 'start'
        });
      }
    });
  });
}

// ============================================
// Initialize All
// ============================================
document.addEventListener('DOMContentLoaded', () => {
  initNavbarScroll();
  initScrollAnimations();
  initWhatsAppButton();
  initSmoothScroll();

  let resizeTimer;
  window.addEventListener('resize', () => {
    clearTimeout(resizeTimer);
    resizeTimer = window.setTimeout(() => {
      ScrollTrigger.refresh();
    }, 250);
  });
});

window.addEventListener('beforeunload', () => {
  ScrollTrigger.getAll().forEach(trigger => trigger.kill());
});
