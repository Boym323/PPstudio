# Changelog

Všechny důležité změny v projektu se evidují v tomto souboru.

## Pravidla zápisu

- Při každém commitu přidej nový řádek do sekce aktuálního data.
- Formát: `- [typ] krátký popis (commit: hash)`.
- Typy: `feat`, `fix`, `refactor`, `style`, `docs`, `chore`, `perf`, `security`.
- Nejnovější záznamy zapisuj nahoru.

## 2026-04-15

- [fix] Opraven pád admin záložky rezervací při načítání bez aktivního filtru inicializací výchozí SQL podmínky v loaderu rezervací (commit: `this-commit`)
- [refactor] Do OOP rezervační vrstvy byly doplněny lehké doménové objekty `ServiceItem`, `AvailabilityWindow`, `ReservationSlot` a `ReservationData`; interní výpočty dostupnosti a zápis rezervace už méně spoléhají na nepojmenovaná asociativní pole při zachování kompatibilních výstupů (commit: `this-commit`)
- [refactor] Admin orchestrace byla rozdělena na základní modulové controllery `AdminDashboardController`, `AdminReservationController`, `AdminServiceController`, `AdminAvailabilityController`, `AdminMediaController`, `AdminSettingsController`, `AdminVoucherController` a `AdminSecurityLogController`; původní `admin.php` zůstává kompatibilní vstupní orchestrátor (commit: `this-commit`)
- [refactor] Nastavení webu a bezpečnostní helpery byly odděleny do OOP tříd `SiteSettingsRepository`, `SiteSettingsService`, `CsrfService`, `SessionService`, `RequestSecurityService` a `SecurityEventLogger`; původní include funkce zůstávají jako kompatibilní wrappery (commit: `this-commit`)
- [refactor] Reminder runner `reservation-reminders.php` byl ztenčen; výběr kandidátů, pravidla odeslání, notifikace, označení `reminder_sent_at` a souhrnné logování nově řeší `ReservationReminderService` při zachování stejného CLI spuštění (commit: `this-commit`)
- [refactor] Veřejné rezervační akční endpointy `reservation-action.php`, `reservation-cancel.php` a `reservation-reschedule.php` byly ztenčeny; ověření podepsaných odkazů, lookup rezervace, změny stavu/termínu a notifikace nově orchestru běží přes `ReservationActionController` a `ReservationActionService` při zachování původních URL i parametrů (commit: `this-commit`)
- [refactor] Technické odesílání e-mailů a podepisování rezervačních akčních odkazů bylo odděleno do služeb `Mailer` a `ReservationLinkSigner`; rezervační e-mailové šablony nově skládá `ReservationNotificationService` při zachování kompatibilních wrapperů v `includes/mailer.php` (commit: `this-commit`)
- [refactor] Veřejné API endpointy `api/availability.php` a `api/services.php` byly ztenčeny na vstupní skripty a aplikační logika se přesunula do OOP controllerů v `src/Http/Controller/` (commit: `this-commit`)
- [refactor] Odesílání rezervačních e-mailů je zapouzdřeno v nové OOP službě `ReservationNotificationService`, takže `ReservationSubmitService` už nevolá globální mailer funkce přímo (commit: `this-commit`)
- [refactor] `reservation-submit.php` ztenčen na bootstrap a předání řízení do `ReservationController`; request validace a orchestrace uložení s e-maily jsou nově v OOP vrstvě pod `src/` při zachování veřejné URL i parametrů formuláře (commit: `this-commit`)
- [feat] Přidán opakovatelný CLI integrační scénář `scripts/run-reservation-integration.php` pro ověření rezervačního flow (volné sloty, validace slotu, kolize i rezervace mimo dostupnost) nad reálnou DB s automatickým cleanupem testovacích dat (commit: `this-commit`)
- [test] Integrační scénář rezervací rozšířen o paralelní kolizní test se dvěma současnými pokusy o stejný termín, který ověřuje lock logiku (`ok` + `slot_unavailable`) (commit: `this-commit`)
- [refactor] Rezervační dostupnost byla rozdělena z `includes/availability.php` do OOP repozitářů a služeb v `src/`, původní funkce zůstávají jako kompatibilní wrappery (commit: `this-commit`)
- [fix] Doplněna rewrite pravidla pro `/admin` a `/admin-lite`, aby krátké URL směřovaly na příslušné entrypointy `admin.php` a `admin-lite.php` (commit: `this-commit`)
- [perf] Výpočet volných dnů rezervací nejdřív pracuje jen s dny, kde existuje dostupnostní okno, takže načítání termínů na webu neprochází zbytečně celé období den po dni (commit: `this-commit`)
- [fix] Rezervační frontend posílá u AJAX požadavků explicitně same-origin session cookies, aby se po odemčení webu správně načítaly volné dny a časy z API (commit: `this-commit`)
- [refactor] Sjednocen bootstrap a vytváření databázového připojení přes `PPStudio\Database\DatabaseFactory`; endpointy už nenačítají DB konfiguraci ručně a přímé `new mysqli(...)` zůstává jen v infrastrukturní connection třídě (commit: `this-commit`)
- [docs] Přidána projektová vývojová pravidla v `docs/DEVELOPMENT.md`, krátké pokyny pro asistenty v `AGENTS.md` a odkazy z `README.md` kvůli konzistentní práci s dokumentací, changelogem a postupným OOP/OOM přechodem (commit: `this-commit`)

## 2026-04-07

- [feat] Přidána klientská samoobsluha pro přesun termínu přes podepsaný odkaz (`/reservation-reschedule.php`) včetně kalendáře a výběru času (commit: `this-commit`)
- [feat] Do potvrzovacího e-mailu doplněno tlačítko „Přesunout termín“ vedle zrušení rezervace (commit: `this-commit`)
- [security] Rozšířena data rezervace o `service_id` a přidán audit event `reservation_customer_rescheduled` (commit: `this-commit`)

## 2026-04-12

- [ux] Text na stránce `Prostory` byl upraven tak, aby jen oznamoval přípravu nových fotografií prostoru bez odkazů na předchozí stav webu nebo staré podklady (commit: `this-commit`)
- [fix] Ze stránky `Prostory` byly staženy neaktuální fotografie starého salonu a nahrazeny poctivou informací, že nové fotky aktuálního prostoru se teprve připravují (commit: `this-commit`)
- [refactor] `reservation-reminders.php` nově zapisuje do `reservation_reminder_logs` jen jeden souhrnný záznam za běh místo více dílčích řádků, aby byl reminder log v adminu přehlednější (commit: `this-commit`)
- [ux] V ceníku na webu byl z hlaviček kategorií odstraněn počet služeb, aby působil klidněji a méně katalogově (commit: `this-commit`)
- [feat] Štítky v ceníku jsou nově spravované ručně v adminu u každé procedury přes pole `Štítek v ceníku`; web už je neodvozuje automaticky z názvu nebo pořadí (commit: `this-commit`)
- [docs] `docs/ADMIN_MANUAL.md` doplněn o ruční správu štítků procedur v adminu (commit: `this-commit`)
- [seo] Ve veřejných textech webu byla sjednocena komunikace lokace na obecné „ve Zlíně“ bez natvrdo uvedeného Interhotelu (homepage, galerie, sekce prostory) (commit: `this-commit`)
- [fix] Veřejný kontakt a strukturovaná data nově zobrazují adresu jen pokud je skutečně vyplněná v nastavení (`contact_address`), bez tvrdého fallbacku adresy (commit: `this-commit`)
- [feat] V adminu přidána nová sekce `Reminder log` s filtrem, stránkováním a přehledem DB auditních záznamů z `reservation-reminders.php` (run token, událost, úroveň, rezervace, kontext) (commit: `this-commit`)
- [docs] `docs/ADMIN_MANUAL.md` rozšířen o práci se sekcí `Reminder log` včetně doporučení pravidelné kontroly (commit: `this-commit`)
- [feat] `reservation-reminders.php` nově zapisuje detailní audit běhu do DB tabulky `reservation_reminder_logs` (start, kandidáti, per-rezervace stav, souhrn) a vypisuje `run_token` do CLI výstupu (commit: `this-commit`)
- [docs] `docs/CONFIGURATION.md` doplněn o technický popis reminder audit logu v DB včetně retenčního cleanupu (90 dní / 500 řádků na běh) (commit: `this-commit`)
- [perf] Databáze rezervací a dostupnosti byla doplněna o nové indexy, přepsány datumové filtry na intervalové dotazy a odstraněny redundantní indexy po ověření `EXPLAIN` plánů (commit: `this-commit`)
- [security] DB nyní vynucuje základní integritní pravidla pro dostupnost, historii cen a poukazy přes `CHECK` constrainty a unikátní otevřený záznam historie ceny služby (commit: `this-commit`)
- [feat] Přidán opakovatelný maintenance runner `database/run_db_maintenance.php`, který aplikuje DB migrace i následné ověření indexů, constraintů a explain plánů (commit: `this-commit`)
- [fix] Vytváření webových i ručních rezervací bylo zpevněno transakční kontrolou dostupnosti a kolizí termínů při souběžném zápisu (commit: `this-commit`)
- [feat] Poukazy lze nově odeslat e-mailem přímo z adminu, ukládá se e-mail příjemce i čas posledního odeslání a v e-mailu se používá nová veřejná stránka dárkového poukazu s tiskem / uložením do PDF (commit: `this-commit`)
- [ux] Ceník byl přepracován do více orientační podoby: přibyl průvodce výběrem, rychlé skoky do kategorií, krátké úvody kategorií a štítky u vybraných služeb (commit: `this-commit`)
- [ux] Veřejná rezervace dostala uklidňující mikrokopie a na mobilu se po přechodu mezi kroky automaticky posouvá na začátek aktivního kroku (commit: `this-commit`)
- [ux] Admin menu bylo přeskupeno do logických skupin `Provoz / Obsah / Nastavení`, samostatný kalendář přesunut do sekce rezervací a sekce `E-mail`, `Recenze a social` i `Nastavení studia` sjednoceny do `Nastavení studia a webu` (commit: `this-commit`)
- [feat] Přidán automatický reminder potvrzených rezervací e-mailem (výchozí 26 hodin předem) včetně evidence `reminder_sent_at`, CLI runneru `reservation-reminders.php` a zobrazení stavu reminderu v detailu rezervace (commit: `this-commit`)

## 2026-04-10

- [refactor] Z konfigurace a admin sekce `Recenze a social` byly odstraněny nepoužívané klíče `instagram_url` a `instagram_feed_embed`, vyčištěny SQL seedy a smazány staré hodnoty z live DB `nastaveni` (commit: `this-commit`)
- [security] Zákaznické odkazy pro zrušení a přesun termínu nově fungují jen do 24 hodin před začátkem procedury a po limitu se už do e-mailu nenabízejí (commit: `this-commit`)
- [ux] Stránka `/reservation-reschedule.php` nově potvrzuje změnu termínu přes AJAX bez reloadu a po úspěchu zobrazí finální potvrzovací kartu (commit: `this-commit`)

## 2026-04-09

- [ux] Veřejný rezervační formulář nově odesílá rezervaci přes AJAX bez reloadu a po úspěchu zobrazí potvrzovací kartu přímo na stránce (commit: `this-commit`)
- [ux] Klientská stránka pro přesun termínu dostala přehlednější potvrzovací panel a výraznější hlavní akci `Potvrdit přesun` navázanou na výběr nového termínu (commit: `this-commit`)
- [style] E-mailová tlačítka pro potvrzení, přesun a zrušení rezervace byla barevně sjednocena do jemnějšího PP Studio stylu při zachování bezpečnostní hierarchie akcí (commit: `this-commit`)
- [ux] Ruční rezervace v adminu nově umožňuje vybrat jen aktivní procedury a pouze skutečně volné dny/časy přes admin dostupnost API (commit: `this-commit`)
- [ux] U formulářů pro jednotlivé i hromadné vytváření poukazů je `Platnost do` nově předvyplněná na rok od aktuálního data (commit: `this-commit`)
- [feat] Sekce `Dostupnost` byla rozšířena o generátor Instagram story/feed obrázků s náhledem, styly výstupu, vlastním pozadím a exportem PNG z aktuálně volných termínů (commit: `this-commit`)
- [ux] Sekce `Fotky a galerie` byla rozdělena do podsekcí `Profilová fotka / Galerie salonu / Certifikáty`, zjednodušena pro běžnou obsluhu a doplněna o lepší náhled hlavní profilové fotky (commit: `this-commit`)
- [ux] Sekce `Dostupnost` byla zjednodušena: denní režim je výchozí, horní navigace týdne je klidnější, týdenní editor je kompaktnější a seznam uložených oken přehlednější (commit: `this-commit`)
- [ux] Dashboard v adminu přepracován do provozní nástěnky s bloky `Co potřebuje pozornost`, dnešními a zítřejšími rezervacemi, čekajícími novými rezervacemi a přehledem posledních zrušení či přesunů (commit: `this-commit`)

## 2026-04-08
- [ux] Sekce `Služby` dostala jasnější navigaci podsekcí, čitelnější aktivní záložky a záložku `Poslední změny cen` s cenovou historií přímo v detailu procedury (commit: `this-commit`)
- [ux] Sekce `Služby` v adminu byla přepracována do podsekcí `Procedury / Kategorie / Historie cen`, doplněna o filtry, detail procedury a kompaktnější formuláře i tabulky (commit: `this-commit`)
- [docs] Aktualizován `docs/ADMIN_MANUAL.md` o nový denní režim dostupnosti, kompaktní správu rezervací a detailní antispam log (commit: `this-commit`)
- [ux] Antispam log přepracován do kompaktního seznamu s detailem události a stránkováním (commit: `this-commit`)
- [ux] Admin rezervace přepracovány do kompaktního seznamu s rozbalovacím detailem, bezpečnějšími akcemi a lepším mobilním layoutem (commit: `this-commit`)
- [feat] Plánování dostupnosti doplněno o denní režim pro rychlou správu slotů z mobilu (commit: `this-commit`)
- [ux] Na stránce ověření poukazu odstraněny admin CTA a nahrazeny dvojicí klientských akcí: „Přejít na rezervaci termínu“ a „Přejít na hlavní stránku“ (commit: `this-commit`)
- [feat] Potvrzovací e-mail po přesunu termínu nyní obsahuje přehled „původní termín -> nový termín“ a upravený předmět zprávy (commit: `this-commit`)
- [feat] V admin sekci rezervací přidána možnost přeplánovat termín existující rezervace (validace dostupnosti, audit, live update tabulky) (commit: `this-commit`)
- [ux] Přeplánování v adminu přesunuto pod vedlejší tlačítko „Přeplánovat“ s výběrem pouze z dostupných dní a časů (commit: `this-commit`)
- [security] Přidán server hardening proti WordPress scanům v `.htaccess` + provedena rotace `PPSTUDIO_CALENDAR_TOKEN` po detekci tokenu v server logu (commit: `this-commit`)
- [ux] U čerpání poukazů se při vazbě na rezervaci automaticky předvyplní částka z ceny rezervace (s respektem k aktuálnímu zůstatku poukazu) (commit: `this-commit`)
- [ux] U vazby čerpání poukazu přidáno vyhledávání rezervací (jméno/telefon/služba/datum) a omezení nabídky na budoucí + posledních 90 dní (commit: `this-commit`)
- [ux] Vyhledávání rezervací u poukazů nyní zobrazuje viditelný seznam výsledků (klikací položky), select zůstává jako fallback (commit: `this-commit`)
- [feat] Přidána DL tisková šablona poukazu (`/admin-voucher-dl.php`) s QR a akcí „Tisk / Uložit jako PDF“ z adminu (commit: `this-commit`)
- [security] QR na poukazu nově používá podepsaný odkaz na `/voucher/verify` místo otevřených dat (commit: `this-commit`)
- [feat] Přidána stránka `/voucher-verify.php` s veřejným ověřením poukazu a rozšířeným detailem po přihlášení do adminu (commit: `this-commit`)
- [docs] Aktualizován `ADMIN_MANUAL.md` o práci s vyhledáváním rezervací u poukazů a DL šablonou (commit: `this-commit`)

## 2026-04-06

- [feat] Doplněn audit zrušení rezervací (důvod, kdo zrušil, účet, čas) pro admin i zákaznický odkaz (commit: `this-commit`)
- [feat] Přidán bezpečný odkaz na zrušení rezervace pro klientku (potvrzovací mezikrok + jednorázový token) (commit: `this-commit`)
- [feat] Přidán admin modul dárkových poukazů včetně částečného čerpání, historie transakcí a UX přehledu (commit: `this-commit`)
- [fix] Odstraněn odkaz na sdílený kalendář studia z potvrzovacího e-mailu klientky (commit: `b3224e7`)
- [style] Vylepšena responzivita tabulky rezervací a zkompaktněny admin akce (commit: `47ce4a9`)
- [refactor] Kontaktní údaje přesunuty do DB nastavení a sjednoceny zdroje konfigurace (commit: `4339fbd`)
- [chore] Přidána konfigurace Dependabotu pro monitoring Composer závislostí (commit: `19b39b1`)
- [refactor] Sjednocena konfigurace webu mezi DB a `.env` fallbacky (commit: `873f3f8`)
- [feat] Přepracován UX plánovače dostupnosti pro desktop i mobil (commit: `fc07a2c`)
- [fix] Opraveno SEO metadata, hierarchie nadpisů a URL homepage v sitemapě (commit: `0ca87fd`)
- [refactor] Vyčištěna nepoužívaná výchozí nastavení a migrační seedy (commit: `632d4dc`)
- [refactor] Odebrána mapa z webu i admin nastavení kontaktu (commit: `09e87c9`)
- [fix] Opravena normalizace URL mapy pro zachování embed odkazů z Mapy.com (commit: `36ea80e`)
- [feat] Povolen vstup `iframe` pro mapu v admin nastavení (commit: `a38b091`)
- [fix] Opravena práce s map embed nastavením a URL normalizací pro Mapy (commit: `b9adbbf`)
