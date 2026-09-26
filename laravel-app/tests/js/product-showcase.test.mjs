import assert from 'node:assert/strict';
import test from 'node:test';

import { shouldAnimate } from '../../resources/js/desk-lamp-renderer.js';

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
