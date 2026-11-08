=== sobelz-embla-carousel-widget ===
Tags: elementor, embla, carousel, slider, autoplay
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Requires Plugins: elementor
Stable tag: 1.1.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A responsive Elementor carousel using locally bundled Embla 8.6.0 and conditionally loaded plugins.

== Description ==

Add the Sobelz Embla Carousel widget from Elementor's General category. Elementor Free 3.20+ is required; Pro is optional.

* Image/text/button slides or published saved Elementor templates.
* Dynamic WordPress queries with post types, terms, authors, included/excluded IDs, offset and ordering.
* Elementor Pro Loop Item templates rendered once per query result, with each post's dynamic context.
* Current main query, related posts and the standard Elementor Query ID hook.
* Responsive slides per view and spacing, using Elementor's responsive controls and breakpoints.
* Horizontal and vertical layouts, RTL, alignment, grouping, looping, dragging, snap controls and physics duration.
* Autoplay, Auto Scroll, Auto Height, Fade, Class Names and Wheel Gestures.
* Accessible buttons, dots per scroll group, keyboard navigation, scroll progress and play/pause.
* Reduced-motion preferences and editor lifecycle cleanup.
* Responsive last-to-first loop spacing, following the slide gap by default or set independently.
* English and Persian controls and frontend labels.

All Embla scripts are shipped in assets/vendor. Neither WordPress nor the browser downloads libraries from a CDN. Enabled plugins are declared as Elementor script dependencies and passed to the EmblaCarousel constructor. When a plugin is newly enabled in the editor, the preview can load that same local script on demand. Shared files are reused across widget instances.

The widget does not make external API calls. Images or content explicitly chosen by an editor may still use their configured URLs.

== Installation ==

1. Install and activate Elementor 3.20 or newer.
2. Upload sobelz-embla-carousel-widget-1.1.0.zip in Plugins > Add New > Upload Plugin and activate it.
3. Edit a page with Elementor and search for Sobelz Embla Carousel.
4. Add slides, adjust Layout and Carousel behavior, and enable the Embla plugins you need.
5. Publish the page. No npm install, build step, API key or CDN access is required on the WordPress server.

When replacing a previous installation named embla-elementor-widget, deactivate that plugin before activating sobelz-embla-carousel-widget. The internal Elementor widget name is preserved so existing pages remain compatible.

== Frequently Asked Questions ==

= Why is looping disabled with a few slides? =
Embla turns looping off when there is not enough content to loop without visible gaps. Add more slides or reduce slides per view.

= Can Autoplay and Auto Scroll run together? =
Select one automatic movement mode. Fade supports Autoplay, and shows one slide per view. Fade and continuous Auto Scroll cannot run together. Auto Height is available for horizontal carousels only.

= Why does automatic movement stop? =
It pauses on hover and keyboard focus. With Stop after interaction enabled, it stays stopped until the play button is used. Disabling that option allows automatic resuming. Automatic movement is paused in the Elementor editor and when the visitor requests reduced motion (unless that preference is disabled in the widget).

= How do I use Elementor templates? =
Publish a template in Templates > Saved Templates, then select Saved Elementor template for a slide. The list includes up to 200 published templates. Circular template references and nesting deeper than three templates are blocked.

= How do I use Elementor Pro Loop Items? =
Publish a Loop Item in Templates > Theme Builder > Loop Item. In this widget select Content source > Dynamic query, choose Query item layout > Elementor Pro Loop Item and select the template. Then configure the Query section. The template supplies the design; WP_Query supplies the items. Dynamic title, image, permalink and other tags resolve for each result post. Pro must be active and support the Loop Item document type. Pro code is not bundled with this plugin.

= Can I query items without Elementor Pro? =
Yes. Choose Dynamic query and Built-in post card. Select a public post type, filters, limit, offset and order. Current query inherits the main query's filters, ordering and pagination. Related posts uses terms from the current post. Up to 100 public, unprotected posts are rendered per widget. Product post types are available when registered by WooCommerce; WooCommerce manages product visibility rules separately.

= How do I use custom dynamic queries? =
Enter a Query ID such as featured_items and attach a callback to elementor/query/featured_items. It receives the WP_Query and widget instances before the query runs. This supports meta queries, relationship-field IDs and custom filters. Developers can also filter eew/query_args with the query arguments, widget and resolved settings. Query ID does not automatically connect AJAX filters or pagination from a different Loop Grid widget.

= How do I fix the gap between the last and first slides? =
Enable Infinite loop, then use Layout > Last-to-first loop gap. Leave it empty to follow Slide gap, or specify a responsive pixel value (including zero). It works with RTL and vertical carousels. Fade does not use slide gaps.

= How do I access Embla's API? =
Listen for the bubbling eew:ready DOM event and use event.detail.api, or read the emblaApi property of the widget's .eew root. Each widget owns its own API instance.

== Third-party code ==

Embla core and its five first-party plugins are pinned to 8.6.0 (MIT). Wheel Gestures is pinned to 8.0.2 (MIT); its UMD distribution includes wheel-gestures (MIT). See THIRD-PARTY.md, individual LICENSE files and assets/vendor/manifest.json for sources, versions and hashes.

== Changelog ==

= 1.1.0 =
Added dynamic queries, Pro Loop Item layouts with per-post context, Query ID and query-argument hooks, current/related/manual query sources, query filters and empty states. Disabled widget output caching for dynamic results. Fixed missing last-to-first spacing in infinite loops and added a responsive seam-gap control.

= 1.0.0 =
Initial release.
