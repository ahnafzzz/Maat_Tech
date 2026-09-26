import { initializeProductShowcases } from './product-showcase.js';

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => initializeProductShowcases(), { once: true });
} else {
    initializeProductShowcases();
}
