# Release

How versions are cut, how consumers pick them up, and what has to be rebuilt before a tag moves.

## Versioning

The package follows semantic versioning and, while it is pre-1.0, the laranail single-moving-tag convention: there is exactly one tag, `v0.1.0`, and each change moves it rather than cutting a new one. The `main` branch carries a `dev-main → 0.1.x-dev` branch alias, so a path or dev checkout still satisfies `^0.1`.

Consumers require the family's uniform constraint:

```json
"laranail/artisan-ui": "^0.1"
```

Graduating to 1.0 ends the moving tag: from then on every release is a new, immutable tag.

## Cutting a release

1. **Rebuild the committed assets.** `resources/dist/artisan-ui.js` and `resources/dist/artisan-ui.css` are committed, because consumers install through Composer and never run npm:

   ```bash
   npm install --no-audit --no-fund
   npm test
   npm run build
   git diff --exit-code resources/dist
   ```

   The `js` workflow runs the same build on every pull request and fails on any drift in `resources/dist`, and checks that the licence banner survived minification. A release with a stale bundle ships stale behaviour.
2. **Update `CHANGELOG.md`.** Add the changes under the version's heading, `## [0.1.0] - <date>`, in Keep a Changelog form. Items may reference the design ledger's IDs.
3. **Land the change on `main` through a pull request.** Never push to `main` directly.
4. **Move the tag once the branch has landed**, never before; a tag moved ahead of its branch points at a commit on no branch:

   ```bash
   git tag -f v0.1.0 origin/main
   git push --force origin v0.1.0
   ```

5. **Verify the tag**, dereferenced, against `origin/main`:

   ```bash
   git ls-remote origin 'refs/tags/v0.1.0^{}' 'refs/tags/v0.1.0'
   ```

## Release notes come from the changelog

Pushing a `v*.*.*` tag triggers `release.yml`, which:

- installs the runtime dependencies and generates a CycloneDX SBOM;
- extracts the `## [X.Y.Z]` section of `CHANGELOG.md` matching the tag and uses it as the GitHub release body, with a link to the full changelog;
- publishes the release with the SBOM attached, keeping the generated contributor list.

A missing section falls back to a link to the changelog, which is a release nobody reads, so keep the section current. The workflow never cancels a run in progress, since a half-published release is worse than a slow one.

## Consumers after a tag move

Composer caches the downloaded archive per package reference, and a moved tag keeps its name. `composer update` can therefore report success, resolve `v0.1.0`, and unpack the archive it cached the first time, without the new code. After a tag move:

```bash
composer clear-cache
composer update laranail/artisan-ui
```

To confirm the new code arrived, look for something the change added under `vendor/laranail/artisan-ui`, not at the tag.

If the application serves the assets in `published` mode, re-publish them after every update, or the panel keeps the old bundle:

```bash
php artisan vendor:publish --tag=laranail::artisan-ui-assets --force
```

`route` mode (the default) needs nothing: the asset URL carries a content hash, so a new bundle is a new URL.

---

[← Docs index](../README.md#documentation)
