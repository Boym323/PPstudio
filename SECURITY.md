# Security Policy

## Supported Versions

Bezpečnostní opravy se řeší pro aktuální nasazenou verzi a větev `main`.

## Reporting a Vulnerability

Prosím neotvírejte veřejné issue s bezpečnostním problémem.

Preferované cesty hlášení:

1. GitHub: použijte "Report a vulnerability" (Security advisories), pokud je v repozitáři dostupné.
2. E-mail: napište na `rezervace@ppstudio.cz` a do předmětu uveďte `[SECURITY]`.

Užitečné informace v reportu:

- stručný popis problému a dopad
- kroky k reprodukci / PoC (pokud je to bezpečné)
- kde v kódu nebo URL se to projevuje
- návrh opravy (pokud máte)

## Safe Testing

- Netestujte na produkci destruktivně (DoS, masové skenování, exfiltrace dat).
- Pokud je nutné ověřit přístup k datům, dělejte to na minimálním vzorku a bez ukládání.

## Implementace

- CSRF tokeny, secure session cookie parametry, request/rate-limit pomocné funkce a audit bezpečnostních událostí jsou zapouzdřené ve třídách v `src/Security/`.
- Public site lock pro web a reservation antispam (honeypot, jednorázové tokeny, rate-limit a audit log) řeší `PublicSiteLockService` a `ReservationAntispamService`.
- Globální bezpečnostní helpery byly z projektu odstraněné; nové call-sitey mají používat `ppstudioSecurityFacade()` nebo přímé služby v `src/Security/`.
