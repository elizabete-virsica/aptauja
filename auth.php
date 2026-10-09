<?php
// Pieslēdzam kopīgās funkcijas (tās pašas pieslēdz datubāzi un sāk sesiju)
require 'includes/functions.php';

// $err ir kļūdu saraksts. $mode nosaka, vai lapa ir reģistrācija vai ielogošanās:
// ja adresē ir ?mode=register, tad reģistrācija, citādi ielogošanās
$err = []; $mode = ($_GET['mode'] ?? '') === 'register' ? 'register' : 'login';

// Šis bloks darbojas tikai tad, kad lietotājs ir nospiedis pogu (forma nosūtīta ar POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') 
{
    // Paņemam ievadītos datus no formas. trim noņem liekās atstarpes lietotājvārdam
    $u = trim($_POST['username']); $p = $_POST['password'];

    // ---------- REĢISTRĀCIJA ----------
    if ($mode === 'register')
    {
        // Validācija: lietotājvārds drīkst būt tikai burti, cipari un _, garums 3-20
        // (preg_match pārbauda tekstu pēc šablona)
        if (!preg_match('/^[A-Za-z0-9_]{3,20}$/', $u)) $err[] = 'Lietotājvārds: 3-20 burti, cipari vai _.';

        // Validācija: parole vismaz 8 simboli
        if (mb_strlen($p) < 8) $err[] = 'Parolei jābūt vismaz 8 simbolus garai.';

        // Ja kļūdu nav, saglabājam lietotāju datubāzē
        if (!$err) 
        {
            try
            {
                // prepare ar ? zīmēm ir "sagatavotais vaicājums", kas aizsargā pret SQL injekciju.
                // password_hash pārvērš paroli garā šifrētā tekstā (hash), tāpēc pašu paroli
                // datubāzē nekad neglabājam
                $pdo->prepare('INSERT INTO users(username,password) VALUES(?,?)')->execute([$u, password_hash($p, PASSWORD_DEFAULT)]);

                // Pēc veiksmīgas reģistrācijas parādām ielogošanās formu
                $mode = 'login'; $ok = 'Reģistrācija veiksmīga, vari ielogoties.';

            } 
            // Kolonna username ir UNIQUE, tāpēc ja vārds jau ir, datubāze met kļūdu, ko mēs noķeram
            catch (PDOException $e) { $err[] = 'Šāds lietotājvārds jau eksistē.'; }
        }
    } 
    // ---------- IELOGOŠANĀS ----------
    else 
    {
        // Meklējam lietotāju datubāzē pēc lietotājvārda
        $st = $pdo->prepare('SELECT * FROM users WHERE username=?'); $st->execute([$u]); $row = $st->fetch();

        // Ja lietotājs atrasts un ievadītā parole atbilst saglabātajam hash, ielogojam
        if ($row && password_verify($p, $row['password'])) 
        {
            // Jauns sesijas ID pēc ielogošanās, lai neviens nevarētu pārņemt veco sesiju
            session_regenerate_id(true);

            // Sesijā atceramies, kurš lietotājs ir ielogojies (me() funkcija lasa šo vērtību)
            $_SESSION['uid'] = $row['id']; $_SESSION['name'] = $row['username'];

            // Pārsūtām uz sākumlapu un apturam skripta darbību
            header('Location: index.php'); exit;
        }
        // Pateicam vienu vispārīgu kļūdu, lai neizpaustu, vai vārds eksistē
        $err[] = 'Nepareizs lietotājvārds vai parole.';
    }
}

// ---------- LAPAS IZVADE ----------
// top() ir funkcija no functions.php, kas izdrukā lapas augšdaļu
top($mode === 'login' ? 'Ielogoties' : 'Reģistrēties');

echo '<h2>' . ($mode === 'login' ? 'Ielogoties' : 'Reģistrēties') . '</h2>';

// Veiksmīgas reģistrācijas paziņojums (zaļš)
if (isset($ok)) echo '<p class="ok">' . $ok . '</p>';

// Izdrukā kļūdu sarakstu (sarkans)
errors($err); ?>

<!-- Forma bez action nosūta datus uz to pašu lapu, tāpēc ?mode=register saglabājas -->
<form method="post">
<label>Lietotājvārds</label><input name="username" required>
<label>Parole</label><input type="password" name="password" required>
<button>Turpināt</button>
</form>

<!-- Saite pārslēdz starp reģistrāciju un ielogošanos -->
<p><?= $mode === 'login' ? '<a href="auth.php?mode=register">Nav konta? Reģistrējies</a>' : '<a href="auth.php">Jau ir konts? Ielogojies</a>' ?></p>
<?php bottom();