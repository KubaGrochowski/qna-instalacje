"""Zamienia treść strony na pola edytowalne w panelu WordPressa.

Każdy tekst/zdjęcie, które Kuba może zmienić, dostaje klucz. Domyślna wartość = to, co jest teraz
w strona/index.html. Wynik: (body z wywołaniami PHP, lista pól do fields.json).
"""
import re

FIELDS = []
TEAM = []
_keys = set()

SECTIONS = [
    ('kontakt', 'Kontakt i dane firmy'),
    ('start', 'Strona główna: góra strony'),
    ('uslugi', 'Usługi'),
    ('onas', 'O nas'),
    ('zespol', 'Zespół'),
    ('marki', 'Marki'),
    ('obszar', 'Obszar działania'),
    ('podstrony', 'Oferta i Kontakt: nagłówki'),
    ('stopka', 'Stopka'),
]


def reg(key, section, label, typ, default, help=''):
    if key in _keys:
        return
    _keys.add(key)
    FIELDS.append({'key': key, 'section': section, 'label': label, 'type': typ,
                   'default': default, 'help': help})


def T(key):  # tekst w HTML
    return "<?php qna_e('%s'); ?>" % key


def A(key):  # tekst w atrybucie
    return "<?php qna_a('%s'); ?>" % key


def H(key):  # tekst z prostym formatowaniem
    return "<?php qna_h('%s'); ?>" % key


def P(key):  # akapit: tekst + nowe linie
    return "<?php qna_p('%s'); ?>" % key


def IMG(key):
    return "<?php echo esc_url( qna_img('%s') ); ?>" % key


def one(body, pattern, repl, flags=re.S, count=1, start=0):
    """Zamienia `count` dopasowań (0 = wszystkie) od pozycji `start`; wymaga co najmniej jednego."""
    head, tail = body[:start], body[start:]
    new, n = re.subn(pattern, repl, tail, count=count, flags=flags)
    assert n >= 1, 'brak dopasowania: ' + pattern[:80]
    return head + new


def pos(body, marker):
    i = body.find(marker)
    assert i >= 0, 'brak znacznika: ' + marker
    return i


def section_head(body, marker, prefix, section, label, with_text=True):
    """Nagłówek sekcji: etykieta, tytuł, opis."""
    i = pos(body, marker)
    m = re.compile(r'<span class="eyebrow">(.*?)</span>(\s*)<h2>(.*?)</h2>((\s*)<p>(.*?)</p>)?', re.S).search(body, i)
    assert m, marker
    reg(prefix + '.eyebrow', section, label + ': mała etykieta', 'text', m.group(1))
    reg(prefix + '.title', section, label + ': tytuł', 'text', m.group(3))
    out = '<span class="eyebrow">%s</span>%s<h2>%s</h2>' % (T(prefix + '.eyebrow'), m.group(2), T(prefix + '.title'))
    if m.group(4) and with_text:
        reg(prefix + '.text', section, label + ': opis', 'textarea', m.group(6))
        out += '%s<p>%s</p>' % (m.group(5), P(prefix + '.text'))
    elif m.group(4):
        out += m.group(4)
    return body[:m.start()] + out + body[m.end():]


def list_loop(keyprefix, n, item_html):
    """Pętla po polach keyprefix1..n, puste pomijane. item_html zawiera {v} (już wyescapowany)."""
    return ("<?php foreach ( qna_list( '%s', %d ) as $qna_item ) : ?>" % (keyprefix, n)
            + item_html + '<?php endforeach; ?>')


SERVICES = [
    ('elektryka', 'elektryka', 'Elektryka'),
    ('klimatyzacja', 'klimatyzacja', 'Klimatyzacja'),
    ('pompy-ciepla', 'pompy', 'Hydraulika i pompy ciepła'),
    ('fotowoltaika', 'fotowoltaika', 'Fotowoltaika'),
    ('serwis', 'serwis', 'Serwis pomp ciepła'),
    ('mycie-paneli', 'mycie', 'Mycie paneli'),
]
LIST_SLOTS = 6


def transform(body):
    # ---------- Kontakt i firma ----------
    reg('firma.nazwa', 'kontakt', 'Pełna nazwa firmy', 'text', 'QNA Instalacje Jakub Maciej Kuna',
        help='W stopce, w danych firmy, w „O nas” i w zgodzie RODO w formularzu.')
    reg('kontakt.telefon', 'kontakt', 'Telefon', 'text', '792 683 024', help='W headerze i w Kontakcie.')
    reg('kontakt.email', 'kontakt', 'E-mail (widoczny na stronie)', 'email', 'biuro@qna24.pl')
    reg('kontakt.formularz_email', 'kontakt', 'E-mail, na który trafiają zapytania z formularza', 'email', 'biuro@qna24.pl')
    reg('kontakt.adres1', 'kontakt', 'Adres: ulica', 'text', 'ul. Na Skarpę 3')
    reg('kontakt.adres2', 'kontakt', 'Adres: kod i miejscowość', 'text', '86-260 Unisław')
    reg('kontakt.obszar', 'kontakt', 'Obszar działania (każdy w nowej linii)', 'textarea',
        'woj. kujawsko-pomorskie\nwoj. śląskie\nNiemcy – Erlangen')
    reg('kontakt.facebook_url', 'kontakt', 'Facebook: adres strony', 'url', 'https://www.facebook.com/p/QNA-Ochrona-100065062626005/')
    reg('kontakt.facebook_nazwa', 'kontakt', 'Facebook: nazwa wyświetlana', 'text', 'QNA Ochrona')
    reg('firma.nip', 'kontakt', 'NIP', 'text', '6412424853')
    reg('firma.regon', 'kontakt', 'REGON', 'text', '340824404')

    body = body.replace('href="tel:+48792683024"', 'href="<?php echo esc_attr( qna_tel() ); ?>"')
    body = body.replace('aria-label="Zadzwoń: 792 683 024"', 'aria-label="Zadzwoń: %s"' % A('kontakt.telefon'))
    body = body.replace('<b>792 683 024</b>', '<b>%s</b>' % T('kontakt.telefon'))
    body = body.replace('>792 683 024</a>', '>%s</a>' % T('kontakt.telefon'))
    body = one(body, r'<a href="mailto:biuro@qna24\.pl">biuro@qna24\.pl</a>',
               '<a href="mailto:%s">%s</a>' % (A('kontakt.email'), T('kontakt.email')))
    body = one(body, r'<span class="val">ul\. Na Skarpę 3<br>86-260 Unisław</span><br><a href="[^"]*"',
               '<span class="val">%s<br>%s</span><br><a href="<?php echo esc_url( qna_maps_url() ); ?>"'
               % (T('kontakt.adres1'), T('kontakt.adres2')))
    body = one(body, r'(<small>Obszar działania</small><span class="val">).*?(</span>)',
               r'\1' + "<?php qna_lines('kontakt.obszar'); ?>" + r'\2')
    body = one(body, r'<a href="https://www\.facebook\.com/[^"]*" target="_blank" rel="noopener">QNA Ochrona</a>',
               '<a href="%s" target="_blank" rel="noopener">%s</a>'
               % ("<?php echo esc_url( qna_get('kontakt.facebook_url') ); ?>", T('kontakt.facebook_nazwa')))
    body = body.replace('NIP <b>6412424853</b> · REGON <b>340824404</b>',
                        'NIP <b>%s</b> · REGON <b>%s</b>' % (T('firma.nip'), T('firma.regon')))
    body = body.replace('QNA Instalacje Jakub Maciej Kuna', T('firma.nazwa'))

    # ---------- Strona główna: góra ----------
    reg('start.zdjecie', 'start', 'Zdjęcie w tle', 'image', 'hero-home-bw.webp',
        help='Najlepiej poziome, min. 1920 px szerokości. Na stronie jest przyciemnione.')
    reg('start.tytul1', 'start', 'Tytuł: pierwsza linia', 'text', 'Instalacje, które')
    reg('start.tytul2', 'start', 'Tytuł: druga linia (czerwona)', 'text', 'działają')
    reg('start.opis', 'start', 'Opis pod tytułem', 'textarea', None)
    reg('start.rok', 'start', 'Rok założenia (linia „na rynku od…”)', 'number', '2010')
    reg('start.pasek', 'start', 'Przewijany pasek: hasła (każde w nowej linii)', 'textarea', None)

    body = one(body, r"(<section class=\"hero hero-home\">\s*<div class=\"hero-bg\" style=\"background-image:url\(')img/hero-home-bw\.webp('\))",
               r'\1' + IMG('start.zdjecie') + r'\2')
    body = one(body, r'(<h1 class="rise d1 split">)Instalacje, które<br><em>działają</em>(</h1>)',
               r'\1' + T('start.tytul1') + '<br><em>' + T('start.tytul2') + r'</em>\2')
    m = re.search(r'(<section class="hero hero-home">.*?<p class="hero-lead rise d2">)(.*?)(</p>)', body, re.S)
    FIELDS[-3]['default'] = m.group(2)  # start.opis
    body = body[:m.start(2)] + P('start.opis') + body[m.end(2):]
    body = one(body, r'aria-label="Na rynku od 2010 roku"><span>na rynku od</span><b data-count="2010" data-from="1990">2010</b>',
               'aria-label="Na rynku od %s roku"><span>na rynku od</span><b data-count="%s" data-from="1990">%s</b>'
               % (A('start.rok'), A('start.rok'), T('start.rok')))
    m = re.search(r'<div class="ticker-track">\s*(.*?)\n', body, re.S)
    items = re.findall(r'<span>(.*?)</span>', m.group(1))
    FIELDS[-1]['default'] = '\n'.join(items)  # start.pasek
    body = one(body, r'(<div class="ticker-track">)\s*.*?(\s*</div>)',
               r'\1' + "<?php qna_ticker(); ?>" + r'\2')

    # ---------- Usługi ----------
    body = section_head(body, '<section class="section mist" id="uslugi-start">', 'uslugi', 'uslugi', 'Nagłówek sekcji usług')
    body = section_head(body, '<section class="section mist" id="uslugi">', 'uslugi', 'uslugi', 'Nagłówek sekcji usług')
    m = re.search(r'(<h1 class="rise d1 split">)Zakres <em>usług</em>(</h1>\s*<p class="hero-lead rise d2">)(.*?)(</p>)', body, re.S)
    reg('oferta.tytul1', 'podstrony', 'Oferta: tytuł (biała część)', 'text', 'Zakres')
    reg('oferta.tytul2', 'podstrony', 'Oferta: tytuł (czerwona część)', 'text', 'usług')
    reg('oferta.opis', 'podstrony', 'Oferta: opis pod tytułem', 'textarea', m.group(3))
    body = body[:m.start()] + m.group(1) + T('oferta.tytul1') + ' <em>' + T('oferta.tytul2') + '</em>' + m.group(2) + P('oferta.opis') + m.group(4) + body[m.end():]

    for sid, formval, short in SERVICES:
        k = 'usluga.' + formval
        lab = 'Usługa „%s”' % short
        tm = re.search(r'<a class="tile" href="#oferta/%s">\s*<svg[^>]*>.*?</svg>\s*<h3>(.*?)</h3>\s*<p>(.*?)</p>' % re.escape(sid), body, re.S)
        reg(k + '.tytul', 'uslugi', lab + ': nazwa', 'text', tm.group(1), help='Na kafelku, w oknie usługi i na liście w formularzu.')
        reg(k + '.kafelek', 'uslugi', lab + ': krótki opis na kafelku', 'textarea', tm.group(2))
        body = re.sub(r'(<a class="tile" href="#oferta/%s">\s*<svg[^>]*>.*?</svg>\s*<h3>).*?(</h3>\s*<p>).*?(</p>)' % re.escape(sid),
                      lambda mm: mm.group(1) + T(k + '.tytul') + mm.group(2) + P(k + '.kafelek') + mm.group(3), body, flags=re.S)
        reg(k + '.ilosc', 'uslugi', lab + ': liczba wykonanych realizacji', 'text', 'x',
            help='Pokazuje się na kafelku i w oknie usługi, np. 120 albo 100+.')
        body, nn = re.subn(r'<b class="ilosc" data-svc="%s">x</b>' % re.escape(sid), '<b class="ilosc">%s</b>' % T(k + '.ilosc'), body)
        assert nn == 3, (sid, nn)
        a0 = pos(body, '<article class="svc" id="%s">' % sid)
        a1 = body.index('</article>', a0)
        art = body[a0:a1]
        img = re.search(r'data-src="img/([\w.-]+)"', art).group(1)
        reg(k + '.zdjecie', 'uslugi', lab + ': zdjęcie w oknie usługi', 'image', img)
        art = art.replace('data-src="img/%s"' % img, 'data-src="%s"' % IMG(k + '.zdjecie'), 1)
        em = re.search(r'<span class="eyebrow">(.*?)</span>\s*<h2>(.*?)</h2>(\s*<p class="svc-count">.*?</p>)?\s*<p>(.*?)</p>', art, re.S)
        reg(k + '.etykieta', 'uslugi', lab + ': mała etykieta w oknie', 'text', em.group(1))
        reg(k + '.opis', 'uslugi', lab + ': opis w oknie', 'textarea', em.group(4))
        art = art[:em.start()] + '<span class="eyebrow">%s</span>\n          <h2>%s</h2>%s\n          <p>%s</p>' % (
            T(k + '.etykieta'), T(k + '.tytul'), em.group(3) or '', P(k + '.opis')) + art[em.end():]
        lis = re.findall(r'<li><svg aria-hidden="true"><use href="#i-check"/></svg><span>(.*?)</span></li>', art)
        for i in range(LIST_SLOTS):
            reg('%s.punkt%d' % (k, i + 1), 'uslugi', '%s: punkt %d listy' % (lab, i + 1), 'text', lis[i] if i < len(lis) else '',
                help='Puste pole = punkt się nie pokazuje.' if i == 0 else '')
        art = re.sub(r'(<ul class="checks">).*?(\s*</ul>)',
                     lambda mm: mm.group(1) + list_loop(k + '.punkt', LIST_SLOTS,
                         '<li><svg aria-hidden="true"><use href="#i-check"/></svg><span><?php echo esc_html( $qna_item ); ?></span></li>') + mm.group(2),
                     art, count=1, flags=re.S)
        body = body[:a0] + art + body[a1:]
        fm = re.search(r'<li><a href="#oferta/%s">(.*?)</a></li>' % re.escape(sid), body)
        reg(k + '.stopka', 'uslugi', lab + ': nazwa w stopce', 'text', fm.group(1))
        body = body[:fm.start(1)] + T(k + '.stopka') + body[fm.end(1):]
        body = one(body, r'(<option value="%s">).*?(</option>)' % formval, r'\1' + T(k + '.tytul') + r'\2')

    # ---------- O nas ----------
    body = section_head(body, '<div class="about-body">', 'onas', 'onas', 'O nas', with_text=False)
    reg('onas.zdjecie', 'onas', 'Zdjęcie', 'image', 'zespol-lutowanie.webp')
    body = one(body, r'(<div class="about-media">\s*<div class="media-clip"><img [^>]*?src=")img/zespol-lutowanie\.webp(")',
               r'\1' + IMG('onas.zdjecie') + r'\2')
    m = re.search(r'(<div class="about-body">.*?<h2>.*?</h2>\s*<p>)(.*?)(</p>)', body, re.S)
    reg('onas.opis', 'onas', 'Opis firmy', 'html', m.group(2).replace(T('firma.nazwa'), 'QNA Instalacje Jakub Maciej Kuna'),
        help='Można pogrubić fragment: <strong>tekst</strong>.')
    body = body[:m.start(2)] + H('onas.opis') + body[m.end(2):]
    m = re.search(r'(<div class="about-body">.*?<ul class="checks">)(.*?)(\s*</ul>)', body, re.S)
    lis = re.findall(r'<span>(.*?)</span></li>', m.group(2))
    for i in range(LIST_SLOTS):
        reg('onas.atut%d' % (i + 1), 'onas', 'Atut %d' % (i + 1), 'html', lis[i] if i < len(lis) else '',
            help='Pogrubienie: <b>tekst</b>. Puste pole = atut się nie pokazuje.' if i == 0 else '')
    body = body[:m.start(2)] + list_loop('onas.atut', LIST_SLOTS,
        '<li><svg aria-hidden="true"><use href="#i-check"/></svg><span><?php echo qna_kses( $qna_item ); ?></span></li>') + body[m.end(2):]
    reg('onas.lata', 'onas', 'Karta na zdjęciu: liczba lat', 'number', '15')
    reg('onas.lata_opis', 'onas', 'Karta na zdjęciu: podpis', 'text', 'lat doświadczenia')
    body = one(body, r'<span data-count="15" data-from="0">15</span>(<i>\+</i></b><span class="lbl">)lat<br>doświadczenia',
               '<span data-count="%s" data-from="0">%s</span>\\1%s' % (A('onas.lata'), T('onas.lata'), T('onas.lata_opis')))

    # ---------- Zespół ----------
    # Osoby pochodzą z wtyczki „QNA Zespół”. Tu zbieramy domyślne dane (do pierwszego uruchomienia wtyczki).
    body = section_head(body, '<section class="section mist" id="zespol">', 'zespol', 'zespol', 'Nagłówek sekcji')
    for tid, photo in (('p-kuba', 'o-nas-kuba.webp'), ('p-aga', '')):
        t0 = pos(body, '<template id="%s">' % tid)
        t1 = body.index('</template>', t0) + len('</template>')
        tpl = body[t0:t1]
        g = lambda pat: re.search(pat, tpl, re.S).group(1)
        TEAM.append({
            'imie': g(r'<div data-name>(.*?)</div>'),
            'rola': g(r'<div data-role>(.*?)</div>'),
            'opis': g(r'<div data-desc>(.*?)</div>').replace(T('firma.nazwa'), 'QNA Instalacje Jakub Maciej Kuna'),
            'info': [list(x) for x in re.findall(r'<div data-spec="(.*?)">(.*?)</div>', tpl)],
            'zdjecie': photo,
        })
        body = body[:t0] + body[t1:]
    body = one(body, r'(<div class="team">).*?(\n      </div>\n    </div>\n  </section>)', r'\1' + "<?php qna_team(); ?>" + r'\2')

    # ---------- Kolaże: Oferta i Kontakt ----------
    for page, prefix, label in (('oferta', 'oferta', 'Oferta'), ('kontakt', 'kontakt_strona', 'Kontakt')):
        p0 = pos(body, '<main data-page="%s">' % page)
        c0 = body.index('<div class="collage', p0)
        c1 = body.index('</div>', c0)
        block = body[c0:c1]
        for i, img in enumerate(re.findall(r'data-src="img/([\w.-]+)"', block), 1):
            key = '%s.kolaz%d' % (prefix, i)
            reg(key, 'podstrony', '%s: zdjęcie %d w kolażu u góry' % (label, i), 'image', img)
            block = block.replace('data-src="img/%s"' % img, 'data-src="%s"' % IMG(key), 1)
        body = body[:c0] + block + body[c1:]

    # ---------- Marki ----------
    body = section_head(body, '<section class="section brands" id="marki">', 'marki', 'marki', 'Nagłówek sekcji')
    m = re.search(r'(<ul class="brand-grid">)(.*?)(\s*</ul>)', body, re.S)
    cells = re.findall(r'<b>(.*?)</b><small>(.*?)</small>', m.group(2))
    for i in range(8):
        n, d = cells[i] if i < len(cells) else ('', '')
        reg('marki.marka%d' % (i + 1), 'marki', 'Marka %d: nazwa' % (i + 1), 'text', n,
            help='Puste pole = marka się nie pokazuje.' if i == 0 else '')
        reg('marki.marka%d_opis' % (i + 1), 'marki', 'Marka %d: co montujemy' % (i + 1), 'text', d)
    body = body[:m.start(2)] + "<?php qna_brands( 8 ); ?>" + body[m.end(2):]

    # ---------- Obszar działania ----------
    body = section_head(body, '<section class="area" id="obszar">', 'obszar', 'obszar', 'Nagłówek sekcji')
    for i, region in enumerate(('kp', 'sl', 'de'), 1):
        m = re.search(r'(<li class="area-item" data-region="%s" tabindex="0"><span class="dot"></span><span><b>)(.*?)(</b><small>)(.*?)(</small>)' % region, body)
        reg('obszar.teren%d' % i, 'obszar', 'Teren %d: nazwa' % i, 'text', m.group(2),
            help='Mapa jest stała (kujawsko-pomorskie, śląskie, Erlangen). Zmiana terenu na mapie wymaga poprawki w motywie.' if i == 1 else '')
        reg('obszar.teren%d_opis' % i, 'obszar', 'Teren %d: podpis' % i, 'text', m.group(4))
        body = body[:m.start(2)] + T('obszar.teren%d' % i) + m.group(3) + T('obszar.teren%d_opis' % i) + body[m.end(4):]

    # ---------- Podstrony ----------
    m = re.search(r'(<h2>)(Opisz inwestycję, a my ją wycenimy)(</h2>)', body)
    reg('oferta.cta', 'podstrony', 'Oferta: tytuł czarnego bloku na dole', 'text', m.group(2))
    body = body[:m.start(2)] + T('oferta.cta') + body[m.end(2):]
    m = re.search(r'(<h1 class="rise d1 split">)Porozmawiajmy o Twojej <em>instalacji</em>(</h1>\s*<p class="hero-lead rise d2">)(.*?)(</p>)', body, re.S)
    reg('kontakt_strona.tytul1', 'podstrony', 'Kontakt: tytuł (biała część)', 'text', 'Porozmawiajmy o Twojej')
    reg('kontakt_strona.tytul2', 'podstrony', 'Kontakt: tytuł (czerwona część)', 'text', 'instalacji')
    reg('kontakt_strona.opis', 'podstrony', 'Kontakt: opis pod tytułem', 'textarea', m.group(3))
    body = body[:m.start()] + m.group(1) + T('kontakt_strona.tytul1') + ' <em>' + T('kontakt_strona.tytul2') + '</em>' + m.group(2) + P('kontakt_strona.opis') + m.group(4) + body[m.end():]

    # ---------- Stopka ----------
    m = re.search(r'(<a class="foot-logo"[^>]*>.*?</a>\s*<p>)(.*?)(</p>)', body, re.S)
    reg('stopka.opis', 'stopka', 'Opis pod logo', 'textarea', m.group(2))
    body = body[:m.start(2)] + P('stopka.opis') + body[m.end(2):]

    assert all(f['default'] is not None for f in FIELDS), [f['key'] for f in FIELDS if f['default'] is None]
    return body, FIELDS, SECTIONS, TEAM
