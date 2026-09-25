# Spoedverhuur — CORE child-app (Laravel 12, SQLite)

Dienstrooster spoedverhuur/storingsdiensten Boels Industrial: rooster importeren (Excel), inzien, ruilen (beide partijen bevestigen, ook via maillink), automatische mails (aankondiging week ervoor, herinnering op maandag, weeklijst naar telefooncentrale Multiline, maandoverzicht vergoedingen naar HR/payroll) en een maandelijkse Excel volgens het sjabloon "24 uurs week vergoedingen".

## Vaste afspraken
- Login uitsluitend via Boels CORE (cookie-relay, `app/Services/CoreSso.php`, middleware `core`). Rollen uit CORE (slug `spoedverhuur`): `medewerker`, `manager`, `admin`; middleware `rol:admin` op beheer. Super-admins hebben alles.
- Medewerkers komen uit de CORE-medewerkerkaart (`/api/employees`): naam, e-mail, telefoon, personeelsnummer. Handmatige velden (`*_handmatig`) alleen als terugval voor wie niet in CORE staat. `Medewerker::emailEffectief()` etc.
- Dagen: 1 = maandag … 7 = zondag. Week = ISO-week. Maand van een week = maand waarin de donderdag valt (`Weekindeling::maandVanWeek`, instelling `maandregel`).
- `diensten` = gepland rooster; `toewijzingen` = effectief rooster na bevestigde ruilingen (`Toewijzingen::herbereken`). Mails, Multiline-lijst en vergoedingen lezen ALLEEN `toewijzingen`.
- Vergoeding = weekbedrag/7 × dagen, 2 decimalen (`Vergoeding`). Alleen dienstsoorten met `betaald`; `vast` (tweede lijn h&h) nooit.
- Alle mail via `MailDienst::verstuur(soort, referentie, aan, onderwerp, tekst|html, opties)`: idempotent op (soort, referentie), gelogd in `mail_taken`, testmodus stuurt alles naar `test_adres`. Teksten/onderwerpen/adressen zijn instellingen (`MailDienst::teksten()`, `setting()`).
- `Planner::draai()` (elke minuut via scheduler) doet exact hetzelfde als de "Verstuur nu"-knoppen, alleen op de ingestelde momenten. Cron in DirectAdmin: `* * * * * php .../laravel_app/artisan schedule:run`.
- Audit: `audit('actie', 'onderwerp', [...])` bij elke wijziging (rooster, ruilingen, instellingen, verzendingen).
- Blade: nooit `@json()` met komma's; `@endif`/`@if` altijd met spatie ertussen. Views in het Nederlands, Bootstrap 5, layout `layouts.app` (Boels-oranje, witte B).
- Lokaal testen: `.env` met `CORE_DEV_FAKE_USER=true` (nepgebruiker met alle rollen + nep-medewerkers), `php artisan serve`, `php artisan test`.
- Deploy: push naar `main` → GitHub Action bouwt release-zips → eerste keer `https://databasehub.sorai.nl/__deploy-child.php?k=<DEPLOY_SECRET>&app=spoedverhuur`, daarna `https://spoedverhuur.sorai.nl/__pull_deploy.php?k=<DEPLOY_SECRET>`; migraties `__migrate.php?k=`, log `__log.php?k=`.
