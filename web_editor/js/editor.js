function execCommand(command, value = null) {
    document.execCommand(command, false, value);
    document.getElementById('editor').focus();
}

function togglePreview() {
    document.body.classList.toggle('preview-mode');
    const btn = document.getElementById('previewBtn');
    btn.textContent = document.body.classList.contains('preview-mode') ? 'Edit Mode' : 'Preview';
}

function rgbToHex(rgb) {
    if (!rgb || !rgb.startsWith('rgb')) return rgb;
    const parts = rgb.match(/^rgb\((\d+),\s*(\d+),\s*(\d+)\)$/);
    if (!parts) return rgb;
    let hex = '#';
    for (let i = 1; i <= 3; ++i) {
        hex += parseInt(parts[i]).toString(16).padStart(2, '0');
    }
    return hex;
}

function sanitizeHTML(html) {
    const temp = document.createElement('div');
    temp.innerHTML = html;

    const allowedTags = ['H1', 'DIV', 'P', 'B'];

    function processNode(node) {
        if (node.nodeType === Node.ELEMENT_NODE) {
            // Process children first (bottom-up)
            const children = Array.from(node.childNodes);
            children.forEach(processNode);

            if (!allowedTags.includes(node.tagName)) {
                // Unwrap disallowed tag
                while (node.firstChild) {
                    node.parentNode.insertBefore(node.firstChild, node);
                }
                node.parentNode.removeChild(node);
            } else {
                // Clean allowed tag
                if (node.style.color) {
                    node.style.color = rgbToHex(node.style.color);
                }
                // Only keep style attribute
                const attrs = Array.from(node.attributes);
                attrs.forEach(attr => {
                    if (attr.name !== 'style') {
                        node.removeAttribute(attr.name);
                    }
                });
            }
        }
    }

    Array.from(temp.childNodes).forEach(processNode);
    return temp.innerHTML;
}

async function exportPDF() {
    const editor = document.getElementById('editor');
    const rawHTML = editor.innerHTML;
    const cleanHTML = sanitizeHTML(rawHTML);

    try {
        const response = await fetch('generate.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'html=' + encodeURIComponent(cleanHTML)
        });

        if (response.ok) {
            const blob = await response.blob();
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'document.pdf';
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
        } else {
            alert('Error generating PDF');
        }
    } catch (error) {
        console.error('Export failed:', error);
        alert('Export failed');
    }
}
