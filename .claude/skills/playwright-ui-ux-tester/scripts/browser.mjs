// Shared Playwright helpers for the playwright-ui-ux-tester skill.
// Import what you need in a throwaway script per workflow — see
// references/workflows.md for full examples of each of the 5 workflows.

import { chromium } from 'playwright';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const LOCAL_DIR = path.join(__dirname, '..', 'local');
const CREDENTIALS_PATH = path.join(LOCAL_DIR, 'test-credentials.json');
const SESSIONS_DIR = path.join(LOCAL_DIR, 'sessions');

export const BASE_URL = process.env.PW_BASE_URL || 'http://pharmacy.local';

// Matches the breakpoint convention documented in assets/css/style.css
// (:root comment). Each viewport is picked 1px below the threshold so
// the "mobile" behavior for that breakpoint is actually active.
export const BREAKPOINTS = {
    desktop: { width: 1280, height: 800 },          // > 1200px
    tabletLandscape: { width: 991, height: 800 },    // <= 991.98px: sidebar becomes off-canvas drawer
    tabletPortrait: { width: 767, height: 900 },     // <= 768px
    phone: { width: 390, height: 844 },              // <= 480px
};

/**
 * Launch a browser + page with console/pageerror capture wired up.
 * Always check `consoleErrors` before declaring a workflow a pass —
 * a page can render its shell fine while a data fetch silently 500s.
 */
export async function launch(viewport = BREAKPOINTS.desktop, { storageState } = {}) {
    const browser = await chromium.launch();
    const context = await browser.newContext({ viewport, storageState });
    const page = await context.newPage();

    const consoleErrors = [];
    page.on('console', (msg) => {
        if (msg.type() === 'error') consoleErrors.push(msg.text());
    });
    page.on('pageerror', (err) => consoleErrors.push(String(err)));

    return { browser, context, page, consoleErrors };
}

export function loadCredentials(role) {
    if (!fs.existsSync(CREDENTIALS_PATH)) {
        throw new Error(
            `Missing ${CREDENTIALS_PATH}.\n` +
            `Copy local/test-credentials.example.json to local/test-credentials.json ` +
            `and fill in real test-account credentials. That file is gitignored — never commit it.`
        );
    }
    const all = JSON.parse(fs.readFileSync(CREDENTIALS_PATH, 'utf8'));
    const creds = all[role];
    if (!creds) {
        throw new Error(`No credentials for role "${role}" in ${CREDENTIALS_PATH}`);
    }
    return creds;
}

/**
 * Logs in via the real login form (app/auth/login.php uses field
 * names "login" and "password" — see references/project-facts.md).
 * Reuse the returned storage state across runs with saveStorageState()
 * so you aren't re-logging-in for every workflow invocation.
 */
export async function login(page, role) {
    const { username, password } = loadCredentials(role);
    await page.goto(`${BASE_URL}/app/auth/login.php`);
    await page.fill('input[name="login"]', username);
    await page.fill('input[name="password"]', password);
    await Promise.all([
        page.waitForNavigation(),
        page.click('button.login-btn'),
    ]);
}

export async function saveStorageState(context, role) {
    fs.mkdirSync(SESSIONS_DIR, { recursive: true });
    const file = path.join(SESSIONS_DIR, `${role}.json`);
    await context.storageState({ path: file });
    return file;
}

export function sessionPath(role) {
    return path.join(SESSIONS_DIR, `${role}.json`);
}

export function hasSavedSession(role) {
    return fs.existsSync(sessionPath(role));
}

/**
 * The core primitive for Workflows 1 & 2 (responsive sweep, overlay/
 * drawer state). A crude bounding-box overlap check flags too many
 * false positives — the navbar is DELIBERATELY layered above the
 * drawer (see the z-index scale note in assets/css/style.css) so its
 * toggle/bell stay clickable while the drawer is open. What actually
 * matters is whether a specific element's content ends up physically
 * painted over and hidden, which is exactly the bug this skill was
 * created to catch (the mobile sidebar logo getting hidden behind
 * navbar-custom).
 *
 * Samples a few points across the element (not just the center, which
 * might legitimately be transparent padding) and asks the browser
 * what's really on top there via elementFromPoint.
 */
export async function isElementUncovered(page, selector) {
    return page.evaluate((sel) => {
        const el = document.querySelector(sel);
        if (!el) return { found: false };

        const rect = el.getBoundingClientRect();
        if (rect.width === 0 || rect.height === 0) {
            return { found: true, visible: false, reason: 'zero-size' };
        }

        const points = [
            [rect.left + rect.width / 2, rect.top + rect.height / 2],
            [rect.left + Math.min(4, rect.width - 1), rect.top + Math.min(4, rect.height - 1)],
            [rect.right - Math.min(4, rect.width - 1), rect.bottom - Math.min(4, rect.height - 1)],
        ];

        for (const [x, y] of points) {
            if (x < 0 || y < 0 || x > window.innerWidth || y > window.innerHeight) continue;
            const top = document.elementFromPoint(x, y);
            if (top && (el === top || el.contains(top) || top.contains(el))) {
                return { found: true, visible: true };
            }
        }

        return { found: true, visible: false, reason: 'covered-by-another-element' };
    }, selector);
}

/**
 * The core primitive for Workflow 4 (live/dynamic widgets). Waits for
 * a real DOM mutation instead of a fixed sleep — a fixed sleep either
 * races the update (flaky fail) or wastes time padding it out.
 * Capture `previousText` with page.textContent(selector) before the
 * action that should trigger the update.
 */
export async function waitForTextChange(page, selector, previousText, timeout = 5000) {
    await page.waitForFunction(
        ({ sel, prev }) => document.querySelector(sel)?.textContent !== prev,
        { sel: selector, prev: previousText },
        { timeout }
    );
}
