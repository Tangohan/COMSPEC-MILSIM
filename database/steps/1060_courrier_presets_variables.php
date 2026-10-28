<?php

declare(strict_types=1);

/**
 * Étape « Formats et variables du Bureau Courrier (si vide) » (Référentiels).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

// Seed presets et variables courrier (si table document_presets vide)
$stmt = $pdo->query("SELECT 1 FROM document_presets LIMIT 1");
if ($stmt && !$stmt->fetch()) {
    echo "Seed presets et variables courrier...\n";
    $presets = [
        ['Format · A4 Portrait', 'a4_portrait', 'a4', 'portrait', 1, 1],
        ['Format · A4 Paysage', 'a4_landscape', 'a4', 'landscape', 1, 0],
        ['Format · Note interne', 'note_interne', 'a4', 'portrait', 1, 0],
        ['Format · Compte rendu', 'compte_rendu', 'a4', 'portrait', 1, 0],
        ['Format · Courrier hiérarchique', 'courrier_hierarchique', 'a4', 'portrait', 1, 0],
        ['Format · Décision', 'decision', 'a4', 'portrait', 1, 0],
        ['Format · Fiche de transmission', 'fiche_transmission', 'a4', 'portrait', 1, 0],
        ['Format · Message court', 'message_court', 'a4', 'portrait', 1, 0],
        ['Format · Compte rendu incident', 'cr_incident', 'a4', 'portrait', 1, 0],
        ['Format · Rapport circonstancié', 'rapport_circonstancie', 'a4', 'portrait', 1, 0],
    ];
    $insPreset = $pdo->prepare("INSERT INTO document_presets (tenant_id, name, code, paper_size, orientation, is_system, is_default, created_at) VALUES (NULL, ?, ?, ?, ?, ?, ?, NOW())");
    foreach ($presets as $i => $p) {
        $insPreset->execute([$p[0], $p[1], $p[2], $p[3], $p[4], $i === 0 ? 1 : 0]);
    }
    $vars = [
        ['current_date_fr', 'Date du jour (FR)', 'system', 'Date au format français', 'system'],
        ['current_datetime_fr', 'Date et heure (FR)', 'system', 'Date et heure au format français', 'system'],
        ['current_year', 'Année en cours', 'system', 'Année courante', 'system'],
        ['document.uuid', 'UUID du document', 'document', 'Identifiant unique du document', 'document'],
        ['document.reference_number', 'Référence du document', 'document', 'Numéro de référence', 'document'],
        ['user.first_name', 'Prénom', 'user', 'Prénom de l\'utilisateur connecté', 'user'],
        ['user.last_name', 'Nom', 'user', 'Nom de l\'utilisateur connecté', 'user'],
        ['user.full_name', 'Nom complet', 'user', 'Prénom et nom', 'user'],
        ['user.rank', 'Grade (code)', 'user', 'Code court du grade', 'user'],
        ['user.rank_label', 'Grade (libellé)', 'user', 'Libellé du grade', 'user'],
        ['user.service_number', 'Matricule', 'user', 'Numéro de service', 'user'],
        ['user.email', 'Email', 'user', 'Adresse email', 'user'],
        ['unit.name', 'Unité (nom)', 'structure', 'Nom de l\'unité', 'structure'],
        ['unit.company', 'Compagnie', 'structure', 'Nom de la compagnie', 'structure'],
        ['unit.section', 'Section', 'structure', 'Section', 'structure'],
        ['unit.address', 'Adresse', 'structure', 'Adresse de l\'unité', 'structure'],
        ['unit.city', 'Ville', 'structure', 'Ville', 'structure'],
        ['superior.rank_label', 'Grade du supérieur', 'hierarchy', 'Grade du supérieur hiérarchique', 'hierarchy'],
        ['superior.full_name', 'Nom du supérieur', 'hierarchy', 'Nom complet du supérieur', 'hierarchy'],
        ['superior.position_label', 'Fonction du supérieur', 'hierarchy', 'Fonction du supérieur', 'hierarchy'],
    ];
    $insVar = $pdo->prepare("INSERT INTO document_variables_catalog (tenant_id, code, label, source_type, description, category, is_active) VALUES (NULL, ?, ?, ?, ?, ?, 1)");
    foreach ($vars as $v) {
        $insVar->execute([$v[0], $v[1], $v[2], $v[3], $v[4]]);
    }
    echo "Seed presets et variables courrier OK.\n";
}
