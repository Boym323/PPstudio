# Pokyny pro Codex a další asistenty

Tento soubor je krátká projektová paměť pro práci v repozitáři PP Studio. Před úpravami si přečti také `README.md` a podle typu změny související dokumentaci v `docs/`.

## Dokumentace a changelog

Dodržuj pravidla v `docs/DEVELOPMENT.md`, zejména:

- Aktualizuj `CHANGELOG.md` u uživatelsky viditelných změn, oprav, bezpečnostních změn, DB migrací, změn deploye a větších refaktorů.
- Aktualizuj `README.md` při změně struktury projektu, architektury, lokálního spuštění, release procesu nebo hlavního vývojového workflow.
- Aktualizuj `docs/CONFIGURATION.md` při změně `.env`, config souborů, externích služeb nebo integračních nastavení.
- Aktualizuj `docs/ADMIN_MANUAL.md` při změně admin UI, rezervací, voucherů, služeb, dostupnosti, médií nebo admin workflow.
- Aktualizuj `SECURITY.md` při změně přihlášení, tokenů, rate-limitů, ochrany formulářů, oprávnění nebo bezpečnostního procesu.
- Aktualizuj `database/schema.sql` a příslušné update SQL soubory při změně databázového modelu.

## Postupný OOP/OOM přechod

- Nový aplikační kód patří do `src/` pod namespace `PPStudio\`.
- Stávající veřejné URL a entrypointy zachovávej, pokud není výslovně zadáno jinak.
- Při přesunu procedurální logiky do tříd nech dočasně kompatibilní wrapper funkce, dokud nejsou převedené všechny call-sites.
- Větší refaktory zapisuj do `CHANGELOG.md` jako `refactor`.

## Pracovní zásady

- Neprováděj velký přepis najednou; preferuj malé bezpečné kroky.
- Neměň chování webu, adminu ani API bez jasného důvodu a záznamu v dokumentaci.
- Pokud změna vyžaduje dokumentaci a není jasné kam ji zapsat, použij nejbližší existující dokument a ve finální odpovědi uveď, kam byla doplněna.
