=== Arlo Training Management Software ===
Contributors: arlosoftware
Tags: event registration, event management, events, courses, course management
Requires at least: 7.0
Tested up to: 7.1
Stable tag: 5.2.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Promote your Arlo courses and events on your own website with customisable pages, and let visitors register through Arlo.

== Description ==

[Arlo](https://www.arlo.co/?utm_source=wordpress%20marketplace&utm_medium=referral%20organic&utm_campaign=wordpress%20marketplace%20listing) is a training management system for creating, scheduling, selling, and delivering training. This plugin connects your WordPress site to your Arlo account so you can promote your courses and events on your own website, in pages that match your site's design. It imports your published courses, events, online activities, presenters, and venues, and shows them using ready-made themes you can restyle, editable templates, shortcodes, and widgets, so the layout and presentation are yours to control. When a visitor is ready to register, they continue to Arlo's checkout, where registration and payment are handled.

An Arlo account is required to show your own courses and events. You can [sign up for a free Arlo trial](https://www.arlo.co/register?utm_source=wordpress%20marketplace&utm_medium=referral%20organic&utm_campaign=wordpress%20marketplace%20listing). To try the plugin first, use *Test with demo data* in *Arlo > Settings*, which connects to Arlo's demo platform.

= How it works =

1. Enter your Arlo platform name in *Arlo > Settings* and save. Nothing is sent to Arlo until you do.
2. The plugin imports your published data and keeps it up to date with a scheduled background sync. You can also run *Synchronize now* from *Arlo > Settings* at any time.
3. The Arlo list pages (catalogue, event search, schedule, upcoming events, online activities, presenters, and venues) are created as drafts when you activate the plugin. Publish them and check their assignments in *Arlo > Settings > Pages*.
4. Visitors browse and filter your training on your site. Each course template, presenter, and venue also gets its own page that WordPress search can find, and scheduled events appear within their course template's page. Registration and payment happen on Arlo.

= What the plugin provides =

* Ready-made list pages for your catalogue, event search, schedule, upcoming events, online activities, presenters, and venues, plus a detail page for each course template, presenter, and venue.
* Ready-made themes (such as Starter, Starter Hero, Block, Jazz, Spark, and Theme Z), including designer versions for sites that need custom HTML and CSS.
* Shortcodes and editable templates, so you control how Arlo data appears on your pages.
* Widgets for upcoming events, categories, event search, and region selection.
* Page filters that show or hide courses and events by category, tag, delivery method, and location, and support for Arlo platforms that use regions.
* Event structured data for Google search results, using the `[arlo_event_rich_snippet]` shortcode.

= About Arlo =

Less admin. More training. Happier learners.

* [Course management](https://www.arlo.co/features/course-management?utm_source=wordpress%20marketplace&utm_medium=referral%20organic&utm_campaign=wordpress%20marketplace%20listing) - Create and schedule courses in minutes, and automate communication with learners across public, private, in-person, online, and blended training.
* [Registrations and payments](https://www.arlo.co/features/course-registration-software?utm_source=wordpress%20marketplace&utm_medium=referral%20organic&utm_campaign=wordpress%20marketplace%20listing) - Smart forms, flexible payment options, and integrated invoicing.
* [Reporting and CRM](https://www.arlo.co/features/crm-reporting-for-training-providers?utm_source=wordpress%20marketplace&utm_medium=referral%20organic&utm_campaign=wordpress%20marketplace%20listing) - Manage client records, relationships, and leads in one place.

Arlo also connects with tools such as Zoom, Moodle, Salesforce, Xero, and Mailchimp. These features are part of your Arlo account, not of this plugin.

= Requirements =
* Arlo account - The plugin shows data from your Arlo platform. [Sign up for a free Arlo trial](https://www.arlo.co/register?utm_source=wordpress%20marketplace&utm_medium=referral%20organic&utm_campaign=wordpress%20marketplace%20listing) if you do not have one.
* WordPress minimum version - Minimum required version of WordPress is 7.0 but WordPress should be kept up to date with the latest version.
* PHP Version - Minimum PHP version is 7.4.
* Shared Server Hosting - Slow shared server hosting can cause issues with the data import. If you have shared server hosting please ensure you [set up a Cron Job](https://developer.arlo.co/doc/wordpress/import#import-cron-job?utm_source=wordpress%20marketplace&utm_medium=referral%20organic&utm_campaign=wordpress%20marketplace%20listing).
* Cache plugins - The Arlo WordPress plugin is compatible with caching plugins. After making changes to Arlo settings or after a data import, clear or reset your cache to ensure visitors see the latest content.
* IP Blocker plugins - Plugins blocking some IP can interfere with the sync system. Please allow [Arlo prod servers IPs](https://support.arlo.co/hc/en-gb/articles/211911583?utm_source=wordpress%20marketplace&utm_medium=referral%20organic&utm_campaign=wordpress%20marketplace%20listing).
* [Google Maps API key](https://developer.arlo.co/doc/wordpress/settings#general-settings?utm_source=wordpress%20marketplace&utm_medium=referral%20organic&utm_campaign=wordpress%20marketplace%20listing) is required for the maps on the venue profile pages (You need to enable the [Maps Embed API](https://developers.google.com/maps/documentation/embed/get-api-key) for the project. If you use `type="static"` in the venue map shortcode, also enable the [Maps Static API](https://developers.google.com/maps/documentation/maps-static/get-api-key)).

= Support & Documentation =
* [Arlo online help centre](https://support.arlo.co/hc/en-gb/categories/201429706-Website-Integration?utm_source=wordpress%20marketplace&utm_medium=referral%20organic&utm_campaign=wordpress%20marketplace%20listing)
* [Arlo for WordPress developer documentation](https://developer.arlo.co/doc/wordpress/index?utm_source=wordpress%20marketplace&utm_medium=referral%20organic&utm_campaign=wordpress%20marketplace%20listing)
* [Arlo for WordPress frequently asked questions (FAQ)](https://support.arlo.co/hc/en-gb/articles/211908963?utm_source=wordpress%20marketplace&utm_medium=referral%20organic&utm_campaign=wordpress%20marketplace%20listing)
* [Manage themes](https://support.arlo.co/hc/en-gb/articles/115001714006?utm_source=wordpress%20marketplace&utm_medium=referral%20organic&utm_campaign=wordpress%20marketplace%20listing)

= For web designers and developers =
Use shortcodes, editable templates and widgets to control exactly how Arlo data appears on your site.

**Pages and site structure**

* Catalogue, event search, schedule, upcoming events, online activities, venue list, and presenter list pages are created as drafts when you activate the plugin. Publish them to make them live.
* The next sync creates published detail pages for your published course templates, presenters, and venues. Scheduled events appear within their course template's page.

**Shortcodes** - Access a range of shortcodes to display data in your WordPress site. You can also add additional attributes to do nifty things with very little effort.

* [Upcoming events](https://developer.arlo.co/doc/wordpress/shortcodes/templateshortcodes/upcomingeventrelated?utm_source=wordpress%20marketplace&utm_medium=referral%20organic&utm_campaign=wordpress%20marketplace%20listing)
* [Event](https://developer.arlo.co/doc/wordpress/shortcodes/templateshortcodes/eventrelated?utm_source=wordpress%20marketplace&utm_medium=referral%20organic&utm_campaign=wordpress%20marketplace%20listing)
* [Event template](https://developer.arlo.co/doc/wordpress/shortcodes/templateshortcodes/eventtemplaterelated?utm_source=wordpress%20marketplace&utm_medium=referral%20organic&utm_campaign=wordpress%20marketplace%20listing)
* [Category](https://developer.arlo.co/doc/wordpress/shortcodes/templateshortcodes/categoryrelated?utm_source=wordpress%20marketplace&utm_medium=referral%20organic&utm_campaign=wordpress%20marketplace%20listing)
* [Venue](https://developer.arlo.co/doc/wordpress/shortcodes/templateshortcodes/venuerelated?utm_source=wordpress%20marketplace&utm_medium=referral%20organic&utm_campaign=wordpress%20marketplace%20listing)
* [Presenter](https://developer.arlo.co/doc/wordpress/shortcodes/templateshortcodes/presenterrelated?utm_source=wordpress%20marketplace&utm_medium=referral%20organic&utm_campaign=wordpress%20marketplace%20listing)

**Templates** - The plugin comes preloaded with the following templates which can all be edited.

* [Event page](https://developer.arlo.co/doc/wordpress/templates#event-template?utm_source=wordpress%20marketplace&utm_medium=referral%20organic&utm_campaign=wordpress%20marketplace%20listing)
* [Catalogue](https://developer.arlo.co/doc/wordpress/templates#events-list-template?utm_source=wordpress%20marketplace&utm_medium=referral%20organic&utm_campaign=wordpress%20marketplace%20listing)
* [Schedule](https://developer.arlo.co/doc/wordpress/templates#schedule-template?utm_source=wordpress%20marketplace&utm_medium=referral%20organic&utm_campaign=wordpress%20marketplace%20listing)
* [Upcoming events](https://developer.arlo.co/doc/wordpress/templates#upcoming-events-template?utm_source=wordpress%20marketplace&utm_medium=referral%20organic&utm_campaign=wordpress%20marketplace%20listing)
* [Venue list](https://developer.arlo.co/doc/wordpress/templates#venue-list-template?utm_source=wordpress%20marketplace&utm_medium=referral%20organic&utm_campaign=wordpress%20marketplace%20listing)
* [Venue information page](https://developer.arlo.co/doc/wordpress/templates#venue-template?utm_source=wordpress%20marketplace&utm_medium=referral%20organic&utm_campaign=wordpress%20marketplace%20listing)
* [Presenter list](https://developer.arlo.co/doc/wordpress/templates#presenter-list-template?utm_source=wordpress%20marketplace&utm_medium=referral%20organic&utm_campaign=wordpress%20marketplace%20listing)
* [Presenter profile pages](https://developer.arlo.co/doc/wordpress/templates#presenter-template?utm_source=wordpress%20marketplace&utm_medium=referral%20organic&utm_campaign=wordpress%20marketplace%20listing)

**Widgets** - The following widgets can be added to any page in your site. 

* [Arlo upcoming events](https://developer.arlo.co/doc/wordpress/widgets#widget-upcoming-events?utm_source=wordpress%20marketplace&utm_medium=referral%20organic&utm_campaign=wordpress%20marketplace%20listing)
* [Arlo categories](https://developer.arlo.co/doc/wordpress/widgets#widget-categories?utm_source=wordpress%20marketplace&utm_medium=referral%20organic&utm_campaign=wordpress%20marketplace%20listing) 
* [Arlo event search](https://developer.arlo.co/doc/wordpress/widgets#widget-search?utm_source=wordpress%20marketplace&utm_medium=referral%20organic&utm_campaign=wordpress%20marketplace%20listing)
* Arlo region selector (for Arlo platforms that use regions)

= Get started today! =
Sign-up for a [free Arlo trial](https://www.arlo.co/register?utm_source=wordpress%20marketplace&utm_medium=referral%20organic&utm_campaign=wordpress%20marketplace%20listing) and then install the Arlo Training Management Software plugin now!

== External services ==

This plugin connects to the following external services. Each entry explains when the service is contacted and what is sent.

= Arlo platform (`{platform}.arlo.co`, `*.arloapi.net`) =
Arlo is the training management service this plugin is an interface to. The plugin imports your published courses, events, presenters and venues from your Arlo platform into your site. An Arlo account is needed to show your own data.

Nothing is sent when the plugin is installed or activated on a new install. Connections start only after you enter your platform name in *Arlo > Settings* and save, or click *Test with demo data* to use Arlo's demo platform (`websitetestdata.arlo.co`). If settings from a previous installation are kept, syncing resumes with the platform saved there.

* Sync: when an import runs, your server sends your site URL, the WordPress version and the plugin version (in the request User-Agent), and a request describing the data to return, with a callback URL and one-time token so the platform can return it. Imported data may be downloaded from `*.arloapi.net` hosts that your platform supplies.
* Platform name check: when you save a new platform name, your server makes a short request to that platform to confirm it exists. The request carries WordPress's standard User-Agent, which includes the WordPress version and your site URL.
* Images: event and presenter images are loaded by each visitor's browser from the image URLs in your Arlo content, which are usually hosted by Arlo. Because that content is authored in your Arlo account, it can also include images from other sites if you add them. The demonstration data used by *Test with demo data* includes example images hosted by Arlo, and is only used if you choose that option. Loading an image discloses the visitor's IP address and browser details to the host serving it.
* Links: registration and event links go to your Arlo platform only when a visitor follows them.

[Terms](https://www.arlo.co/legal/master-subscription-agreement) | [Privacy policy](https://www.arlo.co/legal/privacy-policy)

= Google Maps (`www.google.com/maps/embed`, `maps.googleapis.com`) =
Used to show venue maps. Only enabled once you enter your own Google Maps API key in *Arlo > Settings*; without a key, no requests are made to Google.

* Embedded map (the default): when a venue map is displayed, your visitor's browser loads an embedded map from `www.google.com/maps/embed`. The request includes your API key and the venue's address. It uses the Maps Embed API.
* Static map (`type="static"` in the venue map shortcode): your visitor's browser loads a map image from `maps.googleapis.com`. The request includes your API key and the venue's coordinates. It uses the Maps Static API.
* Either way, loading the map discloses the visitor's IP address and browser details to Google. Address and directions links to `www.google.com/maps` load nothing until a visitor clicks them.

[Terms](https://cloud.google.com/maps-platform/terms) | [Privacy policy](https://policies.google.com/privacy)

== Privacy ==

= Cookie =
If your Arlo platform uses regions and they are configured in the plugin, it sets one first-party cookie, `arlo-region`, in the visitor's browser. Sites without regions do not set it. It stores only the region code the visitor is viewing, or the default region for a visitor who has not chosen one, and is used by your site to show events for that region. It is set when a visitor views your site and when the visitor changes region with the region selector, and it lasts up to 30 days by default. The plugin does not send it to Arlo or any other service.

= Your own privacy notices =
This section describes what the plugin does so you can reflect it in your own privacy notice. Site owners are responsible for their own privacy notices and any consent requirements that apply to them.

== Third-party code and credits ==

This plugin is licensed GPLv2 or later. It bundles the third-party components listed below under the MIT, Apache-2.0, CC BY 4.0 (icons) and SIL OFL 1.1 (fonts) licences. Copyright lines, licence texts and per-file details are in THIRD-PARTY-NOTICES.txt in the plugin folder.

The Arlo source code for this plugin is developed in the open at https://github.com/ArloSoftware/arlowp-plugin. The third-party libraries below are bundled as published by their authors (Arlo's changes to the Bootstrap 3 CSS are described in THIRD-PARTY-NOTICES.txt), including their minified (`*.min.js`, `*.min.css`) files. This plugin has no build step for them, and the non-minified source of each one is available at the upstream address given.

* Bootstrap 3.4.1 (JavaScript) - MIT - https://github.com/twbs/bootstrap/tree/v3.4.1 - `public/custom-assets/bootstrap.min.js`
* Bootstrap collapse plugin 3.2.0 - MIT - https://github.com/twbs/bootstrap/tree/v3.2.0 - `public/custom-assets/collapse.min.js`
* Bootstrap 3 CSS, namespaced and modified (based on 3.3.x) - MIT - https://github.com/twbs/bootstrap/tree/v3.3.4 - `themes/starter*/css/bootstrap.css`, `public/assets/css/libs/bootstrap-modals.css`
* normalize.css - MIT - https://github.com/necolas/normalize.css - `themes/*/css/style.css`, `themes/theme.z/scss/_normalize.scss` (8.0.1)
* selectize.js 0.12.4 - Apache-2.0 - https://github.com/selectize/selectize.js/tree/v0.12.4 - `public/custom-assets/selectize.min.js`
* slick 1.7.1 - MIT - https://github.com/kenwheeler/slick/tree/1.7.1 - `public/custom-assets/slick-1.7.1/`
* tingle 0.12.0 - MIT - https://github.com/robinparisi/tingle/tree/v0.12.0 - `public/custom-assets/tingle/`
* darktooltip 0.1.8 - MIT - https://github.com/rubentd/darktooltip/tree/v0.1.8 - `public/assets/js/libs/jquery.darktooltip.min.js`, `public/assets/css/libs/darktooltip.min.css`
* js-cookie 3.0.8 - MIT - https://github.com/js-cookie/js-cookie/tree/v3.0.8 - `public/assets/js/libs/js.cookie.js`
* Font Awesome 4.7.0 - fonts SIL OFL 1.1, CSS MIT - https://fontawesome.com/v4/license/ - `public/custom-assets/font-awesome-4.7.0/`, the glyph subsets in `public/custom-assets/arlo-icon-compat/`, and the Arlo icon stylesheets that use these glyphs (`admin/assets/css/arlo-icons.css`, `themes/*/css/arlo-icons.css`, `themes/*/css/fonts.css`, `themes/be.software.designers/css/arlo-web-controls.css`)
* Font Awesome Free 6.7.2 - icons CC BY 4.0, fonts SIL OFL 1.1, code MIT - https://github.com/FortAwesome/Font-Awesome/tree/6.7.2 - `themes/theme.z/`
* Swiper 8.4.7 - MIT - https://github.com/nolimits4web/swiper/tree/v8.4.7 - `themes/theme.z/`
* Poppins - SIL OFL 1.1 - https://github.com/itfoundry/Poppins - `themes/theme.z/fonts/`
* CodeMirror 5 stylesheet (5.10.0 or 5.11.0) - MIT - https://github.com/codemirror/codemirror5/tree/5.11.0 - `admin/assets/css/libs/codemirror.css`

== Installation ==
= From your WordPress dashboard =

1. Visit *Plugins > Add New*
2. Search for `Arlo Training Management Software`
3. Activate Arlo from your *Plugins* page.

= From WordPress.org =

1. Download the Arlo WordPress plugin.
2. Upload the `arlo-training-and-event-management-system` directory to your `/wp-content/plugins/` directory, using your favorite method (ftp, sftp, scp, etc...)
3. Activate Arlo from your *Plugins* page.

= Once Activated =

1. Upon activation, the plugin creates any missing Arlo pages (Events, Upcoming Events, Event search, Schedule, Online Activities, Venues, Presenters) as drafts and assigns them to their Arlo page types. Existing pages with the same slugs, for example from a previous installation, are reused unchanged. Go to your *Pages* and publish each draft before continuing.
2. Visit *Arlo > Settings* and enter your Arlo platform name, then click *Save Changes*. This connects the plugin to your platform and begins syncing your data automatically. Nothing is sent to Arlo until you do. (To try the plugin first, click *Test with demo data* instead: it connects to Arlo's demo platform, `websitetestdata`, publishes the draft Arlo pages, assigns them and downloads demo event data.)
3. In *Arlo > Settings*, open the *Pages* tab and check that each published page is assigned to its corresponding Arlo page type. Pages that already existed are not assigned automatically.
4. Save the settings.

= Once Configured =

* The Arlo plugin comes with a lot of different shortcodes, and you can customize your plugin with the shortcodes. For more information about the shortcodes please visit the [Arlo developer site](https://developer.arlo.co/doc/wordpress/index?utm_source=wordpress%20marketplace&utm_medium=referral%20organic&utm_campaign=wordpress%20marketplace%20listing)

== Frequently Asked Questions ==

= Where can I find all Arlo for WordPress FAQ’s? =

You can find all [Arlo for WordPress FAQ's](https://support.arlo.co/hc/en-gb/articles/211908963?utm_source=wordpress%20marketplace&utm_medium=referral%20organic&utm_campaign=wordpress%20marketplace%20listing) on the Arlo online support portal.

= Can I use my existing WordPress theme? =

Yes. Arlo works with most WordPress themes. You can adjust its styling with WordPress's own *Additional CSS*: *Appearance > Customize > Additional CSS* on classic themes, or *Appearance > Editor > Styles > Additional CSS* on block themes.

= Can I use the plugin without an Arlo platform? =

Not for your own courses and events. You need an Arlo account to show them, and you can [sign up for a free Arlo trial](https://www.arlo.co/register?utm_source=wordpress%20marketplace&utm_medium=referral%20organic&utm_campaign=wordpress%20marketplace%20listing). To look around first, *Test with demo data* in *Arlo > Settings* connects the plugin to Arlo's demo platform.

= Where do my visitors register and pay? =

Registration and payment happen on Arlo's checkout, not on your WordPress site. Your pages show availability and a *Register* link, and selecting it opens Arlo's checkout in a new tab. You can change how the checkout looks in your Arlo account.

= How often does my site update from Arlo? =

A site set to the *Production* deployment mode checks for new data automatically in the background about every 30 minutes. A site set to *Non-production* (for staging or test sites) syncs daily, or weekly when it uses the demo platform. To update straight away, save your settings and select *Synchronize now* in *Arlo > Settings*. Large platforms can take several minutes to import.

= Why is my template read only? =

Templates are shortcode markup that prints on your site, so, like WordPress's own raw HTML and CSS, only users who are allowed to add raw HTML can edit them. On a single site that is administrators; on a multisite network it is super administrators; and nobody can if `DISALLOW_UNFILTERED_HTML` is set in `wp-config.php`. Everyone else can read a template but not change it, and can still change the other Arlo settings.

= Why am I being asked to migrate my Custom CSS? =

Arlo no longer keeps custom CSS in the plugin. It now lives in WordPress's own *Additional CSS*, so it is kept with your theme settings and is not lost when the plugin updates. If your site had custom CSS, it keeps working exactly as before until you migrate it. You only need to do this if the *Custom CSS* tab in *Arlo > Settings* shows a warning icon and a migration message, and if you no longer need that CSS you can discard it there instead.

1. Review your CSS in the editor. Changes made there are not published; they are only used when you check or migrate.
2. Select *Check migration readiness* and fix any items marked *Must fix*.
3. Tick the confirmation checkbox and select *Migrate now*.
4. Check your live pages to make sure they still look right.

Migrating needs WordPress's `edit_css` permission, which administrators have on a single site and only super administrators have on a multisite network. After migration, edit your CSS in *Additional CSS*. Your CSS is kept inside a block marked `ARLO-CUSTOM-CSS` in *Additional CSS*, and *Revert* on the *Custom CSS* tab moves it back for as long as that block can still be found. *Additional CSS* is stored separately for each theme, so if you change theme, copy your CSS into the new theme's *Additional CSS*.

= Where can I find documentation? =

The plugin's documentation can be found in our [Arlo developer site](https://developer.arlo.co/doc/wordpress/index?utm_source=wordpress%20marketplace&utm_medium=referral%20organic&utm_campaign=wordpress%20marketplace%20listing).


= How do I get support or report a bug? =

For support, use the [Arlo online help centre](https://support.arlo.co/hc/en-gb/categories/201429706-Website-Integration?utm_source=wordpress%20marketplace&utm_medium=referral%20organic&utm_campaign=wordpress%20marketplace%20listing) or contact Arlo support. Technical help with website integration is a separate service from Arlo's web team; see [website integration services](https://www.arlo.co/pricing/professional-services?utm_source=wordpress%20marketplace&utm_medium=referral%20organic&utm_campaign=wordpress%20marketplace%20listing#website) for details. Bug reports and technical discussion are also welcome as issues on the plugin's [GitHub site](https://github.com/ArloSoftware/arlowp-plugin/issues).

== Languages ==

Arlo Training Management Software is written in English. Translations can be contributed through [translate.wordpress.org](https://translate.wordpress.org/projects/wp-plugins/arlo-training-and-event-management-system/).

== Screenshots ==

1. Arlo Themes
2. Theme Starter – Catalogue
3. Theme Starter – Upcoming events
4. Theme Starter – Schedule
5. Theme Starter Hero – Catalogue
6. Theme Starter Hero – Upcoming events
7. Theme Jazz – Catalogue
8. Theme Spark – Upcoming events
9. Theme Block – Catalogue
10. Theme BeSoftware – Schedule
11. Theme Starter – Presenters
12. Theme Block – Venues
13. Theme Starter – Catalogue – Mobile version
14. Admin – Custom Css
15. Admin – Pages
16. Admin – General
17. Admin – Regions
18. Admin – System requirements


== Changelog ==

= 5.2.0 =
* Improvement: New arlo_region_redirect_location filter lets sites control automatic region redirects without editing plugin files, supporting site-managed regional URLs.
* Improvement: New experimental enhanced import mode (preview) with more optimised performance. Off by default.
* Improvement: Diagnostic log retention can now be configured from the admin Misc settings.
* Improvement: The "Powered by Arlo" link is now off by default and appears only when a site administrator opts in, from the one-time notice on the Arlo settings screen or from Arlo > Settings > Misc. Sites that were showing the link stop showing it until they opt in; saved templates are not modified.
* Improvement: Custom CSS now lives in WordPress's own Additional CSS instead of in the plugin, so it is kept with your theme settings and no longer depends on a file in the plugin folder. Sites that have custom CSS keep it working exactly as before until an administrator migrates it from Arlo > Settings > Custom CSS, with a readiness check and a revert, or discards it if it is no longer needed. New installs and sites with no custom CSS are pointed to Additional CSS. While a migration is pending, a warning icon marks the Custom CSS tab.
* Improvement: Template source can now only be edited by users who may add raw HTML to WordPress (administrators on a single site, super administrators on a multisite network). Other users see their templates read only, and the Upcoming events widget keeps its stored template for them. Stored templates keep rendering, and theme switching and upgrades are unaffected.
* Improvement: Updated bundled front-end libraries: js-cookie 2.2.0 to 3.0.8 and Font Awesome Free in Theme Z 6.5.2 to 6.7.2. The unused jQuery UI stylesheet has been removed from the admin assets.
* Improvement: Clearer first-time setup. A new install no longer contacts Arlo until an administrator saves a platform name or chooses "Test with demo data" beside the platform field, which explains what is sent. Host pages are created as drafts.
* Improvement: Admin CSS and JavaScript cleanup: Arlo admin assets now load only on Arlo screens (or where an Arlo notice is shown), and their styles no longer affect other plugins' admin notices.
* Improvement: Removed the bundled fancyBox library. The "For designers" explanation on the Theme tab is now shown as text on the page instead of in a pop-up window.
* Improvement: Removed the old option to send logs to Arlo. Logs remain available in Arlo > Logs.
* Improvement: Removed the Icons8 icon font and replaced it with Font Awesome alternatives.
* Improvement: Site-wide Arlo error notices are now only shown to administrators.
* Improvement: Removed the "leave a review" prompt from the Arlo settings screen and the "Subscribe to our WP newsletter" row from Arlo > Settings > Misc.
* Improvement: Added backward compatibility support for region ID handling.
* Fix: The Theme Z Course Template now shows the number of date tiles set by the show attribute of [arlo_event_list_item] instead of always showing four.
* Fix: The Theme Z catalogue and schedule "Show more" buttons now follow the limit set on [arlo_event_template_list_item] instead of assuming three events per click, and always ask for their own category.
* Fix: Import scheduling and retry handling are more reliable for standard single-site imports, large and complex imports, and WordPress multisite installs.
* Fix: Persistent import failures now back off more intelligently, reducing repeated full-import attempts and the risk that failing imports consume PHP workers needed for normal site traffic.
* Fix: An import now waits up to 5 minutes for Arlo's snapshot callback; a callback that arrives later is ignored and logged.
* Fix: Scheduled automatic imports are deduplicated, and stalled or overlong imports are failed cleanly instead of occupying the import slot indefinitely.
* Fix: Import entity writes are idempotent when a large import fragment is retried after partial completion.
* Fix: PHP 8.1+ scheduler retry-backoff no longer fails with a fatal error because of invalid named cron arguments.
* Fix: Upgrades from 5.1.0 gracefully migrate the import lock table before schema validation, avoiding unnecessary schema repair during the 5.2.0 upgrade.
* Fix: Upgrading to 5.2.0 mid-import now ends that import instead of failing to resume it.
* Fix: A bug when handling state shortcode attributes that caused execution errors.
* Fix: The [arlo_oa_rich_snippet] shortcode now outputs the online activity structured data when used inside [arlo_oa_list_item] on the Event template.
* Fix: Region fields now support Arlo region IDs up to 16 characters, and upgrades widen those columns before schema validation. Sites with configured regions and evidence that the old 5-character limit may have been hit request a fresh sync.
* Fix: A temporary database error while checking the plugin's database structure no longer causes the plugin's event data tables to be dropped and rebuilt.
* Fix: Theme Z event template hero banners no longer show duplicate advertised presenter links.
* Fix: Improved security hardening of the import callback endpoint.
* Fix: A percent sign in a shortcode attribute that takes a placeholder, such as `width:100%` in a `wrap` style, no longer causes a fatal error on PHP 8. `%s` is still the placeholder and `%%` a literal percent sign.
* Fix: The `format` attribute of the event date shortcodes again follows the documented strftime conversions, as it did before 5.0.10. `%j`, `%U`, `%W`, `%C`, `%F`, `%r`, `%c`, `%x`, `%X`, `%n` and `%t` now render correctly, and flags such as `%-d`, `%-m` and `%-I` work instead of causing a fatal error on PHP 8.
* Fix: PHP 8 notices on category pages and in the Categories widget.
* Fix: The [arlo_powered_by] credit now translates its link label and logo text, and encodes the ampersands in its link.
* Fix: PHP 8.2+ deprecation notices ("Use of self in callables is deprecated") from integer filter helpers on event list pages and shortcodes.
* Fix: The permalink notice no longer appears for supported pretty permalink structures other than /%postname%/ (such as the multisite /blog prefix). It now appears only for Plain permalinks, structures without a trailing slash, and structures that start with index.php, with specific wording for each.
* Fix: Database error when sorting the admin Presenters list by name.
* Fix: Fixed a caching bug in the Search, Region selector, Categories and Upcoming events widgets on sites with a persistent object cache.
* Fix: Region values in the URL are now validated before use, so an unexpected value is ignored and the default or saved region is used.
* Fix: `[arlo_presenter_events_list_advanced]` no longer leaves the last event as the current event template for shortcodes placed after it.
* Fix: The month filter parsing has been hardened.
* Fix: The meta description of an event template page now shows an ampersand or comparison sign in the course summary as written, not as escaped text, and allows up to 160 characters before it is shortened.
* Fix: `[arlo_categories]` with `depth="0"` now lists every category level, as the Categories widget help text describes, instead of rendering nothing.
* Fix: `[arlo_event_template_advertised_price]` now shows online activity offer prices.
* Fix: `[arlo_suggest_templates]` no longer empties the event template for shortcodes placed after it in the same template.
* Fix: Event rich snippets are no longer left out for online events or for events with no time zone.
* Fix: Deleting the plugin now also removes its cached transients, and its dismissed-notice user settings when "Keep settings" is off.
* Performance: Upcoming Events list performance improved for large catalogues and complex schedules.
* Performance: Diagnostic log cleanup now runs at safe import lifecycle points instead of after every log write.
* Security: Custom CSS can no longer close its own style element.
* Security: Updated bundled Bootstrap JS from 3.3.7 to 3.4.1, which includes upstream XSS fixes.
* Security: Applied sanitisation to category breadcrumb, category path, category title and template list group markup.
* Security: Venue, presenter and template titles and descriptions are now sanitised on every import, whichever user starts it. Page addresses are unchanged.
* Security: Rejected import callbacks are now throttled in the Arlo log, so unauthenticated requests can no longer flood it.

= 5.1.2 =
* Improvement: Support for the 2026 Canadian time zone changes (British Columbia, Manitoba, Alberta, Yukon).
* Improvement: System requirements now checks for outdated server time zone data affecting the November 2026 Canadian time zone changes.
* Fix: Event times use the UTC offset from Arlo when a time zone is not recognised, and unrecognised time zones no longer cause errors.
* Fix: Corrected and added Arlo time zone mappings.
* Fix: Admin Events list start and finish dates now show local clock times rather than UTC beside local time zone abbreviations.
* Fix: Admin Events list flags events that use Arlo's stored UTC offset when the server does not recognise the time zone or its rules disagree.
* Fix: Fixed-offset time zone abbreviations now use a clear UTC offset label.

= 5.1.1 =
* Fix: [arlo_event_rich_snippet] now safely returns no output outside an event list context, preventing PHP warnings and deprecation notices.

= 5.1.0 =
* Improvement: Theme Z: new design theme.
* Improvement: Non-production deployment mode syncs daily or weekly rather than every 30 minutes.
* Improvement: Connection health tracking — sync auto-disables after sustained platform failures with admin status indicator and reconnect flow.
* Improvement: Google structured data updated to 2026 spec — attendance mode, eventStatus, RFC 3339 datetimes, and Course provider field.
* Improvement: Shortcode validation indicator on the Pages settings tab.
* Improvement: Warning shown when multiple Arlo page types share a host page.
* Improvement: Admin Pages tab shows the live URL/slug for each assigned host page, with a private-page indicator and direct View and Edit links.
* Improvement: [arlo_event_presenters] supports a layout=list attribute for list display.
* Improvement: On activation, newly created Arlo pages are automatically assigned to their page type slots — Settings → Pages no longer opens with all dropdowns blank after a fresh install.
* Improvement: Page-setup admin notice shows categorised lists — unassigned pages under 'Finish the setup', assigned-but-unpublished pages with a status label under 'Review these pages'.
* Fix: Schema hash depended on the configured table prefix, causing spurious table drops and full re-imports during plugin upgrades on non-wp_ prefixes.
* Fix: Detail pages could briefly show content from an in-progress import.
* Fix: Deleted or unpublished plugin pages show a recovery path in admin; search form degrades gracefully on the front end.
* Fix: Theme Z — corrected show-more button visibility and grouped event paging in the new theme.
* Fix: Settings saved confirmation and admin notices were silently discarded.
* Fix: Import sync throttle was permanently bypassed.
* Fix: Empty search form submission exposed the nonce in the browser address bar.
* Fix: [arlo_event_template_list_image_src] urldecode attribute was ignored.
* Fix: [arlo_event_template_summary] digest attribute mishandled for zero, negative, and non-integer values.
* Fix: [arlo_event_presenters] link='false' produced an empty anchor rather than plain text.
* Fix: Category and region filter selects had incorrect ARIA roles and missing translated labels.
* Fix: Draft or pending regionalized host page accessed by slug caused a redirect loop when regions were configured.
* Performance: Eliminated 1,000+ duplicate database queries per page load.
* Performance: Admin list pagination uses count queries — events admin reduced from ~16s to ~49ms on large datasets.
* Performance: Front-end page loads no longer scan all theme directories on each request.
* Security: Output escaping hardened across shortcodes, admin list tables, widgets, and the import log page.
* Security: Capability and nonce enforcement strengthened on admin AJAX handlers and GET-parameter action handlers.
* Security: Scheduler nonce moved from URL query string to POST body; snapshot callback nonce consumption made atomic.
* Security: HMAC verification in the import crypto layer uses timing-safe comparison.
* Security: SQL query text no longer included in importer error log messages.
* Security: Sync log CSV export sanitised against formula injection in spreadsheet applications.
* Compatibility: PHP up to 8.5. WordPress 7.0 and 7.1.

For the complete changelog see [CHANGELOG.txt](https://plugins.svn.wordpress.org/arlo-training-and-event-management-system/trunk/CHANGELOG.txt).

== Upgrade Notice ==
= 5.2.0 =
Back up your files and database before upgrading. "Powered by Arlo" is now off by default (opt in under Arlo > Settings > Misc). Custom CSS moves to Additional CSS and keeps working until migrated. Editing templates now needs permission to add raw HTML.
