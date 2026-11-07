# SimplePress

<p align="center">
  <img src="simplepress/img/sp.svg" alt="SimplePress" width="96" height="96">
</p>

<p align="center">
  Motyw administracyjny inspirowany WordPressem, kokpit i bezpieczny zestaw narzędzi dla <strong>GetSimple CMS CE</strong>.
</p>

<p align="center">
  <a href="README.md">English documentation</a>
</p>

---

## Spis treści

- [Opis](#opis)
- [Wymagania](#wymagania)
- [Instalacja](#instalacja)
- [Pierwsze kroki](#pierwsze-kroki)
- [Sekcje panelu](#sekcje-panelu)
- [Wygląd i schematy kolorów](#wygląd-i-schematy-kolorów)
- [Edytor](#edytor)
- [Biblioteka mediów](#biblioteka-mediów)
- [Użytkownicy i role](#użytkownicy-i-role)
- [Snippety](#snippety)
- [Rozszerzenia menu](#rozszerzenia-menu)
- [Ustawienia motywu i API customizera](#ustawienia-motywu-i-api-customizera)
- [Frontowy pasek administracyjny](#frontowy-pasek-administracyjny)
- [Konserwacja, kopie i migracja](#konserwacja-kopie-i-migracja)
- [Instalator wtyczek](#instalator-wtyczek)
- [Bezpieczeństwo](#bezpieczeństwo)
- [Struktura katalogów](#struktura-katalogów)
- [Hooki i filtry](#hooki-i-filtry)
- [Lokalizacja](#lokalizacja)
- [Aktualizacja](#aktualizacja)
- [Rozwiązywanie problemów](#rozwiązywanie-problemów)
- [Changelog](#changelog)
- [Licencja](#licencja)

## Opis

SimplePress zastępuje domyślny panel administracyjny GetSimple CMS nowoczesnym
interfejsem inspirowanym WordPressem. Jest wtyczką samodzielną: nie modyfikuje
plików rdzenia GetSimple i nie ładuje WordPressa, publicznych CDN ani zdalnych
zasobów w czasie działania.

Najważniejsze funkcje:

- górny pasek, zwijany ciemny sidebar, podmenu i menu mobilne w stylu WordPressa,
- schematy Classic, Modern i Dark z możliwością nadpisania przez użytkownika,
- postboxy, zakładki, tabele, formularze, powiadomienia i ekran logowania,
- nowe logo SimplePress „Monogram Seal”,
- kokpit ze statystykami, szybkim szkicem i kondycją serwera,
- CKEditor (rdzeń GetSimple) albo lokalny TinyMCE 6,
- biblioteka mediów z drag & drop, miniaturami i lokalnym Cropper.js,
- użytkownicy, role, preferencje schematu/edytora i ukrywanie sekcji panelu,
- snippety, własne pozycje menu i customizer motywu w stylu WordPressa,
- tryb konserwacji, kreator kopii zapasowych, branding, opcje logowania i frontowy pasek administracyjny,
- bezpieczny instalator/odinstalowywacz wtyczek z katalogiem GetSimple CE,
- edytor gsconfig, migracja domeny, wymuszanie HTTPS i przegląd SQLite3,
- lokalne zasoby Dashicons, TinyMCE, Cropper.js i CodeMirror.

## Wymagania

- GetSimple CMS CE.
- PHP 7.4 – 8.x.
- Rozszerzenie PHP `zip` dla instalatora wtyczek i kreatora kopii.
- Rozszerzenie PHP `sqlite3` dla ustawień, metadanych użytkowników i opcji motywu.
- Prawo zapisu do katalogu `data/other/` w GetSimple.

SimplePress nie wymaga WordPressa i nie łączy się z serwerami WordPressa.

## Instalacja

### Instalacja ręczna

1. Skopiuj `simplepress.php` oraz cały katalog `simplepress/` do katalogu
   `plugins/` w GetSimple:

   ```text
   plugins/
   ├── simplepress.php
   └── simplepress/
   ```

2. Otwórz **Wtyczki** w panelu GetSimple i włącz **SimplePress**.
3. Otwórz **SimplePress → Wygląd** i wybierz schemat kolorów.

### Instalacja przez menedżer wtyczek SimplePress

1. Otwórz **SimplePress → Wtyczki**.
2. Wklej adres repozytorium GitHub albo bezpośredni adres archiwum ZIP, np.:

   ```text
   https://github.com/GetSimpleCMS-CE-plugins/plugin-AdminRenamer
   https://github.com/GetSimpleCMS-CE-plugins/plugin-AdminRenamer/archive/refs/heads/1.1.zip
   ```

3. Kliknij **Zainstaluj**.

Instalator:

- akceptuje `github.com`, `codeload.github.com`, `api.github.com`,
  `raw.githubusercontent.com`, `objects.githubusercontent.com`,
  `github-releases.githubusercontent.com` i `getsimple-ce.ovh` wyłącznie po HTTPS,
- zamienia zwykły adres repozytorium GitHub na archiwum ZIP domyślnej gałęzi,
- wykrywa główny plik pluginu po wywołaniu `register_plugin()` albo nagłówku
  `Plugin Name:`, zamiast polegać na nazwie folderu w archiwum,
- obsługuje archiwa płaskie i pluginy zagnieżdżone o jeden poziom,
- pomija dokumentację i pliki deweloperskie repozytorium podczas kopiowania,
- waliduje wpisy ZIP, symlinki, łączny rozmiar i liczbę plików.

### Migracja z `wp_simple_admin`

Jeśli obecne są pliki i dane starej wtyczki `wp_simple_admin`, SimplePress
automatycznie zaimportuje starą bazę i ustawienia przy pierwszym użyciu.

## Pierwsze kroki

1. Włącz wtyczkę.
2. Otwórz **SimplePress → Kokpit** i sprawdź kartę kondycji serwera.
3. Otwórz **Wygląd** i wybierz schemat kolorów oraz edytor.
4. Skonfiguruj ekran logowania, frontowy pasek administracyjny i branding w
   **Wygląd → Branding / Logowanie**.
5. Przejrzyj **Użytkowników** i **Ukryte sekcje**, jeśli prowadzisz stronę
   wieloużytkownikową.
6. Utwórz pierwszy snippet lub własną pozycję menu, jeśli wymaga tego motyw.

## Sekcje panelu

| Sekcja | Przeznaczenie |
| --- | --- |
| Kokpit | Statystyki strony, szybki szkic, ostatnia aktywność i kondycja serwera. |
| Wtyczki | Instalacja, odinstalowywanie i katalog wtyczek GetSimple CE. |
| Edytor | Wybór CKEditora lub TinyMCE i konfiguracja paska narzędzi. |
| Użytkownicy | Tworzenie użytkowników, zmiana ról i preferencje użytkowników. |
| Snippety | Wielokrotnego użytku bloki PHP/HTML dostępne przez `get_snippet()`. |
| Menu | Własne pozycje sidebara, górnych zakładek i frontowego paska. |
| Wygląd | Schematy kolorów, ekran logowania, branding i frontowy pasek. |
| Motyw | Customizer motywu i kreator ustawień zaplecza. |
| Konserwacja | Tryb konserwacji i ustawienia strony konserwacji. |
| Kopie | Tworzenie, lista i pobieranie kopii zapasowych. |
| Ukryte sekcje | Ukrywanie pozycji menu panelu dla użytkowników. |
| Migracja i SSL | Migracja domeny i zarządzane przekierowanie HTTPS. |
| Narzędzia | Edytor gsconfig, edytor komponentów, siatka Bootstrap i narzędzia. |
| Pomoc | Help desk i odnośniki do dokumentacji. |
| SQLite3 | Przegląd bazy SQLite SimplePress. |
| Moje konto | Preferencje schematu, edytora i frontowego paska użytkownika. |

## Wygląd i schematy kolorów

SimplePress ma trzy schematy:

- **Classic** — znajoma paleta WordPressa: niebieski i szary.
- **Modern** — jaśniejsza, bardziej płaska paleta.
- **Dark** — ciemna paleta do pracy nocą.

Administrator wybiera schemat globalny. Każdy użytkownik może go nadpisać w
**Moje konto**. Preferencje są przechowywane w bazie SQLite SimplePress.

## Edytor

SimplePress może korzystać z:

- **CKEditor** — edytor dołączony do GetSimple CMS CE,
- **TinyMCE 6** — lokalna kopia w `simplepress/lib/tinymce/`.

Żadne zasoby edytora nie są ładowane z CDN. Opcje paska narzędzi i preferencje
edytora ustawia się w **SimplePress → Edytor**.

## Biblioteka mediów

Biblioteka mediów obsługuje:

- upload przez drag & drop,
- miniatury obrazów,
- przycinanie obrazów lokalnym Cropper.js,
- wstawianie do edytora,
- bezpieczne rozszerzenia dokumentów i mediów.

Upload plików PHP i SVG jest odrzucany przez walidator.

## Użytkownicy i role

SimplePress dodaje:

- preferencje schematu kolorów i edytora per użytkownik,
- preferencję frontowego paska administracyjnego per użytkownik,
- ukrywanie sekcji panelu per użytkownik,
- sprawdzanie ról dla akcji zastrzeżonych dla administratora.

Wtyczka nie zmienia plików użytkowników rdzenia GetSimple. Metadane są
przechowywane w bazie SQLite SimplePress.

## Snippety

Snippety to bloki HTML/PHP wielokrotnego użytku, które można wstawiać do motywów
i komponentów.

```php
<?php get_snippet('footer-cta'); ?>
```

Aby pobrać treść bez drukowania:

```php
<?php
$html = return_snippet('footer-cta');
?>
```

Snippetami zarządza się w **SimplePress → Snippety**. Są przechowywane w
`data/other/snippetMassive/snippet.json`.

## Rozszerzenia menu

Własne odnośniki można dodać do:

- sidebara panelu,
- górnego paska zakładek,
- frontowego paska administracyjnego.

Zarządza się nimi w **SimplePress → Menu**. Odnośniki są przechowywane w
`data/other/simplepress/menu_ext.json`.

Dostęp programowy:

```php
<?php
$links = wp_simple_get_menu_links();
foreach ($links as $id => $link) {
    echo '<a href="' . htmlspecialchars($link['url'], ENT_QUOTES, 'UTF-8') . '">'
        . htmlspecialchars($link['name'], ENT_QUOTES, 'UTF-8')
        . '</a>';
}
?>
```

## Ustawienia motywu i API customizera

Motywy mogą rejestrować własne sekcje i ustawienia customizera. SimplePress
przechowuje wartości per aktywny motyw i udostępnia API w stylu WordPressa.

### Rejestracja sekcji

```php
<?php
if (function_exists('sp_customizer_add_section')) {
    sp_customizer_add_section('homepage', 'Strona główna', ['priority' => 20]);
}
?>
```

### Rejestracja ustawienia

```php
<?php
if (function_exists('sp_customizer_add_setting')) {
    sp_customizer_add_setting('hero_title', [
        'label'       => 'Tytuł hero',
        'type'        => 'text',
        'default'     => 'Witaj',
        'section'     => 'homepage',
        'description' => 'Tytuł wyświetlany nad treścią.',
        'priority'    => 10,
    ]);
}
?>
```

### Odczyt wartości

```php
<?php
$title = sp_theme_option('hero_title', 'Witaj');
echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
?>
```

Dla ustawień wielowartościowych (galerie, grupy checkboxów, multiselect):

```php
<?php
$images = sp_theme_option_array('hero_gallery', []);
foreach ($images as $image) {
    echo '<img src="' . htmlspecialchars($image, ENT_QUOTES, 'UTF-8') . '" alt="">';
}
?>
```

### Obsługiwane typy pól

`text`, `textarea`, `wysiwyg`, `select`, `radio`, `button_group`, `toggle`,
`checkbox`, `multiselect`, `color`, `range`, `number`, `date`, `time`,
`image`, `media`, `file`, `page`, `link`, `gallery`, `dashicon`, `code`.

### Kreator ustawień zaplecza

**SimplePress → Motyw** zawiera kreator ustawień, który generuje gotowy fragment
PHP do wklejenia w `functions.php` motywu.

## Frontowy pasek administracyjny

Zalogowani użytkownicy mogą widzieć na stronie frontowej pasek administracyjny w
stylu WordPressa. Zapewnia on szybkie odnośniki do:

- kokpitu SimplePress,
- stron,
- edycji bieżącej strony,
- dodania nowej strony,
- customizera motywu (administratorzy),
- konta użytkownika i wylogowania.

Pasek można wyłączyć globalnie lub per użytkownik.

## Konserwacja, kopie i migracja

### Tryb konserwacji

Włącz go w **SimplePress → Konserwacja**. Administratorzy zachowują dostęp,
a odwiedzający widzą stronę konserwacji.

### Kopie zapasowe

**SimplePress → Kopie** tworzy archiwa ZIP z wybranych obszarów:

- plugins,
- admin,
- data,
- themes,
- uploads.

Kopie są przechowywane w `data/other/simplepress/backups/` i pobierane przez
uwierzytelniony endpoint zabezpieczony nonce.

### Migracja domeny i SSL

**SimplePress → Migracja i SSL** potrafi:

- zaktualizować adres strony po przeniesieniu na nową domenę,
- zarządzać przekierowaniem HTTPS,
- zapisać status ostatniego sprawdzenia SSL.

## Instalator wtyczek

Instalator jest dostępny w **SimplePress → Wtyczki**. Jest celowo ograniczony:

- tylko HTTPS,
- allowlista hostów,
- brak symlinków i path traversal w wpisach ZIP,
- limity rozmiaru archiwum i liczby plików,
- blokada nadpisania samego SimplePress,
- pomijanie `.git`, `.github`, `__MACOSX`, `node_modules` i plików
  dokumentacji z katalogu głównego podczas kopiowania.

Jeśli archiwum GitHub zawiera folder `plugin-AdminRenamer-1.1/`, instalator
odczytuje faktyczny główny plik PHP (`AdminRenamer.php`) i instaluje go jako
`plugins/AdminRenamer.php` (oraz katalog pluginu, jeśli istnieje).

## Bezpieczeństwo

- Każda akcja zmieniająca stan jest tylko POST i jest chroniona nonce SimplePress.
- Akcje zastrzeżone dla administratora sprawdzają zalogowanego użytkownika i rolę.
- Upload jest ograniczony do zwalidowanych obrazów i bezpiecznych rozszerzeń.
- Upload plików PHP i SVG jest odrzucany.
- Operacje na plikach używają sanityzacji `basename`, `realpath()` i sprawdzania symlinków.
- Instalacja ZIP waliduje wpisy, symlinki, rozmiar i liczbę plików.
- Kopie są pobierane wyłącznie przez uwierzytelniony endpoint z nonce.
- Zapis JSON jest atomowy, a pliki chronione mają restrykcyjne uprawnienia.
- Statystyki są zapisywane z blokadą i ograniczonym rozmiarem.

Jeśli znajdziesz problem bezpieczeństwa, nie publikuj szczegółów exploita w
publicznym issue. Najpierw skontaktuj się z maintainerem.

## Struktura katalogów

```text
plugins/
├── simplepress.php
└── simplepress/
    ├── css/
    ├── fonts/
    ├── img/
    │   └── sp.svg
    ├── includes/
    ├── js/
    ├── lang/
    ├── lib/
    └── README.md
```

Dane runtime są przechowywane poza katalogiem wtyczki:

```text
data/other/simplepress.db
data/other/simplepress/
data/other/snippetMassive/snippet.json
data/uploads/simplepress/
```

## Hooki i filtry

SimplePress korzysta z `exec_filter()` GetSimple CE:

| Filtr | Przeznaczenie |
| --- | --- |
| `simplepress_admin_tabs` | Dodawanie lub modyfikacja zakładek panelu. |
| `simplepress_admin_views` | Mapowanie kluczy zakładek na funkcje widoków. |
| `simplepress_sidebar_links` | Dodawanie lub modyfikacja pozycji sidebara. |

Przykład:

```php
<?php
exec_filter('simplepress_admin_tabs', function ($tabs) {
    $tabs['my-section'] = ['Moja sekcja', 'dashicons-admin-generic'];
    return $tabs;
});
?>
```

## Lokalizacja

SimplePress zawiera:

- angielski (`en_US`),
- polski (`pl_PL`),
- hiszpański (`es_ES`).

Język jest zgodny z ustawieniem `$LANG` w GetSimple. Własne teksty można dodać w
plikach w `simplepress/lang/`.

## Aktualizacja

1. Zrób kopię `simplepress.php`, katalogu `simplepress/` i pliku
   `data/other/simplepress.db`.
2. Podmień pliki wtyczki na nowe wydanie.
3. Zachowaj katalogi `data/other/simplepress/` i `data/uploads/simplepress/`.
4. Wyczyść cache katalogu wtyczek, jeśli korzystasz z instalatora:

   ```text
   data/cache/simplepress_plugin_catalog.json
   ```

## Rozwiązywanie problemów

### Instalator zgłasza nieprawidłową strukturę archiwum

Zaktualizuj SimplePress do wersji 6.3.1 lub nowszej. Starsze wersje używały nazwy
folderu archiwum GitHub jako ID pluginu, przez co odrzucały większość plików ZIP
z GitHuba.

### Instalator nie może pobrać wtyczki

- Upewnij się, że adres zaczyna się od `https://`.
- Użyj obsługiwanego hosta (`github.com`, `codeload.github.com` itd.).
- Sprawdź, czy PHP ma rozszerzenie `zip` oraz czy dostępne jest cURL lub
  `allow_url_fopen`.
- Sprawdź uprawnienia zapisu do katalogów `plugins/` i `data/other/`.

### Panel wygląda bez stylów

- Upewnij się, że cały katalog `simplepress/` został wgrany, w tym `css/`,
  `js/`, `fonts/`, `img/` i `lib/`.
- Sprawdź, czy `SITEURL` w `gsconfig.php` jest poprawny.

### Ustawienia się nie zapisują

- Sprawdź, czy PHP ma rozszerzenie `sqlite3`.
- Sprawdź uprawnienia zapisu do `data/other/`.

### Wtyczka zainstalowała się pod złą nazwą

Instalator używa nazwy głównego pliku PHP pluginu. Jeśli archiwum zawiera kilka
plików PHP, upewnij się, że główny plik wywołuje `register_plugin()` lub zawiera
nagłówek `Plugin Name:`.

## Changelog

Zobacz [CHANGELOG.md](CHANGELOG.md).

## Licencja

SimplePress jest wydany na licencji GNU General Public License v3.0. Zobacz
[LICENSE](LICENSE).

SimplePress jest niezależnym projektem. Nie jest powiązany z projektem WordPress
ani firmą Automattic.
