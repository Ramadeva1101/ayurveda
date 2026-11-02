"""Build the cPanel upload ZIPs from dist/ and backend/.

Run from the project root after `npm run build`:
    python deploy/make-package.py
"""
import zipfile
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
OUT = ROOT / 'deploy'


def add_tree(z, src, prefix='', skip=lambda rel: False):
    count = 0
    for path in sorted((ROOT / src).rglob('*')):
        if not path.is_file():
            continue
        rel = path.relative_to(ROOT / src).as_posix()
        if skip(rel):
            continue
        z.write(path, prefix + rel)
        count += 1
    return count


with zipfile.ZipFile(OUT / 'public_html.zip', 'w', zipfile.ZIP_DEFLATED) as z:
    # images/newimg holds the original gallery uploads; the site uses the
    # optimised copies from src/assets, so the originals are left out.
    site = add_tree(z, 'dist', skip=lambda rel: rel.startswith('images/newimg/'))
    php = add_tree(z, 'backend/public')
print(f'public_html.zip: {site} site files + {php} api/admin files')

with zipfile.ZipFile(OUT / 'awh-app.zip', 'w', zipfile.ZIP_DEFLATED) as z:
    # Shipped as awh-app/ so it never clashes with another site's app/ folder.
    # config.php holds secrets and differs per server: never shipped.
    app = add_tree(z, 'backend/app', 'awh-app/', skip=lambda rel: rel in ('config.php', '.awh-backend'))
print(f'awh-app.zip: {app} files')
