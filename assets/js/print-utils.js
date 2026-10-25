/**
 * print-utils.js
 * Opens a clean print-only popup window containing just the report section.
 * No navbar, sidebar, or system chrome is printed.
 *
 * Usage: printSection('report-print-area', 'رپورٹ')
 *   containerId – id of the element wrapping the printable content
 *   title       – window title shown in the browser print dialog
 */
function printSection(containerId, title) {
    const el = document.getElementById(containerId);
    if (!el) { window.print(); return; }

    // Collect all <link rel="stylesheet"> hrefs from the host page
    const styleLinks = Array.from(document.querySelectorAll('link[rel="stylesheet"]'))
        .map(l => `<link rel="stylesheet" href="${l.href}">`)
        .join('\n');

    // Inline <style> blocks (Bootstrap overrides etc.)
    const styleBlocks = Array.from(document.querySelectorAll('style'))
        .map(s => `<style>${s.innerHTML}</style>`)
        .join('\n');

    // Current page language/direction so RTL Urdu still works
    const dir  = document.documentElement.getAttribute('dir')  || 'ltr';
    const lang = document.documentElement.getAttribute('lang') || 'en';

    // Build a minimal clean page with just the report content
    const html = `<!DOCTYPE html>
<html lang="${lang}" dir="${dir}">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>${title || 'Print'}</title>
  ${styleLinks}
  ${styleBlocks}
  <style>
    /* Strip system chrome — show only report content */
    body { background:#fff !important; padding: 16px; margin:0; }
    .no-print, .top-navbar, .sidebar, .app-wrapper > .sidebar { display:none !important; }
    /* Make tables fit page width */
    .table { width: 100% !important; }
    /* Hide action buttons inside the printed section */
    .btn, button { display:none !important; }
    @page { margin: 1.2cm; }
  </style>
</head>
<body>
  ${el.outerHTML}
  <script>
    // Auto-print as soon as all stylesheets have loaded
    window.onload = function() {
      setTimeout(function() { window.print(); }, 400);
    };
  <\/script>
</body>
</html>`;

    const pw = window.open('', '_blank', 'width=900,height=700,scrollbars=yes');
    if (!pw) {
        // Popup blocked — fallback to legacy visibility trick
        alert('براہ کرم پاپ اپ کی اجازت دیں (Allow popups) تاکہ پرنٹ ہو سکے۔');
        return;
    }
    pw.document.open();
    pw.document.write(html);
    pw.document.close();
    pw.focus();
}
