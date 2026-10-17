<?php
// index_info.php - Consultazione Iniziative & Calendari (Sola Lettura)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include('db.php');

// Estrazione iniziative con riepilogo sessioni e ore
$sql = "SELECT i.*, 
        (SELECT COUNT(*) FROM attivita a WHERE a.idi = i.idi) as tot_sessioni,
        (SELECT COALESCE(SUM(a.ore), 0) FROM attivita a WHERE a.idi = i.idi) as tot_ore_attivita,
        (SELECT COUNT(*) FROM dettaglio_timesheet dt WHERE dt.idi = i.idi) as tot_timesheet,
        (SELECT COALESCE(SUM(dt.ore), 0) FROM dettaglio_timesheet dt WHERE dt.idi = i.idi) as tot_ore_timesheet
        FROM iniziativa i 
        ORDER BY i.categoria ASC, i.data_inizio DESC, i.idi DESC";

$res = mysqli_query($conn, $sql);

$iniziative = [];
$tutte_le_categorie = [];

while ($row = mysqli_fetch_assoc($res)) {
    $idi = (int)$row['idi'];
    
    // Formatori
    $f_q = mysqli_query($conn, "SELECT CONCAT(f.cognome, ' ', f.nome) as nominativo 
                                FROM siform_iniziativa_formatore sif 
                                JOIN formatore f ON sif.emailf = f.emailf 
                                WHERE sif.idi = $idi ORDER BY f.cognome ASC");
    $formatori = [];
    while ($f_row = mysqli_fetch_assoc($f_q)) { $formatori[] = $f_row['nominativo']; }
    $row['formatori_str'] = !empty($formatori) ? implode(', ', $formatori) : 'Non assegnati';

    // Classi
    $c_q = mysqli_query($conn, "SELECT c.nome FROM siform_iniziativa_classe sic JOIN classe c ON sic.idc = c.idc WHERE sic.idi = $idi ORDER BY c.nome ASC");
    $classi = [];
    while ($c_row = mysqli_fetch_assoc($c_q)) { $classi[] = $c_row['nome']; }
    $row['classi_str'] = !empty($classi) ? implode(', ', $classi) : '';

    // Docenti discenti
    $d_q = mysqli_query($conn, "SELECT CONCAT(d.cognome, ' ', d.nome) as nominativo FROM siform_iniziativa_docentediscente sidd JOIN docente_discente d ON sidd.email_dd = d.email_dd WHERE sidd.idi = $idi ORDER BY d.cognome ASC");
    $docenti = [];
    while ($d_row = mysqli_fetch_assoc($d_q)) { $docenti[] = $d_row['nominativo']; }
    $row['docenti_str'] = !empty($docenti) ? implode(', ', $docenti) : '';

    $cat = $row['categoria'] ?: 'Altro';
    $tutte_le_categorie[$cat] = true;
    $iniziative[] = $row;
}
ksort($tutte_le_categorie);
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consultazione Iniziative & Calendari - Polo Urbani</title>
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
        .info-brand-icon { font-size: 26px; }
        .info-brand-title { font-size: 18px; font-weight: 700; letter-spacing: -0.3px; }
        .info-brand-sub { font-size: 12px; color: #94a3b8; font-weight: 400; }
        .info-badge-readonly {
            background: rgba(56, 189, 248, 0.15);
            color: #38bdf8;
            border: 1px solid rgba(56, 189, 248, 0.35);
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .container {
            max-width: 1400px;
            margin: 24px auto;
            padding: 0 20px 40px 20px;
        }

        .banner-info {
            background: #ffffff;
            border-left: 4px solid #0284c7;
            border-radius: 8px;
            padding: 16px 20px;
            margin-bottom: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        .banner-info p { margin: 0; font-size: 14px; color: #475569; line-height: 1.5; }

        .filter-bar {
            background: #ffffff;
            padding: 16px 20px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            margin-bottom: 24px;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: center;
        }
        .filter-input {
            flex: 2;
            min-width: 260px;
            padding: 10px 14px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 14px;
            outline: none;
        }
        .filter-input:focus {
            border-color: #0284c7;
            box-shadow: 0 0 0 2px rgba(2, 132, 199, 0.15);
        }
        .filter-select {
            flex: 1;
            min-width: 220px;
            padding: 10px 14px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 14px;
            background: #ffffff;
            outline: none;
        }
        .btn-reset {
            padding: 10px 16px;
            background: #f1f5f9;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            color: #475569;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
        }
        .btn-reset:hover { background: #e2e8f0; color: #1e293b; }

        .table-card {
            background: #ffffff;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            overflow: hidden;
            border: 1px solid #e2e8f0;
        }
        .info-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
            text-align: left;
        }
        .info-table th {
            background: #f8fafc;
            color: #475569;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 14px 16px;
            border-bottom: 2px solid #e2e8f0;
        }
        .info-table td {
            padding: 14px 16px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: top;
        }
        .info-table tr:hover { background: #f8fafc; }

        .badge-cat {
            display: inline-block;
            background: #e0f2fe;
            color: #0369a1;
            font-size: 11px;
            font-weight: 600;
            padding: 3px 8px;
            border-radius: 4px;
            margin-bottom: 5px;
        }
        .badge-ore {
            display: inline-block;
            background: #ecfdf5;
            color: #065f46;
            font-size: 11px;
            font-weight: 600;
            padding: 3px 8px;
            border-radius: 4px;
            border: 1px solid #a7f3d0;
        }
        .meet-link-btn {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: #10b981;
            color: #ffffff;
            text-decoration: none;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            margin-top: 4px;
        }
        .meet-link-btn:hover { background: #059669; }

        .btn-view-cal {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #0284c7;
            color: #ffffff;
            padding: 8px 14px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            transition: background 0.2s;
            white-space: nowrap;
        }
        .btn-view-cal:hover { background: #0369a1; }

        .empty-row {
            text-align: center;
            padding: 40px !important;
            color: #94a3b8;
            font-style: italic;
        }
        .info-footer {
            text-align: center;
            color: #94a3b8;
            font-size: 12px;
            margin-top: 30px;
        }
    </style>
</head>
<body>

    <header class="info-navbar">
        <a href="index_info.php" class="info-brand">
            <span class="info-brand-icon">🏛️</span>
            <div>
                <div class="info-brand-title">Consultazione Iniziative & Calendari Didattici</div>
                <div class="info-brand-sub">SIFORM • Polo Tecnico Professionale "Carlo Urbani"</div>
            </div>
        </a>
        <div class="nav-actions" style="display: flex; align-items: center; gap: 12px;">
            <a href="dashboard_info.php" style="background: rgba(56, 189, 248, 0.2); color: #ffffff; border: 1px solid rgba(56, 189, 248, 0.45); text-decoration: none; padding: 6px 14px; border-radius: 6px; font-size: 13px; font-weight: 600; display: inline-flex; align-items: center; gap: 6px; transition: background 0.2s;">
                📊 Dashboard Monitoraggio
            </a>
            <div class="info-badge-readonly">
                <span>👁️</span> Sola Consultazione (Lettura)
            </div>
        </div>
    </header>

    <div class="container">
        
        <div class="banner-info">
            <div>
                <strong style="color: #0369a1; font-size: 15px;">Benvenuto nell'area di consultazione</strong>
                <p>Visualizzazione informativa delle iniziative, dei calendari delle attività e della dashboard di monitoraggio in modalità di sola lettura.</p>
            </div>
            <div style="text-align: right; font-size: 13px; color: #64748b; display: flex; flex-direction: column; align-items: flex-end; gap: 6px;">
                <div>Iniziative registrate: <strong style="color: #0f172a;"><?php echo count($iniziative); ?></strong></div>
                <a href="dashboard_info.php" style="display: inline-flex; align-items: center; gap: 6px; background: #0284c7; color: white; text-decoration: none; padding: 6px 12px; border-radius: 5px; font-weight: 600; font-size: 12px;">
                    📊 Vai alla Dashboard KPI
                </a>
            </div>
        </div>

        <div class="filter-bar">
            <input type="text" id="searchInput" class="filter-input" placeholder="🔍 Cerca per titolo, codice SIFORM, formatore o classe..." onkeyup="filtraTabella()">
            <select id="catSelect" class="filter-select" onchange="filtraTabella()">
                <option value="">-- Tutte le categorie SIFORM --</option>
                <?php foreach(array_keys($tutte_le_categorie) as $categoria): ?>
                    <option value="<?php echo htmlspecialchars($categoria); ?>"><?php echo htmlspecialchars($categoria); ?></option>
                <?php endforeach; ?>
            </select>
            <button type="button" class="btn-reset" onclick="resetFiltri()">Azzera Filtri</button>
        </div>

        <div class="table-card">
            <table class="info-table" id="iniziativeTable">
                <thead>
                    <tr>
                        <th style="width: 32%;">Iniziativa / Codice</th>
                        <th style="width: 14%;">Periodo Svolgimento</th>
                        <th style="width: 22%;">Staff / Formatori</th>
                        <th style="width: 18%;">Destinatari / Partecipanti</th>
                        <th style="width: 14%; text-align: center;">Calendario</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($iniziative)): ?>
                        <tr>
                            <td colspan="5" class="empty-row">Nessuna iniziativa trovata nel sistema.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach($iniziative as $row): 
                            $titolo = !empty($row['titolo']) ? $row['titolo'] : $row['nome_siform'];
                            $data_in = !empty($row['data_inizio']) ? date('d/m/Y', strtotime($row['data_inizio'])) : 'Da definire';
                            $data_fi = !empty($row['data_fine']) ? date('d/m/Y', strtotime($row['data_fine'])) : 'Da definire';
                            
                            $tot_sessioni = (int)$row['tot_sessioni'];
                            $tot_ore = (float)$row['tot_ore_attivita'];
                            if ($tot_sessioni === 0 && (int)$row['tot_timesheet'] > 0) {
                                $tot_sessioni = (int)$row['tot_timesheet'];
                                $tot_ore = (float)$row['tot_ore_timesheet'];
                            }
                        ?>
                            <tr class="iniziativa-row" data-cat="<?php echo htmlspecialchars($row['categoria']); ?>">
                                <td>
                                    <?php if (!empty($row['categoria'])): ?>
                                        <span class="badge-cat"><?php echo htmlspecialchars($row['categoria']); ?></span><br>
                                    <?php endif; ?>
                                    <strong style="font-size: 15px; color: #0f172a;"><?php echo htmlspecialchars($titolo); ?></strong>
                                    <?php if (!empty($row['nome_siform']) && $row['nome_siform'] !== $row['titolo']): ?>
                                        <div style="font-size: 12px; color: #64748b; margin-top: 2px;">
                                            Codice SIFORM: <em><?php echo htmlspecialchars($row['nome_siform']); ?></em>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($row['google_meet_link'])): ?>
                                        <div>
                                            <a href="<?php echo htmlspecialchars($row['google_meet_link']); ?>" target="_blank" class="meet-link-btn" title="Collegati alla stanza Meet">
                                                💻 Stanza Google Meet
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="font-size: 13px;">
                                        <div style="color: #64748b; font-size: 11px; text-transform: uppercase;">Inizio:</div>
                                        <strong><?php echo $data_in; ?></strong>
                                        <div style="color: #64748b; font-size: 11px; text-transform: uppercase; margin-top: 4px;">Fine:</div>
                                        <strong><?php echo $data_fi; ?></strong>
                                    </div>
                                </td>

                                <td>
                                    <div style="font-size: 13px; color: #334155;">
                                        <?php if ($row['formatori_str'] !== 'Non assegnati'): ?>
                                            👤 <strong><?php echo htmlspecialchars($row['formatori_str']); ?></strong>
                                        <?php else: ?>
                                            <span style="color: #94a3b8; font-style: italic;">Nessun formatore</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-size: 13px;">
                                        <?php if (!empty($row['classi_str'])): ?>
                                            <div style="margin-bottom: 4px;">
                                                👥 <strong>Classi:</strong> <?php echo htmlspecialchars($row['classi_str']); ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($row['docenti_str'])): ?>
                                            <div>
                                                🎓 <strong>Docenti:</strong> <span style="color: #475569;"><?php echo htmlspecialchars($row['docenti_str']); ?></span>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (empty($row['classi_str']) && empty($row['docenti_str'])): ?>
                                            <?php 
                                            $is_destinatari_cat = (stripos($row['categoria'] ?? '', 'B.1.13') !== false || stripos($row['categoria'] ?? '', 'destinatari') !== false);
                                            if ($is_destinatari_cat): 
                                            ?>
                                                <span style="color: #64748b; font-style: italic; font-weight: 500;">Vedi Calendario &gt; Note Sessione</span>
                                            <?php else: ?>
                                                <span style="color: #94a3b8; font-style: italic;">Nessun partecipante</span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td style="text-align: center; vertical-align: middle;">
                                    <a href="calendario_info.php?idi=<?php echo $row['idi']; ?>" class="btn-view-cal">
                                        🗓️ Vedi Calendario
                                    </a>
                                    <div style="margin-top: 6px;">
                                        <span class="badge-ore">
                                            <?php echo $tot_sessioni; ?> sess. (<?php echo number_format($tot_ore, 1); ?> h)
                                        </span>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="info-footer">
            Portale informativo SIFORM • Polo Tecnico Professionale "Carlo Urbani" • Sola Consultazione
        </div>

    </div>

    <script>
    function filtraTabella() {
        const query = document.getElementById('searchInput').value.toLowerCase().trim();
        const catFilter = document.getElementById('catSelect').value.toLowerCase().trim();
        const rows = document.querySelectorAll('#iniziativeTable tbody tr.iniziativa-row');

        rows.forEach(row => {
            const rowText = row.textContent.toLowerCase();
            const rowCat = (row.getAttribute('data-cat') || '').toLowerCase();

            const matchQuery = query === '' || rowText.includes(query);
            const matchCat = catFilter === '' || rowCat.includes(catFilter);

            if (matchQuery && matchCat) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }

    function resetFiltri() {
        document.getElementById('searchInput').value = '';
        document.getElementById('catSelect').value = '';
        filtraTabella();
    }
    </script>
</body>
</html>

