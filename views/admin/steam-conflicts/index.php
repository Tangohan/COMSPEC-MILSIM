<?php
declare(strict_types=1);

use App\Services\Account\SteamConflictService;
use App\Support\UtcDateTime;

$cases = is_array($cases ?? null) ? $cases : [];
$onlySteam = (string) ($onlySteam ?? '');
$tenantNames = is_array($tenantNames ?? null) ? $tenantNames : [];
$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$fmtDate = static function (mixed $raw): string {
    $ts = UtcDateTime::timestamp($raw);

    return $ts !== null ? date('d/m/Y H:i', $ts) : '—';
};
$states = [
    SteamConflictService::STATE_PENDING => ['En attente du choix du joueur', 'amber', 'Les joueurs concernés voient la page de choix à chaque visite du site tant que l’un d’eux n’a pas choisi.'],
    SteamConflictService::STATE_REPORTED => ['Signalé : à examiner', 'rose', 'Un joueur ne reconnaît pas l’autre compte. Son Steam est mis de côté (rien n’est supprimé) en attendant votre décision.'],
    SteamConflictService::STATE_NONE => ['Réglé', 'mint', 'Ce Steam n’est plus porté que par une seule personne.'],
];
$statuses = ['active' => 'Actif', 'suspended' => 'Suspendu', 'banned' => 'Banni', 'pending_verification' => 'E-mail à vérifier', 'disabled' => 'Désactivé'];
$reportLabels = ['open' => 'Ouvert', 'restored' => 'Steam rendu au déclarant', 'closed' => 'Classé', 'resolved' => 'Réglé par la preuve Steam'];
?>
<div class="pa paf">
    <div class="pa__frame">
        <header class="pa-hero">
            <p class="pa-crumb">
                <a class="paf-link" href="<?= $h(url('admin')) ?>">Administration du site</a>
                <span aria-hidden="true"> / </span>
                <a class="paf-link" href="<?= $h(url('admin/parc-atak')) ?>">Parc ATAK des joueurs</a>
                <span aria-hidden="true"> / </span>
                Steam en double
            </p>
            <div class="pa-hero__row">
                <div>
                    <p class="pa-hero__kicker">Comptes des joueurs</p>
                    <h1 class="pa-hero__title">Steam en double</h1>
                    <p class="pa-hero__lead">Un même compte Steam relié aux comptes Athena de plusieurs personnes : le jeu refuse alors la connexion par Steam. Le joueur qui prouve posséder ce Steam (connexion Steam) le garde ; l’administration ne le retire jamais d’un compte à sa place.</p>
                    <?php if ($onlySteam !== ''): ?>
                    <div class="pa-hero__actions"><a class="pa-btn pa-btn--ghost" href="<?= $h(url('admin/steam-doublons')) ?>">Voir tous les Steam en double</a></div>
                    <?php endif; ?>
                </div>
            </div>
        </header>

        <?php if ($flash = \App\Core\Session::getFlash('success')): ?>
            <p class="pa-flash pa-flash--ok" role="status"><?= $h((string) $flash) ?></p>
        <?php endif; ?>
        <?php if ($flash = \App\Core\Session::getFlash('error')): ?>
            <p class="pa-flash pa-flash--err" role="alert"><?= $h((string) $flash) ?></p>
        <?php endif; ?>
        <?php if (empty($tablesReady)): ?>
            <p class="pa-flash pa-flash--err" role="alert">La liste n’a pas pu être chargée : lancez les mises à jour de la base (étape « Steam en double »).</p>
        <?php endif; ?>

        <?php if ($cases === []): ?>
        <section class="pa-card"><p class="paf-hint">Aucun Steam en double ni signalement en cours.</p></section>
        <?php endif; ?>

        <?php foreach ($cases as $case): ?>
        <?php $st = $states[$case['state']] ?? $states[SteamConflictService::STATE_NONE]; ?>
        <section class="pa-card" id="steam-<?= $h($case['steam_id']) ?>">
            <p class="pa-card__kicker">Steam <span class="paf-mono"><?= $h($case['steam_id']) ?></span></p>
            <h2 class="pa-card__title"><span class="pa-pill pa-pill--<?= $h($st[1]) ?>"><?= $h($st[0]) ?></span></h2>
            <p class="paf-hint"><?= $h($st[2]) ?></p>

            <h3 class="paf-sub">Fiches qui portent ce Steam</h3>
            <?php if ($case['holders'] === []): ?>
            <p class="paf-hint">Aucune : le Steam a été retiré de toutes les fiches.</p>
            <?php else: ?>
            <dl class="paf-dl">
                <?php foreach ($case['holders'] as $o): ?>
                <div>
                    <dt><?= $h($o['callsign'] !== '' ? $o['callsign'] : 'Sans indicatif') ?> · <?= $h((string) ($tenantNames[$o['tenant_id']] ?? '')) ?></dt>
                    <dd>
                        <?= $h($o['email']) ?> · <?= $h($statuses[strtolower($o['status'])] ?? 'Autre état') ?> · créé le <?= $h($fmtDate($o['created_at'])) ?>
                        · <a class="paf-link" href="<?= $h(url('admin/users/person') . '?user_id=' . $o['id']) ?>">Dossier</a>
                    </dd>
                </div>
                <?php endforeach; ?>
            </dl>
            <?php endif; ?>

            <?php if ($case['reports'] !== []): ?>
            <h3 class="paf-sub">Signalements des joueurs</h3>
            <?php foreach ($case['reports'] as $r): ?>
            <div class="paf-hint">
                <p>Le <?= $h($fmtDate($r['created_at'] ?? null)) ?>, le compte #<?= (int) $r['reporter_user_id'] ?> ne reconnaît pas l’autre compte. État : <?= $h($reportLabels[(string) $r['status']] ?? 'Autre') ?>.</p>
                <?php if ((string) $r['status'] === 'open'): ?>
                <form method="post" action="<?= $h(url('admin/steam-doublons/decider')) ?>" style="display:inline-flex;gap:.5rem;flex-wrap:wrap">
                    <?= \App\Core\Csrf::field() ?>
                    <input type="hidden" name="report_id" value="<?= (int) $r['id'] ?>">
                    <input type="hidden" name="steam" value="<?= $h($case['steam_id']) ?>">
                    <button type="submit" name="decision" value="restore" class="pa-btn pa-btn--sm pa-btn--line">Rendre le Steam au déclarant</button>
                    <button type="submit" name="decision" value="close" class="pa-btn pa-btn--sm pa-btn--mint">Classer : Steam retiré du déclarant</button>
                </form>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </section>
        <?php endforeach; ?>
    </div>
</div>
