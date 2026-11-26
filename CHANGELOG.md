# Changelog

Each version's section is the text of its GitHub release and of the plugin's update window in WordPress: one line per list item, no inline formatting.

## 1.1.1

Livia 1.1.1 tells you when a restaurant's Livia account is no longer active.

### Changed

- When the Livia service refuses a restaurant because its account is no longer active, the group booking page is removed as for a turned off connector, and visitors see the same short notice as before.
- The Livia screen then shows "Livia account inactive" and tells you that the account is no longer active and to contact your Livia representative to reactivate it.

## 1.1.0

Livia 1.1 lets each restaurant choose how its group booking page looks on your site, keeps cache and optimisation tools from getting in the way of Livia, and gives every group booking page a conversation of its own.

### New: display mode of the group booking page

- Each restaurant chooses it in the "Booking page" tab.
- Inside the theme (default): the page keeps your theme's header and footer, as in 1.0.
- Landing page: the page stands alone, like your Livia booking page, without the theme's header and footer, with the restaurant's logo and a "Back to the site" link to your home page.
- On a landing page, your site's scripts still load (audience measure, cookie banner, SEO tags), except the widgets of other booking tools: Zenchef, TheFork (LaFourchette), SevenRooms, Guestonline, Tourbiz, OpenTable, Resy, Formitable, Bookingshake, CoverManager, Quandoo, Resengo and Tablebooker.
- A landing page shows the title of your Livia booking page in place of the headline set in the tab.

### New: cache and optimisation, in the "Advanced settings" tab

- Supported tools: WP Rocket, LiteSpeed Cache, W3 Total Cache, WP Super Cache, WP Fastest Cache, Autoptimize, SiteGround Optimizer, Perfmatters, FlyingPress, Hummingbird, Breeze, Jetpack Boost and NitroPack. The tab shows the ones running on your site.
- On the whole site, Livia's scripts are kept out of these tools' delay, defer, minification and combination, and Livia's stylesheets out of their style optimisations.
- On the group booking pages only, their page cache and image lazy loading are turned off. Every other page stays cached.
- All of this goes through each tool's own hooks and the attributes it reads on a tag. Nothing is written in the tools' settings: it all stops if you deactivate Livia.
- For each tool detected, the tab shows the state of each rule: applied automatically, to copy into the tool's settings, not done by this tool, or left to the attributes Livia puts on its images.
- Where a tool offers no hook for a rule, a "Copy the rule" button copies what to paste into its exclusion settings, one line per entry.
- NitroPack: every rule is to copy, including the group booking page addresses for its page cache and img.skip-lazy for its image lazy loading, with the field to paste it in.
- Breeze: script delay, minification and combination are to copy.
- FlyingPress: stylesheet optimisation is to copy, and the skip-lazy keyword for its image lazy loading.
- WP Fastest Cache: stylesheet optimisation is to copy.
- A "Clear the cache" button clears the cache of every tool detected (Perfmatters keeps no page cache).

### New: group booking page styles, in the "Advanced settings" tab

- A setting of the site, for every restaurant, in both display modes.
- Inherit from the site (default): your site's styles also apply to the page, as in 1.0.
- Isolated: the page keeps Livia's styles, out of reach of your site's CSS; the rest of your site, cookie banner included, keeps its own.
- A link ending in ?couverts=N still fills in the number of guests on an isolated page.

### Changed: the widget of the group booking page

- When no widget is chosen, the page shows the restaurant's first embedded conversation that is not the widget of your Livia booking page, and only otherwise the widget of your Livia booking page.
- The empty choice of the "Widget" list reads "Automatic:" followed by the name of that conversation. A widget you choose still comes first.
- When the page shows the same widget as your Livia booking page, a note under the list says that their conversations are counted together, and that an embedded conversation created in your Livia space tracks them separately.
- The bubble of the group booking page follows its opening setting: open on load on a computer when set so in your Livia space, always closed on phones and small screens.
- On phones, the conversation opens from a button fixed at the bottom of the screen, in your widget's colours, whatever its format; a note under the "Widget" list says so.

### Changed: clearing the cache

- The plugin never clears a cache by itself.
- While a cache tool holds pages from before the update to 1.1, or from before a change of display mode or styles, every tab of the Livia screen shows a notice that leads to the "Clear the cache" button. It goes away once the button is used.

### Fixed

- On a site where a cache or optimisation tool delays or defers JavaScript (WP Rocket and LiteSpeed Cache among them), the Livia bubble, its opening on load, the "Book" window and the number of guests of ?couverts=N waited for the visitor's first interaction with the page.
- They now work as soon as the page loads with every tool that offers a hook for it, and with Breeze and NitroPack once the rules shown in the tab are copied.
- A page cache could keep serving an old version of the group booking page long after Livia had updated it. The group booking pages are now out of the page cache of every tool that offers a hook for it; NitroPack gets the addresses to copy.

### Updating from 1.0

- Settings are kept, and every group booking page stays inside the theme with the site's styles until you change it.
- A restaurant with no widget chosen whose Livia space has an embedded conversation other than the widget of its Livia booking page now shows that conversation, in two columns: the conversation, always open, beside the introduction on a computer, under it on phones and small screens. Its conversations are counted apart from those of the Livia booking page.
- Other sites keep the same settings and the same request to Livia, so the same cached copy of the page.
- No cache is cleared automatically: clear it from the "Advanced settings" tab after the update.

### Compatibility

- WordPress 6.5 or later, tested up to 7.1; PHP 7.4 or later.
- The automated tests run on WordPress 6.5 with PHP 7.4, and on WordPress 7.1 with PHP 8.3 and Elementor.
- The "Livia" Elementor elements need Elementor 3.5 or later.
- The landing page and the isolated styles need the current Livia service, which renders them. A group booking page set as a landing page shows inside the theme when the Livia service does not render landing pages.

### Known limitations

- On a landing page, a booking tool's script added in the browser by Google Tag Manager or by another script is not removed.
- On a landing page, an inline script that loads a booking tool together with other tags that hold no address (a lone gtag('config') call, for instance) is removed as a whole.
- On a landing page, a plugin that starts processing the page's output in the head and finishes it in the footer loses that processing.
- Image lazy loading of Breeze, FlyingPress and NitroPack on the group booking page is turned off by the skip-lazy class the Livia service puts on the page's images, or by the rule to copy.

## 1.0.0

First release.

- Several restaurants: one key per restaurant; a site can present several restaurants.
- Group booking pages: for each restaurant, a real WordPress page at the address you choose, showing its Livia group booking page with its booking assistant inside your theme, with its own switch.
- Site bubbles: one Livia bubble per restaurant, on all pages, all pages but some, or some pages only.
- In the content: the "Livia widget" block, the "Livia" element of Elementor, and the [livia_widget] shortcode.
- Editor panel: each page or post keeps its bubble, removes it or picks another one.
- Booking experience: every "Book" button opens a window that asks for the number of guests and leads the visitor to the right path: the restaurant's booking tool, its Livia conversation or group booking page, or its contact.
- The "Book button" and "How many of you?" blocks and Elementor elements, the [livia_reserver] and [livia_convives] shortcodes, any link to #reserver, a bar on phones and a link in the main menu open the booking window.
- Updates from Livia: new versions, signed, are offered in the WordPress Plugins screen.
