/* ==========================================================================
   SARVAMTA SPICES - LIGHT THEME & NAV MENU INTERACTIVITY
   ========================================================================== */

function initNavHighlight() {
  const sections = document.querySelectorAll('section[id]');
  const navItems = document.querySelectorAll('.nav-item[href^="#"]');

  window.addEventListener('scroll', () => {
    let scrollY = window.pageYOffset;

    sections.forEach(current => {
      const sectionHeight = current.offsetHeight;
      const sectionTop = current.offsetTop - 150;
      const sectionId = current.getAttribute('id');

      if (scrollY > sectionTop && scrollY <= sectionTop + sectionHeight) {
        navItems.forEach(item => {
          if (item.getAttribute('href') === `#${sectionId}`) {
            item.classList.add('active');
          } else {
            item.classList.remove('active');
          }
        });
      }
    });
  });
}

function openInquiryModal(productName) {
  const modal = document.getElementById('inquiry-modal');
  const select = document.getElementById('modal-select-product');

  if (productName && select) {
    select.value = productName;
  }

  if (modal) modal.classList.add('active');
}

function closeInquiryModal() {
  const modal = document.getElementById('inquiry-modal');
  if (modal) modal.classList.remove('active');
}

function initMobileMenu() {
  const toggleBtn = document.getElementById('mobile-menu-toggle');
  const navMenu = document.getElementById('top-nav-menu');

  if (!toggleBtn || !navMenu) return;

  function toggleMenu() {
    const isOpen = navMenu.classList.toggle('is-open');
    toggleBtn.classList.toggle('is-active', isOpen);
    toggleBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
  }

  function closeMenu() {
    navMenu.classList.remove('is-open');
    toggleBtn.classList.remove('is-active');
    toggleBtn.setAttribute('aria-expanded', 'false');
  }

  toggleBtn.addEventListener('click', (e) => {
    e.stopPropagation();
    toggleMenu();
  });

  // Close when clicking any link inside menu
  const menuLinks = navMenu.querySelectorAll('a');
  menuLinks.forEach(link => {
    link.addEventListener('click', () => {
      closeMenu();
    });
  });

  // Close when clicking outside
  document.addEventListener('click', (e) => {
    if (!navMenu.contains(e.target) && !toggleBtn.contains(e.target)) {
      closeMenu();
    }
  });

  // Close on Escape key
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeMenu();
  });

  // Close on resize to desktop view
  window.addEventListener('resize', () => {
    if (window.innerWidth > 991) {
      closeMenu();
    }
  });
}

document.addEventListener('DOMContentLoaded', () => {
  initNavHighlight();
  initMobileMenu();

  const modal = document.getElementById('inquiry-modal');
  if (modal) {
    modal.addEventListener('click', (e) => {
      if (e.target === modal) closeInquiryModal();
    });
  }
});

