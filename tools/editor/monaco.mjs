import { editor, languages } from 'monaco-editor/editor/editor.api.js'
import 'monaco-editor/editor/contrib/find/browser/findController.js'
import 'monaco-editor/languages/definitions/markdown/register.js'

self.MonacoEnvironment = {
    getWorker() {
        return new Worker(new URL('./editor.worker.js', import.meta.url), { type: 'module' })
    },
}

languages.register({ id: 'namumark' })
languages.setMonarchTokensProvider('namumark', {
    tokenizer: {
        root: [
            [/^##.*$/, 'comment'],
            [/^={1,6}\s.*?\s={1,6}\s*$/, 'keyword'],
            [/\[\[[^\]\n]+\]\]/, 'string.link'],
            [/\[(?:\*|include\()[^\]\n]*\]/, 'string'],
            [/\{\{\{|\}\}\}/, 'delimiter'],
            [/'{2,3}|~~|--/, 'keyword'],
        ],
    },
})

export function createWikiEditor(container, value, format) {
    const language = { namumark: 'namumark', markdown: 'markdown' }[format] || 'plaintext'
    return editor.create(container, {
        value,
        language,
        ariaLabel: '위키 문법 편집기',
        automaticLayout: true,
        minimap: { enabled: false },
        theme: document.body.classList.contains('pressdo-dark-mode') ? 'vs-dark' : 'vs',
        wordWrap: 'on',
    })
}
