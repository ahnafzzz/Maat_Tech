import assert from 'node:assert/strict';
import test from 'node:test';

import {
    brightnessForLevel,
    DESK_LAMP_LIMITS,
    DESK_LAMP_PRESETS,
    shouldAnimate,
} from '../../resources/js/desk-lamp-renderer.js';
import { shouldCaptureOrbit } from '../../resources/js/product-showroom.js';

test('rotation runs only while visible, foregrounded, and motion is allowed', () => {
    assert.equal(shouldAnimate({ active: true, documentVisible: true, reducedMotion: false }), true);
    assert.equal(shouldAnimate({ active: false, documentVisible: true, reducedMotion: false }), false);
    assert.equal(shouldAnimate({ active: true, documentVisible: false, reducedMotion: false }), false);
    assert.equal(shouldAnimate({ active: true, documentVisible: true, reducedMotion: true }), false);
});

test('hidden tab and reduced motion always override active visibility', () => {
    for (const reducedMotion of [false, true]) {
        assert.equal(shouldAnimate({ active: true, documentVisible: false, reducedMotion }), false);
    }
    for (const documentVisible of [false, true]) {
        assert.equal(shouldAnimate({ active: true, documentVisible, reducedMotion: true }), false);
    }
});

test('customer interaction permanently pauses automatic rotation', () => {
    assert.equal(shouldAnimate({ active: true, documentVisible: true, reducedMotion: false, autoRotate: false }), false);
});

test('touch orbit captures horizontal intent without trapping vertical scrolling', () => {
    assert.equal(shouldCaptureOrbit(12, 2), true);
    assert.equal(shouldCaptureOrbit(2, 12), false);
    assert.equal(shouldCaptureOrbit(6, 1), false);
});

test('engineering controls expose only the supplied viewer limits and poses', () => {
    assert.deepEqual(DESK_LAMP_LIMITS.jaw, [4, 53.3]);
    assert.deepEqual(DESK_LAMP_LIMITS.baseYaw, [-170, 170]);
    assert.deepEqual(DESK_LAMP_PRESETS.folded, [98, -85, 88, 0]);
});

test('five customer brightness levels map to the supplied renderer scale', () => {
    assert.deepEqual([1, 2, 3, 4, 5].map(brightnessForLevel), [2, 4, 6, 8, 10]);
    assert.equal(brightnessForLevel(0), 2);
    assert.equal(brightnessForLevel(9), 10);
});
