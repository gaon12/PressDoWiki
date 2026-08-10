# NamuMark clean-room implementation

PressDoWiki ships its own NamuMark-compatible renderer. It does not copy or
translate an AGPL parser, and contributors implementing this module must not use
third-party parser source code as a reference. Compatibility is derived from
publicly observable syntax behaviour and new tests written for this repository.
The repository-wide contribution rules are recorded in
`docs/clean-room-policy.md`, and the machine-readable component declaration is
`config/clean-room-components.json`.

## Compatibility order

1. Escape all ordinary text and reject unsafe URL schemes.
2. Enforce input, token, nesting, and expansion limits before adding features.
3. Implement block parsing into a small internal representation.
4. Implement inline parsing without allowing generated HTML to be parsed again.
5. Return explicit link and category metadata for backlink indexing.
6. Add one public-behaviour fixture for every supported syntax rule.

The current slice supports headings, paragraphs and line breaks, bold, italic,
underline, both common strike-through forms, internal links, HTTP(S) links,
quotes nested up to eight levels, unordered lists nested up to eight levels, and
literal `{{{` / `}}}` code blocks. Unknown syntax is rendered as escaped text.
Tables, ordered lists, footnotes, macros, includes, files, categories, redirects,
syntax highlighting, and parameterized styling remain unsupported until their
grammar and resource limits are specified and tested.

## Security invariants

- Source text never becomes raw HTML.
- Only `http` and `https` are accepted as external link schemes.
- Internal document names are encoded into local `/w/` routes.
- Documents larger than 2 MiB are rejected before parsing.
- Documents with more than 100,000 lines are rejected before block parsing.
- Quotes and lists stop at eight nesting levels.
- Literal code blocks are escaped and never sent through the inline parser.
- Unsupported or malformed constructs remain visible rather than disappearing.
- Later macro and include support must have depth, output-size, and cycle limits.
