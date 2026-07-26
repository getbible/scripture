# Releasing

## One-time repository setup

Before publishing any tag:

1. register `https://github.com/getbible/scripture` as
   `getbible/scripture` on Packagist;
2. enable Packagist's GitHub synchronization hook;
3. protect `main` and `v*.*.*` tags with repository rulesets;
4. require every CI job, including the released-extension native integration;
5. allow only the reviewed release workflow or release App to create protected
   tags; and
6. enable private vulnerability reporting.

Packagist reads package versions from Git tags. It does not need a package
upload or a publishing credential in the workflow.

## Release preparation

1. Update `VERSION`.
2. Move applicable changelog entries from `Unreleased` into a dated
   `## [VERSION] - YYYY-MM-DD` section.
3. Confirm `composer.json` declares the exact tested PHP, Joomla Framework, and
   native-extension compatibility.
4. Run:

   ```bash
   composer check
   composer audit
   ```

5. Review the latest coverage inventory. Every public class and method must
   have intentional test evidence; exclusions require a documented reason.
6. Review the retained deterministic native integration evidence for real
   module compilation, two verse lookups, metadata, initialization, and
   maintenance status. This required check must not depend on a live module
   mirror.
7. Verify the lowest-dependency and clean-distribution jobs.
8. Verify `pie install 'getbible/sword:^0.1.1'` resolves a published native
   source package for a clean supported PHP installation.
9. Merge through a maintainer-reviewed pull request and wait for required
   checks on `main`.

Changing `VERSION` does not publish the package.

## Automated release

Run the **Release** workflow from the default branch and enter the exact version
already present in `VERSION` and `CHANGELOG.md`. The workflow:

1. verifies stable semantic versioning and changelog alignment;
2. confirms the release commit is contained in the default branch;
3. runs Composer validation, tests, coding standards, static analysis, and the
   dependency security audit;
4. builds and installs a clean Composer archive;
5. creates and pushes the annotated `vVERSION` tag;
6. creates the GitHub release; and
7. attaches the package archive and `SHA256SUMS`.

A maintainer-created `v*.*.*` tag enters the same validation and publication
path. The tag must match `VERSION` exactly and point to a commit contained in
the default branch.

The release workflow does not register Packagist or alter its settings.
Packagist's configured GitHub hook consumes the validated tag.

## Compatibility declarations

A release must state:

- PHP versions;
- Joomla Framework major versions;
- `getbible/sword` extension version;
- getBibleSword product and ABI versions;
- NDJSON contract identifier; and
- SWORD engine version.

Do not infer contract compatibility from the package tag alone.

## Post-release verification

After Packagist has synchronized:

```bash
composer clear-cache
composer show getbible/scripture --all
```

In a clean supported runtime with the native extension already installed:

```bash
composer require getbible/scripture
vendor/bin/getbible-scripture scripture:doctor --json
```

Confirm that the resolved package tag, extension version, ABI, contract,
installed modules, and health output match the release record.
