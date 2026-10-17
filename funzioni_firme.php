<?php
// funzioni_firme.php - Helper per firma automatica attività calendarizzate

/**
 * Verifica se una categoria di iniziativa è abilitata alla firma automatica massiva.
 * Sono abilitate ESCLUSIVAMENTE:
 * - Sperimentazione (B.1.8)
 * - Accompagnamento e implementazione (B.1.9)
 * - Attività con i destinatari (B.1.13)
 *
 * ESCLUSIONE CATEGORICA:
 * - Formazione dei formatori (B.1.7) e altre categorie.
 */
function is_categoria_ammessa_firme($categoria) {
    if (empty($categoria)) {
        return false;
    }
    // Esclusione categorica: Formazione dei formatori non deve mai passare
    if (stripos($categoria, 'B.1.7') !== false || stripos($categoria, 'Formazione dei formatori') !== false) {
        return false;
    }
    // Solo le categorie ammesse
    if (
        stripos($categoria, 'B.1.8') !== false ||
        stripos($categoria, 'Sperimentazione') !== false ||
        stripos($categoria, 'B.1.9') !== false ||
        stripos($categoria, 'Accompagnamento') !== false ||
        stripos($categoria, 'implementazione') !== false ||
        stripos($categoria, 'B.1.13') !== false ||
        stripos($categoria, 'destinatari') !== false
    ) {
        return true;
    }
    return false;
}

/**
 * Clausola SQL per filtrare le sole iniziative ammesse.
 */
function get_sql_clausola_categorie_ammesse($prefisso = 'i') {
    return "({$prefisso}.categoria NOT LIKE '%B.1.7%' 
             AND {$prefisso}.categoria NOT LIKE '%Formazione dei formatori%'
             AND (
                 {$prefisso}.categoria LIKE '%B.1.8%'
                 OR {$prefisso}.categoria LIKE '%Sperimentazione%'
                 OR {$prefisso}.categoria LIKE '%B.1.9%'
                 OR {$prefisso}.categoria LIKE '%Accompagnamento%'
                 OR {$prefisso}.categoria LIKE '%implementazione%'
                 OR {$prefisso}.categoria LIKE '%B.1.13%'
                 OR {$prefisso}.categoria LIKE '%destinatari%'
             ))";
}

/**
 * Esegue la firma in presenza delle attività calendarizzate per le categorie ammesse.
 * Non sovrascrive presenze già registrate.
 * Non modifica calendari e attività caricate.
 */
function esegui_firma_massiva($conn, $idi = 0) {
    $idi = (int)$idi;

    if ($idi > 0) {
        $chk_q = mysqli_query($conn, "SELECT idi, categoria FROM iniziativa WHERE idi = $idi");
        $ini = mysqli_fetch_assoc($chk_q);
        if (!$ini) {
            return ['success' => false, 'messaggio' => "Iniziativa non trovata."];
        }
        if (!is_categoria_ammessa_firme($ini['categoria'])) {
            return ['success' => false, 'messaggio' => "Operazione bloccata: la categoria '{$ini['categoria']}' è esclusa dalla firma automatica."];
        }
    }

    $where_cat = get_sql_clausola_categorie_ammesse('i');
    $where_idi = ($idi > 0) ? "AND i.idi = $idi" : "";

    // Conteggio presenze già registrate (preservate)
    $q_f = mysqli_query($conn, "
        SELECT COUNT(*) as tot FROM assegnazione_formatore_attivita af
        JOIN attivita a ON af.ida = a.ida
        JOIN iniziativa i ON a.idi = i.idi
        WHERE af.modalita_effettiva != '' AND af.modalita_effettiva IS NOT NULL
          AND $where_cat $where_idi
    ");
    $gia_registrate_f = (int)mysqli_fetch_assoc($q_f)['tot'];

    $q_d = mysqli_query($conn, "
        SELECT COUNT(*) as tot FROM assegnazione_docente_attivita ad
        JOIN attivita a ON ad.ida = a.ida
        JOIN iniziativa i ON a.idi = i.idi
        WHERE ad.modalita_effettiva != '' AND ad.modalita_effettiva IS NOT NULL
          AND $where_cat $where_idi
    ");
    $gia_registrate_d = (int)mysqli_fetch_assoc($q_d)['tot'];

    // 1. UPDATE Formatori
    $sql_upd_f = "
        UPDATE assegnazione_formatore_attivita af
        JOIN attivita a ON af.ida = a.ida
        JOIN iniziativa i ON a.idi = i.idi
        SET af.modalita_effettiva = 'Presenza',
            af.ore_effettive = a.ore
        WHERE (af.modalita_effettiva = '' OR af.modalita_effettiva IS NULL)
          AND $where_cat $where_idi
    ";
    if (!mysqli_query($conn, $sql_upd_f)) {
        return ['success' => false, 'messaggio' => "Errore formatori: " . mysqli_error($conn)];
    }
    $nuove_f = mysqli_affected_rows($conn);

    // 2. UPDATE Docenti Discenti
    $sql_upd_d = "
        UPDATE assegnazione_docente_attivita ad
        JOIN attivita a ON ad.ida = a.ida
        JOIN iniziativa i ON a.idi = i.idi
        SET ad.modalita_effettiva = 'Presenza',
            ad.ore_effettive = a.ore
        WHERE (ad.modalita_effettiva = '' OR ad.modalita_effettiva IS NULL)
          AND $where_cat $where_idi
    ";
    if (!mysqli_query($conn, $sql_upd_d)) {
        return ['success' => false, 'messaggio' => "Errore docenti: " . mysqli_error($conn)];
    }
    $nuove_d = mysqli_affected_rows($conn);

    $tot_nuove = $nuove_f + $nuove_d;
    $tot_pres = $gia_registrate_f + $gia_registrate_d;

    return [
        'success' => true,
        'nuove_formatori' => $nuove_f,
        'nuove_docenti' => $nuove_d,
        'totale_nuove' => $tot_nuove,
        'preservate_totale' => $tot_pres,
        'messaggio' => "Firma registrata con successo: {$tot_nuove} nuove presenze inserite ({$nuove_f} formatori, {$nuove_d} docenti discenti). {$tot_pres} presenze preesistenti preservate intatte. Corsi Formazione dei Formatori inalterati."
    ];
}
