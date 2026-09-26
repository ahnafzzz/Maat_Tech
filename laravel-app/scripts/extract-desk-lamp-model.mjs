import { createHash } from 'node:crypto';
import { mkdir, readFile, writeFile } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const scriptDirectory = path.dirname(fileURLToPath(import.meta.url));
const applicationRoot = path.resolve(scriptDirectory, '..');
const sourcePath = path.resolve(process.argv[2] ?? path.join(applicationRoot, '..', 'desk_lamp_viewer_final_final.html'));
const outputDirectory = path.resolve(applicationRoot, 'public', 'assets', 'models', 'desk-lamp');
const expectedSourceHash = '32f54a79d4a3e6fe96fb8ec207c7c299bb9e15cf8df6ef11cc900dcfdf0639d6';
const modelOpen = '<script id="model" type="application/json">';

const sha256 = (value) => createHash('sha256').update(value).digest('hex');
const category = (name) => {
    if (name.startsWith('Clamp.')) return 'clamp';
    if (name.startsWith('Controller.')) return 'controller';
    if (name.startsWith('USB.')) return 'usb';
    if (name.startsWith('Cable.')) return 'cable';
    if (/coil|_eye|_transition/.test(name)) return 'springs';
    if (/pivot|crosspin|upper_lock|tilt_lock/.test(name) && !name.includes('swivel')) return 'hardware';
    if (name.startsWith('Head.')) return 'head';
    if (name.startsWith('Upper.') || name.startsWith('Elbow.')) return 'upper';

    return 'lower';
};
const role = (name) => {
    if (name.startsWith('Clamp.')) return 'clamp';
    if (name.startsWith('Controller.') || name.startsWith('USB.') || name === 'Cable.USB_lead') return 'fixed';
    if (name.startsWith('Head.')) return 'head';
    if (name.startsWith('Elbow.') || name.startsWith('Upper.')) return 'upper';

    return 'lower';
};

const source = await readFile(sourcePath);
const sourceHash = sha256(source);

if (sourceHash !== expectedSourceHash) {
    throw new Error(`Refusing unverified viewer source ${sourceHash}; expected ${expectedSourceHash}.`);
}

const html = source.toString('utf8');
const payloadStart = html.indexOf(modelOpen);
const jsonStart = payloadStart + modelOpen.length;
const jsonEnd = html.indexOf('</script>', jsonStart);

if (payloadStart < 0 || jsonEnd < 0) {
    throw new Error('The embedded model payload could not be located.');
}

const embedded = JSON.parse(html.slice(jsonStart, jsonEnd));
const binary = Buffer.from(embedded.buffer, 'base64');
const strideBytes = 24;

if (binary.byteLength % strideBytes !== 0) {
    throw new Error('The interleaved position/normal buffer is not aligned to its 24-byte stride.');
}

const vertices = new Float32Array(binary.buffer, binary.byteOffset, binary.byteLength / 4);
if (!vertices.every(Number.isFinite)) {
    throw new Error('The vertex buffer contains a non-finite value.');
}

if (!Array.isArray(embedded.parts) || embedded.parts.length !== 172) {
    throw new Error(`Expected 172 model parts, received ${embedded.parts?.length ?? 0}.`);
}

const vertexCount = binary.byteLength / strideBytes;
for (const part of embedded.parts) {
    if (!Number.isInteger(part.start) || !Number.isInteger(part.count) || part.start < 0 || part.count <= 0) {
        throw new Error(`Invalid draw range for ${part.name}.`);
    }
    if (part.start + part.count > vertexCount) {
        throw new Error(`Draw range for ${part.name} exceeds the extracted vertex buffer.`);
    }
}

for (const expectedName of ['Lower.outer', 'Upper.outer', 'Head.diffuser', 'Controller.lower_shell', 'Cable.USB_lead', 'Clamp.six_lobe_knob']) {
    if (!embedded.parts.some((part) => part.name === expectedName)) {
        throw new Error(`Expected fidelity-critical part ${expectedName} is missing.`);
    }
}

for (const pivot of ['A', 'A1', 'B', 'B1', 'S', 'T', 'C', 'C1', 'H']) {
    if (!Array.isArray(embedded.pivots?.[pivot]) || embedded.pivots[pivot].length !== 3) {
        throw new Error(`Expected pivot ${pivot} is missing or malformed.`);
    }
}

const binaryHash = sha256(binary);
const binaryName = `desk-lamp.${binaryHash.slice(0, 16)}.bin`;
const metadata = {
    schemaVersion: 1,
    modelId: 'maat-led-swing-arm-desk-lamp-v1',
    source: {
        file: path.basename(sourcePath),
        sha256: sourceHash,
        bytes: source.byteLength,
    },
    buffer: {
        url: binaryName,
        sha256: binaryHash,
        bytes: binary.byteLength,
        strideBytes,
        vertexCount,
    },
    parts: embedded.parts.map((part) => ({
        ...part,
        category: category(part.name),
        role: role(part.name),
    })),
    pivots: embedded.pivots,
    profiles: {
        showcase: {
            hiddenPartPrefixes: ['Cable.'],
            proceduralExternalLead: false,
            interactive: false,
        },
        presentation: {
            hiddenPartPrefixes: ['Cable.'],
            proceduralExternalLead: false,
            interactive: true,
        },
        engineering: {
            hiddenPartPrefixes: [],
            proceduralExternalLead: true,
            interactive: true,
        },
    },
    defaultPose: {
        lower: 118,
        upper: 47,
        tilt: 0,
        roll: -25,
        baseYaw: 0,
        jaw: 25.1,
        lightMode: 'cool',
        brightness: 10,
    },
    defaultCamera: {
        yaw: -1.25,
        elevation: 0.18,
        target: [0.06, 0, 0.24],
        scale: 0.52,
    },
};
const metadataBody = `${JSON.stringify(metadata)}\n`;
const metadataHash = sha256(metadataBody);
const metadataName = `desk-lamp.${metadataHash.slice(0, 16)}.json`;

await mkdir(outputDirectory, { recursive: true });
await writeFile(path.join(outputDirectory, binaryName), binary);
await writeFile(path.join(outputDirectory, metadataName), metadataBody);

console.log(JSON.stringify({
    source: { path: sourcePath, bytes: source.byteLength, sha256: sourceHash },
    binary: { path: path.join(outputDirectory, binaryName), bytes: binary.byteLength, sha256: binaryHash },
    metadata: { path: path.join(outputDirectory, metadataName), bytes: Buffer.byteLength(metadataBody), sha256: metadataHash },
    parts: metadata.parts.length,
    vertices: vertexCount,
}, null, 2));
