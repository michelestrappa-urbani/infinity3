<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// include('auth.php'); // DISATTIVATO TEMPORANEAMENTE PER ACCESSO LIBERO
include('db.php');
include('navbar.php');

$idi = isset($_GET['idi']) ? (int)$_GET['idi'] : 0;
$ida = isset($_GET['ida']) ? (int)$_GET['ida'] : 0;
$success_msg = "";

// ==========================================
// AZIONE: SALVATAGGIO DELLE PRESENZE E ORE REALI
// ==========================================
if (isset($_POST['salva_firme_submit'])) {
    // 1. Salvataggio Formatori
    if (!empty($_POST['f_modalita'])) {
        foreach ($_POST['f_modalita'] as $emailf => $mod) {
            $emailf_esc = mysqli_real_escape_string($conn, $emailf);
            $mod_esc = mysqli_real_escape_string($conn, $mod);
            $ore_eff = isset($_POST['f_ore'][$emailf]) ? (float)$_POST['f_ore'][$emailf] : 0.0;
            
            // Verifica se questo formatore era originariamente pianificato a calendario per questa lezione
            $check_p = mysqli_query($conn, "SELECT COUNT(*) as cont FROM assegnazione_formatore_attivita WHERE ida = $ida AND emailf = '$emailf_esc'");
            $was_planned = (mysqli_fetch_assoc($check_p)['cont'] > 0);
            
            mysqli_query($conn, "DELETE FROM assegnazione_formatore_attivita WHERE ida = $ida AND emailf = '$emailf_esc'");
            
            if ($mod_esc !== 'Assente') {
                // Se è presente o FAD lo inseriamo sempre
                mysqli_query($conn, "INSERT INTO assegnazione_formatore_attivita (ida, emailf, modalita_effettiva, ore_effettive) VALUES ($ida, '$emailf_esc', '$mod_esc', $ore_eff)");
            } else {
                // Se è assente, lo inseriamo SOLO se era originariamente pianificato a calendario
                if ($was_planned) {
                    mysqli_query($conn, "INSERT INTO assegnazione_formatore_attivita (ida, emailf, modalita_effettiva, ore_effettive) VALUES ($ida, '$emailf_esc', 'Assente', 0.0)");
                }
            }
        }
    }

    // 2. Salvataggio Docenti Discenti
    if (!empty($_POST['d_modalita'])) {
        foreach ($_POST['d_modalita'] as $email_dd => $mod) {
            $email_dd_esc = mysqli_real_escape_string($conn, $email_dd);
            $mod_esc = mysqli_real_escape_string($conn, $mod);
            $ore_eff = isset($_POST['d_ore'][$email_dd]) ? (float)$_POST['d_ore'][$email_dd] : 0.0;
            
            mysqli_query($conn, "DELETE FROM assegnazione_docente_attivita WHERE ida = $ida AND email_dd = '$email_dd_esc'");
            mysqli_query($conn, "INSERT INTO assegnazione_docente_attivita (ida, email_dd, modalita_effettiva, ore_effettive) VALUES ($ida, '$email_dd_esc', '$mod_esc', $ore_eff)");
        }
    }
    $success_msg = "✅ Registro presenze e ore effettive salvato correttamente.";
}

if ($ida > 0) {
    $act = mysqli_fetch_assoc(mysqli_query($conn, "SELECT a.*, i.titolo, i.nome_siform FROM attivita a JOIN iniziativa i ON a.idi = i.idi WHERE a.ida = $ida"));
    $titolo_corso = !empty($act['titolo']) ? $act['titolo'] : $act['nome_siform'];
    $idi = (int)$act['idi'];
    $max_ore_lezione = (float)$act['ore'];

    // Carica stati reali
    $formatori_stati = []; $formatori_ore = [];
    $rf = mysqli_query($conn, "SELECT emailf, modalita_effettiva, ore_effettive FROM assegnazione_formatore_attivita WHERE ida = $ida");
    while($r = mysqli_fetch_assoc($rf)) { 
        $formatori_stati[$r['emailf']] = $r['modalita_effettiva']; 
        $formatori_ore[$r['emailf']] = $r['ore_effettive']; 
    }

    $docenti_stati = []; $docenti_ore = [];
    $rd = mysqli_query($conn, "SELECT email_dd, modalita_effettiva, ore_effettive FROM assegnazione_docente_attivita WHERE ida = $ida");
    while($r = mysqli_fetch_assoc($rd)) { 
        $docenti_stati[$r['email_dd']] = $r['modalita_effettiva']; 
        $docenti_ore[$r['email_dd']] = $r['ore_effettive']; 
    }

    $is_plenaria_originale = (count($docenti_stati) == 0 || (count($docenti_stati) > 0 && isset($docenti_stati[key($docenti_stati)]) && $docenti_stati[key($docenti_stati)] !== ''));

    $formatori_res = mysqli_query($conn, "SELECT f.emailf, f.nome, f.cognome FROM siform_iniziativa_formatore sif JOIN formatore f ON sif.emailf = f.emailf WHERE sif.idi = $idi ORDER BY f.cognome");
    $docenti_res = mysqli_query($conn, "SELECT d.email_dd, d.nome, d.cognome FROM siform_iniziativa_docentediscente sidd JOIN docente_discente d ON sidd.email_dd = d.email_dd WHERE sidd.idi = $idi ORDER BY d.cognome");
} elseif ($idi > 0) {
    $ini = mysqli_fetch_assoc(mysqli_query($conn, "SELECT titolo, nome_siform FROM iniziativa WHERE idi = $idi"));
    $titolo_corso = !empty($ini['titolo']) ? $ini['titolo'] : $ini['nome_siform'];
    $attivita_res = mysqli_query($conn, "SELECT * FROM attivita WHERE idi = $idi ORDER BY data, ora_inizio");
} else {
    $iniziative_res = mysqli_query($conn, "SELECT i.* FROM iniziativa i ORDER BY LENGTH(SUBSTRING_INDEX(i.categoria, ' ', 1)) ASC, i.categoria ASC");
}
?>

<div style="font-family: sans-serif; padding: 10px;">
    <?php if(!empty($success_msg)): ?> <div style="background:#dff0d8; color:#3c763d; padding:12px; margin-bottom:20px; border:1px solid #d6e9c6; border-radius:4px;"><?php echo $success_msg; ?></div> <?php endif; ?>

    <?php if ($ida > 0): ?>
        <h2>✍️ Compilazione Registro Firme e Computo Ore</h2>
        <h3>Corso: <span style="color:#0275d8;"><?php echo htmlspecialchars($titolo_corso); ?></span></h3>
        
        <div style="background:#f8fafc; border:1px solid #ddd; padding:12px; margin-bottom:20px; border-radius:4px; font-size:14px; display:flex; gap:30px;">
            <div>Lezione del: <strong><?php echo date('d/m/Y', strtotime($act['data'])); ?></strong></div>
            <div>Durata Massima: <strong style="color:#c9302c;"><?php echo number_format($max_ore_lezione, 1); ?> h</strong></div>
            <div>Modalità: <strong><?php echo htmlspecialchars($act['partecipazione']); ?></strong></div>
            <div>Sede: <strong>📍 <?php echo htmlspecialchars($act['sede']); ?></strong></div>
        </div>

        <form method="POST" action="">
            <h4 style="color:#c9302c; border-bottom:2px solid #c9302c; padding-bottom:4px;">👔 Presenze & Ore Staff Formatori</h4>
            <table style="margin-bottom:30px;">
                <tr style="background:#f5f5f5;"><th>Cognome e Nome</th><th>Stato Presenza</th><th style="width:180px;">Ore Svolte Effettive</th></tr>
                <?php while($f = mysqli_fetch_assoc($formatori_res)): 
                    $current_status_f = $formatori_stati[$f['emailf']] ?? '';
                    if (empty($current_status_f)) {
                        $current_status_f = isset($formatori_stati[$f['emailf']]) ? (($act['partecipazione'] === 'Presenza/FAD') ? 'Presenza' : $act['partecipazione']) : 'Assente';
                    }
                    $current_ore_f = isset($formatori_ore[$f['emailf']]) && $formatori_ore[$f['emailf']] !== null ? (float)$formatori_ore[$f['emailf']] : ($current_status_f === 'Assente' ? 0.0 : $max_ore_lezione);
                ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($f['cognome']." ".$f['nome']); ?></strong></td>
                    <td>
                        <select name="f_modalita[<?php echo $f['emailf']; ?>]" class="sel-status" onchange="checkAssenza(this, <?php echo $max_ore_lezione; ?>)" style="padding:6px; font-weight:bold; width:150px;">
                            <option value="Assente" <?php if($current_status_f === 'Assente') echo 'selected'; ?>>❌ Assente</option>
                            <option value="Presenza" <?php if($current_status_f === 'Presenza') echo 'selected'; ?>>🏫 Presenza</option>
                            <option value="FAD" <?php if($current_status_f === 'FAD') echo 'selected'; ?>>🏠 FAD</option>
                        </select>
                    </td>
                    <td>
                        <input type="number" step="0.5" min="0" max="<?php echo $max_ore_lezione; ?>" name="f_ore[<?php echo $f['emailf']; ?>]" value="<?php echo $current_ore_f; ?>" class="input-hours" style="padding:5px; width:80px; font-weight:bold; text-align:center;"> <strong>h</strong>
                    </td>
                </tr>
                <?php endwhile; ?>
            </table>

            <h4 style="color:#449d44; border-bottom:2px solid #449d44; padding-bottom:4px;">🧑‍🏫 Presenze & Ore Docenti Discenti</h4>
            <table style="margin-bottom:20px;">
                <tr style="background:#f5f5f5;"><th>Cognome e Nome</th><th>Stato Presenza</th><th style="width:180px;">Ore Svolte Effettive</th></tr>
                <?php while($d = mysqli_fetch_assoc($docenti_res)): 
                    $current_status_d = $docenti_stati[$d['email_dd']] ?? '';
                    if ($current_status_d === '') {
                        if ($is_plenaria_originale) {
                            $current_status_d = ($act['partecipazione'] === 'Presenza/FAD') ? 'Presenza' : $act['partecipazione'];
                        } else {
                            $current_status_d = isset($docenti_stati[$d['email_dd']]) ? (($act['partecipazione'] === 'Presenza/FAD') ? 'Presenza' : $act['partecipazione']) : 'Assente';
                        }
                    }
                    $current_ore_doc = isset($docenti_ore[$d['email_dd']]) && $docenti_ore[$d['email_dd']] !== null ? (float)$docenti_ore[$d['email_dd']] : ($current_status_d === 'Assente' ? 0.0 : $max_ore_lezione);
                ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($d['cognome']." ".$d['nome']); ?></strong></td>
                    <td>
                        <select name="d_modalita[<?php echo $d['email_dd']; ?>]" class="sel-status" onchange="checkAssenza(this, <?php echo $max_ore_lezione; ?>)" style="padding:6px; font-weight:bold; width:150px;">
                            <option value="Assente" <?php if($current_status_d === 'Assente') echo 'selected'; ?>>❌ Assente</option>
                            <option value="Presenza" <?php if($current_status_d === 'Presenza') echo 'selected'; ?>>🏫 Presenza</option>
                            <option value="FAD" <?php if($current_status_d === 'FAD') echo 'selected'; ?>>🏠 FAD</option>
                        </select>
                    </td>
                    <td>
                        <input type="number" step="0.5" min="0" max="<?php echo $max_ore_lezione; ?>" name="d_ore[<?php echo $d['email_dd']; ?>]" value="<?php echo $current_ore_doc; ?>" class="input-hours" style="padding:5px; width:80px; font-weight:bold; text-align:center;"> <strong>h</strong>
                    </td>
                </tr>
                <?php endwhile; ?>
            </table>
            
            <button type="submit" name="salva_firme_submit" class="btn btn-green" style="font-size:16px; padding:10px 20px;">💾 Salva e Precompila Registro</button>
            <a href="foglio_firme.php?idi=<?php echo $idi; ?>" class="btn btn-blue" style="background:#777; padding:10px 15px; text-decoration:none; color:white;">Annulla</a>
        </form>

        <script>
        function checkAssenza(selectEl, maxHours) {
            const tr = selectEl.closest('tr');
            const inputHours = tr.querySelector('.input-hours');
            if(inputHours) {
                if(selectEl.value === 'Assente') { inputHours.value = 0; } 
                else if(parseFloat(inputHours.value) === 0) { inputHours.value = maxHours; }
            }
        }
        </script>

    <?php elseif ($idi > 0): ?>
        <h2>✍️ Seleziona la Lezione da Firmare</h2>
        <h3>Corso: <span style="color:#0275d8;"><?php echo htmlspecialchars($titolo_corso); ?></span></h3>
        <a href="foglio_firme.php" class="btn btn-blue" style="background:#777; margin-bottom:15px; text-decoration:none; color:white;">⬅️ Torna indietro</a>
        <table>
            <tr style="background:#f5f5f5;"><th>Data e Orario</th><th>Durata Lezione</th><th>Tipo di Attività</th><th>Modalità</th><th>Sede</th><th>Azione</th></tr>
            <?php while($row = mysqli_fetch_assoc($attivita_res)): ?>
            <tr>
                <td><strong><?php echo date('d/m/Y', strtotime($row['data'])); ?></strong> <small>(<?php echo substr($row['ora_inizio'],0,5); ?>)</small></td>
                <td><strong><?php echo number_format($row['ore'], 2); ?> h</strong></td>
                <td><strong><?php echo htmlspecialchars($row['tipo']); ?></strong></td>
                <td><span style="padding:3px 6px; background:#e2e8f0; border-radius:3px; font-weight:bold; font-size:12px;"><?php echo htmlspecialchars($row['partecipazione']); ?></span></td>
                <td>📍 <?php echo htmlspecialchars($row['sede']); ?></td>
                <td><a href="foglio_firme.php?ida=<?php echo $row['ida']; ?>" class="btn btn-green">✍️ Apri Registro Firme</a></td>
            </tr>
            <?php endwhile; ?>
        </table>
    <?php else: ?>
        <h2>✍️ Registro d'Aula: Gestione Fogli Firme SIFORM</h2>
        <table>
            <tr style="background:#f5f5f5;"><th>Iniziativa / Progetto</th><th>Macro-Categoria SIFORM</th><th>Azione</th></tr>
            <?php while($row = mysqli_fetch_assoc($iniziative_res)): 
                if(in_array(substr($row['categoria'], 0, 5), ['B.1.1', 'B.1.2', 'B.1.3', 'B.1.4', 'B.1.5', 'B.1.6'])) continue;
                $titolo = !empty($row['titolo']) ? $row['titolo'] : $row['nome_siform'];
            ?>
            <tr>
                <td><strong><?php echo htmlspecialchars($titolo); ?></strong></td>
                <td><small style="color:#555; font-weight:bold;"><?php echo htmlspecialchars($row['categoria']); ?></small></td>
                <td><a href="foglio_firme.php?idi=<?php echo $row['idi']; ?>" class="btn btn-blue">🗓️ Vedi Lezioni</a></td>
            </tr>
            <?php endwhile; ?>
        </table>
    <?php endif; ?>
</div>