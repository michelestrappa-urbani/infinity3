<?php
// Forza la visibilità degli errori per facilitare il controllo su Altervista
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include('auth.php');
include('db.php');

$idi = isset($_GET['idi']) ? (int)$_GET['idi'] : 0;

if ($idi <= 0) { 
    die("❌ Specificare un'iniziativa valida."); 
}

// 1. RECUPERA I DATI DELL'INIZIATIVA CON IL LINK GOOGLE MEET
$corso_q = mysqli_query($conn, "SELECT titolo, nome_siform, categoria, data_comunicazione_siform, google_meet_link FROM iniziativa WHERE idi = $idi");
$corso = mysqli_fetch_assoc($corso_q);
if (!$corso) { die("❌ Iniziativa non trovata nel database."); }

$categoria_attuale = $corso['categoria'] ?? '';
$titolo_iniziativa = !empty($corso['titolo']) ? $corso['titolo'] : $corso['nome_siform'];

$is_sperimentazione_implementazione = (strpos($categoria_attuale, 'B.1.8') !== false || strpos($categoria_attuale, 'B.1.9') !== false);

// 2. ESTRAZIONE DELLE ANAGRAFICHE GLOBALI DELL'INIZIATIVA
$f_glob_q = mysqli_query($conn, "SELECT GROUP_CONCAT(CONCAT(f.cognome, ' ', f.nome) SEPARATOR ', ') as nomi FROM siform_iniziativa_formatore sif JOIN formatore f ON sif.emailf = f.emailf WHERE sif.idi = $idi");
$f_glob_row = mysqli_fetch_assoc($f_glob_q);
$formatori_globali_str = $f_glob_row['nomi'] ?: 'Da definire';

$d_glob_q = mysqli_query($conn, "SELECT GROUP_CONCAT(CONCAT(d.cognome, ' ', d.nome) SEPARATOR ', ') as nomi FROM siform_iniziativa_docentediscente sidd JOIN docente_discente d ON sidd.email_dd = d.email_dd WHERE sidd.idi = $idi");
$d_glob_row = mysqli_fetch_assoc($d_glob_q);
$docenti_globali_str = $d_glob_row['nomi'] ?: 'Nessun docente discente inserito';

$c_glob_q = mysqli_query($conn, "SELECT GROUP_CONCAT(c.nome SEPARATOR ', ') as nomi FROM siform_iniziativa_classe sic JOIN classe c ON sic.idc = c.idc WHERE sic.idi = $idi");
$c_glob_row = mysqli_fetch_assoc($c_glob_q);
$classi_globali_str = $c_glob_row['nomi'] ?: 'Nessuna classe associata';

$formatori_lettera_q = mysqli_query($conn, "SELECT f.nome, f.cognome FROM siform_iniziativa_formatore sif JOIN formatore f ON sif.emailf = f.emailf WHERE sif.idi = $idi ORDER BY f.cognome ASC");
$formatori_lista = [];
while ($f = mysqli_fetch_assoc($formatori_lettera_q)) {
    $formatori_lista[] = htmlspecialchars($f['cognome'] . ' ' . $f['nome']) . " (Formatore Esperto)";
}
$stringa_formatori = !empty($formatori_lista) ? implode(', ', $formatori_lista) : 'Da definire';

// 3. RECUPERA IL CALENDARIO TEORICO CARICATO
$attivita_q = mysqli_query($conn, "SELECT ida, data, ora_inizio, ora_fine, ore, tipo, sede, partecipazione FROM attivita WHERE idi = $idi ORDER BY data ASC, ora_inizio ASC");

// Nomi estesi dei giorni in Italiano
$giorni_it = ['Domenica', 'Lunedì', 'Martedì', 'Mercoledì', 'Giovedì', 'Venerdì', 'Sabato'];

// 4. FUNZIONE DI CONVERSIONE IMMAGINI IN BASE64 Per Word
function embeddedImage($path_immagine) {
    if (file_exists($path_immagine)) {
        $tipo_file = pathinfo($path_immagine, PATHINFO_EXTENSION);
        $dati_immagine = file_get_contents($path_immagine);
        return 'data:image/' . $tipo_file . ';base64,' . base64_encode($dati_immagine);
    }
    return '';
}

// Caricamento immagini dal server
$url_header  = embeddedImage('img/logo_intestazione.png'); 
$url_footer  = embeddedImage('img/logo_footer.png'); 
$url_firma   = embeddedImage('img/firma_dirigente.png'); 
$url_timbro  = embeddedImage('img/timbro_scuola.png'); 

// Forzatura download Microsoft Word
header("Content-Type: application/msword");
header("Content-Disposition: attachment; filename=Comunicazione_Avvio_IDI_" . $idi . ".doc");
header("Pragma: no-cache");
header("Expires: 0");
?>
<html xmlns:o='urn:schemas-microsoft-com:office:office' xmlns:w='urn:schemas-microsoft-com:office:word' xmlns='http://www.w3.org/TR/REC-html40'>
<head>
    <meta charset="UTF-8">
    <title>Comunicazione Inizio Corso</title>
    <style>
        @page Section1 {
            size: 8.27in 11.69in;
            margin: 0.5in 0.4in 0.6in 0.4in; /* Margini compressi */
            mso-header-margin: 0.3in;
            mso-footer-margin: 0.3in;
            mso-footer: f1;
        }
        div.Section1 { page: Section1; }
        
        body { font-family: 'Arial', sans-serif; font-size: 10.5pt; line-height: 1.3; color: #1e293b; }
        
        .layout-table { width: 100%; border-collapse: collapse; border: none; margin: 0; padding: 0; }
        .layout-cell { border: none; padding: 0; vertical-align: top; }
        
        p { margin-top: 0; margin-bottom: 7px; text-align: justify; font-size: 10.5pt; }
        .list-p { margin-top: 0; margin-bottom: 3px; text-align: justify; font-size: 10.5pt; margin-left: 15pt; text-indent: -15pt; }
        
        /* Tabella Calendario compattata al massimo */
        .calendar-table { width: 100%; border-collapse: collapse; margin-top: 15px; margin-bottom: 15px; font-size: 8.5pt; }
        .calendar-table th, .calendar-table td { border: 1px solid #94a3b8; padding: 3px 4px; text-align: left; vertical-align: top; }
        .calendar-table th { background-color: #f1f5f9; font-weight: bold; color: #334155; }
        
        p.MsoFooter, div.MsoFooter { margin: 0in; text-align: center; width: 100%; }
    </style>
</head>
<body>

<div class="Section1">

    <table class="layout-table" style="margin-bottom: 15px;">
        <tr>
            <td class="layout-cell">
                <?php if(!empty($url_header)): ?>
                    <img width="715" height="115" src="<?php echo $url_header; ?>" style="width:100%; max-height:115px; display:block;" alt="Header">
                <?php else: ?>
                    <div style="border: 1px dashed #ccc; padding: 15px; text-align:center; color:#666;">[Manca img/logo_intestazione.png]</div>
                <?php endif; ?>
            </td>
        </tr>
    </table>

    <table class="layout-table" style="margin-bottom: 15px;">
        <tr>
            <td class="layout-cell" style="width: 60%;">&nbsp;</td>
            <td class="layout-cell" style="width: 40%; text-align: left; font-size: 10.5pt; line-height: 1.25;">
                Alla C.A. della responsabile<br>
                <strong>Melissa Sartini</strong><br>
                Regione Marche - Settore Istruzione
            </td>
        </tr>
    </table>

    <p style="margin-bottom: 10px;"><strong>OGGETTO:</strong> Avvio attività di "<strong><?php echo htmlspecialchars($categoria_attuale); ?></strong>" Progetto: "Verso l'Infinito: la scuola che guarda allo Spazio": <strong><?php echo htmlspecialchars($titolo_iniziativa); ?></strong></p>

    <p><strong>Codice Siform:</strong> 1113767</p>
    <p><strong>ID Domanda:</strong> 85842</p>
    <p><strong>CUP:</strong> B71I25000350009</p>
    <p><strong>Riferimento:</strong> DIDATTICA INNOVATIVA Linea 1 – anno 2025 (PR Marche FSE+ 2021-2027)</p>
    <p style="margin-bottom: 12px;"><strong>Protocollo Atto Adesione:</strong> 0530340 del 30/04/2025</p>

    <p>Con il presente documento comunichiamo l'avvio dell’azione formativa prevista in oggetto.</p>
    
    <p><strong>Formatori esperti:</strong> <?php echo $stringa_formatori; ?></p>
    
    <p>Alcune attività potranno essere tenute da un solo formatore, altre da massimo due formatori in codocenza.</p>
    <p>Le attività si svolgeranno con una modalità mista “FAD/Presenza”, la sede, le modalità di svolgimento e i formatori coinvolti in ogni attività saranno indicati nel calendario.</p>
    <p>Le lezioni in presenza si svolgeranno presso la sede di MONTEGIORGIO, se prevista la FAD Il link Google Meet per il collegamento sarà reperibile nel calendario delle lezioni.</p>

    <p style="margin-top: 10px; margin-bottom: 5px;">Si allega alla presente comunicazione:</p>
    <p class="list-p">● Calendario delle attività</p>
    <?php if($is_sperimentazione_implementazione): ?>
        <p class="list-p">● Elenco dei docenti_discenti partecipanti all’attività</p>
        <p class="list-p">● Elenco degli studenti e delle classi coinvolte nelle attività</p>
    <?php endif; ?>
    <p class="list-p">● Dichiarazione dell’agibilità della sede di Montegiorgio</p>
    <p class="list-p">● Dichiarazione assicurazione INAIL</p>

    <p style="margin-top: 10px; margin-bottom: 5px;"><strong>Note per il monitoraggio:</strong></p>
    <p class="list-p">● I CV e la lettera di incarico degli esperti coinvolti saranno caricati nella sezione “Risorse Umane” della piattaforma SIFORM2 del progetto.</p>
    <p class="list-p">● I partecipanti saranno caricati nella sezione “Partecipanti” della piattaforma SIFORM2 (inserendo i dati forniti nella “Domanda iscrizione agli interventions”)</p>

    <table class="layout-table" style="margin-top: 20px;">
        <tr>
            <td class="layout-cell" style="width: 50%; text-align: left; font-size: 10.5pt;">
                Porto Sant’Elpidio, <?php echo date('d/m/Y'); ?>
                <br><br><br>
                Distinti saluti,<br>
                <strong>La Dirigente Scolastica</strong><br>
                Laura D’Ignazi
                <br>
                <?php if(!empty($url_firma)): ?>
                    <img src="<?php echo $url_firma; ?>" width="150" height="35" style="width:150px; height:35px; display:block; margin-top:15px;" alt="Firma">
                <?php else: ?>
                    <br><br><br>__________________________
                <?php endif; ?>
            </td>
            <td class="layout-cell" style="width: 50%; text-align: left; vertical-align: bottom;">
                <?php if(!empty($url_timbro)): ?>
                    <img src="<?php echo $url_timbro; ?>" width="140" height="128" style="width:140px; height:128px; display:block; margin-left: 20px;" alt="Timbro">
                <?php endif; ?>
            </td>
        </tr>
    </table>

    <br clear="all" style="page-break-before: always; vertical-align: top;">

    <h3 style="color:#0275d8; border-bottom: 2px solid #0275d8; padding-bottom: 3px; margin-top: 5px; font-family:'Arial', sans-serif; font-size:13pt;">Allegato: Calendario Pianificato delle Attività</h3>
    <p style="font-size:10pt; margin-bottom:10px;">Si riepiloga lo schema temporale compresso delle sessioni didattiche pianificate:</p>
    
    <table class="calendar-table">
        <thead>
            <tr>
                <th style="width: 14%; white-space: nowrap;">Data</th>
                <th style="width: 15%;">Orario<br style="mso-data-placement:same-cell;">(Durata)</th>
                <th style="width: 11%;">Sede</th>
                <th style="width: 42%;">Risorse e Attori Coinvolti</th>
                <th style="width: 18%;">Modalità di partecipazione</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            if (mysqli_num_rows($attivita_q) == 0): 
            ?>
                <tr><td colspan="5" style="text-align:center; font-style:italic; color:#aaa; padding: 10px;">Nessuna attività inserita a calendario per questo corso.</td></tr>
            <?php 
            else:
                while ($act = mysqli_fetch_assoc($attivita_q)): 
                    $w_day = date('w', strtotime($act['data']));
                    $giorno_sett = $giorni_it[$w_day];
                    
                    // Estrattore Formatori
                    $f_lez_q = mysqli_query($conn, "SELECT GROUP_CONCAT(CONCAT(f.cognome, ' ', f.nome) SEPARATOR ', ') as nomi FROM assegnazione_formatore_attivita af JOIN formatore f ON af.emailf = f.emailf WHERE af.ida = ".$act['ida']." AND af.modalita_effettiva != 'Assente'");
                    $f_lez_row = mysqli_fetch_assoc($f_lez_q);
                    $formatori_mostrati = $f_lez_row['nomi'] ?: $formatori_globali_str;

                    // FINEZZA: Struttura a paragrafi con margini forzati e font 8.5pt per evitare sballi in Word
                    $risorse_cella = '<p style="margin:0 0 5px 0; padding:0; font-size:8.5pt; line-height:1.25;"><strong>Formatori/Esperti:</strong> ' . htmlspecialchars($formatori_mostrati) . '</p>';
                    
                    if ($is_sperimentazione_implementazione) {
                        $d_lez_q = mysqli_query($conn, "SELECT GROUP_CONCAT(CONCAT(d.cognome, ' ', d.nome) SEPARATOR ', ') as nomi FROM assegnazione_docente_attivita ad JOIN docente_discente d ON ad.email_dd = d.email_dd WHERE ad.ida = ".$act['ida']." AND ad.modalita_effettiva != 'Assente'");
                        $d_lez_row = mysqli_fetch_assoc($d_lez_q);
                        $docenti_mostrati = $d_lez_row['nomi'] ?: $docenti_globali_str;

                        $risorse_cella .= '<p style="margin:5px 0 5px 0; padding:0; font-size:8.5pt; line-height:1.25;"><strong>Classi:</strong> ' . htmlspecialchars($classi_globali_str) . '</p>';
                        $risorse_cella .= '<p style="margin:5px 0 0 0; padding:0; font-size:8.5pt; line-height:1.25;"><strong>Docenti_discenti:</strong> ' . htmlspecialchars($docenti_mostrati) . '</p>';
                    }

                    // Logica Link Google Meet
                    $mod_part = htmlspecialchars($act['partecipazione']);
                    $is_online = (stripos($mod_part, 'FAD') !== false || stripos($mod_part, 'ONLINE') !== false || stripos($mod_part, 'Mista') !== false);
                    if ($is_online && !empty($corso['google_meet_link'])) {
                        $mod_part .= '<br style="mso-data-placement:same-cell;"><br style="mso-data-placement:same-cell;"><span style="font-size:7.5pt; font-weight:normal; text-transform:none; color:#555;">Meet Link:</span><br style="mso-data-placement:same-cell;"><a href="'.htmlspecialchars($corso['google_meet_link']).'" style="color:#0275d8; font-weight:bold; text-transform:none; text-decoration:underline; font-size:7.5pt;">'.htmlspecialchars($corso['google_meet_link']).'</a>';
                    }
            ?>
                <tr>
                    <td><strong><?php echo $giorno_sett; ?></strong><br style="mso-data-placement:same-cell;"><?php echo date('d/m/y', strtotime($act['data'])); ?></td>
                    <td><?php echo substr($act['ora_inizio'],0,5) . ' - ' . substr($act['ora_fine'],0,5); ?><br style="mso-data-placement:same-cell;">(<?php echo number_format($act['ore'], 1); ?>h)</td>
                    <td><?php echo htmlspecialchars($act['sede']); ?></td>
                    <td><?php echo $risorse_cella; ?></td>
                    <td style="font-weight: bold; font-size: 8.5pt; text-transform: uppercase; vertical-align: middle;"><?php echo $mod_part; ?></td>
                </tr>
            <?php 
                endwhile; 
            endif;
            ?>
        </tbody>
    </table>

</div> <div style="mso-element:footer" id="f1">
    <p class="MsoFooter">
        <?php if(!empty($url_footer)): ?>
            <img width="715" height="55" src="<?php echo $url_footer; ?>" style="width:100%; max-height:55px; display:block;" alt="Footer">
        <?php endif; ?>
    </p>
</div>

</body>
</html>