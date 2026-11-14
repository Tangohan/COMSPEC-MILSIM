<?php

declare(strict_types=1);

/**
 * Étape « Moteur de circuits : reprise des demandes en attente » (Données seulement, aucun schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root, après workflow_engine_tables. Chaque demande
 * d'élévation en attente ou en examen et chaque demande de mobilité en attente reçoit son circuit, directement à
 * l'étape « décision des responsables » : aucune tâche ni notification n'est créée pour ces anciennes demandes, qui
 * restent traitées comme avant. Les nouvelles demandes passent par l'avis du chef d'unité.
 * Idempotente : une demande qui a déjà son circuit est ignorée ; une relance ne crée plus rien.
 */

$workflowBackfillSchema = new \App\Support\Migrations\SchemaInspector($pdo);
if (!$workflowBackfillSchema->tableExists('workflow_instances') || !$workflowBackfillSchema->tableExists('workflow_events')) {
    echo "  [OK] Tables du moteur de circuits absentes : lancer d'abord workflow_engine_tables.\n";
} else {
    $workflowBackfillRepo = new \App\Repositories\WorkflowRepository($pdo);
    $workflowBackfillNow = date('Y-m-d H:i:s');
    $workflowBackfillSources = [
        \App\Services\Workflow\WorkflowEngine::SUBJECT_ELEVATION => [
            'table' => 'elevation_requests',
            'sql' => "SELECT id, tenant_id, target_user_id AS member_id, requested_by AS opened_by FROM elevation_requests WHERE status IN ('pending', 'in_review')",
        ],
        \App\Services\Workflow\WorkflowEngine::SUBJECT_MOBILITY => [
            'table' => 'personnel_mobility_requests',
            'sql' => "SELECT id, tenant_id, user_id AS member_id, requested_by AS opened_by FROM personnel_mobility_requests WHERE status = 'pending'",
        ],
    ];
    foreach ($workflowBackfillSources as $workflowBackfillType => $workflowBackfillSource) {
        if (!$workflowBackfillSchema->tableExists($workflowBackfillSource['table'])) {
            echo '  [OK] Table ' . $workflowBackfillSource['table'] . " absente : rien à reprendre.\n";
            continue;
        }
        $workflowBackfillCreated = 0;
        foreach ($pdo->query($workflowBackfillSource['sql'])->fetchAll(PDO::FETCH_ASSOC) ?: [] as $workflowBackfillRow) {
            $workflowBackfillTenant = (int) $workflowBackfillRow['tenant_id'];
            $workflowBackfillId = (int) $workflowBackfillRow['id'];
            if ($workflowBackfillRepo->findBySubject($workflowBackfillTenant, $workflowBackfillType, $workflowBackfillId) !== null) {
                continue;
            }
            $workflowBackfillInstance = $workflowBackfillRepo->createInstance($workflowBackfillTenant, $workflowBackfillType, $workflowBackfillId, [
                'state' => \App\Services\Workflow\WorkflowEngine::STATE_DECISION,
                'member_user_id' => (int) ($workflowBackfillRow['member_id'] ?? 0),
                'opened_by' => (int) ($workflowBackfillRow['opened_by'] ?? 0),
            ], $workflowBackfillNow);
            $workflowBackfillRepo->addEvent(
                $workflowBackfillTenant,
                $workflowBackfillInstance,
                'imported',
                null,
                \App\Services\Workflow\WorkflowEngine::STATE_DECISION,
                null,
                '',
                [],
                $workflowBackfillNow
            );
            ++$workflowBackfillCreated;
        }
        echo '  [OK] ' . $workflowBackfillSource['table'] . ' : ' . $workflowBackfillCreated . " circuit(s) repris.\n";
    }
}
