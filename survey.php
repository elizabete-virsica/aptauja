<?php 
// Pieslēdzam kopīgās funkcijas un datubāzi. Šeit need_login() nav, jo aptauju drīkst aizpildīt jebkurš (arī neielogots)
require 'includes/functions.php';

// Aptaujas ID nāk no adreses (survey.php?id=5), pārvēršam to skaitlī drošības dēļ
$id = (int)($_GET['id'] ?? 0);

// Ielādējam aptauju no datubāzes
$st = $pdo->prepare('SELECT * FROM surveys WHERE id=?'); 
$st->execute([$id]); $s = $st->fetch();

if (!$s) die('Aptauja nav atrasta.');

top($s['title']);

// Pārbaudām derīguma termiņu. Pievienojam 23:59:59, lai aptauja būtu derīga visu pēdējo dienu.
// Ja termiņš beidzies, parādām paziņojumu un apturam lapu (exit)
if (strtotime($s['expires'] . ' 23:59:59') < time()) 
    { 
        echo '<p class="err">Šīs aptaujas derīguma termiņš ir beidzies.</p>'; 
        bottom();
        exit; 
    }

// Ielādējam aptaujas jautājumus
$q = $pdo->prepare('SELECT * FROM questions WHERE survey_id=?'); 
$q->execute([$id]); $qs = $q->fetchAll();

// $err ir kļūdu saraksts, $done kļūst true, kad atbildes ir veiksmīgi saglabātas
$err = []; $done = false;

// ---------- ATBILŽU SAGLABĀŠANA (kad nospiests "Iesniegt") ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') 
    {
        // $vals būs saraksts "jautājuma ID => atbildes", ko saglabāsim datubāzē
        $email = trim($_POST['email'] ?? ''); $vals = [];

        // E-pastu pārbaudām tikai tad, ja aptaujas autors to ir ieslēdzis
        if ($s['collect_email'] && !email_ok($email)) 
            $err[] = 'Nederīga e-pasta adrese.';

        // Ejam cauri katram jautājumam un pārbaudām atbildi uz to.
        // Atbildes nāk kā a[jautājuma_id] no formas
        foreach ($qs as $k) 
        {
            $a = $_POST['a'][$k['id']] ?? ''; $opts = json_decode($k['options'], true);

            // Brīvā teksta atbilde: obligāta, 1-500 rakstzīmes
            if ($k['type'] === 'text') 
            {
                if (!len_ok($a, 1, 500)) $err[] = 'Atbilde uz "' . $k['text'] 
                    . '" ir obligāta (max 500 rakstzīmes).';
                $vals[$k['id']] = [trim($a)];
            } 
            else 
            {
                // Izvēles jautājums: pārvēršam par masīvu (vienai izvēlei nāk viena vērtība,
                // vairākām izvēlēm nāk masīvs). Atbilde ir kļūdaina, ja:
                //  - nekas nav izvēlēts (!$a)
                //  - vienas izvēles jautājumam izvēlētas nevis tieši 1 atbildes
                //  - ir atbilde, kuras nav jautājuma variantos (array_diff), piemēram, kāds
                //    ir pārrakstījis formu pārlūkā
                $a = (array)$a;
                if (!$a || ($k['type'] === 'single' && count($a) != 1) 
                        || array_diff($a, $opts)) $err[] = 'Izvēlies derīgu atbildi: "' . $k['text'] . '".';
                $vals[$k['id']] = $a;
            }
        }

        // Ja kļūdu nav, saglabājam datubāzē
        if (!$err) 
        {
            // Vispirms ierakstām respondentu (tabula responses): e-pastu (ja prasīts) un datumu
            $pdo->prepare('INSERT INTO responses(survey_id,email,created) VALUES(?,?,?)')
                 ->execute([$id, $s['collect_email'] ? $email : null, date('Y-m-d H:i:s')]);

            // Paņemam tikko izveidotā respondenta ID un ar to ierakstām atbildes (tabula answers).
            // Ja ir vairākas izvēles, katra atbilde tiek saglabāta kā atsevišķa rinda
            $rid = $pdo->lastInsertId(); 
            $ins = $pdo->prepare('INSERT INTO answers(response_id,question_id,value) VALUES(?,?,?)');
            foreach ($vals as $qid => $list) 
                foreach ($list as $v) $ins->execute([$rid, $qid, $v]);
            $done = true;
        }
    }

echo '<h2>' . h($s['title']) . '</h2>';

// Ja atbildes saglabātas, parādām paldies un neizdrukājam formu vēlreiz
if ($done) 
    { 
        echo '<p class="ok">Paldies, tava atbilde ir saglabāta!</p>'; 
        bottom(); 
        exit; 
    }
errors($err); 
?>

<form method="post">

<!-- E-pasta lauks parādās tikai tad, ja autors to ir ieslēdzis.
     Ja forma atgriežas ar kļūdām, ievadītais e-pasts paliek (h() aizsargā no kaitīga koda) -->
<?php if ($s['collect_email']): ?>
<label>E-pasts</label>
<input name="email" value="<?= h($_POST['email'] ?? '') ?>">
<?php endif;

// Izdrukājam katru jautājumu
foreach ($qs as $k) 
{
    echo '<div class="q"><b>' . h($k['text']) . '</b><br>';

    // Brīvā teksta jautājumam rādām teksta lauku
    if ($k['type'] === 'text') 
        echo '<textarea name="a[' . $k['id'] . ']" rows="3">' . h($_POST['a'][$k['id']] ?? '') . '</textarea>';

    // Izvēles jautājumam rādām katru variantu: viena izvēle ir radio poga (var izvēlēties vienu),
    // vairākas izvēles ir checkbox (var izvēlēties vairākas). Checkbox vārdam beigās ir [],
    // lai PHP saņemtu atbildes kā masīvu
    else foreach (json_decode($k['options'], true) as $o) 
    {
        $t = $k['type'] === 'single' ? 'radio' : 'checkbox'; 
        $n = 'a[' . $k['id'] . ']' . ($t === 'checkbox' ? '[]' : '');

        echo '<label class="opt"><input type="' . $t . '" name="' . $n . '" value="' . h($o) . '"> ' . h($o) . '</label>';
    }
    echo '</div>';
} 
?>
<button>Iesniegt</button></form>
<?php bottom();