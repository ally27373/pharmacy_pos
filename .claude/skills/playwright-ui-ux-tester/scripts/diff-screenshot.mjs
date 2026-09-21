// Pixel-diff helper for Workflow 5 (visual regression). Kept separate
// from browser.mjs since it has nothing to do with driving a page —
// it just compares two PNGs already on disk.

import fs from 'node:fs';
import { PNG } from 'pngjs';
import pixelmatch from 'pixelmatch';

/**
 * Compares baselinePath against currentPath, writes a highlighted
 * diff image to diffPath, and returns how much changed.
 * Throws if the two images aren't the same size (that alone is worth
 * surfacing — it usually means the viewport or layout shifted).
 */
export function diffPng(baselinePath, currentPath, diffPath, { threshold = 0.1 } = {}) {
    const baseline = PNG.sync.read(fs.readFileSync(baselinePath));
    const current = PNG.sync.read(fs.readFileSync(currentPath));

    if (baseline.width !== current.width || baseline.height !== current.height) {
        throw new Error(
            `Size mismatch: baseline is ${baseline.width}x${baseline.height}, ` +
            `current is ${current.width}x${current.height}. Re-capture the baseline ` +
            `if this viewport/layout change is intentional.`
        );
    }

    const { width, height } = baseline;
    const diff = new PNG({ width, height });
    const changedPixels = pixelmatch(
        baseline.data, current.data, diff.data, width, height, { threshold }
    );

    fs.writeFileSync(diffPath, PNG.sync.write(diff));

    const totalPixels = width * height;
    return { changedPixels, totalPixels, changedRatio: changedPixels / totalPixels };
}
