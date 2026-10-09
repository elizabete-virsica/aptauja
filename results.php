<?php 
// Pieslēdzam kopīgās funkcijas un datubāzi. Neielogotu lietotāju pārsūta uz ielogošanos
require 'includes/functions.php'; need_login();

// Aptaujas ID nāk no adreses (results.php?id=5), pārvēršam to skaitlī drošības dēļ
$id = (int)($_GET['id'] ?? 0);

// Ņemam aptauju tikai tad, ja tā pieder šim lietotājam (user_id=me()),
// tāpēc svešas aptaujas rezultātus redzēt nevar
$st = $pdo->prepare('SELECT * FROM surveys WHERE id=? AND user_id=?'); 
$st->execute([$id, me()]); $s = $st->fetch();

if (!$s) die('Nav piekļuves.');

// Lapas augšdaļa un aptaujas nosaukums
top('Rezultāti'); echo '<h2>' . h($s['title']) . '</h2>';

// Ielādējam visus aptaujas jautājumus
$q = $pdo->prepare('SELECT * FROM questions WHERE survey_id=?'); 
$q->execute([$id]);

// Šajā sarakstā krāsim datus diagrammām, ko pēc tam uzzīmēs JavaScript
$charts = [];

// Ejam cauri katram jautājumam un izdrukājam tā rezultātus
foreach ($q->fetchAll() as $k) 
{
    echo '<div class="q"><b>' . h($k['text']) . '</b>';

    // Paņemam visas atbildes uz šo jautājumu un saskaitām, cik reižu katra atbilde atkārtojas
    // (GROUP BY value grupē vienādas atbildes, COUNT(*) c ir to skaits)
    $a = $pdo->prepare('SELECT value, COUNT(*) c FROM answers WHERE question_id=? GROUP BY value'); $a->execute([$k['id']]); $rows = $a->fetchAll();

    // Brīvā teksta jautājumam vienkārši parādām visas atbildes sarakstā
    if ($k['type'] === 'text') { echo '<ul>'; 
        foreach ($rows as $r) echo '<li>' . h($r['value']) . '</li>'; echo '</ul>'; }
    else 
    {
        // Izvēles jautājumam gatavojam diagrammu.
        // $cnt ir saraksts "atbilde => cik reižu izvēlēta".
        // $labels ir visi atbilžu varianti no jautājuma (JSON pārvēršam atpakaļ masīvā)
        $cnt = array_column($rows, 'c', 'value'); $labels = json_decode($k['options'], true);

        // Katram variantam paņemam skaitu (ja neviens neizvēlējās, tad 0, to dara ?? 0).
        // Tā diagrammā parādās arī varianti, kurus neviens neizvēlējās
        $charts[] = ['id' => 'c' . $k['id'], 'labels' => $labels, 'data' => array_map(fn($l) => (int)($cnt[$l] ?? 0), $labels)];

        // Tukša zīmēšanas vieta (canvas), kurā JavaScript ieliks diagrammu
        echo '<canvas id="c' . $k['id'] . '" height="120"></canvas>';
    }
    echo '</div>';
}
// Poga CSV eksportam
echo '<a class="btn" href="export.php?id=' . $id . '">Eksportēt CSV</a>'; ?>

<!-- Chart.js ir gatava bibliotēka diagrammu zīmēšanai, to ielādējam no interneta -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>

<script>
// Katram izvēles jautājumam uzzīmē joslu diagrammu.
// PHP nodod sagatavotos datus ($charts) JavaScript kā JSON masīvu, un forEach ejam tam cauri
<?= json_encode($charts, JSON_UNESCAPED_UNICODE) ?>.forEach(c => new Chart(document.getElementById(c.id), {
    // type: 'bar' ir joslu diagramma, labels ir atbilžu varianti, data ir to skaits
    type: 'bar', data: {labels: c.labels, datasets: [{label: 'Atbilžu skaits', data: c.data, backgroundColor: '#2b5d8a'}]},
    // Y ass sākas no 0 un rāda tikai veselus skaitļus (nevar būt 1.5 atbildes)
    options: {scales: {y: {beginAtZero: true, ticks: {precision: 0}}}}
}));

</script>
<?php bottom();