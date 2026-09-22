<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vérification Migration Réalisme — ATHENA C2</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #0d0d0d;
            color: #e0e0e0;
            font-family: 'Courier New', monospace;
        }
        .navbar {
            background-color: #1a1a1a;
            border-bottom: 2px solid #00ff00;
        }
        .container {
            max-width: 1200px;
            margin-top: 30px;
        }
        .test-card {
            background-color: #1a1a1a;
            border: 1px solid #333;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .test-card.success {
            border-left: 4px solid #00ff00;
        }
        .test-card.failure {
            border-left: 4px solid #ff0000;
        }
        .test-header {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }
        .test-icon {
            font-size: 24px;
            margin-right: 15px;
        }
        .test-icon.success {
            color: #00ff00;
        }
        .test-icon.failure {
            color: #ff0000;
        }
        .test-title {
            font-size: 18px;
            font-weight: bold;
        }
        .test-details {
            background-color: #0d0d0d;
            border: 1px solid #333;
            border-radius: 4px;
            padding: 15px;
            margin-top: 10px;
            font-size: 12px;
            max-height: 300px;
            overflow-y: auto;
        }
        .alert-banner {
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            text-align: center;
            font-size: 18px;
            font-weight: bold;
        }
        .alert-banner.success {
            background-color: #1a3a1a;
            border: 2px solid #00ff00;
            color: #00ff00;
        }
        .alert-banner.failure {
            background-color: #3a1a1a;
            border: 2px solid #ff0000;
            color: #ff0000;
        }
        .btn-primary {
            background-color: #00ff00;
            border: none;
            color: #0d0d0d;
            font-weight: bold;
        }
        .btn-primary:hover {
            background-color: #00cc00;
            color: #0d0d0d;
        }
        code {
            background-color: #0d0d0d;
            color: #00ff00;
            padding: 2px 6px;
            border-radius: 3px;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark">
        <div class="container-fluid">
            <span class="navbar-brand mb-0 h1">🔬 ATHENA C2 — Vérification Migration Réalisme</span>
            <a href="/admin/atak/realism/config" class="btn btn-outline-success btn-sm">← Retour Config</a>
        </div>
    </nav>

    <div class="container">
        <?php if ($allPassed): ?>
            <div class="alert-banner success">
                ✅ MIGRATION RÉUSSIE — Tous les tests passent
            </div>
        <?php else: ?>
            <div class="alert-banner failure">
                ❌ MIGRATION INCOMPLÈTE — Certains tests échouent
            </div>
        <?php endif; ?>

        <div class="row mb-4">
            <div class="col-md-12">
                <h3 style="color: #00ff00; border-bottom: 2px solid #00ff00; padding-bottom: 10px;">
                    📊 Tests automatiques
                </h3>
                <p style="color: #888; margin-top: 10px;">
                    Cette page vérifie la cohérence de la migration des paramètres de réalisme.
                    <br>Tenant ID: <code><?= $tenantId ?></code>
                </p>
            </div>
        </div>

        <?php foreach ($tests as $testKey => $test): ?>
            <div class="test-card <?= $test['success'] ? 'success' : 'failure' ?>">
                <div class="test-header">
                    <div class="test-icon <?= $test['success'] ? 'success' : 'failure' ?>">
                        <?= $test['success'] ? '✅' : '❌' ?>
                    </div>
                    <div>
                        <div class="test-title">
                            <?= ucfirst(str_replace('_', ' ', $testKey)) ?>
                        </div>
                        <div style="color: <?= $test['success'] ? '#00ff00' : '#ff6666' ?>; margin-top: 5px;">
                            <?= htmlspecialchars($test['message']) ?>
                        </div>
                    </div>
                </div>

                <?php if (!empty($test['details'])): ?>
                    <div class="test-details">
                        <strong>Détails :</strong>
                        <pre style="margin-top: 10px; margin-bottom: 0; color: #00ff00;"><?= htmlspecialchars(json_encode($test['details'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>

        <div class="row mt-5 mb-5">
            <div class="col-md-12">
                <h3 style="color: #00ff00; border-bottom: 2px solid #00ff00; padding-bottom: 10px;">
                    🛠️ Actions recommandées
                </h3>
                
                <?php if ($allPassed): ?>
                    <div style="background-color: #1a3a1a; border: 1px solid #00ff00; border-radius: 8px; padding: 20px; margin-top: 20px;">
                        <p><strong>Migration complétée avec succès !</strong></p>
                        <p>Vous pouvez maintenant :</p>
                        <ol>
                            <li>Configurer les paramètres réalisme via <a href="/admin/atak/realism/config" style="color: #00ff00;">l'interface admin</a></li>
                            <li>Tester en jeu les nouveaux paramètres</li>
                            <li>Supprimer les anciens scripts de migration :
                                <ul>
                                    <li><code>bootstrap/atak_realism_config_migration.php</code></li>
                                    <li><code>bootstrap/atak_realism_config_seed.php</code></li>
                                </ul>
                            </li>
                            <li>Consulter le <a href="/docs/technique/guide-test-config-realisme.md" style="color: #00ff00;">guide de test</a></li>
                        </ol>
                    </div>
                <?php else: ?>
                    <div style="background-color: #3a1a1a; border: 1px solid #ff0000; border-radius: 8px; padding: 20px; margin-top: 20px;">
                        <p><strong>Migration incomplète ou erreurs détectées.</strong></p>
                        <p>Actions à effectuer :</p>
                        <ol>
                            <li>Vérifier les logs de migration : <code>storage/logs/realism-migration-*.log</code></li>
                            <li>Ré-exécuter le script de migration : <code>php setup-realism-migration.php</code></li>
                            <li>Vérifier la structure de la base de données</li>
                            <li>Consulter la documentation technique : <code>docs/technique/PLAN-FINAL-CENTRALISATION-REALISME.md</code></li>
                        </ol>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="row">
            <div class="col-md-12 text-center">
                <button onclick="location.reload()" class="btn btn-primary btn-lg">
                    🔄 Recharger les tests
                </button>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
