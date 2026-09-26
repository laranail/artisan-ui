#!/usr/bin/env node
/**
 * Builds the two files the panel serves:
 *
 *   resources/dist/artisan-ui.js    the ES module client, from resources/js/index.js
 *   resources/dist/artisan-ui.css   the Tailwind stylesheet, from resources/css/artisan-ui.css
 *
 * Both are committed, because consumers install through Composer and never run npm; the CI
 * `js.yml` job rebuilds and fails on any drift. The client has no runtime dependencies, so
 * nothing third-party is bundled and no licence notice needs carrying beyond our own.
 */
import { execFileSync } from 'node:child_process'
import { readFileSync } from 'node:fs'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

import * as esbuild from 'esbuild'

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..')
const dev = process.argv.includes('--dev')
const pkg = JSON.parse(readFileSync(resolve(root, 'package.json'), 'utf8'))

await esbuild.build({
  entryPoints: [resolve(root, 'resources/js/index.js')],
  outfile: resolve(root, 'resources/dist/artisan-ui.js'),
  bundle: true,
  format: 'esm',
  platform: 'browser',
  target: ['es2022'],
  minify: !dev,
  sourcemap: dev ? 'inline' : false,
  legalComments: 'inline',
  banner: { js: `/*! laranail/artisan-ui v${pkg.version} | MIT (c) Simtabi LLC */` },
  logLevel: 'info',
})

execFileSync(
  resolve(root, 'node_modules/.bin/tailwindcss'),
  ['-i', 'resources/css/artisan-ui.css', '-o', 'resources/dist/artisan-ui.css', ...(dev ? [] : ['--minify'])],
  { cwd: root, stdio: 'inherit' },
)

console.log('Built resources/dist/artisan-ui.{js,css}.')
