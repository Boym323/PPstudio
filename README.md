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
- Vývojová pravidla: `docs/DEVELOPMENT.md`

## Rychlé nasazení / lokální spuštění

1. Vytvořte `.env` podle `.env.example` (nikdy necommitovat).
2. Připravte databázi:
   - vytvořit DB
   - importovat schéma `database/schema.sql`
3. Po nasazení doporučeno spustit maintenance:
   - `php database/run_db_maintenance.php`
4. Pro integrační ověření rezervací (sloty, validace, kolize, paralelní lock):
   - `php scripts/run-reservation-integration.php`
5. Pro public flow smoke testy rezervačního formuláře, CSRF, antispamu a site locku:
   - `php scripts/run-reservation-public-flow-tests.php`
6. Pro smoke test admin reservation AJAX mutace:
   - `php scripts/run-admin-reservation-action-tests.php`
7. Pro smoke/integration test admin reservation use-case větví:
   - `php scripts/run-admin-reservation-usecase-tests.php`
8. Pro souhrnný smoke/integration runner pro oba admin use-case testy:
   - `php scripts/run-admin-usecase-regression-tests.php`
9. Pro public flow smoke testy voucher detailu a ověření poukazu:
   - `php scripts/run-voucher-public-flow-tests.php`
10. Pro samostatný smoke test admin DL tisku poukazu:
   - `php scripts/run-admin-voucher-dl-tests.php`
11. Pro admin voucher POST smoke test vytvoření a čerpání poukazu:
   - `php scripts/run-admin-voucher-post-tests.php`
12. Pro smoke test admin voucher use-case větví:
   - `php scripts/run-admin-voucher-usecase-tests.php`
13. Pro golden-master regression test renderu availability story/feed obrázků:
   - `php scripts/run-availability-story-render-regression-tests.php`

## Release ZIP pro FTP (tag + balíček)

Pro ruční nasazení přes FTP je nejjednodušší dělat verzované ZIP balíčky z tagu.

1. Vytvořte tag (příklad):
   - `git tag -a v2026-04-12 -m "Release v2026-04-12"`
2. Vytvořte ZIP z tagu:
   - `bash scripts/make-release-zip.sh v2026-04-12`
3. Výstup:
   - `dist/ppstudio-v2026-04-12.zip`

Poznámky:

- ZIP je vytvořený pomocí `git archive` (obsahuje jen verzované soubory).
- `.gitattributes` zajišťuje, že se do ZIPu nedostanou interní věci jako `docs/` nebo `.github/`.
- `.env`, `uploads/` a `var/` nejsou součástí repozitáře, takže v ZIPu typicky nebudou (kromě `.gitkeep`/`.htaccess`).

### Cron (remindery)

Runner: `reservation-reminders.php`

Doporučený cron (např. 1× za hodinu):

`php /Volumes/web/ppstudio.cz/reservation-reminders.php`

## Struktura adresářů (orientačně)

- `config/` OOP bootstrap `.env`, přístupy, e-mail
- `includes/` kompatibilní vrstva + legacy části (web pages a některé admin entrypoint wrappery)
- `api/` API endpointy
- `src/` OOP aplikační vrstva (`Service/`, `Repository/`, `Http/Controller/`, `Http/View/`)
- `src/Http/View/Templates/` sdílené šablony pro admin/public render (včetně admin sections layoutu)
- `database/` SQL + maintenance runner
- `assets/` statické assety (CSS/JS/fonts/obrázky/favikony)
- `vendor/` přibalené knihovny (např. PHPMailer)
- `var/` runtime úložiště (logy, security, backupy) – necommitovat
- `uploads/` nahrané soubory – necommitovat

## Postupný OOP základ

Nový kód patří do `src/` pod namespace `PPStudio\`. Projekt má připravený
`composer.json` s PSR-4 autoloadem, takže po dostupnosti Composeru stačí spustit:

`composer dump-autoload`

Dokud Composer není na prostředí dostupný, funguje fallback autoloader
`includes/bootstrap.php`.

Databázové připojení se v endpointech získává jednotně přes
`PPStudio\Database\DatabaseFactory`, která načítá výchozí konfiguraci projektu a
předává ji do `PPStudio\Database\DatabaseConnection`. Přímé `new mysqli(...)`
patří jen do této infrastrukturní connection třídy.

Postupně převáděná business logika se dělí na repozitáře v `src/Repository/`
pro čisté databázové dotazy a služby v `src/Service/` pro výpočty, validace a
orchestrace. Starší procedurální helpery zůstávají jen tam, kde je to stále
užitečné pro kompatibilitu, jinak patří nová logika přímo do `src/`.

Společné technické helpery jsou postupně rozdělované do `src/Support/`
(`ViewHelper`, `FormatHelper`, `DateHelper`, `ReservationStatusHelper`,
`ValueHelper`, `OpeningHoursHelper`, `SettingsHelper`, `ContactHelper`),
zatímco `includes/functions.php` zůstává jen jako kompatibilní mezivrstva.

Konfigurační vrstva je nově soustředěná do `src/Config/`
(`AppConfig`, `EnvLoader`, `SiteDefaultsProvider`) a `config/app.php` ji jen
bootstrappuje spolu s tenkými BC helpery pro starší call-sitey.

Admin render vrstva už běží přes `src/Http/View/` (page/login renderer a šablony
v `src/Http/View/Templates/`), zatímco historické cesty v `includes/admin/`
jsou postupně ztenčované na BC vrstvu.

Veřejné stránky se spouštějí přes malou OOP application vrstvu
`PPStudio\Http\Controller\SitePageApplication`; `includes/site/render.php`
zůstává jen jako BC wrapper a stránkové view-modely pro `Služby`, `O mně`
a `Recenze` teď skládá `PPStudio\Http\View\SitePageContextBuilder` místo
inline PHP v šablonách.

Podrobnější pravidla pro postupný OOP/OOM přechod, dokumentaci a changelog jsou
v `docs/DEVELOPMENT.md`. Krátké pokyny pro AI asistenty jsou také v `AGENTS.md`.

## Bezpečnost

Pro hlášení zranitelností viz `SECURITY.md`.
