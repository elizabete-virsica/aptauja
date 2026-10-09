<?php 
// Pieslēdzam kopīgās funkcijas un datubāzi, tad izdrukājam lapas augšdaļu
require 'includes/functions.php'; top('Aptaujas');

// Viens vaicājums paņem visas aptaujas kopā ar autora vārdu (JOIN savieno tabulas surveys un users).
// Iekšējais vaicājums (SELECT COUNT(*)) katrai aptaujai saskaita atbilžu skaitu kā cnt.
// ORDER BY s.id DESC rāda jaunākās aptaujas pirmās
$rows = $pdo->query("SELECT s.*, u.username, (SELECT COUNT(*) FROM responses r WHERE r.survey_id=s.id) AS cnt
                     FROM surveys s JOIN users u ON u.id=s.user_id ORDER BY s.id DESC")->fetchAll();

echo '<h2>Aptaujas</h2>';

// Ja datubāzē aptauju vēl nav, parādām paziņojumu
if (!$rows) echo '<p>Aptauju vēl nav.</p>';

echo '<ul>';

// Ejam cauri katrai aptaujai un izdrukājam vienu saraksta punktu
foreach ($rows as $s) 
{
    // Pārbaudām, vai termiņš ir beidzies: termiņš ir diena, tāpēc pievienojam 23:59:59,
    // lai aptauja būtu derīga visu pēdējo dienu
    $expired = strtotime($s['expires'] . ' 23:59:59') < time();

    // Aptaujas nosaukums, autors, termiņš un atbilžu skaits. h() aizsargā no kaitīga koda
    echo '<li><b>' . h($s['title']) . '</b> (autors: ' . h($s['username']) . ', līdz ' . h($s['expires']) . ($expired ? ', beigusies' : '') . ', atbildes: ' . $s['cnt'] . ')<br>';

    // Aizpildīt var tikai aptauju, kuras termiņš nav beidzies
    if (!$expired) echo '<a href="survey.php?id=' . $s['id'] . '">Aizpildīt</a> ';

    // Šīs saites redz tikai aptaujas autors (ielogotā lietotāja ID sakrīt ar aptaujas īpašnieku)
    if (me() == $s['user_id']) 
    {
        // Rezultāti (diagrammas) un CSV eksports
        echo ' | <a href="results.php?id=' . $s['id'] . '">Rezultāti</a> | <a href="export.php?id=' . $s['id'] . '">CSV</a>';

        // Rediģēt un dzēst drīkst tikai tad, ja neviens vēl nav atbildējis (cnt = 0).
        // Dzēšana ir forma ar POST un apstiprinājuma logu (confirm), lai neizdzēstu nejauši
        if ($s['cnt'] == 0) 
        {
            echo ' | <a href="create.php?id=' . $s['id'] . '">Rediģēt</a> <form method="post" action="delete.php" style="display:inline" onsubmit="return confirm(\'Dzēst aptauju?\')">'
               . '<input type="hidden" name="id" value="' . $s['id'] . '"><button>Dzēst</button></form>';
        }
    }
    echo '</li>';
}
// Aizveram sarakstu un lapu
echo '</ul>'; bottom();