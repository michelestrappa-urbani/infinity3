<?php
// calendario_info.php - Visualizzazione Calendario Attività (Sola Lettura)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include('db.php');

$idi = isset($_GET['idi']) ? (int)$_GET['idi'] : 0;
if ($idi <= 0) {
    header("Location: index_info.php");
    exit();
}
 
// 1. Recupero Dati Iniziativa
$corso_q = mysqli_query($conn, "SELECT * FROM iniziativa WHERE idi = $idi");
$corso = mysqli_fetch_assoc($corso_q);
if (!$corso) {
    die("<div style='font-family:sans-serif; padding:30px; text-align:center;'>
            <h2>❌ Iniziativa non trovata</h2>
            <p><a href='index_info.php' style='color:#0284c7; text-decoration:none; font-weight:bold;'>⬅️ Torna all'elenco iniziative</a></p>
         </div>");
}

$titolo_corso = !empty($corso['titolo']) ? $corso['titolo'] : $corso['nome_siform'];
$giorni_it = ['Domenica', 'Lunedì', 'Martedì', 'Mercoledì', 'Giovedì', 'Venerdì', 'Sabato'];

// 2. Formatori Globali del Corso
$formatori_glob_q = mysqli_query($conn, "SELECT f.nome, f.cognome, f.emailf FROM siform_iniziativa_formatore sif JOIN formatore f ON sif.emailf = f.emailf WHERE sif.idi = $idi ORDER BY f.cognome");
$formatori_glob = [];
while ($f = mysqli_fetch_assoc($formatori_glob_q)) {
    $formatori_glob[] = $f['cognome'] . ' ' . $f['nome'];
}

// 3. Classi Globali
$classi_glob_q = mysqli_query($conn, "SELECT c.nome, c.sede FROM siform_iniziativa_classe sic JOIN classe c ON sic.idc = c.idc WHERE sic.idi = $idi ORDER BY c.nome");
$classi_glob = [];
while ($c = mysqli_fetch_assoc($classi_glob_q)) {
    $classi_glob[] = $c['nome'] . (!empty($c['sede']) ? " (" . $c['sede'] . ")" : "");
}

// 4. Docenti Discenti Globali
$docenti_glob_q = mysqli_query($conn, "SELECT d.nome, d.cognome FROM siform_iniziativa_docentediscente sidd JOIN docente_discente d ON sidd.email_dd = d.email_dd WHERE sidd.idi = $idi ORDER BY d.cognome");
$docenti_glob = [];
while ($d = mysqli_fetch_assoc($docenti_glob_q)) {
    $docenti_glob[] = $d['cognome'] . ' ' . $d['nome'];
}

// 5. Attività a Calendario
$att_q = mysqli_query($conn, "SELECT * FROM attivita WHERE idi = $idi ORDER BY data ASC, ora_inizio ASC");
$attivita = [];
$totale_ore_calendario = 0.0;

while ($act = mysqli_fetch_assoc($att_q)) {
    $ida = (int)$act['ida'];
    $totale_ore_calendario += (float)$act['ore'];

    // Formatori lezione
    $fl_q = mysqli_query($conn, "SELECT f.cognome, f.nome FROM assegnazione_formatore_attivita afa JOIN formatore f ON afa.emailf = f.emailf WHERE afa.ida = $ida ORDER BY f.cognome");
    $fl_list = [];
    while ($fl = mysqli_fetch_assoc($fl_q)) { $fl_list[] = $fl['cognome'] . ' ' . substr($fl['nome'], 0, 1) . '.'; }
    $act['formatori_lezione'] = $fl_list;

    // Classi lezione
    $cl_q = mysqli_query($conn, "SELECT c.nome FROM assegnazione_classe_attivita aca JOIN classe c ON aca.idc = c.idc WHERE aca.ida = $ida ORDER BY c.nome");
    $cl_list = [];
    while ($cl = mysqli_fetch_assoc($cl_q)) { $cl_list[] = $cl['nome']; }
    $act['classi_lezione'] = $cl_list;

    // Docenti lezione
    $dl_q = mysqli_query($conn, "SELECT d.cognome, d.nome FROM assegnazione_docente_attivita ada JOIN docente_discente d ON ada.email_dd = d.email_dd WHERE ada.ida = $ida ORDER BY d.cognome");
    $dl_list = [];
    while ($dl = mysqli_fetch_assoc($dl_q)) { $dl_list[] = $dl['cognome'] . ' ' . substr($dl['nome'], 0, 1) . '.'; }
    $act['docenti_lezione'] = $dl_list;

    $attivita[] = $act;
}

// 6. Attività Diario Timesheet (se presenti)
$ts_q = mysqli_query($conn, "SELECT dt.*, f.cognome, f.nome FROM dettaglio_timesheet dt JOIN formatore f ON dt.emailf = f.emailf WHERE dt.idi = $idi ORDER BY dt.data ASC, dt.ora_inizio ASC");
$timesheets = [];
$totale_ore_timesheet = 0.0;
while ($ts = mysqli_fetch_assoc($ts_q)) {
    $totale_ore_timesheet += (float)$ts['ore'];
    $timesheets[] = $ts;
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendario Iniziativa: <?php echo htmlspecialchars($titolo_corso); ?></title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            background: #f1f5f9;
            color: #1e293b;
            margin: 0;
            padding: 0;
        }

        .info-navbar {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: #ffffff;
            padding: 14px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .info-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            color: #ffffff;
        }
        .info-brand-icon { font-size: 24px; }
        .info-brand-title { font-size: 16px; font-weight: 700; }
        .info-brand-sub { font-size: 12px; color: #94a3b8; }

        .nav-actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .btn-back {
            background: rgba(255, 255, 255, 0.12);
            color: #ffffff;
            text-decoration: none;
            padding: 7px 14px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: background 0.2s;
        }
        .btn-back:hover { background: rgba(255, 255, 255, 0.22); }
        .btn-print {
            background: #0284c7;
            color: #ffffff;
            border: none;
            padding: 7px 14px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-print:hover { background: #0369a1; }

        .container {
            max-width: 1400px;
            margin: 24px auto;
            padding: 0 20px 40px 20px;
        }

        .course-card {
            background: #ffffff;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            padding: 24px;
            margin-bottom: 24px;
        }
        .course-cat {
            display: inline-block;
            background: #e0f2fe;
            color: #0369a1;
            font-size: 12px;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 4px;
            margin-bottom: 10px;
        }
        .course-title {
            margin: 0 0 12px 0;
            font-size: 22px;
            font-weight: 700;
            color: #0f172a;
        }
        .course-meta-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 16px;
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid #f1f5f9;
            font-size: 13px;
        }
        .meta-item strong { color: #475569; display: block; font-size: 11px; text-transform: uppercase; margin-bottom: 3px; }
        .meta-item span { color: #0f172a; font-weight: 500; font-size: 14px; }

        .stats-bar {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            margin-top: 16px;
        }
        .stat-pill {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 8px 14px;
            border-radius: 6px;
            font-size: 13px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .stat-pill strong { color: #0f172a; font-size: 15px; }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 14px;
        }
        .section-header h3 {
            margin: 0;
            font-size: 18px;
            font-weight: 700;
            color: #0f172a;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .table-card {
            background: #ffffff;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            overflow: hidden;
            margin-bottom: 30px;
        }
        .cal-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13.5px;
            text-align: left;
        }
        .cal-table th {
            background: #f8fafc;
            color: #475569;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 12px 14px;
            border-bottom: 2px solid #e2e8f0;
        }
        .cal-table td {
            padding: 12px 14px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }
        .cal-table tr:hover { background: #f8fafc; }

        .badge-mode {
            padding: 3px 7px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
        }
        .badge-presenza { background: #dbeafe; color: #1e40af; }
        .badge-fad { background: #fef3c7; color: #92400e; }
        .badge-mista { background: #f3e8ff; color: #6b21a8; }

        .meet-link-btn {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            color: #15803d;
            font-weight: 600;
            text-decoration: none;
            font-size: 12px;
            margin-top: 3px;
        }
        .meet-link-btn:hover { text-decoration: underline; }

        @media print {
            .info-navbar, .btn-print, .btn-back { display: none !important; }
            body { background: #ffffff; color: #000000; }
            .container { max-width: 100%; margin: 0; padding: 0; }
            .course-card, .table-card { box-shadow: none; border: 1px solid #ccc; }
        }
    </style>
</head>
<body>

    <header class="info-navbar">
        <a href="index_info.php" class="info-brand">
            <span class="info-brand-icon">🏛️</span>
            <div>
                <div class="info-brand-title">Consultazione Iniziative & Calendari</div>
                <div class="info-brand-sub">SIFORM • Polo Urbani</div>
            </div>
        </a>
        <div class="nav-actions">
            <button type="button" class="btn-print" onclick="window.print()">🖨️ Stampa Calendario</button>
            <a href="index_info.php" class="btn-back">⬅️ Tutte le Iniziative</a>
        </div>
    </header>

    <div class="container">
        
        <div class="course-card">
            <?php if (!empty($corso['categoria'])): ?>
                <span class="course-cat"><?php echo htmlspecialchars($corso['categoria']); ?></span>
            <?php endif; ?>
            <h1 class="course-title"><?php echo htmlspecialchars($titolo_corso); ?></h1>
            
            <?php if (!empty($corso['nome_siform']) && $corso['nome_siform'] !== $corso['titolo']): ?>
                <div style="font-size: 13px; color: #64748b; margin-top: -6px; margin-bottom: 12px;">
                    Codice Identificativo SIFORM: <strong><?php echo htmlspecialchars($corso['nome_siform']); ?></strong>
                </div>
            <?php endif; ?>

            <div class="course-meta-grid">
                <div class="meta-item">
                    <strong>Periodo di Svolgimento</strong>
                    <span>
                        <?php 
                        $di = !empty($corso['data_inizio']) ? date('d/m/Y', strtotime($corso['data_inizio'])) : 'Da definire';
                        $df = !empty($corso['data_fine']) ? date('d/m/Y', strtotime($corso['data_fine'])) : 'Da definire';
                        echo $di . " — " . $df;
                        ?>
                    </span>
                </div>
                <div class="meta-item">
                    <strong>Staff Formatori / Esperti</strong>
                    <span><?php echo !empty($formatori_glob) ? htmlspecialchars(implode(', ', $formatori_glob)) : '<em style="color:#94a3b8;">Non assegnati</em>'; ?></span>
                </div>
                <?php if (!empty($classi_glob)): ?>
                    <div class="meta-item">
                        <strong>Classi Convocate</strong>
                        <span><?php echo htmlspecialchars(implode(', ', $classi_glob)); ?></span>
                    </div>
                <?php endif; ?>
                <?php if (!empty($docenti_glob)): ?>
                    <div class="meta-item">
                        <strong>Docenti Discenti Iscritti</strong>
                        <span><?php echo htmlspecialchars(implode(', ', $docenti_glob)); ?></span>
                    </div>
                <?php endif; ?>
                <?php if (!empty($corso['google_meet_link'])): ?>
                    <div class="meta-item">
                        <strong>Aula Virtuale Google Meet Principale</strong>
                        <span>
                            <a href="<?php echo htmlspecialchars($corso['google_meet_link']); ?>" target="_blank" class="meet-link-btn">
                                💻 Accedi alla Stanza Meet del Corso ➔
                            </a>
                        </span>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (!empty($corso['note'])): ?>
                <div style="margin-top: 16px; padding: 12px; background: #f8fafc; border-radius: 6px; font-size: 13px; color: #475569; border-left: 3px solid #cbd5e1;">
                    <strong>Note corso:</strong> <?php echo nl2br(htmlspecialchars($corso['note'])); ?>
                </div>
            <?php endif; ?>

            <div class="stats-bar">
                <div class="stat-pill">
                    <span>🗓️ Sessioni a Calendario:</span>
                    <strong><?php echo count($attivita); ?></strong>
                </div>
                <div class="stat-pill">
                    <span>⏱️ Monte Ore Calendario:</span>
                    <strong style="color: #0284c7;"><?php echo number_format($totale_ore_calendario, 1); ?> h</strong>
                </div>
                <?php if (!empty($timesheets)): ?>
                    <div class="stat-pill">
                        <span>📝 Attività Staff Registrate:</span>
                        <strong><?php echo count($timesheets); ?> (<?php echo number_format($totale_ore_timesheet, 1); ?> h)</strong>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Tabella Calendario Sessioni -->
        <div class="section-header">
            <h3>📅 Calendario delle Sessioni Didattiche</h3>
            <span style="font-size: 13px; color: #64748b;">Ordinamento cronologico</span>
        </div>

        <div class="table-card">
            <table class="cal-table">
                <thead>
                    <tr>
                        <th style="width: 15%;">Giorno e Data</th>
                        <th style="width: 13%;">Orario &amp; Durata</th>
                        <th style="width: 23%;">Attività / Sede / Modalità</th>
                        <th style="width: 23%;">Personale e Partecipanti</th>
                        <th style="width: 14%;">Note Sessione</th>
                        <th style="width: 12%; text-align: center;">Registro Firme</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($attivita)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 40px; color: #94a3b8; font-style: italic;">
                                Nessuna sessione didattica programmata a calendario per questa iniziativa.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach($attivita as $act): 
                            $timestamp = strtotime($act['data']);
                            $num_giorno = date('w', $timestamp);
                            $nome_giorno = $giorni_it[$num_giorno] ?? '';
                            $data_it = date('d/m/Y', $timestamp);
                            $ora_in = substr($act['ora_inizio'], 0, 5);
                            $ora_fi = substr($act['ora_fine'], 0, 5);
                            
                            $mod = $act['partecipazione'] ?: 'Presenza';
                            $mod_class = 'badge-presenza';
                            if (stripos($mod, 'FAD') !== false) {
                                $mod_class = (stripos($mod, 'Presenza') !== false) ? 'badge-mista' : 'badge-fad';
                            }
                            
                            $meet_url = !empty($act['google_meet_link']) ? $act['google_meet_link'] : $corso['google_meet_link'];
                            $is_online = (stripos($mod, 'FAD') !== false || stripos($mod, 'ONLINE') !== false || stripos($mod, 'Mista') !== false);
                        ?>
                            <tr>
                                <td>
                                    <div style="font-weight: 700; color: #0f172a; font-size: 14px;"><?php echo $nome_giorno; ?></div>
                                    <div style="color: #0284c7; font-weight: 600; font-size: 13px;"><?php echo $data_it; ?></div>
                                </td>
                                <td>
                                    <div style="font-weight: 700; font-size: 14px; color: #1e293b;"><?php echo $ora_in . " - " . $ora_fi; ?></div>
                                    <div style="color: #64748b; font-size: 12px; margin-top: 2px;">
                                        <strong><?php echo number_format($act['ore'], 1); ?></strong> ore
                                    </div>
                                </td>
                                <td>
                                    <div style="font-weight: 600; font-size: 14px; color: #0f172a; text-transform: uppercase;">
                                        <?php echo htmlspecialchars($act['tipo']); ?>
                                    </div>
                                    <?php if (!empty($act['sede'])): ?>
                                        <div style="font-size: 12px; color: #475569; margin-top: 2px;">
                                            📍 Sede: <strong><?php echo htmlspecialchars($act['sede']); ?></strong>
                                        </div>
                                    <?php endif; ?>
                                    <div style="margin-top: 4px;">
                                        <span class="badge-mode <?php echo $mod_class; ?>"><?php echo htmlspecialchars($mod); ?></span>
                                    </div>
                                    <?php if ($is_online && !empty($meet_url)): ?>
                                        <div>
                                            <a href="<?php echo htmlspecialchars($meet_url); ?>" target="_blank" class="meet-link-btn" title="Collegati alla lezione">
                                                💻 Stanza Google Meet ➔
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <div style="font-size: 12.5px; line-height: 1.45;">
                                        <div>
                                            👤 <strong>Staff:</strong> 
                                            <?php echo !empty($act['formatori_lezione']) ? htmlspecialchars(implode(', ', $act['formatori_lezione'])) : '<span style="color:#94a3b8; font-style:italic;">Nessuno</span>'; ?>
                                        </div>
                                        <?php if (!empty($act['classi_lezione'])): ?>
                                            <div>
                                                👥 <strong>Classi:</strong> <?php echo htmlspecialchars(implode(', ', $act['classi_lezione'])); ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($act['docenti_lezione'])): ?>
                                            <div>
                                                🎓 <strong>Discenti:</strong> <?php echo htmlspecialchars(implode(', ', $act['docenti_lezione'])); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-size: 12.5px; color: #475569;">
                                        <?php echo !empty($act['note']) ? nl2br(htmlspecialchars($act['note'])) : '<span style="color:#cbd5e1; font-style:italic;">Nessuna nota</span>'; ?>
                                    </div>
                                </td>
                                <td style="text-align: center; vertical-align: middle;">
                                    <a href="foglio_firme_info.php?ida=<?php echo $act['ida']; ?>" style="display: inline-flex; align-items: center; gap: 4px; background: #16a34a; color: white; text-decoration: none; padding: 6px 10px; border-radius: 5px; font-weight: 600; font-size: 12px; box-shadow: 0 1px 2px rgba(0,0,0,0.1); white-space: nowrap;">
                                        ✍️ Foglio Firme
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <tr style="background: #f8fafc; font-weight: 700; border-top: 2px solid #cbd5e1;">
                            <td colspan="2" style="padding: 12px 14px; color: #0f172a;">
                                Totale Ore Calendario:
                            </td>
                            <td colspan="4" style="padding: 12px 14px; color: #0284c7; font-size: 15px;">
                                <?php echo number_format($totale_ore_calendario, 1); ?> ore complessive (<?php echo count($attivita); ?> sessioni)
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if (!empty($timesheets)): ?>
            <div class="section-header" style="margin-top: 35px;">
                <h3>📋 Registro Attività di Staff (Timesheet Interno)</h3>
                <span style="font-size: 13px; color: #64748b;">Attività rendicontate in sola lettura</span>
            </div>

            <div class="table-card">
                <table class="cal-table">
                    <thead>
                        <tr>
                            <th style="width: 14%;">Data</th>
                            <th style="width: 22%;">Operatore / Staff</th>
                            <th style="width: 50%;">Descrizione Attività</th>
                            <th style="width: 14%; text-align: right;">Ore</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($timesheets as $ts): 
                            $orario_ts = (!empty($ts['ora_inizio']) && !empty($ts['ora_fine'])) ? " (" . substr($ts['ora_inizio'], 0, 5) . " - " . substr($ts['ora_fine'], 0, 5) . ")" : "";
                        ?>
                            <tr>
                                <td>
                                    <strong style="color: #0f172a;"><?php echo date('d/m/Y', strtotime($ts['data'])); ?></strong>
                                    <div style="font-size: 11px; color: #64748b;"><?php echo $orario_ts; ?></div>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($ts['cognome'] . ' ' . $ts['nome']); ?></strong>
                                </td>
                                <td>
                                    <?php echo nl2br(htmlspecialchars($ts['descrizione'])); ?>
                                </td>
                                <td style="text-align: right; font-weight: 700; color: #16a34a; font-size: 14px;">
                                    <?php echo number_format($ts['ore'], 2); ?> h
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <tr style="background: #f8fafc; font-weight: 700; border-top: 2px solid #cbd5e1;">
                            <td colspan="3" style="padding: 12px 14px; color: #0f172a;">
                                Totale Ore Staff Rendicontate:
                            </td>
                            <td style="text-align: right; padding: 12px 14px; color: #16a34a; font-size: 15px;">
                                <?php echo number_format($totale_ore_timesheet, 2); ?> ore
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <div style="text-align: center; color: #94a3b8; font-size: 12px; margin-top: 30px;">
            SIFORM • Polo Tecnico Professionale "Carlo Urbani" • Sola Consultazione
        </div>

    </div>

</body>
</html>

