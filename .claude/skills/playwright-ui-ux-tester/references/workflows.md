# The 5 workflows

Each workflow below is a recipe, not a rigid script to run unmodified
— adapt selectors/pages to whatever the user is actually asking about.
Write a small throwaway `.mjs` file under `scripts/` (or in a scratch
dir), import what you need from `browser.mjs` / `diff-screenshot.mjs`,
run it with `node`, and read the output before reporting anything.
Delete throwaway scripts when you're done unless the user wants them
kept.

All examples assume `cd` into
`.claude/skills/playwright-ui-ux-tester/scripts` first, since that's
where `node_modules` and the helpers live.

---

## 1. Responsive Layout Sweep

**When:** a CSS/layout change was made anywhere in the app shell or a
page, and you want to know it doesn't break at other breakpoints —
this is the workflow that would have caught the original mobile
sidebar-logo bug, and the one you re-run to prove a fix worked.

**How:**

```js
import { launch, BREAKPOINTS, isElementUncovered } from './browser.mjs';

const url = 'http://pharmacy.local/app/dashboard/analytics/index.php';
const checks = ['.sidebar-logo', '.navbar-custom', '#sidebar-toggle'];

for (const [name, viewport] of Object.entries(BREAKPOINTS)) {
    const { browser, page, consoleErrors } = await launch(viewport);
    await page.goto(url);

    // Open the drawer at mobile widths so the sidebar is actually
    // visible to check — it's off-screen by default below 992px.
    if (viewport.width < 992) {
        await page.click('#sidebar-toggle');
        await page.waitForSelector('.sidebar.mobile-open');
    }

    await page.screenshot({ path: `../out/responsive-${name}.png`, fullPage: false });

    for (const sel of checks) {
        console.log(name, sel, await isElementUncovered(page, sel));
    }
    console.log(name, 'console errors:', consoleErrors);

    await browser.close();
}
```

**Report:** for each breakpoint × selector, whether it's
`visible: true`. Anything `false` with `reason: 'covered-by-another-element'`
is a real find — say which two elements are involved (you can get the
covering element's selector/class by extending the `elementFromPoint`
call). Always look at the screenshots yourself before declaring a
breakpoint clean; the uncovered-check catches clipping but not every
visual problem (e.g. text overflow, awkward wrapping).

---

## 2. Interactive Overlay & Drawer State Testing

**When:** testing anything that toggles visibility/z-index — the
mobile drawer, the notification panel, a modal, a dropdown. These bugs
only exist in the *open* state, so a static screenshot of the closed
page proves nothing.

**How, using the sidebar drawer as the example:**

```js
import { launch, BREAKPOINTS, isElementUncovered } from './browser.mjs';

const { browser, page } = await launch(BREAKPOINTS.phone);
await page.goto('http://pharmacy.local/app/dashboard/analytics/index.php');

// Closed state: toggle must be visible and clickable.
console.log('toggle before open:', await isElementUncovered(page, '#sidebar-toggle'));

await page.click('#sidebar-toggle');
await page.waitForSelector('.sidebar.mobile-open');
// The class is added instantly, but the drawer slides in over .3s
// ease (assets/css/sidebar.css) — waitForSelector on the class alone
// resolves mid-animation and screenshots a half-open drawer. Wait for
// the transform to actually finish:
await page.waitForFunction(
    () => document.getElementById('sidebar').getBoundingClientRect().left === 0
);
await page.screenshot({ path: '../out/drawer-open.png' });

// Open state: the toggle must STILL be reachable (it's how you close
// the drawer), and the drawer's own content must be uncovered.
console.log('toggle while open:', await isElementUncovered(page, '#sidebar-toggle'));
console.log('logo while open:', await isElementUncovered(page, '.sidebar-logo'));

// Dismiss via backdrop click.
await page.click('#sidebar-backdrop');
await page.waitForSelector('.sidebar:not(.mobile-open)');

// Reopen, dismiss via Escape this time.
await page.click('#sidebar-toggle');
await page.waitForSelector('.sidebar.mobile-open');
await page.keyboard.press('Escape');
await page.waitForSelector('.sidebar:not(.mobile-open)');

await browser.close();
```

For the notification panel, modals, or any other overlay: same shape
— open it, screenshot, check the trigger and the panel's own controls
(e.g. the refresh/close buttons) are uncovered, confirm both dismiss
paths work, close.

**Report:** which open/close paths work, and whether anything that
should stay clickable while the overlay is open actually is
(`isElementUncovered`, not just "the element exists in the DOM" —
`display:none` vs. visually-covered-but-present are different bugs
and worth distinguishing in the report).

---

## 3. Authenticated Role-Based Flow Check

**When:** a change affects what different roles see — sidebar menu
items, page access, redirects — or you just want to confirm a page
works for a real logged-in session rather than bouncing to the login
form.

**How:**

```js
import { launch, login, saveStorageState, hasSavedSession, sessionPath } from './browser.mjs';

async function sessionFor(role) {
    if (hasSavedSession(role)) return sessionPath(role);
    const { browser, context, page } = await launch();
    await login(page, role);
    const file = await saveStorageState(context, role);
    await browser.close();
    return file;
}

for (const role of ['admin', 'cashier']) {
    const storageState = await sessionFor(role);
    const { browser, page, consoleErrors } = await launch(undefined, { storageState });

    await page.goto('http://pharmacy.local/app/dashboard/pos/index.php?page=terminal');
    await page.screenshot({ path: `../out/role-${role}.png` });

    // Admin-only sidebar items should be absent for cashier.
    const hasUserMgmt = await page.locator('a[href*="/dashboard/users/"]').count();
    console.log(role, 'sees User Management:', hasUserMgmt > 0);
    console.log(role, 'console errors:', consoleErrors);

    await browser.close();
}
```

Reuse the saved `storageState` across a whole testing session instead
of logging in fresh every time — it's both faster and avoids hammering
the login form. Delete `local/sessions/*.json` if a session goes stale
(e.g. after a logout-everywhere or password change).

**Report:** what each role can/can't see or reach, plus any console
errors — a page can look fine to a human and still be silently failing
a fetch in the background.

---

## 4. Live/Dynamic Widget Verification

**When:** testing anything that updates without a full page reload —
the navbar clock, the notification badge after a refresh click, the
SARIMA forecast chart, POS cart totals after adding an item. The
point of this workflow is proving the update *actually happens*, not
just that the initial render looks right.

**How, using the navbar clock as the simplest example:**

```js
import { launch, waitForTextChange } from './browser.mjs';

const { browser, page } = await launch();
await page.goto('http://pharmacy.local/app/dashboard/analytics/index.php');

const before = await page.textContent('#navbar-datetime-text');
await waitForTextChange(page, '#navbar-datetime-text', before, 3000);
const after = await page.textContent('#navbar-datetime-text');
console.log('clock ticked:', before, '->', after);

await browser.close();
```

For an action-triggered widget (cart totals, notification badge after
clicking refresh), capture the "before" text, perform the action
(`page.click(...)`, `page.fill(...)`), then `waitForTextChange` on the
element that should reflect it — never a fixed `sleep`, since that
either races the real update or wastes time.

For the SARIMA chart, there's no text to diff — check that the
canvas/SVG element exists, has non-zero size, and (if it's Chart.js/
similar) that its legend/data-point elements are present:

```js
const box = await page.locator('canvas, svg').first().boundingBox();
console.log('chart rendered:', box && box.width > 0 && box.height > 0);
```

**Report:** whether the update actually happened and how long it took
to appear (useful if something feels sluggish), plus `consoleErrors`
— a silent fetch failure often looks identical to "nothing changed."

---

## 5. Visual Regression Snapshot Diff

**When:** after any CSS/JS change to the app shell or a page, as a
safety net for regressions the other 4 workflows aren't specifically
looking for. Most useful once a baseline library exists — the first
run for a given page/breakpoint just establishes the baseline.

**How:**

```js
import fs from 'node:fs';
import { launch, BREAKPOINTS } from './browser.mjs';
import { diffPng } from './diff-screenshot.mjs';

const page_ = 'analytics-dashboard'; // slug for filenames
const url = 'http://pharmacy.local/app/dashboard/analytics/index.php';

for (const [name, viewport] of Object.entries(BREAKPOINTS)) {
    const { browser, page } = await launch(viewport);
    await page.goto(url);

    const baselineDir = `../baselines/${page_}`;
    fs.mkdirSync(baselineDir, { recursive: true });
    const baselinePath = `${baselineDir}/${name}.png`;
    const currentPath = `../out/${page_}-${name}.png`;

    await page.screenshot({ path: currentPath, fullPage: true });

    if (!fs.existsSync(baselinePath)) {
        fs.copyFileSync(currentPath, baselinePath);
        console.log(name, 'no baseline yet — saved this run as the baseline.');
    } else {
        const result = diffPng(baselinePath, currentPath, `../out/${page_}-${name}-diff.png`);
        console.log(name, result);
    }

    await browser.close();
}
```

**Report:** the `changedRatio` per breakpoint, and — for anything
above ~1-2% — actually look at the `-diff.png` (pixelmatch highlights
changed pixels in red) before deciding whether it's a real regression
or an expected change from the work just done. If it's expected,
overwrite the baseline (delete the old PNG and rerun) rather than
leaving the diff unresolved.

Baselines live in `../baselines/<page-slug>/<breakpoint>.png` and are
meant to be committed — that's what makes them useful as a record over
time, not just within one session.
