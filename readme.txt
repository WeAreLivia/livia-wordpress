=== Livia ===
Contributors: livia
Tags: restaurant, booking, reservation, chat, assistant
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.1.1
License: Proprietary
License URI: https://www.wearelivia.com

Publishes your restaurant's Livia group booking page on your site, with its booking assistant, and places Livia widgets on your pages.

== Description ==

Livia is a hospitality platform: bookings, menus, services and conversational assistants for restaurants. This plugin connects a WordPress site to a restaurant's Livia space.

* **Several restaurants**: one key per restaurant; a site can present several restaurants.
* **Group booking pages**: for each restaurant, a real WordPress page, at the address you choose, that shows the restaurant's group booking page with its booking assistant. The page shows inside the theme, which keeps its header and footer, or as a landing page, without them and without the widgets of other booking tools. Each page has its switch: turned off, it goes back to draft and keeps its address.
* **Site widget**: for each restaurant, one Livia bubble on all pages, on all pages but some, or on some pages only; a page shows one bubble at most, and "only" wins over "all". A switch takes every bubble off the site. Each page or post can keep its bubble, remove it or pick another bubble in the "Livia" panel of the editor.
* **In the content**: the "Livia widget" block, the "Livia" element of Elementor (Elementor 3.5 or later), or the shortcode `[livia_widget widget="..."]` for the classic editor and other page builders.
* **Advanced settings**: Livia's scripts and stylesheets kept out of the optimisations of the common cache and optimisation tools, and the group booking pages out of their page cache and image lazy loading, with a button that clears their cache; the group booking pages' styles inherited from the site or isolated from it.
* **Booking experience**: every "Book" button opens a window that asks for the number of guests, then leads the visitor to the restaurant's booking tool, its Livia conversation or group booking page, or its contact, according to its thresholds. With several restaurants, the window also asks where. The "Book button" and "How many of you?" blocks and Elementor elements, the shortcodes `[livia_reserver]` and `[livia_convives]`, any link to `#reserver`, a bar on phones and a link in the main menu open it.

The plugin needs a Livia account. Turn on the WordPress connector in your Livia space (Connectors section), then paste each restaurant's key in the Livia menu of WordPress. The plugin contacts the Livia service (chat.booking.wearelivia.com) to read the restaurant's widgets and page; visitors' browsers load the Livia chat from the same service.

== Installation ==

1. In your Livia space, Connectors section, turn on the WordPress connector and download the plugin.
2. In WordPress, go to Plugins > Add New Plugin > Upload Plugin, choose the zip, install and activate it.
3. Open the Livia menu, paste the restaurant's key (`lvwp_...`) and click Connect. "Add a restaurant" connects another one.
4. In the "Site widget" tab, choose each restaurant's bubble and its pages; in the "Booking page" tab, the address, title, headline and widget of each restaurant's group booking page; in the "Booking experience" tab, each restaurant's thresholds and paths; in the "Advanced settings" tab, the styles of the group booking pages. Each tab has its own Save button.

== Credits ==

The settings screen uses the Hanken Grotesk and Archivo fonts, under the SIL Open Font License 1.1; their licences are in assets/fonts.

== Changelog ==

= 1.1.1 =
* When the Livia service refuses a restaurant because its account is no longer active, the group booking page is removed as for a turned off connector, visitors see the same short notice, and the Livia screen says the account is no longer active and to contact your Livia representative.

= 1.1.0 =
* Display mode of the group booking page, per restaurant: inside the theme (default) or as a landing page, without the theme's header and footer, with the restaurant's logo and a "Back to the site" link; the site's scripts still load, except the widgets of other booking tools.
* "Advanced settings" tab: the supported cache and optimisation tools detected, Livia's scripts and stylesheets kept out of their delay, defer, minification and combination, the group booking pages out of their page cache and image lazy loading, the state of each rule per tool, the rules to copy into a tool without hooks, and a "Clear the cache" button.
* Group booking page styles: inherited from the site (default) or isolated from its CSS.
* Without a widget chosen, the group booking page shows the restaurant's first embedded conversation other than the Livia booking page's widget, else the Livia booking page's widget; a note says when its conversations are counted with the Livia booking page's.
* The group booking page's bubble follows its opening setting, always closed on phones; on phones, the page opens its conversation from a button fixed at the bottom of the screen, whatever the widget's format, as the tab explains.
* Fixed: with a cache tool that delays JavaScript, the bubble, the "Book" window and ?couverts=N waited for the visitor's first interaction.
* The plugin never clears a cache by itself: after the update, clear it from the "Advanced settings" tab. The landing page and the isolated styles need the current Livia service. A landing page shows inside the theme when the Livia service does not render landing pages.

= 1.0.0 =
* First release: one or several restaurants, group booking pages, site bubbles, "Livia widget" block, Elementor elements and shortcodes, editor panel, booking experience, updates from Livia.
