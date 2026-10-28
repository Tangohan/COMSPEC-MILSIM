<?php
declare(strict_types=1);

/** @var list<array<string,mixed>> $fireTeams */
/** @var string $fireTeamsTab */
/** @var array<int,string> $fireTeamsMaps */
/** @var bool $fireTeamsReady */
/** @var bool $fireTeamsIncludeDissolved */

$teams = is_array($fireTeams ?? null) ? $fireTeams : [];
$tab = (string) ($fireTeamsTab ?? 'mission');
$maps = is_array($fireTeamsMaps ?? null) ? $fireTeamsMaps : [];
$ready = !empty($fireTeamsReady);
$includeDissolved = !empty($fireTeamsIncludeDissolved);
$flashSuccess = \App\Core\Session::getFlash('success');
$flashError = \App\Core\Session::getFlash('error');

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

// Couleur d'équipe sûre pour un attribut style (#RRGGBB uniquement).
$safeColor = static function (mixed $c): string {
    $c = strtoupper(trim((string) $c));

    return preg_match('/^#[0-9A-F]{6}$/', $c) === 1 ? $c : '#64748B';
};

$memberName = static function (array $m): string {
    $cs = trim((string) ($m['effective_callsign'] ?? ''));
    if ($cs !== '') {
        return $cs;
    }
    $dn = trim((string) ($m['display_name'] ?? ''));

    return $dn !== '' ? $dn : 'Membre #' . (int) ($m['user_id'] ?? 0);
};

// Synthèse de la vue affichée : équipes actives, membres engagés, points d'attention.
$active = array_values(array_filter($teams, static fn (array $t): bool => !empty($t['is_active'])));
$engaged = [];
$noLeader = 0;
$empty = 0;
foreach ($active as $t) {
    $members = is_array($t['members'] ?? null) ? $t['members'] : [];
    if ($members === []) {
        $empty++;
        continue;
    }
    $hasLeader = false;
    foreach ($members as $m) {
        $engaged[(int) ($m['user_id'] ?? 0)] = true;
        $hasLeader = $hasLeader || ($m['role'] ?? '') === 'leader';
    }
    if (!$hasLeader) {
        $noLeader++;
    }
}
unset($engaged[0]);

$tabs = [
    'mission' => 'Mission',
    'organigramme' => 'Organigramme',
    'toutes' => 'Toutes',
];
$tabUrl = static fn (string $key, bool $dissolved): string => url('back-office/atak/fire-teams?vue=' . $key . ($dissolved ? '&inclure_dissoutes=1' : ''));

$emptyCopy = match ($tab) {
    'organigramme' => 'Aucune équipe rattachée à l’organigramme. Créez-en une pour structurer durablement une unité.',
    'toutes' => 'Aucune équipe de feu enregistrée pour le moment.',
    default => 'Aucune équipe de mission en cours. Constituez la première avant la session : elle apparaîtra sur la carte tactique et dans les téléphones ATAK.',
};

$createMissionUrl = url('back-office/atak/fire-teams/create?type=mission');
$createOrgaUrl = url('back-office/atak/fire-teams/create?type=organigramme');
?>
<div class="bo-ft">
    <div class="bo-ft__frame">
        <header class="bo-ft__hero">
            <div class="bo-ft__hero-main">
                <p class="bo-ft__kicker">Tactique · ATAK</p>
                <h1 class="bo-ft__title">Équipes de feu</h1>
                <p class="bo-ft__lead">Les binômes et trinômes qui jouent ensemble : une couleur, un chef, des membres. Les équipes de mission vivent le temps d’une session ; celles de l’organigramme restent attachées à une unité.</p>
            </div>
            <div class="bo-ft__hero-actions">
                <a href="<?= $h($createMissionUrl) ?>" class="bo-ft__btn bo-ft__btn--primary">
                    <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10.75 4.75a.75.75 0 0 0-1.5 0v4.5h-4.5a.75.75 0 0 0 0 1.5h4.5v4.5a.75.75 0 0 0 1.5 0v-4.5h4.5a.75.75 0 0 0 0-1.5h-4.5v-4.5Z"/></svg>
                    Équipe de mission
                </a>
                <a href="<?= $h($createOrgaUrl) ?>" class="bo-ft__btn">Équipe d’organigramme</a>
            </div>
        </header>

        <?php if ($flashSuccess): ?>
            <p class="bo-ft__flash bo-ft__flash--ok" role="status"><?= $h($flashSuccess) ?></p>
        <?php endif; ?>
        <?php if ($flashError): ?>
            <p class="bo-ft__flash bo-ft__flash--err" role="alert"><?= $h($flashError) ?></p>
        <?php endif; ?>
        <?php if (!$ready): ?>
            <p class="bo-ft__flash bo-ft__flash--warn" role="status">Les tables des équipes de feu ne sont pas encore créées : un administrateur plateforme doit lancer <strong>run-migrations.php</strong>.</p>
        <?php endif; ?>

        <div class="bo-ft__kpis" aria-label="Synthèse de la vue">
            <div class="bo-ft__kpi"><span>Équipes actives</span><strong><?= count($active) ?></strong></div>
            <div class="bo-ft__kpi"><span>Membres engagés</span><strong><?= count($engaged) ?></strong></div>
            <div class="bo-ft__kpi<?= $noLeader > 0 ? ' is-warn' : '' ?>"><span>Sans chef</span><strong><?= $noLeader ?></strong></div>
            <div class="bo-ft__kpi<?= $empty > 0 ? ' is-warn' : '' ?>"><span>Sans membre</span><strong><?= $empty ?></strong></div>
        </div>

        <div class="bo-ft__toolbar">
            <nav class="bo-ft__tabs" aria-label="Type d’équipe">
                <?php foreach ($tabs as $key => $label): ?>
                    <a href="<?= $h($tabUrl($key, $includeDissolved)) ?>" class="bo-ft__tab<?= $tab === $key ? ' is-active' : '' ?>"<?= $tab === $key ? ' aria-current="page"' : '' ?>><?= $h($label) ?></a>
                <?php endforeach; ?>
            </nav>
            <?php if ($teams !== []): ?>
                <label class="bo-ft__search">
                    <span class="bo-ft__sr">Rechercher une équipe ou un membre</span>
                    <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 1 0 3.4 9.83l3.13 3.14a.75.75 0 1 0 1.06-1.06l-3.13-3.14A5.5 5.5 0 0 0 9 3.5ZM5 9a4 4 0 1 1 8 0 4 4 0 0 1-8 0Z" clip-rule="evenodd"/></svg>
                    <input type="search" placeholder="Équipe, indicatif…" data-ft-search autocomplete="off">
                </label>
            <?php endif; ?>
            <a href="<?= $h($tabUrl($tab, !$includeDissolved)) ?>" class="bo-ft__toggle<?= $includeDissolved ? ' is-on' : '' ?>" role="switch" aria-checked="<?= $includeDissolved ? 'true' : 'false' ?>">
                <span class="bo-ft__toggle-track" aria-hidden="true"><span></span></span>
                Afficher les dissoutes
            </a>
        </div>

        <?php if ($teams === []): ?>
            <div class="bo-ft__empty">
                <p class="bo-ft__empty-title">Aucune équipe dans cette vue</p>
                <p><?= $h($emptyCopy) ?></p>
                <a href="<?= $h($tab === 'organigramme' ? $createOrgaUrl : $createMissionUrl) ?>" class="bo-ft__btn bo-ft__btn--primary">Créer une équipe</a>
            </div>
        <?php else: ?>
            <ul class="bo-ft__grid" aria-label="Équipes de feu">
                <?php foreach ($teams as $t): ?>
                    <?php
                    $tid = (int) ($t['id'] ?? 0);
                    $color = $safeColor($t['color'] ?? '');
                    $kind = (string) ($t['kind'] ?? 'ephemeral');
                    $isActive = !empty($t['is_active']);
                    $members = is_array($t['members'] ?? null) ? $t['members'] : [];
                    $leader = null;
                    $others = [];
                    foreach ($members as $m) {
                        if ($leader === null && ($m['role'] ?? '') === 'leader') {
                            $leader = $m;
                        } else {
                            $others[] = $m;
                        }
                    }
                    if ($kind === 'permanent') {
                        $attach = trim((string) ($t['unit_name'] ?? '')) !== '' ? (string) $t['unit_name'] : 'Sans unité liée';
                    } else {
                        $mapId = (int) ($t['map_id'] ?? 0);
                        $attach = $maps[$mapId] ?? ('Carte #' . $mapId);
                        if (trim((string) ($t['mission_key'] ?? '')) !== '') {
                            $attach .= ' · ' . trim((string) $t['mission_key']);
                        }
                    }
                    $status = !empty($t['deleted_at']) ? 'Retirée' : (!empty($t['dissolved_at']) ? 'Dissoute' : '');
                    $notes = trim((string) ($t['notes'] ?? ''));
                    $search = mb_strtolower(implode(' ', array_merge(
                        [(string) ($t['label'] ?? ''), $attach],
                        array_map($memberName, $members),
                        array_map(static fn (array $m): string => (string) ($m['display_name'] ?? ''), $members)
                    )));
                    $show = url('back-office/atak/fire-teams/' . $tid);
                    $visible = array_slice($others, 0, 8);
                    $more = count($others) - count($visible);
                    ?>
                    <li class="bo-ft__card<?= $isActive ? '' : ' is-off' ?>" style="--team: <?= $h($color) ?>" data-ft-card="<?= $h($search) ?>">
                        <div class="bo-ft__card-head">
                            <span class="bo-ft__swatch" aria-hidden="true"></span>
                            <div class="bo-ft__card-id">
                                <h2 class="bo-ft__card-title"><a href="<?= $h($show) ?>"><?= $h($t['label'] ?? 'Équipe') ?></a></h2>
                                <p class="bo-ft__card-sub"><?= $h($attach) ?></p>
                            </div>
                            <span class="bo-ft__chip"><?= $kind === 'permanent' ? 'Organigramme' : 'Mission' ?></span>
                        </div>

                        <?php if ($status !== ''): ?>
                            <p class="bo-ft__state"><?= $h($status) ?><?= !empty($t['dissolved_at']) && ($ts = strtotime((string) $t['dissolved_at'])) ? ' le ' . date('d/m/Y', $ts) : '' ?></p>
                        <?php endif; ?>

                        <div class="bo-ft__lead-row">
                            <span class="bo-ft__label">Chef</span>
                            <?php if ($leader !== null): ?>
                                <span class="bo-ft__leader"><?= $h($memberName($leader)) ?></span>
                            <?php elseif ($members !== []): ?>
                                <a href="<?= $h($show) ?>" class="bo-ft__alert">À désigner</a>
                            <?php else: ?>
                                <span class="bo-ft__muted">—</span>
                            <?php endif; ?>
                            <span class="bo-ft__count"><?= count($members) ?> membre<?= count($members) > 1 ? 's' : '' ?></span>
                        </div>

                        <?php if ($visible !== []): ?>
                            <ul class="bo-ft__members" aria-label="Membres">
                                <?php foreach ($visible as $m): ?>
                                    <li title="<?= $h(trim((string) ($m['display_name'] ?? ''))) ?>"><?= $h($memberName($m)) ?></li>
                                <?php endforeach; ?>
                                <?php if ($more > 0): ?><li class="is-more">+<?= $more ?></li><?php endif; ?>
                            </ul>
                        <?php elseif ($members === []): ?>
                            <p class="bo-ft__muted">Personne dans l’équipe pour l’instant.</p>
                        <?php endif; ?>

                        <?php if ($notes !== ''): ?>
                            <p class="bo-ft__notes" title="<?= $h($notes) ?>"><?= $h($notes) ?></p>
                        <?php endif; ?>

                        <div class="bo-ft__card-foot">
                            <a href="<?= $h($show) ?>" class="bo-ft__btn bo-ft__btn--sm"><?= $isActive ? 'Modifier' : 'Consulter' ?></a>
                            <?php if ($isActive): ?>
                                <form method="post" action="<?= $h(url('back-office/atak/fire-teams/' . $tid . '/dissolve')) ?>" onsubmit="return confirm('Dissoudre l’équipe « <?= $h(addslashes((string) ($t['label'] ?? ''))) ?> » ?');">
                                    <input type="hidden" name="_csrf_token" value="<?= $h(\App\Core\Csrf::token()) ?>">
                                    <button type="submit" class="bo-ft__btn bo-ft__btn--sm bo-ft__btn--quiet">Dissoudre</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
            <p class="bo-ft__empty bo-ft__empty--inline" data-ft-none hidden>Aucune équipe ne correspond à cette recherche.</p>
        <?php endif; ?>

        <p class="bo-ft__links">
            Voir aussi :
            <a href="<?= $h(url('back-office/atak/escouades')) ?>">Escouades en jeu</a> ·
            <a href="<?= $h(url('back-office/atak/operateurs')) ?>">Effectifs en liaison</a> ·
            <a href="<?= $h(url('back-office/teams')) ?>">Équipes ORBAT</a> ·
            <a href="<?= $h(url('back-office/ressources/atak-config')) ?>">Configuration ATAK</a>
        </p>
    </div>
</div>
<script>
(function () {
    var input = document.querySelector('[data-ft-search]');
    if (!input) {
        return;
    }
    var cards = document.querySelectorAll('[data-ft-card]');
    var none = document.querySelector('[data-ft-none]');
    input.addEventListener('input', function () {
        var q = input.value.trim().toLowerCase();
        var shown = 0;
        cards.forEach(function (c) {
            var ok = q === '' || (c.getAttribute('data-ft-card') || '').indexOf(q) !== -1;
            c.hidden = !ok;
            if (ok) {
                shown++;
            }
        });
        if (none) {
            none.hidden = shown > 0;
        }
    });
})();
</script>
