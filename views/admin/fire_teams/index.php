<?php
declare(strict_types=1);

/** @var list<array<string,mixed>> $fireTeams */
/** @var string $fireTeamsTab */
/** @var array<int,string> $fireTeamsMaps */
/** @var bool $fireTeamsReady */
/** @var bool $fireTeamsIncludeDissolved */
/** @var array{total:int,active:int} $fireTeamsStats */

$teams = is_array($fireTeams ?? null) ? $fireTeams : [];
$tab = (string) ($fireTeamsTab ?? 'mission');
$maps = is_array($fireTeamsMaps ?? null) ? $fireTeamsMaps : [];
$ready = !empty($fireTeamsReady);
$includeDissolved = !empty($fireTeamsIncludeDissolved);
$stats = is_array($fireTeamsStats ?? null) ? $fireTeamsStats : [];
$flashSuccess = \App\Core\Session::getFlash('success');
$flashError = \App\Core\Session::getFlash('error');

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

$kindLabel = static function (string $kind): string {
    return $kind === 'permanent' ? 'Organigramme' : 'Mission';
};

$statusLabel = static function (array $t): string {
    if (!empty($t['deleted_at'])) {
        return 'Retirée';
    }
    if (!empty($t['dissolved_at'])) {
        return 'Dissoute';
    }

    return 'Active';
};

$emptyCopy = match ($tab) {
    'organigramme' => 'Aucune équipe rattachée à l’organigramme pour le moment. Créez-en une pour structurer durablement vos unités.',
    'toutes' => 'Aucune équipe de feu enregistrée. Commencez par une équipe de mission ou une équipe d’organigramme.',
    default => 'Aucune équipe de mission dans cette vue. Constituez votre première équipe pour la carte tactique.',
};

$createMissionUrl = url('back-office/atak/fire-teams/create?type=mission');
$createOrgaUrl = url('back-office/atak/fire-teams/create?type=organigramme');
?>
<link href="<?= $h(asset_url('assets/css/back-office-fire-teams.css')) ?>" rel="stylesheet">
<div class="bo-ft">
    <div class="bo-ft__frame">
        <header class="bo-ft__hero">
            <div class="bo-ft__hero-top">
                <div>
                    <p class="bo-ft__eyebrow">Tactique · ATAK</p>
                    <h1 class="bo-ft__title">Équipes de feu</h1>
                    <p class="bo-ft__lead">
                        Constituez des équipes pour une mission cartographique, ou rattachez-les durablement à une unité de l’organigramme.
                        Distinct des équipes RH classiques et de l’appui-feu.
                    </p>
                </div>
                <div class="bo-ft__actions" role="group" aria-label="Actions équipes de feu">
                    <a href="<?= $h($createMissionUrl) ?>" class="bo-ft__btn bo-ft__btn--primary">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/><circle cx="12" cy="12" r="9" opacity=".35"/></svg>
                        <span>Nouvelle mission</span>
                    </a>
                    <a href="<?= $h($createOrgaUrl) ?>" class="bo-ft__btn bo-ft__btn--secondary">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/><path stroke-linecap="round" d="M8 4.5v15M16 4.5v15" opacity=".45"/></svg>
                        <span>Nouvelle organigramme</span>
                    </a>
                    <a href="<?= $h(url('back-office/atak/operateurs')) ?>" class="bo-ft__btn bo-ft__btn--ghost">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.5 20.25a7.5 7.5 0 0 1 15 0"/><path stroke-linecap="round" d="M19.5 8.25v3m0 0v3m0-3h3m-3 0h-3" opacity=".7"/></svg>
                        <span>Effectifs en liaison</span>
                    </a>
                    <a href="<?= $h(url('admin/atak-config')) ?>" class="bo-ft__btn bo-ft__btn--ghost">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.174.1.331.24.46.41l1.12.01c.54.005.99.45.99.99v2.592c0 .54-.45.985-.99.99l-1.12.01a1.6 1.6 0 0 0-.46.41c-.332.184-.582.496-.645.87l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281a1.17 1.17 0 0 0-.645-.87 1.6 1.6 0 0 0-.46-.41l-1.12-.01c-.54-.005-.99-.45-.99-.99V8.502c0-.54.45-.985.99-.99l1.12-.01c.17-.002.33-.12.46-.41.332-.184.582-.496.645-.87l.213-1.28Z"/><circle cx="12" cy="12" r="2.6"/></svg>
                        <span>Config ATAK</span>
                    </a>
                </div>
            </div>

            <div class="bo-ft__kpis" aria-label="Synthèse">
                <div class="bo-ft__kpi">
                    <p class="bo-ft__kpi-label">Liste affichée</p>
                    <p class="bo-ft__kpi-value"><?= (int) ($stats['total'] ?? count($teams)) ?></p>
                </div>
                <div class="bo-ft__kpi bo-ft__kpi--accent">
                    <p class="bo-ft__kpi-label">Actives</p>
                    <p class="bo-ft__kpi-value"><?= (int) ($stats['active'] ?? 0) ?></p>
                </div>
                <div class="bo-ft__kpi">
                    <p class="bo-ft__kpi-label">Raccourcis</p>
                    <p class="bo-ft__kpi-links">
                        <a href="<?= $h(url('back-office/teams')) ?>">Équipes ORBAT</a>
                        <span class="bo-ft__kpi-sep" aria-hidden="true">·</span>
                        <a href="<?= $h(url('orbat')) ?>">Organigramme</a>
                    </p>
                </div>
            </div>
        </header>

        <?php if ($flashSuccess): ?>
            <p class="bo-ft__flash bo-ft__flash--ok" role="status"><?= $h($flashSuccess) ?></p>
        <?php endif; ?>
        <?php if ($flashError): ?>
            <p class="bo-ft__flash bo-ft__flash--err" role="alert"><?= $h($flashError) ?></p>
        <?php endif; ?>

        <?php if (!$ready): ?>
            <div class="bo-ft__flash bo-ft__flash--warn" role="status">
                Les tables des équipes de feu ne sont pas encore créées. Un administrateur plateforme doit exécuter les migrations
                (<strong>run-migrations.php</strong> ou l’écran de migrations).
            </div>
        <?php endif; ?>

        <nav class="bo-ft__filters" aria-label="Filtres des équipes de feu">
            <?php
            $tabs = [
                'mission' => [
                    'label' => 'De mission',
                    'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 6.75V4.5A1.5 1.5 0 0 1 10.5 3h3A1.5 1.5 0 0 1 15 4.5v2.25M4.5 9h15l-1.2 10.2A1.5 1.5 0 0 1 16.81 20.5H7.19a1.5 1.5 0 0 1-1.49-1.3L4.5 9Z"/></svg>',
                ],
                'organigramme' => [
                    'label' => 'Organigramme',
                    'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 7.5h9v3h-9v-3Zm3.75 3v3.75m-5.25 0h10.5v3.75H6v-3.75Z"/></svg>',
                ],
                'toutes' => [
                    'label' => 'Toutes',
                    'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25H12"/></svg>',
                ],
            ];
            foreach ($tabs as $key => $meta):
                $href = url('back-office/atak/fire-teams?vue=' . $key . ($includeDissolved ? '&inclure_dissoutes=1' : ''));
                $active = $tab === $key;
                ?>
                <a href="<?= $h($href) ?>"
                   class="bo-ft__tab<?= $active ? ' is-active' : '' ?>"
                   <?= $active ? 'aria-current="page"' : '' ?>>
                    <?= $meta['icon'] ?>
                    <span><?= $h($meta['label']) ?></span>
                </a>
            <?php endforeach; ?>
            <a href="<?= $h(url('back-office/atak/fire-teams?vue=' . $tab . ($includeDissolved ? '' : '&inclure_dissoutes=1'))) ?>"
               class="bo-ft__tab bo-ft__tab--toggle<?= $includeDissolved ? ' is-on' : '' ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88"/></svg>
                <span><?= $includeDissolved ? 'Masquer les dissoutes' : 'Inclure les dissoutes' ?></span>
            </a>
        </nav>

        <section class="bo-ft__panel" aria-label="Liste des équipes de feu">
            <?php if ($teams === []): ?>
                <div class="bo-ft__empty">
                    <div class="bo-ft__empty-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.547 3.199a3.001 3.001 0 0 1-.547-3.199m0 0a9.06 9.06 0 0 0-3.741-.479m-4.017.479a9.094 9.094 0 0 1-3.741-.479 3 3 0 0 1 4.682-2.72m-1.04 3.199c.24-.681.54-1.31.894-1.877M15 11.25a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6.75 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-18 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z"/>
                        </svg>
                    </div>
                    <h2 class="bo-ft__empty-title">Aucune équipe de feu dans cette vue</h2>
                    <p class="bo-ft__empty-text"><?= $h($emptyCopy) ?></p>
                    <div class="bo-ft__empty-actions">
                        <?php if ($tab === 'organigramme'): ?>
                            <a href="<?= $h($createOrgaUrl) ?>" class="bo-ft__btn bo-ft__btn--primary">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                                <span>Créer la première équipe</span>
                            </a>
                        <?php else: ?>
                            <a href="<?= $h($createMissionUrl) ?>" class="bo-ft__btn bo-ft__btn--primary">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                                <span>Créer la première équipe</span>
                            </a>
                            <?php if ($tab === 'toutes'): ?>
                                <a href="<?= $h($createOrgaUrl) ?>" class="bo-ft__btn bo-ft__btn--secondary">
                                    <span>Équipe d’organigramme</span>
                                </a>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="bo-ft__table-wrap">
                    <table class="bo-ft__table">
                        <thead>
                            <tr>
                                <th scope="col">Équipe</th>
                                <th scope="col">Portée</th>
                                <th scope="col">Rattachement</th>
                                <th scope="col">Effectif</th>
                                <th scope="col">État</th>
                                <th scope="col">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($teams as $t):
                                $tid = (int) ($t['id'] ?? 0);
                                $color = (string) ($t['color'] ?? '#2563EB');
                                $kind = (string) ($t['kind'] ?? 'ephemeral');
                                $mapId = isset($t['map_id']) ? (int) $t['map_id'] : 0;
                                $attach = $kind === 'permanent'
                                    ? ((string) ($t['unit_name'] ?? '') !== '' ? (string) $t['unit_name'] : 'Sans unité liée')
                                    : ($maps[$mapId] ?? ('Carte #' . $mapId));
                                if ($kind === 'ephemeral' && !empty($t['mission_key'])) {
                                    $attach .= ' · ' . (string) $t['mission_key'];
                                }
                                $st = $statusLabel($t);
                                ?>
                                <tr>
                                    <td>
                                        <div class="bo-ft__team">
                                            <span class="bo-ft__dot" style="background:<?= $h($color) ?>" aria-hidden="true"></span>
                                            <span class="bo-ft__team-name"><?= $h($t['label'] ?? '') ?></span>
                                        </div>
                                    </td>
                                    <td><?= $h($kindLabel($kind)) ?></td>
                                    <td><?= $h($attach) ?></td>
                                    <td><?= (int) ($t['member_count'] ?? 0) ?></td>
                                    <td>
                                        <span class="bo-ft__badge <?= $st === 'Active' ? 'bo-ft__badge--ok' : 'bo-ft__badge--muted' ?>">
                                            <?= $h($st) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="bo-ft__row-actions">
                                            <a class="bo-ft__mini" href="<?= $h(url('back-office/atak/fire-teams/' . $tid)) ?>">Ouvrir</a>
                                            <?php if (!empty($t['is_active'])): ?>
                                                <form method="post" action="<?= $h(url('back-office/atak/fire-teams/' . $tid . '/dissolve')) ?>" onsubmit="return confirm('Dissoudre cette équipe de feu ?');">
                                                    <input type="hidden" name="_csrf_token" value="<?= $h(\App\Core\Csrf::token()) ?>">
                                                    <button type="submit" class="bo-ft__mini bo-ft__mini--warn">Dissoudre</button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </div>
</div>
