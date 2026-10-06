<?php
// 1. Forza il server a mostrare qualsiasi cosa succeda dopo
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<div style='background:#0275d8; color:white; padding:15px; font-family:sans-serif;'>";
echo "🔍 [DEBUG SYSTEM] Avvio il caricamento forzato di calendario.php...<br></div>";

// 2. Richiama il calendario passando il parametro del corso
$_GET['idi'] = 5; 
include('calendario.php');

echo "<div style='background:#5cb85c; color:white; padding:15px; font-family:sans-serif; margin-top:20px;'>";
echo "🎉 [DEBUG SYSTEM] Se vedi questo messaggio, calendario.php non ha errori di sintassi!</div>";
?>