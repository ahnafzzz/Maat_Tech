import assert from 'node:assert/strict';
import { spawn } from 'node:child_process';
import { mkdir, mkdtemp, rm, writeFile } from 'node:fs/promises';
import os from 'node:os';
import path from 'node:path';
import { pathToFileURL } from 'node:url';

const baseUrl = process.argv[2];
const evidenceDirectory = path.resolve(process.argv[3] ?? '../deployment/evidence/ui-3d-step-2');
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

const modelTransfers = new Map();
const browserErrors = [];
client.on('Runtime.exceptionThrown', ({ exceptionDetails }) => browserErrors.push(exceptionDetails.exception?.description ?? exceptionDetails.text));
client.on('Runtime.consoleAPICalled', ({ type, args }) => {
    if (type === 'error' || type === 'warning') browserErrors.push(args.map((argument) => argument.value ?? argument.description).join(' '));
});
client.on('Network.responseReceived', ({ requestId, response }) => {
    if (response.url.endsWith('.bin')) {
        modelTransfers.set(requestId, {
            url: response.url,
            status: response.status,
            fromDiskCache: response.fromDiskCache,
            fromServiceWorker: response.fromServiceWorker,
            contentEncoding: response.headers['content-encoding'] ?? response.headers['Content-Encoding'] ?? null,
            contentLength: response.headers['content-length'] ?? response.headers['Content-Length'] ?? null,
        });
    }
});
client.on('Network.loadingFinished', ({ requestId, encodedDataLength }) => {
    if (modelTransfers.has(requestId)) modelTransfers.get(requestId).encodedDataLength = encodedDataLength;
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

try {
    await mkdir(evidenceDirectory, { recursive: true });
    await client.send('Emulation.setDeviceMetricsOverride', { width: 1440, height: 1100, deviceScaleFactor: 1, mobile: false });
    await navigate(`${baseUrl}/products/series-x-articulated-lamp`);
    await waitFor('!["loading", undefined].includes(document.querySelector("[data-product-showroom]")?.dataset.showroomState)', 'Desktop showroom did not settle.');
    const desktopState = await evaluate('document.querySelector("[data-product-showroom]")?.dataset.showroomState');
    assert.equal(desktopState, 'ready', `Desktop showroom state was ${desktopState}; browser errors: ${browserErrors.join(' | ')}`);

    const desktop = await evaluate(`(() => {
        const root = document.querySelector('[data-product-showroom]');
        const canvas = root.querySelector('canvas');
        const cart = [...document.querySelectorAll('button')].find((button) => button.textContent.includes('Add to Cart'));
        canvas.focus();
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
    await screenshot('showroom-desktop-default.png');

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

    await client.send('Emulation.setDeviceMetricsOverride', { width: 390, height: 844, deviceScaleFactor: 2, mobile: true });
    await client.send('Emulation.setEmulatedMedia', { features: [{ name: 'prefers-reduced-motion', value: 'reduce' }] });
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
            canvas: { x: rect.left + rect.width / 2, y: Math.min(innerHeight - 20, rect.top + rect.height / 2) },
        };
    })()`);
    assert.equal(mobile.animation, 'paused');
    assert.equal(mobile.touchAction, 'pan-y');
    assert.equal(mobile.overflow, true);
    assert.equal(mobile.horizontalOverflow, false);
    await screenshot('showroom-mobile-reduced-motion.png');

    await evaluate('window.scrollTo(0, 0); document.activeElement?.blur()');
    await client.send('Input.dispatchMouseEvent', { type: 'mouseWheel', x: mobile.canvas.x, y: mobile.canvas.y, deltaX: 0, deltaY: 500 });
    await delay(250);
    assert.ok(await evaluate('window.scrollY > 0'), 'Unfocused viewer trapped normal page scrolling.');
    await evaluate('window.scrollTo(0, document.documentElement.scrollHeight)');
    await delay(250);
    assert.equal(await evaluate('document.querySelector("[data-product-showroom]").dataset.showroomAnimation'), 'paused');

    await client.send('Network.setCacheDisabled', { cacheDisabled: true });
    await client.send('Network.setBlockedURLs', { urls: ['*desk-lamp.a45a18eea9197981.bin*'] });
    await navigate(`${baseUrl}/products/series-x-articulated-lamp?failure-check=1`);
    await waitFor('document.querySelector("[data-product-showroom]")?.dataset.showroomState === "fallback"', 'Blocked model did not show the poster fallback.');
    assert.equal(await evaluate('getComputedStyle(document.querySelector("[data-showroom-poster]")).display !== "none"'), true);
    await screenshot('showroom-mobile-loading-failure.png');
    await client.send('Network.setBlockedURLs', { urls: [] });

    if (sourceViewer) {
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
    const transfer = [...modelTransfers.values()][0];
    assert.equal(transfer?.status, 200);
    console.log(JSON.stringify({ desktop, interaction, reset, mobile, modelTransfer: transfer, screenshots: evidenceDirectory }, null, 2));
} finally {
    client.close();
    browser.kill('SIGTERM');
    await Promise.race([
        new Promise((resolve) => browser.once('exit', resolve)),
        delay(2000),
    ]);
    await rm(profile, { recursive: true, force: true }).catch(() => {});
}
