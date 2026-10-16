/**
 * Builds a clean release zip: dist/easy-icons.zip
 *
 * 1. Regenerates icon data and compiled JS.
 * 2. Copies the plugin into dist/easy-icons/, skipping everything in .distignore.
 * 3. Zips it and prints the size (WordPress.org limit: 10 MB).
 *
 * Usage: npm run zip
 */
import fs from 'node:fs';
import path from 'node:path';
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const ROOT = path.resolve( path.dirname( fileURLToPath( import.meta.url ) ), '..' );
const SLUG = 'easy-icons';
const DIST = path.join( ROOT, 'dist' );
const STAGE = path.join( DIST, SLUG );
const ZIP = path.join( DIST, `${ SLUG }.zip` );
const LIMIT = 10 * 1024 * 1024;

const run = ( cmd, args, opts = {} ) => execFileSync( cmd, args, { cwd: ROOT, stdio: 'inherit', ...opts } );

run( 'npm', [ 'run', '-s', 'build:icons' ] );
if ( fs.existsSync( path.join( ROOT, 'src' ) ) ) {
	run( 'npm', [ 'run', '-s', 'build' ] );
}

fs.rmSync( DIST, { recursive: true, force: true } );
fs.mkdirSync( STAGE, { recursive: true } );

run( 'rsync', [ '-a', '--exclude-from=.distignore', './', `${ STAGE }/` ] );
run( 'zip', [ '-qr9X', ZIP, SLUG ], { cwd: DIST } );

const size = fs.statSync( ZIP ).size;
const mb = ( size / 1024 / 1024 ).toFixed( 2 );
console.log( `\n${ path.relative( ROOT, ZIP ) }: ${ mb } MB (limit 10 MB)` );
if ( size > LIMIT ) {
	console.error( 'ERROR: zip is over the WordPress.org 10 MB limit.' );
	process.exit( 1 );
}
