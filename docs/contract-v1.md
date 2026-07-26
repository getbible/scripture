# getBibleSword contract v1 mapping

## Compatibility gate

The package accepts only:

```text
contract: getbiblesword.ndjson/v1
contract_version: 1
native ABI: 1
```

The native product version is reported separately and is not used to infer the
contract.

## Record mapping

| v1 record | Scripture use |
|---|---|
| `header` | producer, contract, command, and SWORD provenance |
| `module` | `TranslationMetadata` for `classification=bible` |
| `config_source` | retained in the raw export |
| `config_entry` | ordered `ConfigEntry` metadata |
| `entry` | `Verse`, or separately indexed introduction |
| `artifact_*` | fully validated and retained in the raw export |
| `diagnostic` | retained and counted |
| `footer` | activation gate and generation identity |

## Byte values

Every source-derived byte value is represented by Base64, size, and SHA-256.
`ByteValue::fromArray()`:

1. requires `encoding=base64`;
2. performs strict Base64 decoding;
3. requires canonical re-encoding;
4. verifies decoded size;
5. verifies SHA-256; and
6. verifies that optional `utf8` is byte-identical to the decoded value.

Decoded bytes are authoritative.

## Scope

Bible entries use `scope.type=verse_key`. The package retains:

- testament;
- book position;
- chapter and verse;
- suffix;
- entry index;
- introduction scope;
- OSIS reference;
- book name and abbreviation; and
- versification.

An ordinary verse requires positive book, chapter, and verse values and
`intro_scope=verse`. Module, testament, book, and chapter introductions are
indexed separately.

## Annotation segments

The producer's annotation segments are lexical `text`, `markup`, or `entity`
slices. Markup and entities are `uninterpreted`; this package does not relabel
them as semantic spans.

The validator requires concatenated segment bytes to reproduce `entry.raw`
exactly. Applications may add markup-specific adapters above this lossless layer.

## Official attributes

SWORD official attributes are retained as ordered:

```text
OfficialAttributeType
  -> OfficialAttributeList
    -> OfficialAttributeValue
```

Repeated names and source order are not collapsed into associative arrays.

## Whole-stream validation

JSON Schema validation alone is insufficient. The package independently checks
sequence, phase order, framing, byte envelopes, annotation reconstruction,
artifact state, counts, footer success, and the exact stream digest.

The current reference Python validator can return process exit zero for a
structurally valid `success:false` footer. This package inspects the footer
itself and rejects it.

## Unknown data

Unknown additive record members remain present in the original record. Unknown
record types are rejected within contract v1 because their ordering and hashing
semantics are not defined by the accepted validator.

Normative upstream documentation:

- https://github.com/getbible/getbiblesword/blob/main/docs/contract-v1.md
- https://github.com/getbible/getbiblesword/blob/main/schema/v1/contract.schema.json
- https://github.com/getbible/getbiblesword/blob/main/docs/downstream-integration.md
