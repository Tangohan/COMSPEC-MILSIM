<?php
declare(strict_types=1);

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$schema = $schema ?? [];
$config = $config ?? [];
$configMeta = $configMeta ?? [];
$history = is_array($history ?? null) ? $history : [];
$csrfToken = (string) ($csrfToken ?? '');
$success = $success ?? null;
$error = $error ?? null;

// Encoder JSON pour JavaScript
$schemaJson = json_encode($schema, JSON_UNESCAPED_UNICODE);
$configJson = json_encode($config, JSON_UNESCAPED_UNICODE);
$profilesJson = json_encode($schema['profiles'] ?? [], JSON_UNESCAPED_UNICODE);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuration Réalisme ATAK — ATHENA C2</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --athena-primary: #00ff00;
            --athena-bg: #0d0d0d;
            --athena-card: #1a1a1a;
            --athena-border: #333;
            --athena-text: #e0e0e0;
            --athena-muted: #888;
        }
        
        body {
            background-color: var(--athena-bg);
            color: var(--athena-text);
            font-family: 'Courier New', monospace;
            margin: 0;
            padding: 0;
        }
        
        .navbar {
            background-color: var(--athena-card);
            border-bottom: 2px solid var(--athena-primary);
            padding: 1rem 2rem;
        }
        
        .navbar-brand {
            color: var(--athena-primary) !important;
            font-weight: bold;
            font-size: 1.5rem;
        }
        
        .container-fluid {
            max-width: 1400px;
            padding: 2rem;
        }
        
        .header-card {
            background: linear-gradient(135deg, #1a3a1a 0%, #1a1a1a 100%);
            border: 1px solid var(--athena-primary);
            border-radius: 8px;
            padding: 2rem;
            margin-bottom: 2rem;
        }
        
        .header-card h1 {
            color: var(--athena-primary);
            margin-bottom: 0.5rem;
        }
        
        .header-card .meta {
            color: var(--athena-muted);
            font-size: 0.9rem;
        }
        
        .alert {
            border-radius: 8px;
            border: 1px solid;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .alert-success {
            background-color: #1a3a1a;
            border-color: var(--athena-primary);
            color: var(--athena-primary);
        }
        
        .alert-danger {
            background-color: #3a1a1a;
            border-color: #ff0000;
            color: #ff6666;
        }
        
        .toolbar {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }
        
        .btn {
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 6px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .btn-primary {
            background-color: var(--athena-primary);
            color: var(--athena-bg);
        }
        
        .btn-primary:hover {
            background-color: #00cc00;
            box-shadow: 0 0 10px var(--athena-primary);
        }
        
        .btn-secondary {
            background-color: var(--athena-card);
            color: var(--athena-text);
            border: 1px solid var(--athena-border);
        }
        
        .btn-secondary:hover {
            background-color: #2a2a2a;
        }
        
        .btn-profile {
            background-color: var(--athena-card);
            border: 1px solid var(--athena-border);
            color: var(--athena-text);
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
        }
        
        .btn-profile:hover {
            border-color: var(--athena-primary);
        }
        
        .tabs-container {
            background-color: var(--athena-card);
            border: 1px solid var(--athena-border);
            border-radius: 8px;
            overflow: hidden;
        }
        
        .nav-tabs {
            background-color: #0d0d0d;
            border-bottom: 2px solid var(--athena-primary);
            padding: 1rem 1rem 0;
            display: flex;
            gap: 0.5rem;
            overflow-x: auto;
            flex-wrap: nowrap;
        }
        
        .nav-tabs .nav-link {
            background-color: var(--athena-card);
            border: 1px solid var(--athena-border);
            color: var(--athena-muted);
            padding: 0.75rem 1.5rem;
            border-radius: 6px 6px 0 0;
            white-space: nowrap;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .nav-tabs .nav-link:hover {
            background-color: #2a2a2a;
            color: var(--athena-text);
        }
        
        .nav-tabs .nav-link.active {
            background-color: var(--athena-primary);
            color: var(--athena-bg);
            border-color: var(--athena-primary);
        }
        
        .tab-content {
            padding: 2rem;
        }
        
        .domain-header h3 {
            color: var(--athena-primary);
            margin-bottom: 0.5rem;
        }
        
        .domain-header .text-muted {
            color: var(--athena-muted);
            margin-bottom: 2rem;
            display: block;
        }
        
        .parameters-container {
            display: flex;
            flex-direction: column;
            gap: 2rem;
        }
        
        .parameter-row {
            background-color: #0d0d0d;
            border: 1px solid var(--athena-border);
            border-radius: 6px;
            padding: 1.5rem;
            display: flex;
            gap: 2rem;
        }
        
        .parameter-label {
            flex: 0 0 40%;
        }
        
        .parameter-label label {
            color: var(--athena-primary);
            font-weight: bold;
            display: block;
            margin-bottom: 0.5rem;
        }
        
        .parameter-label small {
            color: var(--athena-muted);
        }
        
        .help-icon {
            cursor: help;
            margin-left: 0.5rem;
            opacity: 0.6;
        }
        
        .parameter-input {
            flex: 1;
            display: flex;
            align-items: center;
        }
        
        .form-control, .form-select {
            background-color: var(--athena-card);
            border: 1px solid var(--athena-border);
            color: var(--athena-text);
            padding: 0.5rem 1rem;
        }
        
        .form-control:focus, .form-select:focus {
            background-color: var(--athena-card);
            border-color: var(--athena-primary);
            color: var(--athena-text);
            box-shadow: 0 0 5px var(--athena-primary);
        }
        
        .form-range {
            width: 100%;
            accent-color: var(--athena-primary);
        }
        
        .slider-container {
            width: 100%;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .slider-value {
            min-width: 100px;
            text-align: right;
            color: var(--athena-primary);
            font-weight: bold;
        }
        
        .form-check-input {
            background-color: var(--athena-card);
            border: 1px solid var(--athena-border);
        }
        
        .form-check-input:checked {
            background-color: var(--athena-primary);
            border-color: var(--athena-primary);
        }
        
        .form-check-label {
            color: var(--athena-text);
        }
        
        .form-switch .form-check-input {
            width: 3em;
            height: 1.5em;
        }
        
        .color-picker-container {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .color-hex {
            font-weight: bold;
            color: var(--athena-primary);
        }
        
        .number-input-container {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .input-unit {
            color: var(--athena-muted);
        }
        
        .multi-select-container {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        
        .save-bar {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background-color: var(--athena-card);
            border-top: 2px solid var(--athena-primary);
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            z-index: 1000;
        }
        
        .spinner-border {
            width: 1.5rem;
            height: 1.5rem;
            border-width: 2px;
            border-color: var(--athena-primary);
            border-right-color: transparent;
        }
        
        .modal-content {
            background-color: var(--athena-card);
            color: var(--athena-text);
            border: 1px solid var(--athena-primary);
        }
        
        .modal-header {
            border-bottom: 1px solid var(--athena-border);
        }
        
        .modal-footer {
            border-top: 1px solid var(--athena-border);
        }
        
        .history-item {
            background-color: #0d0d0d;
            border: 1px solid var(--athena-border);
            border-radius: 6px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        
        .history-item:hover {
            border-color: var(--athena-primary);
        }
        
        @media (max-width: 768px) {
            .parameter-row {
                flex-direction: column;
            }
            
            .parameter-label {
                flex: 1;
            }
            
            .toolbar {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="container-fluid">
            <span class="navbar-brand">⚙️ ATHENA C2 — Configuration Réalisme ATAK</span>
            <div>
                <a href="<?= $h(url('back-office/atak/controle-serveur')) ?>" class="btn btn-secondary btn-sm">
                    ← Retour
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <!-- Header -->
        <div class="header-card">
            <h1>📡 Configuration Réalisme ATAK</h1>
            <div class="meta">
                Version: <?= $h($configMeta['version'] ?? '1.0.0') ?> | 
                Dernière modification: <?= $h($configMeta['updated_at'] ?? 'Jamais') ?>
                <?php if (isset($schema['metadata']['total_parameters'])): ?>
                    | <?= $schema['metadata']['total_parameters'] ?> paramètres
                <?php endif; ?>
            </div>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success" role="alert">
                ✅ <?= $h($success) ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger" role="alert">
                ❌ <?= $h($error) ?>
            </div>
        <?php endif; ?>

        <!-- Toolbar -->
        <div class="toolbar">
            <button type="button" id="btn-save" class="btn btn-primary">
                💾 Enregistrer configuration
            </button>
            
            <button type="button" id="btn-history" class="btn btn-secondary" data-bs-toggle="modal" data-bs-target="#historyModal">
                📜 Historique
            </button>
            
            <button type="button" id="btn-verify" class="btn btn-secondary" onclick="window.open('<?= $h(url('admin/atak/realism/verify')) ?>', '_blank')">
                🔍 Vérifier migration
            </button>
            
            <div style="flex: 1;"></div>
            
            <div style="display: flex; gap: 0.5rem; align-items: center;">
                <span style="color: var(--athena-muted); font-size: 0.9rem;">Profils :</span>
                <button type="button" class="btn btn-profile" data-profile="beginner">
                    🟢 Débutant
                </button>
                <button type="button" class="btn btn-profile" data-profile="event">
                    🟡 Événement
                </button>
                <button type="button" class="btn btn-profile" data-profile="expert">
                    🔴 Expert
                </button>
            </div>
        </div>

        <!-- Onglets -->
        <div class="tabs-container">
            <ul class="nav nav-tabs" id="domainTabs" role="tablist">
                <!-- Généré dynamiquement par JavaScript -->
            </ul>

            <div class="tab-content" id="domainTabsContent">
                <!-- Généré dynamiquement par JavaScript -->
            </div>
        </div>

        <!-- Espace pour barre fixe -->
        <div style="height: 80px;"></div>
    </div>

    <!-- Barre de sauvegarde fixe -->
    <div class="save-bar">
        <div id="save-status">
            <span style="color: var(--athena-muted);">Prêt à enregistrer</span>
        </div>
        <button type="button" id="btn-save-bottom" class="btn btn-primary">
            💾 Enregistrer
        </button>
    </div>

    <!-- Modal Historique -->
    <div class="modal fade" id="historyModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">📜 Historique des versions</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <?php if (empty($history)): ?>
                        <p style="color: var(--athena-muted);">Aucun historique disponible.</p>
                    <?php else: ?>
                        <?php foreach ($history as $item): ?>
                            <div class="history-item">
                                <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                                    <strong style="color: var(--athena-primary);">
                                        <?= $h($item['config_name'] ?? 'Sans nom') ?>
                                    </strong>
                                    <span style="color: var(--athena-muted); font-size: 0.9rem;">
                                        <?= $h($item['updated_at'] ?? '') ?>
                                    </span>
                                </div>
                                <div style="color: var(--athena-muted); font-size: 0.9rem;">
                                    Version: <?= $h($item['config_version'] ?? '1.0.0') ?>
                                    <?php if (isset($item['updated_by'])): ?>
                                        | Par: User #<?= $h($item['updated_by']) ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= $h(url('assets/js/atak-realism-ui-generator.js')) ?>"></script>
    <script>
        // Données PHP → JavaScript
        const SCHEMA = <?= $schemaJson ?>;
        const CURRENT_CONFIG = <?= $configJson ?>;
        const PROFILES = <?= $profilesJson ?>;
        const CSRF_TOKEN = '<?= $h($csrfToken) ?>';
        const SAVE_URL = '<?= $h(url('admin/atak/realism/save')) ?>';
        
        // Instance générateur
        let generator = null;
        
        // Initialisation
        document.addEventListener('DOMContentLoaded', () => {
            console.log('[ATHENA] Initialisation UI réalisme...');
            console.log('Schéma:', SCHEMA);
            console.log('Config actuelle:', CURRENT_CONFIG);
            
            generator = new AtakRealismUIGenerator(SCHEMA);
            generator.loadValues(CURRENT_CONFIG);
            
            renderTabs();
            renderAllDomains();
            generator.attachEvents();
            attachSaveHandlers();
            attachProfileHandlers();
            
            // Activer premier onglet
            const firstTab = document.querySelector('.nav-link');
            if (firstTab) {
                firstTab.click();
            }
            
            console.log('[ATHENA] UI initialisée avec succès');
        });
        
        function renderTabs() {
            const tabsContainer = document.getElementById('domainTabs');
            const domains = SCHEMA.domains || {};
            
            // Trier domaines par order
            const sortedDomains = Object.entries(domains).sort((a, b) => {
                return (a[1].order || 99) - (b[1].order || 99);
            });
            
            sortedDomains.forEach(([key, domain], index) => {
                const li = document.createElement('li');
                li.className = 'nav-item';
                li.role = 'presentation';
                
                const button = document.createElement('button');
                button.className = index === 0 ? 'nav-link active' : 'nav-link';
                button.id = `tab-${key}`;
                button.dataset.bsToggle = 'tab';
                button.dataset.bsTarget = `#content-${key}`;
                button.type = 'button';
                button.role = 'tab';
                button.textContent = domain.label || key;
                
                li.appendChild(button);
                tabsContainer.appendChild(li);
            });
        }
        
        function renderAllDomains() {
            const contentContainer = document.getElementById('domainTabsContent');
            const domains = SCHEMA.domains || {};
            
            // Trier domaines par order
            const sortedDomains = Object.entries(domains).sort((a, b) => {
                return (a[1].order || 99) - (b[1].order || 99);
            });
            
            sortedDomains.forEach(([key, domain], index) => {
                const div = document.createElement('div');
                div.className = index === 0 ? 'tab-pane fade show active' : 'tab-pane fade';
                div.id = `content-${key}`;
                div.role = 'tabpanel';
                
                generator.renderDomain(key, div);
                contentContainer.appendChild(div);
            });
        }
        
        function attachSaveHandlers() {
            const saveButtons = [
                document.getElementById('btn-save'),
                document.getElementById('btn-save-bottom')
            ];
            
            saveButtons.forEach(btn => {
                btn.addEventListener('click', async () => {
                    await saveConfig();
                });
            });
        }
        
        async function saveConfig() {
            const statusDiv = document.getElementById('save-status');
            statusDiv.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Enregistrement...';
            
            try {
                // Extraire valeurs
                const config = generator.extractValues();
                
                // Valider côté client
                const validation = generator.validate(config);
                
                if (!validation.valid) {
                    alert('❌ Configuration invalide :\n\n' + validation.errors.join('\n'));
                    statusDiv.innerHTML = '<span style="color: #ff0000;">❌ Erreurs de validation</span>';
                    return;
                }
                
                // Envoyer au serveur
                const response = await fetch(SAVE_URL, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': CSRF_TOKEN
                    },
                    body: JSON.stringify({
                        config: config,
                        name: 'Configuration modifiée via UI',
                        _csrf: CSRF_TOKEN
                    })
                });
                
                const result = await response.json();
                
                if (result.ok) {
                    statusDiv.innerHTML = '<span style="color: var(--athena-primary);">✅ ' + result.message + '</span>';
                    
                    // Recharger page après 1.5s
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    statusDiv.innerHTML = '<span style="color: #ff0000;">❌ ' + result.error + '</span>';
                    
                    if (result.errors && result.errors.length > 0) {
                        alert('❌ Erreurs serveur :\n\n' + result.errors.join('\n'));
                    }
                }
                
            } catch (error) {
                console.error('Erreur save:', error);
                statusDiv.innerHTML = '<span style="color: #ff0000;">❌ Erreur réseau</span>';
                alert('❌ Erreur réseau : ' + error.message);
            }
        }
        
        function attachProfileHandlers() {
            const profileButtons = document.querySelectorAll('[data-profile]');
            
            profileButtons.forEach(btn => {
                btn.addEventListener('click', () => {
                    const profileKey = btn.dataset.profile;
                    applyProfile(profileKey);
                });
            });
        }
        
        function applyProfile(profileKey) {
            const profile = PROFILES[profileKey];
            
            if (!profile) {
                alert('❌ Profil introuvable : ' + profileKey);
                return;
            }
            
            const confirmed = confirm(
                `🎯 Appliquer le profil "${profile.label}" ?\n\n` +
                `${profile.description}\n\n` +
                `⚠️ Ceci remplacera les valeurs actuelles par les valeurs du profil.`
            );
            
            if (!confirmed) return;
            
            // Merger overrides dans config actuelle
            const overrides = profile.overrides || {};
            
            Object.keys(overrides).forEach(domainKey => {
                Object.keys(overrides[domainKey]).forEach(paramKey => {
                    const input = document.getElementById(`${domainKey}_${paramKey}`);
                    
                    if (input) {
                        const value = overrides[domainKey][paramKey];
                        const type = input.dataset.type;
                        
                        if (type === 'toggle') {
                            input.checked = value;
                            input.dispatchEvent(new Event('change'));
                        } else if (type === 'slider' || type === 'number') {
                            input.value = value;
                            input.dispatchEvent(new Event('input'));
                        } else {
                            input.value = value;
                        }
                    }
                });
            });
            
            alert('✅ Profil "' + profile.label + '" appliqué.\n\nN\'oubliez pas de sauvegarder !');
        }
    </script>
</body>
</html>
