import assert from 'node:assert/strict';
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
client.on('Runtime.exceptionThrown', ({ exceptionDetails }) => browserErrors.push(exceptionDetails.exception?.description ?? exceptionDetails.text));
client.on('Runtime.consoleAPICalled', ({ type, args }) => {
    if (type === 'error' || type === 'warning') browserErrors.push(args.map((argument) => argument.value ?? argument.description).join(' '));
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
    for (let attempt = 0; attempt < 150; attempt += 1) {
        if (await evaluate(expression)) return;
        await delay(100);
    }
    const diagnostics = await evaluate(`({
        url: location.href,
        title: document.title,
        state: document.querySelector('[data-product-showroom]')?.dataset.showroomState,
        status: document.querySelector('[data-showroom-status]')?.textContent,
        scripts: [...document.scripts].map((script) => script.src).filter(Boolean),
    })`);
    throw new Error(`${message} ${JSON.stringify(diagnostics)} Browser errors: ${browserErrors.join(' | ')}`);
}

async function screenshot(name) {
    const { data } = await client.send('Page.captureScreenshot', { format: 'png', captureBeyondViewport: false });
    await writeFile(path.join(evidenceDirectory, name), Buffer.from(data, 'base64'));
}

async function tabTo(selector, maximum = 30) {
    await evaluate('document.activeElement?.blur()');
    for (let index = 0; index < maximum; index += 1) {
        await client.send('Input.dispatchKeyEvent', { type: 'keyDown', key: 'Tab', code: 'Tab', windowsVirtualKeyCode: 9 });
        await client.send('Input.dispatchKeyEvent', { type: 'keyUp', key: 'Tab', code: 'Tab', windowsVirtualKeyCode: 9 });
        if (await evaluate(`document.activeElement?.matches(${JSON.stringify(selector)})`)) return;
    }
    throw new Error(`Keyboard focus did not reach ${selector}.`);
}

const phaseTransfers = (name) => [...transfers.values()].filter((transfer) => transfer.phase === name);

try {
    await mkdir(evidenceDirectory, { recursive: true });
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
            cartHeight: cart.getBoundingClientRect().height,
            quantityLabelled: Boolean(document.querySelector('label input[name="quantity"]')),
        };
    })()`);
    assert.equal(desktop.state, 'ready');
    assert.equal(desktop.animation, 'running');
    assert.equal(desktop.controlsEnabled, true);
    assert.equal(desktop.focusVisible, true);
    assert.ok(desktop.cartHeight >= 44);
    assert.equal(desktop.quantityLabelled, true);
    desktop.modelReadyMs = Date.now() - coldStarted;
    await waitFor('getComputedStyle(document.querySelector("[data-showroom-poster]")).opacity === "0"', 'Poster did not finish fading after the first model frame.');
    desktop.posterHiddenAfterReady = await evaluate('getComputedStyle(document.querySelector("[data-showroom-poster]")).opacity === "0"');
    assert.equal(desktop.posterHiddenAfterReady, true);
    await screenshot('showroom-desktop-default.png');

    const keyboard = await evaluate(`(() => {
        const root = document.querySelector('[data-product-showroom]');
        const canvas = root.querySelector('canvas');
        canvas.focus();
        canvas.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowRight', bubbles: true }));
        return { interacted: root.dataset.showroomInteracted, animation: root.dataset.showroomAnimation };
    })()`);
    assert.deepEqual(keyboard, { interacted: 'true', animation: 'paused' });

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
            labels: root.querySelectorAll('.showroom-part-label').length,
            white: root.querySelector('[data-showroom-value="white"]').getAttribute('aria-pressed'),
            warm: root.querySelector('[data-showroom-value="warm"]').getAttribute('aria-pressed'),
        };
    })()`);
    assert.deepEqual(interaction, { interacted: 'true', animation: 'paused', labels: 8, white: 'true', warm: 'true' });
    await screenshot('showroom-desktop-engineering.png');

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
        };
    })()`);
    assert.deepEqual(reset, { lower: '118', jaw: '25.1', explode: '0', anatomy: false, black: 'true', cool: 'true' });

    assert.equal(await evaluate(`(() => {
        const gl = document.querySelector('[data-showroom-canvas]').getContext('webgl');
        const extension = gl.getExtension('WEBGL_lose_context');
        extension?.loseContext();
        return Boolean(extension);
    })()`), true, 'The software WebGL context-loss extension was unavailable.');
    await waitFor('document.querySelector("[data-product-showroom]")?.dataset.showroomState === "fallback"', 'Context loss did not restore the poster fallback.');

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
    await waitFor('document.querySelector("[data-product-showcase]")?.dataset.showcaseState === "ready"', 'Homepage showcase did not become ready.');
    const homepage = await evaluate(`(() => {
        const root = document.querySelector('[data-product-showcase]');
        const canvas = root.querySelector('canvas');
        return {
            destination: root.closest('a')?.pathname,
            pointerEvents: getComputedStyle(canvas).pointerEvents,
            animation: root.dataset.showcaseAnimation,
            hasManipulationControls: Boolean(root.querySelector('button, input, details')),
        };
    })()`);
    assert.deepEqual(homepage, {
        destination: '/products/series-x-articulated-lamp',
        pointerEvents: 'none',
        animation: 'running',
        hasManipulationControls: false,
    });
    await screenshot('homepage-desktop.png');

    phase = 'image-only-product';
    await navigate(`${baseUrl}/products/led-matrix-panel`);
    assert.equal(await evaluate('Boolean(document.querySelector("[data-product-showroom]"))'), false);
    assert.equal(await evaluate('Boolean(document.querySelector("#product-main-image"))'), false);

    phase = 'homepage-mobile-reduced-motion';
    await client.send('Emulation.setDeviceMetricsOverride', { width: 390, height: 844, deviceScaleFactor: 1, mobile: false });
    await client.send('Emulation.setEmulatedMedia', { features: [{ name: 'prefers-reduced-motion', value: 'reduce' }] });
    await navigate(`${baseUrl}/`);
    await waitFor('document.querySelector("[data-product-showcase]")?.dataset.showcaseState === "ready"', 'Mobile homepage showcase did not become ready.');
    assert.equal(await evaluate('document.querySelector("[data-product-showcase]").dataset.showcaseAnimation'), 'paused');
    assert.equal(await evaluate('document.documentElement.scrollWidth > innerWidth'), false);
    await screenshot('homepage-mobile-reduced-motion.png');

    if (sourceViewer) {
        phase = 'supplied-source';
        await client.send('Emulation.setEmulatedMedia', { features: [{ name: 'prefers-reduced-motion', value: 'no-preference' }] });
        await client.send('Emulation.setDeviceMetricsOverride', { width: 1440, height: 1100, deviceScaleFactor: 1, mobile: false });
        await navigate(pathToFileURL(sourceViewer).href);
        await waitFor('document.querySelector("#c")?.width > 0', 'Supplied viewer did not render.');
        await delay(750);
        await screenshot('supplied-viewer-desktop-default.png');
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
        reset,
        mobile,
        coldTransfers,
        rawRecoveryTransfers: phaseTransfers('compressed-blocked-raw-recovery'),
        throttled: { scenario: '4 Mbps down, 1.5 Mbps up, 150 ms latency, cache disabled', modelReadyMs: throttledReadyMs, transfers: phaseTransfers('throttled-4mbps-150ms') },
        warm: { modelReadyMs: warmReadyMs, transfers: phaseTransfers('warm-reload') },
        homepage,
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
