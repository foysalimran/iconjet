# Build "Easy Icons" – a WordPress plugin (full-featured MVP)

You are building a production-ready WordPress plugin called **Easy Icons** that will be submitted to the WordPress.org Plugin Directory. It must pass WordPress.org review on the first try, so follow the WordPress.org Detailed Plugin Guidelines strictly.

Before writing any code, read this whole brief, then produce a short implementation plan (architecture, file tree, build pipeline, phases) and wait for my approval. After approval, work phase by phase, commit after each phase, and verify each phase before moving on.

---

## 1. Product summary

A **100% free** icon plugin (no Pro version, no upsells, nothing locked) that adds a large library of modern **SVG icons** and **social media icons** to:

- **Gutenberg** (Icon block + Social Icons block)
- **Elementor** (icon packs added to Elementor's native Icon Library)
- **Any other builder** (Divi, Bricks, Beaver, Oxygen, WPBakery, Classic Editor, widgets) via **shortcodes** + an admin **Shortcode Generator**

Core differentiators (must be true in the implementation):
1. Inline SVG only. **No icon fonts.** Only icons actually used on a page are output.
2. One plugin covers block editor, Elementor and shortcodes.
3. Built-in social icons with official brand colors.

## 2. Identity and technical constants

- Plugin Name: `Easy Icons`
- Slug / folder / text domain: `easy-icons`
- Main file: `easy-icons.php`
- PHP namespace: `EasyIcons`
- Function/option/hook prefix: `easyicons_` (options, transients, hooks, REST namespace `easyicons/v1`, CSS classes `easyicons-`)
- Shortcodes: `[easyicons]` and `[easyicons_social]`
- Requires WordPress 6.6+, PHP 7.4+, Tested up to 7.1
- License: GPLv2 or later
- A finished `readme.txt` already exists in the project root. Keep it as the source of truth for features; if implementation forces a change, update the readme to match reality. Never claim a feature that isn't implemented.

## 3. Icon packs and build pipeline

Bundle these packs. Use **one style per pack** to keep size down:

| Pack ID | Source (npm) | Style | License |
|---|---|---|---|
| `lucide` | `lucide-static` | default | ISC |
| `tabler` | `@tabler/icons` | outline only | MIT |
| `phosphor` | `@phosphor-icons/core` | regular only | MIT |
| `bootstrap` | `bootstrap-icons` | default | MIT |
| `material` | `@material-symbols/svg-400` | outlined | Apache-2.0 |
| `simple` | `simple-icons` | brands | CC0-1.0 |
| `fa` | `@fortawesome/fontawesome-free` | solid, regular, brands | Icons CC BY 4.0 |

Build script (Node, in `tools/build-icons.mjs`):
- Read SVGs from the npm packages, optimize with **SVGO** (keep `viewBox`, strip width/height, ids, comments, hardcoded colors where the icon is monochrome so `currentColor` works; keep `fill="none"`/`stroke` attributes for stroke-based packs like Lucide/Tabler).
- Output per pack:
  - `assets/packs/{pack}.json` → `{ "id", "label", "license", "count", "icons": { "name": { "v": "0 0 24 24", "b": "<inner svg markup>", "t": ["tag", "category"] } } }`
  - `assets/packs/index.json` → list of packs with label, count, license, categories.
- Icon reference format everywhere: `pack:name` (e.g. `lucide:heart`).
- **Measure and print the size of every pack and the final zip.** WordPress.org has a **10 MB zip limit**. If over, report it and propose cuts (drop a style, drop a pack) before continuing.
- Copy each pack's LICENSE file into `licenses/`.
- The build tools and `package.json` must be included in the plugin (or linked from the readme) because WordPress.org requires human-readable source for any compiled code.

## 4. Social platforms

- Build `assets/social/platforms.json` with **60+ platforms**: id, label, icon ref, official brand hex color, URL placeholder.
- Use Simple Icons for logos and brand hex colors. **Some brands are missing from Simple Icons (e.g. LinkedIn)** – fall back to Font Awesome Free brands for those and hardcode the brand color.
- Must include at least: Facebook, X, Instagram, LinkedIn, YouTube, TikTok, WhatsApp, Telegram, Pinterest, Threads, Discord, GitHub, Reddit, Snapchat, Tumblr, Twitch, Vimeo, Dribbble, Behance, Medium, Mastodon, Bluesky, Spotify, SoundCloud, Apple Music, Email (generic), Phone (generic), RSS (generic), Website (generic).

## 5. Features (MVP scope)

### 5.1 Core (PHP)
- `Icon_Registry`: loads `index.json`, lazy-loads a pack JSON only when needed, caches parsed packs in a static property for the request and in a transient keyed by plugin version.
- `Icon_Renderer::render( $ref, $args )`: returns inline `<svg>` with `viewBox`, `width/height`, `fill`/`stroke` = `currentColor`, class `easyicons-icon easyicons-{pack}`, `role="img"` + `aria-label` when a label is given, otherwise `aria-hidden="true" focusable="false"`. Output must be escaped/sanitized with a strict SVG `wp_kses` allowlist.
- Settings (Settings API, stored in one option `easyicons_settings`): enable/disable each pack, default icon size, default social style.
- Disabled packs are hidden from all pickers and from Elementor, but existing content using them still renders.

### 5.2 Custom SVG upload
- Admins only (`manage_options`), with nonces.
- Sanitize every upload with **enshrined/svg-sanitize** (GPL-2.0). Bundle it via Composer and **prefix its namespace with Strauss** so it can't conflict with other plugins. If that is not practical, implement a strict allowlist sanitizer and document why.
- Store custom icons in a custom pack `custom` (option or custom table – choose and justify), shown in all pickers.

### 5.3 Gutenberg (built with `@wordpress/scripts`, `block.json`, apiVersion 3)
- **Icon block** `easyicons/icon`: icon picker, size, icon color, background color, padding, border radius, rotation (0–360), flip, link (URL, new tab, rel), aria-label, alignment. Use native `ToolsPanel`, color and spacing supports where possible.
- **Social Icons block** `easyicons/social-icons`: add/remove/reorder platforms, URL per item, style (circle, square, rounded, icon only), size, gap, brand colors toggle or custom color/hover color, open in new tab, `rel="nofollow"` option, alignment.
- **Static save**: save the final SVG markup in post content so icons keep rendering if the plugin is deactivated (the readme promises this). Include block deprecations strategy notes for future changes.
- **Icon picker modal** (shared component): pack tabs, search (by name and tags), category filter, recently used (localStorage, wrapped in try/catch), virtualized grid so thousands of icons stay fast. Pack JSON is fetched lazily from the plugin's own `assets/packs/` URL (no external CDN – guideline 8).
- Blocks must work in the Site Editor, inside Query Loops and in the Navigation block where possible.

### 5.4 Elementor
- Register each enabled pack (except `fa`, which Elementor already has) as a tab in Elementor's native Icon Library via the `elementor/icons_manager/additional_tabs` filter.
- Elementor's icon library is designed for icon fonts. **Do not create icon fonts.** Inspect the installed Elementor source (`Icons_Manager`) and find the supported way to render custom libraries as inline SVG (e.g. a `render_callback` in the tab config, or the `elementor/icons_manager/native` / render filters). Implement what the current Elementor version actually supports, document it in code comments, and make the editor preview show the icons.
- Load the Elementor adapter **only** when Elementor is active (`did_action( 'elementor/loaded' )`).

### 5.5 Shortcodes
- `[easyicons name="lucide:heart" size="32" color="#e11d48" link="" label="" new_tab="no" class=""]`
- `[easyicons_social facebook="url" x="url" ... style="circle" brand_colors="yes" size="28" gap="8" color="" hover_color="" new_tab="yes" nofollow="no" align="left"]`
- All attributes sanitized; output escaped. Unknown icon → empty output (and an HTML comment only when `WP_DEBUG` is on).

### 5.6 Admin
- Top-level menu "Easy Icons" with: **Settings**, **Icon Library** (browse/search/copy shortcode), **Custom Icons** (upload/delete), **Shortcode Generator**.
- Admin UI in React using `@wordpress/components`.
- Shortcode Generator: pick icon or social set, adjust options, live preview, copy button.
- No dashboard-wide notices, no ads, no tracking (guidelines 7 and 11). Links to our site/docs are allowed only on the plugin's own pages.

### 5.7 Frontend assets
- Tiny CSS only for social icons and icon wrapper; enqueue it **only** on pages where a block, shortcode or Elementor widget actually outputs Easy Icons markup (`wp_enqueue_block_style` for blocks, conditional enqueue in shortcode callbacks).
- No JavaScript on the frontend.
- No "Powered by" links or credits on the frontend (guideline 10).

## 6. Code quality and WordPress.org compliance

- Follow WordPress Coding Standards; set up PHPCS with `WordPress` + `WordPressVIPMinimum`-style security sniffs and fix all errors.
- Every input sanitized, every output escaped, nonces + capability checks on every admin action and REST route (`permission_callback` always set).
- No external HTTP requests, no remote assets, no telemetry.
- Use WordPress's bundled libraries; don't bundle React, jQuery, etc.
- `uninstall.php` removes options, transients and custom icons.
- Fully translatable (`easy-icons` text domain), generate `languages/easy-icons.pot`, RTL-safe CSS.
- Run the official **Plugin Check (PCP)** plugin against the build and fix every error and warning before calling the work done.

## 7. Project layout (suggested – adjust in your plan if you have a better structure)

```
easy-icons/
├── easy-icons.php
├── readme.txt
├── uninstall.php
├── composer.json / package.json
├── includes/            (PHP classes: Plugin, Icon_Registry, Icon_Renderer, Svg_Sanitizer,
│                         Settings, Rest_Controller, Shortcodes, Custom_Icons,
│                         Integrations/Elementor.php)
├── src/                 (JS source: blocks/icon, blocks/social-icons, components/IconPicker,
│                         admin app)
├── build/               (compiled assets)
├── assets/packs/        (generated JSON)
├── assets/social/       (platforms.json)
├── licenses/
├── languages/
├── tools/               (build-icons.mjs, make-zip script)
└── vendor-prefixed/     (Strauss output)
```

Add `.distignore` and an `npm run zip` script that builds a clean release zip (no node_modules, no dev files, but includes source needed for review).

## 8. Development environment and verification

- Use `@wordpress/env` (wp-env) with Elementor installed for local testing.
- Add basic PHPUnit tests for `Icon_Renderer`, the SVG sanitizer and shortcode attribute handling.
- At the end, give me a manual test checklist covering: Icon block, Social Icons block, Site Editor, Elementor icon picker + frontend, both shortcodes inside a non-Gutenberg context, custom SVG upload (including a malicious SVG test file with `<script>` and `onload`), pack disabling, plugin deactivation (block icons still visible), and uninstall cleanup.
- Report final zip size, pack sizes, icon counts per pack, and Plugin Check results.

## 9. Out of scope for this MVP (do not build)

Menu icons, floating social bar, classic widget, native Divi/Bricks/Beaver modules, animations, a Pro version, licensing code, analytics.

## 10. Working rules

- Plan first, wait for approval, then build in phases:
  1. Scaffold + build pipeline + icon data (report sizes)
  2. Core registry, renderer, sanitizer, settings, REST
  3. Gutenberg blocks + icon picker
  4. Shortcodes + admin app + shortcode generator + custom icons
  5. Elementor integration
  6. Compliance pass (PHPCS, Plugin Check, i18n, uninstall, zip)
- If anything in this brief conflicts with WordPress.org guidelines or with how Elementor/WordPress actually work, stop and tell me instead of guessing.
- Don't add features I didn't ask for.