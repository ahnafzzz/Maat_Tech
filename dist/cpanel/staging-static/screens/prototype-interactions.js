(function () {
  function onReady(fn) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', fn);
      return;
    }
    fn();
  }

  function ensureToastStyles() {
    if (document.getElementById('proto-toast-style')) {
      return;
    }

    var style = document.createElement('style');
    style.id = 'proto-toast-style';
    style.textContent =
      '.proto-toast-wrap{position:fixed;right:16px;bottom:72px;z-index:95;display:grid;gap:8px;max-width:320px}' +
      '.proto-toast{border:1px solid rgba(51,65,85,.55);background:rgba(15,23,42,.9);color:#e2e8f0;padding:10px 12px;border-radius:10px;font:500 12px/1.35 Inter,sans-serif;box-shadow:0 12px 30px rgba(2,6,23,.35);opacity:0;transform:translateY(6px);transition:opacity .18s ease,transform .18s ease}' +
      '.proto-toast.show{opacity:1;transform:translateY(0)}' +
      '.proto-toast.success{border-color:rgba(16,185,129,.65)}' +
      '.proto-toast.warn{border-color:rgba(245,158,11,.65)}';
    document.head.appendChild(style);
  }

  function toast(message, tone) {
    ensureToastStyles();
    var wrap = document.getElementById('proto-toast-wrap');
    if (!wrap) {
      wrap = document.createElement('div');
      wrap.id = 'proto-toast-wrap';
      wrap.className = 'proto-toast-wrap';
      document.body.appendChild(wrap);
    }

    var item = document.createElement('div');
    item.className = 'proto-toast' + (tone ? ' ' + tone : '');
    item.textContent = message;
    wrap.appendChild(item);

    requestAnimationFrame(function () {
      item.classList.add('show');
    });

    window.setTimeout(function () {
      item.classList.remove('show');
      window.setTimeout(function () {
        item.remove();
      }, 220);
    }, 2100);
  }

  function bindClick(el, fn) {
    if (!el || el.dataset.wiredClick === 'true') {
      return;
    }

    el.dataset.wiredClick = 'true';
    el.addEventListener('click', fn);
  }

  function scrollToElement(el) {
    if (!el) {
      return;
    }
    el.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }

  function parseCounter(el) {
    if (!el) {
      return 0;
    }

    var match = (el.textContent || '').match(/\d+/);
    return match ? Number(match[0]) : 0;
  }

  function setCounter(el, value) {
    if (!el) {
      return;
    }
    el.textContent = String(Math.max(0, value));
  }

  function wireStorefront() {
    var wishlistLink = document.getElementById('sf-wishlist-link');
    var cartLink = document.getElementById('sf-cart-link');
    var loginLink = document.getElementById('sf-login-link');
    var searchInput = document.getElementById('sf-search-input');
    var browseButton = document.getElementById('sf-browse-btn');
    var schematicsButton = document.getElementById('sf-schematics-btn');
    var viewAllLink = document.getElementById('sf-view-all-link');
    var featuredSection = document.getElementById('sf-featured');
    var categorySection = document.getElementById('sf-categories');
    var heroAddButton = document.getElementById('sf-hero-add-cart');
    var panelLoginLink = document.getElementById('sf-panel-login-link');

    var navWishlistCount = wishlistLink ? wishlistLink.querySelector('span') : null;
    var navCartCount = cartLink ? cartLink.querySelector('span') : null;

    bindClick(wishlistLink, function (event) {
      event.preventDefault();
      toast('Wishlist panel removed in compact build.', 'warn');
    });

    bindClick(cartLink, function (event) {
      event.preventDefault();
      toast('Cart preview removed in compact build.', 'warn');
    });

    bindClick(loginLink, function (event) {
      event.preventDefault();
      window.location.href = 'admin-login.html';
    });

    if (searchInput) {
      searchInput.addEventListener('keydown', function (event) {
        if (event.key !== 'Enter') {
          return;
        }
        event.preventDefault();
        var query = searchInput.value.trim();
        toast(query ? 'Searching prototype catalog for "' + query + '".' : 'Type a component name to search.', query ? 'success' : 'warn');
      });
    }

    bindClick(browseButton, function () {
      scrollToElement(featuredSection || categorySection);
    });

    bindClick(schematicsButton, function () {
      window.location.href = 'architecture.md';
    });

    bindClick(viewAllLink, function (event) {
      event.preventDefault();
      scrollToElement(featuredSection);
    });

    bindClick(heroAddButton, function () {
      setCounter(navCartCount, parseCounter(navCartCount) + 1);
      toast('Added Series-X Articulated Lamp to cart.', 'success');
    });

    Array.prototype.forEach.call(document.querySelectorAll('.sf-heart-btn'), function (button) {
      bindClick(button, function () {
        var isActive = button.classList.toggle('text-tech-400');
        var nextValue = parseCounter(navWishlistCount) + (isActive ? 1 : -1);
        setCounter(navWishlistCount, nextValue);
        toast(isActive ? 'Added to wishlist.' : 'Removed from wishlist.');
      });
    });

    Array.prototype.forEach.call(document.querySelectorAll('.sf-add-cart-btn'), function (button) {
      bindClick(button, function () {
        setCounter(navCartCount, parseCounter(navCartCount) + 1);
        toast('Product added to cart.', 'success');
      });
    });

    Array.prototype.forEach.call(document.querySelectorAll('[data-category]'), function (link) {
      bindClick(link, function (event) {
        event.preventDefault();
        scrollToElement(featuredSection);
        toast('Showing ' + link.getAttribute('data-category') + ' products.');
      });
    });

    bindClick(panelLoginLink, function (event) {
      event.preventDefault();
      window.location.href = 'admin-login.html';
    });

    Array.prototype.forEach.call(document.querySelectorAll('a[href="#"]'), function (link) {
      if (link.dataset.wiredClick === 'true') {
        return;
      }

      bindClick(link, function (event) {
        event.preventDefault();
        var label = (link.textContent || '').trim();
        if (label === 'User Dashboard') {
          window.location.href = 'admin-login.html';
          return;
        }
        if (label === 'Order History') {
          toast('Order history module is not included in compact build.', 'warn');
          return;
        }
        if (label === 'Wishlist') {
          toast('Wishlist module is not included in compact build.', 'warn');
          return;
        }
        if (label === 'Support Ticket') {
          window.location.href = 'mailto:support@maattech.com?subject=Support%20Request';
          return;
        }
        toast('Prototype action: ' + (label || 'Navigation link'));
      });
    });

    Array.prototype.forEach.call(document.querySelectorAll('button'), function (button) {
      if (button.dataset.wiredClick === 'true' || button.hasAttribute('onclick')) {
        return;
      }
      bindClick(button, function () {
        toast('Prototype action executed.');
      });
    });
  }

  function wireLeadAdmin() {
    var logoutButton = document.getElementById('admin-logout-button');
    if (!logoutButton) {
      var powerIcon = document.querySelector('.lucide-power');
      logoutButton = powerIcon ? powerIcon.closest('button') : null;
    }

    var addAdminLink = document.getElementById('lead-add-admin-link');
    var queueBadge = document.getElementById('lead-queue-badge');
    var currentRequestId = null;

    bindClick(logoutButton, function () {
      var shouldLogout = window.confirm('Log out from the admin panel?');
      if (!shouldLogout) {
        return;
      }
      window.location.href = 'admin-login.html';
    });

    bindClick(addAdminLink, function (event) {
      event.preventDefault();
      currentRequestId = null;
      if (typeof window.openApprove === 'function') {
        window.openApprove(0);
      }
      toast('Direct admin creation flow opened.');
    });

    Array.prototype.forEach.call(document.querySelectorAll('.lead-edit-btn'), function (button) {
      bindClick(button, function () {
        var row = button.closest('tr');
        var adminCode = row ? (row.querySelector('code') || {}).textContent : '';
        toast('Editing profile for ' + (adminCode ? adminCode.trim() : 'selected admin') + '.');
      });
    });

    function updateQueueCount() {
      if (!queueBadge) {
        return;
      }
      var count = Array.prototype.filter.call(document.querySelectorAll('tbody tr'), function (candidate) {
        var cell = candidate.querySelector('td');
        if (!cell) {
          return false;
        }
        return /^REQ-\d+/.test(cell.textContent.trim());
      }).length;
      if (!Number.isFinite(count)) {
        return;
      }
      queueBadge.textContent = String(count) + ' QUEUED';
    }

    function removeRequestRow(id) {
      if (!id) {
        return;
      }
      var requestCode = 'REQ-00' + id;
      var row = Array.prototype.find.call(document.querySelectorAll('tbody tr'), function (candidate) {
        var cell = candidate.querySelector('td');
        return cell && cell.textContent.trim() === requestCode;
      });
      if (row) {
        row.remove();
      }
      updateQueueCount();
    }

    if (typeof window.openApprove === 'function') {
      var originalOpenApprove = window.openApprove;
      window.openApprove = function (id) {
        currentRequestId = id;
        originalOpenApprove(id);
      };
    }

    if (typeof window.openReject === 'function') {
      var originalOpenReject = window.openReject;
      window.openReject = function (id) {
        currentRequestId = id;
        originalOpenReject(id);
      };
    }

    var confirmCreateButton = document.getElementById('lead-confirm-create-btn');
    bindClick(confirmCreateButton, function () {
      removeRequestRow(currentRequestId);
      if (typeof window.closeApprove === 'function') {
        window.closeApprove();
      }
      toast('Invitation approved and admin account queued.', 'success');
    });

    var executeDenialButton = document.getElementById('lead-execute-denial-btn');
    bindClick(executeDenialButton, function () {
      removeRequestRow(currentRequestId);
      if (typeof window.closeReject === 'function') {
        window.closeReject();
      }
      toast('Invitation request denied.', 'warn');
    });

    Array.prototype.forEach.call(document.querySelectorAll('a[href="#"]'), function (link) {
      if (link.dataset.wiredClick === 'true') {
        return;
      }
      bindClick(link, function (event) {
        event.preventDefault();
        toast('Prototype navigation: ' + ((link.textContent || '').trim() || 'link'));
      });
    });

    Array.prototype.forEach.call(document.querySelectorAll('button'), function (button) {
      if (button.dataset.wiredClick === 'true' || button.hasAttribute('onclick')) {
        return;
      }
      bindClick(button, function () {
        toast('Admin action completed in prototype.');
      });
    });
  }

  onReady(function () {
    var page = (window.location.pathname.split('/').pop() || 'index.html').toLowerCase();

    if (page === 'mech-lamp-storefront.html') {
      wireStorefront();
      return;
    }

    if (page === 'lead-admin-panel.html') {
      wireLeadAdmin();
    }
  });
})();
