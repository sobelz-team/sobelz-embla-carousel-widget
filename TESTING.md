# Validation

Validated on 2026-11-08:

- All shipped PHP files parse with PHP 7.4.
- 19 Chromium/Playwright tests pass using the actual bundled Embla distributions.
- Integration checks pass with WordPress 7.1.4, Elementor 4.3.6 and PHP 8.3.36 in WordPress Playground (SQLite).
- Installation, widget registration, controls, settings normalization, Persian catalog, template slides and frontend rendering are exercised in the real WordPress/Elementor integration.
- Pages without the widget request no Embla files. Basic pages request only core. Autoplay pages request only core plus Autoplay. Combined plugin pages register the selected plugins and request local files.
- Browser tests cover navigation, grouped dots, responsive reinitialization, independent widget instances, deduplicated plugin loading, autoplay pause/resume, reduced motion, RTL keyboard input, vertical Auto Scroll, Fade, Auto Height, Class Names, Wheel Gestures, Elementor's early initialization state and handler destruction/recreation.
- Version 1.1.0 adds measured loop-seam spacing tests for horizontal, RTL and vertical layouts, independent and zero spacing, editor changes, and responsive changes that do not resize the slides.
- Real WordPress database tests cover public custom post types, taxonomy filters, ID inclusion/exclusion, ordering, offset, related terms, main-query pagination, nested Query ID callbacks, callback cleanup on exceptions, empty results, and exclusion of non-public/password-protected posts.
- Loop Item rendering tests check distinct post IDs, titles and permalinks for each result, plus restoration of the containing post, query, WooCommerce product and page state. A test-only Loop Item document implementing the rendering contract is registered in Elementor Free; this does not test Elementor Pro's internal implementation or Pro dynamic CSS.
- Packaging verifies vendor hashes and excludes development dependencies and test files.
- The `sobelz-embla-carousel-widget` package name, PHP entry point, text domain, Persian catalog and installation path are verified by the integration suite. The Elementor widget's internal name remains compatible with existing pages.

The integration suite was run with Elementor Free in a temporary Playground site. Elementor Pro was not available in this environment. To run against an existing, user-supplied Pro installation, set `EEW_ELEMENTOR_PRO_DIR` to an extracted Pro plugin directory. The suite mounts and activates that installation and uses its real Loop Item document instead of the test document. Pro itself is never downloaded or bundled by this repository.

Run locally:

```sh
npm ci
npm run test:php
npm test
npm run package
npm run test:wordpress
```

Set `CHROMIUM_PATH` if Chromium is not at `/usr/bin/chromium`. `EEW_TEST_PHP` selects the PHP version; `EEW_TEST_WP` selects the integration suite's WordPress version. WordPress tests download the official Elementor stable archive. Network environments using a proxy can set `NODE_USE_ENV_PROXY=1`.

```sh
EEW_ELEMENTOR_PRO_DIR=/path/to/elementor-pro npm run test:wordpress
```
