'use strict'

/** Render the wiki's supported inline math delimiters inside one DOM subtree. */
window.renderWikiMath = function (element) {
    if (typeof window.renderMathInElement !== 'function' || !element) {
        return
    }

    window.renderMathInElement(element, {
        delimiters: [
            { left: '[math(', right: ')]', display: false },
            { left: '<math>', right: '</math>', display: false },
        ],
    })
}

document.addEventListener('DOMContentLoaded', function () {
    window.renderWikiMath(document.body)
})
