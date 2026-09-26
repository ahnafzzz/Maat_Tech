import { DeskLampShowcaseRenderer } from './desk-lamp-renderer.js';

const MAX_WEBGL_CONTEXTS = 2;

export class ProductShowcaseManager {
    constructor(root = document, rendererFactory = DeskLampShowcaseRenderer.create) {
        this.root = root;
        this.rendererFactory = rendererFactory;
        this.states = new Map();
        this.motion = window.matchMedia('(prefers-reduced-motion: reduce)');
        this.documentVisible = !document.hidden;
        this.preloadObserver = 'IntersectionObserver' in window
            ? new IntersectionObserver((entries) => this.onPreload(entries), { rootMargin: '320px 0px' })
            : null;
        this.visibilityObserver = 'IntersectionObserver' in window
            ? new IntersectionObserver((entries) => this.onVisibility(entries), { threshold: 0.01 })
            : null;
        this.onDocumentVisibility = () => {
            this.documentVisible = !document.hidden;
            for (const state of this.states.values()) this.updateActivity(state);
        };
        this.onMotionChange = () => {
            for (const state of this.states.values()) {
                state.renderer?.setReducedMotion(this.motion.matches);
                this.setStatus(state, this.motion.matches ? 'Stationary 3D preview' : '3D preview');
                this.updateActivity(state);
            }
        };
        this.onPageHide = () => this.destroy();
    }

    start() {
        const elements = [...this.root.querySelectorAll('[data-product-showcase][data-showcase-manifest]')];
        for (const element of elements) {
            const poster = element.querySelector('[data-showcase-poster]');
            const state = {
                element,
                poster,
                canvas: element.querySelector('[data-showcase-canvas]'),
                status: element.querySelector('[data-showcase-status]'),
                visible: element.dataset.showcasePriority === 'initial',
                renderer: null,
                controller: null,
                loading: false,
                failed: false,
                loadedAt: 0,
            };
            poster?.addEventListener('error', () => this.useImageFallback(state), { once: true });
            this.states.set(element, state);
            this.visibilityObserver?.observe(element);
            if (element.dataset.showcasePriority === 'initial' || !this.preloadObserver) this.load(state);
            else this.preloadObserver.observe(element);
        }
        document.addEventListener('visibilitychange', this.onDocumentVisibility);
        this.motion.addEventListener?.('change', this.onMotionChange);
        window.addEventListener('pagehide', this.onPageHide, { once: true });

        return this;
    }

    onPreload(entries) {
        for (const entry of entries) {
            if (!entry.isIntersecting) continue;
            const state = this.states.get(entry.target);
            if (state) this.load(state);
            this.preloadObserver.unobserve(entry.target);
        }
    }

    onVisibility(entries) {
        for (const entry of entries) {
            const state = this.states.get(entry.target);
            if (!state) continue;
            state.visible = entry.isIntersecting && entry.intersectionRatio > 0;
            this.updateActivity(state);
            if (state.visible && !state.failed && !state.renderer && !state.loading) this.load(state);
        }
    }

    releaseContextSlot(currentState) {
        const loaded = [...this.states.values()].filter((state) => state.renderer && state !== currentState);
        if (loaded.length < MAX_WEBGL_CONTEXTS) return true;
        const candidate = loaded.filter((state) => !state.visible).sort((a, b) => a.loadedAt - b.loadedAt)[0];
        if (!candidate) return false;
        candidate.renderer.destroy();
        candidate.renderer = null;
        candidate.element.dataset.showcaseState = 'poster';
        candidate.element.dataset.showcaseAnimation = 'paused';
        this.setStatus(candidate, '3D preview paused');

        return true;
    }

    async load(state) {
        if (state.loading || state.failed || state.renderer || !state.canvas || !this.releaseContextSlot(state)) return;
        state.loading = true;
        state.controller = new AbortController();
        state.element.dataset.showcaseState = 'loading';
        this.setStatus(state, 'Loading 3D preview');

        try {
            state.renderer = await this.rendererFactory(
                state.canvas,
                state.element.dataset.showcaseManifest,
                {
                    onFirstFrame: () => {
                        state.element.dataset.showcaseState = 'ready';
                        this.setStatus(state, this.motion.matches ? 'Stationary 3D preview' : '3D preview');
                    },
                    onError: () => this.fail(state),
                },
                state.controller.signal,
            );
            state.loadedAt = performance.now();
            state.failed = false;
            state.renderer.setReducedMotion(this.motion.matches);
            this.updateActivity(state);
        } catch (error) {
            if (error?.name !== 'AbortError') this.fail(state);
        } finally {
            state.loading = false;
        }
    }

    updateActivity(state) {
        const active = state.visible && this.documentVisible;
        state.renderer?.setDocumentVisible(this.documentVisible);
        state.renderer?.setActive(active);
        state.element.dataset.showcaseAnimation = active && !this.motion.matches ? 'running' : 'paused';
    }

    fail(state) {
        state.renderer?.destroy();
        state.renderer = null;
        state.failed = true;
        state.element.dataset.showcaseState = 'fallback';
        state.element.dataset.showcaseAnimation = 'paused';
        this.setStatus(state, 'Product preview');
    }

    useImageFallback(state) {
        const fallback = state.poster?.dataset.fallbackSrc;
        if (fallback && state.poster.src !== fallback) state.poster.src = fallback;
    }

    setStatus(state, message) {
        if (state.status) state.status.textContent = message;
    }

    destroy() {
        this.preloadObserver?.disconnect();
        this.visibilityObserver?.disconnect();
        document.removeEventListener('visibilitychange', this.onDocumentVisibility);
        this.motion.removeEventListener?.('change', this.onMotionChange);
        for (const state of this.states.values()) {
            state.controller?.abort();
            state.renderer?.destroy();
        }
        this.states.clear();
    }
}

export function initializeProductShowcases(root = document) {
    return new ProductShowcaseManager(root).start();
}
