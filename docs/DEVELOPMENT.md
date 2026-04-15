# Vývojová pravidla

Tento dokument popisuje pravidla pro úpravy projektu PP Studio. Slouží jako společná paměť pro vývojáře i AI asistenty, aby se při změnách nezapomínalo na dokumentaci, changelog a postupný přechod do objektově orientovaného modelu.

## Před každou úpravou

- Přečti si relevantní části `README.md`.
- U změn konfigurace zkontroluj `docs/CONFIGURATION.md`.
- U změn adminu zkontroluj `docs/ADMIN_MANUAL.md`.
- U bezpečnostních změn zkontroluj `SECURITY.md`.
- U databázových změn zkontroluj `database/schema.sql` a existující update SQL soubory.

## Changelog

`CHANGELOG.md` aktualizuj u každé změny, která je důležitá pro provoz, uživatele nebo další vývoj:

- uživatelsky viditelné změny webu nebo adminu,
- opravy chyb,
- bezpečnostní změny,
- změny databáze a migrace,
- změny deploye, release procesu nebo cronů,
- větší refaktory včetně postupného OOP/OOM přechodu,
- změny dokumentace, pokud přidávají nebo mění projektová pravidla.

Formát zápisu dodržuj podle pravidel přímo v `CHANGELOG.md`.

## Kdy aktualizovat dokumentaci

Aktualizuj `README.md`, když se mění:

- struktura projektu,
- lokální spuštění,
- release/deploy proces,
- hlavní architektonické pravidlo,
- obecný vývojový workflow.

Aktualizuj `docs/CONFIGURATION.md`, když se mění:

- `.env` nebo `.env.example`,
- soubory v `config/`,
- externí integrace,
- e-mail, kalendář, recenze, URL webu nebo runtime nastavení.

Aktualizuj `docs/ADMIN_MANUAL.md`, když se mění:

- admin UI,
- workflow rezervací,
- dostupnost,
- služby a kategorie,
- vouchery,
- média,
- reminder logy,
- nastavení studia a webu.

Aktualizuj `SECURITY.md`, když se mění:

- přihlášení,
- tokeny,
- CSRF nebo antispam,
- rate-limity,
- oprávnění,
- bezpečnostní hlavičky nebo `.htaccess`,
- proces hlášení bezpečnostních problémů.

Aktualizuj databázové soubory, když se mění model dat:

- `database/schema.sql` pro aktuální cílové schéma,
- příslušný `database/update_*.sql` nebo maintenance runner pro existující instalace,
- dokumentaci v `docs/CONFIGURATION.md`, pokud změna ovlivňuje provoz nebo údržbu.

## Postupný OOP/OOM přechod

Projekt migruje postupně. Cílem je zmenšovat procedurální entrypointy a přesouvat aplikační logiku do `src/` pod namespace `PPStudio\`.

Zásady:

- Zachovávej stávající veřejné URL, pokud změna URL není výslovně součástí zadání.
- Nový aplikační kód dávej do `src/`.
- Používej PSR-4 namespace `PPStudio\`.
- Nech kompatibilní wrapper funkce v `includes/`, dokud nejsou převedené všechny call-sites.
- Při větším přesunu logiky doplň záznam do `CHANGELOG.md`.
- Refaktoruj po modulech, ne plošně přes celý projekt.
- Pokud je možné změnu rozdělit, začni bezpečnými částmi: databázové připojení, repozitáře, čisté utility, služby, až potom controllery/admin.

Doporučené pořadí modulů:

1. Sjednocení bootstrapu a databázového připojení.
2. Repozitáře pro nastavení, služby, dostupnost a rezervace.
3. Doménové služby pro dostupnost a rezervace.
4. Tenké API a formulářové controllery.
5. Admin moduly.
6. Mailer a notifikace.

## Kontrola před dokončením

Před předáním změny zkontroluj:

- zda je potřeba aktualizovat `CHANGELOG.md`,
- zda je potřeba aktualizovat README nebo dokumentaci v `docs/`,
- zda změna nevyžaduje úpravu `.env.example`,
- zda změna nevyžaduje SQL migraci,
- zda zůstaly zachované stávající URL a kompatibilita,
- u změn rezervační logiky (dostupnost, validace slotů, kolize, lock) spusť `php scripts/run-reservation-integration.php`,
- zda byly spuštěné dostupné kontroly nebo je jasně uvedené, proč spuštěné nebyly.
