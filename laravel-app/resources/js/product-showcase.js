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
                state.rotationRequested = !this.motion.matches;
                state.explicitMotionChoice = false;
                state.renderer?.setReducedMotion(this.motion.matches);
                state.renderer?.setAutoRotation(state.rotationRequested);
                this.updateActivity(state);
            }
        };
        this.onPageHide = (event) => {
            if (!event.persisted) return this.destroy();
            this.documentVisible = false;
            for (const state of this.states.values()) this.updateActivity(state);
        };
        this.onPageShow = (event) => {
            if (!event.persisted) return;
            this.documentVisible = !document.hidden;
            for (const state of this.states.values()) this.updateActivity(state);
        };
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
                rotationToggle: element.querySelector('[data-showcase-rotation-toggle]'),
                visible: element.dataset.showcasePriority === 'initial',
                renderer: null,
                controller: null,
                loading: false,
                failed: false,
                loadedAt: 0,
                rotationRequested: !this.motion.matches,
                explicitMotionChoice: false,
            };
            element.dataset.showcaseInitialized = 'true';
            state.onRotationToggle = () => this.toggleRotation(state);
            state.rotationToggle?.addEventListener('click', state.onRotationToggle);
            poster?.addEventListener('error', () => this.useImageFallback(state), { once: true });
            this.states.set(element, state);
            this.syncPresentation(state);
            this.visibilityObserver?.observe(element);
            if (element.dataset.showcasePriority === 'initial' || !this.preloadObserver) this.load(state);
            else this.preloadObserver.observe(element);
        }
        document.addEventListener('visibilitychange', this.onDocumentVisibility);
        this.motion.addEventListener?.('change', this.onMotionChange);
        window.addEventListener('pagehide', this.onPageHide);
        window.addEventListener('pageshow', this.onPageShow);

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
        this.syncPresentation(candidate);
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
                        this.syncPresentation(state);
                    },
                    onError: () => this.fail(state),
                },
                state.controller.signal,
            );
            state.loadedAt = performance.now();
            state.failed = false;
            state.renderer.setReducedMotion(this.motion.matches);
            state.renderer.setAutoRotation(state.rotationRequested, {
                overrideReducedMotion: this.motion.matches && state.explicitMotionChoice && state.rotationRequested,
            });
            this.updateActivity(state);
        } catch (error) {
            if (error?.name !== 'AbortError') this.fail(state);
        } finally {
            state.loading = false;
        }
    }

    updateActivity(state) {
        const active = state.visible && this.documentVisible;
        state.element.dataset.showcaseVisibility = state.visible ? 'onscreen' : 'offscreen';
        state.element.dataset.showcaseDocument = this.documentVisible ? 'visible' : 'hidden';
        state.renderer?.setDocumentVisible(this.documentVisible);
        state.renderer?.setActive(active);
        this.syncPresentation(state);
    }

    toggleRotation(state) {
        state.rotationRequested = !state.rotationRequested;
        state.explicitMotionChoice = true;
        state.renderer?.setAutoRotation(state.rotationRequested, {
            overrideReducedMotion: this.motion.matches && state.rotationRequested,
        });
        this.updateActivity(state);
    }

    syncPresentation(state) {
        const snapshot = state.renderer?.snapshot();
        const running = Boolean(
            state.element.dataset.showcaseState === 'ready'
            && state.visible
            && this.documentVisible
            && snapshot?.autoRotate
            && (!this.motion.matches || snapshot.motionOverride)
        );
        state.element.dataset.showcaseAnimation = running ? 'running' : 'paused';

        if (state.rotationToggle) {
            state.rotationToggle.disabled = !state.renderer || state.failed;
            state.rotationToggle.setAttribute('aria-pressed', state.rotationRequested ? 'true' : 'false');
            state.rotationToggle.textContent = state.rotationRequested ? 'Pause rotation' : 'Play rotation';
        }

        if (state.element.dataset.showcaseState !== 'ready') return;
        if (running) this.setStatus(state, 'Rotating 3D preview');
        else if (this.motion.matches && !state.rotationRequested) this.setStatus(state, 'Stationary 3D preview');
        else this.setStatus(state, '3D preview paused');
    }

    fail(state) {
        state.renderer?.destroy();
        state.renderer = null;
        state.failed = true;
        state.element.dataset.showcaseState = 'fallback';
        state.element.dataset.showcaseAnimation = 'paused';
        if (state.rotationToggle) state.rotationToggle.disabled = true;
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
        window.removeEventListener('pagehide', this.onPageHide);
        window.removeEventListener('pageshow', this.onPageShow);
        for (const state of this.states.values()) {
            state.controller?.abort();
            state.renderer?.destroy();
            state.rotationToggle?.removeEventListener('click', state.onRotationToggle);
            delete state.element.dataset.showcaseInitialized;
        }
        this.states.clear();
    }
}

export function initializeProductShowcases(root = document) {
    const manager = new ProductShowcaseManager(root);
    const uninitialized = root.querySelector('[data-product-showcase][data-showcase-manifest]:not([data-showcase-initialized])');
    return uninitialized ? manager.start() : null;
}
