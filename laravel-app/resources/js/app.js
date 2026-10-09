import { initializeProductShowcases } from './product-showcase.js';
import { initializeProductShowrooms } from './product-showroom.js';
import { initializeStorefrontSlideshows } from './storefront-slideshow.js';
import { initializeProductMediaGalleries } from './product-media-gallery.js';
import { createIcons, icons } from 'lucide';

const initialize = () => {
    createIcons({ icons });
    initializeProductShowcases();
    initializeProductShowrooms();
    initializeStorefrontSlideshows();
    initializeProductMediaGalleries();

    document.querySelectorAll('form[data-submit-once]:not([data-submit-once-initialized])').forEach((form) => {
        form.dataset.submitOnceInitialized = 'true';
        form.addEventListener('submit', (event) => {
            if (form.dataset.submitting === 'true') {
                event.preventDefault();
                return;
            }
            if (!form.checkValidity()) return;
            form.dataset.submitting = 'true';

            // Wait until the browser has captured the successful submitter and form data.
            window.requestAnimationFrame(() => {
                const selector = form.id ? `button[form="${CSS.escape(form.id)}"]` : '';
                const controls = [...form.querySelectorAll('button[type="submit"], input[type="submit"]')];
                if (selector) controls.push(...document.querySelectorAll(selector));
                controls.forEach((control) => {
                    control.disabled = true;
                    control.setAttribute('aria-disabled', 'true');
                });
            });
        });
    });

    document.querySelectorAll('[data-purchase-variant]:not([data-purchase-variant-initialized])').forEach((input) => {
        input.dataset.purchaseVariantInitialized = 'true';
        input.addEventListener('change', () => {
            const quantity = document.querySelector('[data-purchase-quantity]');
            const label = document.querySelector('[data-selected-color]');
            if (quantity) {
                quantity.max = input.dataset.variantStock;
                if (Number(quantity.value) > Number(quantity.max)) quantity.value = quantity.max;
            }
            if (label) label.textContent = input.dataset.variantLabel;
            document.querySelector('[data-product-media-gallery]')?.dispatchEvent(new CustomEvent('purchase-finish-change', {
                detail: { finish: input.dataset.variantFinish },
            }));
        });
    });

    document.querySelectorAll('[data-password-toggle]:not([data-password-toggle-initialized])').forEach((button) => {
        button.dataset.passwordToggleInitialized = 'true';
        button.addEventListener('click', () => {
            const field = document.getElementById(button.dataset.passwordToggle);
            if (!field) return;
            const visible = field.type === 'password';
            field.type = visible ? 'text' : 'password';
            button.textContent = visible ? 'Hide password' : 'Show password';
        });
    });

    document.querySelectorAll('[data-image-order]:not([data-image-order-initialized])').forEach((container) => {
        container.dataset.imageOrderInitialized = 'true';
        container.addEventListener('click', (event) => {
            const button = event.target.closest('[data-image-move]');
            if (!button) return;
            const item = button.closest('[data-image-item]');
            const sibling = button.dataset.imageMove === 'up' ? item?.previousElementSibling : item?.nextElementSibling;
            if (!item || !sibling?.matches('[data-image-item]')) return;
            if (button.dataset.imageMove === 'up') item.parentNode.insertBefore(item, sibling);
            else item.parentNode.insertBefore(sibling, item);
        });
    });
};

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize, { once: true });
else initialize();

window.addEventListener('pageshow', (event) => {
    if (!event.persisted) return;
    document.querySelectorAll('form[data-submit-once]').forEach((form) => {
        delete form.dataset.submitting;
        form.querySelectorAll('[aria-disabled="true"]').forEach((control) => {
            control.disabled = false;
            control.removeAttribute('aria-disabled');
        });
    });
    initialize();
});
