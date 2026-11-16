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

  // ==========================================================================
  // Dynamic Company Address Loader (CMS Driven)
  // ==========================================================================
  const escapeHtml = (str) => {
    if (!str) return '';
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  };

  const loadCompanyAddresses = async () => {
    const grid = document.getElementById('locations-grid');
    if (!grid) return;

    try {
      // Attempt to load live addresses from public API
      const response = await fetch('/api/public/addresses', {
        headers: { 'Accept': 'application/json' },
        cache: 'default'
      });

      if (!response.ok) return; // Keep server-rendered fallback on error

      const result = await response.json();
      if (!result || !result.success || !Array.isArray(result.data) || result.data.length === 0) {
        return;
      }

      // Render cards from live CMS data
      const cardsHtml = result.data.map(addr => {
        const isPrimary = Boolean(addr.is_primary);
        const name = escapeHtml(addr.location_name || 'Shubh Labh Office');
        const formattedAddress = escapeHtml(addr.formatted_address || [
          addr.address_line1,
          addr.address_line2,
          addr.landmark,
          addr.city,
          addr.state && addr.pincode ? `${addr.state} ${addr.pincode}` : addr.state,
          addr.country || 'India'
        ].filter(Boolean).join(', '));

        const phoneDigits = (addr.office_phone || '').replace(/[^\d+]/g, '');
        const waDigits = (addr.whatsapp_number || '').replace(/[^\d]/g, '');
        const phone = escapeHtml(addr.office_phone);
        const wa = escapeHtml(addr.whatsapp_number);
        const email = escapeHtml(addr.email);
        const mapsUrl = escapeHtml(addr.google_maps_url || `https://maps.google.com/?q=${encodeURIComponent(addr.location_name + ' ' + (addr.city || ''))}`);

        const primaryBadge = isPrimary ? '<span class="location-badge primary">⭐ Primary Office</span>' : '';
        
        let channelsHtml = '';
        if (phone) {
          channelsHtml += `
            <div class="location-channel-item">
              <svg class="location-channel-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
              <span>Phone: <a href="tel:${phoneDigits}" class="location-channel-link">${phone}</a></span>
            </div>
          `;
        }

        if (wa) {
          channelsHtml += `
            <div class="location-channel-item">
              <svg class="location-channel-icon" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.472 14.382c-.301-.15-1.78-.879-2.056-.98-.276-.1-.476-.15-.676.15-.2.301-.776.98-.952 1.18-.176.2-.352.226-.653.076-.301-.15-1.272-.469-2.423-1.496-.896-.799-1.501-1.786-1.677-2.087-.176-.301-.019-.464.132-.614.135-.135.301-.352.451-.527.151-.176.201-.301.301-.502.1-.2.05-.376-.025-.526-.075-.15-.676-1.631-.927-2.234-.244-.588-.493-.508-.676-.517-.175-.009-.376-.01-.577-.01-.201 0-.527.075-.803.376-.276.301-1.054 1.03-1.054 2.512 0 1.482 1.079 2.912 1.23 3.113.15.201 2.124 3.243 5.145 4.548.719.311 1.28.497 1.718.636.722.23 1.378.197 1.898.12.579-.087 1.78-.727 2.031-1.43.251-.702.251-1.303.176-1.43-.075-.127-.276-.202-.577-.352zm-5.462 7.618C5.467 22 0 16.533 0 9.809 0 4.388 4.388 0 9.809 0c5.422 0 9.809 4.388 9.809 9.809 0 6.724-5.467 12.191-12.191 12.191z"/></svg>
              <span>WhatsApp: <a href="https://wa.me/${waDigits}?text=Hello%20Shubh%20Labh%2C%20I%20would%20like%20to%20know%20more." target="_blank" rel="noopener noreferrer" class="location-channel-link">${wa}</a></span>
            </div>
          `;
        }

        if (email) {
          channelsHtml += `
            <div class="location-channel-item">
              <svg class="location-channel-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
              <span>Email: <a href="mailto:${email}" class="location-channel-link">${email}</a></span>
            </div>
          `;
        }

        let directionsHtml = '';
        if (mapsUrl) {
          directionsHtml = `
            <div class="location-actions">
              <a href="${mapsUrl}" target="_blank" rel="noopener noreferrer" class="location-directions-btn" aria-label="Get directions to ${name} on Google Maps (opens in new tab)">
                <span>Get Directions</span>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="7" y1="17" x2="17" y2="7"></line><polyline points="7 7 17 7 17 17"></polyline></svg>
              </a>
            </div>
          `;
        }

        return `
          <article class="location-card${isPrimary ? ' is-primary' : ''}">
            <div class="location-card-top">
              <div class="location-card-header">
                <h4 class="location-name">${name}</h4>
                ${primaryBadge}
              </div>
              <div class="location-postal">
                <svg class="location-postal-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                <span class="location-postal-text">${formattedAddress}</span>
              </div>
              <div class="location-channels">
                ${channelsHtml}
              </div>
            </div>
            ${directionsHtml}
          </article>
        `;
      }).join('');

      grid.innerHTML = cardsHtml;
    } catch {
      // Silently preserve offline/static fallback cards
    }
  };

  loadCompanyAddresses();
})();

