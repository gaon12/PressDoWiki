(() => {
    try {
        const settings = JSON.parse(localStorage.getItem('pressdo_settings') || '{}')
        const theme = settings?.['wiki.theme'] ?? 'auto'
        const dark = theme === 'dark' || (theme === 'auto' && matchMedia('(prefers-color-scheme: dark)').matches)
        document.body.classList.toggle('pressdo-dark-mode', dark)
        document.body.classList.toggle('pressdo-light-mode', !dark)
    } catch {
        // Browser settings can be unavailable or invalid. Use the stylesheet defaults.
    }
})()
