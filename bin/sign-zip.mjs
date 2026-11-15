// Signs a release zip: writes <zip>.sig, the detached Ed25519 signature of the zip's bytes in base64, with the private
// key of LIVIA_WP_SIGNING_KEY (base64 of the 32-byte seed, or of libsodium's 64-byte secret key, seed first). Fails
// when the key is missing, or when the public key the plugin carries (Livia_Updater::SIGNING_KEY) is empty or does
// not verify the signature: the plugin would refuse that release.
// Usage: LIVIA_WP_SIGNING_KEY=... node bin/sign-zip.mjs dist/livia-<version>.zip
import { createPrivateKey, createPublicKey, sign, verify } from 'node:crypto';
import { readFileSync, writeFileSync } from 'node:fs';

function fail( message ) {
	console.error( message );
	process.exit( 1 );
}

const zip = process.argv[ 2 ];
if ( ! zip ) {
	fail( 'Usage: LIVIA_WP_SIGNING_KEY=... node bin/sign-zip.mjs dist/livia-<version>.zip' );
}
const secret = Buffer.from( process.env.LIVIA_WP_SIGNING_KEY || '', 'base64' );
if ( 32 !== secret.length && 64 !== secret.length ) {
	fail( 'LIVIA_WP_SIGNING_KEY is missing, or is not an Ed25519 private key in base64 (32-byte seed or 64-byte secret key).' );
}
const updater = readFileSync( new URL( '../includes/class-livia-updater.php', import.meta.url ), 'utf8' );
const constant = updater.match( /const\s+SIGNING_KEY\s*=\s*'([^']*)'/ );
const publicKey = Buffer.from( constant ? constant[ 1 ] : '', 'base64' );
if ( 32 !== publicKey.length ) {
	fail( 'Livia_Updater::SIGNING_KEY is empty or is not an Ed25519 public key in base64: the plugin would refuse this release.' );
}

// Raw Ed25519 keys wrapped in their DER envelopes (PKCS #8 for the private key, SPKI for the public one).
const privateKey = createPrivateKey( {
	key: Buffer.concat( [ Buffer.from( '302e020100300506032b657004220420', 'hex' ), secret.subarray( 0, 32 ) ] ),
	format: 'der',
	type: 'pkcs8',
} );
const plugin = createPublicKey( {
	key: Buffer.concat( [ Buffer.from( '302a300506032b6570032100', 'hex' ), publicKey ] ),
	format: 'der',
	type: 'spki',
} );
const data = readFileSync( zip );
const signature = sign( null, data, privateKey );
if ( ! verify( null, data, plugin, signature ) ) {
	fail( 'Livia_Updater::SIGNING_KEY does not match LIVIA_WP_SIGNING_KEY: the plugin would refuse this release.' );
}
writeFileSync( zip + '.sig', signature.toString( 'base64' ) );
console.log( zip + '.sig' );
