# Konfigurace PP Studio

Tento projekt je sjednocený na 2 zdroje konfigurace:

- `DB (tabulka nastaveni)` pro obsahová a provozní nastavení, která se mění přes admin.
- `.env` pro tajné údaje, přihlašovací údaje, integrace a technický runtime.

## 1) DB nastavení (`nastaveni`)

Klíče, které aplikace načítá z DB:

- `site_name` – název studia pro web/e-maily/admin.
- `site_url` – veřejná URL webu (kanonické URL, odkazy v e-mailech, sitemap).
- `contact_address` – adresa studia (web + e-mailové šablony).
- `contact_name` – jméno kontaktní osoby v sekci Rezervace & kontakt.
- `contact_phone` – telefon v sekci Rezervace & kontakt.
- `contact_email` – kontaktní e-mail v sekci Rezervace & kontakt (obfuskovaný na frontendu).
- `contact_instagram_url` – odkaz na Instagram v kontaktním boxu.
- `contact_ico` – IČO v kontaktním boxu.
- `contact_opening_hours` – otevírací doba v kontaktním boxu.
- `notification_emails` – cílové e-maily pro admin notifikace rezervací.
- `instagram_url` – odkaz na Instagram profil.
- `instagram_feed_embed` – embed kód/zdroj pro Instagram feed (pokud používán).
- `google_reviews_url` – odkaz na Google recenze/profil.
- `firmy_reviews_url` – odkaz na Firmy.cz recenze/profil.
- `firmy_reviews_embed` – embed kód pro Firmy.cz.
- `google_place_id` – Place ID pro Google recenze API widget.
- `google_reviews_language` – jazyk recenzí (`cs`, `en`, ...).

Poznámka: Výchozí fallback pro chybějící DB hodnoty je definovaný v `config/app.php` ve funkci `defaultSiteSettings()`.

## 2) ENV nastavení (`.env`)

### Databáze

- `PPSTUDIO_DB_HOST`
- `PPSTUDIO_DB_NAME`
- `PPSTUDIO_DB_USER`
- `PPSTUDIO_DB_PASSWORD`
- `PPSTUDIO_DB_CHARSET`

### Přihlášení / přístup

- `PPSTUDIO_ADMIN_USERNAME`
- `PPSTUDIO_ADMIN_PASSWORD_HASH`
- `PPSTUDIO_STAFF_USERNAME`
- `PPSTUDIO_STAFF_PASSWORD_HASH`
- `PPSTUDIO_PUBLIC_LOCK_ENABLED`
- `PPSTUDIO_PUBLIC_LOCK_PASSWORD`
- `PPSTUDIO_PUBLIC_LOCK_PASSWORD_HASH`

### E-mail / SMTP

- `PPSTUDIO_EMAIL_ENABLED`
- `PPSTUDIO_MAILER`
- `PPSTUDIO_SMTP_HOST`
- `PPSTUDIO_SMTP_PORT`
- `PPSTUDIO_SMTP_ENCRYPTION`
- `PPSTUDIO_SMTP_USERNAME`
- `PPSTUDIO_SMTP_PASSWORD`
- `PPSTUDIO_SMTP_AUTH`
- `PPSTUDIO_FROM_EMAIL`
- `PPSTUDIO_FROM_NAME`
- `PPSTUDIO_REPLY_TO`
- `PPSTUDIO_CALENDAR_TOKEN`
- `PPSTUDIO_ACTION_SECRET`
- `PPSTUDIO_ACTION_TTL_SECONDS`

### Integrace a bezpečnost

- `PPSTUDIO_GOOGLE_PLACES_API_KEY`
- `PPSTUDIO_SECURITY_STORAGE`

### Runtime fallbacky

- `PPSTUDIO_SITE_NAME` – fallback názvu, pokud v DB chybí `site_name`.
- `PPSTUDIO_SITE_URL` – fallback URL, pokud v DB chybí `site_url`.

## 3) Kde se co načítá

- `config/app.php` – bootstrap `.env`, defaulty pro site settings.
- `config/database.php` – DB připojení (jen z `.env`).
- `config/admin.php`, `config/admin_lite.php` – admin účty (jen z `.env`).
- `config/email.php` – SMTP a e-mailové tajné údaje (jen z `.env`).
- `includes/settings.php` – load/save DB nastavení z tabulky `nastaveni`.

## 4) Doporučená praxe

- Do Gitu necommitovat `.env` (jen `.env.example`).
- Hesla ukládat pouze jako hash (`*_PASSWORD_HASH`), ne v plain textu.
- V adminu měnit pouze klíče určené pro DB (`nastaveni`).
- Po změně `site_url` v adminu ověřit `sitemap.php` a kanonické URL.
