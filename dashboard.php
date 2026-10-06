<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// include('auth.php'); // DISATTIVATO TEMPORANEAMENTE PER ACCESSO LIBERO
include('db.php');
include('navbar.php');

// =========================================================================
// CALCOLO TARGET GLOBAL 1: Almeno 21 docenti formati totali (Cat B.1.7)
// =========================================================================
$target1_totale = 21;
$query_t1 = "SELECT COUNT(*) as qualificati FROM (
    SELECT sidd.email_dd,
           SUM(CASE 
               WHEN (SELECT COUNT(*) FROM assegnazione_docente_attivita WHERE ida = a.ida) = 0 THEN a.ore
               WHEN ad.modalita_effettiva IN ('Presenza', 'FAD') THEN IF(ad.ore_effettive IS NULL, a.ore, ad.ore_effettive)
               WHEN ad.modalita_effettiva = '' THEN a.ore
               ELSE 0 
           END) as ore_frequentate,
           (SELECT IFNULL(SUM(ore), 0) FROM attivita WHERE idi = i.idi) as ore_totali_corso
    FROM siform_iniziativa_docentediscente sidd
    JOIN iniziativa i ON sidd.idi = i.idi
    JOIN attivita a ON i.idi = a.idi
    LEFT JOIN assegnazione_docente_attivita ad ON a.ida = ad.ida AND sidd.email_dd = ad.email_dd
    WHERE i.categoria = 'B.1.7 Formazione dei formatori'
    GROUP BY sidd.email_dd, i.idi
    HAVING ore_totali_corso > 0 AND (ore_frequentate / ore_totali_corso) >= 0.75
) as docenti_idonei";

$res_t1 = mysqli_query($conn, $query_t1);
$docenti_formati_attuali = $res_t1 ? (int)mysqli_fetch_assoc($res_t1)['qualificati'] : 0;
$perc_t1 = $target1_totale > 0 ? min(round(($docenti_formati_attuali / $target1_totale) * 100), 100) : 0;

// =========================================================================
// CALCOLO TARGET GLOBAL 2: Almeno 150 docenti distinti (Cat B.1.8 / B.1.9)
// =========================================================================
$target2_totale = 150;
$query_t2 = "SELECT COUNT(DISTINCT sidd.email_dd) as unici
    FROM siform_iniziativa_docentediscente sidd
    JOIN iniziativa i ON sidd.idi = i.idi
    JOIN attivita a ON i.idi = a.idi
    LEFT JOIN assegnazione_docente_attivita ad ON a.ida = ad.ida AND sidd.email_dd = ad.email_dd
    WHERE i.categoria IN ('B.1.8 Sperimentazione', 'B.1.9 Accompagnamento e implementazione')
      AND (
           (SELECT COUNT(*) FROM assegnazione_docente_attivita WHERE ida = a.ida) = 0 
           OR ad.modalita_effettiva IN ('Presenza', 'FAD')
           OR ad.modalita_effettiva = ''
      )";

$res_t2 = mysqli_query($conn, $query_t2);
$docenti_unici_attuali = $res_t2 ? (int)mysqli_fetch_assoc($res_t2)['unici'] : 0;
$perc_t2 = $target2_totale > 0 ? min(round(($docenti_unici_attuali / $target2_totale) * 100), 100) : 0;

$query_iniziative = "SELECT i.idi, i.titolo, i.nome_siform, i.categoria,
    (SELECT IFNULL(SUM(ore), 0) FROM attivita WHERE idi = i.idi) as ore_totali_attivita,
    (SELECT COUNT(*) FROM attivita WHERE idi = i.idi) as totale_sessioni_caricate,
    (SELECT IFNULL(SUM(ore_totali_assegnate), 0) FROM siform_iniziativa_formatore WHERE idi = i.idi) as ore_staff_assegnate,
    (SELECT IFNULL(SUM(ore_svolte), 0) FROM siform_iniziativa_formatore WHERE idi = i.idi) as ore_staff_svolte,
    (SELECT GROUP_CONCAT(CONCAT(f.cognome, ' ', f.nome) SEPARATOR ', ') FROM siform_iniziativa_formatore sif JOIN formatore f ON sif.emailf = f.emailf WHERE sif.idi = i.idi) as formatori_lista,
    (SELECT GROUP_CONCAT(c.nome SEPARATOR ', ') FROM siform_iniziativa_classe sic JOIN classe c ON sic.idc = c.idc WHERE sic.idi = i.idi) as classi_lista
FROM iniziativa i 
ORDER BY LENGTH(SUBSTRING_INDEX(i.categoria, ' ', 1)) ASC, i.categoria ASC, i.data_inizio ASC";

$iniziative_res = mysqli_query($conn, $query_iniziative);
?>

<div style="font-family: sans-serif; padding: 10px;">
    <h2>📊 Dashboard Direzionale & Monitoraggio KPI</h2>
    <p style="color:#666; margin-top:-10px;">Stato di avanzamento e controllo metriche europee/ministeriali in tempo reale.</p>
    
    <div style="display: flex; gap: 20px; margin-bottom: 30px;">
        <div style="flex: 1; background: white; border: 1px solid #ccc; border-radius: 6px; padding: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
            <span style="font-size: 11px; font-weight: bold; color: #d9534f; text-transform: uppercase; display:block; margin-bottom:5px;">Target Totale 1: Formazione Formatori (Cat. B.1.7)</span>
            <div style="display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 10px;">
                <span style="font-size: 32px; font-weight: bold; color: #111;"><?php echo $docenti_formati_attuali; ?> <small style="font-size:16px; color:#666; font-weight:normal;">/ <?php echo $target1_totale; ?> docenti</small></span>
                <span style="background: #f2dede; color: #a94442; padding: 3px 8px; border-radius: 12px; font-size: 12px; font-weight: bold;"><?php echo $perc_t1; ?>% Raggiunto</span>
            </div>
            <p style="font-size: 12px; color: #777; margin: 0 0 10px 0;">Docenti qualificati con frequenza active &ge; 75% calcolata sul computo reale delle ore registrate.</p>
            <div style="background: #eee; height: 12px; border-radius: 6px; overflow: hidden;">
                <div style="background: #d9534f; width: <?php echo $perc_t1; ?>%; height: 100%; transition: width 0.5s;"></div>
            </div>
        </div>

        <div style="flex: 1; background: white; border: 1px solid #ccc; border-radius: 6px; padding: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
            <span style="font-size: 11px; font-weight: bold; color: #5cb85c; text-transform: uppercase; display:block; margin-bottom:5px;">Target Totale 2: Diffusione Sperimentazione (Cat. B.1.8 / B.1.9)</span>
            <div style="display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 10px;">
                <span style="font-size: 32px; font-weight: bold; color: #111;"><?php echo $docenti_unici_attuali; ?> <small style="font-size:16px; color:#666; font-weight:normal;">/ <?php echo $target2_totale; ?> distinti</small></span>
                <span style="background: #dff0d8; color: #3c763d; padding: 3px 8px; border-radius: 12px; font-size: 12px; font-weight: bold;"><?php echo $perc_t2; ?>% Raggiunto</span>
            </div>
            <p style="font-size: 12px; color: #777; margin: 0 0 10px 0;">Docenti unici della scuola che hanno preso parte ad almeno una sessione di sperimentazione attiva.</p>
            <div style="background: #eee; height: 12px; border-radius: 6px; overflow: hidden;">
                <div style="background: #5cb85c; width: <?php echo $perc_t2; ?>%; height: 100%; transition: width 0.5s;"></div>
            </div>
        </div>
    </div>

    <h3 style="border-bottom: 2px solid #0275d8; padding-bottom: 5px; color: #333;">⏱️ Stato di Avanzamento e Bilancio Ore delle Iniziative</h3>
    <table style="width:100%; border-collapse: collapse; background: white; border: 1px solid #ddd; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
        <thead>
            <tr style="background: #f5f5f5; border-bottom: 2px solid #ddd;">
                <th style="padding: 12px; text-align: left;">Iniziativa / Risorse Associate</th>
                <th style="padding: 12px; text-align: center; width: 15%;">Tipo Canale</th>
                <th style="padding: 12px; text-align: center; width: 15%;">Computo Ore Totali</th>
                <th style="padding: 12px; text-align: left; width: 30%;">Stato Progresso / Indicatori Reali</th>
            </tr>
        </thead>
        <tbody>
            <?php while($row = mysqli_fetch_assoc($iniziative_res)): 
                $current_idi = (int)$row['idi']; $cat_row = $row['categoria'] ?? '';
                $titolo_visualizzato = !empty($row['titolo']) ? $row['titolo'] : ($row['nome_siform'] ?? 'Iniziativa');
                
                if ($row['ore_staff_assegnate'] > 0) {
                    $tipo_canale = "⏱️ Staff (Timesheet)"; $ore_effettive = (float)$row['ore_staff_svolte']; $ore_obiettivo = (float)$row['ore_staff_assegnate']; $colore_progress = "#f0ad4e"; 
                    $percentuale = $ore_obiettivo > 0 ? round(($ore_effettive / $ore_obiettivo) * 100) : 0; $larghezza_barra = min($percentuale, 100);
                } else {
                    $tipo_canale = "🗓️ Corso (Calendario)"; $ore_effettive = (float)$row['ore_totali_attivita']; $sessioni = (int)$row['totale_sessioni_caricate']; $colore_progress = "#0275d8"; 
                }
            ?>
            <tr style="border-bottom: 1px solid #eee;">
                <td style="padding: 12px; vertical-align: top;">
                    <strong><?php echo htmlspecialchars($titolo_visualizzato); ?></strong>
                    <small style="display:block; color:#666; margin-top:3px; font-style:italic;"><?php echo htmlspecialchars($cat_row); ?></small>
                    <div style="margin-top: 6px; font-size: 11px; color:#555;">
                        <span style="margin-right: 15px;">👔 <strong>Staff:</strong> <?php echo $row['formatori_lista'] ? htmlspecialchars($row['formatori_lista']) : '<i>Nessuno</i>'; ?></span>
                        <?php if(!empty($row['classi_lista'])): ?><span>🏫 <strong>Classi:</strong> <?php echo htmlspecialchars($row['classi_lista']); ?></span><?php endif; ?>
                    </div>
                </td>
                <td style="padding: 12px; text-align: center; vertical-align: middle;"><span style="font-size:11px; font-weight:bold; color: white; background: <?php echo $colore_progress; ?>; padding: 4px 8px; border-radius: 10px; display:inline-block;"><?php echo $tipo_canale; ?></span></td>
                <td style="padding: 12px; text-align: center; vertical-align: middle;">
                    <strong style="font-size:15px;"><?php echo number_format($ore_effettive, 1); ?> h</strong>
                    <?php if ($row['ore_staff_assegnate'] > 0): ?><br><small style="color:#666;">su <?php echo number_format($ore_obiettivo, 1); ?> h</small><?php endif; ?>
                </td>
                <td style="padding: 12px; vertical-align: middle;">
                    <?php if ($row['ore_staff_assegnate'] > 0): ?>
                        <div style="background: #eee; width: 100%; border-radius: 10px; overflow: hidden; position: relative; height: 20px; border: 1px solid #ccc;">
                            <div style="background: <?php echo $colore_progress; ?>; width: <?php echo $larghezza_barra; ?>%; height: 100%; transition: width 0.4s;"></div>
                            <span style="position: absolute; width: 100%; text-align: center; left: 0; top: 2px; font-size: 11px; font-weight: bold; color: #111;"><?php echo $percentuale; ?>% Caricato</span>
                        </div>
                    <?php else: ?>
                        <?php if($cat_row === 'B.1.7 Formazione dei formatori'): 
                            $q_i = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as tot FROM siform_iniziativa_docentediscente WHERE idi = $current_idi")); $iscritti_corso = (int)$q_i['tot'];
                            $q_f = mysqli_query($conn, "SELECT COUNT(*) as qualificati FROM (
                                SELECT sidd.email_dd,
                                       SUM(CASE 
                                           WHEN (SELECT COUNT(*) FROM assegnazione_docente_attivita WHERE ida = a.ida) = 0 THEN a.ore
                                           WHEN ad.modalita_effettiva IN ('Presenza', 'FAD') THEN IF(ad.ore_effettive IS NULL, a.ore, ad.ore_effettive)
                                           WHEN ad.modalita_effettiva = '' THEN a.ore
                                           ELSE 0 
                                       END) as ore_frequentate,
                                       (SELECT IFNULL(SUM(ore), 0) FROM attivita WHERE idi = $current_idi) as ore_totali_corso
                                FROM siform_iniziativa_docentediscente sidd JOIN attivita a ON a.idi = sidd.idi LEFT JOIN assegnazione_docente_attivita ad ON a.ida = ad.ida AND sidd.email_dd = ad.email_dd WHERE sidd.idi = $current_idi GROUP BY sidd.email_dd HAVING ore_totali_corso > 0 AND (ore_frequentate / ore_totali_corso) >= 0.75
                            ) as t"); $formati_corso = $q_f ? (int)mysqli_fetch_assoc($q_f)['qualificati'] : 0;
                        ?>
                            <div style="display:flex; justify-content:space-between; align-items:center; max-width: 320px;">
                                <div style="background: #fdf2e2; color:#b94a48; padding: 6px 12px; border-radius: 4px; border: 1px solid #fbeed5; font-size: 12px; font-weight: bold; flex:1; line-height:1.5;">👥 Iscritti: <?php echo $iscritti_corso; ?><br>🎓 Formati: <span style="color:green;"><?php echo $formati_corso; ?></span></div>
                                <a href="report_corso.php?idi=<?php echo $current_idi; ?>" class="btn btn-blue" style="background:#673ab7; font-size:11px; padding:6px; margin-left:8px; color:white; font-weight:bold; text-decoration:none;">📋 Vedi</a>
                            </div>
                        <?php elseif(in_array($cat_row, ['B.1.8 Sperimentazione', 'B.1.9 Accompagnamento e implementazione'])): 
                            $q_u = mysqli_query($conn, "SELECT COUNT(DISTINCT sidd.email_dd) as unici_part FROM siform_iniziativa_docentediscente sidd JOIN attivita a ON a.idi = sidd.idi LEFT JOIN assegnazione_docente_attivita ad ON a.ida = ad.ida AND sidd.email_dd = ad.email_dd WHERE sidd.idi = $current_idi AND ((SELECT COUNT(*) FROM assegnazione_docente_attivita WHERE ida = a.ida) = 0 OR ad.modalita_effettiva IN ('Presenza', 'FAD') OR ad.modalita_effettiva = '')"); $unici_corso = $q_u ? (int)mysqli_fetch_assoc($q_u)['unici_part'] : 0;
                        ?>
                            <div style="display:flex; justify-content:space-between; align-items:center; max-width: 320px;">
                                <div style="background: #e6f2ff; color:#0275d8; padding: 6px 12px; border-radius: 4px; border: 1px solid #b3d7ff; font-size: 12px; font-weight: bold; flex:1; line-height:1.5;">🚀 Sessioni: <?php echo $sessioni; ?><br>🧑‍🏫 Univoci: <?php echo $unici_corso; ?></div>
                                <a href="report_corso.php?idi=<?php echo $current_idi; ?>" class="btn btn-blue" style="background:#673ab7; font-size:11px; padding:6px; margin-left:8px; color:white; font-weight:bold; text-decoration:none;">📋 Vedi</a>
                            </div>
                        <?php else: ?>
                            <div style="display:flex; justify-content:space-between; align-items:center; max-width: 320px;">
                                <div style="background: #f5f5f5; color:#333; padding: 6px 12px; border-radius: 4px; border: 1px solid #ddd; font-size: 12px; font-weight: bold; flex:1;">📅 Svolte: <?php echo $sessioni; ?> sessioni</div>
                                <a href="report_corso.php?idi=<?php echo $current_idi; ?>" class="btn btn-blue" style="background:#673ab7; font-size:11px; padding:6px; margin-left:8px; color:white; font-weight:bold; text-decoration:none;">📋 Vedi</a>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>