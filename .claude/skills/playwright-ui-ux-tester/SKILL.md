---
name: playwright-ui-ux-tester
description: Drives the pharmacy_pos app running locally via XAMPP at http://pharmacy.local with Playwright to test DYNAMIC UI/UX — things that only break when the page moves, resizes, or depends on state: responsive layouts at the app's breakpoints (1200/992/768/480px), the mobile off-canvas sidebar drawer, modals/dropdowns/notification panel, role-based rendering (admin vs cashier), live-updating widgets (navbar clock, notification badge, SARIMA forecast chart, POS cart totals), and visual regressions against saved screenshots. Use this whenever the user asks to test, verify, check, or "make sure it works" for a UI/UX or responsive/mobile change, asks how something looks on mobile/tablet, wants a screenshot of the running app, or wants to re-verify a CSS/JS fix to includes/navbar.php, includes/sidebar.php, assets/css/*, or any dashboard page — even if they never say "Playwright" or "test" explicitly. Do NOT use this for pure backend/PHP logic changes with no visual or interactive surface, and don't use it to start/stop XAMPP — it assumes the app is already running.
---

# Playwright UI/UX Tester (pharmacy_pos)

This skill exists because of a real bug: the mobile sidebar drawer's
logo was getting silently hidden behind the navbar bar, and there was
no way to catch that other than a human opening dev tools. A static
HTML fetch or a screenshot of the closed page would never have caught
it — the bug only existed in a *state* (drawer open, narrow viewport).
That's the class of problem this skill targets: **dynamic** UI/UX,
not "does the page render."

Read `references/project-facts.md` once per session before writing
any script — it has the base URL, login form fields, breakpoint
convention, shell selectors, and z-index scale so you're not
rediscovering them from the CSS every time. Read
`references/workflows.md` for the runnable recipe for whichever of
the 5 workflows below fits the task.

## One-time setup (check before assuming it's needed)

```bash
cd .claude/skills/playwright-ui-ux-tester/scripts
npm install                      # playwright, pixelmatch, pngjs
npx playwright install chromium  # downloads the browser binary, ~150MB, one-time
```

Skip steps that are already done — check `node_modules/` and
`%LOCALAPPDATA%\ms-playwright\` (or the Linux/mac equivalent) exist
before re-running. If XAMPP isn't serving `http://pharmacy.local`, say
so and stop — don't try to start Apache/MySQL yourself.

For any workflow that needs to be logged in (3, and often 1/2/4 since
most dashboard pages require auth), copy
`local/test-credentials.example.json` to `local/test-credentials.json`
and ask the user to fill in real test-account credentials if it's not
already there. That file is gitignored — never hardcode credentials in
a script, and never put them in `SKILL.md` or any committed file.

## The 5 workflows

1. **Responsive Layout Sweep** — walk the app's breakpoints
   (1200/992/768/480px) for a page, screenshot each, and check that
   specific elements (e.g. the sidebar logo) aren't silently painted
   over by something else. This is the one that would have caught the
   original bug — run it on any layout/CSS change.
2. **Interactive Overlay & Drawer State Testing** — open the mobile
   drawer / a modal / the notification panel, confirm it and its
   controls stay visible and reachable, and confirm both dismiss paths
   (backdrop click, Escape) actually close it.
3. **Authenticated Role-Based Flow Check** — log in as admin and/or
   cashier and confirm role-conditional UI (sidebar items, page
   access) is correct for each, with no console errors.
4. **Live/Dynamic Widget Verification** — prove a widget that updates
   without a reload (clock, badge, chart, cart total) actually
   updates, by waiting for a real DOM mutation rather than sleeping a
   fixed amount of time.
5. **Visual Regression Snapshot Diff** — screenshot a page per
   breakpoint, diff against a saved baseline in `baselines/`, and flag
   anything that changed more than expected.

Full runnable code for each is in `references/workflows.md`. Don't
run all 5 by default — pick whichever matches what actually changed
or what the user is asking about. A CSS-only tweak to the navbar
probably only needs 1 and 2; a new role permission needs 3; a new
chart or async update needs 4; "did my fix work" after any of the
above benefits from 5 if a baseline already exists.

## How to drive it

There's no bundled all-purpose CLI here on purpose — the app has many
different pages and interactions, and a script tailored to the actual
page/selector under test is more reliable than a generic one trying to
guess. Write a small `.mjs` file (see `references/workflows.md` for
the shape), import from `scripts/browser.mjs` /
`scripts/diff-screenshot.mjs`, and run it with `node your-script.mjs`
from inside `scripts/`. Delete throwaway scripts when done unless the
user wants them kept as a regression check going forward — if you
notice yourself writing near-identical scripts across several
invocations, that's a signal to promote the common part into
`browser.mjs` instead of copy-pasting it again.

## Reporting results

- **Look at the screenshots.** A script exiting 0 proves the page
  didn't crash, not that it looks right — actually view the PNGs
  before declaring success, the same way you would with any browser
  screenshot.
- **Always check `consoleErrors`** from `launch()`. A page can render
  its shell perfectly while a data fetch silently fails.
- State findings as pass/fail per check, not raw Playwright log dumps
  — "sidebar logo: visible at all 4 breakpoints" / "notification badge
  covered by X at 480px" is useful; a wall of `elementFromPoint`
  output is not.
- If something fails, say which element covered which, at which
  breakpoint, with the screenshot path — enough for a fix to be
  written without re-running the check first.

## Gotchas

- The navbar being layered above the sidebar drawer (`--z-navbar:
  1210` > `--z-sidebar-drawer: 1200`) is intentional, not a bug by
  itself — see the nuance in `references/project-facts.md` before
  flagging a z-index "problem." Use `isElementUncovered()`
  (elementFromPoint-based) rather than raw bounding-box overlap or raw
  z-index comparison, which would flag this constantly.
- First `npx`/`npm install` in this folder can be slow (or need
  network access) — that's expected once, not a bug to debug.
- Reuse `storageState` sessions (`scripts/browser.mjs` ->
  `saveStorageState`/`hasSavedSession`) instead of logging in fresh
  every run; delete `local/sessions/*.json` if a session goes stale.
- Never `sleep()` to wait for a dynamic update — use
  `waitForTextChange` or an equivalent `waitForFunction`/
  `waitForSelector`; a fixed sleep either races the update (flaky) or
  wastes time.
- A CSS class toggle (e.g. `.mobile-open`) is applied instantly, but
  if it drives a CSS *transition* (the drawer's `.3s ease` slide-in),
  `waitForSelector` on that class resolves the instant the class is
  added — mid-animation — not when the transition finishes. Screenshots
  taken right after will show a half-open/half-closed state. Wait for
  the actual end state instead, e.g.
  `page.waitForFunction(() => el.getBoundingClientRect().left === 0)`.
- **Check where the app is actually served from before trusting a
  "live" test.** This project deploys via `deploy/deploy-to-xampp.ps1`,
  which mirrors the repo into `C:\xampp\htdocs\pharmacy_pos` — the
  vhost Apache serves for `pharmacy.local` is that copy, not this repo
  folder directly. Editing a file here does nothing to the live site
  until that script runs. If a workflow's result looks stale or a bug
  seems unfixed, run `git status` for uncommitted changes and check
  whether the deploy script has run since, before assuming the fix
  itself is wrong.
