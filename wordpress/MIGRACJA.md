# Przeniesienie strony QNA Instalacje na serwer Kuby (WordPress)

Wygląd, zdjęcia domyślne i formularz siedzą w motywie **`qna-instalacje`**. Teksty, dane kontaktowe i zdjęcia Kuba zmienia w panelu: **Treść strony** (menu po lewej). Zmiany z panelu zapisują się w bazie (opcja `qna_content`); dopóki nikt nic nie zmienił, strona pokazuje treść domyślną z motywu.

Najprościej: czysty WordPress u Kuby + wgranie motywu. Jeśli wcześniej wpiszemy treści lokalnie w panelu, użyj wariantu B albo przepisz je w panelu na serwerze.

## Czego potrzebujemy od Kuby
1. **Domena** (np. qna24.pl) podpięta do hostingu.
2. **FTP / SFTP:** host, login, hasło (albo dostęp do panelu hostingu).
3. **Baza MySQL:** nazwa bazy, użytkownik, hasło, host (często `localhost`).
4. **Skrzynka do wysyłki formularza:** np. biuro@qna24.pl, z hasłem lub danymi SMTP. Bez tego maile z formularza mogą nie dochodzić.

Danych dostępowych nie zapisujemy w repozytorium ani w Obsidianie.

## Krok 0: zbuduj aktualny motyw
```
python wordpress/build-theme.py
```
Powstaje `wordpress/qna-instalacje.zip` (oraz folder `wordpress/qna-instalacje/`).

## Wariant A (polecany): czysty WordPress + motyw
1. **Zainstaluj WordPress u Kuby.**
   - Jeśli hosting ma instalator jednym kliknięciem (np. w panelu: „Instalator aplikacji” / „WordPress”), użyj go i podaj dane bazy.
   - Ręcznie: pobierz WordPress z wordpress.org/pl, wgraj pliki przez FTP do katalogu domeny, wejdź na domenę i przejdź kreator, podając dane bazy od Kuby.
2. **Wgraj motyw:**
   - w panelu WordPressa: *Wygląd → Motywy → Dodaj nowy → Wyślij motyw na serwer* → `qna-instalacje.zip` → *Włącz*,
   - albo przez FTP: folder `qna-instalacje` do `wp-content/themes/`, potem *Wygląd → Motywy → Włącz*.
   **Wgraj i włącz wtyczki** z `wordpress/plugins/`: `qna-zespol.zip`, `qna-zdjecia.zip`, `qna-realizacje.zip`, `qna-formularz.zip` i `qna-opinie.zip` (*Wtyczki → Dodaj nową → Wyślij wtyczkę*), po motywie. „QNA Zespół” i „QNA Realizacje” przy pierwszym włączeniu same dodadzą osoby i realizacje domyślne (ze zdjęciami z motywu).
3. **Język polski:** instaluj polską wersję WordPressa (wordpress.org/pl) albo *Ustawienia → Ogólne → Język witryny: Polski*.
   **Ustawienia → Ogólne:** tytuł „QNA Instalacje”, strefa czasowa Warszawa.
   **Treść strony → Kontakt i dane firmy:** sprawdź telefon, e-mail i „E-mail, na który trafiają zapytania z formularza”.
4. **Ustawienia → Czytanie:** odznacz „Proś wyszukiwarki o nieindeksowanie witryny”.
5. **Poczta formularza:** *Zapytania → Poczta (SMTP)*: serwer, port, szyfrowanie, login i hasło skrzynki (np. biuro@qna24.pl, dane z hostingu) → „Zapisz i wyślij e-mail testowy”. Potem wyślij próbne zapytanie z formularza: pojawi się w *Zapytania* ze statusem „wysłano”.
   **Opinie Google:** *Opinie Google*: wklej klucz API (Google Cloud → włącz „Places API (New)”, utwórz klucz i ogranicz go do tego API; potrzebne konto rozliczeniowe Google) → „Zapisz i pobierz opinie”. Wizytówka: „Qna Instalacje”, Na Skarpę 3, Unisław (5,0, 29 opinii na 2026-11-03).
6. **HTTPS:** włącz certyfikat SSL w panelu hostingu i ustaw adres strony na `https://…` (*Ustawienia → Ogólne*).
7. **Polityka prywatności:** link w stopce jest jeszcze pusty. Trzeba dodać treść (od Kuby lub prawnika).

## Wariant B: przeniesienie całej lokalnej instalacji
Przydaje się, jeśli lokalnie dodamy treści w panelu WordPressa (np. wpisy, wtyczki).
1. Eksport bazy `qna_instalacje`: phpMyAdmin (http://localhost/phpmyadmin → baza → Eksport) albo:
   ```
   C:\xampp\mysql\bin\mysqldump.exe -u root qna_instalacje > qna.sql
   ```
2. Wgraj przez FTP zawartość `C:\xampp\htdocs\qna` **bez** `wp-config.php`.
3. Zaimportuj `qna.sql` do bazy Kuby (phpMyAdmin na hostingu).
4. Na serwerze utwórz `wp-config.php` z danymi bazy Kuby (wzór: `wp-config-sample.php`), nowymi kluczami (https://api.wordpress.org/secret-key/1.1/salt/) i `$table_prefix = 'qna_';`. **Nie** dodawaj `WP_ENVIRONMENT_TYPE 'local'`, bo wtedy formularz zapisuje zapytania do pliku zamiast je wysyłać.
5. Zamień adresy `http://localhost/qna` na adres domeny, np. wtyczką *Better Search Replace*.
6. Dalej jak w wariancie A, punkty 3–7.

## Aktualizacje po publikacji
Zmiany robimy w `strona/index.html` → `python wordpress/build-theme.py` → wgrywamy nowy `qna-instalacje.zip` (Wygląd → Motywy → Dodaj → zastąp istniejący) albo podmieniamy folder przez FTP.

## Lokalna kopia (ten komputer)
- XAMPP: uruchom MySQL i Apache (`C:\xampp\xampp-control.exe`).
- WordPress 7.1.4 (pl_PL). Strona: http://localhost/qna, panel: http://localhost/qna/wp-admin (dane w `wordpress/lokalnie-dane.md`, poza gitem).
- Odświeżenie motywu lokalnie: `python wordpress/build-theme.py --install C:/xampp/htdocs/qna`
- Zapytania z formularza lokalnie: menu Zapytania; bez ustawionego SMTP dodatkowo plik `wp-content/uploads/qna-zapytania.log`.

## Narzędzia w panelu (dla Kuby)
- **Treść strony** (motyw): teksty i dane kontaktowe.
- **Zdjęcia** (wtyczka QNA Zdjęcia): wszystkie zdjęcia, w tym zdjęcia osób z zespołu.
- **Zespół** (wtyczka QNA Zespół): osoby (imię, rola, krótki opis, zdjęcie główne, opcjonalnie doświadczenie/umiejętności/zadania), dodawanie i kolejność.
- **Realizacje** (wtyczka QNA Realizacje): zdjęcia w sekcji Realizacje — rodzaj usługi (filtr na stronie), opcjonalny krótki opis, kolejność. Liczby realizacji przy rodzajach: Treść strony → Usługi.
- **Zapytania** (wtyczka QNA Formularz): każde zapytanie z formularza zapisane w panelu, ze statusem wysyłki e-mail; ustawienia poczty SMTP z e-mailem testowym.
- **Opinie Google** (wtyczka QNA Opinie Google): ocena, liczba i do 5 opinii z Map Google, odświeżane co 12 h; bez klucza API sekcja się nie pokazuje.
- Opis narzędzi jest w kokpicie (widżet „Strona QNA Instalacje: narzędzia”).

## Panel „Treść strony”
- Sekcje: Kontakt i dane firmy, Strona główna, Usługi (6 usług: nazwa, opis kafelka, opis w oknie, do 6 punktów listy, nazwa w stopce), O nas, nagłówek Zespołu, Marki (do 8), Obszar działania, nagłówki Oferty i Kontaktu, Stopka. Zdjęcia i osoby mają własne menu (wyżej).
- Puste pole przy punktach list, atutach i markach = element się nie pokazuje.
- Na dole strony panelu: „Przywróć domyślne” (z potwierdzeniem) cofa wszystkie zmiany.
- Czego nie ma w panelu: mapa (stałe 3 tereny), układ sekcji, kolory, nowe usługi lub sekcje. To zmiany w motywie.
- Nowe pola dodaje się w `wordpress/fields.py` i przebudowuje motyw.
