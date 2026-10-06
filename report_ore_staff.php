<?php
// Forza la visibilità degli errori per facilitare il controllo su Altervista
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// include('auth.php'); // DISATTIVATO TEMPORANEAMENTE PER ACCESSO LIBERO
include('db.php');
include('navbar.php');

// Mappatura delle categorie per sapere come estrarre i dati delle ore
$categorie_struttura = [
    'B.1.1 Direzione/Equipe' => 'timesheet',
    'B.1.2 Coordinamento' => 'timesheet',
    'B.1.3 Amministrazione' => 'timesheet',
    'B.1.4 Segreteria' => 'timesheet',
    'B.1.5 Progettazione' => 'timesheet',
    'B.1.6 Ricerca e Analisi' => 'timesheet',
    'B.1.7 Formazione dei formatori' => 'calendario',
    'B.1.8 Sperimentazione' => 'calendario',
    'B.1.9 Accompagnamento e implementazione' => 'calendario',
    'B.1.10 Elaborazione materiale didattico' => 'timesheet',
    'B.1.11 Monitoraggio e Valutazione' => 'timesheet',
    'B.1.12 Comunicazione' => 'timesheet',
    'B.1.13 Attività con i destinatari' => 'calendario'
];
?>

<div style="font-family: sans-serif; padding: 10px;">
    <h2>⏱️ Analisi Consolidata: Ore Lavorate per Formatore & Staff</h2>
    <p style="color:#475569; margin-top:0; margin-bottom:25px;">Riepilogo delle ore totali pianificate e realmente svolte da ciascun operatore all'interno delle singole iniziative progettuali.</p>

    <?php
    // Estrae tutto lo staff presente nella gestione anagrafiche ordinato per cognome
    $formatori_q = mysqli_query($conn, "SELECT emailf, nome, cognome, tipo FROM formatore ORDER BY cognome ASC, nome ASC");

    if (mysqli_num_rows($formatori_q) == 0) {
        echo '<div style="background:white; padding:20px; border:1px solid #cbd5e1; border-radius:6px; font-style:italic; color:#94a3b8;">Nessun formatore registrato nell\'anagrafica del sistema.</div>';
    } else {
        while ($f = mysqli_fetch_assoc($formatori_q)) {
            $emailf = mysqli_real_escape_string($conn, $f['emailf']);
            
            $tot_personale_pianificato = 0.00;
            $tot_personale_effettivo = 0.00;
            $iniziative_assegnate = [];

            // Cerca tutte le iniziative a cui questo specifico formatore è abbinato
            $init_q = mysqli_query($conn, "
                SELECT i.idi, i.titolo, i.nome_siform, i.categoria, sif.ore_totali_assegnate 
                FROM siform_iniziativa_formatore sif
                JOIN iniziativa i ON sif.idi = i.idi
                WHERE sif.emailf = '$emailf'
                ORDER BY i.categoria ASC, i.idi ASC
            ");

            while ($row_i = mysqli_fetch_assoc($init_q)) {
                $idi_corso = (int)$row_i['idi'];
                $categoria_corso = $row_i['categoria'];
                $tipo_tracciamento = isset($categorie_struttura[$categoria_corso]) ? $categorie_struttura[$categoria_corso] : 'calendario';
                
                $plan_ore = 0.00;
                $real_ore = 0.00;

                if ($tipo_tracciamento === 'timesheet') {
                    // 1. LOGICA TIMESHEET (Diario di bordo a blocchi)
                    // Pianificato = ore budget impostate nella scheda modifica iniziativa
                    $plan_ore = (float)$row_i['ore_totali_assegnate'];

                    // Svolto = somma delle ore registrate nel diario timesheet per questo operatore
                    $r_q = mysqli_query($conn, "SELECT SUM(ore) as tot FROM dettaglio_timesheet WHERE idi = $idi_corso AND emailf = '$emailf'");
                    $r_row = mysqli_fetch_assoc($r_q);
                    $real_ore = (float)($r_row['tot'] ?? 0.00);
                } else {
                    // 2. LOGICA CALENDARIO (Lezioni ed edizioni didattiche)
                    // Pianificato = somma ore di tutte le lezioni a calendario in cui il formatore è inserito
                    $p_q = mysqli_query($conn, "SELECT SUM(a.ore) as tot FROM assegnazione_formatore_attivita af JOIN attivita a ON af.ida = a.ida WHERE a.idi = $idi_corso AND af.emailf = '$emailf'");
                    $p_row = mysqli_fetch_assoc($p_q);
                    $plan_ore = (float)($p_row['tot'] ?? 0.00);

                    // Svolto = somma delle ore effettive delle lezioni convalidate (con firma presenza o FAD)
                    $r_q = mysqli_query($conn, "SELECT SUM(COALESCE(af.ore_effettive, a.ore)) as tot FROM assegnazione_formatore_attivita af JOIN attivita a ON af.ida = a.ida WHERE a.idi = $idi_corso AND af.emailf = '$emailf' AND af.modalita_effettiva IN ('Presenza', 'FAD')");
                    $r_row = mysqli_fetch_assoc($r_q);
                    $real_ore = (float)($r_row['tot'] ?? 0.00);
                }

                $tot_personale_pianificato += $plan_ore;
                $tot_personale_effettivo += $real_ore;

                $iniziative_assegnate[] = [
                    'titolo' => !empty($row_i['titolo']) ? $row_i['titolo'] : $row_i['nome_siform'],
                    'categoria' => $categoria_corso,
                    'tipo' => $tipo_tracciamento,
                    'pianificate' => $plan_ore,
                    'svolte' => $real_ore
                ];
            }
    ?>
            <!-- CARD DEL FORMATORE/STAFF -->
            <div style="background: white; border: 1px solid #cbd5e1; border-radius: 6px; padding: 15px; margin-bottom: 30px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                
                <!-- INTESTAZIONE CARD CON RIEPILOGO COMPLESSIVO OPERATORE -->
                <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:2px solid #cbd5e1; padding-bottom:8px; margin-bottom:12px; flex-wrap:wrap; gap:10px;">
                    <div>
                        <h3 style="margin:0; color:#0f172a; font-size:16px;">👤 <?php echo htmlspecialchars($f['cognome'] . " " . $f['nome']); ?></h3>
                        <small style="background:#e2e8f0; color:#334155; padding:2px 6px; border-radius:4px; font-weight:600; font-size:11px; margin-top:3px; display:inline-block;"><?php echo htmlspecialchars($f['tipo']); ?></small>
                    </div>
                    <div style="font-size:13px; font-weight:bold; background:#f8fafc; border:1px solid #cbd5e1; padding:6px 12px; border-radius:4px;">
                        📊 Totale Ore Svolte su Bando: <span style="color:#16a34a; font-size:14px;"><?php echo number_format($tot_personale_effettivo, 2); ?> h</span> <span style="color:#64748b; font-weight:normal; font-size:12px;">(su <?php echo number_format($tot_personale_pianificato, 2); ?> h pianificate)</span>
                    </div>
                </div>

                <!-- TABELLA DETTAGLIO CORSI DELL'OPERATORE -->
                <?php if (empty($iniziative_assegnate)) { ?>
                    <p style="margin:0; font-style:italic; color:#94a3b8; font-size:13px; padding:5px 0;">Nessun incarico finanziario o lezione a calendario associata a questo operatore.</p>
                <?php } else { ?>
                    <table style="width:100%; border-collapse:collapse; font-size:13px; margin:0; box-shadow:none; border:1px solid #e2e8f0;">
                        <thead>
                            <tr style="background:#f8fafc; color:#475569; border-bottom:1px solid #cbd5e1;">
                                <th style="padding:8px; text-align:left; width:35%; border:1px solid #e2e8f0;">Iniziativa / Edizione</th>
                                <th style="padding:8px; text-align:left; width:35%; border:1px solid #e2e8f0;">Macro-Categoria SIFORM</th>
                                <th style="padding:8px; text-align:center; width:15%; border:1px solid #e2e8f0;">Ore Pianificate</th>
                                <th style="padding:8px; text-align:center; width:15%; border:1px solid #e2e8f0; background:#f0fdf4; color:#166534;">Ore Svolte Reali</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($iniziative_assegnate as $iniz) { 
                                $badge_tipo = ($iniz['tipo'] === 'timesheet') ? 
                                    '<span style="background:#e0f2fe; color:#0369a1; padding:1px 4px; border-radius:3px; font-size:10px; font-weight:bold;">TS</span>' : 
                                    '<span style="background:#fef3c7; color:#b45309; padding:1px 4px; border-radius:3px; font-size:10px; font-weight:bold;">CAL</span>';
                            ?>
                                <tr style="border-bottom:1px solid #f1f5f9;">
                                    <td style="padding:8px; font-weight:bold; color:#334155; border:1px solid #e2e8f0;">
                                        <?php echo htmlspecialchars($iniz['titolo']); ?>
                                    </td>
                                    <td style="padding:8px; color:#475569; font-size:12px; border:1px solid #e2e8f0;">
                                        <?php echo $badge_tipo; ?> <?php echo htmlspecialchars($iniz['categoria']); ?>
                                    </td>
                                    <td style="padding:8px; text-align:center; color:#64748b; font-weight:500; border:1px solid #e2e8f0;">
                                        <?php echo number_format($iniz['pianificate'], 2); ?> h
                                    </td>
                                    <td style="padding:8px; text-align:center; font-weight:bold; color:#16a34a; background:#f0fdf4; border:1px solid #e2e8f0; font-size:14px;">
                                        <?php echo number_format($iniz['svolte'], 2); ?> h
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                <?php } ?>
            </div>
    <?php 
        } 
    }
    ?>
</div>