#!/usr/bin/env bash
# Builds dist/livia-<version>.zip: the plugin's runtime files in a top-level livia/ folder, so that WordPress
# installs and updates it in wp-content/plugins/livia. Run `npm run build` first.
# Usage: bin/build-zip.sh [v<version>]   (the tag, when given, must match the plugin's version)
set -euo pipefail
cd "$(dirname "$0")/.."

version=$(sed -n 's/^ \* Version: *//p' livia.php | tr -d '[:space:]')
if [ -z "$version" ]; then
	echo "livia.php has no Version header." >&2
	exit 1
fi
if [ -n "${1:-}" ] && [ "$1" != "v$version" ]; then
	echo "The tag $1 does not match the plugin version $version (livia.php)." >&2
	exit 1
fi
if ! grep -q "define( 'LIVIA_VERSION', '$version' );" livia.php; then
	echo "LIVIA_VERSION in livia.php differs from its Version header $version." >&2
	exit 1
fi
if ! grep -q "^Stable tag: $version\$" readme.txt; then
	echo "Stable tag in readme.txt differs from the plugin version $version." >&2
	exit 1
fi
if [ ! -f build/widget/index.js ] || [ ! -f build/editor-panel.js ]; then
	echo "The JavaScript build is missing: run npm run build." >&2
	exit 1
fi

mkdir -p dist
rm -f "dist/livia-$version.zip" dist/livia
# dist/livia points to the repository, so that every path in the zip starts with livia/; -y stores any other
# symbolic link as a link, so that none brings a file from outside the repository into the zip.
ln -s .. dist/livia
(
	cd dist
	zip -qrXy "livia-$version.zip" livia/livia.php livia/uninstall.php livia/LICENSE livia/readme.txt livia/includes livia/assets \
		livia/build livia/languages -x 'livia/languages/livia.pot' '*.DS_Store'
)
rm -f dist/livia
echo "dist/livia-$version.zip"
