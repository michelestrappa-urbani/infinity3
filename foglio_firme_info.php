<?php
// foglio_firme_info.php - Visualizzazione Registro Foglio Firme (Sola Consultazione)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include('db.php');

$ida = isset($_GET['ida']) ? (int)$_GET['ida'] : 0;
if ($ida <= 0) {
    header("Location: index_info.php");
    exit();
}

// 1. Dati attività e iniziativa
$act_q = mysqli_query($conn, "
    SELECT a.*, i.titolo, i.nome_siform, i.categoria 
    FROM attivita a 
    JOIN iniziativa i ON a.idi = i.idi 
    WHERE a.ida = $ida
");
$act = mysqli_fetch_assoc($act_q);

if (!$act) {
    die("<div style='font-family:sans-serif; padding:30px; text-align:center;'>
            <h2>❌ Sessione didattica non trovata</h2>
            <p><a href='index_info.php' style='color:#0284c7; text-decoration:none; font-weight:bold;'>⬅️ Torna all'elenco iniziative</a></p>
         </div>");
}

$idi = (int)$act['idi'];
$titolo_corso = !empty($act['titolo']) ? $act['titolo'] : $act['nome_siform'];
$max_ore = (float)$act['ore'];
$data_it = date('d/m/Y', strtotime($act['data']));
$giorni_it = ['Domenica', 'Lunedì', 'Martedì', 'Mercoledì', 'Giovedì', 'Venerdì', 'Sabato'];
$giorno_sett = $giorni_it[date('w', strtotime($act['data']))] ?? '';
$ora_in = substr($act['ora_inizio'], 0, 5);
$ora_fi = substr($act['ora_fine'], 0, 5);

// 2. Formatori assegnati alla sessione
$formatori_q = mysqli_query($conn, "
    SELECT f.cognome, f.nome, f.emailf, f.tipo, af.modalita_effettiva, af.ore_effettive
    FROM assegnazione_formatore_attivita af
    JOIN formatore f ON af.emailf = f.emailf
    WHERE af.ida = $ida
    ORDER BY f.cognome ASC, f.nome ASC
");
$formatori = [];
while ($f = mysqli_fetch_assoc($formatori_q)) {
    $formatori[] = $f;
}

if (empty($formatori)) {
    $fg_q = mysqli_query($conn, "
        SELECT f.cognome, f.nome, f.emailf, f.tipo, '' as modalita_effettiva, NULL as ore_effettive
        FROM siform_iniziativa_formatore sif
        JOIN formatore f ON sif.emailf = f.emailf
        WHERE sif.idi = $idi
        ORDER BY f.cognome ASC, f.nome ASC
    ");
    while ($fg = mysqli_fetch_assoc($fg_q)) {
        $formatori[] = $fg;
    }
}

// 3. Docenti Discenti assegnati alla sessione
$docenti_q = mysqli_query($conn, "
    SELECT d.cognome, d.nome, d.email_dd, ad.modalita_effettiva, ad.ore_effettive
    FROM assegnazione_docente_attivita ad
    JOIN docente_discente d ON ad.email_dd = d.email_dd
    WHERE ad.ida = $ida
    ORDER BY d.cognome ASC, d.nome ASC
");
$docenti = [];
while ($d = mysqli_fetch_assoc($docenti_q)) {
    $docenti[] = $d;
}

if (empty($docenti)) {
    $dg_q = mysqli_query($conn, "
        SELECT d.cognome, d.nome, d.email_dd, '' as modalita_effettiva, NULL as ore_effettive
        FROM siform_iniziativa_docentediscente sidd
        JOIN docente_discente d ON sidd.email_dd = d.email_dd
        WHERE sidd.idi = $idi
        ORDER BY d.cognome ASC, d.nome ASC
    ");
    while ($dg = mysqli_fetch_assoc($dg_q)) {
        $docenti[] = $dg;
    }
}

// 4. Classi abbinate
$classi_q = mysqli_query($conn, "
    SELECT c.nome, c.sede 
    FROM assegnazione_classe_attivita ac 
    JOIN classe c ON ac.idc = c.idc 
    WHERE ac.ida = $ida
");
$classi = [];
while ($c = mysqli_fetch_assoc($classi_q)) {
    $classi[] = $c['nome'];
}

// Helper per badge modalità effettiva
function get_badge_presenza($modalita, $ore_eff, $max_ore) {
    $mod = trim($modalita ?? '');
    if ($mod === 'Presenza') {
        $ore_str = ($ore_eff !== null && $ore_eff > 0) ? number_format((float)$ore_eff, 1) . ' h' : number_format((float)$max_ore, 1) . ' h';
        return '<span style="background:#dcfce7; color:#15803d; border:1px solid #86efac; padding:4px 8px; border-radius:4px; font-weight:700; font-size:12px;">🏫 Presenza (' . $ore_str . ')</span>';
    } elseif ($mod === 'FAD') {
        $ore_str = ($ore_eff !== null && $ore_eff > 0) ? number_format((float)$ore_eff, 1) . ' h' : number_format((float)$max_ore, 1) . ' h';
        return '<span style="background:#e0f2fe; color:#0369a1; border:1px solid #7dd3fc; padding:4px 8px; border-radius:4px; font-weight:700; font-size:12px;">🏠 FAD (' . $ore_str . ')</span>';
    } elseif ($mod === 'Assente') {
        return '<span style="background:#fee2e2; color:#b91c1c; border:1px solid #fca5a5; padding:4px 8px; border-radius:4px; font-weight:700; font-size:12px;">❌ Assente (0.0 h)</span>';
    } else {
        return '<span style="background:#f1f5f9; color:#64748b; border:1px solid #cbd5e1; padding:4px 8px; border-radius:4px; font-style:italic; font-size:12px;">⏳ Da convalidare</span>';
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Foglio Firme Lezione - <?php echo htmlspecialchars($data_it); ?> - Polo Urbani</title>
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
            gap: 10px;
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
        .info-badge-readonly {
            background: rgba(56, 189, 248, 0.15);
            color: #38bdf8;
            border: 1px solid rgba(56, 189, 248, 0.35);
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .container {
            max-width: 1200px;
            margin: 24px auto;
            padding: 0 20px 40px 20px;
        }
        .card {
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 24px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }
        .details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 14px;
            margin-top: 15px;
            background: #f8fafc;
            padding: 14px;
            border-radius: 6px;
            border: 1px solid #e2e8f0;
        }
        .detail-item strong { display: block; font-size: 11px; text-transform: uppercase; color: #64748b; margin-bottom: 2px; }
        .detail-item span { font-size: 14px; font-weight: 600; color: #0f172a; }
        
        table.firme-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            margin-top: 10px;
        }
        table.firme-table th {
            background: #f1f5f9;
            color: #475569;
            padding: 10px 14px;
            text-align: left;
            border-bottom: 1px solid #cbd5e1;
            font-size: 12px;
            text-transform: uppercase;
        }
        table.firme-table td {
            padding: 10px 14px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }
        table.firme-table tr:hover { background: #f8fafc; }

        @media print {
            .info-navbar, .nav-actions, .btn-print, .btn-back { display: none !important; }
            body { background: #fff; }
            .container { max-width: 100%; margin: 0; padding: 0; }
            .card { box-shadow: none; border: 1px solid #ccc; }
        }
    </style>
</head>
<body>
    <header class="info-navbar">
        <a href="index_info.php" class="info-brand">
            <span class="info-brand-icon">🏛️</span>
            <div>
                <div class="info-brand-title">Consultazione Iniziative &amp; Calendari Didattici</div>
                <div class="info-brand-sub">SIFORM • Polo Tecnico Professionale "Carlo Urbani"</div>
            </div>
        </a>
        <div class="nav-actions">
            <span class="info-badge-readonly">👁️ Sola Consultazione</span>
            <button type="button" class="btn-print" onclick="window.print()">🖨️ Stampa Foglio</button>
            <a href="calendario_info.php?idi=<?php echo $idi; ?>" class="btn-back">⬅️ Torna al Calendario</a>
        </div>
    </header>

    <div class="container">
        
        <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: baseline; flex-wrap: wrap; gap: 10px;">
                <div>
                    <span style="font-size: 11px; font-weight: bold; color: #0284c7; text-transform: uppercase;">Foglio Firme / Registro di Sessione</span>
                    <h2 style="margin: 4px 0 0 0; color: #0f172a;"><?php echo htmlspecialchars($titolo_corso); ?></h2>
                    <div style="font-size: 13px; color: #64748b; margin-top: 2px;">
                        Categoria SIFORM: <em><?php echo htmlspecialchars($act['categoria']); ?></em>
                    </div>
                </div>
                <div>
                    <span style="background: #e2e8f0; color: #334155; font-size: 12px; font-weight: 600; padding: 4px 10px; border-radius: 4px;">
                        ID Attività: #<?php echo $act['ida']; ?>
                    </span>
                </div>
            </div>

            <div class="details-grid">
                <div class="detail-item">
                    <strong>Giorno &amp; Data:</strong>
                    <span><?php echo $giorno_sett . " " . $data_it; ?></span>
                </div>
                <div class="detail-item">
                    <strong>Orario Previsto:</strong>
                    <span><?php echo $ora_in . " - " . $ora_fi; ?> (<?php echo number_format($max_ore, 1); ?> h)</span>
                </div>
                <div class="detail-item">
                    <strong>Tipo &amp; Modalità:</strong>
                    <span><?php echo htmlspecialchars($act['tipo']) . " • " . htmlspecialchars($act['partecipazione']); ?></span>
                </div>
                <div class="detail-item">
                    <strong>Sede Lezione:</strong>
                    <span>📍 <?php echo !empty($act['sede']) ? htmlspecialchars($act['sede']) : 'Non indicata'; ?></span>
                </div>
                <?php if (!empty($classi)): ?>
                    <div class="detail-item" style="grid-column: 1 / -1;">
                        <strong>Classi Coinvolte:</strong>
                        <span>👥 <?php echo htmlspecialchars(implode(', ', $classi)); ?></span>
                    </div>
                <?php endif; ?>
                <?php if (!empty($act['note'])): ?>
                    <div class="detail-item" style="grid-column: 1 / -1;">
                        <strong>Note di Sessione:</strong>
                        <span style="font-weight: 400; color: #475569;"><?php echo nl2br(htmlspecialchars($act['note'])); ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- TABELLA FORMATORI -->
        <div class="card">
            <h3 style="color: #0f172a; margin: 0 0 10px 0; font-size: 16px;">
                👔 Staff Formatori &amp; Esperti
            </h3>
            <?php if (empty($formatori)): ?>
                <p style="color: #94a3b8; font-style: italic; margin: 0;">Nessun formatore o esperto associato a questa lezione.</p>
            <?php else: ?>
                <table class="firme-table">
                    <thead>
                        <tr>
                            <th style="width: 35%;">Cognome e Nome</th>
                            <th style="width: 30%;">Qualifica / Ruolo</th>
                            <th style="width: 35%;">Stato Presenza / Firma</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($formatori as $f): ?>
                            <tr>
                                <td>
                                    <strong style="color: #0f172a; font-size: 14px;">
                                        👤 <?php echo htmlspecialchars($f['cognome'] . ' ' . $f['nome']); ?>
                                    </strong>
                                    <div style="font-size: 11.5px; color: #64748b;"><?php echo htmlspecialchars($f['emailf']); ?></div>
                                </td>
                                <td style="color: #475569;">
                                    <?php echo !empty($f['tipo']) ? htmlspecialchars($f['tipo']) : 'Formatore'; ?>
                                </td>
                                <td>
                                    <?php echo get_badge_presenza($f['modalita_effettiva'], $f['ore_effettive'], $max_ore); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- TABELLA DOCENTI DISCENTI -->
        <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 10px;">
                <h3 style="color: #0f172a; margin: 0; font-size: 16px;">
                    🧑‍🏫 Docenti Discenti Partecipanti
                </h3>
                <span style="font-size: 12px; color: #64748b;">
                    Totale Discenti: <strong><?php echo count($docenti); ?></strong>
                </span>
            </div>
            
            <?php if (empty($docenti)): ?>
                <p style="color: #94a3b8; font-style: italic; margin: 0;">Nessun docente discente assegnato a questa sessione didattica.</p>
            <?php else: ?>
                <table class="firme-table">
                    <thead>
                        <tr>
                            <th style="width: 8%; text-align: center;">N°</th>
                            <th style="width: 42%;">Cognome e Nome</th>
                            <th style="width: 25%;">Email di Riferimento</th>
                            <th style="width: 25%;">Stato Presenza / Firma</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $num_d = 1;
                        foreach ($docenti as $d): 
                        ?>
                            <tr>
                                <td style="text-align: center; color: #64748b; font-weight: 600;">
                                    <?php echo $num_d++; ?>
                                </td>
                                <td>
                                    <strong style="color: #0f172a; font-size: 13.5px;">
                                        🎓 <?php echo htmlspecialchars($d['cognome'] . ' ' . $d['nome']); ?>
                                    </strong>
                                </td>
                                <td style="color: #64748b; font-size: 12px;">
                                    <?php echo htmlspecialchars($d['email_dd']); ?>
                                </td>
                                <td>
                                    <?php echo get_badge_presenza($d['modalita_effettiva'], $d['ore_effettive'], $max_ore); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>