<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include('auth.php');
include('db.php');
include('navbar.php');

$success_msg = "";
$error_msg = "";

if (isset($_POST['importa_studenti_submit'])) {
    $idc = (int)$_POST['idc'];
    
    if ($idc <= 0) {
        $error_msg = "❌ Seleziona una classe valida.";
    } elseif (isset($_FILES['file_csv']) && $_FILES['file_csv']['error'] == 0) {
        $filename = $_FILES['file_csv']['tmp_name'];
        $handle = fopen($filename, "r");
        
        $inseriti = 0;
        // Legge il file CSV riga per riga
        while (($data = fgetcsv($handle, 1000, ";")) !== FALSE) {
            // Salta le righe vuote o l'eventuale intestazione (se presente)
            if (empty($data[0]) || strtolower($data[0]) == 'cognome') continue;
            
            $cognome = mysqli_real_escape_string($conn, trim($data[0]));
            $nome = isset($data[1]) ? mysqli_real_escape_string($conn, trim($data[1])) : '';
            
            if (!empty($cognome) && !empty($nome)) {
                $sql = "INSERT INTO studente (idc, cognome, nome) VALUES ($idc, '$cognome', '$nome')";
                if (mysqli_query($conn, $sql)) {
                    $inseriti++;
                }
            }
        }
        fclose($handle);
        $success_msg = "✅ Importazione completata con successo! Inseriti $inseriti studenti.";
    } else {
        $error_msg = "❌ Errore nel caricamento del file. Controlla che sia un CSV valido.";
    }
}

// Carica le classi per il menu a tendina
$classi_res = mysqli_query($conn, "SELECT idc, nome FROM classe ORDER BY nome ASC");
?>

<div style="font-family: sans-serif; padding: 10px; max-width: 600px;">
    <h2>📥 Caricamento Massivo Studenti da CSV</h2>
    <p style="color:#555;">Scegli la classe di destinazione e seleziona il file CSV degli alunni.</p>
    
    <?php if(!empty($success_msg)): ?> <div style="background:#dff0d8; color:#3c763d; padding:12px; margin-bottom:15px; border:1px solid #d6e9c6; border-radius:4px;"><?php echo $success_msg; ?></div> <?php endif; ?>
    <?php if(!empty($error_msg)): ?> <div style="background:#f2dede; color:#a94442; padding:12px; margin-bottom:15px; border:1px solid #ebccd1; border-radius:4px;"><?php echo $error_msg; ?></div> <?php endif; ?>

    <form method="POST" action="" enctype="multipart/form-data" style="background:#f8fafc; border:1px solid #cbd5e1; padding:20px; border-radius:6px;">
        <label style="display:block; margin-bottom:8px;"><strong>1. Seleziona la Classe:</strong></label>
        <select name="idc" required style="width:100%; padding:8px; margin-bottom:20px; font-size:14px;">
            <option value="">-- Seleziona Classe --</option>
            <?php while($c = mysqli_fetch_assoc($classi_res)): ?>
                <option value="<?php echo $c['idc']; ?>"><?php echo htmlspecialchars($c['nome']); ?></option>
            <?php endforeach; ?>
        </select>

        <label style="display:block; margin-bottom:8px;"><strong>2. File CSV degli Studenti (Struttura: <code>Cognome;Nome</code>):</strong></label>
        <input type="file" name="file_csv" accept=".csv" required style="display:block; margin-bottom:25px;">

        <button type="submit" name="importa_studenti_submit" class="btn btn-green" style="font-size:15px; padding:10px 20px;">🚀 Avvia Importazione Alunni</button>
    </form>
    
    <div style="margin-top:20px; background:#eef2f7; padding:12px; border-radius:4px; font-size:12px; color:#475569;">
        <strong>💡 Note per il file Excel/CSV:</strong> Crea un foglio Excel con due colonne: la prima con i cognomi e la seconda con i nomi. Fai "Salva con nome" e scegli il formato <strong>CSV (delimitato da separatore di elenco) (*.csv)</strong>.
    </div>
</div>