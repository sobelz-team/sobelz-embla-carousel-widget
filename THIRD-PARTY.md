# Bundled third-party code

Scripts are downloaded at development time from the upstream npm registry, verified against the registry's integrity field and shipped unchanged. There are no runtime CDN requests. `assets/vendor/manifest.json` records the exact versions, tarball URLs, integrity values and SHA-256 hashes.

| Package | Version | License | Upstream |
| --- | --- | --- | --- |
| embla-carousel | 8.6.0 | MIT | https://github.com/davidjerleke/embla-carousel |
| embla-carousel-autoplay | 8.6.0 | MIT | Same repository |
| embla-carousel-auto-scroll | 8.6.0 | MIT | Same repository |
| embla-carousel-auto-height | 8.6.0 | MIT | Same repository |
| embla-carousel-class-names | 8.6.0 | MIT | Same repository |
| embla-carousel-fade | 8.6.0 | MIT | Same repository |
| embla-carousel-wheel-gestures | 8.0.2 | MIT | https://github.com/xiel/embla-carousel-wheel-gestures |

MIT license notices are included alongside each script. The Embla license is also available at https://github.com/davidjerleke/embla-carousel/blob/v8.6.0/LICENSE.

The Wheel Gestures UMD bundle incorporates wheel-gestures code by Felix Leupold, also licensed under MIT: https://github.com/xiel/wheel-gestures. Its license notice is included in the Wheel Gestures directory. Source maps supplied by upstream are included unchanged.

The WordPress integration, widget and styles in this repository are GPL-2.0-or-later; see LICENSE. The bundled upstream code retains its MIT license.
