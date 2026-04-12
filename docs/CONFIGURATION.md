# Konfigurace PP Studio

Projekt používá 2 zdroje konfigurace:

- `DB (tabulka nastaveni)` pro obsahová nastavení měněná přes admin.
- `.env` pro tajné údaje, přístupy, SMTP a technické klíče.

## 1) DB nastavení (`nastaveni`)

Aktivní klíče, které aplikace načítá přes `SITE_SETTING_KEYS` v `config/app.php`:

- `site_name`
- `site_url`
- `contact_address`
- `contact_name`
- `contact_phone`
- `contact_email`
- `contact_instagram_url`
- `contact_ico`
- `contact_opening_hours`
- `google_reviews_url`
- `firmy_reviews_url`
- `firmy_reviews_embed`
- `google_place_id`
- `google_reviews_language`
- `notification_emails`
- `availability_story_background`

Poznámka: fallbacky pro chybějící DB hodnoty vrací `defaultSiteSettings()` v `config/app.php`.

## 2) ENV nastavení (`.env`)

### Databáze

- `PPSTUDIO_DB_HOST`
- `PPSTUDIO_DB_NAME`
- `PPSTUDIO_DB_USER`
- `PPSTUDIO_DB_PASSWORD`
- `PPSTUDIO_DB_CHARSET`

### Přihlášení a přístupy

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
- `PPSTUDIO_VOUCHER_VERIFY_SECRET`
- `PPSTUDIO_ACTION_TTL_SECONDS`
- `PPSTUDIO_CUSTOMER_ACTION_CUTOFF_SECONDS`
- `PPSTUDIO_RESERVATION_REMINDER_LEAD_SECONDS`
- `PPSTUDIO_RESERVATION_REMINDER_WINDOW_SECONDS`
- Doporučené výchozí nastavení reminderu:
- `PPSTUDIO_RESERVATION_REMINDER_LEAD_SECONDS=93600` (`26 hodin`)
- `PPSTUDIO_RESERVATION_REMINDER_WINDOW_SECONDS=3600` (`1 hodina`)

### Integrace a bezpečnost

- `PPSTUDIO_GOOGLE_PLACES_API_KEY`
- `PPSTUDIO_SECURITY_STORAGE`

### Runtime fallback

- `PPSTUDIO_SITE_NAME` fallback pro `site_name`
- `PPSTUDIO_SITE_URL` fallback pro `site_url`

## 3) Kde se načítá konfigurace

- `config/app.php`: načtení `.env` + seznam DB klíčů (`SITE_SETTING_KEYS`).
- `config/database.php`: DB připojení z `.env`.
- `config/admin.php`: přihlášení do plného adminu.
- `config/admin_lite.php`: přihlášení do user adminu.
- `config/email.php`: SMTP a e-mailové klíče.
- `includes/settings.php`: čtení/zápis `nastaveni`.
- `reservation-reminders.php`: CLI runner pro automatické reminder e-maily rezervací.
- `reservation_reminder_logs` (DB tabulka): audit běhů runneru `reservation-reminders.php` (start, kandidáti, odesláno/chyba/skip, souhrn běhu).
- `database/run_db_maintenance.php`: CLI runner pro databázovou maintenance migraci a ověření stavu DB po nasazení.
- Doporučený cron příkaz:
- `php /Volumes/web/ppstudio.cz/reservation-reminders.php`
- Retence reminder logu:
- runner maže při každém běhu záznamy starší než `90 dní` (po dávkách `500` řádků).

## 3a) DB maintenance

- Maintenance skript:
- `php /Volumes/web/ppstudio.cz/database/run_db_maintenance.php`
- Co dělá:
- aplikuje maintenance SQL pro integritu a indexy,
- odstraňuje bezpečně redundantní indexy,
- ověřuje constrainty a generated column,
- vypisuje `EXPLAIN` pro hlavní provozní dotazy.
- Kdy ho spustit:
- po DB deployi,
- po ruční změně indexů nebo constraintů,
- při podezření na zpomalení admin přehledů rezervací nebo dostupnosti.

## 4) Praktická pravidla

- `.env` nikdy necommitovat do Gitu.
- Hashovaná hesla (`*_PASSWORD_HASH`) používat místo plain textu.
- Obsahová data měnit v adminu, tajné klíče měnit v `.env`.
- Po změně `site_url` zkontrolovat kanonické URL a `sitemap.php`.

## 5) Rychlá kontrola po změně konfigurace

- Přihlášení do `admin.php` i `admin-lite.php`.
- Odeslání testovací rezervace (SMTP + notifikace).
- Zobrazení rezervačního formuláře a dostupnosti termínů.
- Kontrola widgetu recenzí a případných externích embedů.

## 6) Synology hardening (Web Station)

- V kořenovém `.htaccess` jsou blokované WordPress scan cesty (`/wp-admin`, `/wp-login.php`, `/xmlrpc.php`, `/wordpress/*`) návratovým kódem `410 Gone`.
- Cílem je snížit log noise a eliminovat opakované bot pokusy na neexistující WP endpointy.
- `PPSTUDIO_CALENDAR_TOKEN` je tajný klíč pro `/reservations-feed.php?token=...`; při úniku (např. v logu) token vždy otočte.
- Po rotaci tokenu je potřeba znovu přidat odběr kalendáře na všech zařízeních, protože starý odkaz přestane fungovat.
