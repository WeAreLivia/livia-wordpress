// Checks a release zip against the tree of the checked out commit, the tag's, before it is signed: the build job
// that made the zip runs the npm dependencies' code, which must not change the public key or any PHP the sites run.
// Every file of the zip found in the tree must be identical to it, byte for byte; the others must be build outputs
// under livia/build/ (scripts, styles, block.json, and *.asset.php in the exact form wp-scripts writes). Fails on the
// first difference, and on any zip WordPress could read otherwise than this script: a comment, a gap between entries,
// a link, an encrypted, zip64 or duplicate entry.
// Usage: node bin/check-zip.mjs dist/livia-<version>.zip   (from the checkout of the tag)
import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { inflateRawSync } from 'node:zlib';

// json2php's output in @wordpress/dependency-extraction-webpack-plugin: tab indented, one dependency a line.
const ASSET =
	/^<\?php return array\(\n\t'dependencies' => array\(\n\t\t(?:'[a-z0-9@\/._-]+'(?:,\n\t\t'[a-z0-9@\/._-]+')*)?\n\t\),\n\t'version' => '[0-9a-f]{20}'\n\);\n$/;
const BUILD = /^livia\/build\/(?:[a-z0-9-]+\/)*[a-z0-9-]+(?:\.js|\.css|\.json|\.asset\.php)$/;
// No empty, `.` or `..` segment, and no hidden file such as .htaccess or .user.ini.
const NAME = /^livia\/(?:[A-Za-z0-9_@-][A-Za-z0-9_.@-]*\/)*(?:[A-Za-z0-9_@-][A-Za-z0-9_.@-]*)?$/;

const path = process.argv[ 2 ];
function fail( message ) {
	console.error( `${ path }: ${ message } Nothing is signed.` );
	process.exit( 1 );
}
if ( ! path ) {
	fail( 'Usage: node bin/check-zip.mjs dist/livia-<version>.zip' );
}

const tree = new Map();
for ( const line of execFileSync( 'git', [ 'ls-tree', '-r', '-z', 'HEAD' ], { encoding: 'utf8', maxBuffer: 1 << 26 } ).split( '\0' ) ) {
	if ( line ) {
		const [ meta, file ] = line.split( '\t' );
		const [ mode, , sha ] = meta.split( ' ' );
		tree.set( 'livia/' + file, { mode, sha } );
	}
}

const zip = readFileSync( path );
const end = zip.length - 22;
if ( end < 0 || 0x06054b50 !== zip.readUInt32LE( end ) ) {
	fail( 'is not a zip, or carries a comment.' );
}
const count = zip.readUInt16LE( end + 10 );
const cdSize = zip.readUInt32LE( end + 12 );
const cdStart = zip.readUInt32LE( end + 16 );
if ( zip.readUInt16LE( end + 4 ) || zip.readUInt16LE( end + 6 ) || zip.readUInt16LE( end + 8 ) !== count || cdStart + cdSize !== end ) {
	fail( 'has more than one disk, or bytes outside its entries.' );
}

const seen = new Set();
let at = cdStart;
let next = 0;
let kept = 0;
let built = 0;
for ( let i = 0; i < count; i++ ) {
	if ( at + 46 > end || 0x02014b50 !== zip.readUInt32LE( at ) ) {
		fail( 'has a broken central directory.' );
	}
	const flags = zip.readUInt16LE( at + 8 );
	const method = zip.readUInt16LE( at + 10 );
	const crc = zip.readUInt32LE( at + 16 );
	const size = zip.readUInt32LE( at + 20 );
	const length = zip.readUInt32LE( at + 24 );
	const nameLength = zip.readUInt16LE( at + 28 );
	const unix = zip.readUInt32LE( at + 38 ) >>> 16;
	const offset = zip.readUInt32LE( at + 42 );
	const name = zip.toString( 'latin1', at + 46, at + 46 + nameLength );
	at += 46 + nameLength + zip.readUInt16LE( at + 30 ) + zip.readUInt16LE( at + 32 );

	// Allowed flags: the deflate level (bits 1 and 2) and UTF-8 names (bit 11).
	if ( flags & ~0x0806 || ( 0 !== method && 8 !== method ) || 0o120000 === ( unix & 0o170000 ) ) {
		fail( `${ name } is encrypted, a link, or stored in an unexpected way.` );
	}
	if ( ! NAME.test( name ) ) {
		fail( `${ name } is no plain path under livia/.` );
	}
	const key = name.toLowerCase().replace( /\/$/, '' );
	if ( seen.has( key ) ) {
		fail( `${ name } appears twice.` );
	}
	seen.add( key );
	// The entries follow each other from the start, each local header repeating its central one.
	const data = offset + 30 + zip.readUInt16LE( offset + 26 ) + zip.readUInt16LE( offset + 28 );
	if (
		offset !== next ||
		data + size > cdStart ||
		0x04034b50 !== zip.readUInt32LE( offset ) ||
		zip.readUInt16LE( offset + 6 ) !== flags ||
		zip.readUInt16LE( offset + 8 ) !== method ||
		zip.readUInt32LE( offset + 14 ) !== crc ||
		zip.readUInt32LE( offset + 18 ) !== size ||
		zip.readUInt32LE( offset + 22 ) !== length ||
		zip.toString( 'latin1', offset + 30, offset + 30 + zip.readUInt16LE( offset + 26 ) ) !== name
	) {
		fail( `${ name }: its local header differs from the central directory, or entries overlap.` );
	}
	next = data + size;
	const bytes = 8 === method ? inflateRawSync( zip.subarray( data, next ) ) : zip.subarray( data, next );
	if ( bytes.length !== length ) {
		fail( `${ name }: its content does not have the size announced.` );
	}

	if ( name.endsWith( '/' ) ) {
		if ( length ) {
			fail( `${ name } is a folder with content.` );
		}
		continue;
	}
	const file = tree.get( name );
	if ( file ) {
		if ( ( '100644' !== file.mode && '100755' !== file.mode ) || ! bytes.equals( execFileSync( 'git', [ 'cat-file', 'blob', file.sha ], { maxBuffer: 1 << 26 } ) ) ) {
			fail( `${ name } differs from the tag.` );
		}
		kept++;
	} else if ( BUILD.test( name ) && ( ! name.endsWith( '.asset.php' ) || ASSET.test( bytes.toString( 'latin1' ) ) ) ) {
		built++;
	} else {
		fail( `${ name } is neither in the tag nor an expected build output.` );
	}
}
if ( next !== cdStart || at !== end ) {
	fail( 'has bytes outside its entries.' );
}
console.log( `${ path }: ${ kept } files identical to the tag, ${ built } build outputs.` );
