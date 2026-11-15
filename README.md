# Livia for WordPress

WordPress plugin that publishes the Livia group booking pages of one or several restaurants on their WordPress site
and places Livia chat widgets on the site's pages. Each restaurant is connected with its own key.

- **Group booking pages**: one per restaurant, a real WordPress page, at the address the webmaster chooses, carrying
  the server-rendered block "Livia group booking page". Livia renders its content for the chosen widget and headline
  (the title of its banner; the theme shows the page title); the plugin caches it, keeps the last version received
  when Livia cannot be reached, and drops it when Livia refuses the key or the restaurant's WordPress connector is
  turned off. A page reached with `?couverts=N` (1 to 999) hands N to its conversation (`guests` attribute of
  `<livia-chat>`), in the browser, so the cached page stays the same for every address.
- **Site widget**: one bubble per restaurant, on all pages, all pages but some, or only some, never on a group booking
  page. A page shows one bubble at most: a page listed under "Only" goes to the first restaurant listing it, any other
  page to the first restaurant whose rule covers it. A switch takes every bubble off the site. Each page or post can
  keep its bubble, remove it or pick another bubble of a connected restaurant in the "Livia" panel of the editor.
- **In the content**: the "Livia widget" block, the "Livia" element of Elementor, or the shortcode
  `[livia_widget widget="…"]` for the classic editor and other page builders, with the widgets of every connected
  restaurant. The Elementor element needs Elementor 3.5 or later (the first version firing `elementor/widgets/register`);
  without Elementor, the plugin only hooks Elementor's actions, which never run.
- **Booking experience**: a window that asks for the number of guests (and the restaurant, when the site has several)
  and leads to each restaurant's path: below its group threshold, its booking tool (`{n}` in the address stands for
  the number of guests; in the window, in a new tab or in the current page); in its group tier, its embedded Livia
  conversation in the window (`guests` attribute) or its group booking page (`?couverts=N`); above its event
  threshold, its contact or its private hire tool. Any link to `#reserver` or of class `livia-reserver` opens it, as
  do the "Book button" and "How many of you?" blocks, their Elementor elements, the shortcodes `[livia_reserver]` and
  `[livia_convives]`, the phone bar and the link the settings add at the end of a classic theme's main menu. Its
  settings live in the plugin; its script (`build/experience.js`, deferred) and style load only while its switch is
  on, and everything it places leaves the pages when it is off. The window takes the theme's colours and fonts from
  its global styles.

Requirements: WordPress 6.5 or later, PHP 7.4 or later, permalinks other than "Plain".

## Installation

1. In the restaurant's Livia space, Connectors section, turn on the WordPress connector and download the plugin
   (`livia-<version>.zip`).
2. In WordPress, Plugins > Add New Plugin > Upload Plugin, choose the zip, install and activate it.
3. Open the **Livia** menu, paste the restaurant's key (`lvwp_…`, shown on the WordPress connector in Livia) and
   click **Connect**. **Add a restaurant** connects another one.
4. In the **Site widget** tab, choose each restaurant's bubble and its pages; in the **Booking page** tab, the
   address, title, headline and widget of each restaurant's page; in the **Booking experience** tab, each restaurant's
   thresholds and paths. Each tab saves on its own.

Updates come through WordPress's standard update screen: the plugin's `Update URI` header points WordPress to the
Livia update service, which serves the zip to sites presenting a valid key: the plugin presents the first key of its
restaurants that Livia accepted at the last connection. The service's offer carries `signature`, the Ed25519 signature
of the zip in base64: the plugin ignores an offer without one, and installs a downloaded zip only when that signature
matches the public key it carries (`Livia_Updater::SIGNING_KEY`); otherwise the update screen shows why it refused.
WordPress's update list names the package `Livia_Updater::PACKAGE`,
not an address, and a path under `/dev/null` that no file can have: only the plugin, loaded, resolves it to the zip and
checks it, so WordPress installs nothing when it updates the plugin without loading it (WP-CLI with `--skip-plugins`,
the recovery mode). On a network where the plugin is active on sub-sites only, the network's update screen runs
without the plugin, so nothing would check the signature there: it lists the new version without a package, to be
installed from the zip of the Livia space.

Deactivating the plugin turns the published group booking pages into drafts; activating it again publishes them
again. On a network, a network-wide deactivation or activation does so on every site. Removing a restaurant turns its
page into a draft. Deleting the plugin leaves the pages as drafts and removes the plugin's settings.

## Configuration

The plugin calls the Livia service at `https://chat.booking.wearelivia.com`. A site can point it elsewhere in
`wp-config.php`:

```php
define( 'LIVIA_SERVICE_URL', 'https://…' ); // Address the WordPress server calls.
define( 'LIVIA_PUBLIC_URL', 'https://…' );  // Address visitors' browsers load scripts and previews from; defaults to LIVIA_SERVICE_URL.
```

## Local development

Tools: Node.js 24, Docker. PHP runs inside the containers of
[`@wordpress/env`](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/).

```bash
npm ci
npm run build                 # blocks, editor panel and booking window, from src/ to build/
npx wp-env start              # WordPress at http://localhost:8890 (admin / password)
npx wp-env run tests-cli --env-cwd=wp-content/plugins/livia-wordpress composer install
npx wp-env stop
```

`.wp-env.json` runs the latest supported versions (WordPress 7.1, PHP 8.3), with Elementor installed beside the plugin
for its element, and points the plugin to a Livia chat backend started locally on port 3000 (`LIVIA_SERVICE_URL` reached from the containers through
`host.docker.internal`, `LIVIA_PUBLIC_URL` from the browser through `localhost`). To run the oldest supported
versions, create a `.wp-env.override.json` (ignored by git) before `npx wp-env start`:

```json
{ "core": "WordPress/WordPress#6.5-branch", "phpVersion": "7.4", "plugins": [ "." ] }
```

The pinned Elementor needs a newer WordPress than 6.5: wp-env could not activate it, hence `"plugins": [ "." ]`.

### Checks

```bash
# Coding standards (WordPress Coding Standards, PHP 7.4+ compatibility)
npx wp-env run tests-cli --env-cwd=wp-content/plugins/livia-wordpress vendor/bin/phpcs
# Unit tests: the plugin's rules, without WordPress
npx wp-env run tests-cli --env-cwd=wp-content/plugins/livia-wordpress vendor/bin/phpunit -c phpunit-unit.xml.dist
# Integration tests: WordPress's test suite, Livia faked through the pre_http_request filter
npx wp-env run tests-cli --env-cwd=wp-content/plugins/livia-wordpress vendor/bin/phpunit -c phpunit-integration.xml.dist
# The same on a multisite network (the multisite tests are skipped on a single site)
npx wp-env run tests-cli --env-cwd=wp-content/plugins/livia-wordpress env WP_MULTISITE=1 vendor/bin/phpunit -c phpunit-integration.xml.dist
# The same with Elementor loaded (the Elementor tests are skipped without it, the test without Elementor with it)
npx wp-env run tests-cli --env-cwd=wp-content/plugins/livia-wordpress env LIVIA_TEST_ELEMENTOR=1 vendor/bin/phpunit -c phpunit-integration.xml.dist
# The decisions of the booking window (tiers, quick choices, {n}, restaurant choice), with Node's own test runner
npm run test:js
```

The integration tests cover the address checks, activation, deactivation and uninstall, the widgets placed on the
site, the Elementor elements, the booking experience (settings by restaurant, blocks, shortcodes, phone bar, menu link,
conditional loading), the cache of the group booking page, the update filter and the refusals of the settings screen's actions. The CI (`.github/workflows/ci.yml`) runs these
checks, the integration tests on a single site and on a network under WordPress 6.5 with PHP 7.4 and WordPress 7.1
with PHP 8.3, the Elementor run under WordPress 7.1 only, and builds the zip.

### Translations

Source strings are in English; the French translation lives in `languages/livia-fr_FR.po`. After changing strings,
with wp-env started and the scripts built:

```bash
npm run i18n      # updates livia.pot and livia-fr_FR.po, then compiles .mo, .l10n.php and the scripts' .json
```

Translate the new entries of `livia-fr_FR.po`, then run `npm run i18n` again to compile them.

## Publishing a version

1. Set the new version in `livia.php`: the `Version` header and `LIVIA_VERSION`.
2. Merge to `main`, then push a tag `v<version>` on that commit.
3. The Release workflow (`.github/workflows/release.yml`) runs two jobs. The `build` job, without any secret and with
   read access only, installs the dependencies, builds `livia-<version>.zip` (the runtime files in a top-level
   `livia/` folder) and hands it over as a workflow artifact. The `release` job installs and builds nothing: it takes
   that zip, checks it against the tag (`bin/check-zip.mjs`), signs it (`bin/sign-zip.mjs`, from the tag's checkout)
   and attaches the zip and `livia-<version>.zip.sig`
   (its detached Ed25519 signature, base64) to the GitHub release of the tag, a draft until both are attached. The
   workflow fails when the tag is not on `main`, when the tag differs from the plugin version, when the secret
   `LIVIA_WP_SIGNING_KEY` is missing, or when `Livia_Updater::SIGNING_KEY` is empty or does not verify the signature.
   The check refuses the zip, and nothing is signed, unless every file of the zip found in the tag is identical to it,
   byte for byte, and every other file is a build output under `build/`: a script, a style, an `*.asset.php` in the
   exact form wp-scripts writes, or a `block.json` holding the same JSON as its source under `src/` in the tag. The
   check also refuses any entry with an extra field or a comment, which an unzip could read as another name. Every PHP
   file of the zip, the public key with them, is thus the tag's own, byte for byte, every `*.asset.php` returns a
   plain list, and every `block.json`, which names the PHP files WordPress runs for a block, says what the tag's says;
   the scripts and styles under `build/` come from the build job and are not checked. `node --test
   bin/check-zip.test.mjs` (part of `npm run test:js`) shows the check refusing a changed key, an added PHP file, an
   asset file that carries code, an entry with an extra field or a comment, and a `block.json` other than its source.
   The Livia update service offers the latest release to the sites, with the content of its `.sig` as `signature`.

The `release` job runs in the GitHub environment `release`, whose deployment rule only accepts the tags `v*`; the
secret `LIVIA_WP_SIGNING_KEY` is stored in that environment, so only that job, in a run for a `v*` tag, can read it.

The signing key pair: `LIVIA_WP_SIGNING_KEY` holds the private key (the 32-byte Ed25519 seed in base64),
`Livia_Updater::SIGNING_KEY` the public key (32 bytes in base64). To generate one:

```bash
node -e "const k=require('crypto').generateKeyPairSync('ed25519');console.log('LIVIA_WP_SIGNING_KEY', k.privateKey.export({format:'der',type:'pkcs8'}).subarray(16).toString('base64'));console.log('SIGNING_KEY', k.publicKey.export({format:'der',type:'spki'}).subarray(12).toString('base64'))"
```

To build the zip locally: `npm run build && npm run zip` (writes `dist/livia-<version>.zip`).
