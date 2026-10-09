<?php 
// Pieslēdzam kopīgās funkcijas un datubāzi. Neielogotu lietotāju pārsūta uz ielogošanos
require 'includes/functions.php'; need_login();

// Aptaujas ID nāk no adreses (export.php?id=5), pārvēršam to skaitlī drošības dēļ
$id = (int)($_GET['id'] ?? 0);

// Ņemam aptauju tikai tad, ja tā pieder šim lietotājam, tāpēc svešas aptaujas datus eksportēt nevar
$st = $pdo->prepare('SELECT * FROM surveys WHERE id=? AND user_id=?'); 
$st->execute([$id, me()]); $s = $st->fetch();

if (!$s) die('Nav piekļuves.');

// Ielādējam aptaujas jautājumus, tie kļūs par CSV kolonnu virsrakstiem
$q = $pdo->prepare('SELECT * FROM questions WHERE survey_id=?'); 
$q->execute([$id]); $qs = $q->fetchAll();

// Šīs galvenes pasaka pārlūkam, ka atbilde ir CSV fails, ko lejupielādēt
// (nevis lapa, ko parādīt), un norāda faila nosaukumu, piem., aptauja_5.csv
header('Content-Type: text/csv; charset=utf-8'); 
header('Content-Disposition: attachment; filename="aptauja_' . $id . '.csv"');

// php://output nozīmē "rakstīt tieši uz lejupielādējamo failu".
// BOM ir īpašas rakstzīmes faila sākumā, lai Excel pareizi rāda latviešu burtus
$out = fopen('php://output', 'w'); fwrite($out, "\xEF\xBB\xBF"); // BOM, lai Excel pareizi rāda latviešu burtus

// Pirmā rinda: kolonnu virsraksti. array_column paņem visu jautājumu tekstus no saraksta.
// fputcsv pats pareizi ieliek pēdiņas un atdala ar komatiem
fputcsv($out, array_merge(['Datums', 'E-pasts'], array_column($qs, 'text')));

// Ielādējam visas aptaujas atbildes (katrs respondents ir viens ieraksts tabulā responses).
// Sagatavojam arī vaicājumu, kas vēlāk ņems vienu respondenta atbildi uz vienu jautājumu
$r = $pdo->prepare('SELECT * FROM responses WHERE survey_id=?'); $r->execute([$id]);
$a = $pdo->prepare('SELECT value FROM answers WHERE response_id=? AND question_id=?');

// Katram respondentam veidojam vienu CSV rindu
foreach ($r->fetchAll() as $resp) 
{
    // Rindas sākums: atbildes datums un e-pasts
    $row = [$resp['created'], $resp['email']];

    // Katram jautājumam atrodam šī respondenta atbildi. Ja ir vairākas izvēles,
    // implode savieno tās vienā šūnā ar ; (piem., "Jā; Nē")
    foreach ($qs as $k) { $a->execute([$resp['id'], $k['id']]); $row[] = implode('; ', $a->fetchAll(PDO::FETCH_COLUMN)); }

    // Ierakstām gatavo rindu CSV failā
    fputcsv($out, $row);
}