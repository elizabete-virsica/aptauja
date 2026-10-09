# Aptauju veidotājs un rezultātu vizualizators

## 1. Izvēlētā projekta ideja
Izvēlējos ideju **1.4. Aptaujas veidotājs un rezultātu vizualizators**.

## 2. Izmantotie resursi
| Resurss | Ko izmantoju / pielāgoju | Kāpēc izvēlēts |
|---|---|---|
| Chart.js (https://www.chartjs.org/docs/) | Joslu diagramma rezultātu attēlošanai; pielāgoju datus un krāsas | Vienkārša bibliotēka, nav jāraksta zīmēšanas kods |
| PHP `password_hash` (https://www.php.net/manual/en/function.password-hash.php) | Paroļu drošai glabāšanai | Standarta, droša metode, nevar uzzināt paroli no datubāzes |
| PHP `filter_var` (https://www.php.net/manual/en/function.filter-var.php) | E-pasta pārbaude | Gatava funkcija, kas pārbauda e-pasta formātu |
| PHP PDO (https://www.php.net/manual/en/book.pdo.php) | Darbs ar SQLite | Sagatavotie vaicājumi pasargā no SQL injekcijām |
| W3Schools PHP sesijas (https://www.w3schools.com/php/php_sessions.asp) | Lietotāja autorizācija | Skaidri piemēri |

## 3. Validācijas noteikumi
1. **Aptaujas derīguma termiņš** (`check_date`): nevar būt pagātnē un nevar būt tālāk par 1 gadu. Pamatojums: aptauja ar pagātnes termiņu nav izmantojama, bet pārāk garš periods nav saprātīgs (aptauju dati noveco).
2. **Jautājumu un atbilžu garums** (`len_ok`): nosaukums 5-100, jautājums 5-200, atbilžu variants 1-100, brīvā teksta atbilde 1-500 rakstzīmes. Pamatojums: aizsargā datubāzi no pārāk liela datu apjoma un tukšiem ierakstiem.
3. **E-pasta validācija** (`email_ok`): izmanto `filter_var(FILTER_VALIDATE_EMAIL)`, kas seko RFC 5322 vienkāršotajai formai, garums līdz 254 rakstzīmēm (RFC 5321). E-pastu prasa tikai tad, ja autors to ieslēdz. Tā ir personas dati, tāpēc to apstrādei piemēro VDAR (GDPR, Regula 2016/679) principu par datu minimizēšanu.
4. **Lietotājvārds un parole**: lietotājvārds 3-20 simboli (burti, cipari, _), parole vismaz 8 simboli un glabājas kā hash.
5. **Atbilžu pārbaude serverī**: izvēles jautājumiem atbilde drīkst būt tikai viens no esošajiem variantiem.

Implementācija: funkcijas `includes/functions.php`, izmantotas failos `create.php`, `survey.php`, `auth.php`.

## 4. Programmas struktūra
- `includes/db.php` - datubāzes savienojums un tabulu izveide (loģika/dati)
- `includes/functions.php` - validācija un kopīgās funkcijas
- `index.php` - aptauju saraksts
- `auth.php`, `logout.php` - reģistrācija, ielogošanās
- `create.php` - aptaujas izveide un rediģēšana
- `survey.php` - aptaujas aizpildīšana (respondents)
- `results.php` - rezultāti ar diagrammām (autorizēts autors)
- `export.php` - CSV eksports
- `delete.php` - aptaujas dzēšana
- `style.css` - izskats

## 5. Palaišanas instrukcija
1. Jāuzstāda PHP 8 ar `pdo_sqlite` (piem., XAMPP vai `brew install php`).
2. Mapē ar projektu terminālī: `php -S localhost:8000`
3. Pārlūkā atver `http://localhost:8000`.
4. Reģistrējies, ielogojies un izveido aptauju. Datubāze izveidojas automātiski.
5. Funkcijas: izveidot aptauju (3 jautājumu tipi), aizpildīt kā respondents, skatīt diagrammas, noteikt termiņu, eksportēt CSV, rediģēt/dzēst (tikai kamēr nav atbilžu).

Koda repozitorijs: *(ievieto saiti)*
