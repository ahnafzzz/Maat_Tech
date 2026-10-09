import { DESK_LAMP_GROUPS, DeskLampShowcaseRenderer } from './desk-lamp-renderer.js';

export const shouldCaptureOrbit = (deltaX, deltaY, threshold = 8) => (
    Math.abs(deltaX) >= threshold && Math.abs(deltaX) > Math.abs(deltaY) * 1.15
);

export class ProductShowroom {
    constructor(element, rendererFactory = DeskLampShowcaseRenderer.create) {
        this.element = element;
        this.rendererFactory = rendererFactory;
        this.canvas = element.querySelector('[data-showroom-canvas]');
        this.poster = element.querySelector('[data-showroom-poster]');
        this.status = element.querySelector('[data-showroom-status]');
        this.labels = element.querySelector('[data-showroom-labels]');
        this.controls = [...element.querySelectorAll('[data-showroom-controls]')];
        this.motion = window.matchMedia('(prefers-reduced-motion: reduce)');
        this.controller = new AbortController();
        this.pointers = new Map();
        this.renderer = null;
        this.visible = true;
        this.documentVisible = !document.hidden;
        this.mediaActive = element.dataset.activeMedia !== undefined ? element.dataset.activeMedia === '3d' : true;
        this.interacted = false;
        this.destroyed = false;
        this.rotationStorageKey = `maat:showroom:auto-rotation:${window.location.pathname}`;
        this.rotationPreference = this.readRotationPreference();
        this.observer = 'IntersectionObserver' in window
            ? new IntersectionObserver(([entry]) => {
                this.visible = entry.isIntersecting && entry.intersectionRatio > 0;
                this.updateActivity();
            }, { threshold: 0.01 })
            : null;
    }

    async start() {
        this.bind();
        this.observer?.observe(this.element);
        this.element.dataset.showroomState = 'loading';
        this.setStatus('Loading interactive 3D view');
        try {
            this.renderer = await this.rendererFactory(
                this.canvas,
                this.element.dataset.showroomManifest,
                {
                    onFirstFrame: () => {
                        this.element.dataset.showroomState = 'ready';
                        this.controls.forEach((control) => { control.disabled = false; });
                        const enabled = this.rotationPreference === null ? !this.motion.matches : this.rotationPreference;
                        this.setRotation(enabled, { explicit: this.rotationPreference === true, persist: false });
                    },
                    onError: () => this.fail(),
                    onContextLost: () => {
                        this.element.dataset.showroomState = 'context-lost';
                        this.setStatus('3D graphics interrupted · waiting for browser recovery');
                        this.updateActivity();
                    },
                    onContextRestored: () => {
                        this.element.dataset.showroomState = 'ready';
                        this.setStatus('Interactive 3D view restored');
                        this.updateActivity();
                    },
                    onLabels: (labels) => this.renderLabels(labels),
                },
                this.controller.signal,
                { mode: 'showroom', autoRotate: !this.motion.matches },
            );
            this.renderer.setReducedMotion(this.motion.matches);
            this.updateActivity();
        } catch (error) {
            if (error?.name !== 'AbortError') this.fail();
        }

        return this;
    }

    bind() {
        this.onPointerDown = (event) => {
            this.pointers.set(event.pointerId, {
                startX: event.clientX,
                startY: event.clientY,
                x: event.clientX,
                y: event.clientY,
                captured: event.pointerType === 'mouse',
                panning: event.pointerType === 'mouse' && event.shiftKey,
            });
            if (event.pointerType === 'mouse') {
                this.interact();
                this.canvas.setPointerCapture(event.pointerId);
            }
            if (this.pointers.size === 2) {
                this.interact();
                for (const pointerId of this.pointers.keys()) this.canvas.setPointerCapture(pointerId);
            }
        };
        this.onPointerMove = (event) => {
            const pointer = this.pointers.get(event.pointerId);
            if (!pointer || !this.renderer) return;
            const next = { ...pointer, x: event.clientX, y: event.clientY };
            if (this.pointers.size === 2) {
                const other = [...this.pointers.entries()].find(([id]) => id !== event.pointerId)?.[1];
                if (other) {
                    const before = Math.hypot(pointer.x - other.x, pointer.y - other.y);
                    const after = Math.hypot(next.x - other.x, next.y - other.y);
                    if (before > 3 && after > 3) this.renderer.zoomBy(before / after);
                }
                event.preventDefault();
            } else {
                if (!pointer.captured && shouldCaptureOrbit(event.clientX - pointer.startX, event.clientY - pointer.startY)) {
                    next.captured = true;
                    this.interact();
                    this.canvas.setPointerCapture(event.pointerId);
                }
                if (next.captured) {
                    if (next.panning || event.shiftKey) this.renderer.pan(event.clientX - pointer.x, event.clientY - pointer.y);
                    else this.renderer.orbit(event.clientX - pointer.x, event.clientY - pointer.y);
                    event.preventDefault();
                }
            }
            this.pointers.set(event.pointerId, next);
        };
        this.onPointerEnd = (event) => {
            this.pointers.delete(event.pointerId);
            if (this.canvas.hasPointerCapture?.(event.pointerId)) this.canvas.releasePointerCapture(event.pointerId);
        };
        this.onWheel = (event) => {
            if (!this.renderer || (document.activeElement !== this.canvas && !event.ctrlKey)) return;
            event.preventDefault();
            this.interact();
            this.renderer.zoomBy(Math.exp(event.deltaY * 0.001));
        };
        this.onKeyDown = (event) => {
            if (!this.renderer) return;
            const actions = {
                ArrowLeft: () => this.renderer.orbit(-10, 0),
                ArrowRight: () => this.renderer.orbit(10, 0),
                ArrowUp: () => this.renderer.orbit(0, -10),
                ArrowDown: () => this.renderer.orbit(0, 10),
                '+': () => this.renderer.zoomBy(0.88),
                '=': () => this.renderer.zoomBy(0.88),
                '-': () => this.renderer.zoomBy(1.14),
                Home: () => this.reset(),
            };
            if (!actions[event.key]) return;
            event.preventDefault();
            this.interact();
            actions[event.key]();
        };
        this.onClick = (event) => {
            const control = event.target.closest('[data-showroom-action]');
            if (!control) return;
            if (control.dataset.showroomAction === 'retry') {
                window.location.reload();
                return;
            }
            if (!this.renderer) return;
            this.interact();
            const { showroomAction: action, showroomValue: value } = control.dataset;
            if (action === 'finish') this.renderer.setFinish(value);
            if (action === 'light') this.renderer.setLight(value, this.powerModeLevel());
            if (action === 'zoom') this.renderer.zoomBy(Number(value));
            if (action === 'camera') this.renderer.setCameraView(value);
            if (action === 'fit') {
                this.renderer.fitAll();
                this.syncInspectionControls();
            }
            if (action === 'inspect') {
                this.renderer.inspect(value, { elevation: value === 'clamp' ? 0.16 : 0 });
                this.syncInspectionControls();
            }
            if (action === 'focus') this.renderer.fitSelection();
            if (action === 'preset') {
                this.renderer.applyPreset(value);
                this.syncAllControls();
            }
            if (action === 'resetPose') {
                this.renderer.resetPose();
                this.syncAllControls();
            }
            if (action === 'explodeToggle') {
                const next = this.renderer.snapshot().state.explode > 0 ? 0 : 75;
                this.renderer.setExplode(next);
                this.renderer.setAnatomy(next > 0);
                this.syncAllControls();
            }
            if (action === 'separateClamp') {
                this.renderer.separateClamp();
                this.syncAllControls();
            }
            if (action === 'reset') this.reset();
            this.selectButton(control);
        };
        this.onInput = (event) => {
            if (!this.renderer) return;
            const control = event.target.closest('[data-showroom-input]');
            if (!control) return;
            this.interact();
            const name = control.dataset.showroomInput;
            if (name === 'explode') {
                this.renderer.setExplode(control.value);
            } else if (name === 'anatomy') {
                this.renderer.setAnatomy(control.checked);
            } else if (name === 'wires') {
                this.renderer.setWires(control.checked);
                this.syncInspectionControls();
            } else if (name === 'autoRotate') {
                this.setRotation(control.checked, { explicit: true });
            } else if (name === 'selection') {
                this.renderer.setSelection(control.value);
                this.syncInspectionControls();
            } else if (name === 'isolate') {
                this.renderer.setIsolate(control.checked);
            } else if (name === 'powerMode') {
                this.renderer.setPowerMode(control.value);
            } else {
                this.renderer.setArticulation(name, control.value);
            }
            const output = this.element.querySelector(`[data-showroom-output="${name}"]`);
            if (output) output.textContent = `${control.value}${name === 'jaw' ? ' mm' : name === 'explode' ? '%' : name === 'powerMode' ? ' / 10' : '°'}`;
            this.updateActivity();
        };
        this.onVisibility = () => {
            this.documentVisible = !document.hidden;
            this.updateActivity();
            if (!this.documentVisible) this.pointers.clear();
        };
        this.onMotion = () => {
            this.renderer?.setReducedMotion(this.motion.matches);
            if (this.motion.matches) {
                this.setRotation(false, { persist: false });
            } else if (this.rotationPreference !== false) {
                this.setRotation(true, { persist: false });
            }
            this.updateActivity();
        };
        this.onMediaActive = (event) => {
            this.mediaActive = event.detail?.media === '3d';
            this.updateActivity();
        };
        this.onFinishRequest = (event) => {
            if (this.renderer && ['black', 'white'].includes(event.detail?.finish)) {
                this.interact();
                this.renderer.setFinish(event.detail.finish);
                const button = this.element.querySelector(`[data-showroom-action="finish"][data-showroom-value="${event.detail.finish}"]`);
                if (button) this.selectButton(button);
            }
        };
        this.onPageHide = (event) => {
            if (!event.persisted) return this.destroy();
            this.documentVisible = false;
            this.updateActivity();
        };
        this.onPageShow = (event) => {
            if (!event.persisted) return;
            this.documentVisible = !document.hidden;
            this.updateActivity();
        };
        this.onPosterError = () => {
            const fallback = this.poster.dataset.fallbackSrc;
            if (fallback && this.poster.src !== fallback) this.poster.src = fallback;
        };

        this.canvas.addEventListener('pointerdown', this.onPointerDown);
        this.canvas.addEventListener('pointermove', this.onPointerMove);
        this.canvas.addEventListener('pointerup', this.onPointerEnd);
        this.canvas.addEventListener('pointercancel', this.onPointerEnd);
        this.canvas.addEventListener('wheel', this.onWheel, { passive: false });
        this.canvas.addEventListener('keydown', this.onKeyDown);
        this.element.addEventListener('click', this.onClick);
        this.element.addEventListener('input', this.onInput);
        this.element.addEventListener('product-media-active', this.onMediaActive);
        this.element.addEventListener('showroom-finish-request', this.onFinishRequest);
        this.poster.addEventListener('error', this.onPosterError, { once: true });
        document.addEventListener('visibilitychange', this.onVisibility);
        this.motion.addEventListener?.('change', this.onMotion);
        window.addEventListener('pagehide', this.onPageHide);
        window.addEventListener('pageshow', this.onPageShow);
    }

    interact() {
        this.interacted = true;
        this.element.dataset.showroomInteracted = 'true';
        this.setRotation(false, { explicit: true });
    }

    readRotationPreference() {
        try {
            const stored = sessionStorage.getItem(this.rotationStorageKey);
            return stored === 'on' ? true : stored === 'off' ? false : null;
        } catch {
            return null;
        }
    }

    setRotation(enabled, { explicit = false, persist = explicit } = {}) {
        const active = Boolean(enabled);
        if (persist) {
            this.rotationPreference = active;
            try { sessionStorage.setItem(this.rotationStorageKey, active ? 'on' : 'off'); } catch { /* unavailable */ }
        }
        this.renderer?.setAutoRotation(active, { overrideReducedMotion: explicit && this.motion.matches });
        const control = this.element.querySelector('[data-showroom-input="autoRotate"]');
        if (control) {
            control.checked = active;
            control.setAttribute('aria-label', active ? 'Stop automatic rotation' : 'Start automatic rotation');
        }
        this.setStatus(active ? 'Interactive 3D view · automatic rotation on' : 'Interactive 3D view · automatic rotation off');
        this.updateActivity();
    }

    reset() {
        this.renderer.resetView();
        this.element.querySelectorAll('[data-showroom-action][aria-pressed]').forEach((button) => {
            button.setAttribute('aria-pressed', button.dataset.showroomValue === 'black' || button.dataset.showroomValue === 'cool' ? 'true' : 'false');
        });
        this.element.querySelectorAll('[data-showroom-input]').forEach((control) => {
            if (control.type === 'checkbox') control.checked = false;
            else {
                control.value = control.dataset.defaultValue;
                const name = control.dataset.showroomInput;
                const output = this.element.querySelector(`[data-showroom-output="${name}"]`);
                if (output) output.textContent = `${control.value}${name === 'jaw' ? ' mm' : name === 'explode' ? '%' : name === 'powerMode' ? ' / 10' : '°'}`;
            }
        });
        this.syncAllControls();
        this.renderLabels([]);
        this.updateActivity();
    }

    syncPoseControls() {
        const state = this.renderer.snapshot().state;
        for (const name of ['lower', 'upper', 'tilt', 'roll', 'baseYaw', 'jaw', 'explode']) {
            const control = this.element.querySelector(`[data-showroom-input="${name}"]`);
            const output = this.element.querySelector(`[data-showroom-output="${name}"]`);
            if (!control || state[name] === undefined) continue;
            control.value = state[name];
            if (output) output.textContent = `${state[name]}${name === 'jaw' ? ' mm' : name === 'explode' ? '%' : '°'}`;
        }
    }

    syncInspectionControls() {
        const state = this.renderer.snapshot().state;
        const selection = this.element.querySelector('[data-showroom-input="selection"]');
        const isolate = this.element.querySelector('[data-showroom-input="isolate"]');
        const wires = this.element.querySelector('[data-showroom-input="wires"]');
        if (selection) selection.value = state.selection;
        if (isolate) isolate.checked = state.isolate;
        if (wires) wires.checked = state.wires;
        this.updatePartDescription(state.selection);
    }

    syncAllControls() {
        const state = this.renderer.snapshot().state;
        this.syncPoseControls();
        this.syncInspectionControls();
        for (const name of ['anatomy', 'wires']) {
            const control = this.element.querySelector(`[data-showroom-input="${name}"]`);
            if (control) control.checked = Boolean(state[name]);
        }
        const powerMode = this.element.querySelector('[data-showroom-input="powerMode"]');
        const powerModeOutput = this.element.querySelector('[data-showroom-output="powerMode"]');
        if (powerMode) powerMode.value = state.brightness;
        if (powerModeOutput) powerModeOutput.textContent = `${state.brightness} / 10`;
    }

    updatePartDescription(category) {
        const description = this.element.querySelector('[data-showroom-part-description]');
        if (description) description.textContent = category === 'all'
            ? 'Complete articulated lamp assembly.'
            : DESK_LAMP_GROUPS[category]?.description ?? 'Selected model group.';
    }

    selectButton(control) {
        if (!control.hasAttribute('aria-pressed')) return;
        this.element.querySelectorAll(`[data-showroom-action="${control.dataset.showroomAction}"]`)
            .forEach((button) => button.setAttribute('aria-pressed', button === control ? 'true' : 'false'));
    }

    selectedValue(action, fallback) {
        return this.element.querySelector(`[data-showroom-action="${action}"][aria-pressed="true"]`)?.dataset.showroomValue ?? fallback;
    }

    powerModeLevel() {
        return Number(this.element.querySelector('[data-showroom-input="powerMode"]')?.value ?? 10);
    }

    renderLabels(labels) {
        this.labels.replaceChildren(...labels.map((label) => {
            const element = document.createElement('button');
            element.type = 'button';
            element.className = 'showroom-part-label';
            element.textContent = label.label;
            element.dataset.showroomAction = 'inspect';
            element.dataset.showroomValue = label.category;
            element.setAttribute('aria-label', `Inspect ${label.label}`);
            element.style.left = `${label.x}%`;
            element.style.top = `${label.y}%`;
            return element;
        }));
    }

    updateActivity() {
        const active = this.visible && this.documentVisible && this.mediaActive;
        this.renderer?.setDocumentVisible(this.documentVisible);
        this.renderer?.setActive(active);
        const snapshot = this.renderer?.snapshot();
        this.element.dataset.showroomAnimation = active && Boolean(snapshot?.autoRotate)
            && (!this.motion.matches || Boolean(snapshot?.motionOverride)) ? 'running' : 'paused';
    }

    fail() {
        this.renderer?.destroy();
        this.renderer = null;
        this.element.dataset.showroomState = 'fallback';
        this.element.dataset.showroomAnimation = 'paused';
        this.setStatus('Product images available · 3D unavailable');
    }

    setStatus(message) {
        this.status.textContent = message;
    }

    destroy() {
        if (this.destroyed) return;
        this.destroyed = true;
        this.controller.abort();
        this.observer?.disconnect();
        this.renderer?.destroy();
        this.canvas.removeEventListener('pointerdown', this.onPointerDown);
        this.canvas.removeEventListener('pointermove', this.onPointerMove);
        this.canvas.removeEventListener('pointerup', this.onPointerEnd);
        this.canvas.removeEventListener('pointercancel', this.onPointerEnd);
        this.canvas.removeEventListener('wheel', this.onWheel);
        this.canvas.removeEventListener('keydown', this.onKeyDown);
        this.element.removeEventListener('click', this.onClick);
        this.element.removeEventListener('input', this.onInput);
        this.element.removeEventListener('product-media-active', this.onMediaActive);
        this.element.removeEventListener('showroom-finish-request', this.onFinishRequest);
        document.removeEventListener('visibilitychange', this.onVisibility);
        this.motion.removeEventListener?.('change', this.onMotion);
        window.removeEventListener('pagehide', this.onPageHide);
        window.removeEventListener('pageshow', this.onPageShow);
        this.pointers.clear();
        delete this.element.dataset.showroomInitialized;
    }
}

export function initializeProductShowrooms(root = document) {
    return [...root.querySelectorAll('[data-product-showroom][data-showroom-manifest]:not([data-showroom-initialized])')]
        .map((element) => {
            element.dataset.showroomInitialized = 'true';
            return new ProductShowroom(element).start();
        });
}
