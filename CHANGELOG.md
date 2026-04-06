# Changelog

Všechny důležité změny v projektu se evidují v tomto souboru.

## Pravidla zápisu

- Při každém commitu přidej nový řádek do sekce aktuálního data.
- Formát: `- [typ] krátký popis (commit: hash)`.
- Typy: `feat`, `fix`, `refactor`, `style`, `docs`, `chore`, `perf`, `security`.
- Nejnovější záznamy zapisuj nahoru.

## 2026-04-06

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

