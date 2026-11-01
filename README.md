# Strona QNA Instalacje

Strona-wizytówka firmy instalacyjnej QNA Instalacje (Unisław): strona główna, oferta, kontakt.

- `strona/index.html`: cała strona (fragment HTML bez `<head>`, tak jak wymaga podgląd w artefakcie Claude).
- `strona/img/`: zdjęcia i logo.
- `.github/workflows/pages.yml`: po każdym pushu na `main` buduje pełny dokument HTML i publikuje go na GitHub Pages.

Podgląd ma `noindex`, więc nie trafi do wyszukiwarek.

## WordPress
- `wordpress/build-theme.py`: z `strona/index.html` buduje motyw WordPress `qna-instalacje` (folder + zip).
- `wordpress/theme-src/`: pliki motywu pisane ręcznie (`functions.php` z obsługą formularza, `style.css`).
- `wordpress/MIGRACJA.md`: jak przenieść stronę na serwer klienta po otrzymaniu FTP i bazy.
