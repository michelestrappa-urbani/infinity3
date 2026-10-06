<?php
include('auth.php');
include('db.php');

if(isset($_POST['salva_registro'])) {
    $idi = (int)$_POST['idi'];
    $ida = (int)$_POST['ida'];

    // Svuota i vecchi dati registrati per questa singola attività (permette la sovrascrittura pulita)
    mysqli_query($conn, "DELETE FROM presenza_formatore_attivita WHERE ida = $ida");
    mysqli_query($conn, "DELETE FROM presenza_classe_attivita WHERE ida = $ida");
    mysqli_query($conn, "DELETE FROM presenza_docentediscente_attivita WHERE ida = $ida");

    // 1. Inserimento Formatori
    if(!empty($_POST['formatori'])) {
        foreach($_POST['formatori'] as $emailf) {
            $emailf = mysqli_real_escape_string($conn, $emailf);
            $modalita = mysqli_real_escape_string($conn, $_POST['mod_formatore'][$emailf]);
            $ore = (float)$_POST['ore_formatore'][$emailf];
            
            mysqli_query($conn, "INSERT INTO presenza_formatore_attivita (ida, emailf, ore_partecipate, modalita) 
                                 VALUES ($ida, '$emailf', $ore, '$modalita')");
        }
    }

    // 2. Inserimento Classi
    if(!empty($_POST['classi'])) {
        foreach($_POST['classi'] as $idc) {
            $idc = (int)$idc;
            $presenti = $_POST['studenti_presenti'][$idc];
            
            // Gestione del valore opzionale (NULL)
            if($presenti === '') {
                $val_presenti = "NULL";
            } else {
                $val_presenti = (int)$presenti;
            }

            mysqli_query($conn, "INSERT INTO presenza_classe_attivita (ida, idc, numero_studenti_presenti) 
                                 VALUES ($ida, $idc, $val_presenti)");
        }
    }

    // 3. Inserimento Docenti Discenti
    if(!empty($_POST['docenti'])) {
        foreach($_POST['docenti'] as $email_dd) {
            $email_dd = mysqli_real_escape_string($conn, $email_dd);
            $modalita = mysqli_real_escape_string($conn, $_POST['mod_docente'][$email_dd]);
            $ore = (float)$_POST['ore_docente'][$email_dd];

            mysqli_query($conn, "INSERT INTO presenza_docentediscente_attivita (ida, email_dd, ore_partecipate, modalita) 
                                 VALUES ($ida, '$email_dd', $ore, '$modalita')");
        }
    }

    // Torna indietro alla pagina del registro confermando l'operazione
    header("Location: foglio_firme.php?idi=$idi&ida=$ida&status=success");
    exit();
}
?>