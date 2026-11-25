# NamuMark clean-room implementation

PressDoWiki ships its own NamuMark-compatible renderer. It does not copy or
translate an AGPL parser, and contributors implementing this module must not use
third-party parser source code as a reference. Compatibility is derived from
publicly observable syntax behaviour and new tests written for this repository.
The [contribution rules](clean-room-policy.md) and
[component declaration](../config/clean-room-components.json) record the
implementation boundary.

Set `wiki.mark` to `NamuMark` in `config/config.json` to use the renderer.
The [editor build](../README.md#편집기-빌드) supplies source highlighting in
Monaco. Rendered articles show code blocks as escaped literal text.

## Compatibility order

1. Escape all ordinary text and reject unsafe URL schemes.
2. Enforce input, token, nesting, and expansion limits before adding features.
3. Implement block parsing into a small internal representation.
4. Implement inline parsing without allowing generated HTML to be parsed again.
5. Return explicit link and category metadata for backlink indexing.
6. Add one public-behaviour fixture for every supported syntax rule.

The current slice supports headings, paragraphs and line breaks, bold, italic,
underline, both common strike-through forms, internal links, HTTP(S) links,
quotes nested up to eight levels, unordered and decimal, alphabetic, or Roman
lists nested up to eight levels, and literal `{{{` / `}}}` code blocks. Category
declarations and first-line Korean
or English redirect directives produce explicit backlink metadata. Unknown
syntax is rendered as escaped text. Basic consecutive `|| cell ||` table rows
are rendered through a dedicated table parser. Cells can span columns with
`<-N>` or adjacent delimiters, and rows with `<|N>`. Anonymous footnotes use
`[* text]`, and named notes use `[*name text]` followed by `[*name]` for
additional references. Notes appear at the end of a fragment or at a standalone
`[각주]` or `[footnote]` marker. General table attributes, nested footnotes,
other macros, includes, files, highlighted code output, and parameterized styling
remain unsupported.

The table and footnote syntax follows the
[public NamuMark syntax guide](https://namu.moe/w/나무위키:문법%20도움말).

Heading output uses nested `wiki-heading-content` wrappers. A child heading is
inside its parent's wrapper, while a heading at the same or a higher level
closes the preceding section. This keeps the HTML hierarchy valid and provides
the immediate-sibling contract used by the bundled skin's section folding.

## Security invariants

- Source text never becomes raw HTML.
- Only `http` and `https` are accepted as external link schemes.
- Internal document names are encoded into local `/w/` routes.
- Documents larger than 2 MiB are rejected before parsing.
- Documents with more than 100,000 lines are rejected before block parsing.
- Quotes and lists stop at eight nesting levels.
- A table row is limited to 128 cells and a document to 10,000 table cells.
- Numeric spans are limited to 128, and each rendered row spans at most 128 columns.
- A fragment can define up to 1,000 footnotes with up to 2,048 source bytes per note.
- Table contents use the same escaping, link validation, and token budgets as ordinary text.
- Literal code blocks are escaped and never sent through the inline parser.
- Redirect targets are local document names no longer than 255 bytes.
- Category names are limited to 255 bytes and deduplicated before indexing.
- Unsupported or malformed constructs remain visible rather than disappearing.
- Later macro and include support must have depth, output-size, and cycle limits.
