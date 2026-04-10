# Changelog

Všechny důležité změny v projektu se evidují v tomto souboru.

## Pravidla zápisu

- Při každém commitu přidej nový řádek do sekce aktuálního data.
- Formát: `- [typ] krátký popis (commit: hash)`.
- Typy: `feat`, `fix`, `refactor`, `style`, `docs`, `chore`, `perf`, `security`.
- Nejnovější záznamy zapisuj nahoru.

## 2026-04-07

- [feat] Přidána klientská samoobsluha pro přesun termínu přes podepsaný odkaz (`/reservation-reschedule.php`) včetně kalendáře a výběru času (commit: `this-commit`)
- [feat] Do potvrzovacího e-mailu doplněno tlačítko „Přesunout termín“ vedle zrušení rezervace (commit: `this-commit`)
- [security] Rozšířena data rezervace o `service_id` a přidán audit event `reservation_customer_rescheduled` (commit: `this-commit`)

## 2026-04-09

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
