# Iconjet

Lightweight SVG icons and social icons for WordPress: Gutenberg blocks, Elementor's Icon Library, and shortcodes for any other builder. 100% free, no Pro version.

- 21,000+ inline SVG icons from Lucide, Tabler, Phosphor, Bootstrap Icons, Material Symbols, Simple Icons and Font Awesome Free. No icon fonts; only the icons on a page are output.
- Social icons for 70+ platforms with official brand colors.
- Upload your own SVG icons (sanitized, administrators only).

WordPress.org plugin page: coming soon.

## Requirements

- WordPress 6.6+, PHP 7.4+
- Development: Node.js 20+, Composer 2

## Build

```sh
npm install          # JS tooling and the icon source packages
composer install     # PHP dev tools; also prefixes enshrined/svg-sanitize into vendor-prefixed/ (Strauss)
npm run build:icons  # generate assets/packs/, assets/social/ and licenses/ from the npm packages
npm run build        # compile src/ (blocks, icon picker) into build/
```

Generated folders (`assets/packs`, `assets/social`, `licenses`, `build`, `vendor-prefixed`) are not committed.

## Release zip

```sh
npm run zip          # builds everything and writes dist/iconjet.zip (fails above 10 MB)
```

Files listed in `.distignore` are left out. Source code (`src/`, `tools/`, `package.json`, `composer.json`) stays in the zip, as WordPress.org requires.

## Checks

```sh
composer run lint    # PHPCS: WordPress Coding Standards + VIP security sniffs
composer run test    # PHPUnit (wp-phpunit); see tests/wp-tests-config.php for the database
npm run lint:js
npm run lint:css
```

The test suite drops and recreates all tables in its database (`iconjet_tests` by default). Never point it at a real site database.

## License

GPLv2 or later. Bundled icon sets keep their own licenses; see `licenses/` and the Third-Party Resources section of `readme.txt`.
