<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex">
        <title>{{ $title }} ({{ $version }})</title>

        <style>
            * {
                box-sizing: border-box;
            }

            body {
                margin: 0;
                padding: 2.5rem 1.5rem 4rem;
                background: #f6f7f9;
                color: #14161a;
                font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
                font-size: 12pt;
                line-height: 1.6;
            }

            .sheet {
                max-width: 46rem;
                margin: 0 auto;
                padding: 3rem;
                background: #ffffff;
                border: 1px solid #e3e6eb;
                border-radius: 0.75rem;
            }

            header.doc-header {
                border-bottom: 1px solid #e3e6eb;
                padding-bottom: 1.25rem;
                margin-bottom: 2rem;
            }

            .eyebrow {
                margin: 0;
                font-size: 8pt;
                letter-spacing: 0.14em;
                text-transform: uppercase;
                color: #6b7280;
            }

            h1 {
                margin: 0.35rem 0 0.5rem;
                font-size: 22pt;
                line-height: 1.2;
            }

            .meta {
                margin: 0;
                font-size: 9.5pt;
                color: #4b5563;
            }

            .summary {
                margin: 0.85rem 0 0;
                font-size: 10.5pt;
                color: #4b5563;
            }

            .document h1,
            .document h2,
            .document h3,
            .document h4 {
                line-height: 1.25;
                margin: 1.75rem 0 0.6rem;
                page-break-after: avoid;
            }

            .document h1 {
                font-size: 15pt;
            }

            .document h2 {
                font-size: 13pt;
            }

            .document h3 {
                font-size: 11.5pt;
            }

            .document p,
            .document li {
                orphans: 2;
                widows: 2;
            }

            .document ul,
            .document ol {
                padding-left: 1.25rem;
            }

            .document table {
                width: 100%;
                border-collapse: collapse;
                margin: 1rem 0;
                font-size: 10pt;
            }

            .document th,
            .document td {
                border: 1px solid #d7dbe2;
                padding: 0.45rem 0.6rem;
                text-align: left;
                vertical-align: top;
            }

            .document th {
                background: #f3f4f6;
            }

            .document blockquote {
                margin: 1rem 0;
                border-left: 3px solid #d7dbe2;
                padding-left: 1rem;
                color: #4b5563;
            }

            .document a {
                color: #1d4ed8;
            }

            .toolbar {
                max-width: 46rem;
                margin: 0 auto 1rem;
                display: flex;
                flex-wrap: wrap;
                align-items: center;
                justify-content: space-between;
                gap: 0.75rem;
                font-size: 9.5pt;
                color: #4b5563;
            }

            .toolbar button {
                border: 1px solid #1d4ed8;
                border-radius: 0.5rem;
                background: #1d4ed8;
                color: #ffffff;
                padding: 0.5rem 0.9rem;
                font: inherit;
                font-weight: 600;
                cursor: pointer;
            }

            .toolbar a {
                color: #1d4ed8;
            }

            footer.doc-footer {
                margin-top: 2.5rem;
                border-top: 1px solid #e3e6eb;
                padding-top: 1rem;
                font-size: 8.5pt;
                color: #6b7280;
            }

            @page {
                size: A4;
                margin: 18mm 16mm;
            }

            @media print {
                body {
                    background: #ffffff;
                    padding: 0;
                    font-size: 11pt;
                }

                .sheet {
                    max-width: none;
                    border: 0;
                    border-radius: 0;
                    padding: 0;
                }

                .toolbar {
                    display: none;
                }
            }
        </style>
    </head>
    <body>
        <div class="toolbar">
            <span>
                Print-ready copy. Choose &ldquo;Save as PDF&rdquo; in the print
                dialog to keep a copy of {{ $version }}.
            </span>
            <button type="button" onclick="window.print()">Save as PDF</button>
        </div>

        <div class="sheet">
            <header class="doc-header">
                <p class="eyebrow">{{ $platform }}</p>
                <h1>{{ $title }}</h1>
                <p class="meta">
                    {{ $version }}
                    @if ($effectiveOn)
                        &middot; effective {{ $effectiveOn }}
                    @endif
                    @if ($publishedAt)
                        &middot; published {{ $publishedAt }}
                    @endif
                </p>
                @if ($summary)
                    <p class="summary">{{ $summary }}</p>
                @endif
            </header>

            <article class="document">
                {!! $body !!}
            </article>

            <footer class="doc-footer">
                <p>
                    {{ $platform }} &middot; {{ $title }} &middot;
                    {{ $version }}. Printed from
                    {{ url('/legal/'.$slug) }} on {{ $generatedAt }}. The live
                    version of this document is always the one published at that
                    address.
                </p>
            </footer>
        </div>

        <script>
            window.addEventListener('load', function () {
                if (window.location.search.indexOf('auto=0') === -1) {
                    window.print();
                }
            });
        </script>
    </body>
</html>

