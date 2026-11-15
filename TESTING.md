# Validation

Version 1.3.2 was validated on 2026-11-15:

- All shipped PHP files parse with PHP 7.4.
- All 34 Chromium tests pass using the bundled Embla 8.6.0 distributions.
- WordPress 7.1.4/PHP 8.3.36 integration checks pass with Elementor Free 4.2.0 and an existing Elementor Pro 4.2.0 installation.
- Additional native-query comparisons pass with WordPress 7.1.3, PHP 8.3.35 and WooCommerce 11.3.0.

Coverage includes:

- Widget registration, controls, settings normalization, Persian translations, saved templates and real Pro Loop Items.
- Public post types, manual selections, inclusion/exclusion, taxonomy and author filters, ordering, offset, related posts, current-query pagination and scoped Query ID callbacks, including nested queries and exception cleanup.
- Taxonomy queries for categories and brands, empty-term defaults, hierarchy, limits, ordering, filters, duplicate avoidance and native taxonomy context restoration. Pro image tags and independent dynamic background CSS are checked per term.
- Direct comparisons with native Loop Carousel queries, including WooCommerce catalog visibility and shared duplicate avoidance. Temporary fixtures and uploaded images are removed after these checks.
- Responsive underfilled loops in horizontal, RTL and vertical layouts, viewport coverage across repeated wraps, logical navigation, custom seams, fixed/automatic groups, continuous motion, hidden initialization, changing source sets and stable selection on resize.
- Unique copy IDs and references, accessibility/Tab order, links and form focus/state, mirrored nested-carousel state, and Elementor destroy/recreate cleanup. Real Pro editor checks confirm repeated items render, automatic motion stays paused and mirrored images accept clicks without JavaScript errors.
- Local asset loading, independent instances, reduced motion, keyboard navigation, Autoplay, Auto Scroll, Fade, Auto Height, Class Names and Wheel Gestures.
- WordPress updater APIs with intercepted responses: version comparisons, caching, forced checks, upgrade invalidation, shared updater instances and safe failure. These tests do not contact the live release server.
- Package version consistency, vendor hashes, required licenses and exclusion of development files from the installation archive.

## Run locally

```sh
npm ci
npm run test:php
npm test
npm run package
npm run test:wordpress
```

Chromium defaults to `/usr/bin/chromium`; override it with `CHROMIUM_PATH`. `EEW_TEST_PHP` selects the PHP version and `EEW_TEST_WP` selects the WordPress integration version. The WordPress suite uses a temporary Playground site and downloads the official Elementor stable archive. Proxy environments can set `NODE_USE_ENV_PROXY=1`.

An extracted Elementor installation can be mounted to avoid downloading it. To test the actual Pro implementation, supply an existing Pro directory:

```sh
EEW_ELEMENTOR_DIR=/path/to/elementor EEW_ELEMENTOR_PRO_DIR=/path/to/elementor-pro npm run test:wordpress
```

Without Pro, a test-only Loop Item document checks the rendering contract. Elementor Pro is never downloaded or bundled by this repository.

For additional differential checks, activate this plugin and Elementor Pro in a disposable WordPress installation; activate WooCommerce to include product and dynamic image/background checks:

```sh
wp --path=/path/to/wordpress eval-file /path/to/project/tests/wordpress-native-query.php
```
