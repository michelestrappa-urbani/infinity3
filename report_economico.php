<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// include('auth.php'); // DISATTIVATO TEMPORANEAMENTE PER ACCESSO LIBERO
include('db.php');
include('navbar.php');

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
?>

<div style="font-family:sans-serif; padding:10px;">
    <h2>📊 Controllo di Gestione: Spaccato Budget Finanziato vs Pianificato vs Effettivo</h2>
    <p style="color:#475569; margin-top:0;">Il quadro economico include le cifre di bando iniziali, le ore pianificate a sistema e le sessioni effettivamente svolte/firmate dallo staff. Ciascuna sezione calcola il correlato 40% di costi indiretti associati.</p>

    <h3>1. Riepilogo Consolidato delle Macrocategorie Finanziarie</h3>
    <table style="width:100%; border-collapse:collapse; background:white; font-size:12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
        <thead>
            <tr style="background:#1e293b; color:white; border-bottom:2px solid #cbd5e1;">
                <th rowspan="2" style="padding:10px; text-align:left; border:1px solid #475569;">Macrocategoria SIFORM</th>
                <th colspan="3" style="background:#334155; text-align:center; border:1px solid #475569;">💰 1. BUDGET FINANZIATO (BANDO)</th>
                <th colspan="3" style="background:#0284c7; text-align:center; border:1px solid #475569;">📋 2. BUDGET PIANIFICATO (ALLOCATO)</th>
                <th colspan="3" style="background:#16a34a; text-align:center; border:1px solid #475569;">🟢 3. BUDGET EFFETTIVO (SVOLTO)</th>
            </tr>
            <tr style="background:#f8fafc; color:#1e293b;">
                <th style="padding:5px; text-align:right; border:1px solid #cbd5e1;">Staff</th>
                <th style="padding:5px; text-align:right; border:1px solid #cbd5e1; color:#2563eb;">Ind. (40%)</th>
                <th style="padding:5px; text-align:right; border:1px solid #cbd5e1; font-weight:bold; background:#e2e8f0;">Totale</th>
                <th style="padding:5px; text-align:right; border:1px solid #cbd5e1;">Staff</th>
                <th style="padding:5px; text-align:right; border:1px solid #cbd5e1; color:#2563eb;">Ind. (40%)</th>
                <th style="padding:5px; text-align:right; border:1px solid #cbd5e1; font-weight:bold; background:#e0f2fe;">Totale</th>
                <th style="padding:5px; text-align:right; border:1px solid #cbd5e1;">Staff</th>
                <th style="padding:5px; text-align:right; border:1px solid #cbd5e1; color:#2563eb;">Ind. (40%)</th>
                <th style="padding:5px; text-align:right; border:1px solid #cbd5e1; font-weight:bold; background:#dcfce7;">Totale</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $tot_generale_finanziato_diretto = 0;
            $tot_generale_pianificato_diretto = 0;
            $tot_generale_effettivo_diretto = 0;
            
            $categorie_somme = [];
            $iniziat_q = mysqli_query($conn, "SELECT idi, categoria, titolo, nome_siform FROM iniziativa ORDER BY categoria ASC");
            
            // Inizializza tutte le categorie mappate staticamente
            foreach($budget_finanziato_config as $nome_cat => $b_fin) {
                $categorie_somme[$nome_cat] = ['pianificato' => 0, 'effettivo' => 0, 'corsi' => []];
                $tot_generale_finanziato_diretto += $b_fin;
            }
            
            // Aggrega i dati dinamici del database
            while ($i = mysqli_fetch_assoc($iniziat_q)) {
                $cat = $i['categoria'];
                if(!isset($categorie_somme[$cat])) continue;
                
                $is_timesheet_cat = (strpos($cat, 'Sperimentazione') === false && strpos($cat, 'implementazione') === false && strpos($cat, 'formatori') === false && strpos($cat, 'destinatari') === false);
                
                $pianificato_corso = 0;
                $speso_corso = 0;
                
                if ($is_timesheet_cat) {
                    $plan_q = mysqli_query($conn, "SELECT SUM(ore_totali_assegnate * costo_orario) as tot FROM siform_iniziativa_formatore WHERE idi = " . $i['idi']);
                    $plan = mysqli_fetch_assoc($plan_q); $pianificato_corso = (float)($plan['tot'] ?? 0);

                    $calc_q = mysqli_query($conn, "SELECT SUM(d.ore * filter_sif.costo_orario) as tot FROM dettaglio_timesheet d JOIN siform_iniziativa_formatore filter_sif ON (d.idi = filter_sif.idi AND d.emailf = filter_sif.emailf) WHERE d.idi = " . $i['idi']);
                    $calc = mysqli_fetch_assoc($calc_q); $speso_corso = (float)($calc['tot'] ?? 0);
                } else {
                    $plan_q = mysqli_query($conn, "SELECT SUM(a.ore * sif.costo_orario) as tot FROM assegnazione_formatore_attivita p JOIN attivita a ON p.ida = a.ida JOIN siform_iniziativa_formatore sif ON (a.idi = sif.idi AND p.emailf = sif.emailf) WHERE a.idi = " . $i['idi']);
                    $plan = mysqli_fetch_assoc($plan_q); $pianificato_corso = (float)($plan['tot'] ?? 0);

                    $calc_q = mysqli_query($conn, "SELECT SUM(a.ore * sif.costo_orario) as tot FROM assegnazione_formatore_attivita p JOIN attivita a ON p.ida = a.ida JOIN siform_iniziativa_formatore sif ON (a.idi = sif.idi AND p.emailf = sif.emailf) WHERE a.idi = " . $i['idi'] . " AND p.modalita_effettiva IN ('Presenza', 'FAD')");
                    $calc = mysqli_fetch_assoc($calc_q); $speso_corso = (float)($calc['tot'] ?? 0);
                }
                
                $categorie_somme[$cat]['pianificato'] += $pianificato_corso;
                $categorie_somme[$cat]['effettivo'] += $speso_corso;
                $categorie_somme[$cat]['corsi'][] = [
                    'titolo' => $i['titolo'] ? $i['titolo'] : $i['nome_siform'],
                    'pianificato' => $pianificato_corso,
                    'effettivo' => $speso_corso
                ];
                
                $tot_generale_pianificato_diretto += $pianificato_corso;
                $tot_generale_effettivo_diretto += $speso_corso;
            }
            
            foreach ($budget_finanziato_config as $nome_cat => $b_fin) {
                $dati = $categorie_somme[$nome_cat];
                
                $ind_fin = $b_fin * 0.40; $tot_fin = $b_fin + $ind_fin;
                $ind_plan = $dati['pianificato'] * 0.40; $tot_plan = $dati['pianificato'] + $ind_plan;
                $ind_eff = $dati['effettivo'] * 0.40; $tot_eff = $dati['effettivo'] + $ind_eff;
                
                // Evidenzia visivamente se la pianificazione sfora il finanziamento iniziale
                $row_alert = ($dati['pianificato'] > $b_fin) ? "style='background:#fef2f2; font-weight:bold;'" : "";
            ?>
                <tr style="border-bottom:1px solid #cbd5e1;" <?php echo $row_alert; ?>>
                    <td style="padding:8px; border:1px solid #cbd5e1;"><strong><?php echo htmlspecialchars($nome_cat); ?></strong></td>
                    <td style="text-align:right; padding:8px; border:1px solid #cbd5e1;"><?php echo number_format($b_fin, 2); ?> €</td>
                    <td style="text-align:right; padding:8px; border:1px solid #cbd5e1; color:#2563eb;">+<?php echo number_format($ind_fin, 2); ?> €</td>
                    <td style="text-align:right; padding:8px; border:1px solid #cbd5e1; background:#f1f5f9; font-weight:bold;"><?php echo number_format($tot_fin, 2); ?> €</td>
                    <td style="text-align:right; padding:8px; border:1px solid #cbd5e1;"><?php echo number_format($dati['pianificato'], 2); ?> €</td>
                    <td style="text-align:right; padding:8px; border:1px solid #cbd5e1; color:#2563eb;">+<?php echo number_format($ind_plan, 2); ?> €</td>
                    <td style="text-align:right; padding:8px; border:1px solid #cbd5e1; background:#e0f2fe; font-weight:bold;"><?php echo number_format($tot_plan, 2); ?> €</td>
                    <td style="text-align:right; padding:8px; border:1px solid #cbd5e1; background:#f0fdf4;"><?php echo number_format($dati['effettivo'], 2); ?> €</td>
                    <td style="text-align:right; padding:8px; border:1px solid #cbd5e1; color:#2563eb; background:#f0fdf4;">+<?php echo number_format($ind_eff, 2); ?> €</td>
                    <td style="text-align:right; padding:8px; border:1px solid #cbd5e1; background:#dcfce7; font-weight:bold; color:#166534;"><?php echo number_format($tot_eff, 2); ?> €</td>
                </tr>
            <?php } ?>
        </tbody>
    </table>

    <h3 style="margin-top:30px;">2. Spaccato di Dettaglio Iniziative per Macrocategoria</h3>
    <?php foreach ($budget_finanziato_config as $nome_cat => $b_fin) { 
        $dati = $categorie_somme[$nome_cat];
    ?>
        <div style="background:#f8fafc; border:1px solid #cbd5e1; border-radius:6px; padding:12px; margin-bottom:15px;">
            <h4 style="margin:0 0 10px 0; color:#1e293b; border-bottom:1px solid #e2e8f0; padding-bottom:4px; text-transform:uppercase; font-size:12px; letter-spacing:0.5px;"><?php echo htmlspecialchars($nome_cat); ?> (Finanziamento Personale Bando: <?php echo number_format($b_fin,2); ?>€)</h4>
            <?php if(empty($dati['corsi'])) { ?>
                <p style="margin:0; font-style:italic; color:#94a3b8; font-size:13px;">Nessuna iniziativa attiva. Budget di bando interamente disponibile.</p>
            <?php } else { ?>
                <ul style="margin:0; padding-left:20px; font-size:13px; line-height:1.4;">
                    <?php foreach ($dati['corsi'] as $c) { ?>
                        <li style="margin-bottom:5px;">
                            <strong><?php echo htmlspecialchars($c['titolo']); ?></strong><br>
                            <span style="color:#475569;">📋 Pianificato: Staff: <?php echo number_format($c['pianificato'],2); ?>€ | Indiretti Potenziali (40%): <?php echo number_format($c['pianificato']*0.4,2); ?>€</span><br>
                            <span style="color:#166534; font-weight:600;">🟢 Effettivo Svolto: Staff: <?php echo number_format($c['effettivo'],2); ?>€ | Indiretti Maturati (40%): <?php echo number_format($c['effettivo']*0.4,2); ?>€</span>
                        </li>
                    <?php } ?>
                </ul>
            <?php } ?>
        </div>
    <?php } ?>

    <h3>3. Bilancio Finanziario Consolidato del Progetto (Polo Urbani)</h3>
    <div style="display:flex; gap:20px; margin-top:15px; margin-bottom:30px; flex-wrap:wrap;">
        
        <div style="flex:1; min-width:250px; background:#334155; color:white; padding:15px; border-radius:8px;">
            <h4 style="margin:0 0 5px 0; color:#cbd5e1; font-size:11px; text-transform:uppercase; letter-spacing:0.5px;">💰 TOTALE FINANZIATO BANDO</h4>
            <span style="font-size:26px; font-weight:bold; color:#f8fafc;"><?php echo number_format($tot_generale_finanziato_diretto * 1.4, 2); ?> €</span>
            <div style="margin-top:10px; font-size:12px; border-top:1px solid #475569; padding-top:6px; color:#94a3b8;">
                Diretti Personale: <?php echo number_format($tot_generale_finanziato_diretto,2); ?> €<br>
                Costi Indiretti Bando (40%): <?php echo number_format($tot_generale_finanziato_diretto * 0.4,2); ?> €
            </div>
        </div>

        <div style="flex:1; min-width:250px; background:#0284c7; color:white; padding:15px; border-radius:8px;">
            <h4 style="margin:0 0 5px 0; color:#e0f2fe; font-size:11px; text-transform:uppercase; letter-spacing:0.5px;">📋 TOTALE PIANIFICATO ALLOCATO</h4>
            <span style="font-size:26px; font-weight:bold; color:#f8fafc;"><?php echo number_format($tot_generale_pianificato_diretto * 1.4, 2); ?> €</span>
            <div style="margin-top:10px; font-size:12px; border-top:1px solid #38bdf8; padding-top:6px; color:#bae6fd;">
                Diretti Assegnati: <?php echo number_format($tot_generale_pianificato_diretto,2); ?> €<br>
                Indiretti Potenziali (40%): <?php echo number_format($tot_generale_pianificato_diretto * 0.4,2); ?> €
            </div>
        </div>

        <div style="flex:1; min-width:250px; background:#1e293b; color:white; padding:15px; border-radius:8px; border:2px solid #22c55e;">
            <h4 style="margin:0 0 5px 0; color:#a7f3d0; font-size:11px; text-transform:uppercase; letter-spacing:0.5px;">🟢 TOTALE EFFETTIVO CERTIFICATO</h4>
            <span style="font-size:26px; font-weight:bold; color:#4ade80;"><?php echo number_format($tot_generale_effettivo_diretto * 1.4, 2); ?> €</span>
            <div style="margin-top:10px; font-size:12px; border-top:1px solid #334155; padding-top:6px; color:#94a3b8;">
                Diretti Svolti: <?php echo number_format($tot_generale_effettivo_diretto,2); ?> €<br>
                <span style="color:#60a5fa; font-weight:bold;">Indiretti Spendibili ad Oggi (40%): <?php echo number_format($tot_generale_effettivo_diretto * 0.4,2); ?> €</span>
            </div>
        </div>

    </div>
    
    <div style="margin-top:10px; margin-bottom:20px;">
        <a href="index.php" class="btn" style="background:#475569; color:white; text-decoration:none; padding:10px 20px; border-radius:4px; font-weight:bold; display:inline-block;">⬅️ Torna alla Dashboard</a>
    </div>
</div>