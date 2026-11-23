import { copyFile } from 'node:fs/promises'
import { fileURLToPath } from 'node:url'
import { build } from 'esbuild'

const here = fileURLToPath(new URL('.', import.meta.url))
const output = new URL('../../public/src/editor/', import.meta.url)

await build({
    absWorkingDir: here,
    entryPoints: {
        monaco: 'monaco.mjs',
        'editor.worker': 'monaco-editor/editor/editor.worker.js',
    },
    outdir: fileURLToPath(output),
    bundle: true,
    minify: true,
    format: 'esm',
    target: ['chrome109', 'firefox115', 'safari16.4'],
    loader: { '.ttf': 'file' },
    legalComments: 'linked',
})

await copyFile(new URL('./node_modules/monaco-editor/LICENSE', import.meta.url), new URL('LICENSE', output))
