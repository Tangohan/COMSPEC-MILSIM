<?php

declare(strict_types=1);

/**
 * Étape « Annuaire : harmonisation des jeux déclarés par les communautés ».
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Le jeu est un texte libre dans
 * tenants.settings (JSON, community.game_label) : « Arma3 », « ARMA 3 » et « ArmA III » deviennent
 * « Arma 3 » (App\Support\GameCatalog). Rien n'est perdu : le texte saisi d'origine est conservé dans
 * community.game_label_original (posé une seule fois). Les jeux inconnus gardent leur texte.
 * Idempotente : un libellé déjà harmonisé n'est pas réécrit, une relance ne change rien.
 */

$gameLabelsSchema = new \App\Support\Migrations\SchemaInspector($pdo);
if (!$gameLabelsSchema->tableExists('tenants') || !$gameLabelsSchema->columnExists('tenants', 'settings')) {
    echo "  [OK] Pas de réglages de communauté : aucun jeu à harmoniser.\n";
} else {
    $gameLabelsUpdate = $pdo->prepare('UPDATE tenants SET settings = ? WHERE id = ?');
    $gameLabelsChanged = 0;
    $gameLabelsRows = $pdo->query('SELECT id, settings FROM tenants WHERE settings IS NOT NULL')->fetchAll(\PDO::FETCH_ASSOC);
    foreach ($gameLabelsRows as $gameLabelsRow) {
        $gameLabelsRaw = (string) ($gameLabelsRow['settings'] ?? '');
        if (!str_contains($gameLabelsRaw, 'game_label')) {
            continue;
        }
        // Objet (pas tableau) : les « {} » vides du reste des réglages restent des objets.
        $gameLabelsSettings = json_decode($gameLabelsRaw);
        if (!$gameLabelsSettings instanceof \stdClass || !($gameLabelsSettings->community ?? null) instanceof \stdClass) {
            continue;
        }
        $gameLabelsCommunity = $gameLabelsSettings->community;
        $gameLabelsCurrent = is_string($gameLabelsCommunity->game_label ?? null) ? $gameLabelsCommunity->game_label : '';
        $gameLabelsTarget = \App\Support\GameCatalog::normalize($gameLabelsCurrent);
        if ($gameLabelsCurrent === '' || $gameLabelsTarget === $gameLabelsCurrent) {
            continue;
        }
        if (!isset($gameLabelsCommunity->game_label_original)) {
            $gameLabelsCommunity->game_label_original = $gameLabelsCurrent;
        }
        $gameLabelsCommunity->game_label = $gameLabelsTarget;
        $gameLabelsUpdate->execute([
            json_encode($gameLabelsSettings, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $gameLabelsRow['id'],
        ]);
        ++$gameLabelsChanged;
    }
    echo "  [OK] Jeux des communautés : {$gameLabelsChanged} libellé(s) harmonisé(s) (texte d’origine conservé).\n";
}
