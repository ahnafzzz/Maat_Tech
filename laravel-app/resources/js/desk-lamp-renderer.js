const degrees = (value) => value * Math.PI / 180;
const add = (a, b) => a.map((value, index) => value + b[index]);
const subtract = (a, b) => a.map((value, index) => value - b[index]);
const multiply = (value, scalar) => value.map((item) => item * scalar);
const dot = (a, b) => a.reduce((sum, value, index) => sum + value * b[index], 0);
const cross = (a, b) => [
    a[1] * b[2] - a[2] * b[1],
    a[2] * b[0] - a[0] * b[2],
    a[0] * b[1] - a[1] * b[0],
];
const normalize = (value) => multiply(value, 1 / Math.max(Math.hypot(...value), 1e-9));
const direction = (angle) => [Math.cos(degrees(angle)), 0, Math.sin(degrees(angle))];
const identity = () => [1, 0, 0, 0, 0, 1, 0, 0, 0, 0, 1, 0, 0, 0, 0, 1];

export const DESK_LAMP_LIMITS = Object.freeze({
    lower: [58, 142],
    upper: [-85, 112],
    tilt: [-70, 95],
    roll: [-100, 100],
    baseYaw: [-170, 170],
    jaw: [4, 53.3],
    explode: [0, 100],
});

export const DESK_LAMP_PRESETS = Object.freeze({
    study: [118, 47, 0, -25],
    reach: [77, 10, 0, -10],
    tall: [72, 96, 0, -20],
    low: [77, -24, 16, -10],
    wide: [130, -33, 24, -15],
    folded: [98, -85, 88, 0],
});

export const DESK_LAMP_GROUPS = Object.freeze({
    clamp: { label: 'Clamp & screw', color: [0.28, 0.49, 0.68], description: 'Curved C body, mounting socket, pressure pad and sliding T handle.' },
    lower: { label: 'Lower linkage', color: [0.32, 0.58, 0.51], description: 'Two rectangular rails and bent spring-anchor brackets.' },
    upper: { label: 'Upper linkage', color: [0.47, 0.43, 0.70], description: 'Upper rails and four-corner elbow plates.' },
    head: { label: 'Light head', color: [0.73, 0.56, 0.30], description: 'Fork, laminated swivel neck, housing, diffuser and end caps.' },
    springs: { label: 'Tension springs', color: [0.77, 0.40, 0.32], description: 'Four coils with attachment eyes; anchors follow the moving rails.' },
    hardware: { label: 'Pivot hardware', color: [0.54, 0.61, 0.65], description: 'Axles, washers, nuts, transverse pins and thumb levers.' },
    controller: { label: 'Light controller', color: [0.25, 0.57, 0.70], description: 'Four keys: brightness +, colour mode, brightness − and power.' },
    cable: { label: 'Power leads', color: [0.25, 0.25, 0.28], description: 'Base-to-controller and controller-to-USB leads; arm and head wiring remain concealed.' },
    usb: { label: 'USB connector', color: [0.64, 0.53, 0.40], description: 'Overmould, hollow metal shell, insulator and contacts.' },
});

const clamp = (value, [minimum, maximum]) => Math.max(minimum, Math.min(maximum, value));
const modelCache = new Map();

export const brightnessForLevel = (level) => [2, 4, 6, 8, 10][clamp(Math.round(level), [1, 5]) - 1];

function multiplyMatrices(a, b) {
    const result = Array(16).fill(0);
    for (let column = 0; column < 4; column += 1) {
        for (let row = 0; row < 4; row += 1) {
            for (let index = 0; index < 4; index += 1) {
                result[column * 4 + row] += a[index * 4 + row] * b[column * 4 + index];
            }
        }
    }

    return result;
}

function translate(value) {
    const matrix = identity();
    [matrix[12], matrix[13], matrix[14]] = value;

    return matrix;
}

function rotateY(angle) {
    const cosine = Math.cos(degrees(angle));
    const sine = Math.sin(degrees(angle));

    return [cosine, 0, -sine, 0, 0, 1, 0, 0, sine, 0, cosine, 0, 0, 0, 0, 1];
}

function rotateX(angle) {
    const cosine = Math.cos(degrees(angle));
    const sine = Math.sin(degrees(angle));

    return [1, 0, 0, 0, 0, cosine, sine, 0, 0, -sine, cosine, 0, 0, 0, 0, 1];
}

function rotateZ(angle) {
    const cosine = Math.cos(degrees(angle));
    const sine = Math.sin(degrees(angle));

    return [cosine, sine, 0, 0, -sine, cosine, 0, 0, 0, 0, 1, 0, 0, 0, 0, 1];
}

const transformPoint = (matrix, point) => [
    matrix[0] * point[0] + matrix[4] * point[1] + matrix[8] * point[2] + matrix[12],
    matrix[1] * point[0] + matrix[5] * point[1] + matrix[9] * point[2] + matrix[13],
    matrix[2] * point[0] + matrix[6] * point[1] + matrix[10] * point[2] + matrix[14],
];
const move = (from, to, angle = 0) => multiplyMatrices(translate(to), multiplyMatrices(rotateY(-angle), translate(multiply(from, -1))));

function springMap(fromStart, fromEnd, toStart, toEnd) {
    const sourceDirection = normalize(subtract(fromEnd, fromStart));
    const targetDirection = normalize(subtract(toEnd, toStart));
    const angle = Math.atan2(targetDirection[2], targetDirection[0]) - Math.atan2(sourceDirection[2], sourceDirection[0]);
    const ratio = Math.hypot(...subtract(toEnd, toStart)) / Math.hypot(...subtract(fromEnd, fromStart));
    const stretch = identity();

    for (let row = 0; row < 3; row += 1) {
        for (let column = 0; column < 3; column += 1) {
            stretch[column * 4 + row] += (ratio - 1) * sourceDirection[row] * sourceDirection[column];
        }
    }

    return multiplyMatrices(
        translate(toStart),
        multiplyMatrices(rotateY(-angle * 180 / Math.PI), multiplyMatrices(stretch, translate(multiply(fromStart, -1)))),
    );
}

function compileShader(gl, type, source) {
    const shader = gl.createShader(type);
    gl.shaderSource(shader, source);
    gl.compileShader(shader);
    if (!gl.getShaderParameter(shader, gl.COMPILE_STATUS)) {
        throw new Error(gl.getShaderInfoLog(shader) || 'The showcase shader could not be compiled.');
    }

    return shader;
}

function createProgram(gl) {
    const program = gl.createProgram();
    const vertexShader = compileShader(gl, gl.VERTEX_SHADER, `
        attribute vec3 position;
        attribute vec3 surfaceNormal;
        uniform mat4 viewProjection;
        uniform mat4 model;
        varying vec3 normal;
        void main() {
            normal = mat3(model) * surfaceNormal;
            gl_Position = viewProjection * model * vec4(position, 1.0);
        }
    `);
    const fragmentShader = compileShader(gl, gl.FRAGMENT_SHADER, `
        precision mediump float;
        varying vec3 normal;
        uniform vec3 color;
        uniform float glow;
        void main() {
            vec3 normalized = normalize(normal);
            float key = abs(dot(normalized, normalize(vec3(-0.4, -0.7, 1.0))));
            float fill = abs(dot(normalized, normalize(vec3(0.7, 0.4, 0.3))));
            vec3 shaded = color * (0.48 + 0.6 * key + 0.2 * fill) + vec3(pow(key, 30.0) * 0.025);
            shaded = mix(shaded, color, glow);
            gl_FragColor = vec4(pow(max(shaded, vec3(0.0)), vec3(1.0 / 2.2)), 1.0);
        }
    `);
    gl.attachShader(program, vertexShader);
    gl.attachShader(program, fragmentShader);
    gl.linkProgram(program);
    gl.deleteShader(vertexShader);
    gl.deleteShader(fragmentShader);
    if (!gl.getProgramParameter(program, gl.LINK_STATUS)) {
        throw new Error(gl.getProgramInfoLog(program) || 'The showcase shader program could not be linked.');
    }

    return program;
}

async function fetchWithTimeout(url, signal, timeout = 20000) {
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(new Error('The 3D asset request timed out.')), timeout);
    const abort = () => controller.abort(signal?.reason);
    signal?.addEventListener('abort', abort, { once: true });

    try {
        const response = await fetch(url, { signal: controller.signal, credentials: 'same-origin' });
        if (!response.ok) throw new Error(`The 3D asset returned HTTP ${response.status}.`);

        return response;
    } finally {
        clearTimeout(timer);
        signal?.removeEventListener('abort', abort);
    }
}

async function decodeGzip(response) {
    if (!response.body || typeof DecompressionStream !== 'function') {
        throw new Error('Streaming gzip decompression is unavailable.');
    }

    return new Response(response.body.pipeThrough(new DecompressionStream('gzip'))).arrayBuffer();
}

async function fetchModelBinary(manifest, manifestResponse, manifestUrl) {
    const baseUrl = manifestResponse.url || manifestUrl;
    const compressed = manifest.buffer.compressed;
    if (compressed?.format === 'gzip' && typeof DecompressionStream === 'function') {
        try {
            const compressedUrl = new URL(compressed.url, baseUrl);
            const compressedResponse = await fetchWithTimeout(compressedUrl, undefined, 90000);

            return await decodeGzip(compressedResponse);
        } catch {
            // The raw fingerprinted buffer remains the compatibility and recovery path.
        }
    }

    const binaryUrl = new URL(manifest.buffer.url, baseUrl);
    const binaryResponse = await fetchWithTimeout(binaryUrl, undefined, 120000);

    return binaryResponse.arrayBuffer();
}

export function shouldAnimate({ active, documentVisible, reducedMotion, autoRotate = true }) {
    return active && documentVisible && !reducedMotion && autoRotate;
}

export class DeskLampShowcaseRenderer {
    constructor(canvas, manifest, vertices, callbacks = {}, options = {}) {
        this.canvas = canvas;
        this.manifest = manifest;
        this.vertices = vertices;
        this.callbacks = callbacks;
        this.active = false;
        this.documentVisible = !document.hidden;
        this.reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        this.autoRotate = options.autoRotate !== false;
        this.mode = options.mode ?? 'showcase';
        this.frame = null;
        this.lastFrameTime = null;
        this.cameraYaw = manifest.defaultCamera.yaw;
        this.cameraElevation = manifest.defaultCamera.elevation;
        this.target = manifest.defaultCamera.target;
        this.scale = manifest.defaultCamera.scale;
        this.zoom = 1;
        this.firstFrameDrawn = false;
        this.destroyed = false;
        this.state = {
            ...manifest.defaultPose,
            finish: 'black',
            mode: 'cool',
            explode: 0,
            anatomy: false,
            brightness: 10,
            wires: false,
            selection: 'all',
            isolate: false,
        };
        this.profile = manifest.profiles[this.mode === 'showroom' ? 'presentation' : 'showcase'];
        this.parts = manifest.parts.map((part) => ({ ...part }));
        this.gl = canvas.getContext('webgl', { antialias: true, alpha: true, powerPreference: 'high-performance' });

        if (!this.gl) throw new Error('WebGL is unavailable.');

        this.onContextLost = (event) => {
            event.preventDefault();
            this.pause();
            this.callbacks.onError?.(new Error('The WebGL context was lost.'));
        };
        canvas.addEventListener('webglcontextlost', this.onContextLost, false);
        this.prepareParts();
        this.prepareGraphics();
        this.updateTransforms();
        this.fit();
        this.resizeObserver = new ResizeObserver(() => this.requestDraw());
        this.resizeObserver.observe(canvas);
        this.requestDraw();
    }

    static async create(canvas, manifestUrl, callbacks = {}, signal, options = {}) {
        let modelPromise = modelCache.get(manifestUrl);
        if (!modelPromise) {
            modelPromise = (async () => {
                const manifestResponse = await fetchWithTimeout(manifestUrl);
                const manifest = await manifestResponse.json();
                const binary = await fetchModelBinary(manifest, manifestResponse, manifestUrl);

                if (binary.byteLength !== manifest.buffer.bytes || binary.byteLength % manifest.buffer.strideBytes !== 0) {
                    throw new Error('The 3D vertex buffer failed its size validation.');
                }

                return { manifest, vertices: new Float32Array(binary) };
            })().catch((error) => {
                modelCache.delete(manifestUrl);
                throw error;
            });
            modelCache.set(manifestUrl, modelPromise);
        }

        const aborted = new Promise((_, reject) => {
            signal?.addEventListener('abort', () => reject(signal.reason ?? new DOMException('Aborted', 'AbortError')), { once: true });
        });
        const { manifest, vertices } = signal ? await Promise.race([modelPromise, aborted]) : await modelPromise;

        return new DeskLampShowcaseRenderer(canvas, manifest, vertices, callbacks, options);
    }

    prepareParts() {
        for (const part of this.parts) {
            const low = [Infinity, Infinity, Infinity];
            const high = [-Infinity, -Infinity, -Infinity];
            for (let index = part.start * 6; index < (part.start + part.count) * 6; index += 6) {
                for (let axis = 0; axis < 3; axis += 1) {
                    low[axis] = Math.min(low[axis], this.vertices[index + axis]);
                    high[axis] = Math.max(high[axis], this.vertices[index + axis]);
                }
            }
            part.low = low;
            part.high = high;
            part.middle = multiply(add(low, high), 0.5);
        }
    }

    prepareGraphics() {
        const gl = this.gl;
        this.program = createProgram(gl);
        gl.useProgram(this.program);
        this.locations = {
            viewProjection: gl.getUniformLocation(this.program, 'viewProjection'),
            model: gl.getUniformLocation(this.program, 'model'),
            color: gl.getUniformLocation(this.program, 'color'),
            glow: gl.getUniformLocation(this.program, 'glow'),
            position: gl.getAttribLocation(this.program, 'position'),
            normal: gl.getAttribLocation(this.program, 'surfaceNormal'),
        };
        this.buffer = gl.createBuffer();
        gl.bindBuffer(gl.ARRAY_BUFFER, this.buffer);
        gl.bufferData(gl.ARRAY_BUFFER, this.vertices, gl.STATIC_DRAW);
        gl.enable(gl.DEPTH_TEST);
        gl.enableVertexAttribArray(this.locations.position);
        gl.enableVertexAttribArray(this.locations.normal);
        gl.vertexAttribPointer(this.locations.position, 3, gl.FLOAT, false, 24, 0);
        gl.vertexAttribPointer(this.locations.normal, 3, gl.FLOAT, false, 24, 12);
        this.wireBuffer = gl.createBuffer();
    }

    anchors() {
        const pivots = this.manifest.pivots;
        const end = add(pivots.A, multiply(direction(this.state.lower), 0.3302));
        const upperStart = add(end, subtract(pivots.S, pivots.B));
        const innerStart = add(end, subtract(pivots.T, pivots.B));
        const headStart = add(upperStart, multiply(direction(this.state.upper), 0.3302));

        return {
            end,
            upperStart,
            innerStart,
            headStart,
            headInner: add(innerStart, multiply(direction(this.state.upper), 0.3302)),
            headPivot: add(headStart, subtract(pivots.H, pivots.C)),
            lowerSpring: add(pivots.A1, multiply(direction(this.state.lower), 0.148)),
            upperSpring: add(innerStart, multiply(direction(this.state.upper), 0.160)),
            base: rotateZ(this.state.baseYaw),
        };
    }

    localMatrix(part, anchors) {
        const pivots = this.manifest.pivots;
        const name = part.name;

        if (name.startsWith('Clamp.')) {
            return /screw|cut_thread|pad_swivel|pressure_disc|rubber_disc|T_boss|sliding_handle|handle_ball/.test(name)
                ? translate([0, 0, -(this.state.jaw - 25.1) / 1000])
                : identity();
        }
        if (part.role === 'fixed') return identity();
        if (name === 'Lower.outer') return move(pivots.A, pivots.A, this.state.lower - 118);
        if (name === 'Lower.inner') return move(pivots.A1, pivots.A1, this.state.lower - 118);
        if (name === 'Upper.outer') return move(pivots.S, anchors.upperStart, this.state.upper - 47);
        if (name === 'Upper.inner') return move(pivots.T, anchors.innerStart, this.state.upper - 47);
        if (name.startsWith('Lower.spring_crosspin')) return translate(subtract(anchors.lowerSpring, add(pivots.A1, multiply(direction(118), 0.148))));
        if (name.startsWith('Upper.spring_crosspin')) return translate(subtract(anchors.upperSpring, add(pivots.T, multiply(direction(47), 0.160))));

        if (part.category === 'springs') {
            const lower = name.startsWith('Lower.');
            const sourceStart = lower ? add(pivots.A, [0.031, 0, 0.020]) : pivots.S;
            const sourceEnd = lower ? add(pivots.A1, multiply(direction(118), 0.148)) : add(pivots.T, multiply(direction(47), 0.160));
            const targetStart = lower ? sourceStart : anchors.upperStart;
            const targetEnd = lower ? anchors.lowerSpring : anchors.upperSpring;
            const angle = (Math.atan2(targetEnd[2] - targetStart[2], targetEnd[0] - targetStart[0])
                - Math.atan2(sourceEnd[2] - sourceStart[2], sourceEnd[0] - sourceStart[0])) * 180 / Math.PI;

            if (name.includes('root_eye')) return move(sourceStart, targetStart, angle);
            if (name.includes('tip_eye')) return move(sourceEnd, targetEnd, angle);

            return springMap(sourceStart, sourceEnd, targetStart, targetEnd);
        }

        if (name.startsWith('Head.')) {
            if (/fork_plate|rail_pivot|tilt_lock/.test(name)) return translate(subtract(anchors.headStart, pivots.C));
            let rotation = rotateY(-this.state.tilt);
            if (!/laminated|black_swivel/.test(name)) rotation = multiplyMatrices(rotation, rotateX(this.state.roll + 25));

            return multiplyMatrices(translate(anchors.headPivot), multiplyMatrices(rotation, translate(multiply(pivots.H, -1))));
        }
        if (name.startsWith('Elbow.')) return translate(subtract(anchors.end, pivots.B));

        return identity();
    }

    explodedOffset(part) {
        const amount = this.state.explode / 100;
        const side = part.middle[1] < 0 ? -1 : 1;
        const name = part.name;
        let value = part.role === 'lower' ? [-0.09, 0, 0.055]
            : part.role === 'upper' ? [0.045, 0, 0.10]
                : part.role === 'head' ? [0.16, 0, 0.17] : [0, 0, 0];

        if (part.category === 'clamp') {
            value = [-0.045, 0, -0.05];
            if (/screw|cut_thread|pad_swivel|T_boss|sliding_handle|handle_ball/.test(name)) value = [-0.010, 0, -0.078];
            if (/pressure_disc|rubber_disc/.test(name)) value = [-0.010, 0, -0.063];
            if (/lock_stem|lock_washer|six_lobe_knob/.test(name)) value = [-0.070, 0, -0.05];
        }
        if (part.category === 'controller') value = [-0.14, 0, 0.015];
        if (part.category === 'usb') value = [-0.15, 0, -0.07];
        if (part.category === 'cable') value = [-0.14, 0, 0.015];
        if (part.category === 'springs') value = add(value, [0, side * 0.065, 0.025]);
        if (part.category === 'hardware') value = add(value, [0, side * 0.085, 0]);
        if (/plate/.test(name)) value = add(value, [0, side * 0.036, 0]);
        if (name.endsWith('inner')) value = add(value, [0, 0.035, 0]);
        if (name === 'Head.diffuser') value = add(value, [0, -0.05, -0.027]);
        if (/aluminum_back|sidewall|longitudinal/.test(name)) value = add(value, [0, 0.025, 0.02]);

        return multiply(value, amount);
    }

    updateTransforms() {
        const anchors = this.anchors();
        this.matrices = this.parts.map((part) => {
            let matrix = this.localMatrix(part, anchors);
            if (part.role !== 'clamp' && part.role !== 'fixed') matrix = multiplyMatrices(anchors.base, matrix);

            return multiplyMatrices(translate(this.explodedOffset(part)), matrix);
        });
        this.updateWireBuffer(anchors);
    }

    isVisible(part) {
        if (part.name === 'Cable.base_to_controller') return false;
        if (part.category === 'cable' && !this.state.wires) return false;
        if (this.state.isolate && this.state.selection !== 'all' && part.category !== this.state.selection) return false;
        if (part.category === 'cable' && this.state.wires) return this.state.explode === 0;

        return !this.profile.hiddenPartPrefixes.some((prefix) => part.name.startsWith(prefix));
    }

    updateWireBuffer(anchors = this.anchors()) {
        if (!this.wireBuffer) return;
        const start = transformPoint(anchors.base, [-0.004, 0.009, 0.024]);
        const controlPoints = [
            start,
            add(start, [-0.018, 0.010, 0.035]),
            [-0.083, 0.002, 0.105],
            [-0.103, -0.026, 0.082],
        ];
        const points = [];
        const samples = 41;
        for (let index = 0; index < samples; index += 1) {
            const t = index / (samples - 1);
            const inverse = 1 - t;
            points.push([0, 1, 2].map((axis) => (
                inverse ** 3 * controlPoints[0][axis]
                + 3 * inverse ** 2 * t * controlPoints[1][axis]
                + 3 * inverse * t ** 2 * controlPoints[2][axis]
                + t ** 3 * controlPoints[3][axis]
            )));
        }
        const rings = points.map((point, index) => {
            const tangent = normalize(subtract(points[Math.min(index + 1, samples - 1)], points[Math.max(index - 1, 0)]));
            const side = normalize(cross(tangent, Math.abs(tangent[1]) < 0.9 ? [0, 1, 0] : [1, 0, 0]));
            const up = normalize(cross(side, tangent));

            return Array.from({ length: 10 }, (_, segment) => {
                const angle = Math.PI * 2 * segment / 10;
                const normal = add(multiply(side, Math.cos(angle)), multiply(up, Math.sin(angle)));
                return { position: add(point, multiply(normal, 0.00145)), normal };
            });
        });
        const vertices = [];
        const append = ({ position, normal }) => vertices.push(...position, ...normal);
        for (let ring = 0; ring < rings.length - 1; ring += 1) {
            for (let segment = 0; segment < 10; segment += 1) {
                const next = (segment + 1) % 10;
                append(rings[ring][segment]); append(rings[ring + 1][segment]); append(rings[ring + 1][next]);
                append(rings[ring][segment]); append(rings[ring + 1][next]); append(rings[ring][next]);
            }
        }
        this.wireVertexCount = vertices.length / 6;
        this.gl.bindBuffer(this.gl.ARRAY_BUFFER, this.wireBuffer);
        this.gl.bufferData(this.gl.ARRAY_BUFFER, new Float32Array(vertices), this.gl.DYNAMIC_DRAW);
    }

    fit(category = 'all') {
        const points = [];
        this.parts.forEach((part, index) => {
            if (category !== 'all' && part.category !== category) return;
            if (!this.isVisible(part)) return;
            for (const x of [part.low[0], part.high[0]]) {
                for (const y of [part.low[1], part.high[1]]) {
                    for (const z of [part.low[2], part.high[2]]) points.push(transformPoint(this.matrices[index], [x, y, z]));
                }
            }
        });
        if (!points.length) return;
        const low = [0, 1, 2].map((axis) => Math.min(...points.map((point) => point[axis])));
        const high = [0, 1, 2].map((axis) => Math.max(...points.map((point) => point[axis])));
        this.target = multiply(add(low, high), 0.5);
        this.scale = Math.max(0.055, Math.hypot(...subtract(high, low)) * 0.56);
        this.zoom = 1;
    }

    viewProjection() {
        const eye = add(this.target, [
            2 * Math.cos(this.cameraElevation) * Math.cos(this.cameraYaw),
            2 * Math.cos(this.cameraElevation) * Math.sin(this.cameraYaw),
            2 * Math.sin(this.cameraElevation),
        ]);
        const z = normalize(subtract(eye, this.target));
        const x = normalize(cross([0, 0, 1], z));
        const y = cross(z, x);
        const view = [
            x[0], y[0], z[0], 0,
            x[1], y[1], z[1], 0,
            x[2], y[2], z[2], 0,
            -dot(x, eye), -dot(y, eye), -dot(z, eye), 1,
        ];
        let height = this.scale * this.zoom;
        let width = height * this.canvas.clientWidth / Math.max(this.canvas.clientHeight, 1);
        if (width < height * 0.78) {
            height *= height * 0.78 / width;
            width = this.scale * this.zoom * 0.78;
        }
        const projection = [1 / width, 0, 0, 0, 0, 1 / height, 0, 0, 0, 0, -0.2, 0, 0, 0, -1, 1];

        return multiplyMatrices(projection, view);
    }

    draw(time = performance.now()) {
        if (this.destroyed) return;
        const gl = this.gl;
        const deviceScale = Math.min(window.devicePixelRatio || 1, 1.7);
        const width = Math.max(1, Math.round(this.canvas.clientWidth * deviceScale));
        const height = Math.max(1, Math.round(this.canvas.clientHeight * deviceScale));
        if (this.canvas.width !== width || this.canvas.height !== height) {
            this.canvas.width = width;
            this.canvas.height = height;
        }

        if (this.lastFrameTime !== null && shouldAnimate(this)) {
            this.cameraYaw += Math.min(time - this.lastFrameTime, 50) * 0.0002;
        }
        this.lastFrameTime = time;
        gl.viewport(0, 0, width, height);
        gl.clearColor(0, 0, 0, 0);
        gl.clear(gl.COLOR_BUFFER_BIT | gl.DEPTH_BUFFER_BIT);
        gl.useProgram(this.program);
        gl.bindBuffer(gl.ARRAY_BUFFER, this.buffer);
        gl.vertexAttribPointer(this.locations.position, 3, gl.FLOAT, false, 24, 0);
        gl.vertexAttribPointer(this.locations.normal, 3, gl.FLOAT, false, 24, 12);
        gl.uniformMatrix4fv(this.locations.viewProjection, false, this.viewProjection());

        this.parts.forEach((part, index) => {
            if (!this.isVisible(part)) return;
            let color = part.color;
            let glow = 0;
            const group = DESK_LAMP_GROUPS[part.category];
            const painted = ['clamp', 'lower', 'upper', 'head'].includes(part.category)
                && Math.max(...part.color) < 0.03
                && !/knob|rubber|thread|gland|black_swivel|Controller|Cable|USB/.test(part.name);
            if (this.state.anatomy && group) color = group.color.map((channel) => channel * channel * 0.7);
            else if (this.state.finish === 'white' && painted) color = [0.72, 0.76, 0.82];
            if (this.state.selection !== 'all' && part.category !== this.state.selection) color = multiply(color, 0.6);
            if (part.emissive) {
                color = this.state.mode === 'warm' ? [0.95, 0.55, 0.20]
                    : this.state.mode === 'neutral' ? [0.88, 0.74, 0.53]
                        : this.state.mode === 'off' ? [0.42, 0.46, 0.50] : [0.74, 0.82, 0.95];
                if (this.state.mode !== 'off') {
                    color = multiply(color, 0.2 + 0.08 * this.state.brightness);
                    glow = 1;
                }
            }
            gl.uniformMatrix4fv(this.locations.model, false, this.matrices[index]);
            gl.uniform3fv(this.locations.color, color);
            gl.uniform1f(this.locations.glow, glow);
            gl.drawArrays(gl.TRIANGLES, part.start, part.count);
        });

        if (this.state.wires && this.state.explode === 0 && (!this.state.isolate || ['all', 'cable'].includes(this.state.selection))) {
            gl.bindBuffer(gl.ARRAY_BUFFER, this.wireBuffer);
            gl.vertexAttribPointer(this.locations.position, 3, gl.FLOAT, false, 24, 0);
            gl.vertexAttribPointer(this.locations.normal, 3, gl.FLOAT, false, 24, 12);
            gl.uniformMatrix4fv(this.locations.model, false, identity());
            gl.uniform3fv(this.locations.color, this.state.selection === 'all' || this.state.selection === 'cable' ? [0.008, 0.009, 0.012] : [0.003, 0.003, 0.004]);
            gl.uniform1f(this.locations.glow, 0);
            gl.drawArrays(gl.TRIANGLES, 0, this.wireVertexCount);
        }

        this.publishLabels();

        if (!this.firstFrameDrawn) {
            this.firstFrameDrawn = true;
            this.callbacks.onFirstFrame?.();
        }

        if (shouldAnimate(this)) this.frame = requestAnimationFrame((nextTime) => this.draw(nextTime));
        else this.frame = null;
    }

    requestDraw() {
        if (this.destroyed || this.frame !== null) return;
        this.frame = requestAnimationFrame((time) => this.draw(time));
    }

    publishLabels() {
        if (!this.callbacks.onLabels) return;
        if (!this.state.anatomy) {
            this.callbacks.onLabels([]);
            return;
        }
        const viewProjection = this.viewProjection();
        const labels = [];
        for (const [category, group] of Object.entries(DESK_LAMP_GROUPS)) {
            const points = [];
            this.parts.forEach((part, index) => {
                if (part.category === category && this.isVisible(part)) {
                    points.push(transformPoint(this.matrices[index], part.middle));
                }
            });
            if (!points.length) continue;
            const anchor = multiply(points.reduce(add, [0, 0, 0]), 1 / points.length);
            const projected = transformPoint(viewProjection, anchor);
            labels.push({ category, label: group.label, x: (projected[0] + 1) * 50, y: (1 - projected[1]) * 50 });
        }
        this.callbacks.onLabels(labels);
    }

    stopAutoRotation() {
        this.autoRotate = false;
        this.setActive(this.active);
        this.requestDraw();
    }

    setAutoRotation(enabled) {
        this.autoRotate = Boolean(enabled);
        this.setActive(this.active);
        this.requestDraw();
    }

    orbit(deltaX, deltaY) {
        this.stopAutoRotation();
        this.cameraYaw -= deltaX * 0.008;
        this.cameraElevation = clamp(this.cameraElevation + deltaY * 0.008, [-1.45, 1.45]);
        this.requestDraw();
    }

    zoomBy(factor) {
        this.stopAutoRotation();
        this.zoom = clamp(this.zoom * factor, [0.1, 6]);
        this.requestDraw();
    }

    pan(deltaX, deltaY) {
        this.stopAutoRotation();
        this.target = add(this.target, [-deltaX * 0.001 * this.scale, 0, deltaY * 0.001 * this.scale]);
        this.requestDraw();
    }

    setFinish(finish) {
        this.stopAutoRotation();
        this.state.finish = finish === 'white' ? 'white' : 'black';
        this.requestDraw();
    }

    setLight(mode, level = 5) {
        this.stopAutoRotation();
        this.state.mode = ['warm', 'neutral', 'cool', 'off'].includes(mode) ? mode : 'cool';
        this.state.brightness = brightnessForLevel(level);
        this.requestDraw();
    }

    setSimulationBrightness(level) {
        this.stopAutoRotation();
        this.state.brightness = clamp(Math.round(Number(level)), [1, 10]);
        this.requestDraw();
    }

    setArticulation(name, value) {
        if (!(name in DESK_LAMP_LIMITS) || name === 'explode') return;
        this.stopAutoRotation();
        this.state[name] = clamp(Number(value), DESK_LAMP_LIMITS[name]);
        this.updateTransforms();
        this.requestDraw();
    }

    setExplode(value) {
        this.stopAutoRotation();
        this.state.explode = clamp(Number(value), DESK_LAMP_LIMITS.explode);
        this.updateTransforms();
        this.fit();
        this.requestDraw();
    }

    setAnatomy(enabled) {
        this.stopAutoRotation();
        this.state.anatomy = Boolean(enabled);
        this.publishLabels();
        this.requestDraw();
    }

    setWires(enabled) {
        this.stopAutoRotation();
        this.state.wires = Boolean(enabled);
        this.requestDraw();
    }

    setSelection(category) {
        this.stopAutoRotation();
        this.state.selection = category === 'all' || DESK_LAMP_GROUPS[category] ? category : 'all';
        if (this.state.selection === 'cable') this.state.wires = true;
        this.publishLabels();
        this.requestDraw();
    }

    setIsolate(enabled) {
        this.stopAutoRotation();
        this.state.isolate = Boolean(enabled);
        this.fit();
        this.publishLabels();
        this.requestDraw();
    }

    setCameraView(view) {
        this.stopAutoRotation();
        if (view === 'front') {
            this.cameraYaw = -Math.PI / 2;
            this.cameraElevation = 0;
        } else {
            this.cameraYaw = this.manifest.defaultCamera.yaw;
            this.cameraElevation = this.manifest.defaultCamera.elevation;
        }
        this.requestDraw();
    }

    fitSelection() {
        this.stopAutoRotation();
        this.fit(this.state.selection);
        this.requestDraw();
    }

    fitAll() {
        this.stopAutoRotation();
        this.state.selection = 'all';
        this.state.isolate = false;
        this.fit();
        this.requestDraw();
    }

    inspect(category, { elevation = 0 } = {}) {
        this.setSelection(category);
        this.state.isolate = category !== 'all';
        this.cameraYaw = -Math.PI / 2;
        this.cameraElevation = elevation;
        this.fit(category);
        this.requestDraw();
    }

    separateClamp() {
        this.stopAutoRotation();
        this.state.selection = 'clamp';
        this.state.isolate = true;
        this.state.explode = 100;
        this.state.jaw = 25.1;
        this.state.anatomy = false;
        this.cameraYaw = Math.PI / 2;
        this.cameraElevation = 0.06;
        this.updateTransforms();
        this.fit('clamp');
        this.requestDraw();
    }

    applyPreset(name) {
        const preset = DESK_LAMP_PRESETS[name];
        if (!preset) return;
        this.stopAutoRotation();
        [this.state.lower, this.state.upper, this.state.tilt, this.state.roll] = preset;
        this.state.baseYaw = 0;
        this.state.explode = 0;
        this.state.selection = 'all';
        this.state.isolate = false;
        this.updateTransforms();
        this.fit();
        this.requestDraw();
    }

    resetView() {
        this.stopAutoRotation();
        this.state = {
            ...this.manifest.defaultPose,
            finish: 'black',
            mode: 'cool',
            explode: 0,
            anatomy: false,
            brightness: 10,
            wires: false,
            selection: 'all',
            isolate: false,
        };
        this.cameraYaw = this.manifest.defaultCamera.yaw;
        this.cameraElevation = this.manifest.defaultCamera.elevation;
        this.updateTransforms();
        this.fit();
        this.requestDraw();
    }

    resetPose() {
        this.stopAutoRotation();
        for (const name of ['lower', 'upper', 'tilt', 'roll', 'baseYaw', 'jaw']) {
            this.state[name] = this.manifest.defaultPose[name];
        }
        Object.assign(this.state, {
            explode: 0,
            anatomy: false,
            wires: false,
            selection: 'all',
            isolate: false,
        });
        this.cameraYaw = this.manifest.defaultCamera.yaw;
        this.cameraElevation = this.manifest.defaultCamera.elevation;
        this.updateTransforms();
        this.fit();
        this.requestDraw();
    }

    snapshot() {
        return {
            cameraYaw: this.cameraYaw,
            cameraElevation: this.cameraElevation,
            zoom: this.zoom,
            autoRotate: this.autoRotate,
            state: { ...this.state },
        };
    }

    setActive(active) {
        this.active = active;
        this.lastFrameTime = null;
        if (shouldAnimate(this)) this.requestDraw();
        else this.pause();
    }

    setDocumentVisible(visible) {
        this.documentVisible = visible;
        this.setActive(this.active);
    }

    setReducedMotion(reducedMotion) {
        this.reducedMotion = reducedMotion;
        this.setActive(this.active);
        this.requestDraw();
    }

    pause() {
        if (this.frame !== null) cancelAnimationFrame(this.frame);
        this.frame = null;
    }

    destroy() {
        if (this.destroyed) return;
        this.destroyed = true;
        this.pause();
        this.resizeObserver?.disconnect();
        this.canvas.removeEventListener('webglcontextlost', this.onContextLost);
        this.gl.deleteBuffer(this.buffer);
        this.gl.deleteBuffer(this.wireBuffer);
        this.gl.deleteProgram(this.program);
        this.gl.getExtension('WEBGL_lose_context')?.loseContext();
    }
}
