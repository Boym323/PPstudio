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

- Dashboard je nově provozní nástěnka pro rychlou ranní kontrolu.
- Hlavní horní blok ukazuje:
- dnešní rezervace,
- zítřejší rezervace,
- čekající nové rezervace,
- volné sloty dnes.
- Blok `Co potřebuje pozornost` shrnuje nejdůležitější situace, které je vhodné řešit jako první.
- Pod ním jsou samostatné přehledy:
- `Dnešní rezervace`
- `Zítřejší rezervace`
- `Čekající nové rezervace`
- `Poslední zrušení a přesuny`
- Spodní část dashboardu zůstává jako sekundární analytika:
- nejbližší rezervace,
- stavy rezervací,
- nejžádanější procedury,
- top kategorie.

### Rezervace

- Filtrování podle jména/e-mailu/telefonu/stavu/období.
- Přehled je nově kompaktní seznam s tlačítkem `Detail`.
- V detailu rezervace lze:
- upravit stav rezervace,
- doplnit interní poznámku,
- zadat důvod zrušení (povinný při změně na stav `zrusena`),
- přeplánovat rezervaci na dostupný den a čas,
- trvale smazat rezervaci v oddělené „nebezpečné“ sekci.
- Potvrzovací e-mail klientce obsahuje bezpečný odkaz na zrušení (s mezikrokem potvrzení).

#### Stavy rezervace

- `nova`: nová, dosud nevyřízená.
- `potvrzena`: termín potvrzený klientce.
- `dokoncena`: služba proběhla.
- `zrusena`: rezervace zrušená.

Praktický dopad:
- Stav ovlivňuje přehledy, filtry a provozní statistiky.
- Stav `zrusena` se nezapočítává jako aktivní termín.
- U zrušené rezervace se eviduje audit: důvod, kdo zrušil, účet a čas.

### Kalendář

- Týdenní/měsíční přehled rezervací.
- Náhled obsazenosti a návaznost na plánování dostupnosti.

### E-mail

- Test notifikací a kontrola e-mailových šablon.
- Ověření, že SMTP odesílá korektně.

### Antispam log

- Přehled bezpečnostních událostí s filtrem podle důvodu, textu a počtu řádků.
- Seznam je zjednodušený, detail události se otevírá přes tlačítko `Detail`.
- V detailu je vidět:
- čas,
- důvod,
- sekce,
- IP adresa,
- user-agent,
- plný kontext události.
- Je přidané stránkování, takže lze procházet i starší události.
- Pokud systém běží ve fallback režimu bez DB tabulky `security_events`, zobrazuje pouze události rezervačního formuláře.

### Dostupnost

- Plánování dostupných intervalů je nově stavěné primárně pro rychlou denní práci.
- Po otevření sekce se jako výchozí zobrazí `Denní režim`.
- Denní režim slouží pro běžnou obsluhu:
- vybereš den,
- jedním klikem zapínáš/vypínáš jednotlivé časy,
- nebo hromadně upravíš celý rozsah.
- `Týdenní režim` zůstává jako pokročilejší editor pro větší změny a kontrolu celého týdne.
- Nahoře je kompaktní navigace týdne (`Předchozí / Tento týden / Další`).
- Ve spodním bloku `Uložená okna dostupnosti` je přehled už uložených intervalů a možnost jednotlivé okno ručně smazat.
- Součástí sekce je i blok `Instagram story`:
- z aktuálně volných termínů vygeneruje PNG pro Instagram,
- podporuje styly `Story / Minimal / Feed`,
- umožňuje nastavit období, nadpis, měsíc, počet dnů a doplňkové řádky,
- ukazuje živý náhled přímo v adminu,
- volitelně používá vlastní nahrané pozadí.
- Cíl: definovat volné sloty, ze kterých se generují časy v rezervaci.

### Služby

- Sekce je rozdělená do podsekcí:
- `Procedury`
- `Kategorie`
- `Poslední změny cen`
- V `Procedury` je:
- formulář pro založení nebo úpravu služby,
- filtrování podle názvu/popisu/kategorie/stavu,
- kompaktní přehled procedur s tlačítkem `Detail`.
- V detailu procedury je vidět plný popis, rychlá správa aktivace/deaktivace a vlastní cenová historie.
- V `Kategorie` je:
- formulář pro název a pořadí,
- přehled kategorií,
- řazení pomocí drag and drop.
- `Poslední změny cen` ukazují přehled změn ve formátu `původní cena -> nová cena` napříč všemi procedurami.

### Poukazy

- Hromadné generování kódů poukazů.
- Ruční vytvoření poukazu.
- Pole `Platnost do` je u nového i hromadně generovaného poukazu předvyplněné na rok od aktuálního dne.
- Částečné čerpání se zůstatkem a historií transakcí.
- Vazba čerpání na rezervaci:
- vyhledání rezervace podle jména/telefonu/služby/data,
- automatické předvyplnění částky podle ceny rezervace (max do výše zůstatku poukazu).
- DL šablona poukazu:
- v detailu poukazu tlačítko `DL tisk / PDF`,
- otevře tiskovou šablonu formátu DL (210 × 99 mm) s QR a údaji poukazu,
- finální PDF se uloží přes systémové `Tisk / Uložit jako PDF`.
- QR ověření:
- QR vede na podepsaný odkaz `/voucher/verify?v=...&sig=...`,
- bez přihlášení ukáže jen základní ověření platnosti,
- po přihlášení v adminu zobrazí i detail (včetně zůstatku).

Pravidlo:
- Čerpání je účetní operace, vždy přidávej poznámku, pokud jde o nestandardní případ.

### Fotky a galerie

- Sekce je rozdělená do podsekcí:
- `Profilová fotka`
- `Galerie salonu`
- `Certifikáty`
- `Profilová fotka`:
- slouží pro hlavní portrétní fotografii v sekci `O mně`,
- vždy se používá poslední nahraná profilová fotka,
- vpravo je náhled toho, co se právě zobrazuje na webu.
- `Galerie salonu`:
- slouží pro veřejnou sekci `Prostory`,
- lze vyplnit nadpis, podnadpis, odkaz a pořadí,
- podsekce odděluje formulář nahrání od přehledu už uložených snímků.
- `Certifikáty`:
- zobrazují se na webu v sekci `O mně`,
- po nahrání lze u každého upravit název, který se ukáže na webu,
- certifikát lze samostatně smazat bez zásahu do ostatních souborů.

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
