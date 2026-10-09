import assert from 'node:assert/strict';
import { createHash } from 'node:crypto';
import { spawn } from 'node:child_process';
import { mkdir, mkdtemp, rm, writeFile } from 'node:fs/promises';
import os from 'node:os';
import path from 'node:path';
import { pathToFileURL } from 'node:url';

const baseUrl = process.argv[2];
const evidenceDirectory = path.resolve(process.argv[3] ?? '../deployment/evidence/ui-3d-step-3');
const sourceViewer = process.argv[4] ? path.resolve(process.argv[4]) : null;

if (!baseUrl) throw new Error('Usage: node tests/browser/verify-showroom.mjs <base-url> [evidence-directory] [source-viewer]');

const profile = await mkdtemp(path.join(os.tmpdir(), 'maat-showroom-chromium-'));
const port = 9334;
const browser = spawn('/usr/bin/chromium', [
    '--headless=new',
    '--no-sandbox',
    '--disable-dev-shm-usage',
    '--enable-unsafe-swiftshader',
    '--use-angle=swiftshader',
    '--allow-file-access-from-files',
    `--remote-debugging-port=${port}`,
    `--user-data-dir=${profile}`,
    'about:blank',
], { stdio: 'ignore' });

const delay = (milliseconds) => new Promise((resolve) => setTimeout(resolve, milliseconds));
const digest = (value) => createHash('sha256').update(value).digest('hex');

async function debuggerUrl() {
    for (let attempt = 0; attempt < 100; attempt += 1) {
        try {
            const pages = await fetch(`http://127.0.0.1:${port}/json/list`).then((response) => response.json());
            const page = pages.find((candidate) => candidate.type === 'page' && candidate.url === 'about:blank')
                ?? pages.find((candidate) => candidate.type === 'page' && !candidate.url.startsWith('chrome-extension://'));
            if (page?.webSocketDebuggerUrl) return page.webSocketDebuggerUrl;
        } catch {}
        await delay(100);
    }
    throw new Error('Chromium DevTools did not become available.');
}

class DevTools {
    constructor(url) {
        this.socket = new WebSocket(url);
        this.sequence = 0;
        this.pending = new Map();
        this.listeners = new Map();
    }

    async connect() {
        await new Promise((resolve, reject) => {
            this.socket.addEventListener('open', resolve, { once: true });
            this.socket.addEventListener('error', reject, { once: true });
        });
        this.socket.addEventListener('message', ({ data }) => {
            const message = JSON.parse(data);
            if (message.id) {
                const pending = this.pending.get(message.id);
                this.pending.delete(message.id);
                if (message.error) pending?.reject(new Error(message.error.message));
                else pending?.resolve(message.result);
                return;
            }
            for (const listener of this.listeners.get(message.method) ?? []) listener(message.params);
        });
    }

    send(method, params = {}) {
        const id = ++this.sequence;
        this.socket.send(JSON.stringify({ id, method, params }));
        return new Promise((resolve, reject) => this.pending.set(id, { resolve, reject }));
    }

    on(method, listener) {
        const listeners = this.listeners.get(method) ?? [];
        listeners.push(listener);
        this.listeners.set(method, listeners);
    }

    close() {
        this.socket.close();
    }
}

const client = new DevTools(await debuggerUrl());
await client.connect();
await Promise.all([
    client.send('Page.enable'),
    client.send('Runtime.enable'),
    client.send('Network.enable'),
]);

const transfers = new Map();
let phase = 'setup';
const browserErrors = [];
const browserExceptions = [];
const bfcacheExclusions = [];
const requests = new Map();
const failedRequests = [];
client.on('Runtime.exceptionThrown', ({ exceptionDetails }) => {
    const message = exceptionDetails.exception?.description ?? exceptionDetails.text;
    browserExceptions.push(message);
    browserErrors.push(message);
});
client.on('Page.backForwardCacheNotUsed', (details) => bfcacheExclusions.push(details));
client.on('Runtime.consoleAPICalled', ({ type, args }) => {
    if (type === 'error' || type === 'warning') browserErrors.push(args.map((argument) => argument.value ?? argument.description).join(' '));
});
client.on('Network.requestWillBeSent', ({ requestId, request }) => requests.set(requestId, { phase, url: request.url }));
client.on('Network.loadingFailed', ({ requestId, errorText, blockedReason, canceled }) => {
    failedRequests.push({ ...(requests.get(requestId) ?? { phase, url: null }), errorText, blockedReason: blockedReason ?? null, canceled });
});
client.on('Network.responseReceived', ({ requestId, response, type }) => {
    if (type === 'Document' || /\.(?:json|bin|bin\.gz)(?:\?|$)/.test(response.url)) {
        transfers.set(requestId, {
            phase,
            type,
            url: response.url,
            status: response.status,
            fromDiskCache: response.fromDiskCache,
            fromServiceWorker: response.fromServiceWorker,
            mimeType: response.mimeType,
            contentEncoding: response.headers['content-encoding'] ?? response.headers['Content-Encoding'] ?? null,
            contentLength: response.headers['content-length'] ?? response.headers['Content-Length'] ?? null,
            cacheControl: response.headers['cache-control'] ?? response.headers['Cache-Control'] ?? null,
            vary: response.headers.vary ?? response.headers.Vary ?? null,
        });
    }
});
client.on('Network.loadingFinished', ({ requestId, encodedDataLength }) => {
    if (transfers.has(requestId)) transfers.get(requestId).encodedDataLength = encodedDataLength;
});

async function evaluate(expression) {
    const result = await client.send('Runtime.evaluate', { expression, awaitPromise: true, returnByValue: true });
    if (result.exceptionDetails) {
        throw new Error(result.exceptionDetails.exception?.description ?? result.exceptionDetails.text);
    }
    return result.result.value;
}

async function navigate(url) {
    await client.send('Page.navigate', { url });
    for (let attempt = 0; attempt < 600; attempt += 1) {
        if (await evaluate('document.readyState === "complete"')) return;
        await delay(100);
    }
    throw new Error(`Page did not load: ${url}`);
}

async function waitFor(expression, message) {
    for (let attempt = 0; attempt < 300; attempt += 1) {
        if (await evaluate(expression)) return;
        await delay(100);
    }
    const diagnostics = await evaluate(`({
        url: location.href,
        title: document.title,
        state: document.querySelector('[data-product-showroom]')?.dataset.showroomState,
        status: document.querySelector('[data-showroom-status]')?.textContent,
        anatomyChecked: document.querySelector('[data-showroom-input="anatomy"]')?.checked,
        explodeValue: document.querySelector('[data-showroom-input="explode"]')?.value,
        labelCount: document.querySelectorAll('.showroom-part-label').length,
        scripts: [...document.scripts].map((script) => script.src).filter(Boolean),
    })`);
    throw new Error(`${message} ${JSON.stringify(diagnostics)} Browser errors: ${browserErrors.join(' | ')}`);
}

async function screenshot(name) {
    const { data } = await client.send('Page.captureScreenshot', { format: 'png', captureBeyondViewport: false });
    await writeFile(path.join(evidenceDirectory, name), Buffer.from(data, 'base64'));
}

async function selectorScreenshot(selector, name = null) {
    const clip = await evaluate(`(() => {
        const rect = document.querySelector(${JSON.stringify(selector)}).getBoundingClientRect();
        return { x: rect.left + scrollX, y: rect.top + scrollY, width: rect.width, height: rect.height, scale: 1 };
    })()`);
    const { data } = await client.send('Page.captureScreenshot', { format: 'png', clip, captureBeyondViewport: true });
    if (name) await writeFile(path.join(evidenceDirectory, name), Buffer.from(data, 'base64'));

    return data;
}

async function dispatchTab(shift = false) {
    await client.send('Input.dispatchKeyEvent', { type: 'keyDown', key: 'Tab', code: 'Tab', windowsVirtualKeyCode: 9, modifiers: shift ? 8 : 0 });
    await client.send('Input.dispatchKeyEvent', { type: 'keyUp', key: 'Tab', code: 'Tab', windowsVirtualKeyCode: 9, modifiers: shift ? 8 : 0 });
}

async function tabTo(selector, maximum = 30) {
    await evaluate('document.activeElement?.blur()');
    for (let index = 0; index < maximum; index += 1) {
        await dispatchTab();
        if (await evaluate(`document.activeElement?.matches(${JSON.stringify(selector)})`)) return;
    }
    throw new Error(`Keyboard focus did not reach ${selector}.`);
}

async function tabOutOf(selector, { reverse = false, maximum = 40 } = {}) {
    for (let index = 0; index < maximum; index += 1) {
        await dispatchTab(reverse);
        if (await evaluate(`!document.activeElement?.closest(${JSON.stringify(selector)})`)) return;
    }
    throw new Error(`Keyboard focus did not leave ${selector}.`);
}

const phaseTransfers = (name) => [...transfers.values()].filter((transfer) => transfer.phase === name);
let checkoutFlow = null;
let gallery = null;
let navigationLifecycle = null;
let contextLoss = null;
let bfcache = null;

try {
    await mkdir(evidenceDirectory, { recursive: true });
    await client.send('Page.addScriptToEvaluateOnNewDocument', { source: `(() => {
        window.__maatLifecycleEvidence = { pageShows: [], pageHides: [], outstandingAnimationFrames: 0 };
        const nativeRequestAnimationFrame = window.requestAnimationFrame.bind(window);
        const nativeCancelAnimationFrame = window.cancelAnimationFrame.bind(window);
        const outstanding = new Set();
        window.requestAnimationFrame = (callback) => {
            let id = 0;
            id = nativeRequestAnimationFrame((time) => {
                outstanding.delete(id);
                window.__maatLifecycleEvidence.outstandingAnimationFrames = outstanding.size;
                callback(time);
            });
            outstanding.add(id);
            window.__maatLifecycleEvidence.outstandingAnimationFrames = outstanding.size;
            return id;
        };
        window.cancelAnimationFrame = (id) => {
            outstanding.delete(id);
            window.__maatLifecycleEvidence.outstandingAnimationFrames = outstanding.size;
            nativeCancelAnimationFrame(id);
        };
        addEventListener('pageshow', (event) => window.__maatLifecycleEvidence.pageShows.push({ persisted: event.persisted, at: performance.now() }));
        addEventListener('pagehide', (event) => window.__maatLifecycleEvidence.pageHides.push({ persisted: event.persisted, at: performance.now() }));
    })();` });
    await client.send('Network.setCacheDisabled', { cacheDisabled: true });
    await client.send('Emulation.setDeviceMetricsOverride', { width: 1440, height: 1100, deviceScaleFactor: 1, mobile: false });
    phase = 'cold-desktop';
    const coldStarted = Date.now();
    await navigate(`${baseUrl}/products/series-x-articulated-lamp`);
    await waitFor('!["loading", undefined].includes(document.querySelector("[data-product-showroom]")?.dataset.showroomState)', 'Desktop showroom did not settle.');
    const desktopState = await evaluate('document.querySelector("[data-product-showroom]")?.dataset.showroomState');
    assert.equal(desktopState, 'ready', `Desktop showroom state was ${desktopState}; browser errors: ${browserErrors.join(' | ')}`);
    await tabTo('[data-showroom-canvas]');

    const desktop = await evaluate(`(() => {
        const root = document.querySelector('[data-product-showroom]');
        const canvas = root.querySelector('canvas');
        const cart = [...document.querySelectorAll('button')].find((button) => button.textContent.includes('Add to Cart'));
        const focusedStyle = getComputedStyle(canvas);
        return {
            state: root.dataset.showroomState,
            animation: root.dataset.showroomAnimation,
            controlsEnabled: [...root.querySelectorAll('[data-showroom-controls]')].every((control) => !control.disabled),
            focusVisible: focusedStyle.boxShadow !== 'none' || focusedStyle.outlineStyle !== 'none',
            webgl: Boolean(canvas.getContext('webgl')),
            prefersReducedMotion: matchMedia('(prefers-reduced-motion: reduce)').matches,
            documentVisibility: document.visibilityState,
            cartHeight: cart.getBoundingClientRect().height,
            quantityLabelled: Boolean(document.querySelector('label input[name="quantity"]')),
        };
    })()`);
    assert.equal(desktop.state, 'ready');
    assert.equal(desktop.animation, 'running');
    assert.equal(desktop.controlsEnabled, true);
    assert.equal(desktop.focusVisible, true);
    assert.equal(desktop.webgl, true);
    assert.equal(desktop.prefersReducedMotion, false);
    assert.equal(desktop.documentVisibility, 'visible');
    assert.ok(desktop.cartHeight >= 44);
    assert.equal(desktop.quantityLabelled, true);
    desktop.modelReadyMs = Date.now() - coldStarted;
    await waitFor('getComputedStyle(document.querySelector("[data-showroom-poster]")).opacity === "0"', 'Poster did not finish fading after the first model frame.');
    desktop.posterHiddenAfterReady = await evaluate('getComputedStyle(document.querySelector("[data-showroom-poster]")).opacity === "0"');
    assert.equal(desktop.posterHiddenAfterReady, true);
    await screenshot('showroom-desktop-default.png');

    gallery = await evaluate(`(async () => {
        const root = document.querySelector('[data-product-media-gallery]');
        const showroom = document.querySelector('[data-product-showroom]');
        const select = async (name) => {
            root.querySelector('[data-media-select="' + name + '"]').click();
            await new Promise((resolve) => requestAnimationFrame(() => requestAnimationFrame(resolve)));
        };
        const controlsHidden = () => [...root.querySelectorAll('[data-showroom-only]')].every((control) => control.hidden);
        await select('photo-0');
        const photo = {
            active: root.dataset.activeMedia,
            controlsHidden: controlsHidden(),
            animation: showroom.dataset.showroomAnimation,
            loaded: Boolean(root.querySelector('[data-media-panel="photo-0"] img').src),
        };
        const videoButton = root.querySelector('[data-media-select="video"]');
        let videoState = null;
        if (videoButton) {
            await select('video');
            const video = root.querySelector('[data-media-video]');
            videoState = {
                active: root.dataset.activeMedia,
                controlsHidden: controlsHidden(),
                controls: video.controls,
                autoplay: video.autoplay,
                loaded: Boolean(video.src),
            };
        }
        await select('3d');
        const model = {
            active: root.dataset.activeMedia,
            controlsVisible: [...root.querySelectorAll('[data-showroom-only]')].every((control) => !control.hidden),
            animation: showroom.dataset.showroomAnimation,
        };
        const white = document.querySelector('[data-purchase-variant][value="white"]');
        return {
            tabs: root.querySelectorAll('[data-media-select]').length,
            panels: root.querySelectorAll('[data-media-panel]').length,
            photo,
            video: videoState,
            model,
            selectedColor: document.querySelector('[data-selected-color]').textContent.trim(),
            selectedVariant: document.querySelector('[data-purchase-variant]:checked').value,
            whiteDisabled: white?.disabled ?? null,
        };
    })()`);
    assert.deepEqual(gallery, {
        tabs: 2,
        panels: 1,
        photo: { active: 'photo-0', controlsHidden: true, animation: 'paused', loaded: true },
        video: null,
        model: { active: '3d', controlsVisible: true, animation: 'running' },
        selectedColor: 'Black',
        selectedVariant: 'black',
        whiteDisabled: true,
    });
    await screenshot('showroom-desktop-gallery-and-purchase.png');

    const contextExtensionAvailable = await evaluate(`(() => {
        const canvas = document.querySelector('[data-showroom-canvas]');
        const extension = canvas.getContext('webgl')?.getExtension('WEBGL_lose_context');
        if (!extension) return false;
        window.__maatContextLossExtension = extension;
        extension.loseContext();
        return true;
    })()`);
    if (contextExtensionAvailable) {
        await waitFor('document.querySelector("[data-product-showroom]")?.dataset.showroomState === "context-lost"', 'The viewer did not expose a context-loss fallback.');
        await waitFor('Number(getComputedStyle(document.querySelector("[data-showroom-poster]")).opacity) > 0.9', 'The context-loss poster fallback did not become visible.');
        const fallback = await evaluate(`(() => ({
            canvasCount: document.querySelectorAll('[data-showroom-canvas]').length,
            posterVisible: Number(getComputedStyle(document.querySelector('[data-showroom-poster]')).opacity) > 0,
            retryVisible: getComputedStyle(document.querySelector('[data-showroom-action="retry"]')).display !== 'none',
            status: document.querySelector('[data-showroom-status]').textContent.trim(),
        }))()`);
        assert.equal(fallback.canvasCount, 1);
        assert.equal(fallback.posterVisible, true);
        assert.equal(fallback.retryVisible, true);
        assert.match(fallback.status, /waiting for browser recovery/i);
        await evaluate('window.__maatContextLossExtension.restoreContext()');
        await waitFor('document.querySelector("[data-product-showroom]")?.dataset.showroomState === "ready"', 'The viewer did not recover after restoring its WebGL context.');
        await delay(150);
        const restored = await evaluate(`(() => ({
            canvasCount: document.querySelectorAll('[data-showroom-canvas]').length,
            controlsEnabled: [...document.querySelectorAll('[data-showroom-controls]')].every((control) => !control.disabled),
            status: document.querySelector('[data-showroom-status]').textContent.trim(),
        }))()`);
        assert.deepEqual(restored, {
            canvasCount: 1,
            controlsEnabled: true,
            status: 'Interactive 3D view restored',
        });
        assert.equal(browserExceptions.length, 0, `Uncaught browser failures during context recovery: ${browserExceptions.join(' | ')}`);
        contextLoss = { supported: true, fallback, restored, uncaughtExceptions: [] };
    } else {
        contextLoss = { supported: false, skipped: true, reason: 'WEBGL_lose_context is unavailable in this browser/graphics backend.' };
    }

    const effectHashes = {};
    const resetForEffect = async () => {
        await evaluate('document.querySelector(\'[data-showroom-action="reset"]\').click()');
        await delay(100);
    };
    const captureEffect = async (name, action, evidenceName = null) => {
        await resetForEffect();
        if (action) await evaluate(action);
        await delay(120);
        const image = await selectorScreenshot('[data-showroom-canvas]', evidenceName);
        effectHashes[name] = digest(image);
        return effectHashes[name];
    };
    const baselineEffect = await captureEffect('baseline', null);
    const changedEffects = [
        ['finishWhite', `document.querySelector('[data-showroom-action="finish"][data-showroom-value="white"]').click()`],
        ['lightOff', `document.querySelector('[data-showroom-action="light"][data-showroom-value="off"]').click()`],
        ['powerModeOne', `(() => { const input = document.querySelector('[data-showroom-input="powerMode"]'); input.value = 1; input.dispatchEvent(new Event('input', { bubbles: true })); })()`, 'showroom-power-mode-1.png'],
        ['cameraFront', `document.querySelector('[data-showroom-action="camera"][data-showroom-value="front"]').click()`],
        ['zoomIn', `document.querySelector('[data-showroom-action="zoom"][data-showroom-value="0.84"]').click()`],
        ['poseReach', `document.querySelector('[data-showroom-action="preset"][data-showroom-value="reach"]').click()`],
        ['poseTall', `document.querySelector('[data-showroom-action="preset"][data-showroom-value="tall"]').click()`],
        ['poseLow', `document.querySelector('[data-showroom-action="preset"][data-showroom-value="low"]').click()`],
        ['poseWide', `document.querySelector('[data-showroom-action="preset"][data-showroom-value="wide"]').click()`],
        ['poseFolded', `document.querySelector('[data-showroom-action="preset"][data-showroom-value="folded"]').click()`],
        ['lowerJoint', `(() => { const input = document.querySelector('[data-showroom-input="lower"]'); input.value = 70; input.dispatchEvent(new Event('input', { bubbles: true })); })()`],
        ['upperJoint', `(() => { const input = document.querySelector('[data-showroom-input="upper"]'); input.value = -20; input.dispatchEvent(new Event('input', { bubbles: true })); })()`],
        ['neckJoint', `(() => { const input = document.querySelector('[data-showroom-input="tilt"]'); input.value = 30; input.dispatchEvent(new Event('input', { bubbles: true })); })()`],
        ['headJoint', `(() => { const input = document.querySelector('[data-showroom-input="roll"]'); input.value = 20; input.dispatchEvent(new Event('input', { bubbles: true })); })()`],
        ['baseJoint', `(() => { const input = document.querySelector('[data-showroom-input="baseYaw"]'); input.value = 35; input.dispatchEvent(new Event('input', { bubbles: true })); })()`],
        ['clampJoint', `(() => { const input = document.querySelector('[data-showroom-input="jaw"]'); input.value = 45; input.dispatchEvent(new Event('input', { bubbles: true })); })()`],
        ['exploded', `document.querySelector('[data-showroom-action="explodeToggle"]').click()`],
        ['anatomy', `(() => { const input = document.querySelector('[data-showroom-input="anatomy"]'); input.checked = true; input.dispatchEvent(new Event('input', { bubbles: true })); })()`],
        ['partHighlight', `(() => { const input = document.querySelector('[data-showroom-input="selection"]'); input.value = 'head'; input.dispatchEvent(new Event('input', { bubbles: true })); })()`],
        ['partIsolate', `(() => { const selection = document.querySelector('[data-showroom-input="selection"]'); selection.value = 'head'; selection.dispatchEvent(new Event('input', { bubbles: true })); const isolate = document.querySelector('[data-showroom-input="isolate"]'); isolate.checked = true; isolate.dispatchEvent(new Event('input', { bubbles: true })); })()`],
        ['partFocus', `(() => { const selection = document.querySelector('[data-showroom-input="selection"]'); selection.value = 'controller'; selection.dispatchEvent(new Event('input', { bubbles: true })); document.querySelector('[data-showroom-action="focus"]').click(); })()`],
        ['wireInspection', `(() => { const input = document.querySelector('[data-showroom-input="wires"]'); input.checked = true; input.dispatchEvent(new Event('input', { bubbles: true })); })()`],
    ];
    for (const [name, action, evidenceName] of changedEffects) {
        assert.notEqual(await captureEffect(name, action, evidenceName), baselineEffect, `${name} did not visibly change the integrated renderer.`);
    }
    await resetForEffect();
    await evaluate(`(() => {
        const powerMode = document.querySelector('[data-showroom-input="powerMode"]');
        powerMode.value = 2;
        powerMode.dispatchEvent(new Event('input', { bubbles: true }));
    })()`);
    assert.equal(await evaluate('document.querySelector(\'[data-showroom-output="powerMode"]\').textContent.trim()'), '2 / 10');
    await resetForEffect();
    await evaluate(`(() => {
        const selection = document.querySelector('[data-showroom-input="selection"]');
        selection.value = 'cable';
        selection.dispatchEvent(new Event('input', { bubbles: true }));
    })()`);
    assert.equal(await evaluate('document.querySelector(\'[data-showroom-input="wires"]\').checked'), true);
    await evaluate('document.querySelector(\'[data-showroom-action="preset"][data-showroom-value="reach"]\').click()');
    assert.deepEqual(await evaluate(`(() => ({
        selection: document.querySelector('[data-showroom-input="selection"]').value,
        isolate: document.querySelector('[data-showroom-input="isolate"]').checked,
        explode: document.querySelector('[data-showroom-input="explode"]').value,
    }))()`), { selection: 'all', isolate: false, explode: '0' });
    await resetForEffect();

    const keyboard = await evaluate(`(() => {
        const root = document.querySelector('[data-product-showroom]');
        const canvas = root.querySelector('canvas');
        canvas.focus();
        canvas.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowRight', bubbles: true }));
        return { interacted: root.dataset.showroomInteracted, animation: root.dataset.showroomAnimation };
    })()`);
    assert.deepEqual(keyboard, { interacted: 'true', animation: 'paused' });
    for (let index = 0; index < 12; index += 1) {
        await client.send('Input.dispatchKeyEvent', { type: 'keyDown', key: 'ArrowRight', code: 'ArrowRight', windowsVirtualKeyCode: 39 });
        await client.send('Input.dispatchKeyEvent', { type: 'keyUp', key: 'ArrowRight', code: 'ArrowRight', windowsVirtualKeyCode: 39 });
    }
    await delay(150);
    await screenshot('showroom-desktop-black-angle.png');

    const interaction = await evaluate(`(async () => {
        const root = document.querySelector('[data-product-showroom]');
        root.querySelector('[data-showroom-value="white"]').click();
        root.querySelector('[data-showroom-value="warm"]').click();
        root.querySelector('details').open = true;
        const change = (name, value) => {
            const input = root.querySelector('[data-showroom-input="' + name + '"]');
            input.value = value;
            input.dispatchEvent(new Event('input', { bubbles: true }));
        };
        change('lower', 77);
        change('upper', 10);
        change('jaw', 42);
        change('explode', 70);
        const anatomy = root.querySelector('[data-showroom-input="anatomy"]');
        anatomy.checked = true;
        anatomy.dispatchEvent(new Event('input', { bubbles: true }));
        await new Promise((resolve) => setTimeout(resolve, 300));
        return {
            interacted: root.dataset.showroomInteracted,
            animation: root.dataset.showroomAnimation,
            white: root.querySelector('[data-showroom-value="white"]').getAttribute('aria-pressed'),
            warm: root.querySelector('[data-showroom-value="warm"]').getAttribute('aria-pressed'),
        };
    })()`);
    assert.deepEqual(interaction, { interacted: 'true', animation: 'paused', white: 'true', warm: 'true' });
    await waitFor('document.querySelectorAll(".showroom-part-label").length === 6', 'Normal presentation labels did not match the lamp-only assembly.');
    await screenshot('showroom-desktop-engineering.png');
    await evaluate(`(() => {
        const root = document.querySelector('[data-product-showroom]');
        for (const [name, value] of [['explode', 0]]) {
            const input = root.querySelector('[data-showroom-input="' + name + '"]');
            input.value = value;
            input.dispatchEvent(new Event('input', { bubbles: true }));
        }
        const anatomy = root.querySelector('[data-showroom-input="anatomy"]');
        anatomy.checked = false;
        anatomy.dispatchEvent(new Event('input', { bubbles: true }));
        root.querySelector('[data-showroom-action="camera"][data-showroom-value="front"]').click();
    })()`);
    await delay(150);
    await screenshot('showroom-desktop-white-front.png');

    const parity = await evaluate(`(async () => {
        const root = document.querySelector('[data-product-showroom]');
        const click = (action, value) => root.querySelector('[data-showroom-action="' + action + '"]' + (value ? '[data-showroom-value="' + value + '"]' : '')).click();
        for (const mode of ['cool', 'neutral', 'off', 'warm']) click('light', mode);
        for (const view of ['front', 'perspective']) click('camera', view);
        click('fit');
        for (const group of ['controller', 'clamp', 'head']) click('inspect', group);
        for (const preset of ['study', 'reach', 'tall', 'low', 'wide', 'folded']) click('preset', preset);
        click('resetPose');
        const input = (name, value, checked = null) => {
            const control = root.querySelector('[data-showroom-input="' + name + '"]');
            if (checked === null) control.value = value;
            else control.checked = checked;
            control.dispatchEvent(new Event('input', { bubbles: true }));
        };
        input('powerMode', 3);
        click('resetPose');
        input('selection', 'cable');
        input('isolate', '', true);
        click('focus');
        input('isolate', '', false);
        input('anatomy', '', true);
        input('wires', '', true);
        await new Promise((resolve) => setTimeout(resolve, 250));
        return {
            cameras: root.querySelectorAll('[data-showroom-action="camera"]').length,
            closeups: root.querySelectorAll('.showroom-choice[data-showroom-action="inspect"]').length,
            presets: root.querySelectorAll('[data-showroom-action="preset"]').length,
            articulation: ['lower', 'upper', 'tilt', 'roll', 'baseYaw', 'jaw'].every((name) => root.querySelector('[data-showroom-input="' + name + '"]')),
            selectionOptions: root.querySelector('[data-showroom-input="selection"]').options.length,
            powerMode: root.querySelector('[data-showroom-output="powerMode"]').textContent.trim(),
            wires: root.querySelector('[data-showroom-input="wires"]').checked,
            labels: root.querySelectorAll('.showroom-part-label').length,
            description: root.querySelector('[data-showroom-part-description]').textContent,
        };
    })()`);
    assert.deepEqual(parity, {
        cameras: 2,
        closeups: 3,
        presets: 6,
        articulation: true,
        selectionOptions: 10,
        powerMode: '3 / 10',
        wires: true,
        labels: 9,
        description: 'Base-to-controller and controller-to-USB leads; arm and head wiring remain concealed.',
    });
    await screenshot('showroom-desktop-wire-inspection.png');

    await evaluate(`document.querySelector('[data-showroom-action="separateClamp"]').click()`);
    await delay(200);
    await screenshot('showroom-desktop-clamp-separated.png');

    await evaluate(`(() => {
        document.querySelector('[data-showroom-action="resetPose"]').click();
        document.querySelector('[data-showroom-action="explodeToggle"]').click();
    })()`);
    await delay(200);
    await screenshot('showroom-desktop-exploded.png');

    const reset = await evaluate(`(() => {
        const root = document.querySelector('[data-product-showroom]');
        root.querySelector('[data-showroom-action="reset"]').click();
        return {
            lower: root.querySelector('[data-showroom-input="lower"]').value,
            jaw: root.querySelector('[data-showroom-input="jaw"]').value,
            explode: root.querySelector('[data-showroom-input="explode"]').value,
            anatomy: root.querySelector('[data-showroom-input="anatomy"]').checked,
            black: root.querySelector('[data-showroom-value="black"]').getAttribute('aria-pressed'),
            cool: root.querySelector('[data-showroom-value="cool"]').getAttribute('aria-pressed'),
            powerMode: root.querySelector('[data-showroom-output="powerMode"]').textContent.trim(),
            wires: root.querySelector('[data-showroom-input="wires"]').checked,
            selection: root.querySelector('[data-showroom-input="selection"]').value,
        };
    })()`);
    assert.deepEqual(reset, {
        lower: '118',
        jaw: '25.1',
        explode: '0',
        anatomy: false,
        black: 'true',
        cool: 'true',
        powerMode: '10 / 10',
        wires: false,
        selection: 'all',
    });

    await client.send('Emulation.setDeviceMetricsOverride', { width: 390, height: 844, deviceScaleFactor: 1, mobile: false });
    await client.send('Emulation.setEmulatedMedia', { features: [{ name: 'prefers-reduced-motion', value: 'reduce' }] });
    phase = 'mobile-reduced-motion';
    await navigate(`${baseUrl}/products/series-x-articulated-lamp`);
    await waitFor('!["loading", undefined].includes(document.querySelector("[data-product-showroom]")?.dataset.showroomState)', 'Mobile showroom did not settle.');
    assert.equal(await evaluate('document.querySelector("[data-product-showroom]")?.dataset.showroomState'), 'ready', `Mobile showroom failed; browser errors: ${browserErrors.join(' | ')}`);
    const mobile = await evaluate(`(() => {
        const root = document.querySelector('[data-product-showroom]');
        const canvas = root.querySelector('canvas');
        const rect = canvas.getBoundingClientRect();
        return {
            animation: root.dataset.showroomAnimation,
            touchAction: getComputedStyle(canvas).touchAction,
            overflow: document.documentElement.scrollHeight > innerHeight,
            horizontalOverflow: document.documentElement.scrollWidth > innerWidth,
            viewport: { innerWidth, innerHeight, visualWidth: visualViewport.width, visualHeight: visualViewport.height },
            overflowElements: [...document.querySelectorAll('body *')].map((element) => {
                const rect = element.getBoundingClientRect();
                return { tag: element.tagName, classes: element.className?.toString?.() ?? '', left: rect.left, right: rect.right, width: rect.width };
            }).filter((item) => item.left < -0.5 || item.right > innerWidth + 0.5).slice(0, 12),
            stickyHeight: document.querySelector('[aria-label="Mobile purchase action"]')?.getBoundingClientRect().height,
            stickyForm: document.querySelector('[aria-label="Mobile purchase action"] button')?.getAttribute('form'),
            canvas: { x: rect.left + rect.width / 2, y: Math.min(innerHeight - 20, rect.top + rect.height / 2) },
        };
    })()`);
    assert.equal(mobile.animation, 'paused');
    assert.equal(mobile.touchAction, 'pan-y');
    assert.equal(mobile.overflow, true);
    assert.equal(mobile.horizontalOverflow, false, JSON.stringify(mobile));
    assert.ok(mobile.stickyHeight >= 44);
    assert.equal(mobile.stickyForm, 'product-purchase-form');
    await screenshot('showroom-mobile-reduced-motion.png');

    await evaluate('window.scrollTo(0, 0); document.activeElement?.blur()');
    await client.send('Input.dispatchMouseEvent', { type: 'mouseWheel', x: mobile.canvas.x, y: mobile.canvas.y, deltaX: 0, deltaY: 500 });
    await delay(250);
    assert.ok(await evaluate('window.scrollY > 0'), 'Unfocused viewer trapped normal page scrolling.');
    await evaluate('window.scrollTo(0, document.documentElement.scrollHeight)');
    await delay(250);
    assert.equal(await evaluate('document.querySelector("[data-product-showroom]").dataset.showroomAnimation'), 'paused');

    phase = 'compressed-blocked-raw-recovery';
    await client.send('Network.setBlockedURLs', { urls: ['*desk-lamp.3d8ef3d83ad3e741.bin.gz*'] });
    await navigate(`${baseUrl}/products/series-x-articulated-lamp?failure-check=1`);
    await waitFor('document.querySelector("[data-product-showroom]")?.dataset.showroomState === "ready"', 'Raw model fallback did not recover after compressed asset failure.');
    assert.ok(phaseTransfers('compressed-blocked-raw-recovery').some((transfer) => transfer.url.includes('.bin') && !transfer.url.includes('.bin.gz')));

    phase = 'all-models-blocked';
    await client.send('Network.setBlockedURLs', { urls: ['*desk-lamp.3d8ef3d83ad3e741.bin.gz*', '*desk-lamp.a45a18eea9197981.bin*'] });
    await navigate(`${baseUrl}/products/series-x-articulated-lamp?failure-check=2`);
    await waitFor('document.querySelector("[data-product-showroom]")?.dataset.showroomState === "fallback"', 'Blocked model did not show the poster fallback.');
    assert.equal(await evaluate('getComputedStyle(document.querySelector("[data-showroom-poster]")).display !== "none"'), true);
    await screenshot('showroom-mobile-loading-failure.png');
    await client.send('Network.setBlockedURLs', { urls: [] });

    await client.send('Emulation.setDeviceMetricsOverride', { width: 1440, height: 1100, deviceScaleFactor: 1, mobile: false });
    await client.send('Emulation.setEmulatedMedia', { features: [{ name: 'prefers-reduced-motion', value: 'no-preference' }] });
    await client.send('Network.emulateNetworkConditions', {
        offline: false,
        latency: 150,
        downloadThroughput: 500000,
        uploadThroughput: 187500,
        connectionType: 'cellular4g',
    });
    phase = 'throttled-4mbps-150ms';
    const throttledStarted = Date.now();
    await navigate(`${baseUrl}/products/series-x-articulated-lamp?throttled-check=1`);
    assert.equal(await evaluate('getComputedStyle(document.querySelector("[data-showroom-poster]")).opacity'), '1');
    await waitFor('document.querySelector("[data-product-showroom]")?.dataset.showroomState === "ready"', 'Throttled showroom did not become usable.');
    const throttledReadyMs = Date.now() - throttledStarted;
    await client.send('Network.emulateNetworkConditions', { offline: false, latency: 0, downloadThroughput: -1, uploadThroughput: -1 });

    await client.send('Network.setCacheDisabled', { cacheDisabled: false });
    phase = 'warm-prime';
    await navigate(`${baseUrl}/products/series-x-articulated-lamp?cache-check=1`);
    await waitFor('document.querySelector("[data-product-showroom]")?.dataset.showroomState === "ready"', 'Cache priming load failed.');
    phase = 'warm-reload';
    const warmStarted = Date.now();
    await navigate(`${baseUrl}/products/series-x-articulated-lamp?cache-check=1`);
    await waitFor('document.querySelector("[data-product-showroom]")?.dataset.showroomState === "ready"', 'Warm showroom reload failed.');
    const warmReadyMs = Date.now() - warmStarted;

    phase = 'homepage-desktop';
    await client.send('Emulation.setDeviceMetricsOverride', { width: 1440, height: 1100, deviceScaleFactor: 1, mobile: false });
    await navigate(`${baseUrl}/`);
    const slideshow = await evaluate(`(() => {
        const root = document.querySelector('[data-storefront-slideshow]');
        const active = root?.querySelector('[data-slideshow-slide][data-active="true"]');
        const image = active?.querySelector('img');
        return {
            slides: root?.querySelectorAll('[data-slideshow-slide]').length,
            dots: root?.querySelectorAll('[data-slideshow-dot]').length,
            interval: root?.dataset.slideshowInterval,
            animation: root?.dataset.slideshowAnimation,
            index: root?.dataset.slideshowIndex,
            destination: active?.querySelector('a')?.pathname,
            objectFit: image ? getComputedStyle(image).objectFit : null,
            pausePlayControls: root?.querySelectorAll('[data-slideshow-pause], [data-slideshow-play]').length,
            aspectRatio: root ? Number((root.querySelector('.aspect-video').getBoundingClientRect().width / root.querySelector('.aspect-video').getBoundingClientRect().height).toFixed(4)) : null,
            allImagesContained: root ? [...root.querySelectorAll('img')].every((candidate) => getComputedStyle(candidate).objectFit === 'contain') : false,
        };
    })()`);
    assert.deepEqual(slideshow, {
        slides: 9,
        dots: 9,
        interval: '5000',
        animation: 'running',
        index: '0',
        destination: '/products/series-x-articulated-lamp',
        objectFit: 'contain',
        pausePlayControls: 0,
        aspectRatio: 1.7778,
        allImagesContained: true,
    });
    await selectorScreenshot('[data-storefront-slideshow]', 'homepage-desktop-slideshow-landscape.png');
    await tabTo('[data-slideshow-next]');
    await delay(50);
    assert.equal(await evaluate('document.activeElement === document.querySelector("[data-slideshow-next]")'), true);
    assert.equal(await evaluate('document.querySelector("[data-storefront-slideshow]").dataset.slideshowAnimation'), 'paused');
    await delay(600);
    assert.equal(await evaluate('document.querySelector("[data-storefront-slideshow]").dataset.slideshowIndex'), '0');
    await tabOutOf('[data-storefront-slideshow]', { reverse: true });
    assert.equal(await evaluate('document.querySelector("[data-storefront-slideshow]").dataset.slideshowAnimation'), 'running');
    await delay(1000);
    assert.equal(await evaluate('document.querySelector("[data-storefront-slideshow]").dataset.slideshowIndex'), '0');
    await delay(4300);
    await waitFor('document.querySelector("[data-storefront-slideshow]").dataset.slideshowIndex === "1"', 'Homepage slideshow did not advance after five seconds.');
    await evaluate('document.querySelector(\'[data-slideshow-dot="6"]\').click()');
    assert.equal(await evaluate('document.querySelector("[data-storefront-slideshow]").dataset.slideshowIndex'), '6');
    await waitFor('document.querySelector(\'[data-slideshow-slide][data-active="true"] img\')?.complete && document.querySelector(\'[data-slideshow-slide][data-active="true"] img\')?.naturalWidth > 0', 'Portrait slideshow image did not load.');
    assert.equal(await evaluate('getComputedStyle(document.querySelector(\'[data-slideshow-slide][data-active="true"]\')).visibility'), 'visible');
    await selectorScreenshot('[data-storefront-slideshow]', 'homepage-desktop-slideshow-portrait.png');
    const beforePrevious = Number(await evaluate('document.querySelector("[data-storefront-slideshow]").dataset.slideshowIndex'));
    await evaluate('document.querySelector("[data-slideshow-previous]").click()');
    assert.equal(Number(await evaluate('document.querySelector("[data-storefront-slideshow]").dataset.slideshowIndex')), (beforePrevious + 8) % 9);
    await evaluate('document.querySelector(\'[data-slideshow-dot="0"]\').click()');
    await waitFor('document.querySelector("[data-product-showcase]")?.dataset.showcaseState === "ready"', 'Homepage showcase did not become ready.');
    const homepage = await evaluate(`(() => {
        const root = document.querySelector('[data-product-showcase]');
        const canvas = root.querySelector('canvas');
        const link = root.querySelector('[data-showcase-link]');
        const toggle = root.querySelector('[data-showcase-rotation-toggle]');
        return {
            destination: link?.pathname,
            pointerEvents: getComputedStyle(canvas).pointerEvents,
            animation: root.dataset.showcaseAnimation,
            visibility: root.dataset.showcaseVisibility,
            documentVisibility: root.dataset.showcaseDocument,
            rotationControl: toggle?.textContent.trim(),
            hasUnexpectedManipulationControls: Boolean(root.querySelector('input, details, [data-showroom-action]')),
            visibleSitemapLinks: [...document.querySelectorAll('a')].filter((link) => link.textContent.trim() === 'Sitemap').length,
        };
    })()`);
    assert.deepEqual(homepage, {
        destination: '/products/series-x-articulated-lamp',
        pointerEvents: 'none',
        animation: 'running',
        visibility: 'onscreen',
        documentVisibility: 'visible',
        rotationControl: 'Pause rotation',
        hasUnexpectedManipulationControls: false,
        visibleSitemapLinks: 0,
    });
    await tabTo('[data-showcase-link]');
    assert.equal(await evaluate(`(() => {
        const style = getComputedStyle(document.querySelector('[data-showcase-link]'));
        return style.boxShadow !== 'none' || style.outlineStyle !== 'none';
    })()`), true, 'Homepage showcase focus was not visibly styled.');
    await waitFor('getComputedStyle(document.querySelector("[data-showcase-poster]")).opacity === "0"', 'Homepage poster did not finish fading.');
    await delay(600);
    const rotationStart = await selectorScreenshot('[data-product-showcase]', 'homepage-desktop-rotation-start.png');
    await delay(2200);
    const rotationEnd = await selectorScreenshot('[data-product-showcase]', 'homepage-desktop-rotation-end.png');
    assert.notEqual(digest(rotationStart), digest(rotationEnd), 'Homepage model pixels did not change during normal-motion rotation.');
    homepage.sustainedRotationObserved = true;
    await client.send('Emulation.setDeviceMetricsOverride', { width: 1440, height: 600, deviceScaleFactor: 1, mobile: false });
    await evaluate(`(() => {
        const spacer = document.createElement('div');
        spacer.id = 'offscreen-lifecycle-spacer';
        spacer.style.height = '1200px';
        document.body.append(spacer);
    })()`);
    await evaluate('window.scrollTo(0, document.documentElement.scrollHeight)');
    await waitFor('document.querySelector("[data-product-showcase]").dataset.showcaseAnimation === "paused"', 'Homepage showcase did not pause after moving fully offscreen.');
    assert.equal(await evaluate('document.querySelector("[data-storefront-slideshow]").dataset.slideshowAnimation'), 'paused');
    await evaluate('document.querySelector("[data-product-showcase]").scrollIntoView({ block: "center" })');
    await waitFor('document.querySelector("[data-product-showcase]").dataset.showcaseAnimation === "running"', 'Homepage showcase did not resume after returning onscreen.');
    await evaluate('document.querySelector("#offscreen-lifecycle-spacer")?.remove()');
    await client.send('Emulation.setDeviceMetricsOverride', { width: 1440, height: 1100, deviceScaleFactor: 1, mobile: false });
    await client.send('Input.dispatchKeyEvent', { type: 'keyDown', key: 'Enter', code: 'Enter', windowsVirtualKeyCode: 13 });
    await client.send('Input.dispatchKeyEvent', { type: 'keyUp', key: 'Enter', code: 'Enter', windowsVirtualKeyCode: 13 });
    await waitFor('location.pathname === "/products/series-x-articulated-lamp"', 'Homepage showcase keyboard activation did not navigate to its associated product.');

    await evaluate('history.back()');
    await waitFor('location.pathname === "/"', 'Back navigation did not restore the homepage.');
    await waitFor('document.querySelector("[data-storefront-slideshow]")?.dataset.slideshowAnimation === "running"', 'Restored slideshow did not resume.');
    const homeLifecycle = await evaluate('window.__maatLifecycleEvidence');
    const beforeBackNext = Number(await evaluate('document.querySelector("[data-storefront-slideshow]").dataset.slideshowIndex'));
    await evaluate('document.querySelector("[data-slideshow-next]").click()');
    const backIndex = await evaluate('document.querySelector("[data-storefront-slideshow]").dataset.slideshowIndex');
    assert.equal(Number(backIndex), (beforeBackNext + 1) % 9, 'A restored slideshow click did not advance exactly one slide.');
    await evaluate('history.forward()');
    await waitFor('location.pathname === "/products/series-x-articulated-lamp"', 'Forward navigation did not restore the product page.');
    await waitFor('document.querySelector("[data-product-showroom]")?.dataset.showroomState === "ready"', 'Restored showroom did not become ready.');
    const productLifecycle = await evaluate('window.__maatLifecycleEvidence');
    const restoredCanvas = await evaluate(`(() => {
        const rect = document.querySelector('[data-showroom-canvas]').getBoundingClientRect();
        return { x: rect.left + rect.width / 2, y: rect.top + rect.height / 2 };
    })()`);
    await client.send('Input.dispatchMouseEvent', { type: 'mousePressed', x: restoredCanvas.x, y: restoredCanvas.y, button: 'left', buttons: 1, clickCount: 1 });
    await client.send('Input.dispatchMouseEvent', { type: 'mouseMoved', x: restoredCanvas.x + 55, y: restoredCanvas.y + 12, button: 'left', buttons: 1 });
    await client.send('Input.dispatchMouseEvent', { type: 'mouseReleased', x: restoredCanvas.x + 55, y: restoredCanvas.y + 12, button: 'left', buttons: 0, clickCount: 1 });
    await delay(100);
    navigationLifecycle = await evaluate(`(() => {
        const galleryRoot = document.querySelector('[data-product-media-gallery]');
        galleryRoot.querySelector('[data-media-select="photo-0"]').click();
        galleryRoot.querySelector('[data-media-select="3d"]').click();
        return {
            backIndex: ${JSON.stringify(backIndex)},
            media: galleryRoot.dataset.activeMedia,
            slideshowInitializers: document.querySelectorAll('[data-slideshow-initialized]').length,
            galleryInitializers: document.querySelectorAll('[data-media-gallery-initialized]').length,
            showroomInitializers: document.querySelectorAll('[data-showroom-initialized]').length,
            canvases: document.querySelectorAll('[data-showroom-canvas]').length,
            interacted: document.querySelector('[data-product-showroom]').dataset.showroomInteracted,
            autoRotationChecked: document.querySelector('[data-showroom-input="autoRotate"]').checked,
            outstandingAnimationFrames: window.__maatLifecycleEvidence.outstandingAnimationFrames,
        };
    })()`);
    assert.equal(navigationLifecycle.backIndex, backIndex);
    assert.equal(navigationLifecycle.media, '3d');
    assert.equal(navigationLifecycle.slideshowInitializers, 0);
    assert.equal(navigationLifecycle.galleryInitializers, 1);
    assert.equal(navigationLifecycle.showroomInitializers, 1);
    assert.equal(navigationLifecycle.canvases, 1);
    assert.equal(navigationLifecycle.interacted, 'true');
    assert.equal(navigationLifecycle.autoRotationChecked, false);
    assert.ok(navigationLifecycle.outstandingAnimationFrames <= 1, `Unexpected animation-frame fan-out after navigation: ${navigationLifecycle.outstandingAnimationFrames}`);
    bfcache = {
        homePersisted: homeLifecycle.pageShows.some((event) => event.persisted),
        productPersisted: productLifecycle.pageShows.some((event) => event.persisted),
        verified: homeLifecycle.pageShows.some((event) => event.persisted) && productLifecycle.pageShows.some((event) => event.persisted),
        homeLifecycle,
        productLifecycle,
        exclusions: bfcacheExclusions,
    };
    await waitFor('[...document.images].every((image) => image.complete)', 'Product images did not settle before the repeated bfcache cycle.');
    await delay(700);
    await evaluate('history.back()');
    await waitFor('location.pathname === "/"', 'Second Back navigation did not restore the homepage.');
    await waitFor('document.querySelector("[data-storefront-slideshow]")?.dataset.slideshowAnimation === "running"', 'Slideshow did not resume on the second Back navigation.');
    const secondHomeLifecycle = await evaluate('window.__maatLifecycleEvidence');
    const secondBefore = Number(await evaluate('document.querySelector("[data-storefront-slideshow]").dataset.slideshowIndex'));
    await evaluate('document.querySelector("[data-slideshow-next]").click()');
    assert.equal(Number(await evaluate('document.querySelector("[data-storefront-slideshow]").dataset.slideshowIndex')), (secondBefore + 1) % 9);
    await evaluate('history.forward()');
    await waitFor('location.pathname === "/products/series-x-articulated-lamp"', 'Second Forward navigation did not restore the product page.');
    await waitFor('document.querySelector("[data-product-showroom]")?.dataset.showroomState === "ready"', 'Showroom did not recover on the second Forward navigation.');
    const secondProductLifecycle = await evaluate('window.__maatLifecycleEvidence');
    await evaluate(`(() => {
        const gallery = document.querySelector('[data-product-media-gallery]');
        gallery.querySelector('[data-media-select="photo-0"]').click();
        gallery.querySelector('[data-media-select="3d"]').click();
    })()`);
    assert.equal(await evaluate('document.querySelector("[data-product-media-gallery]").dataset.activeMedia'), '3d');
    bfcache.secondHomeLifecycle = secondHomeLifecycle;
    bfcache.secondProductLifecycle = secondProductLifecycle;
    bfcache.homePersisted = secondHomeLifecycle.pageShows.some((event) => event.persisted);
    bfcache.productPersisted = secondProductLifecycle.pageShows.some((event) => event.persisted);
    bfcache.verified = bfcache.homePersisted && bfcache.productPersisted;

    phase = 'image-only-product';
    await navigate(`${baseUrl}/products/led-matrix-panel`);
    assert.equal(await evaluate('Boolean(document.querySelector("[data-product-showroom]"))'), false);
    const imageOnlyProduct = await evaluate(`({
        status: document.title.includes('Page Expired') ? 'error' : 'rendered',
        hasProductPage: Boolean(document.querySelector('#product-purchase-form')),
        hasMainImage: Boolean(document.querySelector('[data-product-media-gallery] [data-media-image]')),
        hasFallback: Boolean(document.querySelector('[data-lucide="image-off"]')),
    })`);
    if (imageOnlyProduct.hasProductPage) assert.equal(imageOnlyProduct.hasMainImage || imageOnlyProduct.hasFallback, true);

    phase = 'homepage-mobile-reduced-motion';
    await client.send('Emulation.setDeviceMetricsOverride', { width: 390, height: 844, deviceScaleFactor: 1, mobile: false });
    await client.send('Emulation.setEmulatedMedia', { features: [{ name: 'prefers-reduced-motion', value: 'reduce' }] });
    await navigate(`${baseUrl}/`);
    await waitFor('document.querySelector("[data-product-showcase]")?.dataset.showcaseState === "ready"', 'Mobile homepage showcase did not become ready.');
    const reducedSlideshowIndex = await evaluate('document.querySelector("[data-storefront-slideshow]").dataset.slideshowIndex');
    assert.equal(await evaluate('document.querySelector("[data-storefront-slideshow]").dataset.slideshowAnimation'), 'paused');
    await delay(5200);
    assert.equal(await evaluate('document.querySelector("[data-storefront-slideshow]").dataset.slideshowIndex'), reducedSlideshowIndex);
    await evaluate('document.querySelector("[data-slideshow-next]").click()');
    assert.notEqual(await evaluate('document.querySelector("[data-storefront-slideshow]").dataset.slideshowIndex'), reducedSlideshowIndex);
    await evaluate('document.querySelector(\'[data-slideshow-dot="6"]\').click()');
    assert.equal(await evaluate('document.querySelector("[data-storefront-slideshow]").dataset.slideshowIndex'), '6');
    await waitFor('document.querySelector(\'[data-slideshow-slide][data-active="true"] img\')?.complete && document.querySelector(\'[data-slideshow-slide][data-active="true"] img\')?.naturalWidth > 0', 'Mobile slideshow image did not load after manual navigation.');
    await selectorScreenshot('[data-storefront-slideshow]', 'homepage-mobile-slideshow-manual.png');
    await evaluate('document.querySelector("[data-product-showcase]").scrollIntoView({ block: "center" })');
    await waitFor('document.querySelector("[data-product-showcase]").dataset.showcaseVisibility === "onscreen"', 'Mobile homepage showcase did not become visible for reduced-motion controls.');
    assert.equal(await evaluate('document.querySelector("[data-product-showcase]").dataset.showcaseAnimation'), 'paused');
    assert.equal(await evaluate('matchMedia("(prefers-reduced-motion: reduce)").matches'), true);
    assert.equal(await evaluate('document.querySelector("[data-showcase-rotation-toggle]").textContent.trim()'), 'Play rotation');
    assert.equal(await evaluate('document.documentElement.scrollWidth > innerWidth'), false);
    await waitFor('getComputedStyle(document.querySelector("[data-showcase-poster]")).opacity === "0"', 'Reduced-motion homepage poster did not finish fading.');
    await delay(600);
    const reducedStart = await selectorScreenshot('[data-product-showcase]', 'homepage-mobile-reduced-motion-start.png');
    await delay(1500);
    const reducedEnd = await selectorScreenshot('[data-product-showcase]', 'homepage-mobile-reduced-motion-end.png');
    const reducedMotion = {
        animationBefore: 'paused',
        animationAfter: await evaluate('document.querySelector("[data-product-showcase]").dataset.showcaseAnimation'),
        startScreenshotHash: digest(reducedStart),
        endScreenshotHash: digest(reducedEnd),
    };
    assert.equal(reducedMotion.animationAfter, 'paused');
    await screenshot('homepage-mobile-reduced-motion.png');
    await evaluate('document.querySelector("[data-showcase-rotation-toggle]").click()');
    assert.equal(await evaluate('location.pathname'), '/');
    assert.equal(await evaluate('document.querySelector("[data-product-showcase]").dataset.showcaseAnimation'), 'running');
    const reducedOptInStart = await selectorScreenshot('[data-product-showcase]', 'homepage-mobile-reduced-motion-play-start.png');
    await delay(2200);
    const reducedOptInEnd = await selectorScreenshot('[data-product-showcase]', 'homepage-mobile-reduced-motion-play-end.png');
    assert.notEqual(digest(reducedOptInStart), digest(reducedOptInEnd), 'Explicit reduced-motion opt-in did not rotate the lamp.');
    await evaluate('document.querySelector("[data-showcase-rotation-toggle]").click()');
    assert.equal(await evaluate('document.querySelector("[data-product-showcase]").dataset.showcaseAnimation'), 'paused');
    assert.equal(await evaluate('document.querySelector("[data-showcase-rotation-toggle]").textContent.trim()'), 'Play rotation');

    if (process.env.VERIFY_CHECKOUT === '1') {
        phase = 'disposable-checkout';
        await client.send('Emulation.setEmulatedMedia', { features: [{ name: 'prefers-reduced-motion', value: 'no-preference' }] });
        await client.send('Emulation.setDeviceMetricsOverride', { width: 1280, height: 900, deviceScaleFactor: 1, mobile: false });
        await navigate(`${baseUrl}/products/series-x-articulated-lamp`);
        await evaluate(`(() => {
            const form = document.querySelector('#product-purchase-form');
            form.querySelector('[name="quantity"]').value = 2;
            form.requestSubmit();
        })()`);
        await waitFor('document.body?.textContent?.includes("added to cart") === true', 'Browser add-to-cart flow did not complete.');
        await navigate(`${baseUrl}/cart`);
        assert.equal(await evaluate('document.body.textContent.includes("LED Swing-Arm Desk Lamp")'), true);
        await navigate(`${baseUrl}/checkout`);
        assert.equal(await evaluate('document.body.textContent.includes("Free delivery all across Bangladesh.")'), true);
        assert.equal(await evaluate('document.querySelector("#shipping-fee-label").textContent.trim()'), 'FREE / ৳0');
        const checkoutPayload = await evaluate(`(() => {
            const form = document.querySelector('form[action$="/checkout"]');
            const values = {
                _token: form.querySelector('[name="_token"]').value,
                checkout_attempt_key: form.querySelector('[name="checkout_attempt_key"]').value,
                name: 'Synthetic Browser Buyer',
                phone: '01700000000',
                district: 'Dhaka',
                address: '123 Disposable Test Road',
                customer_note: 'Disposable browser verification',
            };
            for (const [name, value] of Object.entries(values)) {
                const control = form.querySelector('[name="' + name + '"]');
                if (control) control.value = value;
            }
            form.requestSubmit();
            return values;
        })()`);
        await waitFor('location.pathname === "/orders" && document.body?.textContent?.includes("Order placed successfully") === true', 'Browser checkout did not create an order.');
        const firstOrderPage = await evaluate('document.body.textContent');
        const replay = await evaluate(`(async () => {
            const response = await fetch('/checkout', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams(${JSON.stringify(checkoutPayload)}),
            });
            return { status: response.status, url: response.url, text: await response.text() };
        })()`);
        assert.equal(replay.status, 200);
        assert.equal(new URL(replay.url).pathname, '/orders');
        assert.equal(replay.text.includes('LED Swing-Arm Desk Lamp'), true);
        checkoutFlow = {
            attemptKey: checkoutPayload.checkout_attempt_key,
            firstOrderVisible: firstOrderPage.includes('LED Swing-Arm Desk Lamp'),
            replayStatus: replay.status,
            replayDestination: new URL(replay.url).pathname,
            freeDelivery: firstOrderPage.includes('Delivery ৳0'),
            payableTotal: firstOrderPage.includes('Total ৳4,998'),
            colorPreserved: firstOrderPage.includes('Black'),
        };
        assert.equal(checkoutFlow.freeDelivery, true);
        assert.equal(checkoutFlow.payableTotal, true);
        assert.equal(checkoutFlow.colorPreserved, true);
        await screenshot('checkout-idempotent-order.png');
    }

    let sourceEffectHashes = null;
    if (sourceViewer) {
        phase = 'supplied-source';
        await client.send('Emulation.setEmulatedMedia', { features: [{ name: 'prefers-reduced-motion', value: 'no-preference' }] });
        await client.send('Emulation.setDeviceMetricsOverride', { width: 1440, height: 1100, deviceScaleFactor: 1, mobile: false });
        await navigate(pathToFileURL(sourceViewer).href);
        await waitFor('document.querySelector("#c")?.width > 0', 'Supplied viewer did not render.');
        await delay(750);
        await screenshot('supplied-viewer-desktop-default.png');
        sourceEffectHashes = {};
        const resetSource = async () => {
            await evaluate(`(() => {
                document.querySelector('#reset').click();
                const set = (selector, value, eventName = 'input') => {
                    const control = document.querySelector(selector);
                    control.value = value;
                    control.dispatchEvent(new Event(eventName, { bubbles: true }));
                };
                set('#variant', 'black', 'change');
                set('#mode', 'cool', 'change');
                set('#brightness', 10);
                document.querySelector('#spin').checked = false;
            })()`);
            await delay(100);
        };
        const captureSourceEffect = async (name, action) => {
            await resetSource();
            if (action) await evaluate(action);
            await delay(120);
            sourceEffectHashes[name] = digest(await selectorScreenshot('#c'));
            return sourceEffectHashes[name];
        };
        const sourceBaseline = await captureSourceEffect('baseline', null);
        const sourceChangedEffects = [
            ['finishWhite', `(() => { const control = document.querySelector('#variant'); control.value = 'white'; control.dispatchEvent(new Event('change', { bubbles: true })); })()`],
            ['lightOff', `(() => { const control = document.querySelector('#mode'); control.value = 'off'; control.dispatchEvent(new Event('change', { bubbles: true })); })()`],
            ['brightnessOne', `(() => { const control = document.querySelector('#brightness'); control.value = 1; control.dispatchEvent(new Event('input', { bubbles: true })); })()`],
            ['cameraFront', `document.querySelector('#front').click()`],
            ['poseReach', `document.querySelector('#pose_reach').click()`],
            ['lowerJoint', `(() => { const control = document.querySelector('#lower'); control.value = 70; control.dispatchEvent(new Event('input', { bubbles: true })); })()`],
            ['upperJoint', `(() => { const control = document.querySelector('#upper'); control.value = -20; control.dispatchEvent(new Event('input', { bubbles: true })); })()`],
            ['neckJoint', `(() => { const control = document.querySelector('#tilt'); control.value = 30; control.dispatchEvent(new Event('input', { bubbles: true })); })()`],
            ['headJoint', `(() => { const control = document.querySelector('#roll'); control.value = 20; control.dispatchEvent(new Event('input', { bubbles: true })); })()`],
            ['baseJoint', `(() => { const control = document.querySelector('#yaw'); control.value = 35; control.dispatchEvent(new Event('input', { bubbles: true })); })()`],
            ['clampJoint', `(() => { const control = document.querySelector('#jaw'); control.value = 45; control.dispatchEvent(new Event('input', { bubbles: true })); })()`],
            ['exploded', `document.querySelector('#explodeButton').click()`],
            ['anatomy', `(() => { const control = document.querySelector('#anatomy'); control.checked = true; control.dispatchEvent(new Event('change', { bubbles: true })); })()`],
            ['partHighlight', `(() => { const control = document.querySelector('#partSelect'); control.value = 'head'; control.dispatchEvent(new Event('change', { bubbles: true })); })()`],
            ['partIsolate', `(() => { const selection = document.querySelector('#partSelect'); selection.value = 'head'; selection.dispatchEvent(new Event('change', { bubbles: true })); const isolate = document.querySelector('#isolate'); isolate.checked = true; isolate.dispatchEvent(new Event('change', { bubbles: true })); })()`],
            ['partFocus', `(() => { const selection = document.querySelector('#partSelect'); selection.value = 'controller'; selection.dispatchEvent(new Event('change', { bubbles: true })); document.querySelector('#focus').click(); })()`],
            ['wireHidden', `(() => { const control = document.querySelector('#wire'); control.checked = false; control.dispatchEvent(new Event('change', { bubbles: true })); })()`],
        ];
        for (const [name, action] of sourceChangedEffects) {
            assert.notEqual(await captureSourceEffect(name, action), sourceBaseline, `${name} did not visibly change the supplied viewer.`);
        }
        await resetSource();
        await evaluate('document.querySelector("#explodeButton").click()');
        await delay(300);
        await screenshot('supplied-viewer-desktop-engineering.png');
    }

    await delay(100);
    const coldTransfers = phaseTransfers('cold-desktop');
    const compressedTransfer = coldTransfers.find((transfer) => transfer.url.includes('.bin.gz'));
    assert.equal(compressedTransfer?.status, 200);
    assert.ok(compressedTransfer.encodedDataLength < 5000000);
    const report = {
        desktop,
        keyboard,
        interaction,
        parity,
        effectHashes,
        gallery,
        sourceEffectHashes,
        reset,
        mobile,
        coldTransfers,
        rawRecoveryTransfers: phaseTransfers('compressed-blocked-raw-recovery'),
        throttled: { scenario: '4 Mbps down, 1.5 Mbps up, 150 ms latency, cache disabled', modelReadyMs: throttledReadyMs, transfers: phaseTransfers('throttled-4mbps-150ms') },
        warm: { modelReadyMs: warmReadyMs, transfers: phaseTransfers('warm-reload') },
        slideshow,
        homepage,
        imageOnlyProduct,
        reducedMotion,
        contextLoss,
        bfcache,
        navigationLifecycle,
        checkoutFlow,
        browserExceptions,
        browserErrors,
        failedRequests,
        screenshots: evidenceDirectory,
    };
    await writeFile(path.join(evidenceDirectory, 'browser-transfer-report.json'), `${JSON.stringify(report, null, 2)}\n`);
    console.log(JSON.stringify(report, null, 2));
} finally {
    client.close();
    browser.kill('SIGTERM');
    await Promise.race([
        new Promise((resolve) => browser.once('exit', resolve)),
        delay(2000),
    ]);
    await rm(profile, { recursive: true, force: true }).catch(() => {});
}
