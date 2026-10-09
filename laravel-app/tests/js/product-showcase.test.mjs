import assert from 'node:assert/strict';
import test from 'node:test';

import {
    DESK_LAMP_GROUPS,
    DESK_LAMP_LIMITS,
    DESK_LAMP_PRESETS,
    powerModeLevel,
    shouldAnimate,
} from '../../resources/js/desk-lamp-renderer.js';
import { shouldCaptureOrbit } from '../../resources/js/product-showroom.js';
import { slideshowShouldAdvance } from '../../resources/js/storefront-slideshow.js';
import { galleryNextIndex } from '../../resources/js/product-media-gallery.js';

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

test('a customer can explicitly opt into rotation without changing reduced-motion preference', () => {
    assert.equal(shouldAnimate({
        active: true,
        documentVisible: true,
        reducedMotion: true,
        motionOverride: true,
    }), true);
    assert.equal(shouldAnimate({
        active: true,
        documentVisible: false,
        reducedMotion: true,
        motionOverride: true,
    }), false);
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

test('power mode maps directly to the supplied renderer levels one through ten', () => {
    assert.deepEqual([1, 2, 3, 4, 5, 6, 7, 8, 9, 10].map(powerModeLevel), [1, 2, 3, 4, 5, 6, 7, 8, 9, 10]);
    assert.equal(powerModeLevel(0), 1);
    assert.equal(powerModeLevel(11), 10);
});

test('slideshow automation pauses for reduced motion, hidden tabs, and offscreen content', () => {
    assert.equal(slideshowShouldAdvance({ documentVisible: true, onscreen: true, reducedMotion: false }), true);
    assert.equal(slideshowShouldAdvance({ documentVisible: false, onscreen: true, reducedMotion: false }), false);
    assert.equal(slideshowShouldAdvance({ documentVisible: true, onscreen: false, reducedMotion: false }), false);
    assert.equal(slideshowShouldAdvance({ documentVisible: true, onscreen: true, reducedMotion: true }), false);
    assert.equal(slideshowShouldAdvance({ documentVisible: true, onscreen: true, reducedMotion: false, focusInside: true }), false);
});

test('media tab keyboard navigation wraps and supports first and last shortcuts', () => {
    assert.equal(galleryNextIndex(0, 4, 'ArrowLeft'), 3);
    assert.equal(galleryNextIndex(3, 4, 'ArrowRight'), 0);
    assert.equal(galleryNextIndex(2, 4, 'Home'), 0);
    assert.equal(galleryNextIndex(1, 4, 'End'), 3);
});

test('engineering inspection exposes every original component group', () => {
    assert.deepEqual(Object.keys(DESK_LAMP_GROUPS), [
        'clamp', 'lower', 'upper', 'head', 'springs', 'hardware', 'controller', 'cable', 'usb',
    ]);
    assert.ok(Object.values(DESK_LAMP_GROUPS).every((group) => group.label && group.description));
});
