<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// include('auth.php'); // DISATTIVATO TEMPORANEAMENTE PER ACCESSO LIBERO
include('db.php');
include('navbar.php');

// Mappatura delle categorie per discernere il tipo di tracciamento ore
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
    <h2>📊 Registro di Analisi: Report Dettagliato per Risorsa & Macrocategoria</h2>
    <p style="color:#475569; margin-top:0; margin-bottom:25px;">Riepilogo analitico riga per riga delle sessioni e dei diari di lavoro, completati dai calcoli di quadratura economica del budget pianificato ed effettivo.</p>

    <?php
    // 1. Estrazione di tutto il personale a sistema ordinato per cognome
    $risorse_q = mysqli_query($conn, "SELECT emailf, nome, cognome, tipo FROM formatore ORDER BY cognome ASC, nome ASC");

    if (mysqli_num_rows($risorse_q) == 0) {
        echo '<div style="background:white; padding:20px; border:1px solid #cbd5e1; border-radius:6px; font-style:italic; color:#94a3b8;">Nessun operatore inserito nelle anagrafiche del sistema.</div>';
    } else {
        while ($risorsa = mysqli_fetch_assoc($risorse_q)) {
            $emailf = mysqli_real_escape_string($conn, $risorsa['emailf']);
            $nome_completo = htmlspecialchars($risorsa['cognome'] . " " . $risorsa['nome']);
            
            // Trova solo le macrocategorie a cui questa specifica risorsa è abbinata
            $cat_q = mysqli_query($conn, "
                SELECT DISTINCT i.categoria 
                FROM siform_iniziativa_formatore sif 
                JOIN iniziativa i ON sif.idi = i.idi 
                WHERE sif.emailf = '$emailf' 
                ORDER BY i.categoria ASC
            ");
    ?>
            <!-- BLOCCO CONTENITORE RISORSA -->
            <div style="background: white; border: 1px solid #cbd5e1; border-radius: 6px; padding: 20px; margin-bottom: 40px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
                <div style="border-bottom: 3px solid #1e293b; padding-bottom: 8px; margin-bottom: 20px;">
                    <h3 style="margin:0; color:#0f172a; font-size:18px;">👤 Risorsa: <?php echo $nome_completo; ?></h3>
                    <small style="background:#f1f5f9; color:#475569; padding:2px 8px; border-radius:4px; font-weight:bold; font-size:11px; margin-top:4px; display:inline-block; border:1px solid #e2e8f0;"><?php echo htmlspecialchars($risorsa['tipo']); ?> — <?php echo $emailf; ?></small>
                </div>

                <?php 
                if (mysqli_num_rows($cat_q) == 0) {
                    echo '<p style="margin:0; font-style:italic; color:#94a3b8; font-size:13px;">Nessuna linea di finanziamento o bando associato a questa risorsa.</p>';
                } else {
                    while ($cat_row = mysqli_fetch_assoc($cat_q)) {
                        $categoria = $cat_row['categoria'];
                        $tipo_canale = isset($categorie_struttura[$categoria]) ? $categorie_struttura[$categoria] : 'calendario';
                        
                        $cat_tot_pianificato = 0.00;
                        $cat_tot_firmato = 0.00;
                        
                        $righe_tabella = [];

                        // REPERIMENTO DATI FILTRATO PER TIPOLOGIA CANALE SIFORM
                        if ($tipo_canale === 'calendario') {
                            // Canale Calendario: Estrae le lezioni pianificate e firmate
                            $cal_sql = "
                                SELECT a.ida, a.tipo as attivita_nome, a.data, a.ora_inizio, a.ora_fine, a.ore as ore_pian,
                                       af.modalita_effettiva, af.ore_effettive, i.titolo, i.nome_siform
                                FROM assegnazione_formatore_attivita af
                                JOIN attivita a ON af.ida = a.ida
                                JOIN iniziativa i ON a.idi = i.idi
                                WHERE af.emailf = '$emailf' AND i.categoria = '$categoria'
                                ORDER BY a.data ASC, a.ora_inizio ASC
                            ";
                            $cal_res = mysqli_query($conn, $cal_sql);
                            while ($r = mysqli_fetch_assoc($cal_res)) {
                                $pian = (float)$r['ore_pian'];
                                $firm = 0.00;
                                if (in_array($r['modalita_effettiva'], ['Presenza', 'FAD'])) {
                                    $firm = ($r['ore_effettive'] !== null) ? (float)$r['ore_effettive'] : $pian;
                                }
                                
                                $cat_tot_pianificato += $pian;
                                $cat_tot_firmato += $firm;

                                // Identifica altri formatori presenti nella medesima lezione
                                $ida_corrente = (int)$r['ida'];
                                $co_f_q = mysqli_query($conn, "SELECT GROUP_CONCAT(CONCAT(f.cognome, ' ', f.nome) SEPARATOR ', ') as altri FROM assegnazione_formatore_attivita af JOIN formatore f ON af.emailf = f.emailf WHERE af.ida = $ida_corrente AND af.emailf != '$emailf'");
                                $co_f_row = mysqli_fetch_assoc($co_f_q);
                                $attori = $co_f_row['altri'] ? "Codocenza con: " . htmlspecialchars($co_f_row['altri']) : "Docenza Singola";

                                $righe_tabella[] = [
                                    'iniziativa' => $r['titolo'] ? $r['titolo'] : $r['nome_siform'],
                                    'attivita' => $r['attivita_nome'],
                                    'data' => date('d/m/Y', strtotime($r['data'])),
                                    'inizio' => substr($r['ora_inizio'], 0, 5),
                                    'fine' => substr($r['ora_fine'], 0, 5),
                                    'attori' => $attori,
                                    'pianificate' => $pian,
                                    'firmate' => $firm,
                                    'stato' => $r['modalita_effettiva'] ? "Firmata (".$r['modalita_effettiva'].")" : "⏳ Da Firmare"
                                ];
                            }
                        } else {
                            // Canale Timesheet: Estrae la pianificazione totale e i diari a blocchi registrati
                            $init_ts_q = mysqli_query($conn, "
                                SELECT i.idi, i.titolo, i.nome_siform, sif.ore_totali_assegnate 
                                FROM siform_iniziativa_formatore sif
                                JOIN iniziativa i ON sif.idi = i.idi
                                WHERE sif.emailf = '$emailf' AND i.categoria = '$categoria'
                            ");
                            while ($ini_ts = mysqli_fetch_assoc($init_ts_q)) {
                                $idi_ts = (int)$ini_ts['idi'];
                                // Nel timesheet, la pianificazione è totale a monte sulla macrocategoria
                                $cat_tot_pianificato += (float)$ini_ts['ore_totali_assegnate'];

                                $ts_sql = "
                                    SELECT data, ore, ora_inizio, ora_fine, descrizione 
                                    FROM dettaglio_timesheet 
                                    WHERE idi = $idi_ts AND emailf = '$emailf'
                                    ORDER BY data ASC
                                ";
                                $ts_res = mysqli_query($conn, $ts_sql);
                                while ($r = mysqli_fetch_assoc($ts_res)) {
                                    $firm = (float)$r['ore'];
                                    $cat_tot_firmato += $firm;

                                    $righe_tabella[] = [
                                        'iniziativa' => $ini_ts['titolo'] ? $ini_ts['titolo'] : $ini_ts['nome_siform'],
                                        'attivita' => $r['descrizione'],
                                        'data' => date('d/m/Y', strtotime($r['data'])),
                                        'inizio' => $r['ora_inizio'] ? substr($r['ora_inizio'], 0, 5) : '-',
                                        'fine' => $r['ora_fine'] ? substr($r['ora_fine'], 0, 5) : '-',
                                        'attori' => 'Staff Singolo (Timesheet)',
                                        'pianificate' => 0.00, // Le righe di rendicontazione non sono pianificate singolarmente
                                        'firmate' => $firm,
                                        'stato' => 'Registrato a Registro'
                                    ];
                                }
                            }
                        }
                ?>
                        <!-- SOTTO-SEZIONE MACROCATEGORIA SIFORM -->
                        <div style="margin-left: 10px; margin-bottom: 30px; border: 1px solid #e2e8f0; border-radius: 5px; overflow: hidden;">
                            <div style="background: #f8fafc; padding: 10px 15px; border-bottom: 1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                                <span style="font-weight: bold; color: #334155; font-size:14px;">📁 Macrocategoria: <?php echo htmlspecialchars($categoria); ?></span>
                                <span style="font-size:11px; font-weight:bold; color:white; background:<?php echo $tipo_canale==='timesheet'?'#f0ad4e':'#0275d8'; ?>; padding:3px 7px; border-radius:10px; text-transform:uppercase;"><?php echo $tipo_canale; ?> mode</span>
                            </div>

                            <?php if (empty($righe_tabella) && $cat_tot_pianificato == 0) { ?>
                                <p style="padding: 15px; margin:0; font-style:italic; color:#94a3b8; font-size:13px; background:#fff;">Nessuna riga di registro presente e nessun monte ore allocato.</p>
                            <?php } else { ?>
                                <table style="width:100%; border-collapse:collapse; font-size:12px; margin:0; border:none;">
                                    <thead>
                                        <tr style="background:#f1f5f9; color:#475569; border-bottom:1px solid #cbd5e1;">
                                            <th style="padding:8px; text-align:left; border:none; width:20%;">Iniziativa Madre</th>
                                            <th style="padding:8px; text-align:left; border:none; width:30%;">Descrizione Attività / Lezione</th>
                                            <th style="padding:8px; text-align:center; border:none; width:10%;">Data</th>
                                            <th style="padding:8px; text-align:center; border:none; width:7%;">Inizio</th>
                                            <th style="padding:8px; text-align:center; border:none; width:7%;">Fine</th>
                                            <th style="padding:8px; text-align:left; border:none; width:14%;">Attori Coinvolti</th>
                                            <th style="padding:8px; text-align:center; border:none; width:6%;">Pianif.</th>
                                            <th style="padding:8px; text-align:center; border:none; width:6%; background:#f0fdf4; color:#166534;">Firmate</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        if (empty($righe_tabella)) {
                                            echo '<tr><td colspan="8" style="padding:15px; text-align:center; color:#94a3b8; font-style:italic; background:#fff;">Nessuna attività singola ancora registrata. Ore di budget allocate pronte all\'uso.</td></tr>';
                                        } else {
                                            foreach ($righe_tabella as $riga) { 
                                        ?>
                                            <tr style="border-bottom:1px solid #f1f5f9; background:#fff;">
                                                <td style="padding:8px; color:#475569;"><?php echo htmlspecialchars($riga['iniziativa']); ?></td>
                                                <td style="padding:8px; font-weight:600; color:#334155;"><?php echo htmlspecialchars($riga['attivita']); ?></td>
                                                <td style="padding:8px; text-align:center; color:#0275d8; font-weight:600;"><?php echo $riga['data']; ?></td>
                                                <td style="padding:8px; text-align:center; color:#64748b;"><?php echo $riga['inizio']; ?></td>
                                                <td style="padding:8px; text-align:center; color:#64748b;"><?php echo $riga['fine']; ?></td>
                                                <td style="padding:8px; color:#64748b; font-size:11px;"><?php echo $riga['attori']; ?></td>
                                                <td style="padding:8px; text-align:center; color:#64748b; font-weight:500;"><?php echo $riga['pianificate'] > 0 ? number_format($riga['pianificate'], 1) . " h" : "-"; ?></td>
                                                <td style="padding:8px; text-align:center; font-weight:bold; color:#16a34a; background:#f0fdf4; font-size:13px;"><?php echo $riga['firmate'] > 0 ? number_format($riga['firmate'], 1) . " h" : "0.0 h"; ?></td>
                                            </tr>
                                        <?php 
                                            } 
                                        }
                                        ?>
                                        <!-- RIGA TOTALI MACROCATEGORIA -->
                                        <tr style="background:#e2e8f0; font-weight:bold; color:#1e293b; border-top:1px solid #cbd5e1;">
                                            <td colspan="6" style="padding:10px; text-align:right; font-size:12px; text-transform:uppercase; letter-spacing:0.5px;">
                                                🧮 TOTALI RENDICONTAZIONE [<?php echo htmlspecialchars(substr($categoria, 0, 7)); ?>...]:
                                            </td>
                                            <td style="padding:10px; text-align:center; font-size:13px; color:#1e3a8a; background:#dbeafe;">
                                                Pianif: <?php echo number_format($cat_tot_pianificato, 1); ?> h
                                            </td>
                                            <td style="padding:10px; text-align:center; font-size:13px; color:#065f46; background:#bbf7d0;">
                                                Firmate: <?php echo number_format($cat_tot_firmato, 1); ?> h
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            <?php } ?>
                        </div>
                <?php 
                    } 
                } 
                ?>
            </div>
    <?php 
        } 
    }
    ?>
    
    <div style="margin-top:20px; margin-bottom:30px;">
        <a href="index.php" class="btn" style="background:#475569; color:white; text-decoration:none; padding:10px 20px; border-radius:4px; font-weight:bold; display:inline-block;">⬅️ Torna alla Dashboard Generale</a>
    </div>
</div>