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
      '.proto-toast.warn{border-color:rgba(245,158,11,.65)}' +
      '.sf-pop{position:fixed;inset:0;z-index:90;background:rgba(2,6,23,.72);display:none;align-items:center;justify-content:center;padding:16px}' +
      '.sf-pop.show{display:flex}' +
      '.sf-pop-card{width:min(980px,96vw);max-height:92vh;overflow:auto;border:1px solid rgba(30,41,59,.85);background:rgba(10,10,15,.98);border-radius:12px;padding:18px}' +
      '.sf-mini-cart{position:absolute;right:0;top:36px;width:min(360px,92vw);border:1px solid rgba(30,41,59,.85);background:rgba(10,10,15,.98);border-radius:10px;padding:10px;display:none;z-index:80}' +
      '.sf-mini-cart.show{display:block}' +
      '.sf-suggest-item{width:100%;text-align:left;padding:8px 10px;border-radius:6px;font:500 12px/1.3 Inter,sans-serif;color:#cbd5e1;background:transparent;border:0}' +
      '.sf-suggest-item:hover{background:rgba(15,118,110,.2);color:#fff}' +
      '.sf-highlight{outline:1px solid rgba(20,184,166,.85);box-shadow:0 0 0 2px rgba(20,184,166,.25) inset}' +
      '.sf-tab-btn{padding:8px 10px;border:1px solid rgba(51,65,85,.9);border-radius:8px;background:transparent;color:#94a3b8;font:500 12px/1.3 Inter,sans-serif}' +
      '.sf-tab-btn.active{border-color:rgba(20,184,166,.85);color:#fff;background:rgba(15,118,110,.2)}' +
      '.sf-input{width:100%;border:1px solid rgba(51,65,85,.9);background:rgba(15,23,42,.6);border-radius:8px;padding:10px 12px;color:#e2e8f0;font:500 13px/1.3 Inter,sans-serif}' +
      '.sf-error{color:#fca5a5;font:500 12px/1.3 Inter,sans-serif;min-height:16px}' +
      '.sf-zoom-box{transition:transform .2s ease;transform-origin:center}' +
      '.sf-zoom-box:hover{transform:scale(1.08)}';
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

  function money(numberValue) {
    return '৳' + Number(numberValue || 0).toLocaleString('en-US');
  }

  function parseProductReviews(rawValue) {
    if (!rawValue) {
      return [];
    }
    return rawValue.split('|').map(function (chunk) {
      var parts = chunk.split('::');
      return {
        name: parts[0] || 'Verified buyer',
        comment: parts[1] || 'Verified purchase.',
        rating: parts[2] || '4.5',
      };
    });
  }

  function extractProducts() {
    return Array.prototype.map.call(document.querySelectorAll('.sf-product-card'), function (card, index) {
      return {
        id: card.getAttribute('data-sku') || 'SKU-' + index,
        index: index,
        name: card.getAttribute('data-name') || 'Product',
        sku: card.getAttribute('data-sku') || 'SKU-' + index,
        category: card.getAttribute('data-category') || 'General',
        brand: card.getAttribute('data-brand') || 'MAAT TECHNOLOGIE BD',
        price: Number(card.getAttribute('data-price') || 0),
        stock: Number(card.getAttribute('data-stock') || 0),
        rating: Number(card.getAttribute('data-rating') || 0),
        summary: card.getAttribute('data-summary') || '',
        specs: (card.getAttribute('data-specs') || '').split('|').filter(Boolean),
        reviews: parseProductReviews(card.getAttribute('data-reviews') || ''),
        gallery: (card.getAttribute('data-gallery') || '').split(',').filter(Boolean),
        card: card,
      };
    });
  }

  function ensureMiniCartElement(anchor) {
    var existing = document.getElementById('sf-mini-cart');
    if (existing) {
      return existing;
    }
    var panel = document.createElement('div');
    panel.id = 'sf-mini-cart';
    panel.className = 'sf-mini-cart';
    panel.innerHTML =
      '<div class="flex items-center justify-between px-1 pb-2 border-b border-slate-700">' +
      '<strong class="font-mono text-xs text-white">MINI_CART</strong>' +
      '<button type="button" id="sf-mini-close" class="text-slate-400 hover:text-white text-xs">Close</button>' +
      '</div>' +
      '<div id="sf-mini-items" class="py-2 grid gap-2"></div>' +
      '<div class="border-t border-slate-700 pt-2 mt-2">' +
      '<div class="flex justify-between text-sm text-slate-200"><span>Subtotal</span><span id="sf-mini-subtotal">৳0</span></div>' +
      '<button type="button" id="sf-mini-checkout" class="mt-2 w-full bg-teal-600 hover:bg-teal-500 text-white rounded-md py-2 text-xs font-mono">PROCEED_CHECKOUT</button>' +
      '</div>';
    var shell = anchor ? anchor.parentElement : null;
    if (shell) {
      shell.style.position = 'relative';
      shell.appendChild(panel);
    } else {
      document.body.appendChild(panel);
    }
    return panel;
  }

  function ensureProductModal() {
    var existing = document.getElementById('sf-product-modal');
    if (existing) {
      return existing;
    }
    var modal = document.createElement('div');
    modal.id = 'sf-product-modal';
    modal.className = 'sf-pop';
    modal.innerHTML =
      '<div class="sf-pop-card">' +
      '<div class="flex items-center justify-between pb-3 border-b border-slate-700">' +
      '<h3 id="sf-pm-title" class="font-mono text-sm text-white">PRODUCT</h3>' +
      '<button type="button" id="sf-pm-close" class="text-slate-400 hover:text-white">Close</button>' +
      '</div>' +
      '<div class="grid md:grid-cols-2 gap-4 mt-4">' +
      '<div>' +
      '<div id="sf-pm-main" class="sf-zoom-box rounded-lg border border-slate-700 bg-slate-900 h-52 flex items-center justify-center text-teal-300 font-mono text-sm"></div>' +
      '<div id="sf-pm-gallery" class="grid grid-cols-3 gap-2 mt-2"></div>' +
      '</div>' +
      '<div>' +
      '<div id="sf-pm-stock" class="text-xs font-mono text-amber-300 mb-1"></div>' +
      '<div id="sf-pm-price" class="text-xl font-bold text-white"></div>' +
      '<div id="sf-pm-breakdown" class="mt-2 text-sm text-slate-300"></div>' +
      '<div class="mt-3 flex gap-2">' +
      '<button type="button" class="sf-tab-btn active" data-tab="desc">Description</button>' +
      '<button type="button" class="sf-tab-btn" data-tab="specs">Specs</button>' +
      '<button type="button" class="sf-tab-btn" data-tab="reviews">Reviews</button>' +
      '</div>' +
      '<div id="sf-pm-tab-desc" class="mt-3 text-sm text-slate-300"></div>' +
      '<div id="sf-pm-tab-specs" class="mt-3 text-sm text-slate-300 hidden"></div>' +
      '<div id="sf-pm-tab-reviews" class="mt-3 text-sm text-slate-300 hidden"></div>' +
      '<div id="sf-pm-tier" class="mt-4 rounded-md border border-slate-700 bg-slate-900/50 p-3 text-xs text-slate-300"></div>' +
      '<button type="button" id="sf-pm-add" class="mt-4 w-full bg-teal-600 hover:bg-teal-500 text-white rounded-md py-2 text-xs font-mono">ADD_TO_CART</button>' +
      '</div>' +
      '</div>' +
      '</div>';
    document.body.appendChild(modal);
    return modal;
  }

  function ensureCheckoutModal() {
    var existing = document.getElementById('sf-checkout-modal');
    if (existing) {
      return existing;
    }
    var modal = document.createElement('div');
    modal.id = 'sf-checkout-modal';
    modal.className = 'sf-pop';
    modal.innerHTML =
      '<div class="sf-pop-card">' +
      '<div class="flex items-center justify-between pb-3 border-b border-slate-700">' +
      '<h3 class="font-mono text-sm text-white">CHECKOUT_FLOW</h3>' +
      '<button type="button" id="sf-checkout-close" class="text-slate-400 hover:text-white">Close</button>' +
      '</div>' +
      '<div class="mt-3 text-xs text-slate-400">Step 1/1 • Shipping & Payment Preview</div>' +
      '<div class="grid lg:grid-cols-[1fr,320px] gap-4 mt-4">' +
      '<form id="sf-checkout-form" class="grid gap-3">' +
      '<input id="sf-co-name" class="sf-input" placeholder="Full name">' +
      '<div id="sf-co-name-err" class="sf-error"></div>' +
      '<input id="sf-co-email" class="sf-input" placeholder="Email address">' +
      '<div id="sf-co-email-err" class="sf-error"></div>' +
      '<input id="sf-co-phone" class="sf-input" placeholder="Phone number">' +
      '<div id="sf-co-phone-err" class="sf-error"></div>' +
      '<input id="sf-co-address" class="sf-input" placeholder="Street address">' +
      '<div id="sf-co-address-err" class="sf-error"></div>' +
      '<input id="sf-co-district" class="sf-input" placeholder="District">' +
      '<div id="sf-co-district-err" class="sf-error"></div>' +
      '<button type="submit" class="mt-2 bg-teal-600 hover:bg-teal-500 text-white rounded-md py-2 text-xs font-mono">PLACE_ORDER</button>' +
      '</form>' +
      '<aside class="rounded-lg border border-slate-700 p-3 bg-slate-900/50">' +
      '<h4 class="font-mono text-xs text-white mb-2">ORDER_SUMMARY</h4>' +
      '<div id="sf-co-items" class="grid gap-1 text-xs text-slate-300"></div>' +
      '<div class="mt-3 flex gap-2">' +
      '<input id="sf-co-coupon" class="sf-input" placeholder="Coupon code" style="padding:8px 10px;font-size:12px">' +
      '<button type="button" id="sf-co-apply" class="px-3 rounded-md border border-slate-600 text-xs text-slate-200">Apply</button>' +
      '</div>' +
      '<div id="sf-co-coupon-msg" class="sf-error mt-1"></div>' +
      '<div class="mt-3 pt-3 border-t border-slate-700 grid gap-1 text-xs text-slate-300">' +
      '<div class="flex justify-between"><span>Subtotal</span><span id="sf-co-subtotal">৳0</span></div>' +
      '<div class="flex justify-between"><span>Shipping</span><span id="sf-co-shipping">৳120</span></div>' +
      '<div class="flex justify-between"><span>Tax</span><span id="sf-co-tax">৳0</span></div>' +
      '<div class="flex justify-between"><span>Discount</span><span id="sf-co-discount">৳0</span></div>' +
      '<div class="flex justify-between text-white font-semibold text-sm pt-1"><span>Total</span><span id="sf-co-total">৳0</span></div>' +
      '</div>' +
      '</aside>' +
      '</div>' +
      '</div>';
    document.body.appendChild(modal);
    return modal;
  }

  function wireStorefront() {
    var wishlistLink = document.getElementById('sf-wishlist-link');
    var cartLink = document.getElementById('sf-cart-link');
    var loginLink = document.getElementById('sf-login-link');
    var searchInput = document.getElementById('sf-search-input');
    var mobileSearchInput = document.getElementById('sf-search-input-mobile');
    var browseButton = document.getElementById('sf-browse-btn');
    var schematicsButton = document.getElementById('sf-schematics-btn');
    var viewAllLink = document.getElementById('sf-view-all-link');
    var featuredSection = document.getElementById('sf-featured');
    var categorySection = document.getElementById('sf-categories');
    var heroAddButton = document.getElementById('sf-hero-add-cart');
    var panelLoginLink = document.getElementById('sf-panel-login-link');

    var navWishlistCount = wishlistLink ? wishlistLink.querySelector('span') : null;
    var navCartCount = cartLink ? cartLink.querySelector('span') : null;
    var searchShell = document.getElementById('sf-search-shell');
    var suggestionBox = document.getElementById('sf-search-suggestions');
    var productCards = extractProducts();
    var sortSelect = document.getElementById('sf-sort-select');
    var resultCount = document.getElementById('sf-result-count');
    var priceFilter = document.getElementById('sf-filter-price');
    var priceFilterLabel = document.getElementById('sf-filter-price-label');
    var ratingFilter = document.getElementById('sf-filter-rating');
    var stockOnlyFilter = document.getElementById('sf-filter-stock');
    var brandFilters = document.querySelectorAll('.sf-filter-brand');
    var productGrid = document.getElementById('sf-product-grid');
    var emptyState = document.getElementById('sf-empty-state');

    var state = {
      wishlist: new Set(),
      cart: {},
      activeProductId: null,
      shippingFee: 120,
      discount: 0,
      searchQuery: '',
      categoryQuery: '',
    };

    function seedCounters() {
      if (!productCards.length) {
        return;
      }
      productCards.slice(0, 3).forEach(function (item) {
        state.wishlist.add(item.id);
      });
      productCards.slice(0, 2).forEach(function (item) {
        state.cart[item.id] = 1;
      });
      setCounter(navWishlistCount, state.wishlist.size);
      setCounter(navCartCount, getCartCount());
    }

    function getCartCount() {
      return Object.keys(state.cart).reduce(function (acc, key) {
        return acc + Number(state.cart[key] || 0);
      }, 0);
    }

    function getProductById(id) {
      return productCards.find(function (item) {
        return item.id === id;
      }) || null;
    }

    function cartLines() {
      return Object.keys(state.cart)
        .map(function (id) {
          var product = getProductById(id);
          if (!product) {
            return null;
          }
          return {
            id: id,
            name: product.name,
            price: product.price,
            qty: Number(state.cart[id] || 0),
          };
        })
        .filter(Boolean)
        .filter(function (line) {
          return line.qty > 0;
        });
    }

    function cartSubtotal() {
      return cartLines().reduce(function (acc, line) {
        return acc + line.qty * line.price;
      }, 0);
    }

    function cartTax(subtotal) {
      return Math.round(subtotal * 0.05);
    }

    function cartTotal() {
      var subtotal = cartSubtotal();
      var tax = cartTax(subtotal);
      return subtotal + state.shippingFee + tax - state.discount;
    }

    function addToCart(product, qty) {
      if (!product) {
        return;
      }
      var previous = Number(state.cart[product.id] || 0);
      state.cart[product.id] = previous + (qty || 1);
      setCounter(navCartCount, getCartCount());
      renderMiniCart();
      renderCheckoutSummary();
    }

    function changeCartQty(productId, delta) {
      if (!productId) {
        return;
      }
      var current = Number(state.cart[productId] || 0);
      var next = current + Number(delta || 0);
      if (next <= 0) {
        delete state.cart[productId];
      } else {
        state.cart[productId] = next;
      }
      setCounter(navCartCount, getCartCount());
      renderMiniCart();
      renderCheckoutSummary();
    }

    function toggleWishlist(product, button) {
      if (!product) {
        return;
      }
      if (state.wishlist.has(product.id)) {
        state.wishlist.delete(product.id);
        if (button) {
          button.classList.remove('text-tech-400');
        }
        toast('Removed from wishlist.');
      } else {
        state.wishlist.add(product.id);
        if (button) {
          button.classList.add('text-tech-400');
        }
        toast('Added to wishlist.', 'success');
      }
      setCounter(navWishlistCount, state.wishlist.size);
    }

    function resetCardEmphasis() {
      productCards.forEach(function (item) {
        item.card.classList.remove('sf-highlight');
      });
    }

    function spotlightProduct(product) {
      if (!product || !product.card) {
        return;
      }
      resetCardEmphasis();
      product.card.classList.add('sf-highlight');
      scrollToElement(product.card);
    }

    function computeSuggestions(query) {
      var normalized = (query || '').trim().toLowerCase();
      if (!normalized) {
        return [];
      }
      return productCards
        .filter(function (item) {
          return (
            item.name.toLowerCase().indexOf(normalized) !== -1 ||
            item.sku.toLowerCase().indexOf(normalized) !== -1 ||
            item.category.toLowerCase().indexOf(normalized) !== -1 ||
            item.brand.toLowerCase().indexOf(normalized) !== -1
          );
        })
        .slice(0, 6);
    }

    function hideSuggestions() {
      if (!suggestionBox) {
        return;
      }
      suggestionBox.classList.add('hidden');
      suggestionBox.innerHTML = '';
    }

    function renderSuggestions(matches) {
      if (!suggestionBox) {
        return;
      }
      if (!matches.length) {
        hideSuggestions();
        return;
      }
      suggestionBox.innerHTML = matches
        .map(function (item) {
          return (
            '<button type="button" class="sf-suggest-item" data-id="' +
            item.id +
            '">' +
            '<div class="font-mono text-[11px] text-teal-300">' +
            item.sku +
            '</div>' +
            '<div>' +
            item.name +
            ' <span class="text-slate-500">• ' +
            item.category +
            '</span></div>' +
            '</button>'
          );
        })
        .join('');
      suggestionBox.classList.remove('hidden');
      Array.prototype.forEach.call(suggestionBox.querySelectorAll('.sf-suggest-item'), function (button) {
        bindClick(button, function () {
          var product = getProductById(button.getAttribute('data-id'));
          if (!product) {
            return;
          }
          state.searchQuery = product.name;
          if (searchInput) {
            searchInput.value = product.name;
          }
          applyFilters();
          spotlightProduct(product);
          hideSuggestions();
        });
      });
    }

    function selectedBrands() {
      var selected = [];
      Array.prototype.forEach.call(brandFilters, function (input) {
        if (input.checked) {
          selected.push(input.value);
        }
      });
      return selected;
    }

    function applyFilters() {
      var maxPrice = Number(priceFilter ? priceFilter.value : 50000);
      var minRating = Number(ratingFilter ? ratingFilter.value : 0);
      var inStockOnly = !!(stockOnlyFilter && stockOnlyFilter.checked);
      var brands = selectedBrands();
      var query = (state.searchQuery || '').trim().toLowerCase();
      var categoryQuery = (state.categoryQuery || '').trim().toLowerCase();
      var sortMode = sortSelect ? sortSelect.value : 'featured';

      var filtered = productCards.filter(function (item) {
        var queryMatch =
          !query ||
          item.name.toLowerCase().indexOf(query) !== -1 ||
            item.sku.toLowerCase().indexOf(query) !== -1 ||
            item.category.toLowerCase().indexOf(query) !== -1 ||
            item.brand.toLowerCase().indexOf(query) !== -1;
        var categoryMatch = !categoryQuery || item.category.toLowerCase() === categoryQuery;
        var brandMatch = !brands.length || brands.indexOf(item.brand) !== -1;
        var ratingMatch = item.rating >= minRating;
        var priceMatch = item.price <= maxPrice;
        var stockMatch = !inStockOnly || item.stock > 0;
        return queryMatch && categoryMatch && brandMatch && ratingMatch && priceMatch && stockMatch;
      });

      filtered.sort(function (a, b) {
        if (sortMode === 'price-asc') {
          return a.price - b.price;
        }
        if (sortMode === 'price-desc') {
          return b.price - a.price;
        }
        if (sortMode === 'stock-desc') {
          return b.stock - a.stock;
        }
        return a.index - b.index;
      });

      productCards.forEach(function (item) {
        item.card.style.display = 'none';
      });

      filtered.forEach(function (item) {
        item.card.style.display = '';
        if (productGrid) {
          productGrid.appendChild(item.card);
        }
      });

      if (resultCount) {
        resultCount.textContent = String(filtered.length) + ' UNITS';
      }

      if (emptyState) {
        emptyState.classList.toggle('hidden', filtered.length !== 0);
      }

      if (filtered.length === 0) {
        toast('No products matched current filters.', 'warn');
      }
    }

    var miniCart = ensureMiniCartElement(cartLink);
    var productModal = ensureProductModal();
    var checkoutModal = ensureCheckoutModal();

    function hideMiniCart() {
      miniCart.classList.remove('show');
    }

    function renderMiniCart() {
      var itemWrap = document.getElementById('sf-mini-items');
      var subtotalEl = document.getElementById('sf-mini-subtotal');
      if (!itemWrap || !subtotalEl) {
        return;
      }
      var lines = cartLines();
      if (!lines.length) {
        itemWrap.innerHTML = '<div class="text-slate-500 text-xs py-2">Cart is empty.</div>';
      } else {
        itemWrap.innerHTML = lines
          .map(function (line) {
            return (
              '<div class="rounded-md border border-slate-700 p-2 text-xs text-slate-200">' +
              '<div class="font-medium text-white">' + line.name + '</div>' +
              '<div class="flex justify-between mt-1"><span>Qty ' + line.qty + '</span><span>' + money(line.qty * line.price) + '</span></div>' +
              '<div class="mt-2 flex items-center gap-2">' +
              '<button type="button" class="sf-mini-dec px-2 py-1 border border-slate-600 rounded text-[11px]" data-id="' + line.id + '">-</button>' +
              '<button type="button" class="sf-mini-inc px-2 py-1 border border-slate-600 rounded text-[11px]" data-id="' + line.id + '">+</button>' +
              '<button type="button" class="sf-mini-rm px-2 py-1 border border-rose-800 text-rose-300 rounded text-[11px]" data-id="' + line.id + '">Remove</button>' +
              '</div>' +
              '</div>'
            );
          })
          .join('');

        Array.prototype.forEach.call(itemWrap.querySelectorAll('.sf-mini-dec'), function (button) {
          bindClick(button, function () {
            changeCartQty(button.getAttribute('data-id'), -1);
          });
        });
        Array.prototype.forEach.call(itemWrap.querySelectorAll('.sf-mini-inc'), function (button) {
          bindClick(button, function () {
            changeCartQty(button.getAttribute('data-id'), 1);
          });
        });
        Array.prototype.forEach.call(itemWrap.querySelectorAll('.sf-mini-rm'), function (button) {
          bindClick(button, function () {
            changeCartQty(button.getAttribute('data-id'), -9999);
          });
        });
      }
      subtotalEl.textContent = money(cartSubtotal());
    }

    function closeProductModal() {
      productModal.classList.remove('show');
    }

    function switchProductTab(tabName) {
      var targets = ['desc', 'specs', 'reviews'];
      targets.forEach(function (name) {
        var panel = document.getElementById('sf-pm-tab-' + name);
        if (!panel) {
          return;
        }
        panel.classList.toggle('hidden', name !== tabName);
      });
      Array.prototype.forEach.call(productModal.querySelectorAll('.sf-tab-btn'), function (button) {
        button.classList.toggle('active', button.getAttribute('data-tab') === tabName);
      });
    }

    function openProductModal(product) {
      if (!product) {
        return;
      }
      state.activeProductId = product.id;
      var title = document.getElementById('sf-pm-title');
      var main = document.getElementById('sf-pm-main');
      var gallery = document.getElementById('sf-pm-gallery');
      var stock = document.getElementById('sf-pm-stock');
      var price = document.getElementById('sf-pm-price');
      var breakdown = document.getElementById('sf-pm-breakdown');
      var desc = document.getElementById('sf-pm-tab-desc');
      var specs = document.getElementById('sf-pm-tab-specs');
      var reviews = document.getElementById('sf-pm-tab-reviews');
      var tiers = document.getElementById('sf-pm-tier');
      if (!title || !main || !gallery || !stock || !price || !breakdown || !desc || !specs || !reviews || !tiers) {
        return;
      }

      var vat = Math.round(product.price * 0.05);
      title.textContent = product.name + ' • ' + product.sku;
      stock.textContent = product.stock <= 3 ? 'Only ' + product.stock + ' left in stock' : 'In stock: ' + product.stock + ' units';
      price.textContent = money(product.price);
      breakdown.innerHTML =
        '<div>Base: ' + money(product.price - vat) + '</div>' +
        '<div>VAT (5%): ' + money(vat) + '</div>' +
        '<div class="text-xs text-slate-500 mt-1">Bulk tier discounts apply automatically at checkout.</div>';
      main.textContent = product.gallery[0] || product.sku;

      gallery.innerHTML = product.gallery
        .map(function (label, index) {
          return '<button type="button" class="sf-pm-thumb rounded-md border border-slate-700 bg-slate-900 p-2 text-[11px] text-slate-300" data-thumb="' + label + '" data-index="' + index + '">' + label + '</button>';
        })
        .join('');

      Array.prototype.forEach.call(gallery.querySelectorAll('.sf-pm-thumb'), function (button) {
        bindClick(button, function () {
          main.textContent = button.getAttribute('data-thumb') || product.sku;
        });
      });

      desc.textContent = product.summary;
      specs.innerHTML = product.specs.map(function (item) {
        return '<div>• ' + item + '</div>';
      }).join('');
      reviews.innerHTML = product.reviews.map(function (review) {
        return '<div class="mb-2 border-b border-slate-800 pb-2"><div class="text-white text-xs">' + review.name + ' • ' + review.rating + '/5</div><div>' + review.comment + '</div></div>';
      }).join('');

      tiers.innerHTML =
        '<div class="font-mono text-teal-300 mb-1">Bulk tiers</div>' +
        '<div>2-4 units: 5% off</div>' +
        '<div>5-9 units: 9% off</div>' +
        '<div>10+ units: 14% off</div>';

      switchProductTab('desc');
      productModal.classList.add('show');
    }

    function closeCheckoutModal() {
      checkoutModal.classList.remove('show');
    }

    function renderCheckoutSummary() {
      var itemWrap = document.getElementById('sf-co-items');
      var subtotalEl = document.getElementById('sf-co-subtotal');
      var shippingEl = document.getElementById('sf-co-shipping');
      var taxEl = document.getElementById('sf-co-tax');
      var discountEl = document.getElementById('sf-co-discount');
      var totalEl = document.getElementById('sf-co-total');
      if (!itemWrap || !subtotalEl || !shippingEl || !taxEl || !discountEl || !totalEl) {
        return;
      }

      var lines = cartLines();
      itemWrap.innerHTML = lines.length
        ? lines.map(function (line) {
            return '<div class="flex justify-between"><span>' + line.name + ' × ' + line.qty + '</span><span>' + money(line.qty * line.price) + '</span></div>';
          }).join('')
        : '<div class="text-slate-500">No items added yet.</div>';

      var subtotal = cartSubtotal();
      var tax = cartTax(subtotal);
      subtotalEl.textContent = money(subtotal);
      shippingEl.textContent = money(state.shippingFee);
      taxEl.textContent = money(tax);
      discountEl.textContent = '-' + money(state.discount);
      totalEl.textContent = money(subtotal + tax + state.shippingFee - state.discount);
    }

    function openCheckoutModal() {
      if (!cartLines().length) {
        toast('Cart is empty. Add a product first.', 'warn');
        return;
      }
      renderCheckoutSummary();
      checkoutModal.classList.add('show');
    }

    function validateCheckout() {
      var nameInput = document.getElementById('sf-co-name');
      var emailInput = document.getElementById('sf-co-email');
      var phoneInput = document.getElementById('sf-co-phone');
      var addressInput = document.getElementById('sf-co-address');
      var districtInput = document.getElementById('sf-co-district');
      if (!nameInput || !emailInput || !phoneInput || !addressInput || !districtInput) {
        return false;
      }
      var isValid = true;

      function setErr(id, message) {
        var target = document.getElementById(id);
        if (!target) {
          return;
        }
        target.textContent = message || '';
      }

      var emailOk = /[^@\s]+@[^@\s]+\.[^@\s]+/.test(emailInput.value.trim());
      var phoneNormalized = phoneInput.value.replace(/\D/g, '');

      setErr('sf-co-name-err', nameInput.value.trim() ? '' : 'Name is required.');
      setErr('sf-co-email-err', emailOk ? '' : 'Valid email is required.');
      setErr('sf-co-phone-err', phoneNormalized.length >= 10 ? '' : 'Valid phone is required.');
      setErr('sf-co-address-err', addressInput.value.trim() ? '' : 'Address is required.');
      setErr('sf-co-district-err', districtInput.value.trim() ? '' : 'District is required.');

      if (!nameInput.value.trim() || !emailOk || phoneNormalized.length < 10 || !addressInput.value.trim() || !districtInput.value.trim()) {
        isValid = false;
      }

      return isValid;
    }

    function bindStorefrontEvents() {
      bindClick(wishlistLink, function (event) {
        event.preventDefault();
        toast('Wishlist contains ' + state.wishlist.size + ' saved items.');
      });

      bindClick(cartLink, function (event) {
        event.preventDefault();
        renderMiniCart();
        miniCart.classList.toggle('show');
      });

      bindClick(loginLink, function (event) {
        event.preventDefault();
        window.location.href = '/login';
      });

      if (searchInput) {
        searchInput.addEventListener('input', function () {
          state.searchQuery = searchInput.value.trim();
          if (mobileSearchInput) {
            mobileSearchInput.value = state.searchQuery;
          }
          renderSuggestions(computeSuggestions(state.searchQuery));
          applyFilters();
        });

        searchInput.addEventListener('keydown', function (event) {
          if (event.key !== 'Enter') {
            return;
          }
          event.preventDefault();
          var matches = computeSuggestions(searchInput.value.trim());
          if (!matches.length) {
            toast('No matching product found for this query.', 'warn');
            return;
          }
          state.searchQuery = matches[0].name;
          searchInput.value = matches[0].name;
          applyFilters();
          spotlightProduct(matches[0]);
          hideSuggestions();
        });
      }

      if (mobileSearchInput) {
        mobileSearchInput.addEventListener('input', function () {
          state.searchQuery = mobileSearchInput.value.trim();
          if (searchInput) {
            searchInput.value = state.searchQuery;
          }
          renderSuggestions(computeSuggestions(state.searchQuery));
          applyFilters();
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
        state.searchQuery = '';
        state.categoryQuery = '';
        if (searchInput) {
          searchInput.value = '';
        }
        if (mobileSearchInput) {
          mobileSearchInput.value = '';
        }
        applyFilters();
        scrollToElement(featuredSection);
      });

      bindClick(heroAddButton, function () {
        var source = productCards[0];
        addToCart(source, 1);
        toast('Added Series-X compatible arm to cart.', 'success');
      });

      Array.prototype.forEach.call(document.querySelectorAll('[data-category]'), function (link) {
        bindClick(link, function (event) {
          event.preventDefault();
          state.categoryQuery = (link.getAttribute('data-category') || '').trim();
          state.searchQuery = '';
          if (searchInput) {
            searchInput.value = '';
          }
          applyFilters();
          scrollToElement(featuredSection);
          toast('Filtered by ' + state.categoryQuery + '.');
        });
      });

      Array.prototype.forEach.call(document.querySelectorAll('.sf-product-card'), function (card) {
        var id = card.getAttribute('data-sku');
        var product = getProductById(id);
        var heartBtn = card.querySelector('.sf-heart-btn');
        var addBtn = card.querySelector('.sf-add-cart-btn');
        var viewBtn = card.querySelector('.sf-view-product-btn');

        bindClick(heartBtn, function () {
          toggleWishlist(product, heartBtn);
        });
        bindClick(addBtn, function () {
          addToCart(product, 1);
          toast('Added ' + product.name + ' to cart.', 'success');
        });
        bindClick(viewBtn, function () {
          openProductModal(product);
        });
      });

      bindClick(panelLoginLink, function (event) {
        event.preventDefault();
        window.location.href = '/admin/login';
      });

      bindClick(document.getElementById('sf-mini-close'), hideMiniCart);
      bindClick(document.getElementById('sf-mini-checkout'), function () {
        hideMiniCart();
        openCheckoutModal();
      });

      bindClick(document.getElementById('sf-pm-close'), closeProductModal);
      bindClick(document.getElementById('sf-pm-add'), function () {
        var product = getProductById(state.activeProductId);
        if (!product) {
          return;
        }
        addToCart(product, 1);
        closeProductModal();
        toast('Added ' + product.name + ' to cart.', 'success');
      });

      Array.prototype.forEach.call(productModal.querySelectorAll('.sf-tab-btn'), function (button) {
        bindClick(button, function () {
          switchProductTab(button.getAttribute('data-tab') || 'desc');
        });
      });

      bindClick(document.getElementById('sf-checkout-close'), closeCheckoutModal);

      bindClick(document.getElementById('sf-co-apply'), function () {
        var input = document.getElementById('sf-co-coupon');
        var message = document.getElementById('sf-co-coupon-msg');
        if (!input || !message) {
          return;
        }
        var code = input.value.trim().toUpperCase();
        state.discount = 0;
        state.shippingFee = 120;
        if (code === 'SAVE10') {
          state.discount = Math.round(cartSubtotal() * 0.1);
          message.textContent = 'Coupon SAVE10 applied.';
        } else if (code === 'FREESHIP') {
          state.shippingFee = 0;
          message.textContent = 'Free shipping applied.';
        } else if (!code) {
          message.textContent = 'Enter a coupon code.';
        } else {
          message.textContent = 'Coupon is invalid.';
        }
        renderCheckoutSummary();
      });

      var checkoutForm = document.getElementById('sf-checkout-form');
      if (checkoutForm) {
        checkoutForm.addEventListener('submit', function (event) {
          event.preventDefault();
          if (!validateCheckout()) {
            toast('Please correct highlighted checkout fields.', 'warn');
            return;
          }
          toast('Order placed successfully. Confirmation sent via email.', 'success');
          state.cart = {};
          state.discount = 0;
          state.shippingFee = 120;
          setCounter(navCartCount, 0);
          renderMiniCart();
          renderCheckoutSummary();
          closeCheckoutModal();
        });
      }

      if (priceFilter && priceFilterLabel) {
        priceFilter.addEventListener('input', function () {
          priceFilterLabel.textContent = 'Up to ' + money(priceFilter.value);
          applyFilters();
        });
      }
      if (ratingFilter) {
        ratingFilter.addEventListener('change', applyFilters);
      }
      if (stockOnlyFilter) {
        stockOnlyFilter.addEventListener('change', applyFilters);
      }
      if (sortSelect) {
        sortSelect.addEventListener('change', applyFilters);
      }
      Array.prototype.forEach.call(brandFilters, function (input) {
        input.addEventListener('change', applyFilters);
      });

      Array.prototype.forEach.call(document.querySelectorAll('a[href="#"]'), function (link) {
        if (link.dataset.wiredClick === 'true') {
          return;
        }

        bindClick(link, function (event) {
          event.preventDefault();
          var label = (link.textContent || '').trim();
          if (label === 'User Dashboard') {
            window.location.href = '/login';
            return;
          }
          if (label === 'Order History') {
            openCheckoutModal();
            return;
          }
          if (label === 'Wishlist') {
            toast('Wishlist contains ' + state.wishlist.size + ' items.');
            return;
          }
          if (label === 'Support Ticket') {
            window.location.href = 'mailto:support@maattech.com?subject=Support%20Request';
            return;
          }
          toast('Action executed: ' + (label || 'Navigation link'));
        });
      });

      document.addEventListener('click', function (event) {
        if (searchShell && suggestionBox && !searchShell.contains(event.target)) {
          hideSuggestions();
        }
        if (miniCart && cartLink && !miniCart.contains(event.target) && !cartLink.contains(event.target)) {
          hideMiniCart();
        }
        if (event.target === productModal) {
          closeProductModal();
        }
        if (event.target === checkoutModal) {
          closeCheckoutModal();
        }
      });
    }

    bindStorefrontEvents();
    seedCounters();
    applyFilters();
    renderMiniCart();
    renderCheckoutSummary();
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
      window.location.href = '/admin/login';
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
        toast('Admin action completed.');
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
