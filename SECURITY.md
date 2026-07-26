# Security policy

## Supported versions

Security fixes are applied to the latest tagged release and to the default
branch. Older release lines are not supported unless a security advisory
explicitly states otherwise.

## Reporting

Do not disclose a suspected vulnerability in a public issue. Use GitHub's
private vulnerability reporting for `getbible/scripture`.

Include:

- affected package and native extension versions;
- operating system and PHP version;
- a minimal reproducer;
- whether untrusted NDJSON, module archives, or module paths are involved; and
- the expected and observed behavior.

## Trust boundaries

SWORD modules and their metadata are untrusted input. This package verifies the
v1 stream, byte envelopes, artifact framing, sequence values, footer digest, and
success state before activating a snapshot.

Raw and rendered markup is data, not trusted HTML. Callers must escape or
sanitize it for the destination context.

Module installation is intentionally not implemented through ABI v1. Any
injected provisioner must add explicit repository policy, TLS requirements,
archive validation, licensing decisions, interprocess locks, staging, atomic
activation, and rollback.
