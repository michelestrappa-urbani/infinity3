<nav class="main-navbar">
    <div class="nav-group-links">
        <a href="index.php" class="nav-link-item">🗂️ Elenco Iniziative</a>
        <a href="index.php?action=add" class="nav-link-item">➕ Nuova Iniziativa</a>
        <a href="anagrafiche.php" class="nav-link-item nav-link-orange">👥 Gestione Anagrafiche</a>
        <a href="foglio_firme.php" class="nav-link-item nav-link-green">✍️ Registro Foglio Firme</a>
        <a href="dashboard.php" class="nav-link-item nav-link-blue">📊 Dashboard</a>
        <!-- UNIFORMATI: Aggiunte le classi corrette e i colori semantici dedicati -->
        <a href="report_docenti_svolto.php" class="nav-link-item nav-link-purple">🎓 Report Frequenza Discenti</a>
        <a href="report_ore_staff.php" class="nav-link-item nav-link-teal">⏱️ Analisi Ore Staff</a>
        <a href="index_info.php" target="_blank" class="nav-link-item" style="color: #38bdf8; font-weight: 600;" title="Visualizzazione sola lettura di iniziative e calendari">👁️ Portale Consultazione</a>
    </div>
    <div class="nav-group-actions">
        <a href="login.php?action=logout" class="nav-logout-btn">🚪 Esci</a>
    </div>
</nav>

<style>
    /* Layout globale dello stile */
    body { 
        font-family: system-ui, -apple-system, sans-serif; 
        background: #f8fafc; 
        color: #334155; 
        margin: 0; 
        padding: 0 20px; 
    }
    
    /* Barra di navigazione moderna */
    .main-navbar {
        background: #1e293b; /* Elegante colore ardesia scuro */
        padding: 12px 20px;
        margin-top: 10px;
        margin-bottom: 25px;
        border-radius: 6px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    }
    
    .nav-group-links {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap; /* Evita che il menu si spezzi sui monitor più piccoli */
    }
    
    /* Voci standard del menu */
    .nav-link-item {
        color: #cbd5e1;
        text-decoration: none;
        font-size: 14px;
        font-weight: 500;
        padding: 8px 12px;
        border-radius: 4px;
        transition: all 0.2s ease-in-out;
    }
    
    .nav-link-item:hover {
        color: #ffffff;
        background: rgba(255, 255, 255, 0.08);
    }
    
    /* Voci con colori semantici personalizzati */
    .nav-link-orange { color: #f0ad4e; font-weight: 600; }
    .nav-link-orange:hover { background: rgba(240, 173, 78, 0.15); color: #fff; }
    
    .nav-link-green { color: #5cb85c; font-weight: 600; }
    .nav-link-green:hover { background: rgba(92, 184, 92, 0.15); color: #fff; }
    
    .nav-link-blue { color: #38bdf8; font-weight: 600; }
    .nav-link-blue:hover { background: rgba(56, 189, 248, 0.15); color: #fff; }

    /* NUOVI STILI UNIFORMATI: Varianti Purple e Teal */
    .nav-link-purple { color: #c084fc; font-weight: 600; }
    .nav-link-purple:hover { background: rgba(192, 132, 252, 0.15); color: #fff; }

    .nav-link-teal { color: #2dd4bf; font-weight: 600; }
    .nav-link-teal:hover { background: rgba(45, 212, 191, 0.15); color: #fff; }
    
    /* Pulsante di logout */
    .nav-logout-btn {
        color: #f87171;
        text-decoration: none;
        font-size: 14px;
        font-weight: 600;
        padding: 6px 12px;
        border-radius: 4px;
        border: 1px solid rgba(248, 113, 113, 0.2);
        transition: all 0.2s;
    }
    
    .nav-logout-btn:hover {
        color: #ffffff;
        background: #ef4444;
        border-color: #ef4444;
    }
    
    /* Structure component styles */
    table { width: 100%; border-collapse: collapse; margin-top: 15px; background: white; box-shadow: 0 1px 3px rgba(0,0,0,0.05); border-radius: 4px; overflow: hidden; }
    th, td { border: 1px solid #e2e8f0; padding: 12px; text-align: left; }
    th { background-color: #f1f5f9; color: #475569; font-weight: bold; }
    
    .btn { padding: 6px 12px; text-decoration: none; border-radius: 4px; display: inline-block; cursor: pointer; border: none; font-size: 14px; font-weight: 500; }
    .btn-blue { background: #0275d8; color: white; }
    .btn-green { background: #5cb85c; color: white; }
    .btn-gray { background: #f0ad4e; color: white; }
    
    .tab-link { padding: 10px 20px; background: #e2e8f0; text-decoration: none; color: #475569; border-radius: 4px 4px 0 0; font-weight: bold; display: inline-block; }
    .tab-active { background: white; border: 1px solid #cbd5e1; border-bottom: 1px solid white; color: #0275d8; }
</style>