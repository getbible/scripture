# Native integration fixture

`verse.imp` is a deliberately minimal SWORD IMP module source authored for
this repository. It contains two short Public Domain Bible-text samples and no
third-party module archive.

Required CI compiles the source locally with the Ubuntu Noble
`libsword-utils` 1.9.0 `imp2vs` utility, then reads the resulting module through
the released `getbiblesword` PHP extension and the complete Scripture package.
The source SHA-256 is pinned by `scripts/create-native-fixture.sh`, and CI
retains the generated module-tree hash with its integration evidence.

The fixture is distributed under the repository's GPL-2.0-only license. The
underlying two Bible phrases are Public Domain.
