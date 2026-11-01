"""Buduje motyw WordPress z strona/index.html.

Uruchom:  python wordpress/build-theme.py
Wynik:    wordpress/qna-instalacje/        (gotowy folder motywu)
          wordpress/qna-instalacje.zip     (do wgrania: Wygląd → Motywy → Dodaj → Wyślij motyw na serwer)
Opcja:    --install C:/xampp/htdocs/qna    kopiuje motyw do lokalnego WordPressa
"""
import os, re, shutil, sys, zipfile

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
SRC = os.path.join(ROOT, 'strona', 'index.html')
THEME_SRC = os.path.join(ROOT, 'wordpress', 'theme-src')
OUT = os.path.join(ROOT, 'wordpress', 'qna-instalacje')

html = open(SRC, encoding='utf-8').read()

css = re.search(r'<style>(.*?)</style>', html, re.S).group(1)
js = re.search(r'<script>(.*?)</script>', html, re.S).group(1)

body = html
body = re.sub(r'<title>.*?</title>\s*', '', body, flags=re.S)
body = re.sub(r'<link [^>]*>\s*', '', body)
body = re.sub(r'<style>.*?</style>\s*', '', body, count=1, flags=re.S)
body = re.sub(r'<script[^>]*>.*?</script>\s*', '', body, flags=re.S)
body = re.sub(r'<script [^>]*></script>\s*', '', body)
# image paths -> theme folder
body = re.sub(r'(["\'(])img/', r'\1<?php echo esc_url( QNA_IMG ); ?>', body)
assert 'img/' not in re.sub(r'QNA_IMG \); \?>[\w.-]+', '', body), 'nieprzepisana ścieżka img/'

index_php = '''<?php
/**
 * Strona QNA Instalacje. Plik generowany przez wordpress/build-theme.py z strona/index.html.
 * Nie edytuj ręcznie: zmiany wprowadzaj w strona/index.html i zbuduj motyw ponownie.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
''' + body.strip() + '''

<?php wp_footer(); ?>
</body>
</html>
'''

if os.path.isdir(OUT):
    shutil.rmtree(OUT)
os.makedirs(os.path.join(OUT, 'assets'))
os.makedirs(os.path.join(OUT, 'img'))
for f in os.listdir(THEME_SRC):
    shutil.copy(os.path.join(THEME_SRC, f), OUT)
open(os.path.join(OUT, 'index.php'), 'w', encoding='utf-8', newline='\n').write(index_php)
open(os.path.join(OUT, 'assets', 'site.css'), 'w', encoding='utf-8', newline='\n').write(css.strip() + '\n')
open(os.path.join(OUT, 'assets', 'site.js'), 'w', encoding='utf-8', newline='\n').write(js.strip() + '\n')

used = set(re.findall(r'QNA_IMG \); \?>([\w.-]+)', index_php)) | {'favicon.png', 'hero-home-bw.webp'}
for name in sorted(used):
    shutil.copy(os.path.join(ROOT, 'strona', 'img', name), os.path.join(OUT, 'img', name))

# screenshot for the theme list
try:
    from PIL import Image
    im = Image.open(os.path.join(ROOT, 'strona', 'img', 'hero-home-bw.webp')).convert('RGB')
    w, h = im.size; th = int(w * 0.75)
    im.crop((0, max(0, (h - th) // 2), w, max(0, (h - th) // 2) + min(th, h))).resize((1200, 900)).save(os.path.join(OUT, 'screenshot.png'))
except Exception as e:
    print('screenshot pominięty:', e)

zpath = OUT + '.zip'
with zipfile.ZipFile(zpath, 'w', zipfile.ZIP_DEFLATED) as z:
    for dp, _, files in os.walk(OUT):
        for f in files:
            full = os.path.join(dp, f)
            z.write(full, os.path.join('qna-instalacje', os.path.relpath(full, OUT)))

print('motyw:', OUT)
print('zip:', zpath, '(%d KB)' % (os.path.getsize(zpath) // 1024))
print('obrazy:', len(used))

if '--install' in sys.argv:
    wp = sys.argv[sys.argv.index('--install') + 1]
    dest = os.path.join(wp, 'wp-content', 'themes', 'qna-instalacje')
    if os.path.isdir(dest):
        shutil.rmtree(dest)
    shutil.copytree(OUT, dest)
    print('zainstalowano w:', dest)
