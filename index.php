<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// include('auth.php'); // DISATTIVATO TEMPORANEAMENTE PER ACCESSO LIBERO COME RICHIESTO
include('db.php');

// Cattura e normalizza le variabili d'ingresso dall'URL
$action = isset($_GET['action']) ? trim($_GET['action']) : '';
$idi = isset($_GET['idi']) ? (int)$_GET['idi'] : 0;

if (isset($_GET['amp;idi']) && $idi === 0) {
    $idi = (int)$_GET['amp;idi'];
}

$error_message = "";
$success_message = "";
$save_debug_log = ""; // Contenitore dei log di scrittura post

$categorie_config = [
    'B.1.1 Direzione/Equipe' => ['calendario' => false, 'timesheet' => true, 'classi' => false, 'docenti' => false],
    'B.1.2 Coordinamento' => ['calendario' => false, 'timesheet' => true, 'classi' => false, 'docenti' => false],
    'B.1.3 Amministrazione' => ['calendario' => false, 'timesheet' => true, 'classi' => false, 'docenti' => false],
    'B.1.4 Segreteria' => ['calendario' => false, 'timesheet' => true, 'classi' => false, 'docenti' => false],
    'B.1.5 Progettazione' => ['calendario' => false, 'timesheet' => true, 'classi' => false, 'docenti' => false],
    'B.1.6 Ricerca e Analisi' => ['calendario' => false, 'timesheet' => true, 'classi' => false, 'docenti' => false],
    'B.1.7 Formazione dei formatori' => ['calendario' => true, 'timesheet' => false, 'formatori' => true, 'docenti' => true, 'classi' => false],
    'B.1.8 Sperimentazione' => ['calendario' => true, 'timesheet' => false, 'formatori' => true, 'docenti' => true, 'classi' => true],
    'B.1.9 Accompagnamento e implementazione' => ['calendario' => true, 'timesheet' => false, 'formatori' => true, 'docenti' => true, 'classi' => true],
    'B.1.10 Elaborazione materiale didattico' => ['calendario' => false, 'timesheet' => true, 'classi' => false, 'docenti' => false],
    'B.1.11 Monitoraggio e Valutazione' => ['calendario' => false, 'timesheet' => true, 'classi' => false, 'docenti' => false],
    'B.1.12 Comunicazione' => ['calendario' => false, 'timesheet' => true, 'classi' => false, 'docenti' => false],
    'B.1.13 Attività con i destinatari' => ['calendario' => true, 'timesheet' => false, 'formatori' => true, 'docenti' => false, 'classi' => false]
];

$budget_finanziato_config = [
    'B.1.1 Direzione/Equipe' => 31000.00,
    'B.1.2 Coordinamento' => 6000.00,
    'B.1.3 Amministrazione' => 6800.00,
    'B.1.4 Segreteria' => 2000.00,
    'B.1.5 Progettazione' => 4000.00,
    'B.1.6 Ricerca e Analisi' => 1500.00,
    'B.1.7 Formazione dei formatori' => 4500.00,
    'B.1.8 Sperimentazione' => 28000.00,
    'B.1.9 Accompagnamento e implementazione' => 18000.00,
    'B.1.10 Elaborazione materiale didattico' => 6500.00,
    'B.1.11 Monitoraggio e Valutazione' => 3500.00,
    'B.1.12 Comunicazione' => 6000.00,
    'B.1.13 Attività con i destinatari' => 25000.00
];

// ==========================================
// PROCESSO: CANCELLAZIONE A CASCATA (CESTINO)
// ==========================================
if ($action === 'delete_iniziativa' && $idi > 0 && isset($_GET['pwd']) && $_GET['pwd'] === 'spazio') {
    $act_q = mysqli_query($conn, "SELECT ida FROM attivita WHERE idi = $idi");
    while($act = mysqli_fetch_assoc($act_q)) {
        $ida = (int)$act['ida'];
        mysqli_query($conn, "DELETE FROM assegnazione_formatore_attivita WHERE ida = $ida");
        mysqli_query($conn, "DELETE FROM assegnazione_classe_attivita WHERE ida = $ida");
        mysqli_query($conn, "DELETE FROM assegnazione_docente_attivita WHERE ida = $ida");
    }
    mysqli_query($conn, "DELETE FROM attivita WHERE idi = $idi");
    mysqli_query($conn, "DELETE FROM siform_iniziativa_formatore WHERE idi = $idi");
    mysqli_query($conn, "DELETE FROM siform_iniziativa_classe WHERE idi = $idi");
    mysqli_query($conn, "DELETE FROM siform_iniziativa_docentediscente WHERE idi = $idi");
    mysqli_query($conn, "DELETE FROM dettaglio_timesheet WHERE idi = $idi");
    mysqli_query($conn, "DELETE FROM iniziativa WHERE idi = $idi");
    
    echo "<script>window.location.replace('index.php?status=deleted');</script>";
    exit();
}

// ==========================================
// PROCESSO: SALVATAGGIO NUOVA INIZIATIVA
// ==========================================
if (isset($_POST['salva_iniziativa'])) {
    $titolo = mysqli_real_escape_string($conn, $_POST['titolo']);
    $nome = mysqli_real_escape_string($conn, $_POST['nome_siform']);
    $categoria = mysqli_real_escape_string($conn, $_POST['categoria']);
    $data_com = mysqli_real_escape_string($conn, $_POST['data_comunicazione_siform']);
    $data_in = mysqli_real_escape_string($conn, $_POST['data_inizio']);
    $data_fi = mysqli_real_escape_string($conn, $_POST['data_fine']);
    $note = mysqli_real_escape_string($conn, $_POST['note']);
    $google_meet_link = mysqli_real_escape_string($conn, $_POST['google_meet_link']);

    $sql = "INSERT INTO iniziativa (titolo, nome_siform, categoria, data_comunicazione_siform, data_inizio, data_fine, note, google_meet_link) 
            VALUES ('$titolo', '$nome', '$categoria', '$data_com', '$data_in', '$data_fi', '$note', '$google_meet_link')";
    
    if (mysqli_query($conn, $sql)) {
        $new_idi = mysqli_insert_id($conn);
        if (!empty($_POST['formatori'])) {
            foreach ($_POST['formatori'] as $f) {
                $f_esc = mysqli_real_escape_string($conn, $f);
                $costo = isset($_POST['costo_orario'][$f]) ? (float)$_POST['costo_orario'][$f] : 0.00;
                mysqli_query($conn, "INSERT INTO siform_iniziativa_formatore (idi, emailf, ore_totali_assegnate, costo_orario) VALUES ($new_idi, '$f_esc', 0.00, $costo)");
            }
        }
        if (!empty($_POST['classi'])) {
            foreach ($_POST['classi'] as $c) { 
                mysqli_query($conn, "INSERT INTO siform_iniziativa_classe (idi, idc) VALUES ($new_idi, (int)$c)"); 
            }
        }
        if (!empty($_POST['docenti'])) {
            foreach ($_POST['docenti'] as $d) { 
                mysqli_query($conn, "INSERT INTO siform_iniziativa_docentediscente (idi, email_dd) VALUES ($new_idi, '" . mysqli_real_escape_string($conn, $d) . "')"); 
            }
        }
        echo "<script>window.location.replace('index.php?status=created');</script>";
        exit();
    }
}

// ==========================================
// PROCESSO: SALVATAGGIO MODIFICA INIZIATIVA (CON DIAGNOSTICA BLOCCANTE)
// ==========================================
if (isset($_POST['modifica_iniziativa_submit'])) {
    $titolo = mysqli_real_escape_string($conn, $_POST['titolo']);
    $nome = mysqli_real_escape_string($conn, $_POST['nome_siform']);
    $categoria = mysqli_real_escape_string($conn, $_POST['categoria']);
    $data_com = mysqli_real_escape_string($conn, $_POST['data_comunicazione_siform']);
    $data_in = mysqli_real_escape_string($conn, $_POST['data_inizio']);
    $data_fi = mysqli_real_escape_string($conn, $_POST['data_fine']);
    $note = mysqli_real_escape_string($conn, $_POST['note']);
    $google_meet_link = mysqli_real_escape_string($conn, $_POST['google_meet_link']);

    $formatori_scelti = isset($_POST['formatori']) ? $_POST['formatori'] : [];
    $classi_scelte = isset($_POST['classi']) ? $_POST['classi'] : [];
    $docenti_scelti = isset($_POST['docenti']) ? $_POST['docenti'] : [];

    // Compilazione del log visivo dei dati in arrivo
    $save_debug_log .= "📥 <b>DATI INTERCETTATI IN SCRITTURA (IDI: $idi):</b><br>";
    $save_debug_log .= "• Array \$_POST['classi'] inviato dal form: " . (!empty($classi_scelte) ? implode(', ', $classi_scelte) : "<span style='color:#ef4444;'>VUOTO/NESSUNA CLASSE SPUNTATA</span>") . "<br>";

    $bloccato = false;
    $check_cat_key = trim($categoria);
    $check_calendario = false;
    foreach($categorie_config as $k => $v) {
        if(trim($k) === $check_cat_key && $v['calendario']) { $check_calendario = true; break; }
    }

    if ($check_calendario) {
        $current_f = mysqli_query($conn, "SELECT emailf FROM siform_iniziativa_formatore WHERE idi = $idi");
        while ($cf = mysqli_fetch_assoc($current_f)) {
            if (!in_array($cf['emailf'], $formatori_scelti)) {
                $check = mysqli_query($conn, "SELECT COUNT(*) as tot FROM assegnazione_formatore_attivita p JOIN attivita a ON p.ida = a.ida WHERE a.idi = $idi AND p.emailf = '".$cf['emailf']."' AND p.modalita_effettiva IN ('Presenza', 'FAD')");
                if (mysqli_fetch_assoc($check)['tot'] > 0) { $error_message .= "❌ Impossibile rimuovere il formatore ".$cf['emailf']." (ha già firmato attività effettive).<br>"; $bloccato = true; }
            }
        }
    }

    if (!$bloccato) {
        $upd_i = mysqli_query($conn, "UPDATE iniziativa SET titolo='$titolo', nome_siform='$nome', categoria='$categoria', data_comunicazione_siform='$data_com', data_inizio='$data_in', data_fine='$data_fi', note='$note', google_meet_link='$google_meet_link' WHERE idi = $idi");
        $save_debug_log .= "• Query UPDATE 'iniziativa': " . ($upd_i ? "🟢 COMPLETATA" : "🔴 ERRORE: " . mysqli_error($conn)) . "<br>";

        mysqli_query($conn, "DELETE FROM siform_iniziativa_formatore WHERE idi = $idi");
        foreach ($formatori_scelti as $f) { 
            $f_esc = mysqli_real_escape_string($conn, $f);
            $sum_q = mysqli_query($conn, "SELECT SUM(a.ore) as tot_ore FROM assegnazione_formatore_attivita af JOIN attivita a ON af.ida = a.ida WHERE a.idi = $idi AND af.emailf = '$f_esc'");
            $sum_row = mysqli_fetch_assoc($sum_q); $ore_tot_calcolate = (float)($sum_row['tot_ore'] ?? 0.00);
            $costo = isset($_POST['costo_orario'][$f]) ? (float)$_POST['costo_orario'][$f] : 0.00;
            mysqli_query($conn, "INSERT INTO siform_iniziativa_formatore (idi, emailf, ore_totali_assegnate, costo_orario) VALUES ($idi, '$f_esc', $ore_tot_calcolate, $costo)"); 
        }
        
        // ESECUZIONE RELAZIONE CLASSI CON TRACCIAMENTO SQL DETTAGLIATO
        $del_c = mysqli_query($conn, "DELETE FROM siform_iniziativa_classe WHERE idi = $idi");
        $save_debug_log .= "• Query DELETE relazioni classi precedenti: " . ($del_c ? "🟢 COMPLETATA (" . mysqli_affected_rows($conn) . " righe rimosse)" : "🔴 ERRORE: " . mysqli_error($conn)) . "<br>";

        if (!empty($classi_scelte)) {
            foreach ($classi_scelte as $c) { 
                $idc_clean = (int)$c;
                $ins_sql = "INSERT INTO siform_iniziativa_classe (idi, idc) VALUES ($idi, $idc_clean)";
                $ins_c = mysqli_query($conn, $ins_sql);
                $save_debug_log .= "  -> Query: <code>$ins_sql</code> ➔ Esito: " . ($ins_c ? "🟢 SUCCESS" : "🔴 FALLITO: " . mysqli_error($conn)) . "<br>";
            }
        }
        
        mysqli_query($conn, "DELETE FROM siform_iniziativa_docentediscente WHERE idi = $idi");
        if (!empty($docenti_scelti)) {
            foreach ($docenti_scelti as $d) { mysqli_query($conn, "INSERT INTO siform_iniziativa_docentediscente (idi, email_dd) VALUES ($idi, '" . mysqli_real_escape_string($conn, $d) . "')"); }
        }
        
        // Mostra il report a schermo interrompendo il redirect automatico per analisi
        $action = 'debug_pause';
    }
}

// ==========================================
// PROCESSO: DIARIO TIMESHEET
// ==========================================
if (isset($_POST['add_diario_ts_submit'])) {
    $emailf = mysqli_real_escape_string($conn, $_POST['emailf']);
    $data_ts = mysqli_real_escape_string($conn, $_POST['data_ts']);
    $descrizione = mysqli_real_escape_string($conn, $_POST['descrizione']);
    $tipo_input = $_POST['tipo_input'];
    $id_ts_edit = isset($_POST['id_ts_edit']) ? (int)$_POST['id_ts_edit'] : 0;
    
    $ore = 0.00; $ora_in = "NULL"; $ora_fi = "NULL";
    if ($tipo_input === 'blocco') {
        $ore = (float)$_POST['ore_blocco'];
    } else {
        $ora_in_str = $_POST['ora_inizio']; $ora_fi_str = $_POST['ora_fine'];
        $ora_in = "'".mysqli_real_escape_string($conn, $ora_in_str)."'"; $ora_fi = "'".mysqli_real_escape_string($conn, $ora_fi_str)."'";
        $ore = (strtotime($ora_fi_str) - strtotime($ora_in_str)) / 3600;
    }
    
    if ($ore > 0 && !empty($data_ts) && !empty($descrizione)) {
        if ($id_ts_edit > 0) {
            mysqli_query($conn, "UPDATE dettaglio_timesheet SET emailf='$emailf', data='$data_ts', ore=$ore, ora_inizio=$ora_in, ora_fine=$ora_fi, descrizione='$descrizione' WHERE id_ts = $id_ts_edit");
            $vecchio_f = mysqli_real_escape_string($conn, $_POST['vecchio_emailf']);
            if ($vecchio_f !== $emailf) { mysqli_query($conn, "UPDATE siform_iniziativa_formatore SET ore_svolte = COALESCE((SELECT SUM(ore) FROM dettaglio_timesheet WHERE idi=$idi AND emailf='$vecchio_f'), 0) WHERE idi=$idi AND emailf='$vecchio_f'"); }
            $success_message = "✅ Attività del diario aggiornata correttamente!";
        } else {
            mysqli_query($conn, "INSERT INTO dettaglio_timesheet (idi, emailf, data, ore, ora_inizio, ora_fine, descrizione) VALUES ($idi, '$emailf', '$data_ts', $ore, $ora_in, $ora_fi, '$descrizione')");
            $success_message = "📝 Riga diario di lavoro registrata con successo!";
        }
        mysqli_query($conn, "UPDATE siform_iniziativa_formatore SET ore_svolte = (SELECT SUM(ore) FROM dettaglio_timesheet WHERE idi=$idi AND emailf='$emailf') WHERE idi=$idi AND emailf='$emailf'");
    }
    $action = 'timesheet';
}

if ($action === 'delete_riga_ts' && isset($_GET['id_ts'])) {
    $id_ts = (int)$_GET['id_ts'];
    $emailf_tmp = mysqli_real_escape_string($conn, $_GET['emailf']);
    mysqli_query($conn, "DELETE FROM dettaglio_timesheet WHERE id_ts = $id_ts");
    mysqli_query($conn, "UPDATE siform_iniziativa_formatore SET ore_svolte = COALESCE((SELECT SUM(ore) FROM dettaglio_timesheet WHERE idi=$idi AND emailf='$emailf_tmp'), 0) WHERE idi=$idi AND emailf='$emailf_tmp'");
    $success_message = "🗑️ Riga del diario eliminata.";
    $action = 'timesheet';
}

if (isset($_GET['status'])) {
    if ($_GET['status'] === 'created') $success_message = "✅ Iniziativa creata con successo!";
    if ($_GET['status'] === 'updated') $success_message = "✅ Iniziativa e budget aggiornati correttamente!";
    if ($_GET['status'] === 'deleted') $success_message = "🗑️ Iniziativa e tutti i dati a calendario eliminati.";
}

// INCLUSIONE GRAFICA DELLA NAVBAR (DOPO TUTTI I POSSIBILI REDIRECT INTERNI)
include('navbar.php');
?>

<?php if($error_message != ""): ?> <div style="background:#f2dede; color:#a94442; padding:15px; margin-bottom:20px; border:1px solid #ebccd1; font-family:sans-serif; border-radius:4px;"><?php echo $error_message; ?></div> <?php endif; ?>
<?php if($success_message != ""): ?> <div style="background:#dff0d8; color:#3c763d; padding:15px; margin-bottom:20px; border:1px solid #d6e9c6; font-family:sans-serif; border-radius:4px;"><?php echo $success_message; ?></div> <?php endif; ?>

<?php
// =======================================================
// SCHERMATA: BLOCCO PAUSA DIAGNOSTICO POST SALVATAGGIO
// =======================================================
if ($action === 'debug_pause') {
?>
    <div style="background: #0f172a; color: #38bdf8; border: 3px solid #e11d48; padding: 20px; margin-top: 20px; font-family: monospace; border-radius: 6px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
        <h3 style="color:#f43f5e; margin-top:0;">📊 RADAR DI VERIFICA SCRITTURA (POST_SAVE):</h3>
        <?php echo $save_debug_log; ?>
        <hr style="border-color:#334155; margin:15px 0;">
        <span style="color:#fbbf24; font-weight:bold;">SBLOCCO MANUALE RICHIESTO:</span> Se i log qui sopra non mostrano errori rossi, le spunte sono state scritte sul DB.<br>
        Clicca sul pulsante per confermare la visualizzazione: 
        <a href="index.php?status=updated" style="display:inline-block; margin-top:10px; padding:6px 12px; background:#16a34a; color:white; font-weight:bold; border-radius:4px; text-decoration:none;">Conferma e Vai alla Dashboard</a>
    </div>
<?php
}

// =======================================================
// INTERFACCIA: FORM INSERIMENTO / MODIFICA (ADD & EDIT)
// =======================================================
if ($action === 'add' || $action === 'edit') {
    $ini = ['titolo'=>'', 'nome_siform'=>'', 'categoria'=>'B.1.8 Sperimentazione', 'data_comunicazione_siform'=>'', 'data_inizio'=>'', 'data_fine'=>'', 'note'=>'', 'google_meet_link'=>''];
    $macro_f = []; $macro_c = []; $macro_d = []; $ore_tot_f = []; $costi_f = [];
    
    if ($action === 'edit' && $idi > 0) {
        $ini = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM iniziativa WHERE idi = $idi"));
        $rf = mysqli_query($conn, "SELECT emailf, ore_totali_assegnate, costo_orario FROM siform_iniziativa_formatore WHERE idi = $idi"); 
        while($r = mysqli_fetch_assoc($rf)){ 
            $macro_f[] = $r['emailf']; $ore_tot_f[$r['emailf']] = $r['ore_totali_assegnate']; $costi_f[$r['emailf']] = $r['costo_orario']; 
        }
        
        // STRUTTURA ROBUSTA: Caricamento e tracciamento ID della tabella di legame
        $rc = mysqli_query($conn, "SELECT idc FROM siform_iniziativa_classe WHERE idi = $idi"); 
        while($r = mysqli_fetch_assoc($rc)){ $macro_c[] = $r['idc']; }
        
        // Monitor di lettura a schermo prima del rendering delle spunte
        echo "<div style='background: #1e293b; color: #38bdf8; border: 1px solid #475569; padding: 12px; margin-bottom: 20px; font-family: monospace; font-size:12px; border-radius:4px;'>";
        echo "🔍 <b>DIARIO DI RECUPERO DATI (EDIT VIEW) FOR IDI $idi:</b><br>";
        echo "• ID Classi estratti da 'siform_iniziativa_classe': [" . (!empty($macro_c) ? implode(', ', $macro_c) : "<i>Nessuno trovato</i>") . "]<br>";
        
        if (empty($macro_c)) {
            echo "↳ ⚠️ Tabella principale vuota. Avvio blocco di recupero alternativo (Fallback dal Calendario delle Lezioni)...<br>";
            $rc_fallback = mysqli_query($conn, "SELECT DISTINCT ac.idc FROM assegnazione_classe_attivita ac JOIN attivita a ON ac.ida = a.ida WHERE a.idi = $idi");
            while($r = mysqli_fetch_assoc($rc_fallback)){ $macro_c[] = $r['idc']; }
            echo "   • ID Classi agganciati via fallback: [" . (!empty($macro_c) ? implode(', ', $macro_c) : "Nessuna classe trovata") . "]<br>";
        }
        echo "</div>";
        
        $rd = mysqli_query($conn, "SELECT email_dd FROM siform_iniziativa_docentediscente WHERE idi = $idi"); 
        while($r = mysqli_fetch_assoc($rd)){ $macro_d[] = $r['email_dd']; }
    }
    
    $res_f = mysqli_query($conn, "SELECT emailf, nome, cognome FROM formatore ORDER BY cognome");
    $res_c = mysqli_query($conn, "SELECT idc, nome FROM classe ORDER BY nome");
    $res_d = mysqli_query($conn, "SELECT email_dd, nome, cognome FROM docente_discente ORDER BY cognome");
?>
    <h2><?php echo ($action === 'edit') ? "✏️ Modifica e Controllo Budget" : "➕ Nuova Iniziativa SIFORM"; ?></h2>
    <form method="POST" action="index.php?action=<?php echo htmlspecialchars($action); ?>&idi=<?php echo $idi; ?>" style="background: white; padding: 20px; border: 1px solid #ddd; max-width: 950px; border-radius:6px; font-family:sans-serif;">
        <label><strong>Titolo Iniziativa (Breve):</strong></label><br>
        <input type="text" name="titolo" value="<?php echo htmlspecialchars($ini['titolo'] ?? ''); ?>" required style="width:100%; padding:8px; margin-bottom:10px; font-size:15px; border:1px solid #0275d8; border-radius:4px;"><br>
        
        <label>Nome SIFORM completo (Opzionale):</label><br>
        <input type="text" name="nome_siform" value="<?php echo htmlspecialchars($ini['nome_siform'] ?? ''); ?>" placeholder="Lascia vuoto se non presente" style="width:100%; padding:8px; margin-bottom:10px; border-radius:4px; border:1px solid #cbd5e1;"><br>

        <label>Categoria SIFORM:</label><br>
        <select name="categoria" id="selectCategoria" onchange="switchCampi()" style="width:100%; padding:8px; margin-bottom:10px; font-weight:bold; border-radius:4px;">
            <?php foreach($categorie_config as $cat_name => $conf) { ?>
                <option value="<?php echo $cat_name; ?>" <?php if(($ini['categoria'] ?? '') === $cat_name) echo 'selected'; ?>><?php echo $cat_name; ?></option>
            <?php } ?>
        </select><br>

        <label>Data Comunicazione SIFORM:</label><br>
        <input type="date" name="data_comunicazione_siform" value="<?php echo $ini['data_comunicazione_siform'] ?? ''; ?>" required style="padding:8px; margin-bottom:10px; border-radius:4px; border:1px solid #cbd5e1;"><br>
        
        <label>Link Aula Virtuale Default Iniziativa:</label><br>
        <input type="text" name="google_meet_link" value="<?php echo htmlspecialchars($ini['google_meet_link'] ?? ''); ?>" style="width:100%; padding:8px; margin-bottom:10px; border-radius:4px; border:1px solid #cbd5e1;"><br>
        
        <label>Data Inizio:</label> <input type="date" name="data_inizio" value="<?php echo $ini['data_inizio'] ?? ''; ?>" required style="padding:8px; border-radius:4px; border:1px solid #cbd5e1;"> 
        <label>Data Fine:</label> <input type="date" name="data_fine" value="<?php echo $ini['data_fine'] ?? ''; ?>" required style="padding:8px; margin-bottom:10px; border-radius:4px; border:1px solid #cbd5e1;"><br>
        
        <label><strong>Note Iniziativa:</strong></label><br>
        <textarea name="note" style="width:100%; height:65px; padding:8px; margin-bottom:12px; border:1px solid #cbd5e1; font-family:sans-serif; border-radius:4px;"><?php echo htmlspecialchars($ini['note'] ?? ''); ?></textarea><br>

        <button type="submit" name="<?php echo ($action === 'edit') ? 'modifica_iniziativa_submit' : 'salva_iniziativa'; ?>" class="btn btn-green">Salva Configurazione Finanziaria</button>
        <a href="index.php" class="btn" style="background:#64748b; color:white; margin-left:5px; text-decoration:none; padding:6px 12px; border-radius:4px;">Annulla</a>
        
        <hr><h3>Abbinamento Risorse e Costi Professionali</h3>
        <div style="display:flex; gap:20px; border:1px solid #eee; padding:15px; background:#fcfcfc; border-radius:6px;">
            <div style="flex:1.3; max-height:350px; overflow-y:auto;" id="boxFormatori">
                <h4 style="margin-top:0; border-bottom:2px solid #d9534f; padding-bottom:5px;">Staff / Formatori</h4>
                <?php while($f = mysqli_fetch_assoc($res_f)) { 
                    $is_chk = in_array($f['emailf'], $macro_f);
                    $o_tot = isset($ore_tot_f[$f['emailf']]) ? $ore_tot_f[$f['emailf']] : '0.00';
                    $c_or = isset($costi_f[$f['emailf']]) ? $costi_f[$f['emailf']] : '0.00';
                ?>
                    <div style="margin-bottom:12px; background:#fff; padding:6px; border:1px solid #e2e8f0; border-radius:4px;">
                        <label><input type="checkbox" name="formatori[]" value="<?php echo $f['emailf']; ?>" <?php if($is_chk) echo 'checked'; ?>> <strong><?php echo $f['cognome']." ".$f['nome']; ?></strong></label>
                        <div style="margin-left:20px; margin-top:4px;">
                            <small>Ore Assegnate:</small> <input type="text" readonly name="ore_totali[<?php echo $f['emailf']; ?>]" value="<?php echo number_format((float)$o_tot,2); ?>" style="width:55px; padding:2px; background:#f1f5f9; border:1px solid #cbd5e1; border-radius:3px; color:#475569; text-align:center; font-weight:bold;"> h
                            <small style="margin-left:10px;">Costo Orario:</small> <input type="number" step="0.01" name="costo_orario[<?php echo $f['emailf']; ?>]" value="<?php echo $c_or; ?>" style="width:65px; padding:2px; border:1px solid #cbd5e1; border-radius:3px;"> €/h
                        </div>
                    </div>
                <?php } ?>
            </div>
            <div style="flex:0.8; max-height:350px; overflow-y:auto;" id="boxClassi">
                <h4 style="margin-top:0; border-bottom:2px solid #f0ad4e; padding-bottom:5px;">Classi</h4>
                <?php while($c = mysqli_fetch_assoc($res_c)) { 
                    // ALLINEAMENTO COERENTE DI TIPO: Controllo incrociato sia per interi che per stringhe pure
                    $is_classe_checked = (in_array((int)$c['idc'], $macro_c) || in_array((string)$c['idc'], $macro_c)) ? 'checked' : '';
                ?>
                    <label style="display:block; margin-bottom:6px;"><input type="checkbox" name="classi[]" value="<?php echo $c['idc']; ?>" <?php echo $is_classe_checked; ?>> <?php echo htmlspecialchars($c['nome']); ?></label>
                <?php } ?>
            </div>
            <div style="flex:1; display:flex; flex-direction:column;" id="boxDocenti">
                <h4 style="margin-top:0; border-bottom:2px solid #5cb85c; padding-bottom:5px;">Docenti Discenti</h4>
                <input type="text" id="macroSearchDocente" placeholder="Filtra per cognome/nome..." style="width:100%; padding:6px; margin-bottom:6px; border:1px solid #5cb85c; border-radius:4px;">
                <div style="overflow-y:auto; max-height:280px; border:1px solid #ddd; padding:6px; background:#fff; border-radius:4px;">
                    <?php while($d = mysqli_fetch_assoc($res_d)) { $is_chk = in_array($d['email_dd'], $macro_d); ?>
                        <div class="macro-docente-row"><label><input type="checkbox" name="docenti[]" value="<?php echo $d['email_dd']; ?>" <?php if($is_chk) echo 'checked'; ?>> <span class="macro-docente-name"><?php echo $d['cognome']." ".$d['nome']; ?></span></label></div>
                    <?php } ?>
                </div>
            </div>
        </div>
    </form>
    <script>
    const config = <?php echo json_encode($categorie_config); ?>;
    function switchCampi(){
        let selectCat = document.getElementById('selectCategoria');
        if (!selectCat) return; 
        let cat = selectCat.value;
        let cat_key = cat.trim();
        let trovato = null;
        for(let k in config){ if(k.trim() === cat_key){ trovato = config[k]; break; } }
        let boxClassi = document.getElementById('boxClassi');
        let boxDocenti = document.getElementById('boxDocenti');
        if (trovato) {
            if (boxClassi) boxClassi.style.display = (trovato.classi !== false) ? 'block' : 'none';
            if (boxDocenti) boxDocenti.style.display = (trovato.docenti !== false) ? 'flex' : 'none';
        } else {
            if (boxClassi) boxClassi.style.display = 'block';
            if (boxDocenti) boxDocenti.style.display = 'flex';
        }
    }
    document.addEventListener('DOMContentLoaded', function() {
        switchCampi();
        const searchInput = document.getElementById('macroSearchDocente');
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                let term = this.value.toLowerCase().trim();
                let rows = document.querySelectorAll('.macro-docente-row');
                rows.forEach(function(row) {
                    let nameText = row.querySelector('.macro-docente-name').textContent.toLowerCase();
                    if (nameText.includes(term)) { row.style.display = ''; } else { row.style.display = 'none'; }
                });
            });
        }
    });
    </script>
<?php
// =======================================================
// INTERFACCIA: REGISTRO DIARIO INTERNO (TIMESHEET STAFF)
// =======================================================
} elseif ($action === 'timesheet' && $idi > 0) {
    $ini = mysqli_fetch_assoc(mysqli_query($conn, "SELECT titolo, nome_siform, categoria FROM iniziativa WHERE idi = $idi"));
    $formatori_ts = mysqli_query($conn, "SELECT f.nome, f.cognome, sif.emailf, sif.costo_orario, sif.ore_totali_assegnate FROM siform_iniziativa_formatore sif JOIN formatore f ON sif.emailf = f.emailf WHERE sif.idi = $idi");
    $id_ts_edit = isset($_GET['edit_ts_id']) ? (int)$_GET['edit_ts_id'] : 0;
    $val_edit = ['emailf'=>'', 'data'=>'', 'ore'=>'1', 'ora_inizio'=>'', 'ora_fine'=>'', 'descrizione'=>'', 'tipo_input'=>'blocco'];
    if ($id_ts_edit > 0) {
        $edit_q = mysqli_query($conn, "SELECT * FROM dettaglio_timesheet WHERE id_ts = $id_ts_edit");
        if ($res_edit = mysqli_fetch_assoc($edit_q)) { $val_edit = $res_edit; $val_edit['tipo_input'] = ($res_edit['ora_inizio']) ? 'calendario' : 'blocco'; }
    }
?>
    <h2>⏱️ Registro Diario di Bordo e Costi Staff</h2>
    <h3><?php echo htmlspecialchars($ini['titolo'] ? $ini['titolo'] : $ini['nome_siform']); ?> <small>(<?php echo $ini['categoria']; ?>)</small></h3>
    <div style="display:flex; gap:20px; font-family:sans-serif;">
        <div style="width:35%; background:#f8fafc; padding:15px; border:1px solid #cbd5e1; border-radius:6px; height:fit-content;">
            <h3><?php echo ($id_ts_edit > 0) ? "✏️ Modifica" : "✍️ Registra"; ?></h3>
            <form method="POST" action="">
                <input type="hidden" name="id_ts_edit" value="<?php echo $id_ts_edit; ?>">
                <input type="hidden" name="vecchio_emailf" value="<?php echo htmlspecialchars($val_edit['emailf']); ?>">
                <select name="emailf" required style="width:100%; padding:6px; margin-bottom:10px; border-radius:4px; border:1px solid #cbd5e1;">
                    <?php mysqli_data_seek($formatori_ts, 0); while($f = mysqli_fetch_assoc($formatori_ts)) { ?>
                        <option value="<?php echo $f['emailf']; ?>" <?php if($val_edit['emailf'] === $f['emailf']) echo 'selected'; ?>><?php echo $f['cognome']." ".$f['nome']." (".$f['costo_orario']."€/h)"; ?></option>
                    <?php } ?>
                </select>
                <input type="date" name="data_ts" value="<?php echo $val_edit['data']; ?>" required style="padding:6px; margin-bottom:10px; border-radius:4px; border:1px solid #cbd5e1;"><br>
                
                <label><input type="radio" name="tipo_input" value="blocco" <?php if($val_edit['tipo_input'] === 'blocco') echo 'checked'; ?> onclick="document.getElementById('inputBlocco').style.display='block'; document.getElementById('inputCalendario').style.display='none';"> A Blocco</label>
                <label style="margin-left:10px;"><input type="radio" name="tipo_input" value="calendario" <?php if($val_edit['tipo_input'] === 'calendario') echo 'checked'; ?> onclick="document.getElementById('inputBlocco').style.display='none'; document.getElementById('inputCalendario').style.display='block';"> Calendario</label>
                
                <div id="inputBlocco" style="margin-top:10px; display:<?php echo ($val_edit['tipo_input'] === 'blocco')?'block':'none'; ?>;"><input type="number" step="0.25" name="ore_blocco" value="<?php echo $val_edit['ore']; ?>" style="width:70px; padding:4px; border:1px solid #cbd5e1; border-radius:4px;"> h</div>
                <div id="inputCalendario" style="margin-top:10px; display:<?php echo ($val_edit['tipo_input'] === 'calendario')?'block':'none'; ?>;"><input type="time" name="ora_inizio" value="<?php echo $val_edit['ora_inizio']?substr($val_edit['ora_inizio'],0,5):'09:00'; ?>" style="padding:4px; border:1px solid #cbd5e1; border-radius:4px;"> to <input type="time" name="ora_fine" value="<?php echo $val_edit['ora_fine']?substr($val_edit['ora_fine'],0,5):'11:00'; ?>" style="padding:4px; border:1px solid #cbd5e1; border-radius:4px;"></div>
                
                <textarea name="descrizione" required style="width:100%; height:60px; margin-top:10px; padding:6px; border:1px solid #cbd5e1; border-radius:4px; font-family:sans-serif;"><?php echo htmlspecialchars($val_edit['descrizione']); ?></textarea>
                <button type="submit" name="add_diario_ts_submit" class="btn btn-green" style="width:100%; margin-top:10px;">Salva</button>
            </form>
        </div>
        <div style="width:65%;">
            <table style="width:100%; border-collapse:collapse; background:white; box-shadow:0 1px 3px rgba(0,0,0,0.1); border-radius:6px; overflow:hidden;">
                <tr style="background:#f1f5f9; color:#475569;">
                    <th style="padding:10px; text-align:left;">Data</th>
                    <th style="padding:10px; text-align:left;">Operatore</th>
                    <th style="padding:10px; text-align:left;">Descrizione</th>
                    <th style="padding:10px; text-align:left;">Durata</th>
                    <th style="padding:10px; text-align:left;">Lordo</th>
                    <th style="padding:10px; text-align:center;">Azioni</th>
                </tr>
                <?php 
                $diario_q = mysqli_query($conn, "SELECT d.*, f.cognome, f.nome, sif.costo_orario FROM dettaglio_timesheet d JOIN formatore f ON d.emailf = f.emailf JOIN siform_iniziativa_formatore sif ON (d.idi = sif.idi AND d.emailf = sif.emailf) WHERE d.idi = $idi ORDER BY d.data DESC");
                while ($d = mysqli_fetch_assoc($diario_q)) { $lordo = $d['ore'] * $d['costo_orario']; ?>
                    <tr style="border-bottom:1px solid #e2e8f0;">
                        <td style="padding:10px;"><?php echo date('d/m/Y', strtotime($d['data'])); ?></td>
                        <td style="padding:10px; font-weight:bold; color:#334155;"><?php echo $d['cognome']; ?></td>
                        <td style="padding:10px; font-size:13px; color:#475569;"><?php echo htmlspecialchars($d['descrizione']); ?></td>
                        <td style="padding:10px; font-weight:bold; color:#16a34a;"><?php echo $d['ore']; ?> h</td>
                        <td style="padding:10px; font-weight:bold; color:#0275d8;"><?php echo number_format($lordo,2); ?> €</td>
                        <td style="padding:10px; text-align:center;">
                            <a href="index.php?action=timesheet&idi=<?php echo $idi; ?>&edit_ts_id=<?php echo $d['id_ts']; ?>" class="btn btn-blue" style="padding:4px 8px; font-size:12px;">✏️</a> 
                            <a href="index.php?action=delete_riga_ts&idi=<?php echo $idi; ?>&id_ts=<?php echo $d['id_ts']; ?>&emailf=<?php echo $d['emailf']; ?>" class="btn" style="background:#d9534f; color:white; padding:4px 8px; font-size:12px;" onclick="return confirm('Sicuro di voler eliminare questa riga?');">🗑️</a>
                        </td>
                    </tr>
                <?php } ?>
            </table>
        </div>
    </div>
<?php
// =======================================================
// INTERFACCIA: CRUSCOTTO PRINCIPALE GENERALE (DASHBOARD)
// =======================================================
} else {
    $result = mysqli_query($conn, "SELECT * FROM iniziativa");
    $iniziative_per_categoria = [];
    foreach($categorie_config as $cat_name => $conf) { $iniziative_per_categoria[$cat_name] = []; }
    while($row = mysqli_fetch_assoc($result)) {
        $cat = $row['categoria'];
        if (isset($iniziative_per_categoria[$cat])) { $iniziative_per_categoria[$cat][] = $row; }
    }
?>
    <div style="display:flex; justify-content:space-between; align-items:center; font-family:sans-serif; margin-bottom:20px;">
        <h2>Elenco Iniziative & Controllo Costi Budget SIFORM</h2>
        <div style="display:flex; gap:10px;">
            <a href="report_economico.php" class="btn" style="background:#15803d; color:white; font-size:15px; padding:10px 15px; text-decoration:none; font-weight:bold; border-radius:4px;">📊 Registro Costi Indiretti (Flat Rate 40%)</a>
            <a href="index.php?action=add" class="btn btn-green" style="font-size:15px; padding:10px 15px;">➕ Nuova Iniziativa</a>
        </div>
    </div>

    <?php 
    foreach($iniziative_per_categoria as $nome_cat => $lista_iniziative) { 
        $is_ts = isset($categorie_config[$nome_cat]['timesheet']) && $categorie_config[$nome_cat]['timesheet'];
        $budget_finanziato_diretto = $budget_finanziato_config[$nome_cat];
        
        $cat_pianificato_diretto = 0.00;
        $cat_effettivo_diretto = 0.00;
        
        $calcoli_iniziative = [];
        foreach($lista_iniziative as $row) {
            $speso_diretto = 0.00; $pianificato_diretto = 0.00;
            
            if ($is_ts) {
                $plan_q = mysqli_query($conn, "SELECT SUM(ore_totali_assegnate * costo_orario) as tot FROM siform_iniziativa_formatore WHERE idi = " . $row['idi']);
                $plan = mysqli_fetch_assoc($plan_q); $pianificato_diretto = (float)($plan['tot'] ?? 0);

                $calc_q = mysqli_query($conn, "SELECT SUM(d.ore * filter_sif.costo_orario) as tot FROM dettaglio_timesheet d JOIN siform_iniziativa_formatore filter_sif ON (d.idi = filter_sif.idi AND d.emailf = filter_sif.emailf) WHERE d.idi = " . $row['idi']);
                $calc = mysqli_fetch_assoc($calc_q); $speso_diretto = (float)($calc['tot'] ?? 0);
            } else {
                $plan_q = mysqli_query($conn, "SELECT SUM(a.ore * sif.costo_orario) as tot FROM assegnazione_formatore_attivita p JOIN attivita a ON p.ida = a.ida JOIN siform_iniziativa_formatore sif ON (a.idi = sif.idi AND p.emailf = sif.emailf) WHERE a.idi = " . $row['idi']);
                $plan = mysqli_fetch_assoc($plan_q); $pianificato_diretto = (float)($plan['tot'] ?? 0);

                $calc_q = mysqli_query($conn, "SELECT SUM(a.ore * sif.costo_orario) as tot FROM assegnazione_formatore_attivita p JOIN attivita a ON p.ida = a.ida JOIN siform_iniziativa_formatore sif ON (a.idi = sif.idi AND p.emailf = sif.emailf) WHERE a.idi = " . $row['idi'] . " AND p.modalita_effettiva IN ('Presenza', 'FAD')");
                $calc = mysqli_fetch_assoc($calc_q); $speso_diretto = (float)($calc['tot'] ?? 0);
            }
            
            $cat_pianificato_diretto += $pianificato_diretto;
            $cat_effettivo_diretto += $speso_diretto;
            
            $form_q = mysqli_query($conn, "SELECT GROUP_CONCAT(CONCAT(f.cognome, ' (', sif.costo_orario, '€/h)') SEPARATOR ', ') as lista FROM siform_iniziativa_formatore sif JOIN formatore f ON sif.emailf = f.emailf WHERE sif.idi = " . $row['idi']);
            $form_row = mysqli_fetch_assoc($form_q);
            
            $calcoli_iniziative[] = [
                'row' => $row, 'formatori' => $form_row['lista'] ?? 'Nessuno',
                'pianificato' => $pianificato_diretto, 'effettivo' => $speso_diretto
            ];
        }
        
        $alert_style = ($cat_pianificato_diretto > $budget_finanziato_diretto) ? "color:#b91c1c; font-weight:bold; background:#fef2f2; border:1px solid #fca5a5;" : "background:#f0fdf4; color:#166534;";
    ?>
        <div style="background: white; border: 1px solid #cbd5e1; border-radius: 6px; padding: 15px; margin-bottom: 30px; font-family: sans-serif; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
            <div style="display:flex; justify-content:space-between; align-items:center; border-bottom: 2px solid #e2e8f0; padding-bottom: 10px; margin-bottom: 15px; flex-wrap: wrap; gap:10px;">
                <h3 style="margin:0; color:#1e293b; font-size:16px;"><?php echo htmlspecialchars($nome_cat); ?></h3>
                <div style="display:flex; gap:10px; font-size:12px;">
                    <div style="background:#f8fafc; border:1px solid #cbd5e1; padding:4px 10px; border-radius:4px;">
                        <span style="color:#475569; font-weight:bold;">📋 FINANZIATO BANDO:</span> <strong><?php echo number_format($budget_finanziato_diretto,2); ?> €</strong> <small style="color:#64748b;">(Ind. +<?php echo number_format($budget_finanziato_diretto*0.4,2); ?>€)</small>
                    </div>
                    <div style="<?php echo $alert_style; ?> padding:4px 10px; border-radius:4px;">
                        <span style="font-weight:bold;">📋 PIANIFICATO ATTUALE:</span> <strong><?php echo number_format($cat_pianificato_diretto,2); ?> €</strong> <small>(Ind. +<?php echo number_format($cat_pianificato_diretto*0.4,2); ?>€)</small>
                    </div>
                    <div style="background:#ecfdf5; border:1px solid #a7f3d0; color:#065f46; padding:4px 10px; border-radius:4px;">
                        <span style="font-weight:bold;">🟢 EFFETTIVO MATURATO:</span> <strong><?php echo number_format($cat_effettivo_diretto,2); ?> €</strong> <small>(Ind. +<?php echo number_format($cat_effettivo_diretto*0.4,2); ?>€)</small>
                    </div>
                </div>
            </div>
            
            <?php if(empty($calcoli_iniziative)) { ?>
                <p style="margin:0; font-style:italic; color:#94a3b8; font-size:13px; padding:5px 0;">Nessuna iniziativa caricata. Tutto il budget di <strong><?php echo number_format($budget_finanziato_diretto,2); ?> €</strong> è attualmente disponibile.</p>
            <?php } else { ?>
                <table style="width:100%; border-collapse:collapse; font-size:13px; margin:0;">
                    <tr style="background:#f8fafc; border-bottom:1px solid #cbd5e1;">
                        <th style="padding:6px; text-align:left; width:30%;">Titolo Iniziativa</th>
                        <th style="padding:6px; text-align:left; width:12%;">Periodo</th>
                        <th style="padding:6px; text-align:left; width:30%;">Personale Abbinato</th>
                        <th style="padding:6px; text-align:left; width:18%;">Quadro Economico Interno</th>
                        <th style="padding:6px; text-align:center; width:10%;">Azioni</th>
                    </tr>
                    <?php foreach($calcoli_iniziative as $item) { $i_row = $item['row']; ?>
                        <tr style="border-bottom:1px solid #e2e8f0;">
                            <td style="padding:8px; font-weight:bold; color:#334155;">
                                <?php echo htmlspecialchars(!empty($i_row['titolo']) ? $i_row['titolo'] : $i_row['nome_siform']); ?>
                            </td>
                            <td style="padding:8px; color:#64748b; font-size:12px;">
                                <?php echo date('d/m', strtotime($i_row['data_inizio'])); ?> al <?php echo date('d/m/y', strtotime($i_row['data_fine'])); ?>
                            </td>
                            <td style="padding:8px; color:#475569; font-size:12px;">
                                <?php echo htmlspecialchars($item['formatori']); ?>
                            </td>
                            <td style="padding:8px; line-height:1.3; font-size:12px;">
                                <span style="color:#475569;">Pianificato: <strong><?php echo number_format($item['pianificato'],2); ?>€</strong> (+<?php echo number_format($item['pianificato']*0.4,2); ?>€)</span><br>
                                <span style="color:#16a34a; font-weight:600;">Svolto: <strong><?php echo number_format($item['effettivo'],2); ?>€</strong> (+<?php echo number_format($item['effettivo']*0.4,2); ?>€)</span>
                            </td>
                            <td style="padding:8px; text-align:center; white-space:nowrap;">
                                <a href="comunicazione_avvio.php?idi=<?php echo $i_row['idi']; ?>" class="btn" style="background:#009688; color:white; padding:4px 8px; font-size:12px; border-radius:4px;" title="Avvio">📄</a>
                                <?php if($is_ts) { ?>
                                    <a href="index.php?action=timesheet&idi=<?php echo $i_row['idi']; ?>" class="btn" style="background:#f0ad4e; color:white; padding:4px 8px; font-size:12px; border-radius:4px;" title="Timesheet Staff">⏱️</a>
                                <?php } else { ?>
                                    <a href="calendario.php?idi=<?php echo $i_row['idi']; ?>" class="btn btn-blue" style="padding:4px 8px; font-size:12px; border-radius:4px;" title="Calendario">🗓️</a>
                                    <a href="report_corso.php?idi=<?php echo $i_row['idi']; ?>" class="btn" style="background:#8b5cf6; color:white; padding:4px 8px; font-size:12px; border-radius:4px;" title="Report Frequenze e Esiti">🎓</a>
                                <?php } ?>
                                <a href="index.php?action=edit&idi=<?php echo $i_row['idi']; ?>" class="btn btn-gray" style="background:#777; color:white; padding:4px 8px; font-size:12px; border-radius:4px;" title="Modifica Iniziativa">✏️</a>
                                
                                <a href="#" onclick="var pw = prompt('Inserisci la parola segreta per eliminare DEFINITIVAMENTE questa iniziativa e tutti i dati a calendario:'); if(pw === 'spazio') { window.location.href='index.php?action=delete_iniziativa&idi=<?php echo $i_row['idi']; ?>&pwd='+pw; } return false;" class="btn" style="background:#d9534f; color:white; padding:4px 8px; font-size:12px; border-radius:4px; margin-left:2px;" title="Elimina Iniziativa">🗑️</a>
                            </td>
                        </tr>
                    <?php } ?>
                </table>
            <?php } ?>
        </div>
    <?php } ?>
<?php } ?>