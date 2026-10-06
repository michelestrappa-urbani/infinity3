<?php
// Forza la visibilità degli errori per facilitare il controllo su Altervista
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// include('auth.php'); // DISATTIVATO TEMPORANEAMENTE PER ACCESSO LIBERO
include('db.php');
include('navbar.php');

$tab = isset($_GET['tab']) ? $_GET['tab'] : 'formatori';
$action = isset($_GET['action']) ? $_GET['action'] : '';
$id = isset($_GET['id']) ? mysqli_real_escape_string($conn, $_GET['id']) : '';

$success = "";

// ==========================================
// ELABORAZIONE DATI (SALVATAGGI E MODIFICHE)
// ==========================================

// 1. SALVATAGGIO FORMATORI
if (isset($_POST['save_formatore'])) {
    $email = mysqli_real_escape_string($conn, $_POST['emailf']);
    $nome = mysqli_real_escape_string($conn, $_POST['nome']);
    $cognome = mysqli_real_escape_string($conn, $_POST['cognome']);
    $tipo = mysqli_real_escape_string($conn, $_POST['tipo']);
    $note = mysqli_real_escape_string($conn, $_POST['note']);

    if ($_POST['is_edit'] == '1') {
        mysqli_query($conn, "UPDATE formatore SET nome='$nome', cognome='$cognome', tipo='$tipo', note='$note' WHERE emailf='$email'");
    } else {
        mysqli_query($conn, "INSERT INTO formatore VALUES ('$email', '$nome', '$cognome', '$tipo', '$note')");
    }
    header("Location: anagrafiche.php?tab=formatori&status=ok"); exit();
}

// 2. SALVATAGGIO DOCENTE DISCENTE SINGOLO
if (isset($_POST['save_docente'])) {
    $email = mysqli_real_escape_string($conn, $_POST['email_dd']);
    $nome = mysqli_real_escape_string($conn, $_POST['nome']);
    $cognome = mysqli_real_escape_string($conn, $_POST['cognome']);

    if ($_POST['is_edit'] == '1') {
        mysqli_query($conn, "UPDATE docente_discente SET nome='$nome', cognome='$cognome' WHERE email_dd='$email'");
    } else {
        mysqli_query($conn, "INSERT INTO docente_discente VALUES ('$email', '$nome', '$cognome')");
    }
    header("Location: anagrafiche.php?tab=docenti&status=ok"); exit();
}

// 3. IMPORTAZIONE MASSIVA DOCENTI DISCENTI VIA CSV
if (isset($_POST['import_docenti_csv'])) {
    $csv_data = $_POST['csv_text'];
    $lines = explode("\n", $csv_data);
    $inserted = 0;
    
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) continue;
        
        $data = str_getcsv($line, ",");
        if (count($data) < 3) { $data = str_getcsv($line, ";"); }
        
        if (count($data) >= 3) {
            $email = mysqli_real_escape_string($conn, trim($data[0]));
            $nome = mysqli_real_escape_string($conn, trim($data[1]));
            $cognome = mysqli_real_escape_string($conn, trim($data[2]));
            
            if (!empty($email) && !empty($nome) && !empty($cognome)) {
                $query = "INSERT INTO docente_discente (email_dd, nome, cognome) VALUES ('$email', '$nome', '$cognome') 
                          ON DUPLICATE KEY UPDATE nome='$nome', cognome='$cognome'";
                if (mysqli_query($conn, $query)) { $inserted++; }
            }
        }
    }
    header("Location: anagrafiche.php?tab=docenti&status=bulk_ok&count=" . $inserted); exit();
}

// 4. SALVATAGGIO CLASSE
if (isset($_POST['save_classe'])) {
    $nome = mysqli_real_escape_string($conn, $_POST['nome']);
    $sede = mysqli_real_escape_string($conn, $_POST['sede']);
    $tot = (int)$_POST['numero_studenti_totali'];

    if ($_POST['is_edit'] == '1') {
        $idc = (int)$_POST['idc'];
        mysqli_query($conn, "UPDATE classe SET nome='$nome', sede='$sede', numero_studenti_totali=$tot WHERE idc=$idc");
    } else {
        mysqli_query($conn, "INSERT INTO classe (nome, sede, numero_studenti_totali) VALUES ('$nome', '$sede', $tot)");
    }
    header("Location: anagrafiche.php?tab=classi&status=ok"); exit();
}

// 5. SALVATAGGIO SINGOLO STUDENTE IN CLASSE
if (isset($_POST['save_studente'])) {
    $idc = (int)$_POST['idc'];
    $cognome = mysqli_real_escape_string($conn, trim($_POST['cognome']));
    $nome = mysqli_real_escape_string($conn, trim($_POST['nome']));
    
    if ($_POST['is_edit'] == '1') {
        $ids = (int)$_POST['ids'];
        mysqli_query($conn, "UPDATE studente SET cognome='$cognome', nome='$nome' WHERE ids=$ids");
    } else {
        mysqli_query($conn, "INSERT INTO studente (idc, cognome, nome) VALUES ($idc, '$cognome', '$nome')");
    }
    header("Location: anagrafiche.php?tab=classi&action=studenti&id=$idc&status=ok"); exit();
}

// 6. IMPORTAZIONE MASSIVA ALUNNI DI UNA CLASSE VIA COPIA/INCOLLA EXCEL CSV
if (isset($_POST['import_studenti_csv'])) {
    $idc = (int)$_POST['idc'];
    $csv_data = $_POST['csv_text'];
    $lines = explode("\n", $csv_data);
    $inserted = 0;
    
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) continue;
        
        $data = str_getcsv($line, ",");
        if (count($data) < 2) { $data = str_getcsv($line, ";"); }
        
        if (count($data) >= 2) {
            $cognome = mysqli_real_escape_string($conn, trim($data[0]));
            $nome = mysqli_real_escape_string($conn, trim($data[1]));
            
            if (!empty($cognome) && !empty($nome)) {
                mysqli_query($conn, "INSERT INTO studente (idc, cognome, nome) VALUES ($idc, '$cognome', '$nome')");
                $inserted++;
            }
        }
    }
    header("Location: anagrafiche.php?tab=classi&action=studenti&id=$idc&status=bulk_ok&count=" . $inserted); exit();
}

// CANCELLAZIONI STANDARD
if ($action === 'delete') {
    if ($tab === 'formatori') mysqli_query($conn, "DELETE FROM formatore WHERE emailf='$id'");
    if ($tab === 'docenti') mysqli_query($conn, "DELETE FROM docente_discente WHERE email_dd='$id'");
    if ($tab === 'classi') mysqli_query($conn, "DELETE FROM classe WHERE idc=".(int)$id);
    header("Location: anagrafiche.php?tab=$tab&status=ok"); exit();
}

// CANCELLAZIONE SINGOLO STUDENTE
if ($action === 'delete_studente' && isset($_GET['ids'])) {
    $ids = (int)$_GET['ids'];
    $idc = (int)$_GET['idc'];
    mysqli_query($conn, "DELETE FROM studente WHERE ids=$ids");
    header("Location: anagrafiche.php?tab=classi&action=studenti&id=$idc&status=ok"); exit();
}

if (isset($_GET['status']) && $_GET['status'] == 'ok') $success = "✅ Operazione completata con successo!";
if (isset($_GET['status']) && $_GET['status'] == 'bulk_ok') {
    $count = (int)$_GET['count'];
    $success = "📥 Importazione massiva completata! Registrati o aggiornati con successo <strong>$count</strong> record nel database.";
}
?>

<h2>👥 Gestione Anagrafiche e Risorse Polo Urbani</h2>
<?php if($success != ""): ?> <div style="background:#dff0d8; color:#3c763d; padding:12px; margin-bottom:20px; border:1px solid #d6e9c6; font-family:sans-serif;"><?php echo $success; ?></div> <?php endif; ?>

<div style="display:flex; margin-bottom: 20px; border-bottom: 1px solid #ddd;">
    <a href="anagrafiche.php?tab=formatori" class="tab-link <?php if($tab == 'formatori') echo 'tab-active'; ?>">👔 Formatori</a>
    <a href="anagrafiche.php?tab=docenti" class="tab-link <?php if($tab == 'docenti') echo 'tab-active'; ?>">🧑‍🏫 Docenti Discenti</a>
    <a href="anagrafiche.php?tab=classi" class="tab-link <?php if($tab == 'classi') echo 'tab-active'; ?>">🏫 Classi & Alunni</a>
</div>

<div style="display:flex; gap:30px; font-family:sans-serif;">

    <?php if($tab === 'formatori'): 
        $edit_f = ['emailf'=>'', 'nome'=>'', 'cognome'=>'', 'tipo'=>'', 'note'=>'', 'is_edit'=>'0'];
        if($action === 'edit' && $id != '') {
            $edit_f = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM formatore WHERE emailf='$id'"));
            $edit_f['is_edit'] = '1';
        }
    ?>
        <div style="width:30%; background:white; padding:15px; border:1px solid #ddd; height:fit-content;">
            <h3><?php echo ($edit_f['is_edit']=='1') ? "✏️ Modifica" : "➕ Aggiungi"; ?> Formatore</h3>
            <form method="POST" action="">
                <input type="hidden" name="is_edit" value="<?php echo $edit_f['is_edit']; ?>">
                <label>Email (Chiave):</label><br><input type="email" name="emailf" value="<?php echo $edit_f['emailf']; ?>" <?php if($edit_f['is_edit']=='1') echo 'readonly style="background:#eee;"'; ?> required style="width:100%; padding:6px; margin-bottom:10px;"><br>
                <label>Nome:</label><br><input type="text" name="nome" value="<?php echo htmlspecialchars($edit_f['nome']); ?>" required style="width:100%; padding:6px; margin-bottom:10px;"><br>
                <label>Cognome:</label><br><input type="text" name="cognome" value="<?php echo htmlspecialchars($edit_f['cognome']); ?>" required style="width:100%; padding:6px; margin-bottom:10px;"><br>
                <label>Qualifica / Tipo:</label><br><input type="text" name="tipo" value="<?php echo htmlspecialchars($edit_f['tipo']); ?>" style="width:100%; padding:6px; margin-bottom:10px;"><br>
                <label>Note:</label><br><textarea name="note" style="width:100%; height:50px; margin-bottom:10px;"><?php echo htmlspecialchars($edit_f['note']); ?></textarea><br>
                <button type="submit" name="save_formatore" class="btn btn-blue" style="width:100%;">Salva Formatore</button>
            </form>
        </div>
        <div style="width:70%;">
            <h3>Elenco Formatori</h3>
            <table>
                <tr><th>Cognome e Nome</th><th>Email</th><th>Tipo</th><th>Note</th><th>Azioni</th></tr>
                <?php $res = mysqli_query($conn, "SELECT * FROM formatore ORDER BY cognome"); while($r = mysqli_fetch_assoc($res)): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($r['cognome']." ".$r['nome']); ?></strong></td>
                    <td><?php echo $r['emailf']; ?></td>
                    <td><?php echo htmlspecialchars($r['tipo']); ?></td>
                    <td><small><?php echo htmlspecialchars($r['note']); ?></small></td>
                    <td>
                        <a href="anagrafiche.php?tab=formatori&amp;action=edit&amp;id=<?php echo $r['emailf']; ?>" class="btn btn-gray" style="padding:3px 6px; font-size:12px;">✏️</a>
                        <a href="anagrafiche.php?tab=formatori&amp;action=delete&amp;id=<?php echo $r['emailf']; ?>" class="btn" style="background:#d9534f; color:white; padding:3px 6px; font-size:12px;" onclick="return confirm('Cancellare questo formatore?');">🗑️</a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </table>
        </div>

    <?php elseif($tab === 'docenti'): 
        $edit_d = ['email_dd'=>'', 'nome'=>'', 'cognome'=>'', 'is_edit'=>'0'];
        if($action === 'edit' && $id != '') {
            $edit_d = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM docente_discente WHERE email_dd='$id'"));
            $edit_d['is_edit'] = '1';
        }
    ?>
        <div style="width:33%; display:flex; flex-direction:column; gap:20px;">
            <div style="background:white; padding:15px; border:1px solid #ddd;">
                <h3><?php echo ($edit_d['is_edit']=='1') ? "✏️ Modifica" : "➕ Aggiungi Singolo"; ?> Docente</h3>
                <form method="POST" action="">
                    <input type="hidden" name="is_edit" value="<?php echo $edit_d['is_edit']; ?>">
                    <label>Email:</label><br><input type="email" name="email_dd" value="<?php echo $edit_d['email_dd']; ?>" <?php if($edit_d['is_edit']=='1') echo 'readonly style="background:#eee;"'; ?> required style="width:100%; padding:6px; margin-bottom:10px;"><br>
                    <label>Nome:</label><br><input type="text" name="nome" value="<?php echo htmlspecialchars($edit_d['nome']); ?>" required style="width:100%; padding:6px; margin-bottom:10px;"><br>
                    <label>Cognome:</label><br><input type="text" name="cognome" value="<?php echo htmlspecialchars($edit_d['cognome']); ?>" required style="width:100%; padding:6px; margin-bottom:10px;"><br>
                    <button type="submit" name="save_docente" class="btn btn-blue" style="width:100%;">Salva Singolo</button>
                </form>
            </div>
            
            <?php if($edit_d['is_edit'] == '0'): ?>
            <div style="background:#f9f9f9; padding:15px; border:1px solid #ccc;">
                <h3 style="margin-top:0; color:#5cb85c;">📥 Importazione Massiva (CSV)</h3>
                <p style="font-size:12px; color:#666; margin-top:0;">Incolla le righe generate dal file Excel. Il formato richiesto è:<br><code style="background:#eee; padding:2px; font-weight:bold;">email,nome,cognome</code></p>
                <form method="POST" action="">
                    <textarea name="csv_text" placeholder="esempio.docente@polourbani.edu.it,Mario,Rossi" required style="width:100%; height:180px; font-family:monospace; font-size:12px; padding:6px; margin-bottom:10px; border:1px solid #bbb; box-sizing:border-box;"></textarea>
                    <button type="submit" name="import_docenti_csv" class="btn btn-green" style="width:100%; font-weight:bold;">Avvia Importazione Massiva</button>
                </form>
            </div>
            <?php endif; ?>
        </div>
        
        <div style="width:67%;">
            <h3>Elenco Docenti Discenti</h3>
            <input type="text" id="liveSearch" placeholder="🔍 Cerca rapidamente per cognome o nome..." style="width:100%; padding:8px; margin-bottom:10px; border:1px solid #ccc; border-radius:4px;">
            <table>
                <thead><tr><th>Cognome e Nome</th><th>Email</th><th>Azioni</th></tr></thead>
                <tbody id="tableBody">
                    <?php $res = mysqli_query($conn, "SELECT * FROM docente_discente ORDER BY cognome"); while($r = mysqli_fetch_assoc($res)): ?>
                    <tr class="search-row">
                        <td class="search-name"><strong><?php echo htmlspecialchars($r['cognome']." ".$r['nome']); ?></strong></td>
                        <td><?php echo $r['email_dd']; ?></td>
                        <td>
                            <a href="anagrafiche.php?tab=docenti&amp;action=edit&amp;id=<?php echo $r['email_dd']; ?>" class="btn btn-gray" style="padding:3px 6px; font-size:12px;">✏️</a>
                            <a href="anagrafiche.php?tab=docenti&amp;action=delete&amp;id=<?php echo $r['email_dd']; ?>" class="btn" style="background:#d9534f; color:white; padding:3px 6px; font-size:12px;" onclick="return confirm('Cancellare il docente? Se ha firme associate l\'operazione potrebbe fallire.');">🗑️</a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

    <?php elseif($tab === 'classi'): 
        if ($action === 'studenti' && $id != '') {
            $idc = (int)$id;
            $classe_info = mysqli_fetch_assoc(mysqli_query($conn, "SELECT nome FROM classe WHERE idc=$idc"));
            
            $edit_s = ['ids'=>'', 'cognome'=>'', 'nome'=>'', 'is_edit'=>'0'];
            if (isset($_GET['sub_action']) && $_GET['sub_action'] === 'edit_studente' && isset($_GET['ids'])) {
                $ids_edit = (int)$_GET['ids'];
                $edit_s = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM studente WHERE ids=$ids_edit"));
                $edit_s['is_edit'] = '1';
            }
    ?>
            <div style="width:33%; display:flex; flex-direction:column; gap:20px;">
                <div style="background:white; padding:15px; border:1px solid #ddd;">
                    <h3><?php echo ($edit_s['is_edit']=='1') ? "✏️ Modifica" : "➕ Aggiungi Singolo"; ?> Alunno</h3>
                    <h4 style="margin:0 0 10px 0; color:#0275d8;">Classe: <?php echo htmlspecialchars($classe_info['nome']); ?></h4>
                    <form method="POST" action="">
                        <input type="hidden" name="is_edit" value="<?php echo $edit_s['is_edit']; ?>">
                        <input type="hidden" name="idc" value="<?php echo $idc; ?>">
                        <input type="hidden" name="ids" value="<?php echo $edit_s['ids']; ?>">
                        <label>Cognome:</label><br><input type="text" name="cognome" value="<?php echo htmlspecialchars($edit_s['cognome']); ?>" required style="width:100%; padding:6px; margin-bottom:10px;"><br>
                        <label>Nome:</label><br><input type="text" name="nome" value="<?php echo htmlspecialchars($edit_s['nome']); ?>" required style="width:100%; padding:6px; margin-bottom:10px;"><br>
                        <button type="submit" name="save_studente" class="btn btn-blue" style="width:100%;">Salva Alunno</button>
                    </form>
                </div>
                
                <?php if($edit_s['is_edit'] == '0'): ?>
                <div style="background:#f9f9f9; padding:15px; border:1px solid #ccc;">
                    <h3 style="margin-top:0; color:#5cb85c;">📥 Importa Elenco Alunni (CSV)</h3>
                    <p style="font-size:12px; color:#666; margin-top:0;">Incolla le righe di Excel. Il formato atteso è:<br><code style="background:#eee; padding:2px; font-weight:bold;">Cognome,Nome</code> oppure <code style="background:#eee; padding:2px; font-weight:bold;">Cognome;Nome</code></p>
                    <form method="POST" action="">
                        <input type="hidden" name="idc" value="<?php echo $idc; ?>">
                        <textarea name="csv_text" placeholder="Rossi,Mario&#10;Bianchi,Luigi" required style="width:100%; height:180px; font-family:monospace; font-size:12px; padding:6px; margin-bottom:10px; border:1px solid #bbb; box-sizing:border-box;"></textarea>
                        <button type="submit" name="import_studenti_csv" class="btn btn-green" style="width:100%; font-weight:bold;">Avvia Importazione Massiva</button>
                    </form>
                </div>
                <?php endif; ?>
                
                <a href="anagrafiche.php?tab=classi" class="btn" style="background:#777; color:white; text-align:center; padding:8px; text-decoration:none; font-weight:bold; border-radius:4px;">⬅️ Torna al tabellone Classi</a>
            </div>
            
            <div style="width:67%;">
                <h3>Elenco Alunni iscritti nella classe: <span style="color:#0275d8;"><?php echo htmlspecialchars($classe_info['nome']); ?></span></h3>
                <input type="text" id="liveSearchStudenti" placeholder="🔍 Filtra rapidamente gli alunni per cognome o nome..." style="width:100%; padding:8px; margin-bottom:10px; border:1px solid #ccc; border-radius:4px;">
                <table>
                    <thead>
                        <tr><th style="width:10%; text-align:center;">N°</th><th>Cognome e Nome Alunno</th><th>Azioni</th></tr>
                    </thead>
                    <tbody>
                        <?php 
                        $res_s = mysqli_query($conn, "SELECT * FROM studente WHERE idc=$idc ORDER BY cognome ASC, nome ASC"); 
                        $count_s = 1;
                        if(mysqli_num_rows($res_s) == 0) {
                        ?>
                            <tr><td colspan="3" style="text-align:center; font-style:italic; color:#aaa; padding:15px;">Nessuno studente inserito per questa classe. Usa la casella di testo a sinistra per caricarli in blocco.</td></tr>
                        <?php 
                        } else {
                            while($s = mysqli_fetch_assoc($res_s)) { 
                        ?>
                            <tr class="search-student-row">
                                <td style="text-align:center; font-weight:bold; color:#555;"><?php echo $count_s; ?></td>
                                <td class="search-student-name"><strong><?php echo htmlspecialchars($s['cognome']." ".$s['nome']); ?></strong></td>
                                <td>
                                    <a href="anagrafiche.php?tab=classi&amp;action=studenti&amp;id=<?php echo $idc; ?>&amp;sub_action=edit_studente&amp;ids=<?php echo $s['ids']; ?>" class="btn btn-gray" style="padding:3px 6px; font-size:12px;" title="Modifica Nome Alunno">✏️</a>
                                    <a href="anagrafiche.php?tab=classi&amp;action=delete_studente&amp;ids=<?php echo $s['ids']; ?>&amp;idc=<?php echo $idc; ?>" class="btn" style="background:#d9534f; color:white; padding:3px 6px; font-size:12px;" onclick="return confirm('Eliminare questo studente dalla classe?');" title="Rimuovi Studente">🗑️</a>
                                </td>
                            </tr>
                        <?php 
                            $count_s++; 
                            } 
                        } 
                        ?>
                    </tbody>
                </table>
            </div>
            
            <script>
            const searchInputS = document.getElementById('liveSearchStudenti');
            if(searchInputS) {
                searchInputS.addEventListener('keyup', function() {
                    let filter = this.value.toUpperCase();
                    document.querySelectorAll('.search-student-row').forEach(row => {
                        let name = row.querySelector('.search-student-name').textContent;
                        row.style.display = (name.toUpperCase().indexOf(filter) > -1) ? "" : "none";
                    });
                });
            }
            </script>
    <?php 
        } else {
            $edit_c = ['idc'=>'', 'nome'=>'', 'sede'=>'', 'numero_studenti_totali'=>'', 'is_edit'=>'0'];
            if($action === 'edit' && $id != '') {
                $edit_c = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM classe WHERE idc=".(int)$id));
                $edit_c['is_edit'] = '1';
            }
    ?>
            <div style="width:30%; background:white; padding:15px; border:1px solid #ddd; height:fit-content;">
                <h3><?php echo ($edit_c['is_edit']=='1') ? "✏️ Modifica" : "➕ Aggiungi"; ?> Classe</h3>
                <form method="POST" action="">
                    <input type="hidden" name="is_edit" value="<?php echo $edit_c['is_edit']; ?>">
                    <input type="hidden" name="idc" value="<?php echo $edit_c['idc']; ?>">
                    <label>Nome Classe (es. 3A Informatica):</label><br><input type="text" name="nome" value="<?php echo htmlspecialchars($edit_c['nome']); ?>" required style="width:100%; padding:6px; margin-bottom:10px;"><br>
                    <label>Sede Scuola:</label><br><input type="text" name="sede" value="<?php echo htmlspecialchars($edit_c['sede']); ?>" required style="width:100%; padding:6px; margin-bottom:10px;"><br>
                    <label>Numero Studenti Iscritti:</label><br><input type="number" name="numero_studenti_totali" value="<?php echo $edit_c['numero_studenti_totali']; ?>" required style="width:100%; padding:6px; margin-bottom:15px;"><br>
                    <button type="submit" name="save_classe" class="btn btn-blue" style="width:100%;">Salva Classe</button>
                </form>
            </div>
            <div style="width:70%;">
                <h3>Elenco Classi e Integrazione Alunni</h3>
                <table>
                    <tr><th>Nome Classe</th><th>Sede</th><th>Studenti Totali</th><th>Azioni di Registro</th></tr>
                    <?php $res = mysqli_query($conn, "SELECT * FROM classe ORDER BY nome"); while($r = mysqli_fetch_assoc($res)): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($r['nome']); ?></strong></td>
                        <td><?php echo htmlspecialchars($r['sede']); ?></td>
                        <td><?php echo $r['numero_studenti_totali']; ?> coinvolti</td>
                        <td style="white-space: nowrap;">
                            <a href="anagrafiche.php?tab=classi&amp;action=studenti&amp;id=<?php echo $r['idc']; ?>" class="btn" style="background:#0275d8; color:white; padding:3px 8px; font-size:12px; text-decoration:none; font-weight:bold; border-radius:3px;" title="Vedi o Carica l'elenco degli Studenti">🧑‍🎓 Alunni</a>
                            <a href="anagrafiche.php?tab=classi&amp;action=edit&amp;id=<?php echo $r['idc']; ?>" class="btn btn-gray" style="padding:3px 6px; font-size:12px;">✏️</a>
                            <a href="anagrafiche.php?tab=classi&amp;action=delete&amp;id=<?php echo $r['idc']; ?>" class="btn" style="background:#d9534f; color:white; padding:3px 6px; font-size:12px;" onclick="return confirm('Cancellare la classe? L\'azione eliminerà anche tutti gli studenti associati.');">🗑️</a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </table>
            </div>
    <?php 
        } 
    endif; 
    ?>

</div>

<script>
const searchInput = document.getElementById('liveSearch');
if(searchInput) {
    searchInput.addEventListener('keyup', function() {
        let filter = this.value.toUpperCase();
        document.querySelectorAll('.search-row').forEach(row => {
            let name = row.querySelector('.search-name').textContent;
            row.style.display = (name.toUpperCase().indexOf(filter) > -1) ? "" : "none";
        });
    });
}
</script>