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

function openInquiryModal(productName, pkgSize) {
  const modal = document.getElementById('inquiry-modal');
  const prodInput = document.getElementById('modal-select-product');
  const pkgSelect = document.getElementById('modal-select-pkg');

  if (prodInput) {
    prodInput.value = productName ? productName : 'General Spices Inquiry';
  }

  if (pkgSize && pkgSelect) {
    let pkgExists = false;
    for (let i = 0; i < pkgSelect.options.length; i++) {
      if (pkgSelect.options[i].value.toLowerCase() === pkgSize.toLowerCase()) {
        pkgSelect.selectedIndex = i;
        pkgExists = true;
        break;
      }
    }
    if (!pkgExists) {
      const newPkgOpt = document.createElement('option');
      newPkgOpt.value = pkgSize;
      newPkgOpt.textContent = pkgSize;
      pkgSelect.appendChild(newPkgOpt);
      pkgSelect.value = pkgSize;
    }
  }

  if (modal) modal.classList.add('active');
}

function closeInquiryModal() {
  const modal = document.getElementById('inquiry-modal');
  if (modal) modal.classList.remove('active');
}

function showToastNotification(title, message) {
  let toast = document.getElementById('sarvamta-toast-notice');
  if (!toast) {
    toast = document.createElement('div');
    toast.id = 'sarvamta-toast-notice';
    toast.style.cssText = `
      position: fixed;
      bottom: 24px;
      right: 24px;
      background: #260802;
      color: #ffffff;
      padding: 16px 20px;
      border-left: 4px solid #d48806;
      border-radius: 0px !important;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.35);
      z-index: 99999;
      display: flex;
      align-items: center;
      gap: 14px;
      font-family: 'Plus Jakarta Sans', sans-serif;
      min-width: 300px;
      max-width: 420px;
      transform: translateY(100px);
      opacity: 0;
      transition: all 0.35s ease;
    `;
    document.body.appendChild(toast);
  }

  toast.innerHTML = `
    <div style="width:36px; height:36px; border-radius:0px !important; background:linear-gradient(135deg, #d48806, #ff9800); color:#fff; display:flex; align-items:center; justify-content:center; font-size:1.1rem; flex-shrink:0;">
      <i class="fa-solid fa-check"></i>
    </div>
    <div style="flex:1;">
      <div style="font-weight:800; font-size:0.95rem; color:#ffffff; margin-bottom:2px;">${title}</div>
      <div style="font-size:0.82rem; color:#dfc8c0;">${message}</div>
    </div>
    <button onclick="dismissToastNotification()" style="background:none; border:none; color:#dfc8c0; font-size:1.2rem; cursor:pointer; padding:0 4px; border-radius:0px !important;">&times;</button>
  `;

  setTimeout(() => {
    toast.style.transform = 'translateY(0)';
    toast.style.opacity = '1';
  }, 10);

  if (window.toastTimer) clearTimeout(window.toastTimer);
  window.toastTimer = setTimeout(() => {
    dismissToastNotification();
  }, 4500);
}

function dismissToastNotification() {
  const toast = document.getElementById('sarvamta-toast-notice');
  if (toast) {
    toast.style.transform = 'translateY(100px)';
    toast.style.opacity = '0';
  }
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

  const menuLinks = navMenu.querySelectorAll('a');
  menuLinks.forEach(link => {
    link.addEventListener('click', () => {
      closeMenu();
    });
  });

  document.addEventListener('click', (e) => {
    if (!navMenu.contains(e.target) && !toggleBtn.contains(e.target)) {
      closeMenu();
    }
  });

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeMenu();
  });

  window.addEventListener('resize', () => {
    if (window.innerWidth > 991) {
      closeMenu();
    }
  });
}

function initHeroVideoSlider() {
  const slides = document.querySelectorAll('.hero-slide');
  const dots = document.querySelectorAll('.hero-dot');
  const prevBtn = document.getElementById('hero-prev-btn');
  const nextBtn = document.getElementById('hero-next-btn');

  if (!slides.length) return;

  let currentIndex = 0;
  let timer = null;
  const slideDuration = 6000;

  function showSlide(index) {
    if (index < 0) index = slides.length - 1;
    if (index >= slides.length) index = 0;

    slides.forEach((slide, i) => {
      if (i === index) {
        slide.classList.add('active');
        const video = slide.querySelector('video');
        if (video) {
          video.currentTime = 0;
          video.play().catch(() => {});
        }
      } else {
        slide.classList.remove('active');
      }
    });

    dots.forEach((dot, i) => {
      if (i === index) {
        dot.classList.add('active');
      } else {
        dot.classList.remove('active');
      }
    });

    currentIndex = index;
  }

  function startAutoPlay() {
    stopAutoPlay();
    timer = setInterval(() => {
      showSlide(currentIndex + 1);
    }, slideDuration);
  }

  function stopAutoPlay() {
    if (timer) clearInterval(timer);
  }

  if (prevBtn) {
    prevBtn.addEventListener('click', () => {
      showSlide(currentIndex - 1);
      startAutoPlay();
    });
  }

  if (nextBtn) {
    nextBtn.addEventListener('click', () => {
      showSlide(currentIndex + 1);
      startAutoPlay();
    });
  }

  dots.forEach((dot, i) => {
    dot.addEventListener('click', () => {
      showSlide(i);
      startAutoPlay();
    });
  });

  showSlide(0);
  startAutoPlay();
}

function initFormSubmissions() {
  const forms = document.querySelectorAll('form[action*="formspree.io"]');

  forms.forEach(form => {
    form.addEventListener('submit', async function(e) {
      e.preventDefault();

      const submitBtn = form.querySelector('button[type="submit"]');
      const originalBtnText = submitBtn ? submitBtn.innerHTML : '';

      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = 'Sending... <i class="fa-solid fa-spinner fa-spin"></i>';
      }

      const formData = new FormData(form);

      // Immediately close inquiry modal popup
      if (typeof closeInquiryModal === 'function') {
        closeInquiryModal();
      }

      // 1. Save to MySQL Backend API
      try {
        await fetch('api/save_inquiry.php', {
          method: 'POST',
          body: formData
        });
      } catch (err) {}

      // 2. Save to LocalStorage Backup
      try {
        const localInquiries = JSON.parse(localStorage.getItem('sarvamta_inquiries') || '[]');
        const newEntry = {
          id: Date.now(),
          name: formData.get('name') || '',
          email: formData.get('email') || '',
          phone: formData.get('phone') || '',
          product_name: formData.get('product_name') || formData.get('product') || 'General Inquiry',
          packaging_size: formData.get('packaging_size') || '200g',
          quantity: parseInt(formData.get('quantity')) || 1,
          status: 'Pending',
          created_at: new Date().toISOString().slice(0, 19).replace('T', ' ')
        };
        localInquiries.unshift(newEntry);
        localStorage.setItem('sarvamta_inquiries', JSON.stringify(localInquiries));
      } catch (err) {}

      // 3. Submit to Formspree
      try {
        await fetch(form.action, {
          method: 'POST',
          body: formData,
          headers: {
            'Accept': 'application/json'
          }
        });
      } catch (err) {}

      form.reset();

      if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalBtnText;
      }

      // 4. Show Sleek Toast Notification at Bottom-Right Corner
      showToastNotification(
        'Inquiry Sent Successfully!',
        'Thank you! SARVAMTA Food & Spices has received your inquiry. We will contact you soon.'
      );
    });
  });
}

/* ==========================================================================
   HOMEPAGE FAVORITE PRODUCTS (DYNAMIC FETCH FROM MYSQL DB)
   ========================================================================== */
let homeFavProducts = [];
let homeFavSelectedPackages = {};

async function initHomeFavorites() {
  const section = document.getElementById('favorite-products');
  const grid = document.getElementById('home-favorites-grid');
  if (!section || !grid) return;

  try {
    const res = await fetch('api/get_products.php?favorite=1');
    const json = await res.json();
    const prods = (json.status === 'success' && Array.isArray(json.data)) ? json.data : [];

    // IF NO PRODUCTS ARE MARKED AS FAVORITE, DO NOT SHOW THIS SECTION AT ALL
    if (prods.length === 0) {
      section.style.display = 'none';
      return;
    }

    // Favorite products exist -> Show the section
    section.style.display = 'block';
    homeFavProducts = prods;

    let html = '';
    prods.forEach(product => {
      let defaultPkg = (product.packages && product.packages.find(p => p.is_default)) || (product.packages && product.packages[0]) || { size: '200g', price: 180 };
      homeFavSelectedPackages[product.id] = defaultPkg;

      let pillsHtml = '';
      if (product.packages && product.packages.length > 0) {
        product.packages.forEach(pkg => {
          const isActive = (pkg.size === defaultPkg.size);
          pillsHtml += `
            <button type="button" 
                    class="pkg-pill ${isActive ? 'active' : ''}" 
                    style="border: 1px solid ${isActive ? '#d48806' : '#cbd5e1'}; background: ${isActive ? '#d48806' : '#ffffff'}; color: ${isActive ? '#ffffff' : '#334155'}; font-size: 0.75rem; font-weight: 700; padding: 4px 8px; cursor: pointer; border-radius: 0px !important;"
                    onclick="selectHomeFavPackage(${product.id}, '${pkg.size}', ${pkg.price}, event)">
              ${pkg.size}
            </button>
          `;
        });
      }

      html += `
        <div class="product-card-item" id="home-fav-card-${product.id}" style="border-radius: 0px !important; cursor: pointer; position: relative; background: #ffffff; border: 1px solid #ececec; padding: 16px; display: flex; flex-direction: column; justify-content: space-between; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.02);" onclick="navigateToDetail('${encodeURIComponent(product.name)}', event)">
          <div class="card-img-wrapper" style="position: relative; width: 100%; height: 230px; background-color: #ffffff; border-radius: 0px !important; overflow: hidden; display: flex; align-items: center; justify-content: center; padding: 10px; margin-bottom: 14px;">
            <img src="${product.image}" alt="${product.name}" style="max-width: 100%; max-height: 100%; object-fit: contain; border-radius: 0px !important;" onerror="this.src='images/logo.png'">
          </div>
          
          <div class="card-details" style="display: flex; flex-direction: column; gap: 8px; flex-grow: 1;">
            <div class="card-name-btn-row" style="display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-top: 4px; margin-bottom: 6px;">
              <h3 class="card-title" style="margin: 0; font-family: 'Plus Jakarta Sans', sans-serif; font-size: 1.02rem; font-weight: 700; color: #111827; flex: 1; line-height: 1.3;">${product.name}</h3>
              <button type="button" class="btn-card-add-cart" style="background: linear-gradient(135deg, #d48806, #ff9800); color: #ffffff; border: none; border-radius: 0px !important; font-family: 'Plus Jakarta Sans', sans-serif; font-size: 0.78rem; font-weight: 700; padding: 6px 12px; cursor: pointer; display: inline-flex; align-items: center; gap: 5px; white-space: nowrap; flex-shrink: 0; box-shadow: 0 2px 8px rgba(212, 136, 6, 0.25);" title="Add to Cart" onclick="homeFavAddCart(${product.id}, event)">
                <i class="fa-solid fa-cart-shopping"></i> Add to Cart
              </button>
            </div>

            <div class="card-price-row" style="font-size: 0.95rem; font-weight: 700; color: #1e293b;">
              From <span class="card-price-val" id="home-fav-price-${product.id}" style="color: #d48806; font-size: 1.15rem; font-weight: 800;">₹ ${defaultPkg.price}</span>
            </div>

            <div class="card-packaging-pills" id="home-fav-pills-${product.id}" style="display: flex; flex-wrap: wrap; gap: 6px; margin-top: 4px;">
              ${pillsHtml}
            </div>
          </div>
        </div>
      `;
    });

    grid.innerHTML = html;
  } catch (err) {
    if (section) section.style.display = 'none';
  }
}

function selectHomeFavPackage(productId, size, price, event) {
  if (event) event.stopPropagation();
  const product = homeFavProducts.find(p => p.id === productId);
  if (product && product.packages) {
    const pkgObj = product.packages.find(p => p.size === size);
    if (pkgObj) homeFavSelectedPackages[productId] = pkgObj;
  }

  const priceValEl = document.getElementById(`home-fav-price-${productId}`);
  if (priceValEl) {
    priceValEl.textContent = `₹ ${price}`;
  }

  const pillsContainer = document.getElementById(`home-fav-pills-${productId}`);
  if (pillsContainer) {
    const pills = pillsContainer.querySelectorAll('.pkg-pill');
    pills.forEach(pill => {
      if (pill.textContent.trim() === size) {
        pill.classList.add('active');
        pill.style.background = '#d48806';
        pill.style.borderColor = '#d48806';
        pill.style.color = '#ffffff';
      } else {
        pill.classList.remove('active');
        pill.style.background = '#ffffff';
        pill.style.borderColor = '#cbd5e1';
        pill.style.color = '#334155';
      }
    });
  }
}

function homeFavAddCart(productId, event) {
  if (event) event.stopPropagation();
  const product = homeFavProducts.find(p => p.id === productId);
  if (!product) return;

  const pkg = homeFavSelectedPackages[productId] || (product.packages ? product.packages[0] : null);
  const pkgSize = pkg ? pkg.size : '200g';
  openInquiryModal(product.name, pkgSize);
}

function navigateToDetail(productName, event) {
  if (event) {
    if (event.target.closest('button') || event.target.closest('.pkg-pill')) {
      return;
    }
  }
  let safeParam = productName;
  try {
    safeParam = encodeURIComponent(decodeURIComponent(productName));
  } catch(e) {
    safeParam = encodeURIComponent(productName);
  }
  window.location.href = `product-detail.html?name=${safeParam}`;
}

document.addEventListener('DOMContentLoaded', () => {
  initNavHighlight();
  initMobileMenu();
  initHeroVideoSlider();
  initFormSubmissions();
  initHomeFavorites();

  const modal = document.getElementById('inquiry-modal');
  if (modal) {
    modal.addEventListener('click', (e) => {
      if (e.target === modal) closeInquiryModal();
    });
  }
});
