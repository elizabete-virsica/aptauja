<?php 
// Pieslēdzam kopīgās funkcijas un datubāzi. need_login() neielogotu lietotāju pārsūta uz auth.php
require 'includes/functions.php'; need_login();

// Ja adresē ir ?id=5, tad aptauju rediģē, ja nav, tad veido jaunu (id būs 0).
// (int) pārvērš vērtību skaitlī, lai nevarētu ielikt kaitīgu tekstu
$id = (int)($_GET['id'] ?? 0);

// Sākuma vērtības tukšai formai: $s ir aptaujas dati, $qs ir jautājumu saraksts
$s = ['title' => '', 'expires' => '', 'collect_email' => 0]; $qs = [];

// ---------- REDIĢĒŠANAS REŽĪMS: ielādējam esošo aptauju ----------
if ($id) 
{
    // Ņemam aptauju tikai tad, ja tā pieder šim lietotājam (user_id=me()),
    // tāpēc svešu aptauju rediģēt nevar
    $st = $pdo->prepare('SELECT * FROM surveys WHERE id=? AND user_id=?');
     $st->execute([$id, me()]); $s = $st->fetch();

    // die() apstādina lapu un parāda ziņu
    if (!$s) die('Aptauja nav atrasta.');

    // Skaitām, cik ir atbilžu. Ja kāds jau atbildējis, rediģēt nedrīkst
    $c = $pdo->prepare('SELECT COUNT(*) FROM responses WHERE survey_id=?'); 
    $c->execute([$id]);

    if ($c->fetchColumn() > 0) die('Aptauju vairs nevar rediģēt, jo balsošana ir sākta.');

    // Ielādējam aptaujas jautājumus, lai tos parādītu formā
    $q = $pdo->prepare('SELECT * FROM questions WHERE survey_id=?'); 
    $q->execute([$id]); $qs = $q->fetchAll();
}
$err = [];

// ---------- FORMAS SAGLABĀŠANA (kad nospiests "Saglabāt") ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') 
{
    // Paņemam aptaujas laukus no formas. Izvēles rūtiņa (checkbox) POST nonāk tikai tad,
    // ja ir atķeksēta, tāpēc izmantojam isset
    $s = ['title' => trim($_POST['title']), 'expires' => $_POST['expires'], 'collect_email' => isset($_POST['collect_email']) ? 1 : 0];

    // Validācija: nosaukums 5-100 rakstzīmes (len_ok no functions.php)
    if (!len_ok($s['title'], 5, 100)) $err[] = 'Nosaukumam jābūt 5-100 rakstzīmju garam.';

    // Validācija: termiņš nav pagātnē un nav tālāk par 1 gadu.
    // check_date atgriež kļūdas tekstu vai null, tāpēc piešķiram un pārbaudām vienlaikus
    if ($e = check_date($s['expires'])) $err[] = $e;

    $qs = [];

    // Ejam cauri visiem jautājumiem no formas (name="q[]" nāk kā masīvs).
    // $i ir jautājuma kārtas numurs, $t ir jautājuma teksts
    foreach ($_POST['q'] ?? [] as $i => $t) 
    {
        $n = $i + 1; $type = $_POST['type'][$i] ?? 'text';

        // Atļaujam tikai trīs zināmos tipus, citādi uzliekam 'text'
        if (!in_array($type, ['text', 'single', 'multi'])) $type = 'text';

        // Atbilžu varianti ir ierakstīti katrs jaunā rindā. explode sadala pa rindām,
        // array_map('trim') noņem atstarpes, array_filter izmet tukšās rindas
        $opts = array_values(array_filter(array_map('trim', explode("\n", $_POST['opts'][$i] ?? ''))));

        // Validācija: jautājums 5-200 rakstzīmes
        if (!len_ok($t, 5, 200)) $err[] = "$n. jautājumam jābūt 5-200 rakstzīmju garam.";

        // Izvēles jautājumiem (viena/vairākas izvēles) vajag vismaz 2 variantus
        if ($type !== 'text') 
        {
            if (count($opts) < 2) $err[] = "$n. jautājumam vajag vismaz 2 atbilžu variantus.";

            // Katrs variants drīkst būt max 100 rakstzīmes
            foreach ($opts as $o) if (!len_ok($o, 1, 100)) $err[] = "$n. jautājuma atbilde ir pārāk gara (max 100).";

        } 
        // Brīvā teksta jautājumam variantu nav
        else $opts = [];

        // Saglabājam jautājumu sarakstā. Variantus pārvēršam par JSON tekstu,
        // lai tos varētu glabāt vienā datubāzes laukā
        $qs[] = ['text' => trim($t), 'type' => $type, 'options' => json_encode($opts, JSON_UNESCAPED_UNICODE)];
    }
    // Vismaz 1 un ne vairāk kā 30 jautājumi
    if (!$qs) $err[] = 'Pievieno vismaz vienu jautājumu.';

    if (count($qs) > 30) $err[] = 'Maksimums 30 jautājumi.';

    // Ja kļūdu nav, saglabājam datubāzē
    if (!$err) 
    {
        if ($id) 
        {
            // Rediģēšana: atjaunojam aptauju un izdzēšam vecos jautājumus,
            // lai zemāk ierakstītu jaunos
            $pdo->prepare('UPDATE surveys SET title=?,expires=?,collect_email=? WHERE id=?')->execute([$s['title'], $s['expires'], $s['collect_email'], $id]);
            $pdo->prepare('DELETE FROM questions WHERE survey_id=?')->execute([$id]);
        } 
        else 
        {
            // Jauna aptauja: ierakstām un paņemam tikko izveidotā ieraksta ID
            $pdo->prepare('INSERT INTO surveys(user_id,title,expires,collect_email) VALUES(?,?,?,?)')->execute([me(), $s['title'], $s['expires'], $s['collect_email']]);
            $id = $pdo->lastInsertId();
        }

        // Ierakstām visus jautājumus, piesaistot tos aptaujai (survey_id)
        $ins = $pdo->prepare('INSERT INTO questions(survey_id,text,type,options) VALUES(?,?,?,?)');
        foreach ($qs as $q) $ins->execute([$id, $q['text'], $q['type'], $q['options']]);

        // Pārsūtām uz sākumlapu
        header('Location: index.php'); exit;
    }
}
// ---------- LAPAS IZVADE ----------
top('Aptauja'); echo '<h2>' . ($id ? 'Rediģēt' : 'Jauna') . ' aptauja</h2>'; errors($err); ?>

<!-- h() aizsargā ievadītos laukus no kaitīga koda. Ja forma atgriežas ar kļūdām,
     lauki paliek aizpildīti -->
<form method="post">
  <label>Nosaukums</label><input name="title" value="<?= h($s['title']) ?>" required>
  <label>Derīga līdz</label><input type="date" name="expires" value="<?= h($s['expires']) ?>" required>
  <label><input type="checkbox" name="collect_email" <?= $s['collect_email'] ? 'checked' : '' ?>> Prasīt respondenta e-pastu</label>
  <!-- Šeit JavaScript ieliks jautājumu blokus -->
  <h3>Jautājumi</h3><div id="qs"></div>
  <button type="button" onclick="addQ()">+ Pievienot jautājumu</button> <button>Saglabāt</button>
</form>

<script>
// Funkcija pievieno formai vienu jautājuma bloku. Parametri ir sākuma vērtības
// (tukši jaunam jautājumam, aizpildīti rediģējot)
function addQ(text = '', type = 'text', opts = '') 
{
  const d = document.createElement('div'); d.className = 'q';
  // Vārdi q[], type[], opts[] ar [] nozīmē, ka PHP saņems masīvus (viens elements katram jautājumam)
  d.innerHTML = '<label>Jautājums</label><input name="q[]">' +
    '<label>Tips</label><select name="type[]"><option value="text">Brīvs teksts</option><option value="single">Viena izvēle</option><option value="multi">Vairākas izvēles</option></select>' +
    '<label>Atbilžu varianti (katrs jaunā rindā)</label><textarea name="opts[]" rows="3"></textarea>' +
    '<button type="button" onclick="this.parentNode.remove()">Dzēst</button>';
  // Vērtības liekam ar .value (nevis iekš HTML teksta), lai īpašās rakstzīmes nesalauztu lapu
  d.querySelector('input').value = text; d.querySelector('select').value = type; d.querySelector('textarea').value = opts;
  document.getElementById('qs').appendChild(d);
}
// PHP nodod JavaScript esošos jautājumus (rediģēšanas gadījumā) kā JSON masīvu
const init = <?= json_encode(array_map(fn($q) => [$q['text'], $q['type'], implode("\n", json_decode($q['options'], true) ?: [])], $qs), JSON_UNESCAPED_UNICODE) ?>;
// Ja jautājumi ir, parāda tos, ja nav, parāda vienu tukšu bloku
init.length ? init.forEach(a => addQ(...a)) : addQ();
</script>
<?php bottom();