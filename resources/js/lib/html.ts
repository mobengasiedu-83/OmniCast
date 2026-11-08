/**
 * The words an HTML body carries, so a body can be shown or counted as copy.
 *
 * Block level elements become line breaks and `<br>` survives as one, which is
 * enough to read the message back without a parser dependency.
 */
export function htmlToPlainText(html: string): string {
    if (html.trim() === '') {
        return '';
    }

    if (typeof document === 'undefined') {
        return html;
    }

    const container = document.createElement('div');
    container.innerHTML = html;

    container.querySelectorAll('br').forEach((element) => {
        element.replaceWith('\n');
    });

    container
        .querySelectorAll('p, div, li, h1, h2, h3, h4, blockquote, tr')
        .forEach((element) => {
            element.append('\n');
        });

    return (container.textContent ?? '').replace(/\n{3,}/g, '\n\n').trim();
}
