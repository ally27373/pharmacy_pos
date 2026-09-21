# Project facts this skill relies on

These are the concrete details that make the skill work against
*this* app without rediscovering them each run. If any of these have
changed since this was written, trust the current code over this
file and update it.

## Environment

- The app runs locally via XAMPP. Assume it's already running at
  **http://pharmacy.local** — this skill never starts/stops XAMPP or
  Apache. If a page doesn't load, tell the user to check XAMPP rather
  than trying to launch it yourself.
- `PW_BASE_URL` env var overrides the base URL if the user is testing
  a different host.
- **`pharmacy.local` is served from `C:\xampp\htdocs\pharmacy_pos`, a
  separate deployed copy — not this repo folder.** `deploy/deploy-to-xampp.ps1`
  mirrors the repo's current working tree (including uncommitted
  changes) into that folder. A code change made in this repo is
  invisible to `pharmacy.local` until that script runs again. Before
  trusting a "the bug is still there" result, check `git status` for
  unstaged changes and confirm with the user whether they want the
  deploy script run — it's a `robocopy /MIR`, which also deletes
  anything in htdocs that isn't in the repo, so treat it as a real
  action worth a quick confirmation, not a no-op refresh.

## Login

`app/auth/login.php`, plain POST form (no JS/AJAX):

- Username field: `input[name="login"]`
- Password field: `input[name="password"]`
- Submit button: `button.login-btn`
- On success it redirects (302) to a role-specific dashboard.
- Role is stored server-side in `$_SESSION['role_id']`: `1` = admin,
  `2` = cashier (see `includes/sidebar.php`). You don't need to know
  the numeric value to drive Playwright — just log in with whichever
  role's credentials from `local/test-credentials.json`.

## Responsive breakpoint convention

Documented in `assets/css/style.css` (`:root` comment) and used
consistently across the app's stylesheets:

| Breakpoint | Meaning |
|---|---|
| max-width: 1200px | small desktop / laptop |
| max-width: 992px | tablet landscape — **sidebar becomes an off-canvas drawer below this** |
| max-width: 768px | tablet portrait |
| max-width: 480px | phone |

`scripts/browser.mjs` exports `BREAKPOINTS` with viewport sizes picked
just under each threshold so the corresponding mobile behavior is
actually active when you test it.

## App shell selectors

From `includes/navbar.php` and `includes/sidebar.php`:

| Selector | What it is |
|---|---|
| `.navbar-custom` | the sticky top bar |
| `#sidebar-toggle` | hamburger button (mobile only, `display:none` above 992px) |
| `#sidebar` / `.sidebar` | the drawer/sidebar itself |
| `.sidebar.mobile-open` | class added when the drawer is open on mobile |
| `#sidebar-backdrop` | dark overlay behind the open drawer, click to close |
| `.sidebar-logo` | logo + "NICA XANDRA" / "Pharmacy POS" block at the top of the sidebar |
| `#notification-button` / `#notification-panel` / `#notification-badge` | the bell dropdown in the navbar |
| `#navbar-datetime-text` | live-ticking clock in the navbar (updates every second — good smoke test for Workflow 4) |

Drawer open/close is driven by `assets/js/sidebar.js`
(`#sidebar-toggle` click, `#sidebar-backdrop` click, or `Escape`).

## Z-index scale (`assets/css/style.css` `:root`)

```
--z-sidebar-backdrop: 1150
--z-sidebar-drawer:   1200
--z-navbar:           1210
--z-notification-panel: 1120
--z-modal:            1300
--z-toast:            1400
```

**Important nuance:** the navbar is *deliberately* layered above the
sidebar drawer so its hamburger/bell stay clickable while the drawer
is open — that's not a bug by itself. The actual bug class this skill
watches for is specific *content* silently ending up hidden behind
something else (e.g. the sidebar logo getting painted over by the
navbar). That's why `isElementUncovered()` in `scripts/browser.mjs`
checks real paint order via `elementFromPoint`, not raw z-index
numbers or bounding-box overlap — a raw overlap check would flag the
navbar/drawer relationship constantly even though it's working as
designed.

## Pages worth including in a sweep

- `/app/auth/login.php` (unauthenticated)
- `/app/dashboard/analytics/index.php` (admin — has the SARIMA forecast chart)
- `/app/dashboard/inventory/index.php` (admin)
- `/app/dashboard/pos/index.php?page=terminal` (admin + cashier)
- `/app/dashboard/inventory_management/index.php` (admin only)
- `/app/dashboard/users/index.php` (admin only)

Pick a subset relevant to what changed rather than always running all
of them — see SKILL.md for how each workflow decides scope.
