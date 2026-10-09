export const slideshowShouldAdvance = ({ documentVisible, onscreen, reducedMotion, focusInside = false }) => (
    documentVisible && onscreen && !reducedMotion && !focusInside
);

export class StorefrontSlideshow {
    constructor(element) {
        this.element = element;
        this.slides = [...element.querySelectorAll('[data-slideshow-slide]')];
        this.dots = [...element.querySelectorAll('[data-slideshow-dot]')];
        this.previous = element.querySelector('[data-slideshow-previous]');
        this.next = element.querySelector('[data-slideshow-next]');
        this.interval = Number(element.dataset.slideshowInterval) || 5000;
        this.motion = window.matchMedia('(prefers-reduced-motion: reduce)');
        this.documentVisible = !document.hidden;
        this.onscreen = true;
        this.focusInside = false;
        this.index = Math.max(0, this.slides.findIndex((slide) => slide.dataset.active === 'true'));
        this.timer = null;
        this.observer = 'IntersectionObserver' in window
            ? new IntersectionObserver(([entry]) => {
                this.onscreen = entry.isIntersecting && entry.intersectionRatio > 0;
                this.schedule();
            }, { threshold: 0.01 })
            : null;

        this.onPrevious = () => this.show(this.index - 1, { manual: true });
        this.onNext = () => this.show(this.index + 1, { manual: true });
        this.onDotClick = (event) => this.show(Number(event.currentTarget.dataset.slideshowDot), { manual: true });
        this.onVisibility = () => {
            this.documentVisible = !document.hidden;
            this.schedule();
        };
        this.onMotion = () => this.schedule();
        this.onFocusIn = () => { this.focusInside = true; this.schedule(); };
        this.onFocusOut = (event) => {
            if (this.element.contains(event.relatedTarget)) return;
            this.focusInside = false;
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
        this.element.addEventListener('focusin', this.onFocusIn);
        this.element.addEventListener('focusout', this.onFocusOut);
        this.element.addEventListener('focus', this.onFocusIn, true);
        this.element.addEventListener('blur', this.onFocusOut, true);
        document.addEventListener('visibilitychange', this.onVisibility);
        this.motion.addEventListener?.('change', this.onMotion);
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
            reducedMotion: this.motion.matches,
            focusInside: this.focusInside,
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
        this.element.removeEventListener('focusin', this.onFocusIn);
        this.element.removeEventListener('focusout', this.onFocusOut);
        this.element.removeEventListener('focus', this.onFocusIn, true);
        this.element.removeEventListener('blur', this.onFocusOut, true);
        document.removeEventListener('visibilitychange', this.onVisibility);
        this.motion.removeEventListener?.('change', this.onMotion);
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
