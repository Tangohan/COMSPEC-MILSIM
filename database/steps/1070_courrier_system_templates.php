<?php

declare(strict_types=1);

/**
 * Étape « Modèles courrier système (première installation) » (Référentiels).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

// Seed modèles courrier (templates) liés aux presets — si pas déjà présents
$stmt = $pdo->query("SELECT id, code FROM document_presets WHERE tenant_id IS NULL");
$presetIdsByCode = [];
while ($stmt && ($row = $stmt->fetch(PDO::FETCH_ASSOC))) {
    $presetIdsByCode[$row['code']] = (int) $row['id'];
}
$templatesToSeed = [
    ['note-interne', 'Note interne', 'note_interne', 'courrier', 'Modèle pour note interne.', '<div class="p-8 text-sm"><p class="text-right text-gray-500">Le {{current_date_fr}}</p><p><strong>Objet :</strong> {{subject}}</p><p class="mt-4">{{document.reference_number}}</p><div class="mt-6"><p>Madame, Monsieur,</p><p>Contenu de la note.</p></div>{{signature_block}}</div>'],
    ['compte-rendu', 'Compte rendu', 'compte_rendu', 'courrier', 'Modèle compte rendu.', '<div class="p-8 text-sm"><p class="text-right">Le {{current_date_fr}}</p><p><strong>Objet :</strong> {{subject}}</p><p><strong>Réf. :</strong> {{document.reference_number}}</p><div class="mt-6 space-y-4"><p>Rédigez le compte rendu ci-dessous.</p></div>{{signature_block}}</div>'],
    ['courrier-hierarchique', 'Courrier hiérarchique', 'courrier_hierarchique', 'courrier', 'Courrier à la hiérarchie.', '<div class="p-8 text-sm"><p>{{user.rank_label}} {{user.last_name}} {{user.first_name}}</p><p>Matricule : {{user.service_number}}</p><p class="text-right mt-4">Le {{current_date_fr}}</p><p><strong>À :</strong> {{destination_label}}</p><p><strong>Objet :</strong> {{subject}}</p><div class="mt-6">{{signature_block}}</div></div>'],
    ['decision', 'Décision', 'decision', 'courrier', 'Modèle décision.', '<div class="p-8 text-sm"><p class="font-bold">Décision</p><p>Réf. {{document.reference_number}} — Le {{current_date_fr}}</p><p><strong>Objet :</strong> {{subject}}</p><div class="mt-6"><p>Il est décidé ce qui suit :</p><p class="mt-4">[Contenu de la décision]</p></div>{{signature_block}}</div>'],
    ['fiche-transmission', 'Fiche de transmission', 'fiche_transmission', 'courrier', 'Fiche de transmission.', '<div class="p-8 text-sm border border-gray-200"><p><strong>Fiche de transmission</strong></p><p>Réf. {{document.reference_number}} — {{current_date_fr}}</p><p>De : {{user.full_name}} — À : {{destination_label}}</p><p>Objet : {{subject}}</p><div class="mt-4 min-h-[4rem] border-b border-gray-200"></div>{{signature_block}}</div>'],
    ['message-court', 'Message court', 'message_court', 'courrier', 'Message court.', '<div class="p-8 text-sm"><p>{{current_date_fr}}</p><p><strong>Objet :</strong> {{subject}}</p><p class="mt-4">[Message]</p>{{signature_block}}</div>'],
    ['compte-rendu-incident', 'Compte rendu incident', 'cr_incident', 'courrier', 'Compte rendu d\'incident.', '<div class="p-8 text-sm"><p class="text-right">Le {{current_date_fr}}</p><p><strong>Objet :</strong> {{subject}}</p><p><strong>Réf. :</strong> {{document.reference_number}}</p><div class="mt-6"><p>Compte rendu des faits :</p><p class="mt-2">[Rédiger le compte rendu]</p></div>{{signature_block}}</div>'],
    ['rapport-circonstancie', 'Rapport circonstancié', 'rapport_circonstancie', 'courrier', 'Rapport circonstancié.', '<div class="p-8 text-sm"><p class="font-bold">Rapport circonstancié</p><p>Réf. {{document.reference_number}} — Le {{current_date_fr}}</p><p><strong>Objet :</strong> {{subject}}</p><div class="mt-6 space-y-4"><p>[Exposé des faits]</p></div>{{signature_block}}</div>'],
];
// Première installation seulement : un modèle système supprimé par l'administration ne doit plus revenir
// à chaque passage. Dès qu'un modèle système existe, l'étape ne fait plus rien.
$hasSystemTemplate = $pdo->query('SELECT 1 FROM document_templates WHERE tenant_id IS NULL AND is_system = 1 LIMIT 1');
if ($hasSystemTemplate && $hasSystemTemplate->fetch()) {
    $templatesToSeed = [];
    echo "  [OK] Modèles courrier système déjà installés (aucun ajout).\n";
}
$insTpl = $pdo->prepare("INSERT INTO document_templates (tenant_id, name, slug, category, description, is_system, is_locked, is_active, preset_id, body_template, created_at) VALUES (NULL, ?, ?, ?, ?, 1, 0, 1, ?, ?, NOW())");
foreach ($templatesToSeed as $t) {
    $stmt = $pdo->query("SELECT 1 FROM document_templates WHERE slug = " . $pdo->quote($t[0]) . " LIMIT 1");
    if ($stmt && !$stmt->fetch()) {
        $presetId = $presetIdsByCode[$t[2]] ?? null;
        if ($presetId) {
            $insTpl->execute([$t[1], $t[0], $t[3], $t[4], $presetId, $t[5]]);
        }
    }
}
