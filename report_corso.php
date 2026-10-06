<?php
// Forza la visibilità degli errori per facilitare il controllo su Altervista
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// include('auth.php'); // DISATTIVATO TEMPORANEAMENTE PER ACCESSO LIBERO
include('db.php');
include('navbar.php');

$idi = isset($_GET['idi']) ? (int)$_GET['idi'] : 0;

if ($idi <= 0) { 
    die("❌ Specificare un corso valido."); 
}

// 1. RECUPERA I DETTAGLI COMPLETI DEL CORSO
$corso_q = mysqli_query($conn, "SELECT titolo, nome_siform, categoria FROM iniziativa WHERE idi = $idi");
$corso = mysqli_fetch_assoc($corso_q);
if (!$corso) { die("❌ Iniziativa non trovata nel database."); }
$categoria_attuale = $corso['categoria'] ?? '';
$titolo_corso = !empty($corso['titolo']) ? $corso['titolo'] : $corso['nome_siform'];

$is_b17 = ($categoria_attuale === 'B.1.7 Formazione dei formatori');

// 2. LOGICA: CALCOLA IL TOTALE DEL CORSO DAL CALENDARIO PIANIFICATO (AGGIUNTI ORARI IN SELECT)
$attivita_res = mysqli_query($conn, "SELECT ida, tipo, data, ora_inizio, ora_fine, ore, partecipazione, sede FROM attivita WHERE idi = $idi ORDER BY data ASC, ora_inizio ASC");
$lezioni = []; 
$ore_totali_corso = 0.0;
while ($act = mysqli_fetch_assoc($attivita_res)) {
    $lezioni[] = $act;
    $ore_totali_corso += (float)$act['ore'];
}

// Array per la conversione dei giorni della settimana in Italiano
$giorni_it = ['dom', 'lun', 'mar', 'mer', 'gio', 'ven', 'sab'];

// 3. CARICAMENTO DELLE MAPPE DI PRESENZA IN MEMORIA
$docenti_modalita_mappa = []; 
$docenti_ore_mappa = []; 
$lezione_ha_docenti_assegnati = []; 
$docenti_lezione_compilata = [];

$formatori_modalita_mappa = []; 
$formatori_ore_mappa = []; 
$formatori_lezione_compilata = [];

if (count($lezioni) > 0) {
    $ide_string = implode(',', array_column($lezioni, 'ida'));
    
    // Configurazione Mappe per i Docenti Discenti
    $check_q = mysqli_query($conn, "SELECT ida, COUNT(*) as contr FROM assegnazione_docente_attivita WHERE ida IN ($ide_string) GROUP BY ida");
    while ($r = mysqli_fetch_assoc($check_q)) { 
        if ((int)$r['contr'] > 0) { $lezione_ha_docenti_assegnati[$r['ida']] = true; } 
    }
    
    $det_doc_q = mysqli_query($conn, "SELECT ida, email_dd, modalita_effettiva, ore_effettive FROM assegnazione_docente_attivita WHERE ida IN ($ide_string)");
    while ($r = mysqli_fetch_assoc($det_doc_q)) {
        $docenti_modalita_mappa[$r['ida']][$r['email_dd']] = $r['modalita_effettiva'];
        $docenti_ore_mappa[$r['ida']][$r['email_dd']] = $r['ore_effettive'];
        if (!empty($r['modalita_effettiva'])) { $docenti_lezione_compilata[$r['ida']] = true; }
    }
    
    // Configurazione Mappe per i Formatori Staff
    $det_form_q = mysqli_query($conn, "SELECT ida, emailf, modalita_effettiva, ore_effettive FROM assegnazione_formatore_attivita WHERE ida IN ($ide_string)");
    while ($r = mysqli_fetch_assoc($det_form_q)) {
        $formatori_modalita_mappa[$r['ida']][$r['emailf']] = $r['modalita_effettiva'];
        $formatori_ore_mappa[$r['ida']][$r['emailf']] = $r['ore_effettive'];
        if (!empty($r['modalita_effettiva'])) { $formatori_lezione_compilata[$r['ida']] = true; }
    }
}

// 4. CARICAMENTO DELLE ANAGRAFICHE
$formatori_res = mysqli_query($conn, "SELECT f.emailf, f.nome, f.cognome FROM siform_iniziativa_formatore sif JOIN formatore f ON sif.emailf = f.emailf WHERE sif.idi = $idi ORDER BY f.cognome ASC");
$docenti_res = mysqli_query($conn, "SELECT d.email_dd, d.nome, d.cognome FROM siform_iniziativa_docentediscente sidd JOIN docente_discente d ON sidd.email_dd = d.email_dd WHERE sidd.idi = $idi ORDER BY d.cognome ASC");
?>

<div style="font-family: sans-serif; padding: 10px;">
    <h2>📋 Registro Scrutinio Analitico delle Presenze e Ore Reali</h2>
    <strong style="color:#0275d8; font-size:16px;"><?php echo htmlspecialchars($titolo_corso); ?></strong> <span style="color:#666;">(<?php echo htmlspecialchars($categoria_attuale); ?>)</span>
    <br><br>

    <div style="background:#f8fafc; border:1px solid #e2e8f0; padding:12px; border-radius:6px; margin-bottom:15px; font-size:13px; display:flex; gap:35px;">
        <div>Ore Complessive Totali Corso: <strong style="color:#c9302c;"><?php echo number_format($ore_totali_corso, 1); ?> h</strong></div>
        <?php if($is_b17): ?>
            <div>Soglia Minima Ammissione Discenti (75%): <strong><?php echo number_format($ore_totali_corso * 0.75, 1); ?> h</strong></div>
        <?php endif; ?>
    </div>

    <div style="width:100%; overflow-x:auto; border: 1px solid #cbd5e1; border-radius: 4px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
        <table style="width:100%; border-collapse: collapse; background: white; font-size:13px;">
            <thead>
                <tr style="background: #f1f5f9; border-bottom: 2px solid #cbd5e1;">
                    <th style="padding: 12px; text-align: left; min-width: 230px; position: sticky; left: 0; background: #f1f5f9; z-index: 3; border-right:2px solid #cbd5e1;">Anagrafica Risorsa</th>
                    
                    <?php foreach ($lezioni as $index => $lez): 
                        $w_day = date('w', strtotime($lez['data']));
                        $giorno_settimana = $giorni_it[$w_day];
                    ?>
                        <th style="padding: 10px; text-align: center; min-width: 140px; font-size:11px; border-right:1px solid #cbd5e1; background:#f8fafc; line-height: 1.4;">
                            <span style="color:#0275d8; font-weight:bold;">Lezione <?php echo ($index + 1); ?></span><br>
                            <small style="font-weight:600; text-transform: lowercase;"><?php echo $giorno_settimana . ' ' . date('d/m', strtotime($lez['data'])); ?></small><br>
                            <!-- INSERITI ORA INIZIO E ORA FINE -->
                            <small style="color:#475569; font-weight:bold; font-size:10px;"><?php echo substr($lez['ora_inizio'], 0, 5) . ' - ' . substr($lez['ora_fine'], 0, 5); ?></small><br>
                            <strong><?php echo number_format($lez['ore'], 1); ?> h</strong><br>
                            <span style="font-size:10px; font-style:italic; font-weight:bold; color:#777;"><?php echo htmlspecialchars($lez['partecipazione']); ?></span>
                        </th>
                    <?php endforeach; ?>
                    
                    <th style="padding: 12px; text-align: center; width: 130px; background:#f8fafc; font-weight:bold; border-left:2px solid #cbd5e1;">Ore Fatte / Totali</th>
                    <?php if ($is_b17): ?>
                        <th style="padding: 12px; text-align: center; width: 85px; background:#f8fafc; font-weight:bold;">% Freq.</th>
                        <th style="padding: 12px; text-align: center; width: 115px; background:#f8fafc; font-weight:bold;">Esito Corso</th>
                    <?php else: ?>
                        <th style="padding: 12px; text-align: center; width: 115px; background:#f8fafc; font-weight:bold;">Sessioni Svolte</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                
                <tr style="background:#f1f5f9; font-weight:bold; color:#475569;">
                    <td colspan="<?php echo count($lezioni) + ($is_b17 ? 4 : 3); ?>" style="padding:8px 12px; border-bottom:1px solid #cbd5e1;">
                        👔 STAFF / FORMATORI (Computo individuale ore svolte)
                    </td>
                </tr>
                <?php while ($form = mysqli_fetch_assoc($formatori_res)): 
                    $emailf = $form['emailf']; $ore_fatte_f = 0.0; $sess_f = 0;
                ?>
                <tr style="border-bottom: 1px solid #e2e8f0;">
                    <td style="padding: 12px; font-weight: bold; position: sticky; left: 0; background: white; z-index: 1; border-right:2px solid #cbd5e1;"><?php echo htmlspecialchars($form['cognome'] . ' ' . $form['nome']); ?></td>
                    <?php foreach ($lezioni as $lez): 
                        $ida = $lez['ida']; $ore_lezione = (float)$lez['ore']; $presente_f = false; $mod_f = ''; $ore_singole_f = 0.0;
                        
                        if (isset($formatori_lezione_compilata[$ida])) {
                            $status_f = $formatori_modalita_mappa[$ida][$emailf] ?? '';
                            if ($status_f === 'Presenza' || $status_f === 'FAD') { 
                                $presente_f = true; $mod_f = $status_f; 
                                $ore_singole_f = isset($formatori_ore_mappa[$ida][$emailf]) && $formatori_ore_mappa[$ida][$emailf] !== null ? (float)$formatori_ore_mappa[$ida][$emailf] : $ore_lezione;
                            } elseif ($status_f === 'Assente') { $mod_f = 'Assente'; }
                        } else {
                            if (isset($formatori_modalita_mappa[$ida][$emailf])) { 
                                $presente_f = true; $mod_f = ($lez['partecipazione'] === 'Presenza/FAD') ? 'Presenza' : $lez['partecipazione']; 
                                $ore_singole_f = $ore_lezione;
                            }
                        }
                        if ($presente_f) { $ore_fatte_f += $ore_singole_f; $sess_f++; }
                    ?>
                        <td style="padding: 10px; text-align: center; border-right:1px solid #e2e8f0; background: <?php echo $presente_f ? '#f0fdf4' : ($mod_f === 'Assente' ? '#fff1f2' : '#ffffff'); ?>; vertical-align: middle;">
                            <?php if ($presente_f): ?>
                                <strong>✅ <?php echo number_format($ore_singole_f, 1); ?> h</strong><br><span style="font-size:9px; font-weight:bold; text-transform:uppercase; color:#666;"><?php echo $mod_f; ?></span>
                            <?php elseif ($mod_f === 'Assente'): ?>
                                <span style="color:#dc2626; font-size:12px; font-weight:500;">❌ Assente</span>
                            <?php else: ?>
                                <span style="color:#94a3b8; font-size:12px;">-</span>
                            <?php endif; ?>
                        </td>
                    <?php endforeach; ?>
                    <td style="padding: 12px; text-align: center; font-weight: bold; background:#f8fafc; border-left:2px solid #cbd5e1; color:#1e293b; font-size:14px;">
                        <?php echo number_format($ore_fatte_f, 1); ?> / <?php echo number_format($ore_totali_corso, 1); ?> h
                    </td>
                    <td colspan="<?php echo $is_b17 ? 2 : 1; ?>" style="background:#f8fafc; text-align:center; color:#475569; font-weight:bold;">💼 <?php echo $sess_f; ?> lezioni</td>
                </tr>
                <?php endwhile; ?>

                <tr style="background:#f1f5f9; font-weight:bold; color:#475569;">
                    <td colspan="<?php echo count($lezioni) + ($is_b17 ? 4 : 3); ?>" style="padding:8px 12px; border-bottom:1px solid #cbd5e1; border-top:2px solid #cbd5e1;">
                        🧑‍🏫 DOCENTI INSERITI COME DISCENTI / BENEFICIARI
                    </td>
                </tr>
                <?php while ($doc = mysqli_fetch_assoc($docenti_res)): 
                    $email = $doc['email_dd']; $ore_totali_fatte = 0.0; $sessioni_frequentate = 0;
                ?>
                <tr style="border-bottom: 1px solid #e2e8f0;">
                    <td style="padding: 12px; font-weight: bold; position: sticky; left: 0; background: white; z-index: 1; border-right:2px solid #cbd5e1;"><?php echo htmlspecialchars($doc['cognome'] . ' ' . $doc['nome']); ?></td>
                    <?php foreach ($lezioni as $lez): 
                        $ida = $lez['ida']; $ore_lezione = (float)$lez['ore']; $presente_d = false; $mod_d = ''; $ore_singole_d = 0.0;
                        
                        if (isset($docenti_lezione_compilata[$ida])) {
                            $status_d = $docenti_modalita_mappa[$ida][$email] ?? 'Assente';
                            if ($status_d === 'Presenza' || $status_d === 'FAD') { 
                                $presente_d = true; $mod_d = $status_d; 
                                $ore_singole_d = isset($docenti_ore_mappa[$ida][$email]) && $docenti_ore_mappa[$ida][$email] !== null ? (float)$docenti_ore_mappa[$ida][$email] : $ore_lezione;
                            }
                        } else {
                            if (!isset($lezione_ha_docenti_assegnati[$ida]) || isset($docenti_modalita_mappa[$ida][$email])) {
                                $presente_d = true; $mod_d = ($lez['partecipazione'] === 'Presenza/FAD') ? 'Presenza' : $lez['partecipazione']; 
                                $ore_singole_d = $ore_lezione;
                            }
                        }
                        if ($presente_d) { $ore_totali_fatte += $ore_singole_d; $sessioni_frequentate++; }
                    ?>
                        <td style="padding: 10px; text-align: center; border-right:1px solid #e2e8f0; background: <?php echo $presente_d ? '#f0fdf4' : '#fff1f2'; ?>; vertical-align: middle;">
                            <?php if ($presente_d): ?>
                                <strong>✅ <?php echo number_format($ore_singole_d, 1); ?> h</strong><br><span style="display:inline-block; font-size:9px; font-weight:700; background:<?php echo $mod_d === 'FAD' ? '#e0f2fe' : '#dcfce7'; ?>; color:<?php echo $mod_d === 'FAD' ? '#0369a1' : '#15803d'; ?>; padding:1px 4px; border-radius:3px; margin-top:3px; text-transform:uppercase;"><?php echo $mod_d; ?></span>
                            <?php else: ?>
                                <span style="color:#dc2626; font-size:12px; font-weight:500;">❌ Assente</span>
                            <?php endif; ?>
                        </td>
                    <?php endforeach; ?>
                    
                    <td style="padding: 12px; text-align: center; font-weight: bold; background:#f8fafc; border-left:2px solid #cbd5e1; color:#1e293b; font-size:14px;">
                        <?php echo number_format($ore_totali_fatte, 1); ?> / <?php echo number_format($ore_totali_corso, 1); ?> h
                    </td>
                    
                    <?php if ($is_b17): 
                        $perc = $ore_totali_corso > 0 ? round(($ore_totali_fatte / $ore_totali_corso) * 100) : 0; $sup = ($perc >= 75);
                    ?>
                        <td style="padding: 12px; text-align: center; font-weight: bold; background:#f8fafc; color: <?php echo $sup ? '#16a34a' : '#dc2626'; ?>; font-size:14px;"><?php echo $perc; ?>%</td>
                        <td style="padding: 12px; text-align: center; background:#f8fafc; font-weight: bold;">
                            <span style="color:white; background:<?php echo $sup ? '#16a34a' : '#dc2626'; ?>; padding:4px 8px; border-radius:4px; font-size:11px; display:inline-block; width:95px; text-align:center; box-shadow:0 1px 2px rgba(0,0,0,0.1);"><?php echo $sup ? '🏆 SUPERATO' : '❌ NON SUP.'; ?></span>
                        </td>
                    <?php else: ?>
                        <!-- RISOLTO IL BIUG DELLA VARIABILE UNDEFINED SOSTITUENDO CON IL NOME CORRETTO -->
                        <td style="padding: 12px; text-align: center; background:#f8fafc; font-weight: bold; font-size:13px; color:#0275d8;"><div style="background:#e6f2ff; border:1px solid #b3d7ff; padding:4px 8px; border-radius:4px; display:inline-block; min-width:55px;">🚀 <?php echo $sessioni_frequentate; ?> svolte</div></td>
                    <?php endif; ?>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>