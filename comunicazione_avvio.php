<?php
// Forza la visibilità degli errori per facilitare il controllo su Altervista
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// include('auth.php'); // DISATTIVATO TEMPORANEAMENTE PER ACCESSO LIBERO
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

// Logiche di controllo categorie
$has_docenti = (strpos($categoria_attuale, 'B.1.7') !== false || strpos($categoria_attuale, 'B.1.8') !== false || strpos($categoria_attuale, 'B.1.9') !== false);
$has_classi = (strpos($categoria_attuale, 'B.1.8') !== false || strpos($categoria_attuale, 'B.1.9') !== false);
$is_attivita_destinatari = (strpos($categoria_attuale, 'B.1.13') !== false);

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

$formatori_lettera_q = mysqli_query($conn, "SELECT f.nome, f.cognome, f.tipo FROM siform_iniziativa_formatore sif JOIN formatore f ON sif.emailf = f.emailf WHERE sif.idi = $idi ORDER BY f.cognome ASC");
$formatori_lista = [];
while ($f = mysqli_fetch_assoc($formatori_lettera_q)) {
    $qualifica = !empty($f['tipo']) ? $f['tipo'] : 'Esperto';
    $formatori_lista[] = htmlspecialchars($f['cognome'] . ' ' . $f['nome'] . ' (' . $qualifica . ')');
}
$stringa_formatori = !empty($formatori_lista) ? implode(', ', $formatori_lista) : 'Da definire';

// Query analitiche per gli allegati in coda
if ($has_docenti) {
    $lista_docenti_appendice = mysqli_query($conn, "SELECT d.cognome, d.nome, d.email_dd FROM siform_iniziativa_docentediscente sidd JOIN docente_discente d ON sidd.email_dd = d.email_dd WHERE sidd.idi = $idi ORDER BY d.cognome ASC");
}
if ($has_classi) {
    $lista_studenti_appendice = mysqli_query($conn, "
        SELECT c.idc, c.nome as nome_classe, c.sede as sede_classe, s.cognome, s.nome 
        FROM siform_iniziativa_classe sic 
        JOIN classe c ON sic.idc = c.idc 
        LEFT JOIN studente s ON c.idc = s.idc 
        WHERE sic.idi = $idi 
        ORDER BY c.nome ASC, s.cognome ASC, s.nome ASC
    ");
}

// 3. RECUPERA IL CALENDARIO TEORICO CARICATO
$attivita_q = mysqli_query($conn, "SELECT ida, data, ora_inizio, ora_fine, ore, tipo, sede, partecipazione, google_meet_link FROM attivita WHERE idi = $idi ORDER BY data ASC, ora_inizio ASC");
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
            margin: 0.5in 0.4in 0.6in 0.4in;
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
        
        .calendar-table { width: 100%; border-collapse: collapse; margin-top: 10px; margin-bottom: 15px; font-size: 8.5pt; }
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
    <p>Le attività potrebbero svolgersi in modalita mista “FAD/Presenza”, la sede, le modalità di svolgimento e gli attori coinvolti in ogni attività saranno indicati nel calendario.</p>
    <p>Le lezioni in presenza si svolgeranno presso la sede di MONTEGIORGIO, se prevista la FAD Il link Google Meet per il collegamento sarà reperibile nel calendario delle lezioni.</p>

    <p style="margin-top: 10px; margin-bottom: 5px;">Si allega alla presente comunicazione:</p>
    <p class="list-p">● Calendario delle attività</p>
    <?php if($has_docenti): ?>
        <p class="list-p">● Elenco dei docenti_discenti partecipanti all’attività</p>
    <?php endif; ?>
    <?php if($has_classi): ?>
        <p class="list-p">● Elenco degli studenti e delle classi coinvolte nelle attività</p>
    <?php endif; ?>
    <p class="list-p">● Dichiarazione dell’agibilità della sede di Montegiorgio</p>
    <p class="list-p">● Dichiarazione assicurazione INAIL</p>

    <p style="margin-top: 10px; margin-bottom: 5px;"><strong>Note per il monitoraggio:</strong></p>
    <?php if($is_attivita_destinatari): ?>
        <p class="list-p">● Le firme dei destinatari che parteciperanno alle attività saranno registrate su un registro dedicato (modello 8 del progetto)</p>
        <p class="list-p">● I partecipanti saranno caricati nella sezione “Partecipanti” della piattaforma SIFORM2 (inserendo i dati forniti nella “Domanda iscrizione agli interventi”)</p>
    <?php else: ?>
        <p class="list-p">● I CV e la lettera di incarico degli esperti coinvolti saranno caricati nella sezione “Risorse Umane” della piattaforma SIFORM2 del progetto.</p>
        <p class="list-p">● I partecipanti saranno caricati nella sezione “Partecipanti” della piattaforma SIFORM2 (inserendo i dati forniti nella “Domanda iscrizione agli interventi”)</p>
    <?php endif; ?>

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
                    <img src="<?php echo $url_firma; ?>" width="165" height="38" style="width:165px; height:38px; display:block; margin-top:15px;" alt="Firma">
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

    <h3 style="color:#000000; font-weight:bold; border:none; margin-top: 5px; margin-bottom: 8px; font-family:'Arial', sans-serif; font-size:13pt;">Allegato: Calendario Pianificato delle Attività</h3>
    <p style="font-size:10pt; margin-bottom:10px;">Si riepiloga lo schema temporale delle sessioni didattiche pianificate:</p>
    
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
            <?php if (mysqli_num_rows($attivita_q) == 0): ?>
                <tr><td colspan="5" style="text-align:center; font-style:italic; color:#aaa; padding: 10px;">Nessuna attività inserita a calendario.</td></tr>
            <?php else:
                while ($act = mysqli_fetch_assoc($attivita_q)): 
                    $w_day = date('w', strtotime($act['data']));
                    $giorno_sett = $giorni_it[$w_day];
                    
                    // 1. Query Formatori puntuale per lezione
                    $f_lez_q = mysqli_query($conn, "SELECT GROUP_CONCAT(CONCAT(f.cognome, ' ', f.nome) SEPARATOR ', ') as nomi FROM assegnazione_formatore_attivita af JOIN formatore f ON af.emailf = f.emailf WHERE af.ida = ".$act['ida']." AND af.modalita_effettiva != 'Assente'");
                    $f_lez_row = mysqli_fetch_assoc($f_lez_q);
                    $formatori_mostrati = $f_lez_row['nomi'] ?: $formatori_globali_str;

                    $risorse_cella = '<p style="margin:0 0 5px 0; padding:0; font-size:8.5pt; line-height:1.25;"><strong>Formatori/Esperti:</strong> ' . htmlspecialchars($formatori_mostrati) . '</p>';
                    
                    if ($has_docenti) {
                        // 2. CORREZIONE STRUTTURALMENTE FILTRATA: Estrae solo le classi associate a questa singola attività/sessione
                        if ($has_classi) {
                            $c_lez_q = mysqli_query($conn, "SELECT GROUP_CONCAT(c.nome SEPARATOR ', ') as nomi FROM assegnazione_classe_attivita ac JOIN classe c ON ac.idc = c.idc WHERE ac.ida = ".$act['ida']);
                            $c_lez_row = mysqli_fetch_assoc($c_lez_q);
                            $classi_mostrate = $c_lez_row['nomi'] ?: $classi_globali_str;
                            
                            $risorse_cella .= '<p style="margin:5px 0 5px 0; padding:0; font-size:8.5pt; line-height:1.25;"><strong>Classi:</strong> ' . htmlspecialchars($classi_mostrate) . '</p>';
                        }

                        // 3. Query Docenti puntuale per lezione
                        $d_lez_q = mysqli_query($conn, "SELECT GROUP_CONCAT(CONCAT(d.cognome, ' ', d.nome) SEPARATOR ', ') as nomi FROM assegnazione_docente_attivita ad JOIN docente_discente d ON ad.email_dd = d.email_dd WHERE ad.ida = ".$act['ida']." AND ad.modalita_effettiva != 'Assente'");
                        $d_lez_row = mysqli_fetch_assoc($d_lez_q);
                        $docenti_mostrati = $d_lez_row['nomi'] ?: $docenti_globali_str;

                        $risorse_cella .= '<p style="margin:5px 0 0 0; padding:0; font-size:8.5pt; line-height:1.25;"><strong>Docenti_discenti:</strong> ' . htmlspecialchars($docenti_mostrati) . '</p>';
                    }

                    $mod_part = htmlspecialchars($act['partecipazione']);
                    $is_online = (stripos($mod_part, 'FAD') !== false || stripos($mod_part, 'ONLINE') !== false || stripos($mod_part, 'Mista') !== false);
                    $meet_link_effettivo = !empty(trim($act['google_meet_link'] ?? '')) ? trim($act['google_meet_link']) : (!empty(trim($corso['google_meet_link'] ?? '')) ? trim($corso['google_meet_link']) : '');
                    if ($is_online && !empty($meet_link_effettivo)) {
                        $mod_part .= '<br style="mso-data-placement:same-cell;"><br style="mso-data-placement:same-cell;"><span style="font-size:7.5pt; font-weight:normal; text-transform:none; color:#555;">Meet Link:</span><br style="mso-data-placement:same-cell;"><a href="'.htmlspecialchars($meet_link_effettivo).'" style="color:#0275d8; font-weight:bold; text-transform:none; text-decoration:underline; font-size:7.5pt;">'.htmlspecialchars($meet_link_effettivo).'</a>';
                    }
            ?>
                <tr>
                    <td><strong><?php echo $giorno_sett; ?></strong><br style="mso-data-placement:same-cell;"><?php echo date('d/m/y', strtotime($act['data'])); ?></td>
                    <td><?php echo substr($act['ora_inizio'],0,5) . ' - ' . substr($act['ora_fine'],0,5); ?><br style="mso-data-placement:same-cell;">(<?php echo number_format($act['ore'], 1); ?>h)</td>
                    <td><?php echo htmlspecialchars($act['sede']); ?></td>
                    <td><?php echo $risorse_cella; ?></td>
                    <td style="font-weight: bold; font-size: 8.5pt; text-transform: uppercase; vertical-align: middle;"><?php echo $mod_part; ?></td>
                </tr>
            <?php endwhile; endif; ?>
        </tbody>
    </table>

    <?php if ($has_docenti): ?>
        <br clear="all" style="page-break-before: always; vertical-align: top;">

        <h3 style="color:#000000; font-weight:bold; border:none; margin-top: 5px; margin-bottom: 8px; font-family:'Arial', sans-serif; font-size:13pt;">Allegato: Elenco Docenti_discenti Partecipanti</h3>
        <p style="font-size:10pt; margin-bottom:10px;">Si riporta l'elenco nominativo completo dei docenti beneficiari inseriti nell'intervento formativo:</p>
        
        <table class="calendar-table" style="width: 100%;">
            <thead>
                <tr>
                    <th style="width: 10%; text-align:center;">N°</th>
                    <th style="width: 45%;">Cognome e Nome</th>
                    <th style="width: 45%;">Indirizzo E-mail di Riferimento</th>
                </tr>
            </thead>
            <tbody>
                <?php if (mysqli_num_rows($lista_docenti_appendice) == 0): ?>
                    <tr><td colspan="3" style="text-align:center; font-style:italic; color:#aaa; padding: 10px;">Nessun docente discente associato.</td></tr>
                <?php else:
                    $count_d = 1;
                    while ($doc_app = mysqli_fetch_assoc($lista_docenti_appendice)): ?>
                    <tr>
                        <td style="text-align:center; font-weight:bold;"><?php echo $count_d; ?></td>
                        <td><strong><?php echo htmlspecialchars($doc_app['cognome'] . ' ' . $doc_app['nome']); ?></strong></td>
                        <td><?php echo htmlspecialchars($doc_app['email_dd']); ?></td>
                    </tr>
                <?php $count_d++; endwhile; endif; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <?php if ($has_classi): ?>
        <br clear="all" style="page-break-before: always; vertical-align: top;">

        <h3 style="color:#000000; font-weight:bold; border:none; margin-top: 5px; margin-bottom: 8px; font-family:'Arial', sans-serif; font-size:13pt;">Allegato: Elenco degli Studenti suddivisi per Classe</h3>
        <p style="font-size:10pt; margin-bottom:10px;">Si riporta il roster degli studenti registrati a sistema, raggruppati per le classi coinvolte nel progetto:</p>
        
        <?php 
        $current_idc = null;
        $count_s = 1;
        
        if (mysqli_num_rows($lista_studenti_appendice) == 0): 
        ?>
            <p style="text-align:center; font-style:italic; color:#aaa; padding: 15px; font-size:10pt;">Nessun alunno caricato nel database per le classi associate. Usa il pannello anagrafiche per caricarli.</p>
        <?php 
        else:
            while ($std_app = mysqli_fetch_assoc($lista_studenti_appendice)): 
                if ($current_idc !== $std_app['idc']) {
                    if ($current_idc !== null) {
                        echo '</tbody></table><br>'; 
                    }
                    $current_idc = $std_app['idc'];
                    $count_s = 1;
                    ?>
                    <h4 style="margin: 15px 0 6px 0; color:#334155; font-size:11pt; font-family:'Arial', sans-serif;">Classe: <strong><?php echo htmlspecialchars($std_app['nome_classe']); ?></strong> — Sede: <em><?php echo htmlspecialchars($std_app['sede_classe']); ?></em></h4>
                    <table class="calendar-table" style="width: 100%; margin-top:0;">
                        <thead>
                            <tr>
                                <th style="width: 12%; text-align:center;">N°</th>
                                <th style="width: 44%;">Cognome</th>
                                <th style="width: 44%;">Nome</th>
                            </tr>
                        </thead>
                        <tbody>
                    <?php
                }
                
                if ($std_app['cognome'] !== null) {
                ?>
                    <tr>
                        <td style="text-align:center; font-weight:bold; color:#555;"><?php echo $count_s; ?></td>
                        <td><strong><?php echo htmlspecialchars($std_app['cognome']); ?></strong></td>
                        <td><?php echo htmlspecialchars($std_app['nome']); ?></td>
                    </tr>
                <?php 
                    $count_s++;
                } else {
                    ?>
                    <tr>
                        <td colspan="3" style="text-align:center; font-style:italic; color:#94a3b8; padding: 8px;">Nessun alunno inserito in questa classe. Esegui l'importazione massiva dal pannello "Classi & Alunni".</td>
                    </tr>
                <?php
                }
            endwhile; 
            echo '</tbody></table>'; 
        endif; 
        ?>
    <?php endif; ?>

</div> <div style="mso-element:footer" id="f1">
    <p class="MsoFooter">
        <?php if(!empty($url_footer)): ?>
            <img width="715" height="55" src="<?php echo $url_footer; ?>" style="width:100%; max-height:55px; display:block;" alt="Footer">
        <?php endif; ?>
    </p>
</div>

</body>
</html>