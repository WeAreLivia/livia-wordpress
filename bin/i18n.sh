#!/usr/bin/env bash
# Regenerates languages/livia.pot from the sources, merges it into languages/livia-fr_FR.po, then compiles the French
# files WordPress reads (.mo, .l10n.php, and one .json per built script). Needs `npm run build` and a started wp-env.
# Translate the new entries of livia-fr_FR.po, then run the script again to compile them.
set -euo pipefail
cd "$(dirname "$0")/.."

run() {
	npx wp-env run cli --env-cwd="wp-content/plugins/$(basename "$PWD")" "$@"
}

run wp i18n make-pot . languages/livia.pot --slug=livia --domain=livia --exclude=build,node_modules,vendor,tests,dist
run wp i18n update-po languages/livia.pot languages/livia-fr_FR.po
rm -f languages/*.json
run wp i18n make-mo languages/livia-fr_FR.po languages
run wp i18n make-php languages/livia-fr_FR.po languages
# The JSON files are named after the built scripts, not their sources.
run wp i18n make-json languages/livia-fr_FR.po languages --no-purge --use-map=bin/i18n-map.json
