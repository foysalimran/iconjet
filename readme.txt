=== Iconjet ===
Contributors: faysal61
Tags: icons, svg, social icons, elementor, icon block
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

21,000+ lightweight SVG icons and social icons for Gutenberg, Elementor and any page builder. 100% free.

== Description ==

Iconjet gives you a large library of modern SVG icons and ready-made social media icons that work everywhere on your WordPress site: in the block editor, in Elementor, and in any other page builder through shortcodes.

Every feature is free. There is no Pro version and nothing is locked.

= Why Iconjet is different =

* **Only the icons you use are loaded.** Icons are output as inline SVG. No icon font files, no extra CSS loaded on every page.
* **One plugin for every editor.** Gutenberg blocks, Elementor's built-in Icon Library, and shortcodes for every other builder. No need for a separate icon plugin per builder.
* **Social icons included.** A ready-made Iconjet Social Icons block and shortcode with official brand colors, so you don't need a second plugin for social links.

= Works with =

* **Gutenberg (Block Editor)** – Iconjet Icon block and Iconjet Social Icons block (they work in the Site Editor, Query Loops and the Navigation block too)
* **Elementor** – All icon packs except Font Awesome (which Elementor already includes) added directly to Elementor's built-in Icon Library, so they work in every Elementor widget that has an icon option. Social logos from Iconjet packs get their brand colors in Elementor's Social Icons widget.
* **Any other builder** – Divi, Beaver Builder, Bricks, Oxygen, WPBakery, the Classic Editor and widgets, using shortcodes

= Icon packs included =

* Lucide (2,121 icons)
* Tabler Icons (5,166 icons)
* Phosphor Icons (1,512 icons)
* Bootstrap Icons (2,078 icons)
* Material Symbols (3,927 icons)
* Simple Icons (3,463 brand and social logos)
* Font Awesome Free (2,883 icons; block editor and shortcodes only, since Elementor already includes it)

Turn any pack on or off from the settings page.

= Social icons =

* 70+ social platforms ready to use: Facebook, X, Instagram, LinkedIn, YouTube, TikTok, WhatsApp, Telegram, Pinterest, Threads, Discord, GitHub and more
* Official brand colors with one click, or your own colors
* Shapes: circle, square, rounded, or icon only
* Size, spacing and hover color controls
* Open links in a new tab and add rel="nofollow" if needed
* In Elementor's Social Icons widget, logos from Iconjet packs (Simple Icons and the generic email, phone, RSS and website icons) use their brand colors. Elementor's own Custom and hover colors still apply

= Icon features =

* Size, color, background, padding, border radius and rotation
* Add a link to any icon
* Fast icon search with categories and recently used icons
* Upload your own SVG icons (sanitized for security, administrators only, files up to 50 KB that must be 20 KB or smaller after sanitizing, up to 500 custom icons)
* Accessibility built in: aria-label for linked icons, aria-hidden for decorative icons
* Translation ready and RTL supported

= Shortcodes =

Single icon:

`[iconjet name="lucide:heart" size="32" color="#e11d48"]`

Icon with link:

`[iconjet name="tabler:mail" size="24" link="mailto:hello@example.com" label="Email us"]`

Social icons:

`[iconjet_social facebook="https://facebook.com/yourpage" x="https://x.com/yourname" instagram="https://instagram.com/yourname" style="circle" brand_colors="yes" size="28"]`

Not sure about the syntax? Use the built-in Shortcode Generator under **Iconjet → Shortcode Generator**, pick your icons, and copy the shortcode.

== Installation ==

1. Go to **Plugins → Add New** and search for "Iconjet".
2. Click **Install Now**, then **Activate**.
3. Go to **Iconjet → Settings** to choose which icon packs to enable.
4. Use the icons:
   * Block editor: add the **Iconjet Icon** or **Iconjet Social Icons** block.
   * Elementor: open any icon control and choose **Icon Library**. The new packs appear in the sidebar.
   * Other builders: paste a shortcode into a text, code or shortcode module.

== Frequently Asked Questions ==

= Is it really free? =

Yes. Every feature and every icon pack is free. There is no Pro version.

= Does it work with Divi, Bricks or Beaver Builder? =

Yes, through shortcodes. Add a text or shortcode module and paste the shortcode. The Shortcode Generator writes the shortcode for you.

= Will it slow down my site? =

No. Icons are inline SVG and only the icons used on a page are output. No icon font files are loaded.

= Does it conflict with Elementor's Font Awesome icons? =

No. Iconjet adds new packs to Elementor's Icon Library and leaves the built-in Font Awesome icons untouched.

= Can I upload my own icons? =

Yes. Administrators can upload SVG files under **Iconjet → Custom Icons**. Every file is sanitized before it is saved: scripts, event handlers and external references are removed, and files with a DOCTYPE or entity declarations are rejected. Each uploaded file can be up to 50 KB, and the icon must be 20 KB or smaller after sanitizing. You can store up to 500 custom icons. Custom icons appear in every icon picker, in the shortcodes and in Elementor. Icons drawn in a single color follow the color you choose; icons with two or more colors keep their own colors.

= What happens when I turn off an icon pack? =

The pack is hidden from the icon pickers and from Elementor's Icon Library. Icons from that pack that are already in your content keep showing on the site, but they may look empty in the Elementor editor until you turn the pack on again.

= Do social icons keep their brand colors in Elementor? =

Yes. In Elementor's Social Icons widget, choose a logo from an Iconjet pack (for example Facebook from Simple Icons) and it uses that brand's color. If you set the widget's color to Custom, or set a hover color, Elementor uses your colors instead. LinkedIn, Slack, Weibo and CodePen logos come from Font Awesome, which Elementor already includes with its own colors.

= What happens to my icons if I deactivate the plugin? =

Icons added with the Iconjet Icon and Iconjet Social Icons blocks are saved as SVG in your content and stay visible, including their size, colors and shape. Hover colors need the plugin to be active. Shortcodes and Elementor icons from Iconjet packs will stop displaying until the plugin is active again.

= What does deleting the plugin remove? =

Deleting Iconjet from the Plugins screen removes its settings, its icon cache and your uploaded custom icons. Content made with the blocks keeps its saved SVG markup.

== Privacy ==

Iconjet does not collect or send any data, does not set cookies and makes no requests to external servers. Icons are served from the plugin's own files on your site, and nothing is added to your site's frontend except the inline SVG and small stylesheets, only on pages that use Iconjet icons.

The only thing Iconjet remembers is your list of recently used icons in the icon picker. It is kept in your own browser (localStorage) and is never sent anywhere.

== Screenshots ==

1. Icon picker with pack tabs, search and categories
2. Iconjet Icon block and its settings in the block editor
3. Iconjet Social Icons block with brand colors
4. Iconjet packs in Elementor's Icon Library
5. Elementor's Social Icons widget with Iconjet brand logos
6. Shortcode Generator with live preview
7. Custom Icons page for uploading your own SVG icons
8. Settings page with icon pack toggles

== Changelog ==

= 1.0.0 =
* Initial release.

== Source Code ==

The plugin ships with its full, human-readable source, and the development repository is public at https://github.com/foysalimran/iconjet.

The JavaScript for the blocks and admin screens is in the `src/` folder and is compiled into `build/` with `@wordpress/scripts`. The icon data in `assets/packs/`, the social platform data in `assets/social/` and the Elementor files in `assets/elementor/` are generated from the original npm packages by `tools/build-icons.mjs` (the platform list is in `tools/social-platforms.mjs`). The SVG sanitizer library in `vendor-prefixed/` is installed with Composer and namespaced with Strauss.

To rebuild:

1. Run `composer install`
2. Run `npm install`
3. Run `npm run build:icons` to regenerate the icon data
4. Run `npm run build` to compile the JavaScript

`npm run zip` runs all of the above and creates the release zip.

== Third-Party Resources ==

This plugin bundles the following icon sets. All are GPL-compatible.

* Lucide – ISC License – https://lucide.dev
* Tabler Icons – MIT License – https://tabler.io/icons
* Phosphor Icons – MIT License – https://phosphoricons.com
* Bootstrap Icons – MIT License – https://icons.getbootstrap.com
* Material Symbols – Apache License 2.0 – https://fonts.google.com/icons
* Simple Icons – CC0 1.0 – https://simpleicons.org
* Font Awesome Free – Icons: CC BY 4.0 – https://fontawesome.com/license/free

Brand logos are trademarks of their respective owners and are provided for identifying those brands only.

Uploaded SVG files are cleaned with svg-sanitize by Daryll Doyle (GPL-2.0-or-later) – https://github.com/darylldoyle/svg-sanitizer

The license files for each icon set are in the `licenses/` folder.
