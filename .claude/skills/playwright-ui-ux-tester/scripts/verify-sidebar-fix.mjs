import fs from 'node:fs';
import {
    launch, login, saveStorageState, BREAKPOINTS, isElementUncovered, BASE_URL,
} from './browser.mjs';

const OUT = '../out';
fs.mkdirSync(OUT, { recursive: true });

// Get an authenticated session once, reuse across breakpoints.
const { browser: loginBrowser, context: loginContext, page: loginPage } = await launch();
await login(loginPage, 'admin');
const storageState = await saveStorageState(loginContext, 'admin');
await loginBrowser.close();

const url = `${BASE_URL}/app/dashboard/analytics/index.php`;
const checks = ['.sidebar-logo', '.navbar-custom', '#sidebar-toggle'];
const results = {};

for (const [name, viewport] of Object.entries(BREAKPOINTS)) {
    const { browser, page, consoleErrors } = await launch(viewport, { storageState });
    await page.goto(url);
    await page.waitForSelector('.sidebar-logo');

    const isMobile = viewport.width < 992;
    if (isMobile) {
        await page.click('#sidebar-toggle');
        await page.waitForSelector('.sidebar.mobile-open');
        // The class is added instantly but the drawer slides in over
        // .3s ease (see sidebar.css) — wait for the transform to
        // actually finish, not just the class to be present.
        await page.waitForFunction(
            () => document.getElementById('sidebar').getBoundingClientRect().left === 0
        );
    }

    await page.screenshot({ path: `${OUT}/responsive-${name}.png` });

    results[name] = { viewport, drawerOpened: isMobile, checks: {}, consoleErrors };
    for (const sel of checks) {
        results[name].checks[sel] = await isElementUncovered(page, sel);
    }

    await browser.close();
}

console.log(JSON.stringify(results, null, 2));
