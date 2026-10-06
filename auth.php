<?php
// auth.php - FIX DEFINITIVO PER IL LOOP DEI REDIRECT
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Identifica in quale pagina si trova l'utente in questo momento
$current_page = basename($_SERVER['PHP_SELF']);

// Se l'utente NON ha la sessione attiva
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    // E NON si trova già nella pagina di login, scatta il reindirizzamento
    if ($current_page !== 'login.php') {
        header("Location: login.php");
        exit();
    }
}
?>