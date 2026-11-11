<?php

declare(strict_types=1);

/**
 * Mon identité (OperatorHubController::identities / documents / covers) : identités, papiers et couvertures du membre.
 *
 * @var string $idTab identites | documents | couverture
 * @var array<string, string> $idTabs
 * @var list<array<string, mixed>> $idIdentities
 * @var list<array<string, mixed>> $idDocuments
 * @var list<array<string, mixed>> $idCovers
 * @var list<array<string, mixed>> $idCountries
 * @var list<array<string, mixed>> $idHistory
 * @var array<string, string>|null $idSync
 * @var int $idCoverQuota
 * @var int $idEditId
 * @var bool $idUnavailable
 * @var bool $idSteamLinked
 * @var string|null $idManagerUrl
 * @var string $csrfToken
 * @var string|null $success
 * @var string|null $error
 */
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
require __DIR__ . '/_icons.php';
$tabUrls = ['identites' => url('operateur/identite'), 'documents' => url('operateur/identite/documents'), 'couverture' => url('operateur/identite/couverture')];
$activeIdentities = array_values(array_filter($idIdentities, static fn (array $i): bool => !$i['archived']));
$countryName = static function (?string $code) use ($idCountries): string {
    foreach ($idCountries as $c) {
        if ($c['code'] === $code) {
            return (string) $c['name'];
        }
    }

    return $code !== null && $code !== '' ? $code : '—';
};
$dateFr = static function (?string $iso): string {
    $iso = (string) $iso;

    return preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $iso, $m) === 1 ? $m[3] . '/' . $m[2] . '/' . $m[1] : '—';
};
$historyLabels = [
    'op_identity.identity_create' => 'Identité créée', 'op_identity.identity_update' => 'Identité modifiée',
    'op_identity.identity_archive' => 'Identité archivée', 'op_identity.identity_restore' => 'Identité restaurée',
    'op_identity.photo' => 'Photo d’identité changée', 'op_identity.document_request' => 'Papier demandé',
    'op_identity.document_renew' => 'Renouvellement demandé', 'op_identity.cover_edit' => 'Légende modifiée',
    'op_identity.cover_duplicate' => 'Couverture dupliquée', 'op_identity.cover_revoke' => 'Couverture révoquée',
    'op_identity.cover_activate' => 'Couverture active choisie', 'id_cover.export_generate' => 'Couverture générée',
    'id_cover.export_athena' => 'Couverture générée depuis l’identité Athena', 'id_cover.export_download' => 'Couverture téléchargée',
    'id_member.document_lost' => 'Papier déclaré perdu', 'id_member.document_stolen' => 'Papier déclaré volé',
    'id_member.document_delete' => 'Demande annulée', 'id_member.sync_send_game' => 'Identité envoyée en jeu',
    'id_member.kit_save' => 'Papiers portés en jeu recomposés',
];
?>
<link href="<?= $h(asset_url('assets/css/operateur-hub.css')) ?>" rel="stylesheet">

<div class="opx opx--identity" data-opx-identity>
    <header class="opx-hero opx-hero--short">
        <div class="opx-hero__inner">
            <div class="opx-hero__copy">
                <h1>Mon identité</h1>
                <p class="opx-hero__lead">Qui vous êtes en jeu : vos identités, vos papiers, vos couvertures. <span class="opx-sim">Simulation fictive</span></p>
            </div>
            <div class="opx-hero__actions">
                <a class="opx-btn opx-btn--ghost-dark" href="<?= $h(url('operateur')) ?>">Tous les outils</a>
                <?php if ($idManagerUrl !== null): ?>
                    <a class="opx-btn opx-btn--ghost-dark" href="<?= $h($idManagerUrl) ?>">Papiers des membres</a>
                <?php endif; ?>
            </div>
        </div>
        <nav class="opx-tabs" aria-label="Sections de mon identité">
            <?php foreach ($idTabs as $key => $label): ?>
                <a class="opx-tab<?= $key === $idTab ? ' is-active' : '' ?>" href="<?= $h($tabUrls[$key]) ?>"<?= $key === $idTab ? ' aria-current="page"' : '' ?>><?= $h($label) ?></a>
            <?php endforeach; ?>
        </nav>
    </header>

    <div class="opx-wrap">
        <?php if (is_string($success ?? null) && $success !== ''): ?>
            <p class="opx-flash opx-flash--ok" role="status"><?= $h($success) ?></p>
        <?php endif; ?>
        <?php if (is_string($error ?? null) && $error !== ''): ?>
            <p class="opx-flash opx-flash--err" role="alert"><?= $h($error) ?></p>
        <?php endif; ?>

        <?php if ($idUnavailable): ?>
            <div class="opx-empty">
                <h2>Indisponible pour l’instant</h2>
                <p>Vos identités n’ont pas pu être lues. Réessayez dans un moment ; si cela dure, prévenez un responsable.</p>
            </div>
        <?php else: ?>
            <?php require __DIR__ . '/_tab_' . ($idTab === 'documents' ? 'documents' : ($idTab === 'couverture' ? 'couverture' : 'identites')) . '.php'; ?>

            <?php if ($idHistory !== []): ?>
                <details class="opx-history">
                    <summary>Historique de mes changements (<?= count($idHistory) ?>)</summary>
                    <ul>
                        <?php foreach ($idHistory as $e): ?>
                            <li>
                                <span><?= $h($historyLabels[$e['action']] ?? 'Changement enregistré') ?><?= isset($e['after']['ref']) ? ' · ' . $h($e['after']['ref']) : '' ?></span>
                                <time><?= $h(substr((string) $e['created_at'], 0, 16)) ?> UTC</time>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </details>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
<script src="<?= $h(asset_url('assets/js/operateur-hub.js')) ?>" defer></script>
