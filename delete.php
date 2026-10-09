<?php 
// Pieslēdzam kopīgās funkcijas un datubāzi. Neielogotu lietotāju pārsūta uz ielogošanos
require 'includes/functions.php'; need_login();

// Aptaujas ID nāk no formas (POST) sākumlapā. (int) pārvērš to skaitlī drošības dēļ
$id = (int)($_POST['id'] ?? 0);

// Skaitām, cik atbilžu ir šai aptaujai
$c = $pdo->prepare('SELECT COUNT(*) FROM responses WHERE survey_id=?'); $c->execute([$id]);

// Dzēšam tikai tad, ja neviens vēl nav atbildējis (balsošana nav sākta).
// Nosacījums user_id=? nodrošina, ka lietotājs var dzēst tikai savu aptauju,
// nevis svešu, pat ja ievadītu citu ID
if ($c->fetchColumn() == 0) $pdo->prepare('DELETE FROM surveys WHERE id=? AND user_id=?')->execute([$id, me()]);

// Jebkurā gadījumā atgriežamies sākumlapā
header('Location: index.php');