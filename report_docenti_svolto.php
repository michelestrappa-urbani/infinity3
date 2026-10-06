<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// include('auth.php'); // DISATTIVATO TEMPORANEAMENTE PER ACCESSO LIBERO
include('db.php');
include('navbar.php');
?>

<div style="font-family: sans-serif; padding: 10px;">
    <h2>🎓 Report Consolidato: Stato Frequenza Docenti Discenti</h2>
    <p style="color:#475569; margin-top:0;">Elenco dei docenti discenti che hanno completato sessioni certificate nelle linee di attività FSE+ di Sperimentazione e Implementazione.</p>

    <table style="width:100%; border-collapse:collapse; background:white; box-shadow: 0 1px 3px rgba(0,0,0,0.1); font-size:13px;">
        <thead>
            <tr style="background:#1e293b; color:white; border-bottom:2px solid #cbd5e1;">
                <th rowspan="2" style="padding:10px; text-align:left; border:1px solid #475569;">Cognome e Nome</th>
                <th rowspan="2" style="padding:10px; text-align:left; border:1px solid #475569;">Email</th>
                <th colspan="2" style="background:#0284c7; text-align:center; border:1px solid #475569;">🧪 B.1.8 SPERIMENTAZIONE</th>
                <th colspan="2" style="background:#b45309; text-align:center; border:1px solid #475569;">🚀 B.1.9 IMPLEMENTAZIONE</th>
                <th rowspan="2" style="padding:10px; text-align:center; border:1px solid #475569; background:#0f172a;">Totale Ore</th>
            </tr>
            <tr style="background:#f8fafc; color:#1e293b;">
                <th style="padding:6px; text-align:center; border:1px solid #cbd5e1;">Sessioni</th>
                <th style="padding:6px; text-align:right; border:1px solid #cbd5e1; font-weight:bold;">Ore Svolte</th>
                <th style="padding:6px; text-align:center; border:1px solid #cbd5e1;">Sessioni</th>
                <th style="padding:6px; text-align:right; border:1px solid #cbd5e1; font-weight:bold;">Ore Svolte</th>
            </tr>
        </thead>
        <tbody>
            <?php
            // Query combinata per estrarre e totalizzare i dati in un'unica passata sul DB
            $query_discenti = "
                SELECT 
                    d.cognome, 
                    d.nome, 
                    d.email_dd,
                    SUM(CASE WHEN i.categoria = 'B.1.8 Sperimentazione' THEN 1 ELSE 0 END) as sess_sper,
                    SUM(CASE WHEN i.categoria = 'B.1.8 Sperimentazione' THEN COALESCE(da.ore_effettive, a.ore) ELSE 0 END) as ore_sper,
                    SUM(CASE WHEN i.categoria = 'B.1.9 Accompagnamento e implementazione' THEN 1 ELSE 0 END) as sess_impl,
                    SUM(CASE WHEN i.categoria = 'B.1.9 Accompagnamento e implementazione' THEN COALESCE(da.ore_effettive, a.ore) ELSE 0 END) as ore_impl
                FROM assegnazione_docente_attivita da
                JOIN attivita a ON da.ida = a.ida
                JOIN iniziativa i ON a.idi = i.idi
                JOIN docente_discente d ON da.email_dd = d.email_dd
                WHERE da.modalita_effettiva IN ('Presenza', 'FAD')
                  AND i.categoria IN ('B.1.8 Sperimentazione', 'B.1.9 Accompagnamento e implementazione')
                GROUP BY d.email_dd
                ORDER BY d.cognome ASC, d.nome ASC
            ";

            $res = mysqli_query($conn, $query_discenti);

            if (mysqli_num_rows($res) == 0) {
                echo '<tr><td colspan="7" style="text-align:center; font-style:italic; color:#94a3b8; padding:20px;">Nessun dato di frequenza registrato per le categorie selezionate.</td></tr>';
            } else {
                while ($row = mysqli_fetch_assoc($res)) {
                    $tot_ore_docente = $row['ore_sper'] + $row['ore_impl'];
            ?>
                    <tr style="border-bottom:1px solid #e2e8f0; hover:background:#f8fafc;">
                        <td style="padding:10px; font-weight:600; color:#334155; border:1px solid #e2e8f0;">
                            <?php echo htmlspecialchars($row['cognome'] . " " . $row['nome']); ?>
                        </td>
                        <td style="padding:10px; color:#64748b; border:1px solid #e2e8f0;">
                            <?php echo htmlspecialchars($row['email_dd']); ?>
                        </td>
                        <td style="padding:10px; text-align:center; border:1px solid #e2e8f0; color:#0284c7; font-weight:bold;">
                            <?php echo $row['sess_sper']; ?>
                        </td>
                        <td style="padding:10px; text-align:right; border:1px solid #e2e8f0; font-weight:600;">
                            <?php echo number_format($row['ore_sper'], 2); ?> h
                        </td>
                        <td style="padding:10px; text-align:center; border:1px solid #e2e8f0; color:#b45309; font-weight:bold;">
                            <?php echo $row['sess_impl']; ?>
                        </td>
                        <td style="padding:10px; text-align:right; border:1px solid #e2e8f0; font-weight:600;">
                            <?php echo number_format($row['ore_impl'], 2); ?> h
                        </td>
                        <td style="padding:10px; text-align:center; border:1px solid #cbd5e1; background:#f8fafc; font-weight:bold; color:#0f172a; font-size:14px;">
                            <?php echo number_format($tot_ore_docente, 2); ?> h
                        </td>
                    </tr>
            <?php
                }
            }
            ?>
        </tbody>
    </table>
</div>