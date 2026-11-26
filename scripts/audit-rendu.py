#!/usr/bin/env python3
"""
Contrôle du HTML réellement servi, page par page : code HTTP, title, meta description,
robots, H1, mot-clé dans le H1 et les H2/H3, images sans alt (hors SVG), nombre de mots du <main>.

Usage :
  wp eval-file audit-metas.php map --allow-root > /tmp/seo/map.json
  python3 -I audit-rendu.py https://domaine.tld /tmp/seo/map.json [--local]

  map.json : {"/": "portail automatique", "/portes-de-garage/": "porte de garage", ...}
  --local  : interroge le serveur lui-même (127.0.0.1) au lieu du DNS public (préprod, NAT en épingle)
"""
import json, re, subprocess, sys, html as H
from urllib.parse import urlparse

if len(sys.argv) < 3:
    sys.exit(__doc__)
base, mapfile, local = sys.argv[1].rstrip('/'), sys.argv[2], '--local' in sys.argv
host = urlparse(base).hostname
port = 443 if base.startswith('https') else 80
kwmap = json.load(open(mapfile, encoding='utf-8'))

def fetch(url):
    cmd = ['curl', '-sk', '-L', '--max-time', '30', '-w', '\n%{http_code}', url]
    if local:
        cmd[1:1] = ['--resolve', f'{host}:{port}:127.0.0.1']
    out = subprocess.run(cmd, capture_output=True, text=True).stdout
    body, _, code = out.rpartition('\n')
    return code, body

def text(s):
    return re.sub(r'\s+', ' ', H.unescape(re.sub(r'<[^>]+>', ' ', s))).replace('\xa0', ' ').strip()

def has(kw, s):
    return re.search(r'(?<![\w])' + re.escape(kw) + r'(?![\w])', s.lower()) is not None

for path, kw in kwmap.items():
    kw = kw.lower()
    code, h = fetch(base + path)
    title = text((re.search(r'<title>(.*?)</title>', h, re.S) or [None, ''])[1])
    desc = re.search(r'<meta name="description" content="([^"]*)"', h)
    desc = H.unescape(desc.group(1)) if desc else ''
    robots = re.search(r'<meta name="robots" content="([^"]*)"', h)
    main = re.search(r'<main.*?</main>', h, re.S)
    # Contenu propre à la page : <main>, sinon la page sans en-tête, pied de page, nav, scripts
    body = main.group(0) if main else re.sub(r'<(header|footer|nav|script|style|noscript)\b.*?</\1>', ' ', h, flags=re.S)
    h1 = [text(x) for x in re.findall(r'<h1[^>]*>(.*?)</h1>', h, re.S)]
    hn = [text(x) for x in re.findall(r'<h[23][^>]*>(.*?)</h[23]>', body, re.S)]
    def src(i):
        m = re.search(r'src="([^"]+)"', i)
        return m.group(1) if m else i[:100]
    imgs = [i for i in re.findall(r'<img [^>]*>', h) if not re.search(r'src="[^"]+\.svg', i)]
    no_attr = sorted({src(i) for i in imgs if not re.search(r'\salt=', i)})       # erreur : pas d'attribut alt
    empty = sorted({src(i) for i in imgs if re.search(r'\salt=""', i)})           # alt vide : OK si décoratif
    words = len(re.findall(r"[\w’'-]+", text(re.sub(r'<(script|style)[^>]*>.*?</\1>', ' ', body, flags=re.S))))

    warn = []
    if code != '200': warn.append(f'HTTP {code}')
    if len(h1) != 1: warn.append(f'{len(h1)} H1')
    elif not has(kw, h1[0]): warn.append('KW absent du H1')
    if not any(has(kw, x) for x in hn): warn.append('aucun H2/H3 avec le KW')
    if no_attr: warn.append(f'{len(no_attr)} image(s) sans attribut alt')
    if len(title) > 60: warn.append(f'title {len(title)} car.')
    if len(desc) > 160: warn.append(f'desc {len(desc)} car.')
    if words < 300: warn.append(f'{words} mots de contenu')
    print(f"{path:40} kw={kw:32} H1=« {h1[0][:60] if h1 else '-'} » robots={robots.group(1) if robots else '-'}")
    print('    ' + ('⚠ ' + ' | '.join(warn) if warn else 'OK'))
    for u in no_attr[:5]:
        print('      sans attribut alt :', u)
    for u in empty:
        print('      alt vide (OK si décoratif) :', u.rsplit('/', 1)[-1])
