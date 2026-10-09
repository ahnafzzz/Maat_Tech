export const slideshowShouldAdvance = ({ documentVisible, onscreen }) => (
    documentVisible && onscreen
);

export class StorefrontSlideshow {
    constructor(element) {
        this.element = element;
        this.slides = [...element.querySelectorAll('[data-slideshow-slide]')];
        this.dots = [...element.querySelectorAll('[data-slideshow-dot]')];
        this.previous = element.querySelector('[data-slideshow-previous]');
        this.next = element.querySelector('[data-slideshow-next]');
        this.interval = Number(element.dataset.slideshowInterval) || 3000;
        this.documentVisible = !document.hidden;
        this.onscreen = true;
        this.index = Math.max(0, this.slides.findIndex((slide) => slide.dataset.active === 'true'));
        this.timer = null;
        this.swipeStart = null;
        this.suppressClickUntil = 0;
        this.observer = 'IntersectionObserver' in window
            ? new IntersectionObserver(([entry]) => {
                this.onscreen = entry.isIntersecting && entry.intersectionRatio > 0;
                this.schedule();
            }, { threshold: 0.01 })
            : null;

        this.onPrevious = () => this.show(this.index - 1, { manual: true });
        this.onNext = () => this.show(this.index + 1, { manual: true });
        this.onDotClick = (event) => this.show(Number(event.currentTarget.dataset.slideshowDot), { manual: true });
        this.onTouchStart = (event) => {
            if (event.touches.length !== 1) return;
            const touch = event.touches[0];
            this.swipeStart = { x: touch.clientX, y: touch.clientY };
        };
        this.onTouchEnd = (event) => {
            if (!this.swipeStart || event.changedTouches.length !== 1) return;
            const touch = event.changedTouches[0];
            const deltaX = touch.clientX - this.swipeStart.x;
            const deltaY = touch.clientY - this.swipeStart.y;
            this.swipeStart = null;

            if (Math.abs(deltaX) < 45 || Math.abs(deltaX) <= Math.abs(deltaY) * 1.2) return;
            this.suppressClickUntil = performance.now() + 500;
            this.show(this.index + (deltaX < 0 ? 1 : -1), { manual: true });
        };
        this.onTouchCancel = () => { this.swipeStart = null; };
        this.onClick = (event) => {
            if (performance.now() >= this.suppressClickUntil) return;
            event.preventDefault();
            event.stopPropagation();
        };
        this.onVisibility = () => {
            this.documentVisible = !document.hidden;
            this.schedule();
        };
        this.onPageHide = (event) => {
            if (!event.persisted) return this.destroy();
            this.documentVisible = false;
            this.schedule();
        };
        this.onPageShow = (event) => {
            if (!event.persisted) return;
            this.documentVisible = !document.hidden;
            this.schedule();
        };
    }

    start() {
        if (this.slides.length < 2) return this;
        this.previous?.addEventListener('click', this.onPrevious);
        this.next?.addEventListener('click', this.onNext);
        this.dots.forEach((dot) => dot.addEventListener('click', this.onDotClick));
        this.element.addEventListener('touchstart', this.onTouchStart, { passive: true });
        this.element.addEventListener('touchend', this.onTouchEnd, { passive: true });
        this.element.addEventListener('touchcancel', this.onTouchCancel, { passive: true });
        this.element.addEventListener('click', this.onClick, true);
        document.addEventListener('visibilitychange', this.onVisibility);
        window.addEventListener('pagehide', this.onPageHide);
        window.addEventListener('pageshow', this.onPageShow);
        this.observer?.observe(this.element);
        this.show(this.index);

        return this;
    }

    show(requestedIndex, { manual = false } = {}) {
        const count = this.slides.length;
        this.index = ((requestedIndex % count) + count) % count;
        this.slides.forEach((slide, index) => {
            const active = index === this.index;
            slide.dataset.active = active ? 'true' : 'false';
            slide.setAttribute('aria-hidden', active ? 'false' : 'true');
            slide.querySelectorAll('a, button').forEach((control) => {
                if (active) control.removeAttribute('tabindex');
                else control.setAttribute('tabindex', '-1');
            });
        });
        this.dots.forEach((dot, index) => {
            const active = index === this.index;
            dot.setAttribute('aria-current', active ? 'true' : 'false');
            dot.setAttribute('aria-label', `${active ? 'Current slide' : 'Go to slide'} ${index + 1}`);
        });
        this.element.dataset.slideshowIndex = String(this.index);
        if (manual) this.element.dataset.slideshowLastAction = 'manual';
        this.schedule();
    }

    schedule() {
        window.clearTimeout(this.timer);
        this.timer = null;
        const running = slideshowShouldAdvance({
            documentVisible: this.documentVisible,
            onscreen: this.onscreen,
        });
        this.element.dataset.slideshowAnimation = running ? 'running' : 'paused';
        if (!running || this.slides.length < 2) return;
        this.timer = window.setTimeout(() => this.show(this.index + 1), this.interval);
    }

    destroy() {
        window.clearTimeout(this.timer);
        this.observer?.disconnect();
        this.previous?.removeEventListener('click', this.onPrevious);
        this.next?.removeEventListener('click', this.onNext);
        this.dots.forEach((dot) => dot.removeEventListener('click', this.onDotClick));
        this.element.removeEventListener('touchstart', this.onTouchStart);
        this.element.removeEventListener('touchend', this.onTouchEnd);
        this.element.removeEventListener('touchcancel', this.onTouchCancel);
        this.element.removeEventListener('click', this.onClick, true);
        document.removeEventListener('visibilitychange', this.onVisibility);
        window.removeEventListener('pagehide', this.onPageHide);
        window.removeEventListener('pageshow', this.onPageShow);
        delete this.element.dataset.slideshowInitialized;
    }
}

export function initializeStorefrontSlideshows(root = document) {
    return [...root.querySelectorAll('[data-storefront-slideshow]:not([data-slideshow-initialized])')]
        .map((element) => {
            element.dataset.slideshowInitialized = 'true';
            return new StorefrontSlideshow(element).start();
        });
}
