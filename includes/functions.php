<?php
// piesledzam datubazes failu(db.php)
require __DIR__ . '/db.php';

//aizsardziba: parverss ipasas rakstzimes drosa teksta, lai leitotajs nevaretu ievcadit kodu
function h($s) 
{ 
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); 
}
//atgriez ielogota lietotajas ID no sesijas
function me() 
{ 
    return $_SESSION['uid'] ?? null; 
}
//ja nav ielogojoes, parsuta uz ielogosanas lapu
function need_login() 
{ 
    if (!me()) { header('Location: auth.php'); 
    exit; } 
}

// Teksta garuma validācija (min un max rakstzīmju skaits)
function len_ok($s, $min, $max) 
{ 
    //pareiiz skaita ar lv burtus
    $l = mb_strlen(trim($s)); //trim nonem atstarpes sakuma un beigas
    return $l >= $min && $l <= $max; 
}

// Derīguma termiņa validācija: nav pagātnē un nav vēlāk par 1 gadu
function check_date($d) 
{
    //parvers datumu sekundes
    $t = strtotime($d);

    if (!$t) return 'Derīguma termiņš nav norādīts vai ir nederīgs.';
    if ($t < strtotime('today')) 
        return 'Derīguma termiņš nevar būt pagātnē.';

    if ($t > strtotime('+1 year')) 
        return 'Derīguma termiņš nevar būt tālāk par 1 gadu.';

    return null;
}

// E-pasta validācija (PHP filter_var, balstīts uz RFC 5322 vienkāršoto formu)
function email_ok($e) 
{ 
    return strlen($e) <= 254 && filter_var($e, FILTER_VALIDATE_EMAIL) !== false; 
}

//izdruka lapas augsdalu (html sakumu)
function top($title) 
{
    echo '<!DOCTYPE html><html lang="lv">
        <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width,initial-scale=1">';   
    echo '<title>' . h($title) . '</title><link rel="stylesheet" href="style.css">
        </head>
        <body>
        <header>
        <a href="index.php"><b>Aptauju veidotājs</b></a><nav>';

    //ja ielogojas 
    if (me()) echo '<a href="create.php">Jauna aptauja</a><span>' 
        . h($_SESSION['name']) . '</span><a href="logout.php">Iziet</a>';

    else echo '<a href="auth.php">Ielogoties</a>';
    echo '</nav></header><main>';
}
function bottom() 
{ 
    echo '</main></body></html>'; 
}
function errors($err) 
{ 
    foreach ($err as $e) echo '<p class="err">' . h($e) . '</p>'; 
}
