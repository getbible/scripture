# Security policy

## Supported versions

Security fixes are applied to the latest released minor line. Until `1.0.0`,
only the most recent `0.x` release is supported.

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

Module installation is intentionally not implemented through ABI v1. A future
provisioner must add explicit repository policy, TLS requirements, archive
validation, licensing decisions, interprocess locks, staging, and atomic
activation.
