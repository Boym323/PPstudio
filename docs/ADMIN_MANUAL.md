# Admin manuál (PP Studio)

Tento manuál je praktický návod pro každodenní práci v adminu.

## 1) Typy admin rozhraní

- `admin.php` (plný admin): obsahuje všechny sekce.
- `admin-lite.php` (user admin): zjednodušená verze pro běžnou obsluhu.

## 2) Přihlášení

- URL: `https://.../admin.php` nebo `https://.../admin-lite.php`
- Údaje jsou řízené přes `.env`:
- `PPSTUDIO_ADMIN_USERNAME` + `PPSTUDIO_ADMIN_PASSWORD_HASH`
- `PPSTUDIO_STAFF_USERNAME` + `PPSTUDIO_STAFF_PASSWORD_HASH`

## 3) Sekce a co v nich dělat

### Dashboard

- Rychlý přehled provozu.
- KPI pro orientaci v aktuálním stavu rezervací.

### Rezervace

- Filtrování podle jména/e-mailu/telefonu/stavu/období.
- Úprava stavu rezervace.
- Interní poznámka k rezervaci.
- Smazání rezervace (chráněno potvrzením).

#### Stavy rezervace

- `nova`: nová, dosud nevyřízená.
- `potvrzena`: termín potvrzený klientce.
- `dokoncena`: služba proběhla.
- `zrusena`: rezervace zrušená.

Praktický dopad:
- Stav ovlivňuje přehledy, filtry a provozní statistiky.
- Stav `zrusena` se nezapočítává jako aktivní termín.

### Kalendář

- Týdenní/měsíční přehled rezervací.
- Náhled obsazenosti a návaznost na plánování dostupnosti.

### E-mail

- Test notifikací a kontrola e-mailových šablon.
- Ověření, že SMTP odesílá korektně.

### Antispam log

- Přehled antispam událostí rezervačního formuláře.
- Kontrola falešných/robotických pokusů.

### Dostupnost

- Plánování dostupných intervalů po týdnech.
- Rychlá tlačítka pro pracovní režimy.
- Cíl: definovat volné sloty, ze kterých se generují časy v rezervaci.

### Služby

- Správa kategorií a procedur.
- Úprava ceny, délky, popisu, pořadí.
- Přesun kategorií (drag and drop).

### Poukazy

- Hromadné generování kódů poukazů.
- Ruční vytvoření poukazu.
- Částečné čerpání se zůstatkem a historií transakcí.

Pravidlo:
- Čerpání je účetní operace, vždy přidávej poznámku, pokud jde o nestandardní případ.

### Fotky a galerie

- Nahrávání profilové fotky, galerie a certifikátů.
- Certifikáty se zobrazují i v sekci O mně na webu.

### Recenze a social

- Odkazy na Google/Firmy.cz/Instagram.
- Embed pole pro recenzní nebo sociální widgety.

### Nastavení studia

- Hlavní kontaktní údaje.
- Název webu, URL, provozní informace.
- Odesílací a notifikační vazby jsou zde jen částečně, tajné údaje jsou v `.env`.

## 4) Doporučený provozní postup

1. Každé ráno zkontrolovat nové rezervace.
2. Potvrdit nebo upravit stav.
3. Zkontrolovat dostupnost na následující 1–2 týdny.
4. Po změně ceníku ověřit webovou sekci Ceník + formulář rezervace.
5. 1× týdně projít antispam log.

## 5) Bezpečnostní zásady

- Admin účty nezdílet mezi více lidmi.
- Používat silná hesla (hash v `.env`).
- Po práci se vždy odhlásit.
- Neukládat tajné klíče do DB ani do veřejných souborů.

## 6) Rychlé řešení problémů

- Nelze se přihlásit:
- zkontroluj `PPSTUDIO_*_USERNAME` a `PPSTUDIO_*_PASSWORD_HASH`.
- Nechodí e-maily:
- zkontroluj SMTP hodnoty v `.env` + SPF/DKIM/DMARC v DNS.
- Nejde uložit dostupnost/služby:
- ověř DB připojení a práva k tabulkám.
- Chybí widgety:
- zkontroluj URL/embed hodnoty v admin nastavení recenzí.
