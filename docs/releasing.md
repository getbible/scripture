# Releasing

## Before `0.1.0`

1. Complete the Phase 1 release gates in the roadmap.
2. Install `getbible/sword` through PIE in the integration job.
3. Verify the package name `getbible/scripture` on Packagist.
4. Configure Packagist's GitHub hook.
5. Confirm the repository ruleset protects `main` and release tags.

## Release preparation

1. Update `VERSION`.
2. Move applicable changelog entries from `Unreleased` into the version.
3. Run:

   ```bash
   composer check
   ```

4. Verify the installed-module integration workflow and retained evidence.
5. Merge through a maintainer-reviewed pull request.
6. Create an annotated `vVERSION` tag.

Packagist consumes the Composer package from the Git tag. The native extension
remains a separate PIE installation.

## Compatibility declarations

A release must state:

- PHP versions;
- Joomla Framework major versions;
- `getbible/sword` extension version;
- getBibleSword product and ABI versions;
- NDJSON contract identifier; and
- SWORD engine version.

Do not infer contract compatibility from the package tag alone.
