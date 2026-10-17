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

// =========================================================================
// GESTIONE BOX MACROCATEGORIE (B.1.1 - B.1.13) DA BUDGET_ALLOCATO.CSV
// =========================================================================

// Definizione ordinata delle 13 macrocategorie e del loro canale di rendicontazione
$macrocategorie_ordine = [
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

// Helper per estrarre il codice macrocategoria normalizzato (es. 'B.1.1', 'B.1.12')
function estrai_codice_macro($stringa) {
    $str = trim($stringa);
    if (preg_match('/^(B\.1\.\d+)/i', $str, $matches)) {
        return strtoupper($matches[1]);
    }
    return $str;
}

// Inizializza l'elenco dei contratti per ciascuna macrocategoria
$contratti_per_macro = [];
foreach ($macrocategorie_ordine as $cat_nome => $tipo_canale) {
    $contratti_per_macro[$cat_nome] = [];
}

// Lettura e parsing del file budget_allocato.csv
$csv_path = __DIR__ . '/budget_allocato.csv';
if (file_exists($csv_path)) {
    if (($handle = fopen($csv_path, "r")) !== FALSE) {
        $header = fgetcsv($handle, 1000, ",");
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            if (empty($data) || !isset($data[0])) continue;
            
            $macro_csv = trim($data[0]);
            $risorsa_csv = trim($data[1] ?? '');
            $email_csv = strtolower(trim($data[2] ?? ''));
            $orepp_csv = (float)str_replace(',', '.', trim($data[3] ?? '0'));
            $contratto_csv = trim(str_replace(['"', "'"], '', $data[4] ?? ''));
            $ritirato_csv = strtoupper(trim($data[5] ?? ''));
            $is_ritirato = ($ritirato_csv === 'SI');

            $codice_csv = estrai_codice_macro($macro_csv);
            
            // Abbina alla macrocategoria corrispondente in base al codice
            $target_cat = null;
            foreach ($macrocategorie_ordine as $cat_nome => $tipo) {
                if (estrai_codice_macro($cat_nome) === $codice_csv) {
                    $target_cat = $cat_nome;
                    break;
                }
            }

            if ($target_cat !== null) {
                $contratti_per_macro[$target_cat][] = [
                    'risorsa' => $risorsa_csv,
                    'email' => $email_csv,
                    'orepp' => $orepp_csv,
                    'contratto' => $contratto_csv,
                    'ritirato' => $is_ritirato
                ];
            }
        }
        fclose($handle);
    }
}

// =========================================================================
// QUERY AGGREGATE PER CALCOLO ORE
// =========================================================================

// 1. Mappa ore svolte a Timesheet per (email_formatore, codice_macro)
$ts_ore_map = [];
$ts_sql = "SELECT LOWER(TRIM(dt.emailf)) as emailf, i.categoria, SUM(dt.ore) as tot_ore
           FROM dettaglio_timesheet dt
           JOIN iniziativa i ON dt.idi = i.idi
           GROUP BY LOWER(TRIM(dt.emailf)), i.categoria";
$ts_res = mysqli_query($conn, $ts_sql);
if ($ts_res) {
    while ($r = mysqli_fetch_assoc($ts_res)) {
        $em = $r['emailf'];
        $cod = estrai_codice_macro($r['categoria']);
        if (!isset($ts_ore_map[$em])) $ts_ore_map[$em] = [];
        if (!isset($ts_ore_map[$em][$cod])) $ts_ore_map[$em][$cod] = 0.0;
        $ts_ore_map[$em][$cod] += (float)$r['tot_ore'];
    }
}

// 2. Mappa ore pianificate a Calendario per (email_formatore, codice_macro)
// Considera tutte le sessioni a cui il formatore è assegnato (gestione codocenze inclusa)
$cal_plan_map = [];
$cal_plan_sql = "SELECT LOWER(TRIM(af.emailf)) as emailf, i.categoria, SUM(a.ore) as tot_pianificate
                 FROM assegnazione_formatore_attivita af
                 JOIN attivita a ON af.ida = a.ida
                 JOIN iniziativa i ON a.idi = i.idi
                 GROUP BY LOWER(TRIM(af.emailf)), i.categoria";
$cal_plan_res = mysqli_query($conn, $cal_plan_sql);
if ($cal_plan_res) {
    while ($r = mysqli_fetch_assoc($cal_plan_res)) {
        $em = $r['emailf'];
        $cod = estrai_codice_macro($r['categoria']);
        if (!isset($cal_plan_map[$em])) $cal_plan_map[$em] = [];
        if (!isset($cal_plan_map[$em][$cod])) $cal_plan_map[$em][$cod] = 0.0;
        $cal_plan_map[$em][$cod] += (float)$r['tot_pianificate'];
    }
}

// 3. Mappa ore firmate a Calendario per (email_formatore, codice_macro)
// Sessioni convalidate in modalità Presenza o FAD
$cal_firm_map = [];
$cal_firm_sql = "SELECT LOWER(TRIM(af.emailf)) as emailf, i.categoria, 
                        SUM(IF(af.ore_effettive IS NOT NULL AND af.ore_effettive > 0, af.ore_effettive, a.ore)) as tot_firmate
                 FROM assegnazione_formatore_attivita af
                 JOIN attivita a ON af.ida = a.ida
                 JOIN iniziativa i ON a.idi = i.idi
                 WHERE af.modalita_effettiva IN ('Presenza', 'FAD')
                 GROUP BY LOWER(TRIM(af.emailf)), i.categoria";
$cal_firm_res = mysqli_query($conn, $cal_firm_sql);
if ($cal_firm_res) {
    while ($r = mysqli_fetch_assoc($cal_firm_res)) {
        $em = $r['emailf'];
        $cod = estrai_codice_macro($r['categoria']);
        if (!isset($cal_firm_map[$em])) $cal_firm_map[$em] = [];
        if (!isset($cal_firm_map[$em][$cod])) $cal_firm_map[$em][$cod] = 0.0;
        $cal_firm_map[$em][$cod] += (float)$r['tot_firmate'];
    }
}
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

    <!-- SEZIONE REPORT DI CONTROLLO PER MACROCATEGORIA (B.1.1 - B.1.13) -->
    <div style="margin-top: 30px;">
        <h3 style="color:#0f172a; margin-bottom: 5px;">📋 Controllo Contratti &amp; Avanzamento per Macrocategoria</h3>
        <p style="color:#64748b; font-size:13px; margin-top:0; margin-bottom:25px;">
            Monitoraggio degli esperti/formatori contrattualizzati nel bando (da B.1.1 a B.1.13), con confronto tra ore contrattuali (OREPP) e ore rendicontate (a Time Report o a Calendario).
        </p>

        <?php foreach ($macrocategorie_ordine as $cat_nome => $tipo_canale): 
            $cod_macro = estrai_codice_macro($cat_nome);
            $lista_contratti = $contratti_per_macro[$cat_nome] ?? [];
            $is_timesheet = ($tipo_canale === 'timesheet');
            $badge_colore = $is_timesheet ? '#d97706' : '#2563eb';
            $badge_bg = $is_timesheet ? '#fef3c7' : '#dbeafe';
            $canale_label = $is_timesheet ? '⏱️ Time Report (Timesheet)' : '🗓️ Calendario (Sessioni Didattiche)';
        ?>
            <div style="background: white; border: 1px solid #cbd5e1; border-radius: 8px; margin-bottom: 25px; box-shadow: 0 1px 3px rgba(0,0,0,0.06); overflow: hidden;">
                <div style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; padding: 12px 18px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                    <div>
                        <strong style="font-size: 16px; color: #0f172a;"><?php echo htmlspecialchars($cat_nome); ?></strong>
                        <span style="display: inline-block; margin-left: 10px; font-size: 11px; font-weight: bold; color: <?php echo $badge_colore; ?>; background: <?php echo $badge_bg; ?>; padding: 3px 8px; border-radius: 4px; border: 1px solid <?php echo $badge_colore; ?>40;">
                            <?php echo $canale_label; ?>
                        </span>
                        <?php if (!$is_timesheet): ?>
                            <span style="display: inline-block; margin-left: 8px; font-size: 11px; color: #64748b;">
                                (Barra: <span style="display:inline-block; width:9px; height:9px; background:#2563eb; border-radius:2px; vertical-align:middle;"></span> Firmato, <span style="display:inline-block; width:9px; height:9px; background:#93c5fd; border-radius:2px; vertical-align:middle;"></span> Pianificato)
                            </span>
                        <?php endif; ?>
                    </div>
                    <div style="font-size: 12px; color: #64748b;">
                        Contratti assegnati: <strong><?php echo count($lista_contratti); ?></strong>
                    </div>
                </div>

                <?php if (empty($lista_contratti)): ?>
                    <div style="padding: 18px; color: #94a3b8; font-style: italic; font-size: 13px;">
                        Nessun contratto di esperti/formatori censito in <code>budget_allocato.csv</code> per questa macrocategoria.
                    </div>
                <?php else: ?>
                    <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                        <thead>
                            <tr style="background: #f1f5f9; color: #475569; border-bottom: 1px solid #cbd5e1; font-size: 12px; text-transform: uppercase; letter-spacing: 0.3px;">
                                <th style="padding: 10px 14px; text-align: left; width: 32%;">Formatore / Esperto</th>
                                <?php if ($is_timesheet): ?>
                                    <th style="padding: 10px 10px; text-align: center; width: 14%; background: #f0fdf4; color: #166534;">Ore Svolte</th>
                                    <th style="padding: 10px 10px; text-align: center; width: 14%;">Ore Rimanenti</th>
                                    <th style="padding: 10px 10px; text-align: center; width: 14%;">Ore Totali (OREPP)</th>
                                    <th style="padding: 10px 14px; text-align: left; width: 26%;">Stato Avanzamento</th>
                                <?php else: ?>
                                    <th style="padding: 10px 10px; text-align: center; width: 11%; color: #0284c7;">Ore Pianificate</th>
                                    <th style="padding: 10px 10px; text-align: center; width: 11%; background: #f0fdf4; color: #166534;">Ore Firmate</th>
                                    <th style="padding: 10px 10px; text-align: center; width: 11%;">Ore Rimanenti</th>
                                    <th style="padding: 10px 10px; text-align: center; width: 11%;">Ore Totali (OREPP)</th>
                                    <th style="padding: 10px 14px; text-align: left; width: 24%;">Stato (Firmato / Pian.)</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            foreach ($lista_contratti as $c): 
                                $em = $c['email'];
                                $ore_contratto = (float)$c['orepp'];
                                $is_rit = $c['ritirato'];
                                $contratto_val = $c['contratto'];

                                if ($is_timesheet) {
                                    $ore_svolte = (float)($ts_ore_map[$em][$cod_macro] ?? 0.0);
                                    $ore_rimanenti = $ore_contratto - $ore_svolte;
                                    $perc = $ore_contratto > 0 ? round(($ore_svolte / $ore_contratto) * 100) : 0;
                                    $perc_num = max(0, min($perc, 100));
                                    $colore_barra = ($perc >= 100) ? '#16a34a' : ($perc > 0 ? '#d97706' : '#94a3b8');
                                } else {
                                    $ore_pianificate = (float)($cal_plan_map[$em][$cod_macro] ?? 0.0);
                                    $ore_firmate = (float)($cal_firm_map[$em][$cod_macro] ?? 0.0);
                                    $ore_rimanenti = $ore_contratto - $ore_firmate;
                                    $perc = $ore_contratto > 0 ? round(($ore_firmate / $ore_contratto) * 100) : 0;
                                    $perc_num = max(0, min($perc, 100));
                                    $perc_pian = $ore_contratto > 0 ? round(($ore_pianificate / $ore_contratto) * 100) : 0;
                                    $perc_pian_num = max(0, min($perc_pian, 100));
                                    $colore_barra = ($perc >= 100) ? '#16a34a' : ($perc > 0 ? '#2563eb' : '#94a3b8');
                                    $colore_barra_pian = '#93c5fd';
                                }
                            ?>
                                <tr style="border-bottom: 1px solid #f1f5f9; transition: background 0.15s;" onmouseover="this.style.background='#f8fafc';" onmouseout="this.style.background='white';">
                                    <td style="padding: 10px 14px; vertical-align: middle;">
                                        <div style="font-weight: 600; color: #0f172a; font-size: 13.5px;">
                                            👤 <?php echo htmlspecialchars($c['risorsa']); ?>
                                        </div>
                                        <div style="color: #64748b; font-size: 11.5px; margin-top: 1px;">
                                            <?php echo htmlspecialchars($c['email']); ?>
                                        </div>
                                        <?php if ($is_rit): ?>
                                            <div style="margin-top: 4px; display: inline-flex; align-items: center; gap: 6px; flex-wrap: wrap;">
                                                <span style="background: #fee2e2; color: #b91c1c; font-size: 11px; font-weight: bold; padding: 2px 6px; border-radius: 4px; border: 1px solid #fca5a5;">
                                                    ⚠️ Ritirato
                                                </span>
                                                <?php if (!empty($contratto_val)): ?>
                                                    <span style="background: #fef3c7; color: #92400e; font-size: 11px; font-weight: 600; padding: 2px 7px; border-radius: 4px; border: 1px solid #fde68a;">
                                                        Budget di progetto: € <?php echo htmlspecialchars($contratto_val); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <?php if ($is_timesheet): ?>
                                        <!-- ORE SVOLTE TIMESHEET -->
                                        <td style="padding: 10px; text-align: center; vertical-align: middle; background: #f0fdf4;">
                                            <strong style="color: #166534; font-size: 14px;">
                                                <?php echo number_format($ore_svolte, 1); ?> h
                                            </strong>
                                        </td>
                                        <!-- ORE RIMANENTI -->
                                        <td style="padding: 10px; text-align: center; vertical-align: middle; color: <?php echo ($ore_rimanenti <= 0) ? '#16a34a' : '#475569'; ?>; font-weight: 500;">
                                            <?php echo number_format($ore_rimanenti, 1); ?> h
                                        </td>
                                        <!-- ORE TOTALI (OREPP) -->
                                        <td style="padding: 10px; text-align: center; vertical-align: middle; color: #0f172a; font-weight: 600;">
                                            <?php echo number_format($ore_contratto, 1); ?> h
                                        </td>
                                        <!-- STATO AVANZAMENTO -->
                                        <td style="padding: 10px 14px; vertical-align: middle;">
                                            <div style="display: flex; align-items: center; gap: 8px;">
                                                <div style="flex: 1; background: #e2e8f0; height: 14px; border-radius: 7px; overflow: hidden;">
                                                    <div style="background: <?php echo $colore_barra; ?>; width: <?php echo $perc_num; ?>%; height: 100%; transition: width 0.3s;"></div>
                                                </div>
                                                <span style="font-size: 12px; font-weight: bold; color: <?php echo $colore_barra; ?>; min-width: 42px; text-align: right;">
                                                    <?php echo $perc; ?>%
                                                </span>
                                            </div>
                                        </td>
                                    <?php else: ?>
                                        <!-- ORE PIANIFICATE CALENDARIO -->
                                        <td style="padding: 10px; text-align: center; vertical-align: middle; color: #0284c7; font-weight: 600;">
                                            <?php echo number_format($ore_pianificate, 1); ?> h
                                        </td>
                                        <!-- ORE FIRMATE CALENDARIO -->
                                        <td style="padding: 10px; text-align: center; vertical-align: middle; background: #f0fdf4;">
                                            <strong style="color: #166534; font-size: 14px;">
                                                <?php echo number_format($ore_firmate, 1); ?> h
                                            </strong>
                                        </td>
                                        <!-- ORE RIMANENTI -->
                                        <td style="padding: 10px; text-align: center; vertical-align: middle; color: <?php echo ($ore_rimanenti <= 0) ? '#16a34a' : '#475569'; ?>; font-weight: 500;">
                                            <?php echo number_format($ore_rimanenti, 1); ?> h
                                        </td>
                                        <!-- ORE TOTALI (OREPP) -->
                                        <td style="padding: 10px; text-align: center; vertical-align: middle; color: #0f172a; font-weight: 600;">
                                            <?php echo number_format($ore_contratto, 1); ?> h
                                        </td>
                                        <!-- STATO AVANZAMENTO (DOPPIA BARRA: BUFFER PIANIFICATO + FIRMATO) -->
                                        <td style="padding: 10px 14px; vertical-align: middle;">
                                            <div style="display: flex; align-items: center; gap: 8px;">
                                                <div style="flex: 1; background: #e2e8f0; height: 14px; border-radius: 7px; overflow: hidden; position: relative;" title="Firmate: <?php echo number_format($ore_firmate, 1); ?> h (<?php echo $perc; ?>%) | Pianificate: <?php echo number_format($ore_pianificate, 1); ?> h (<?php echo $perc_pian; ?>%) su <?php echo number_format($ore_contratto, 1); ?> h">
                                                    <!-- Barra Ore Pianificate (Buffer blu tenue) -->
                                                    <div style="position: absolute; left: 0; top: 0; background: <?php echo $colore_barra_pian; ?>; width: <?php echo $perc_pian_num; ?>%; height: 100%; transition: width 0.3s;"></div>
                                                    <!-- Barra Ore Firmate (Blu vivo / Verde) -->
                                                    <div style="position: absolute; left: 0; top: 0; background: <?php echo $colore_barra; ?>; width: <?php echo $perc_num; ?>%; height: 100%; transition: width 0.3s;"></div>
                                                </div>
                                                <div style="min-width: 78px; text-align: right; white-space: nowrap; font-size: 11px;">
                                                    <strong style="color: <?php echo $colore_barra; ?>; font-size: 12px;"><?php echo $perc; ?>%</strong>
                                                    <span style="color: #64748b; font-size: 10.5px; margin-left: 3px;" title="Pianificato: <?php echo $perc_pian; ?>%">(<?php echo $perc_pian; ?>%p)</span>
                                                </div>
                                            </div>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>