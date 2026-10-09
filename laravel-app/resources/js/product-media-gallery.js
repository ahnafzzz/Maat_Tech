export const galleryNextIndex = (current, count, key) => {
    if (count < 1) return -1;
    if (key === 'Home') return 0;
    if (key === 'End') return count - 1;
    const direction = key === 'ArrowRight' ? 1 : -1;
    return (current + direction + count) % count;
};

export class ProductMediaGallery {
    constructor(element) {
        this.element = element;
        this.buttons = [...element.querySelectorAll('[data-media-select]')];
        this.panels = [...element.querySelectorAll('[data-media-panel]')];
        this.showroomOnly = [...element.querySelectorAll('[data-showroom-only]')];
        this.video = element.querySelector('[data-media-video]');
        this.active = element.dataset.activeMedia || '3d';
        this.onClick = (event) => {
            const button = event.target.closest('[data-media-select]');
            if (button) this.show(button.dataset.mediaSelect);
        };
        this.onKeydown = (event) => {
            const current = event.target.closest('[data-media-select]');
            if (!current || !['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
            event.preventDefault();
            const index = this.buttons.indexOf(current);
            const nextIndex = galleryNextIndex(index, this.buttons.length, event.key);
            this.show(this.buttons[nextIndex].dataset.mediaSelect);
        };
        this.onMediaError = (event) => {
            if (!event.target.matches('[data-media-image], [data-media-video]')) return;
            event.target.hidden = true;
            const fallback = event.target.parentElement?.querySelector('[data-media-fallback]');
            if (fallback) fallback.hidden = false;
        };
        this.onPurchaseFinish = (event) => {
            if (event.detail?.finish) {
                this.element.dispatchEvent(new CustomEvent('showroom-finish-request', { detail: event.detail }));
            }
        };
        this.onPageHide = (event) => event.persisted ? this.video?.pause() : this.destroy();
    }

    start() {
        this.element.addEventListener('click', this.onClick);
        this.element.addEventListener('keydown', this.onKeydown);
        this.element.addEventListener('error', this.onMediaError, true);
        this.element.addEventListener('purchase-finish-change', this.onPurchaseFinish);
        window.addEventListener('pagehide', this.onPageHide);
        this.show(this.active, { focus: false });
        return this;
    }

    show(name, { focus = true } = {}) {
        if (name !== '3d' && !this.panels.some((panel) => panel.dataset.mediaPanel === name)) return;
        if (this.active === 'video' && name !== 'video') this.video?.pause();

        this.active = name;
        this.element.dataset.activeMedia = name;
        this.panels.forEach((panel) => {
            const selected = panel.dataset.mediaPanel === name;
            panel.hidden = !selected;
            if (selected) {
                panel.querySelectorAll('[data-src]').forEach((media) => {
                    if (!media.getAttribute('src')) media.setAttribute('src', media.dataset.src);
                });
            }
        });
        this.showroomOnly.forEach((control) => { control.hidden = name !== '3d'; });
        this.buttons.forEach((button) => {
            const selected = button.dataset.mediaSelect === name;
            button.setAttribute('aria-selected', selected ? 'true' : 'false');
            button.tabIndex = selected ? 0 : -1;
            button.classList.toggle('border-tech-400', selected);
            button.classList.toggle('bg-tech-950/50', selected);
        });
        this.element.dispatchEvent(new CustomEvent('product-media-active', { detail: { media: name } }));
        if (focus) this.buttons.find((button) => button.dataset.mediaSelect === name)?.focus({ preventScroll: true });
    }

    destroy() {
        this.video?.pause();
        this.element.removeEventListener('click', this.onClick);
        this.element.removeEventListener('keydown', this.onKeydown);
        this.element.removeEventListener('error', this.onMediaError, true);
        this.element.removeEventListener('purchase-finish-change', this.onPurchaseFinish);
        window.removeEventListener('pagehide', this.onPageHide);
        delete this.element.dataset.mediaGalleryInitialized;
    }
}

export function initializeProductMediaGalleries(root = document) {
    return [...root.querySelectorAll('[data-product-media-gallery]:not([data-media-gallery-initialized])')]
        .map((element) => {
            element.dataset.mediaGalleryInitialized = 'true';
            return new ProductMediaGallery(element).start();
        });
}
