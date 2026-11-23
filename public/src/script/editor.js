const form = document.querySelector('[data-wiki-editor]')
const raw = form?.querySelector('textarea[name="content"]')
const visual = form?.querySelector('[data-editor-tab="visual"]')

if (form && raw && !raw.readOnly) {
    const tabs = new Map([...form.querySelectorAll('[data-editor-tab]')].map(tab => [tab.dataset.editorTab, tab]))
    const panels = new Map([...form.querySelectorAll('[data-editor-panel]')].map(panel => [panel.dataset.editorPanel, panel]))
    const toolbar = form.querySelector('[data-editor-toolbar]')
    const status = form.querySelector('[data-editor-status]')
    let editor
    let active = 'raw'
    let previewRequest

    for (const [name, tab] of tabs) {
        if (name === 'preview' || name === 'visual') {
            tab.hidden = false
        }
        tab.addEventListener('click', () => selectTab(name))
    }

    async function selectTab(name) {
        if (name === 'visual' && !editor) {
            const previous = active
            visual.disabled = true
            try {
                const { createWikiEditor } = await loadVisualEditor()
                if (active !== previous) {
                    return
                }
                showPanel('visual')
                editor = createWikiEditor(panels.get('visual').firstElementChild, raw.value, form.dataset.editorFormat)
                editor.onDidChangeModelContent(() => {
                    raw.value = editor.getValue()
                })
            } catch {
                showPanel('raw')
                status.textContent = '편집기를 불러오지 못했습니다. 원문 탭에서 계속 편집할 수 있습니다.'
                return
            } finally {
                visual.disabled = false
            }
        }

        if (name === 'visual' && active !== 'visual' && editor.getValue() !== raw.value) {
            editor.setValue(raw.value)
        }
        status.textContent = ''
        showPanel(name)
        if (name === 'visual') {
            editor.focus()
        }
        if (name === 'preview') {
            renderPreview()
        }
    }

    function showPanel(name) {
        active = name
        for (const [id, tab] of tabs) {
            tab.classList.toggle('a', id === name)
            tab.setAttribute('aria-pressed', String(id === name))
        }
        for (const [id, panel] of panels) {
            panel.classList.toggle('a', id === name)
        }
        if (toolbar) {
            toolbar.hidden = name !== 'visual'
        }
    }

    function loadVisualEditor() {
        let style = document.querySelector('link[data-monaco-styles]')
        let stylesheet = Promise.resolve()
        if (!style) {
            style = document.createElement('link')
            style.rel = 'stylesheet'
            style.href = '/src/editor/monaco.css'
            style.dataset.monacoStyles = ''
            stylesheet = new Promise((resolve, reject) => {
                style.addEventListener('load', resolve, { once: true })
                style.addEventListener('error', () => {
                    style.remove()
                    reject(new Error('Unable to load editor styles.'))
                }, { once: true })
            })
            document.head.append(style)
        }
        return Promise.all([import('/src/editor/monaco.js'), stylesheet]).then(([module]) => module)
    }

    async function renderPreview() {
        previewRequest?.abort()
        const request = new AbortController()
        previewRequest = request
        const target = panels.get('preview').firstElementChild
        const data = new FormData()
        data.append('text', raw.value)
        data.append('title', form.dataset.documentTitle)
        data.append('_csrf', form.elements.namedItem('_csrf').value)

        try {
            const response = await fetch('/api/preview', {
                method: 'POST',
                body: data,
                signal: request.signal,
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            })
            const html = await response.text()
            if (request.signal.aborted) {
                return
            }
            if (!response.ok) {
                target.textContent = html || '미리보기를 불러오지 못했습니다.'
                return
            }
            target.innerHTML = html
            window.renderWikiMath?.(target)
        } catch {
            if (!request.signal.aborted) {
                target.textContent = '미리보기를 불러오지 못했습니다.'
            }
        }
    }

    toolbar?.addEventListener('click', event => {
        const button = event.target.closest('[data-editor-prefix]')
        if (!button || !editor) {
            return
        }
        const model = editor.getModel()
        const edits = editor.getSelections().map(range => ({
            range,
            text: button.dataset.editorPrefix + model.getValueInRange(range) + button.dataset.editorSuffix,
        }))
        editor.pushUndoStop()
        editor.executeEdits('wiki-toolbar', edits)
        editor.pushUndoStop()
        editor.focus()
    })

    form.addEventListener('submit', event => {
        if (form.dataset.editorCaptcha === '1' && typeof window.grecaptcha?.execute === 'function') {
            event.preventDefault()
            window.grecaptcha.execute()
        }
    })
}
