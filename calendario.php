<?php
// Forza la visibilità degli errori per facilitare il controllo su Altervista
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// include('auth.php'); // RIMOSSO PER EVITARE I BLOCCHI DELLA LOGIN
include('db.php');
include('navbar.php');
include('funzioni_firme.php');

$idi = isset($_GET['idi']) ? (int)$_GET['idi'] : 0;
$action = isset($_GET['action']) ? $_GET['action'] : '';
$id_attivita = isset($_GET['ida']) ? (int)$_GET['ida'] : 0;

$success_message = "";
$error_message = "";

if ($idi <= 0) {
    die("<div style='padding:20px; font-family:sans-serif; color:#d9534f;'>❌ Specificare un'iniziativa valida.</div>");
}

$categorie_config = [
    'B.1.7 Formazione dei formatori' => ['docenti' => true, 'classi' => false],
    'B.1.8 Sperimentazione' => ['docenti' => true, 'classi' => true],
    'B.1.9 Accompagnamento e implementazione' => ['docenti' => true, 'classi' => true],
    'B.1.13 Attività con i destinatari' => ['docenti' => false, 'classi' => false]
];

// 1. RECUPERA I DATI DELL'INIZIATIVA MADRE
$corso_q = mysqli_query($conn, "SELECT titolo, nome_siform, categoria, google_meet_link FROM iniziativa WHERE idi = $idi");
$corso = mysqli_fetch_assoc($corso_q);
if (!$corso) {
    die("<div style='padding:20px; font-family:sans-serif; color:#d9534f;'>❌ Iniziativa non trovata nel database.</div>");
}
$titolo_corso = !empty($corso['titolo']) ? $corso['titolo'] : $corso['nome_siform'];
$giorni_it = ['Domenica', 'Lunedì', 'Martedì', 'Mercoledì', 'Giovedì', 'Venerdì', 'Sabato'];

// ==========================================
// AZIONE: FIRMA MASSIVA AUTOMATICA SESSIONI DEL CORSO
// ==========================================
if (isset($_POST['azione_firma_massiva_calendario'])) {
    $res_f = esegui_firma_massiva($conn, $idi);
    if ($res_f['success']) {
        $success_message = "✅ " . $res_f['messaggio'];
    } else {
        $error_message = "❌ " . $res_f['messaggio'];
    }
}

// ==========================================
// AZIONE: SALVATAGGIO NUOVA ATTIVITÀ (INSERIMENTO)
// ==========================================
if (isset($_POST['salva_nuova_attivita'])) {
    $tipo = mysqli_real_escape_string($conn, $_POST['tipo']);
    $data_act = mysqli_real_escape_string($conn, $_POST['data']);
    $ora_inizio = mysqli_real_escape_string($conn, $_POST['ora_inizio']);
    $ora_fine = mysqli_real_escape_string($conn, $_POST['ora_fine']);
    $partecipazione = mysqli_real_escape_string($conn, $_POST['partecipazione']);
    $sede = mysqli_real_escape_string($conn, $_POST['sede']);
    $note = mysqli_real_escape_string($conn, $_POST['note']);
    $meet_custom = mysqli_real_escape_string($conn, $_POST['google_meet_link_custom']);
    
    $diff_secondi = strtotime($ora_fine) - strtotime($ora_inizio);
    if ($diff_secondi < 0) { $diff_secondi += 86400; }
    $ore = $diff_secondi / 3600;
    
    $meet_value = (empty(trim($meet_custom))) ? "NULL" : "'$meet_custom'";

    $sql_ins = "INSERT INTO attivita (tipo, data, ora_inizio, ora_fine, ore, partecipazione, sede, note, idi, google_meet_link) 
                VALUES ('$tipo', '$data_act', '$ora_inizio', '$ora_fine', $ore, '$partecipazione', '$sede', '$note', $idi, $meet_value)";
    
    if (mysqli_query($conn, $sql_ins)) {
        $nuovo_ida = mysqli_insert_id($conn);
        
        if (!empty($_POST['formatori_lezione'])) {
            foreach ($_POST['formatori_lezione'] as $f_email) {
                $f_esc = mysqli_real_escape_string($conn, $f_email);
                mysqli_query($conn, "INSERT INTO assegnazione_formatore_attivita (ida, emailf, modalita_effettiva) VALUES ($nuovo_ida, '$f_esc', '')") or die(mysqli_error($conn));
            }
        }
        if (!empty($_POST['classi_lezione'])) {
            foreach ($_POST['classi_lezione'] as $c_id) {
                $c_clean = (int)$c_id;
                mysqli_query($conn, "INSERT INTO assegnazione_classe_attivita (ida, idc) VALUES ($nuovo_ida, $c_clean)") or die(mysqli_error($conn));
            }
        }
        if (!empty($_POST['docenti_lezione'])) {
            foreach ($_POST['docenti_lezione'] as $d_email) {
                $d_esc = mysqli_real_escape_string($conn, $d_email);
                mysqli_query($conn, "INSERT INTO assegnazione_docente_attivita (ida, email_dd, modalita_effettiva) VALUES ($nuovo_ida, '$d_esc', '')") or die(mysqli_error($conn));
            }
        }
        
        // ENGINE AUTOMATICO: Ricalcolo monte ore dello staff
        $form_init_q = mysqli_query($conn, "SELECT emailf FROM siform_iniziativa_formatore WHERE idi = $idi");
        while($f_row = mysqli_fetch_assoc($form_init_q)) {
            $fe = mysqli_real_escape_string($conn, $f_row['emailf']);
            $sum_q = mysqli_query($conn, "SELECT SUM(a.ore) as tot_ore FROM assegnazione_formatore_attivita af JOIN attivita a ON af.ida = a.ida WHERE a.idi = $idi AND af.emailf = '$fe'");
            $sum_row = mysqli_fetch_assoc($sum_q); $tot_ore = (float)($sum_row['tot_ore'] ?? 0.00);
            mysqli_query($conn, "UPDATE siform_iniziativa_formatore SET ore_totali_assegnate = $tot_ore WHERE idi = $idi AND emailf = '$fe'");
        }

        $success_message = "✅ Sessione salvata e monte ore staff aggiornato automaticamente!";
        $action = '';
    } else {
        $error_message = "❌ Errore nell'inserimento dell'attività: " . mysqli_error($conn);
    }
}

// ==========================================
// AZIONE: AGGIORNAMENTO ATTIVITÀ
// ==========================================
if (isset($_POST['update_attivita_submit'])) {
    $tipo = mysqli_real_escape_string($conn, $_POST['tipo']);
    $data_act = mysqli_real_escape_string($conn, $_POST['data']);
    $ora_inizio = mysqli_real_escape_string($conn, $_POST['ora_inizio']);
    $ora_fine = mysqli_real_escape_string($conn, $_POST['ora_fine']);
    $partecipazione = mysqli_real_escape_string($conn, $_POST['partecipazione']);
    $sede = mysqli_real_escape_string($conn, $_POST['sede']);
    $note = mysqli_real_escape_string($conn, $_POST['note']);
    $meet_custom = mysqli_real_escape_string($conn, $_POST['google_meet_link_custom']);
    
    $diff_secondi = strtotime($ora_fine) - strtotime($ora_inizio);
    if ($diff_secondi < 0) { $diff_secondi += 86400; }
    $ore = $diff_secondi / 3600;
    
    $meet_value = (empty(trim($meet_custom))) ? "NULL" : "'$meet_custom'";

    $sql_upd = "UPDATE attivita SET tipo='$tipo', data='$data_act', ora_inizio='$ora_inizio', ora_fine='$ora_fine', ore=$ore, partecipazione='$partecipazione', sede='$sede', note='$note', google_meet_link=$meet_value WHERE ida = $id_attivita AND idi = $idi";
    
    if (mysqli_query($conn, $sql_upd)) {
        mysqli_query($conn, "DELETE FROM assegnazione_classe_attivita WHERE ida = $id_attivita") or die(mysqli_error($conn));
        if (!empty($_POST['classi_lezione'])) {
            foreach ($_POST['classi_lezione'] as $c_id) {
                $c_clean = (int)$c_id;
                mysqli_query($conn, "INSERT INTO assegnazione_classe_attivita (ida, idc) VALUES ($id_attivita, $c_clean)") or die(mysqli_error($conn));
            }
        }
        
        mysqli_query($conn, "DELETE FROM assegnazione_docente_attivita WHERE ida = $id_attivita AND modalita_effettiva = ''") or die(mysqli_error($conn));
        if (!empty($_POST['docenti_lezione'])) {
            foreach ($_POST['docenti_lezione'] as $d_email) {
                $d_esc = mysqli_real_escape_string($conn, $d_email);
                $chk_d = mysqli_query($conn, "SELECT COUNT(*) as tot FROM assegnazione_docente_attivita WHERE ida = $id_attivita AND email_dd = '$d_esc'");
                if (mysqli_fetch_assoc($chk_d)['tot'] == 0) {
                    mysqli_query($conn, "INSERT INTO assegnazione_docente_attivita (ida, email_dd, modalita_effettiva) VALUES ($id_attivita, '$d_esc', '')") or die(mysqli_error($conn));
                }
            }
        }

        $formatori_scelti = isset($_POST['formatori_lezione']) ? $_POST['formatori_lezione'] : [];
        mysqli_query($conn, "DELETE FROM assegnazione_formatore_attivita WHERE ida = $id_attivita AND modalita_effettiva IN ('', 'Assente')") or die(mysqli_error($conn));
        
        foreach ($formatori_scelti as $f_email) {
            $f_esc = mysqli_real_escape_string($conn, $f_email);
            $check_f = mysqli_query($conn, "SELECT COUNT(*) as tot FROM assegnazione_formatore_attivita WHERE ida = $id_attivita AND emailf = '$f_esc'");
            if (mysqli_fetch_assoc($check_f)['tot'] == 0) {
                mysqli_query($conn, "INSERT INTO assegnazione_formatore_attivita (ida, emailf, modalita_effettiva) VALUES ($id_attivita, '$f_esc', '')") or die(mysqli_error($conn));
            }
        }
        
        // ENGINE AUTOMATICO: Aggiornamento ore staff in background
        $form_init_q = mysqli_query($conn, "SELECT emailf FROM siform_iniziativa_formatore WHERE idi = $idi");
        while($f_row = mysqli_fetch_assoc($form_init_q)) {
            $fe = mysqli_real_escape_string($conn, $f_row['emailf']);
            $sum_q = mysqli_query($conn, "SELECT SUM(a.ore) as tot_ore FROM assegnazione_formatore_attivita af JOIN attivita a ON af.ida = a.ida WHERE a.idi = $idi AND af.emailf = '$fe'");
            $sum_row = mysqli_fetch_assoc($sum_q); $tot_ore = (float)($sum_row['tot_ore'] ?? 0.00);
            mysqli_query($conn, "UPDATE siform_iniziativa_formatore SET ore_totali_assegnate = $tot_ore WHERE idi = $idi AND emailf = '$fe'");
        }
        
        $success_message = "✅ Attività modificata e monte ore sincronizzato con successo!";
        $action = '';
    } else {
        $error_message = "❌ Errore nella modifica: " . mysqli_error($conn);
    }
}

// ==========================================
// AZIONE: CANCELLAZIONE ATTIVITÀ
// ==========================================
if ($action === 'delete_attivita' && $id_attivita > 0) {
    mysqli_query($conn, "DELETE FROM attivita WHERE ida = $id_attivita AND idi = $idi") or die(mysqli_error($conn));
    mysqli_query($conn, "DELETE FROM assegnazione_formatore_attivita WHERE ida = $id_attivita") or die(mysqli_error($conn));
    mysqli_query($conn, "DELETE FROM assegnazione_classe_attivita WHERE ida = $id_attivita") or die(mysqli_error($conn));
    mysqli_query($conn, "DELETE FROM assegnazione_docente_attivita WHERE ida = $id_attivita") or die(mysqli_error($conn));
    
    $form_init_q = mysqli_query($conn, "SELECT emailf FROM siform_iniziativa_formatore WHERE idi = $idi");
    while($f_row = mysqli_fetch_assoc($form_init_q)) {
        $fe = mysqli_real_escape_string($conn, $f_row['emailf']);
        $sum_q = mysqli_query($conn, "SELECT SUM(a.ore) as tot_ore FROM assegnazione_formatore_attivita af JOIN attivita a ON af.ida = a.ida WHERE a.idi = $idi AND af.emailf = '$fe'");
        $sum_row = mysqli_fetch_assoc($sum_q); $tot_ore = (float)($sum_row['tot_ore'] ?? 0.00);
        mysqli_query($conn, "UPDATE siform_iniziativa_formatore SET ore_totali_assegnate = $tot_ore WHERE idi = $idi AND emailf = '$fe'");
    }
    
    $success_message = "🗑️ Sessione rimossa e ore ricalcolate.";
    $action = '';
}
?>

<div style="font-family: sans-serif; padding: 10px;">
    
    <?php if(!empty($success_message)): ?> <div style="background:#dff0d8; color:#3c763d; padding:12px; margin-bottom:15px; border:1px solid #d6e9c6; border-radius:4px; font-weight:bold;"><?php echo $success_message; ?></div> <?php endif; ?>
    <?php if(!empty($error_message)): ?> <div style="background:#f2dede; color:#a94442; padding:12px; margin-bottom:15px; border:1px solid #ebccd1; border-radius:4px; font-weight:bold;"><?php echo $error_message; ?></div> <?php endif; ?>

    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px; border-bottom:2px solid #cbd5e1; padding-bottom:10px;">
        <div>
            <h2 style="margin:0; color:#1e293b;">🗓️ Calendario Sessioni Didattiche</h2>
            <h3 style="margin:5px 0 0 0; color:#475569; font-weight:normal;"><?php echo htmlspecialchars($titolo_corso); ?></h3>
        </div>
        <div style="display:flex; gap:10px; align-items:center;">
            <?php if(empty($action)): ?>
                <?php if (is_categoria_ammessa_firme($corso['categoria'])): ?>
                    <form method="POST" style="margin:0;" onsubmit="return confirm('Confermi la firma automatica (Tutti in Presenza) di tutte le sessioni di questo corso?\n\n- I formatori e i docenti discenti abbinati a ciascuna sessione saranno registrati come Presenti.\n- Le firme e le presenze già inserite NON saranno sovrascritte.\n- Nessuna attività o orario verrà modificato.');">
                        <button type="submit" name="azione_firma_massiva_calendario" class="btn" style="background:#16a34a; color:white; padding:10px 15px; font-weight:bold; border:none; border-radius:4px; font-size:14px; cursor:pointer; box-shadow:0 1px 3px rgba(0,0,0,0.1);">
                            ⚡ Firma Tutte le Sessioni (Tutti in Presenza)
                        </button>
                    </form>
                <?php endif; ?>
                <a href="calendario.php?idi=<?php echo $idi; ?>&amp;action=add_attivita" class="btn btn-green" style="font-weight:bold; font-size:14px; padding:10px 15px; text-decoration:none;">➕ Aggiungi Lezione / Sessione</a>
            <?php endif; ?>
            <a href="index.php" class="btn" style="background:#475569; color:white; text-decoration:none; padding:10px 15px; border-radius:4px; font-weight:bold; font-size:14px;">⬅️ Torna alla Dashboard</a>
        </div>
    </div>

    <?php
    if ($action === 'add_attivita' || $action === 'edit_attivita'):
        $act_val = ['tipo' => 'Lezione', 'data' => '', 'ora_inizio' => '09:00', 'ora_fine' => '11:00', 'partecipazione' => 'Presenza/FAD', 'sede' => 'Montegiorgio', 'note' => '', 'google_meet_link' => ''];
        $formatori_selezionati = []; $classi_selezionate = []; $docenti_selezionati = [];

        if ($action === 'edit_attivita' && $id_attivita > 0) {
            $act_val = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM attivita WHERE ida = $id_attivita AND idi = $idi"));
            $f_sel_q = mysqli_query($conn, "SELECT emailf FROM assegnazione_formatore_attivita WHERE ida = $id_attivita");
            while($f = mysqli_fetch_assoc($f_sel_q)) { $formatori_selezionati[] = $f['emailf']; }
            $c_sel_q = mysqli_query($conn, "SELECT idc FROM assegnazione_classe_attivita WHERE ida = $id_attivita");
            while($c = mysqli_fetch_assoc($c_sel_q)) { $classi_selezionate[] = (int)$c['idc']; }
            $d_sel_q = mysqli_query($conn, "SELECT email_dd FROM assegnazione_docente_attivita WHERE ida = $id_attivita AND modalita_effettiva != 'Assente'");
            while($d = mysqli_fetch_assoc($d_sel_q)) { $docenti_selezionati[] = $d['email_dd']; }
        }

        $res_formatori = mysqli_query($conn, "SELECT f.emailf, f.nome, f.cognome FROM siform_iniziativa_formatore sif JOIN formatore f ON sif.emailf = f.emailf WHERE sif.idi = $idi ORDER BY f.cognome");
        $res_classi = mysqli_query($conn, "SELECT c.idc, c.nome FROM siform_iniziativa_classe sic JOIN classe c ON sic.idc = c.idc WHERE sic.idi = $idi ORDER BY c.nome");
        $res_docenti_subset = mysqli_query($conn, "SELECT d.email_dd, d.nome, d.cognome FROM siform_iniziativa_docentediscente sidd JOIN docente_discente d ON sidd.email_dd = d.email_dd WHERE sidd.idi = $idi ORDER BY d.cognome");
    ?>
        <h3><?php echo ($action === 'edit_attivita') ? "✏️ Modifica Dettagli Sessione Didattica" : "➕ Pianifica Nuova Sessione Didattica"; ?></h3>
        <form method="POST" action="" style="background:white; padding:20px; border:1px solid #cbd5e1; border-radius:6px; max-width:950px;">
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px; margin-bottom:15px;">
                <div style="grid-column: span 2;">
                    <label><strong>Tipo / Nome Attività:</strong></label><br>
                    <input type="text" name="tipo" value="<?php echo htmlspecialchars($act_val['tipo']); ?>" required style="width:100%; padding:8px; margin-top:4px;">
                </div>
                <div>
                    <label><strong>Data Svolgimento:</strong></label><br>
                    <input type="date" name="data" value="<?php echo $act_val['data']; ?>" required style="width:100%; padding:8px; margin-top:4px;">
                </div>
                <div>
                    <label>Modalità Partecipazione:</label><br>
                    <select name="partecipazione" style="width:100%; padding:8px; margin-top:4px;">
                        <option value="Presenza" <?php if($act_val['partecipazione']=='Presenza') echo 'selected'; ?>>Presenza</option>
                        <option value="FAD" <?php if($act_val['partecipazione']=='FAD') echo 'selected'; ?>>FAD / Online</option>
                        <option value="Presenza/FAD" <?php if($act_val['partecipazione']=='Presenza/FAD') echo 'selected'; ?>>Presenza/FAD (Mista)</option>
                    </select>
                </div>
                <div>
                    <label><strong>Ora Inizio:</strong></label><br>
                    <input type="time" name="ora_inizio" value="<?php echo substr($act_val['ora_inizio'],0,5); ?>" required style="width:100%; padding:8px; margin-top:4px;">
                </div>
                <div>
                    <label><strong>Ora Fine:</strong></label><br>
                    <input type="time" name="ora_fine" value="<?php echo substr($act_val['ora_fine'],0,5); ?>" required style="width:100%; padding:8px; margin-top:4px;">
                </div>
            </div>
            <div style="margin-bottom:15px;">
                <label>Sede Svolgimento:</label><br>
                <input type="text" name="sede" value="<?php echo htmlspecialchars($act_val['sede']); ?>" style="width:100%; padding:8px; margin-top:4px;">
            </div>
            <div style="margin-bottom:15px;">
                <label>Link Google Meet Dedicato (Override):</label><br>
                <input type="text" name="google_meet_link_custom" placeholder="Lascia vuoto per default" value="<?php echo htmlspecialchars($act_val['google_meet_link'] ?? ''); ?>" style="width:100%; padding:8px; margin-top:4px;">
            </div>
            <div style="margin-bottom:15px;">
                <label>Note Interne Lezione:</label><br>
                <textarea name="note" style="width:100%; height:50px; margin-top:4px;"><?php echo htmlspecialchars($act_val['note'] ?? ''); ?></textarea>
            </div>

            <div style="display:flex; gap:20px; background:#f8fafc; padding:15px; border:1px solid #e2e8f0; margin-bottom:20px; border-radius:4px;">
                <div style="flex:1; max-height:220px; overflow-y:auto;">
                    <strong style="color:#d9534f; display:block; border-bottom:1px solid #ddd; padding-bottom:3px; margin-bottom:5px;">Formatori coinvolti:</strong>
                    <?php while($f = mysqli_fetch_assoc($res_formatori)) { $chk = in_array($f['emailf'], $formatori_selezionati) ? 'checked' : ''; ?>
                        <label style="display:block; margin-bottom:4px;"><input type="checkbox" name="formatori_lezione[]" value="<?php echo $f['emailf']; ?>" <?php echo $chk; ?>> <?php echo $f['cognome']." ".$f['nome']; ?></label>
                    <?php } ?>
                </div>
                
                <div style="flex:0.8; max-height:220px; overflow-y:auto;">
                    <strong style="color:#f0ad4e; display:block; border-bottom:1px solid #ddd; padding-bottom:3px; margin-bottom:5px;">Classi Convocate:</strong>
                    <?php while($c = mysqli_fetch_assoc($res_classi)) { 
                        // FIX: Pre-selezione automatica abilitata su creazione nuova lezione
                        $chk = ($action === 'add_attivita' || in_array((int)$c['idc'], $classi_selezionate)) ? 'checked' : ''; 
                    ?>
                        <label style="display:block; margin-bottom:4px;"><input type="checkbox" name="classi_lezione[]" value="<?php echo $c['idc']; ?>" <?php echo $chk; ?>> <?php echo $c['nome']; ?></label>
                    <?php } ?>
                </div>

                <div style="flex:1.2; max-height:220px; overflow-y:auto; border-left:1px solid #e2e8f0; padding-left:15px;">
                    <strong style="color:#8b5cf6; display:block; border-bottom:1px solid #ddd; padding-bottom:3px; margin-bottom:2px;">Docenti Discenti (Subset Corso):</strong>
                    
                    <div style="margin-bottom:8px; font-size:11px;">
                        <a href="#" onclick="toggleAllDocenti(true); return false;" style="color:#0275d8; text-decoration:none; font-weight:bold; margin-right:10px;">☑️ Spunta tutti</a>
                        <a href="#" onclick="toggleAllDocenti(false); return false;" style="color:#d9534f; text-decoration:none; font-weight:bold;">⬜ Svuota tutti</a>
                    </div>

                    <?php if(mysqli_num_rows($res_docenti_subset) == 0) echo "<small style='color:#777;'>Nessun discente abbinato all'iniziativa madre.</small>";
                    while($d = mysqli_fetch_assoc($res_docenti_subset)) { 
                        $chk = ($action === 'add_attivita' || in_array($d['email_dd'], $docenti_selezionati)) ? 'checked' : ''; 
                    ?>
                        <label style="display:block; margin-bottom:4px;"><input type="checkbox" name="docenti_lezione[]" class="chk-docente" value="<?php echo $d['email_dd']; ?>" <?php echo $chk; ?>> <?php echo $d['cognome']." ".$d['nome']; ?></label>
                    <?php } ?>
                </div>
            </div>

            <button type="submit" name="<?php echo ($action === 'edit_attivita') ? 'update_attivita_submit' : 'salva_nuova_attivita'; ?>" class="btn btn-green">Salva Sessione Didattica</button>
            <a href="calendario.php?idi=<?php echo $idi; ?>" class="btn" style="background:#777; color:white; text-decoration:none; padding:7px 12px; border-radius:4px; display:inline-block; margin-left:5px;">Annulla</a>
        </form>
    <?php else: ?>
        <table style="width:100%; border-collapse:collapse; background:white; box-shadow: 0 1px 3px rgba(0,0,0,0.1); font-family:sans-serif; margin-top:10px;">
            <thead>
                <tr style="background:#f1f5f9; border-bottom:2px solid #cbd5e1;">
                    <th style="padding:10px; text-align:left; width:13%;">Data Lezione</th>
                    <th style="padding:10px; text-align:left; width:12%;">Orario (Durata)</th>
                    <th style="padding:10px; text-align:left; width:33%;">Sede e Aula Google Meet (Override)</th>
                    <th style="padding:10px; text-align:left; width:22%;">Risorse e Attori Abbinati</th>
                    <th style="padding:10px; text-align:center; width:10%;">Stato Firma</th>
                    <th style="padding:10px; text-align:center; width:10%;">Azioni</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $attivita_q = mysqli_query($conn, "SELECT * FROM attivita WHERE idi = $idi ORDER BY data ASC, ora_inizio ASC");
                while ($act = mysqli_fetch_assoc($attivita_q)) {
                    $w_day = date('w', strtotime($act['data'])); $giorno_sett = $giorni_it[$w_day];
                    
                    $f_lez_q = mysqli_query($conn, "SELECT f.cognome, f.nome, af.modalita_effettiva FROM assegnazione_formatore_attivita af JOIN formatore f ON af.emailf = f.emailf WHERE af.ida = " . $act['ida']);
                    $formatori_nomi = []; $stato_lezione_firma = ''; 
                    while ($f = mysqli_fetch_assoc($f_lez_q)) { $formatori_nomi[] = htmlspecialchars($f['cognome'] . " " . substr($f['nome'], 0, 1) . "."); $stato_lezione_firma = $f['modalita_effettiva']; }
                    $stringa_formatori_cella = !empty($formatori_nomi) ? implode(', ', $formatori_nomi) : '<span style="color:#cbd5e1; font-style:italic;">Nessuno</span>';

                    $c_lez_q = mysqli_query($conn, "SELECT c.nome FROM assegnazione_classe_attivita ac JOIN classe c ON ac.idc = c.idc WHERE ac.ida = " . $act['ida']);
                    $classi_nomi = []; while ($c = mysqli_fetch_assoc($c_lez_q)) { $classi_nomi[] = htmlspecialchars($c['nome']); }
                    $stringa_classi_cella = !empty($classi_nomi) ? implode(', ', $classi_nomi) : '<span style="color:#94a3b8; font-style:italic;">Nessuna</span>';

                    $d_lez_q = mysqli_query($conn, "SELECT d.cognome, d.nome FROM assegnazione_docente_attivita ad JOIN docente_discente d ON ad.email_dd = d.email_dd WHERE ad.ida = " . $act['ida'] . " AND ad.modalita_effettiva != 'Assente'");
                    $doc_nomi = []; while ($d = mysqli_fetch_assoc($d_lez_q)) { $doc_nomi[] = htmlspecialchars($d['cognome'] . " " . substr($d['nome'], 0, 1) . "."); }
                    $stringa_docenti_cella = !empty($doc_nomi) ? implode(', ', $doc_nomi) : '<span style="color:#cbd5e1; font-style:italic;">Nessuno</span>';

                    if ($stato_lezione_firma === '' || $stato_lezione_firma === null) {
                        $badge_grafico = '<span style="background:#f1f5f9; color:#475569; border:1px solid #cbd5e1; padding:3px 6px; font-size:11px; font-weight:bold; border-radius:4px; text-transform:uppercase; display:inline-block; text-align:center; width:90px;">⏳ Da Firmare</span>';
                    } elseif ($stato_lezione_firma === 'Assente') {
                        $badge_grafico = '<span style="background:#fef2f2; color:#b91c1c; border:1px solid #fca5a5; padding:3px 6px; font-size:11px; font-weight:bold; border-radius:4px; text-transform:uppercase; display:inline-block; text-align:center; width:90px;">❌ Assente</span>';
                    } else {
                        $badge_grafico = '<span style="background:#f0fdf4; color:#166534; border:1px solid #86efac; padding:3px 6px; font-size:11px; font-weight:bold; border-radius:4px; text-transform:uppercase; display:inline-block; text-align:center; width:90px;">🟢 Firmata</span>';
                    }
                    
                    $usa_custom = !empty($act['google_meet_link']);
                    $meet_corrente = $usa_custom ? $act['google_meet_link'] : $corso['google_meet_link'];
                    $is_online = (stripos($act['partecipazione'], 'FAD') !== false || stripos($act['partecipazione'], 'ONLINE') !== false || stripos($act['partecipazione'], 'Mista') !== false);
                ?>
                    <tr style="border-bottom:1px solid #e2e8f0;">
                        <td style="padding:10px; font-size:14px;"><strong><?php echo $giorno_sett; ?></strong><br><span style="color:#0275d8; font-weight:600;"><?php echo date('d/m/Y', strtotime($act['data'])); ?></span></td>
                        <td style="padding:10px; font-size:14px;"><strong><?php echo substr($act['ora_inizio'], 0, 5) . " - " . substr($act['ora_fine'], 0, 5); ?></strong><br><small style="color:#64748b;">(<?php echo number_format($act['ore'], 1); ?> ore)</small></td>
                        <td style="padding:10px; font-size:13px; vertical-align:middle;">
                            <span style="font-weight:600; text-transform:uppercase;"><?php echo htmlspecialchars($act['tipo']." @ ".$act['sede']); ?></span> - <small style="background:#f1f5f9; padding:1px 4px; border-radius:3px; font-size:11px; color:#475569; font-weight:bold;"><?php echo htmlspecialchars($act['partecipazione']); ?></small>
                            <?php if($is_online && !empty($meet_corrente)): ?><div style="margin-top:3px; font-size:11px;"><a href="<?php echo htmlspecialchars($meet_corrente); ?>" target="_blank" style="color:#16a34a; font-weight:bold; text-decoration:none;">💻 Aula Meet ➔</a></div><?php endif; ?>
                        </td>
                        <td style="padding:10px; font-size:13px; line-height:1.35;">
                            <div>👤 <strong>Staff:</strong> <?php echo $stringa_formatori_cella; ?></div>
                            <div>👥 <strong>Classi:</strong> <?php echo $stringa_classi_cella; ?></div>
                            <div>🎓 <strong>Discenti:</strong> <?php echo $stringa_docenti_cella; ?></div>
                        </td>
                        <td style="padding:10px; text-align:center; vertical-align:middle;"><?php echo $badge_grafico; ?></td>
                        <td style="padding:10px; text-align:center; vertical-align:middle; white-space:nowrap;">
                            <a href="foglio_firme.php?idi=<?php echo $idi; ?>&amp;ida=<?php echo $act['ida']; ?>" class="btn" style="background:#16a34a; color:white; text-decoration:none; padding:3px 5px; font-size:12px; font-weight:bold; border-radius:4px; display:inline-block;">✍️</a>
                            <a href="calendario.php?idi=<?php echo $idi; ?>&amp;action=edit_attivita&amp;ida=<?php echo $act['ida']; ?>" class="btn" style="background:#0275d8; color:white; text-decoration:none; padding:3px 5px; font-size:12px; font-weight:bold; border-radius:4px; display:inline-block; margin-left:2px;">✏️</a>
                            <a href="calendario.php?idi=<?php echo $idi; ?>&amp;action=delete_attivita&amp;ida=<?php echo $act['ida']; ?>" class="btn" style="background:#d9534f; color:white; text-decoration:none; padding:3px 5px; font-size:12px; font-weight:bold; border-radius:4px; display:inline-block; margin-left:2px;" onclick="return confirm('Rimuovere questa lezione?');">🗑️</a>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<script>
function toggleAllDocenti(status) {
    const checkboxes = document.querySelectorAll('.chk-docente');
    checkboxes.forEach(chk => {
        chk.checked = status;
    });
}
</script>