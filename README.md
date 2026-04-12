# PP Studio (ppstudio.cz)

Web + rezervační systém pro PP Studio včetně admin rozhraní.

## Co v projektu je

- Veřejný web (stránky v `includes/site/pages/`)
- Rezervační formulář + správa rezervací
- Admin:
  - `admin.php` (plný admin)
  - `admin-lite.php` (zjednodušený admin)
- Automatické reminder e-maily (runner `reservation-reminders.php`, typicky přes cron)

Detailnější dokumentace:

- Konfigurace: `docs/CONFIGURATION.md`
- Admin manuál: `docs/ADMIN_MANUAL.md`

## Rychlé nasazení / lokální spuštění

1. Vytvořte `.env` podle `.env.example` (nikdy necommitovat).
2. Připravte databázi:
   - vytvořit DB
   - importovat schéma `database/schema.sql`
3. Po nasazení doporučeno spustit maintenance:
   - `php database/run_db_maintenance.php`

### Cron (remindery)

Runner: `reservation-reminders.php`

Doporučený cron (např. 1× za hodinu):

`php /Volumes/web/ppstudio.cz/reservation-reminders.php`

## Struktura adresářů (orientačně)

- `config/` načtení `.env`, přístupy, e-mail
- `includes/` PHP logika (web + admin)
- `api/` API endpointy
- `database/` SQL + maintenance runner
- `frontend/` statické assety (CSS/JS/fonts/obrázky)
- `vendor/` přibalené knihovny (např. PHPMailer)
- `var/` runtime úložiště (logy, security, backupy) – necommitovat
- `uploads/` nahrané soubory – necommitovat

## Bezpečnost

Pro hlášení zranitelností viz `SECURITY.md`.

