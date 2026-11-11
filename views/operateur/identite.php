<?php

declare(strict_types=1);

/**
 * Mes identités (OperatorHubController::identities / documents) : les identités du membre et leurs papiers, dans le
 * gabarit et avec les composants du tableau de bord.
 *
 * @var string $idTab identites | documents
 * @var array<string, string> $idTabs
 * @var list<array<string, mixed>> $idIdentities
 * @var list<array<string, mixed>> $idDocuments
 * @var list<array<string, mixed>> $idCountries
 * @var list<array<string, mixed>> $idHistory
 * @var array<string, string>|null $idSync
 * @var array<string, string> $idProposal
 * @var int $idEditId
 * @var bool $idStoryAvailable champ « Histoire » disponible (étape 4450 passée)
 * @var array<int, list<array<string, string>>> $idMissingPapers papiers habituels que chaque identité n'a pas encore
 * @var bool $idUnavailable
 * @var bool $idSteamLinked
 * @var string|null $idManagerUrl
 * @var string $csrfToken
 * @var string|null $success
 * @var string|null $error
 */
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
require __DIR__ . '/_icons.php';
$idProposal = is_array($idProposal ?? null) ? $idProposal : [];
$idStoryAvailable = !empty($idStoryAvailable);
$idMissingPapers = is_array($idMissingPapers ?? null) ? $idMissingPapers : [];
$tabUrls = ['identites' => url('operateur/identite'), 'documents' => url('operateur/identite/documents')];
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
    return preg_match('/^(\d{4})-(\d{2})-(\d{2})/', (string) $iso, $m) === 1 ? $m[3] . '/' . $m[2] . '/' . $m[1] : '—';
};
// Fenêtre (élément dialog) : en-tête commun. Sans JavaScript, le contenu reste affiché dans la page.
$dialogOpen = static function (string $id, string $title, string $lead = '', bool $auto = false) use ($h, $opxIcon): string {
    return '<dialog class="opx-dialog" id="' . $h($id) . '" aria-labelledby="' . $h($id) . '-t"' . ($auto ? ' data-opx-autoopen' : '') . '>'
        . '<div class="opx-dialog__head"><div><h2 id="' . $h($id) . '-t">' . $h($title) . '</h2>' . ($lead !== '' ? '<p>' . $h($lead) . '</p>' : '') . '</div>'
        . '<button type="button" class="opx-dialog__close" data-opx-close aria-label="Fermer">' . $opxIcon('close', '') . '</button></div><div class="opx-dialog__body">';
};
$dialogClose = '</div></dialog>';
$historyLabels = [
    'op_identity.identity_create' => 'Identité créée', 'op_identity.identity_update' => 'Identité modifiée',
    'op_identity.identity_archive' => 'Identité archivée', 'op_identity.identity_restore' => 'Identité restaurée',
    'op_identity.identity_primary' => 'Personnage changé',
    'op_identity.photo' => 'Photo d’identité changée', 'op_identity.document_request' => 'Papier demandé',
    'op_identity.document_renew' => 'Renouvellement demandé',
    'id_member.document_lost' => 'Papier déclaré perdu', 'id_member.document_stolen' => 'Papier déclaré volé',
    'id_member.document_delete' => 'Demande annulée', 'id_member.sync_send_game' => 'Identité envoyée en jeu',
    'id_member.kit_save' => 'Papiers portés en jeu recomposés',
];
?>
<div class="opx opx--identity" data-opx-identity>
    <header class="opx-top">
        <div class="opx-top__inner">
            <div class="opx-top__copy">
                <h1 class="dash-hero__title">Mes identités<span class="dash-hero__title-dot" aria-hidden="true">.</span></h1>
                <p class="opx-top__lead">Qui vous êtes en jeu : votre personnage, vos identités d’emprunt et leurs papiers. <span class="cc-badge cc-badge--on-dark">Simulation fictive</span></p>
                <div class="opx-top__actions">
                    <a class="cc-btn cc-btn-ghost--on-dark" href="<?= $h(url('operateur')) ?>">Tous les outils</a>
                    <?php if ($idManagerUrl !== null): ?>
                        <a class="cc-btn cc-btn-ghost--on-dark" href="<?= $h($idManagerUrl) ?>">Papiers des membres</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>
    <nav class="opx-tabs" aria-label="Sections de mes identités">
        <div class="opx-tabs__inner">
            <?php foreach ($idTabs as $key => $label): ?>
                <a class="opx-tab<?= $key === $idTab ? ' is-active' : '' ?>" href="<?= $h($tabUrls[$key] ?? url('operateur/identite')) ?>"<?php if ($key === $idTab): ?> aria-current="page"<?php endif; ?>><?= $h($label) ?></a>
            <?php endforeach; ?>
        </div>
    </nav>

    <?php if ((is_string($success ?? null) && $success !== '') || (is_string($error ?? null) && $error !== '')): ?>
        <section class="dash-zone dash-zone--actions">
            <div class="dash-zone__inner dash-zone__inner--tight">
                <?php if (is_string($success ?? null) && $success !== ''): ?>
                    <p class="opx-flash opx-flash--ok" role="status"><?= $h($success) ?></p>
                <?php endif; ?>
                <?php if (is_string($error ?? null) && $error !== ''): ?>
                    <p class="opx-flash opx-flash--err" role="alert"><?= $h($error) ?></p>
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($idUnavailable): ?>
        <section class="dash-zone dash-zone--actions">
            <div class="dash-zone__inner">
                <div class="cc-empty">
                    <p><strong>Indisponible pour l’instant.</strong> Vos identités n’ont pas pu être lues. Réessayez dans un moment ; si cela dure, prévenez un responsable.</p>
                </div>
            </div>
        </section>
    <?php else: ?>
        <?php require __DIR__ . '/_tab_' . ($idTab === 'documents' ? 'documents' : 'identites') . '.php'; ?>

        <?php require __DIR__ . '/_photo_guide.php'; ?>

        <?php if ($idHistory !== []): ?>
            <section class="dash-zone" aria-labelledby="opx-history-title">
                <div class="dash-zone__inner">
                    <article class="cc-card">
                        <div class="cc-card__head">
                            <h2 id="opx-history-title" class="cc-card__title">Mes derniers changements</h2>
                        </div>
                        <ul class="opx-history">
                            <?php foreach ($idHistory as $e): ?>
                                <li>
                                    <span><?= $h($historyLabels[$e['action']] ?? 'Changement enregistré') ?><?= isset($e['after']['ref']) ? ' · ' . $h($e['after']['ref']) : '' ?></span>
                                    <time><?= $h($dateFr((string) $e['created_at'])) ?> <?= $h(substr((string) $e['created_at'], 11, 5)) ?> UTC</time>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </article>
                </div>
            </section>
        <?php endif; ?>
    <?php endif; ?>
</div>
