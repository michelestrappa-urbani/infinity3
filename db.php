<?php
$host = "localhost";
$user = "strappa";
$pass = ""; // Su Altervista la password di default per la connessione interna è vuota
$dbname = "my_strappa";

$conn = mysqli_connect($host, $user, $pass, $dbname);

if (!$conn) {
    die("Connessione fallita: " . mysqli_connect_error());
}

// Imposta l'encoding corretto per accenti e caratteri speciali
mysqli_set_charset($conn, "utf8mb4");
?>