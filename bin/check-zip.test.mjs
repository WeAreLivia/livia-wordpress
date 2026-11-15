// bin/check-zip.mjs passes the zip bin/build-zip.sh makes from the committed tree, and refuses it once its public key
// is changed, a PHP file is added, an asset file carries code, or its central directory hides bytes. Needs git, tar,
// zip and bash, as the release does.
import assert from 'node:assert/strict';
import { execFileSync, spawnSync } from 'node:child_process';
import { copyFileSync, mkdirSync, mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { dirname, join } from 'node:path';
import { after, test } from 'node:test';
import { fileURLToPath } from 'node:url';

const repo = join( dirname( fileURLToPath( import.meta.url ) ), '..' );
const tmp = mkdtempSync( join( tmpdir(), 'livia-check-zip-' ) );
after( () => rmSync( tmp, { recursive: true, force: true } ) );

// The committed tree, which the checker reads with git, plus the outputs of a build.
const out = join( tmp, 'export' );
mkdirSync( out );
execFileSync( 'tar', [ '-x', '-f', '-', '-C', out ], { input: execFileSync( 'git', [ 'archive', '--format=tar', 'HEAD' ], { cwd: repo, maxBuffer: 1 << 28 } ) } );
const asset = ( deps ) =>
	`<?php return array(\n\t'dependencies' => array(\n\t\t${ deps.map( ( d ) => `'${ d }'` ).join( ',\n\t\t' ) }\n\t),\n\t'version' => '0123456789abcdef0123'\n);\n`;
const build = {
	'widget/index.js': 'console.log(1);',
	'widget/index.asset.php': asset( [ 'wp-blocks', 'wp-i18n' ] ),
	'widget/block.json': '{}',
	'editor-panel.js': 'console.log(2);',
	'editor-panel.asset.php': asset( [] ),
	'editor-panel.css': 'a{}',
};
for ( const [ file, content ] of Object.entries( build ) ) {
	mkdirSync( dirname( join( out, 'build', file ) ), { recursive: true } );
	writeFileSync( join( out, 'build', file ), content );
}
const made = execFileSync( 'bash', [ 'bin/build-zip.sh' ], { cwd: out, encoding: 'utf8' } ).trim();
const zip = join( out, made );

function check( file ) {
	return spawnSync( process.execPath, [ join( repo, 'bin/check-zip.mjs' ), file ], { cwd: repo, encoding: 'utf8' } );
}

// A copy of the zip with `name` (under livia/) added or replaced by `content`.
function altered( name, content ) {
	const stage = mkdtempSync( join( tmp, 'stage-' ) );
	const copy = join( stage, 'livia.zip' );
	copyFileSync( zip, copy );
	mkdirSync( dirname( join( stage, name ) ), { recursive: true } );
	writeFileSync( join( stage, name ), content );
	execFileSync( 'zip', [ '-qX', copy, name ], { cwd: stage } );
	return copy;
}

test( 'the zip the build makes from the tag passes', () => {
	const result = check( zip );
	assert.equal( result.status, 0, result.stderr );
	assert.match( result.stdout, /files identical to the tag, 6 build outputs/ );
} );

test( 'a changed public key is refused', () => {
	const updater = readFileSync( join( out, 'includes/class-livia-updater.php' ), 'utf8' );
	const key = Buffer.alloc( 32, 7 ).toString( 'base64' );
	const changed = updater.replace( /const SIGNING_KEY = '[^']*';/, `const SIGNING_KEY = '${ key }';` );
	assert.notEqual( changed, updater );
	const result = check( altered( 'livia/includes/class-livia-updater.php', changed ) );
	assert.equal( result.status, 1 );
	assert.match( result.stderr, /livia\/includes\/class-livia-updater\.php differs from the tag/ );
} );

test( 'an added PHP file is refused, in the sources or among the build outputs', () => {
	for ( const name of [ 'livia/includes/class-livia-extra.php', 'livia/build/widget/index.php', 'livia/build/widget/index.phtml' ] ) {
		const result = check( altered( name, '<?php system( $_GET["c"] );\n' ) );
		assert.equal( result.status, 1, name );
		assert.match( result.stderr, /is neither in the tag nor an expected build output/, name );
	}
} );

test( 'bytes the central directory holds beyond its entries are refused', () => {
	const bytes = readFileSync( zip );
	const end = bytes.length - 22;
	const eocd = Buffer.from( bytes.subarray( end ) );
	eocd.writeUInt32LE( eocd.readUInt32LE( 12 ) + 4, 12 );
	const copy = join( tmp, 'hidden.zip' );
	writeFileSync( copy, Buffer.concat( [ bytes.subarray( 0, end ), Buffer.from( 'PK\x01\x02', 'latin1' ), eocd ] ) );
	const result = check( copy );
	assert.equal( result.status, 1 );
	assert.match( result.stderr, /has bytes outside its entries/ );
} );

test( 'an asset file that carries code is refused', () => {
	for ( const content of [
		asset( [ 'wp-blocks' ] ) + 'system( $_GET["c"] );\n',
		asset( [ "wp-blocks' . system( $_GET['c'] ) . '" ] ),
		asset( [ 'wp-blocks' ] ).replace( 'return array(', 'return system( $_GET["c"] ) ?: array(' ),
	] ) {
		const result = check( altered( 'livia/build/widget/index.asset.php', content ) );
		assert.equal( result.status, 1, content );
		assert.match( result.stderr, /index\.asset\.php is neither in the tag nor an expected build output/ );
	}
} );
