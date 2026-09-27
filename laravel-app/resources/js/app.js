import { initializeProductShowcases } from './product-showcase.js';
import { initializeProductShowrooms } from './product-showroom.js';

const initialize = () => {
    initializeProductShowcases();
    initializeProductShowrooms();
};

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize, { once: true });
else initialize();
