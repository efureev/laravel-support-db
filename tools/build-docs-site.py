#!/usr/bin/env python3
"""Render docs/*.md into a single self-contained HTML page.

The markdown under docs/ is the source of truth; this script only presents it. Run it after
changing anything there:

    python3 tools/build-docs-site.py [output.html]

Markdown supported — the subset docs/ actually uses: h1/h2 headings, paragraphs, fenced code
(php, sql, bash), blockquotes, GFM tables, and inline code/links/bold/italic. A ```php fence
immediately followed by a ```sql fence renders as a call-and-emits pair rather than two blocks.
"""

import html
import pathlib
import re
import sys

ROOT = pathlib.Path(__file__).resolve().parent.parent
DOCS = ROOT / 'docs'
CSS = pathlib.Path(__file__).resolve().parent / 'docs-site.css'


def released_version():
    """The newest released version, from CHANGELOG.md.

    Read from the file rather than from git: a shallow CI checkout carries no tags, and the
    changelog is the release record anyway. An `unreleased` heading is skipped, not being a
    version yet.
    """
    for line in (ROOT / 'CHANGELOG.md').read_text().split('\n'):
        m = re.match(r'^##\s+\[?v?(\d+\.\d+\.\d+[0-9A-Za-z.-]*)\]?', line)
        if m:
            return 'v' + m.group(1)
    return ''


# page key, source file, rail label, eyebrow
PAGES = [
    ('index',   'readme.md',        'Documentation',          'laravel-support-db'),
    ('start',   'installation.md',  'Getting started',        'Reference 1'),
    ('columns', 'column-types.md',  'Columns',                'Reference 2'),
    ('indexes', 'indexes.md',       'Indexes',                'Reference 3'),
    ('views',   'views.md',         'Views',                  'Reference 4'),
    ('schema',  'schema.md',        'Schema operations',      'Reference 5'),
    ('query',   'query-builder.md', 'Query builder',          'Reference 6'),
    ('recipes', 'recipes.md',       'Recipes',                'Practice'),
    ('notes',   'behaviour.md',     'Behaviour notes',        'Practice'),
    ('roadmap', 'roadmap.md',       'Roadmap',                'Practice'),
    ('contrib', 'contributing.md',  'Testing & contributing', 'Practice'),
]
GROUPS = {'start': 'Reference', 'recipes': 'Practice'}       # rail group label starts here
FILE_TO_KEY = {src: key for key, src, _, _ in PAGES}
UNRESOLVED = []   # relative links that name no page; the build refuses to ship them


# ─────────────────────────────── inline ───────────────────────────────

def esc(text):
    return html.escape(text, quote=False)


def inline(text):
    """Inline markdown to HTML. Code spans are protected from every other rule."""
    spans = []

    def stash(m):
        spans.append('<code>%s</code>' % esc(m.group(1)))
        return '\x00%d\x00' % (len(spans) - 1)

    text = re.sub(r'`([^`]+)`', stash, text)
    text = esc(text)

    def link(m):
        label, href = m.group(1), m.group(2)
        if not href.startswith(('http://', 'https://', '#')):
            # a link between docs pages becomes a link between pages of this site
            target = FILE_TO_KEY.get(href.split('#')[0].removeprefix('./'))
            if target is None:
                UNRESOLVED.append(href)
                return label
            href = '#' + target
        return '<a href="%s">%s</a>' % (href, label)

    text = re.sub(r'\[([^\]]+)\]\(([^)]+)\)', link, text)
    text = re.sub(r'\*\*([^*]+)\*\*', r'<strong>\1</strong>', text)
    text = re.sub(r'(?<![\w*])\*([^*\n]+)\*(?![\w*])', r'<em>\1</em>', text)
    text = re.sub(r'\x00(\d+)\x00', lambda m: spans[int(m.group(1))], text)
    return text


# ─────────────────────────────── code ───────────────────────────────

def highlight(code, lang):
    """Comments and string literals only — guessing at keywords does more harm than good."""
    out, i, n = [], 0, len(code)
    line_comment = '--' if lang == 'sql' else ('#' if lang == 'bash' else '//')
    while i < n:
        ch = code[i]
        if code.startswith(line_comment, i):
            j = code.find('\n', i)
            j = n if j == -1 else j
            out.append('<span class="c">%s</span>' % esc(code[i:j]))
            i = j
        elif ch in '\'"':
            # bounded at the newline, so an unpaired quote spoils one line rather than the block
            j = i + 1
            while j < n and code[j] not in (ch, '\n'):
                j += 2 if code[j] == '\\' else 1
            j = j if (j < n and code[j] == '\n') else min(j + 1, n)
            out.append('<span class="s">%s</span>' % esc(code[i:j]))
            i = j
        else:
            out.append(esc(ch))
            i += 1
    return ''.join(out)


def code_block(code, lang):
    cls = 'code term' if lang in ('sql', 'bash') else 'code'
    return '<pre class="%s">%s</pre>' % (cls, highlight(code.rstrip('\n'), lang))


def pair_block(php, sqlc):
    return (
        '<div class="pair">\n'
        '  <div class="pair-php"><div class="pair-label">Call</div><pre>%s</pre></div>\n'
        '  <div class="pair-sql"><div class="pair-label">Emits</div><pre>%s</pre></div>\n'
        '</div>' % (highlight(php.rstrip('\n'), 'php'), highlight(sqlc.rstrip('\n'), 'sql'))
    )


# ─────────────────────────────── blocks ───────────────────────────────

NAV_LINE = re.compile(r'^\s*\[[^\]]*\]\((?:\.\./)?readme\.md\)')


def is_nav(text):
    """The link line each page carries for reading it on GitHub; the rail replaces it here."""
    return bool(NAV_LINE.match(text.strip()))


def split_blocks(text):
    """(kind, payload) tuples. Fences are taken whole so their contents are never parsed."""
    lines, blocks, i = text.split('\n'), [], 0
    while i < len(lines):
        ln = lines[i]
        m = re.match(r'^```([a-zA-Z]*)\s*$', ln)
        if m:
            lang, body, i = m.group(1).lower() or 'text', [], i + 1
            while i < len(lines) and not lines[i].startswith('```'):
                body.append(lines[i]); i += 1
            i += 1
            blocks.append(('code', (lang, '\n'.join(body))))
        elif re.match(r'^#{1,6}\s', ln):
            h = re.match(r'^(#{1,6})\s+(.*)$', ln)
            blocks.append(('h%d' % min(len(h.group(1)), 4), h.group(2).strip()))
            i += 1
        elif ln.startswith('>'):
            body = []
            while i < len(lines) and lines[i].startswith('>'):
                body.append(lines[i].lstrip('>').strip()); i += 1
            blocks.append(('quote', '\n'.join(body)))
        elif re.match(r'^\s*([-*]|\d+\.)\s', ln):
            ordered = bool(re.match(r'^\s*\d+\.\s', ln))
            items = []
            while i < len(lines) and re.match(r'^\s*([-*]|\d+\.)\s', lines[i]):
                items.append(re.sub(r'^\s*([-*]|\d+\.)\s+', '', lines[i]))
                i += 1
                while i < len(lines) and lines[i].startswith('  ') and lines[i].strip():
                    items[-1] += ' ' + lines[i].strip()      # a wrapped continuation line
                    i += 1
            blocks.append(('list', (ordered, items)))
        elif ln.startswith('|'):
            rows = []
            while i < len(lines) and lines[i].startswith('|'):
                rows.append(lines[i]); i += 1
            blocks.append(('table', rows))
        elif ln.strip() == '' or ln.strip() == '---':
            i += 1
        else:
            body = [lines[i]]
            i += 1                                   # always advance: a '#' line that is not a
            while (i < len(lines) and lines[i].strip()      # heading would otherwise loop forever
                   and not lines[i].startswith(('#', '>', '|', '```'))):
                body.append(lines[i]); i += 1
            blocks.append(('p', ' '.join(body)))
    return blocks


def render_table(rows):
    cells = [[c.strip() for c in r.strip().strip('|').split('|')] for r in rows]
    head, body = cells[0], cells[2:] if len(cells) > 2 else []
    head_html = ''.join('<th>%s</th>' % inline(c) for c in head)
    body_html = ''.join(
        '<tr>%s</tr>' % ''.join('<td>%s</td>' % inline(c) for c in row) for row in body
    )
    return ('<div class="tw"><table>\n  <thead><tr>%s</tr></thead>\n'
            '  <tbody>%s</tbody>\n</table></div>' % (head_html, body_html))


def render(blocks):
    out, i, open_section = [], 0, False
    while i < len(blocks):
        kind, payload = blocks[i]

        # a php fence followed by a sql fence is one call-and-emits pair
        if kind == 'code' and payload[0] == 'php' and i + 1 < len(blocks):
            nxt = blocks[i + 1]
            if nxt[0] == 'code' and nxt[1][0] == 'sql':
                out.append(pair_block(payload[1], nxt[1][1]))
                i += 2
                continue

        if kind == 'h1':
            i += 1                                    # the page title comes from PAGES
        elif kind == 'h2':
            if open_section:
                out.append('</section>')
            out.append('<section><h2>%s</h2>' % inline(payload))
            open_section = True
            i += 1
        elif kind in ('h3', 'h4'):
            out.append('<h3>%s</h3>' % inline(payload))
            i += 1
        elif kind == 'p':
            if not is_nav(payload):
                out.append('<p>%s</p>' % inline(payload))
            i += 1
        elif kind == 'quote':
            paras = ''.join('<p>%s</p>' % inline(p) for p in payload.split('\n\n'))
            out.append('<div class="note">%s</div>' % paras)
            i += 1
        elif kind == 'list':
            ordered, items = payload
            tag = 'ol' if ordered else 'ul'
            out.append('<%s>%s</%s>' % (tag, ''.join('<li>%s</li>' % inline(x) for x in items), tag))
            i += 1
        elif kind == 'table':
            out.append(render_table(payload))
            i += 1
        elif kind == 'code':
            out.append(code_block(payload[1], payload[0]))
            i += 1
        else:
            i += 1
    if open_section:
        out.append('</section>')
    return '\n'.join(out)


# ─────────────────────────────── page ───────────────────────────────

NAV_FOOT = '''      <div class="rail-foot">
        <a href="https://github.com/efureev/laravel-support-db">GitHub</a>
        <a href="https://packagist.org/packages/efureev/laravel-support-db">Packagist</a>
        <a href="https://github.com/efureev/laravel-support-db/blob/master/CHANGELOG.md">Changelog</a>
      </div>'''

SCRIPT = '''<script>
(function () {
  document.documentElement.className += ' js';
  var pages = Array.prototype.slice.call(document.querySelectorAll('.page'));
  var links = Array.prototype.slice.call(document.querySelectorAll('.rail nav a'));
  var ids   = pages.map(function (p) { return p.getAttribute('data-page'); });
  var rail  = document.querySelector('.railnav');
  var narrow = function () { return window.matchMedia('(max-width: 920px)').matches; };

  function show(id, scroll) {
    if (ids.indexOf(id) === -1) { id = 'index'; }
    pages.forEach(function (p) { p.classList.toggle('on', p.getAttribute('data-page') === id); });
    links.forEach(function (a) {
      if (a.getAttribute('href') === '#' + id) { a.setAttribute('aria-current', 'page'); }
      else { a.removeAttribute('aria-current'); }
    });
    var page = document.getElementById('p-' + id);
    var h1 = page && page.querySelector('h1');
    document.title = (id === 'index' || !h1) ? 'laravel-support-db'
                                             : h1.textContent + ' \\u00b7 laravel-support-db';
    if (scroll) { window.scrollTo(0, 0); }
    if (narrow() && rail) { rail.removeAttribute('open'); }
  }

  window.addEventListener('hashchange', function () { show(location.hash.slice(1), true); });
  show(location.hash.slice(1) || 'index', false);
})();
</script>'''


def lede_of(text):
    """The first paragraph after the nav rule is the page's standfirst."""
    for kind, payload in split_blocks(text):
        if kind == 'p' and not is_nav(payload):
            return payload
    return ''


def build():
    css = CSS.read_text()
    articles, nav = [], []

    for key, src, label, eyebrow in PAGES:
        if key in GROUPS:
            nav.append('        <span class="grp">%s</span>' % GROUPS[key])
        nav.append('        <a href="#%s">%s</a>' % (key, esc(label)))

    for i, (key, src, label, eyebrow) in enumerate(PAGES):
        text = (DOCS / src).read_text()
        title = re.search(r'^#\s+(.*)$', text, re.M).group(1).strip()
        lede = lede_of(text)
        body = render(split_blocks(text))

        # the lede is the page head, not the first paragraph of the body
        body = body.replace('<p>%s</p>' % inline(lede), '', 1)

        head = ('    <div class="page-head">\n'
                '      <p class="eyebrow">%s</p>\n'
                '      <h1>%s</h1>\n'
                '      <p class="lede">%s</p>\n'
                '    </div>' % (esc(eyebrow), inline(title), inline(lede)))

        pager = ['    <nav class="pager">']
        if i:
            pager.append('      <a href="#%s"><span class="dir">Previous</span>%s</a>'
                         % (PAGES[i - 1][0], esc(PAGES[i - 1][2])))
        if i < len(PAGES) - 1:
            pager.append('      <a class="next" href="#%s"><span class="dir">Next</span>%s</a>'
                         % (PAGES[i + 1][0], esc(PAGES[i + 1][2])))
        pager.append('    </nav>')

        articles.append('  <article class="page" id="p-%s" data-page="%s">\n%s\n%s\n%s\n  </article>'
                        % (key, key, head, body, '\n'.join(pager)))

    page = '\n'.join([
        '<!doctype html>',
        '<html lang="en">',
        '<head>',
        '<meta charset="utf-8">',
        '<meta name="viewport" content="width=device-width, initial-scale=1">',
        '<title>laravel-support-db</title>',
        '<link rel="preconnect" href="https://fonts.googleapis.com">',
        '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>',
        '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?'
        'family=Archivo:wght@500;600;700&'
        'family=Source+Serif+4:ital,opsz,wght@0,8..60,400;0,8..60,600;1,8..60,400&'
        'family=IBM+Plex+Mono:wght@400;500;600&display=swap">',
        '',
        '<style>\n%s</style>' % css,
        '</head>',
        '<body>',
        '',
        '<div class="shell">',
        '  <aside class="rail">',
        '    <a class="brand" href="#index">laravel-support-db</a>',
        '    <span class="brand-v">%s &middot; docs</span>' % esc(released_version()),
        '    <details class="railnav" open>',
        '      <summary class="rail-title">Contents</summary>',
        '      <div class="rail-inner">',
        '        <nav>',
        '\n'.join(nav),
        '        </nav>',
        NAV_FOOT,
        '      </div>',
        '    </details>',
        '  </aside>',
        '',
        '  <main>',
        '\n\n'.join(articles),
        '  </main>',
        '</div>',
        '',
        SCRIPT,
        '</body>',
        '</html>',
    ])

    if UNRESOLVED:
        raise SystemExit('links that resolve to no page: ' + ', '.join(sorted(set(UNRESOLVED))))

    return page


if __name__ == '__main__':
    target = pathlib.Path(sys.argv[1]) if len(sys.argv) > 1 else ROOT / 'build' / 'docs.html'
    target.parent.mkdir(parents=True, exist_ok=True)
    out = build()
    tmp = target.with_suffix(target.suffix + '.new')
    tmp.write_text(out, encoding='utf-8')
    tmp.replace(target)                              # never truncate a good file on failure
    print('%s  %.1f KB  %d pages' % (target, len(out) / 1024, len(PAGES)))
