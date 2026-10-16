(() => {
  const header = document.querySelector('.site-header');
  const toggle = document.querySelector('.menu-toggle');
  const nav = document.querySelector('.main-nav');
  const year = document.getElementById('year');
  if (year) year.textContent = new Date().getFullYear();

  const setHeader = () => header?.classList.toggle('scrolled', window.scrollY > 24);
  setHeader();
  window.addEventListener('scroll', setHeader, { passive: true });

  toggle?.addEventListener('click', () => {
    const isOpen = toggle.getAttribute('aria-expanded') === 'true';
    toggle.setAttribute('aria-expanded', String(!isOpen));
    toggle.setAttribute('aria-label', isOpen ? 'Open navigation' : 'Close navigation');
    nav?.classList.toggle('open', !isOpen);
  });

  nav?.querySelectorAll('a').forEach(link => link.addEventListener('click', () => {
    nav.classList.remove('open');
    toggle?.setAttribute('aria-expanded', 'false');
    toggle?.setAttribute('aria-label', 'Open navigation');
  }));

  const revealItems = document.querySelectorAll('.reveal');
  if ('IntersectionObserver' in window) {
    const observer = new IntersectionObserver((entries, obs) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.classList.add('is-visible');
          obs.unobserve(entry.target);
        }
      });
    }, { threshold: 0.12, rootMargin: '0px 0px -35px 0px' });
    revealItems.forEach(item => observer.observe(item));
  } else {
    revealItems.forEach(item => item.classList.add('is-visible'));
  }

  const sections = [...document.querySelectorAll('main section[id]')];
  const navLinks = [...document.querySelectorAll('.main-nav a')];
  if ('IntersectionObserver' in window) {
    const activeObserver = new IntersectionObserver(entries => {
      entries.forEach(entry => {
        if (!entry.isIntersecting) return;
        navLinks.forEach(link => link.classList.toggle('active', link.getAttribute('href') === `#${entry.target.id}`));
      });
    }, { rootMargin: '-30% 0px -60% 0px' });
    sections.forEach(section => activeObserver.observe(section));
  }

  // ==========================================================================
  // Facebook Gallery Controller
  // ==========================================================================
  const fbBtn = document.getElementById('fb-gallery-btn');
  const fbModal = document.getElementById('fb-gallery-modal');
  const fbBackdrop = document.getElementById('fb-modal-backdrop');
  const fbCloseBtn = document.getElementById('fb-modal-close-btn');
  const fbCloseFooterBtn = document.getElementById('fb-modal-close-footer-btn');
  const fbIframe = document.getElementById('fb-page-iframe');
  const fbLoading = document.getElementById('fb-loading-state');
  const fbEmbedContainer = document.getElementById('fb-embed-container');
  const fbFallback = document.getElementById('fb-fallback-state');
  const fbTabEmbed = document.getElementById('fb-tab-embed');
  const fbTabInfo = document.getElementById('fb-tab-info');
  const fbRetryBtn = document.getElementById('fb-retry-embed-btn');

  let fbTimeoutId = null;
  let isEmbedLoaded = false;

  const showFallback = () => {
    if (fbLoading) fbLoading.style.display = 'none';
    if (fbEmbedContainer) fbEmbedContainer.style.display = 'none';
    if (fbFallback) fbFallback.style.display = 'block';
    if (fbTabEmbed) {
      fbTabEmbed.classList.remove('active');
      fbTabEmbed.setAttribute('aria-selected', 'false');
    }
    if (fbTabInfo) {
      fbTabInfo.classList.add('active');
      fbTabInfo.setAttribute('aria-selected', 'true');
    }
  };

  const showEmbed = () => {
    if (fbFallback) fbFallback.style.display = 'none';
    if (isEmbedLoaded) {
      if (fbLoading) fbLoading.style.display = 'none';
      if (fbEmbedContainer) fbEmbedContainer.style.display = 'flex';
    } else {
      if (fbLoading) fbLoading.style.display = 'flex';
      if (fbEmbedContainer) fbEmbedContainer.style.display = 'none';
    }
    if (fbTabInfo) {
      fbTabInfo.classList.remove('active');
      fbTabInfo.setAttribute('aria-selected', 'false');
    }
    if (fbTabEmbed) {
      fbTabEmbed.classList.add('active');
      fbTabEmbed.setAttribute('aria-selected', 'true');
    }
  };

  const loadFacebookIframe = () => {
    if (!fbIframe) return;
    const targetSrc = fbIframe.getAttribute('data-src');
    if (!targetSrc) return;

    if (fbIframe.getAttribute('src') !== targetSrc) {
      isEmbedLoaded = false;
      if (fbLoading) fbLoading.style.display = 'flex';
      if (fbEmbedContainer) fbEmbedContainer.style.display = 'none';
      if (fbFallback) fbFallback.style.display = 'none';

      fbIframe.onload = () => {
        isEmbedLoaded = true;
        if (fbTimeoutId) clearTimeout(fbTimeoutId);
        if (fbLoading) fbLoading.style.display = 'none';
        if (fbEmbedContainer && fbTabEmbed?.classList.contains('active')) {
          fbEmbedContainer.style.display = 'flex';
        }
      };

      fbIframe.onerror = () => {
        if (fbTimeoutId) clearTimeout(fbTimeoutId);
        showFallback();
      };

      // Guard timeout: if frame blocked by adblock or privacy protections, switch to helpful fallback after 5.5s
      fbTimeoutId = setTimeout(() => {
        if (!isEmbedLoaded) {
          showFallback();
        }
      }, 5500);

      fbIframe.setAttribute('src', targetSrc);
    }
  };

  const openGalleryModal = () => {
    if (!fbModal) return;
    fbModal.classList.add('is-open');
    fbModal.setAttribute('aria-hidden', 'false');
    fbBtn?.setAttribute('aria-expanded', 'true');
    document.body.classList.add('fb-modal-open');

    // Lazy load the iframe only upon user click to avoid initial page layout shift/weight
    loadFacebookIframe();

    // Focus close button for keyboard accessibility
    setTimeout(() => {
      fbCloseBtn?.focus();
    }, 60);
  };

  const closeGalleryModal = () => {
    if (!fbModal) return;
    fbModal.classList.remove('is-open');
    fbModal.setAttribute('aria-hidden', 'true');
    fbBtn?.setAttribute('aria-expanded', 'false');
    document.body.classList.remove('fb-modal-open');

    // Return focus to floating trigger button
    fbBtn?.focus();
  };

  fbBtn?.addEventListener('click', (e) => {
    e.preventDefault();
    openGalleryModal();
  });

  fbCloseBtn?.addEventListener('click', closeGalleryModal);
  fbCloseFooterBtn?.addEventListener('click', closeGalleryModal);
  fbBackdrop?.addEventListener('click', closeGalleryModal);

  fbTabEmbed?.addEventListener('click', () => {
    showEmbed();
    if (!isEmbedLoaded && fbIframe && !fbIframe.getAttribute('src')) {
      loadFacebookIframe();
    }
  });

  fbTabInfo?.addEventListener('click', () => {
    showFallback();
  });

  fbRetryBtn?.addEventListener('click', () => {
    if (fbIframe) {
      fbIframe.removeAttribute('src');
      isEmbedLoaded = false;
      showEmbed();
      setTimeout(() => {
        loadFacebookIframe();
      }, 100);
    }
  });

  // Keyboard accessibility: Escape key to close, Tab focus trapping
  document.addEventListener('keydown', (e) => {
    if (!fbModal?.classList.contains('is-open')) return;

    if (e.key === 'Escape' || e.key === 'Esc') {
      e.preventDefault();
      closeGalleryModal();
      return;
    }

    if (e.key === 'Tab') {
      const focusable = fbModal.querySelectorAll('button:not([disabled]), [href], input:not([disabled]), [tabindex]:not([tabindex="-1"])');
      if (focusable.length === 0) return;
      const first = focusable[0];
      const last = focusable[focusable.length - 1];

      if (e.shiftKey) {
        if (document.activeElement === first) {
          e.preventDefault();
          last.focus();
        }
      } else {
        if (document.activeElement === last) {
          e.preventDefault();
          first.focus();
        }
      }
    }
  });
})();

