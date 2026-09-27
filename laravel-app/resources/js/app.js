import { initializeProductShowcases } from './product-showcase.js';
import { initializeProductShowrooms } from './product-showroom.js';

const initialize = () => {
    initializeProductShowcases();
    initializeProductShowrooms();

    document.querySelectorAll('form[data-submit-once]').forEach((form) => {
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
};

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize, { once: true });
else initialize();
