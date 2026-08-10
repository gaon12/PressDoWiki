# Clean-room contribution policy

PressDoWiki replaces optional AGPL parsers and skins with independently written
first-party components. This policy protects that boundary; it does not change
the copyright or license of pre-existing PressDoWiki code.

## Allowed implementation inputs

- Publicly observable input and output behaviour.
- Public user-facing syntax documentation that does not contain implementation
  source code.
- Standards and permissively licensed libraries already accepted by the
  dependency policy.
- New tests and specifications written in this repository.

## Prohibited implementation inputs

- Source code, patches, generated code, or internal tests from an AGPL parser or
  skin.
- Mechanical translation, porting, or line-by-line imitation of such code.
- Copying an AGPL component into a build cache, fixture, submodule, release
  archive, or optional plugin directory.

If a contributor has inspected the implementation of a component being
replaced, they must disclose that fact before contributing to the replacement.
The project owner can then assign the implementation to someone who only sees
the public-behaviour specification and tests.

## Machine-enforced boundary

`config/clean-room-components.json` records the first-party replacement paths
and their implementation basis. `tests/Compliance/LicenseBoundaryTest.php`
fails when:

- a locked Composer package declares an AGPL license;
- a registered clean-room component is missing;
- a component records a third-party source-code reference; or
- the policy contains an invalid or ambiguous entry.

The check is deliberately strict. A dual-licensed dependency that advertises
AGPL must be reviewed and explicitly replaced with a dependency whose selected
license is unambiguous in the lock file.

## Repository license status

The Git history contains an AGPLv3 license while `composer.json` currently says
`proprietary`. Independently replacing the NamuMark renderer and bundled skin
does not resolve that repository-level conflict. Relicensing pre-existing code
requires confirmation from the relevant copyright holders; this policy must not
be treated as evidence that such permission exists.
