#!/usr/bin/env node
/**
 * Run a PHP script using a discoverable PHP binary.
 *
 * Usage: node tools/run-php.js path/to/script.php
 *
 * Override binary: set PHP_BIN=C:\path\to\php.exe
 */

const { spawnSync } = require( 'child_process' );
const fs = require( 'fs' );
const path = require( 'path' );

const scriptPath = process.argv[ 2 ];

if ( ! scriptPath ) {
	console.error( 'Usage: node tools/run-php.js <script.php>' );
	process.exit( 1 );
}

const candidates = [
	process.env.PHP_BIN,
	process.env.XAMPP_PHP,
	'C:\\xampp\\php\\php.exe',
	'C:\\Program Files\\xampp\\php\\php.exe',
	'php',
].filter( Boolean );

function resolvePhpBinary() {
	for ( const candidate of candidates ) {
		if ( candidate === 'php' ) {
			return candidate;
		}

		if ( fs.existsSync( candidate ) ) {
			return candidate;
		}
	}

	return null;
}

const phpBinary = resolvePhpBinary();

if ( ! phpBinary ) {
	console.error(
		'PHP binary not found. Set PHP_BIN to your php.exe path (e.g. C:\\xampp\\php\\php.exe).'
	);
	process.exit( 1 );
}

const absoluteScript = path.resolve( scriptPath );
const result = spawnSync( phpBinary, [ absoluteScript ], {
	stdio: 'inherit',
	shell: phpBinary === 'php',
} );

process.exit( result.status ?? 1 );
