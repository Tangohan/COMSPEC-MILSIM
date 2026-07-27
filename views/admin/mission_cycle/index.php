<?php
declare(strict_types=1);

/**
 * @var list<array<string,mixed>> $missionCycleList
 * @var array<string,mixed>|null $missionCycleFocus
 * @var list<array{mapId:int,label:string}> $missionCycleWorkspaces
 */

$list = is_array($missionCycleList ?? null) ? $missionCycleList : [];
$focus = is_array($missionCycleFocus ?? null) ? $missionCycleFocus : null;
$workspaces = is_array($missionCycleWorkspaces ?? null) ? $missionCycleWorkspaces : [];
$flashSuccess = \App\Core\Session::getFlash('success');
$flashError = \App\Core\Session::getFlash('error');
$csrf = \App\Core\Csrf::token();

$statusClass = static function (string $status): string {
    return match ($status) {
        'en_cours' => 'is-live',
        'cloturee' => 'is-done',
        default => 'is-prep',
    };
};

$phaseActive = static function (?array $m, string $phase): bool {
    if ($m === null) {
        return $phase === 'prep';
    }
    $s = (string) ($m['status'] ?? 'preparation');
    return match ($phase) {
        'prep' => $s === 'preparation',
        'exec' => $s === 'en_cours',
        'aar' => $s === 'cloturee',
        default => false,
    };
};
?>
<link href="<?= htmlspecialchars(asset_url('assets/css/back-office-mission-cycle.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">

<div class="bo-mcycle" data-bo-mcycle>
    <header class="bo-mcycle__hero">
        <div class="bo-mcycle__hero-inner">
            <div>
                <p class="bo-mcycle__eyebrow">Poste de commandement</p>
                <h1 class="bo-mcycle__title">Cycle de mission</h1>
                <p class="bo-mcycle__lead">
                    Préparez le briefing, ouvrez la mission pour l’exécution sur la carte, puis clôturez pour figer
                    la relecture et le bilan après-action.
                </p>
            </div>
            <div class="bo-mcycle__hero-actions">
                <a href="#bo-mcycle-create" class="bo-mcycle__btn bo-mcycle__btn--solid">Créer une mission</a>
                <a href="<?= htmlspecialchars(url('back-office/atak/briefing-slides'), ENT_QUOTES, 'UTF-8') ?>" class="bo-mcycle__btn bo-mcycle__btn--ghost">Diapositives de briefing</a>
                <a href="<?= htmlspecialchars(url('tacmap'), ENT_QUOTES, 'UTF-8') ?>" class="bo-mcycle__btn bo-mcycle__btn--ghost">Carte tactique</a>
                <a href="<?= htmlspecialchars(url('atak'), ENT_QUOTES, 'UTF-8') ?>" class="bo-mcycle__btn bo-mcycle__btn--ghost">Poste ATAK</a>
            </div>
        </div>
    </header>

    <div class="bo-mcycle__deck">
        <?php if ($flashSuccess): ?>
            <div class="bo-mcycle__flash bo-mcycle__flash--ok" role="status"><?= htmlspecialchars((string) $flashSuccess, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <?php if ($flashError): ?>
            <div class="bo-mcycle__flash bo-mcycle__flash--err" role="alert"><?= htmlspecialchars((string) $flashError, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <section class="bo-mcycle__phases" aria-label="Les trois phases du cycle">
            <article class="bo-mcycle__phase <?= $phaseActive($focus, 'prep') ? 'is-current' : '' ?>">
                <p class="bo-mcycle__phase-num">1</p>
                <h2 class="bo-mcycle__phase-title">Briefing</h2>
                <p class="bo-mcycle__phase-text">Ordre d’opérations et diapositives de briefing pour les opérateurs.</p>
                <a class="bo-mcycle__phase-link" href="<?= htmlspecialchars(url('back-office/atak/briefing-slides'), ENT_QUOTES, 'UTF-8') ?>">Préparer le briefing</a>
            </article>
            <article class="bo-mcycle__phase <?= $phaseActive($focus, 'exec') ? 'is-current' : '' ?>">
                <p class="bo-mcycle__phase-num">2</p>
                <h2 class="bo-mcycle__phase-title">Exécution</h2>
                <p class="bo-mcycle__phase-text">Carte live : ordres, situation, urgences médicales et repères.</p>
                <a class="bo-mcycle__phase-link" href="<?= htmlspecialchars(url('tacmap'), ENT_QUOTES, 'UTF-8') ?>">Ouvrir la carte</a>
            </article>
            <article class="bo-mcycle__phase <?= $phaseActive($focus, 'aar') ? 'is-current' : '' ?>">
                <p class="bo-mcycle__phase-num">3</p>
                <h2 class="bo-mcycle__phase-title">Après-action</h2>
                <p class="bo-mcycle__phase-text">Relecture figée sur la fenêtre d’exécution et bilan de mission.</p>
                <?php if ($focus && ($focus['status'] ?? '') === 'cloturee' && !empty($focus['links']['replay'])): ?>
                    <a class="bo-mcycle__phase-link" href="<?= htmlspecialchars((string) $focus['links']['replay'], ENT_QUOTES, 'UTF-8') ?>">Voir la relecture</a>
                <?php else: ?>
                    <span class="bo-mcycle__phase-muted">Disponible après clôture</span>
                <?php endif; ?>
            </article>
        </section>

        <?php if ($focus): ?>
        <section class="bo-mcycle__panel" aria-labelledby="bo-mcycle-focus-title">
            <div class="bo-mcycle__panel-head">
                <div>
                    <p class="bo-mcycle__panel-eyebrow">Mission sélectionnée</p>
                    <h2 id="bo-mcycle-focus-title" class="bo-mcycle__panel-title"><?= htmlspecialchars((string) $focus['title'], ENT_QUOTES, 'UTF-8') ?></h2>
                </div>
                <span class="bo-mcycle__status <?= htmlspecialchars($statusClass((string) ($focus['status'] ?? '')), ENT_QUOTES, 'UTF-8') ?>">
                    <?= htmlspecialchars((string) ($focus['status_label'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                </span>
            </div>

            <dl class="bo-mcycle__meta">
                <div>
                    <dt>Carte</dt>
                    <dd><?php
                        $mapLabel = 'Carte ' . (int) ($focus['map_id'] ?? 1);
                        foreach ($workspaces as $w) {
                            if ((int) ($w['mapId'] ?? 0) === (int) ($focus['map_id'] ?? 0)) {
                                $mapLabel = (string) ($w['label'] ?? $mapLabel);
                                break;
                            }
                        }
                        echo htmlspecialchars($mapLabel, ENT_QUOTES, 'UTF-8');
                    ?></dd>
                </div>
                <div>
                    <dt>Début</dt>
                    <dd><?= htmlspecialchars((string) ($focus['started_at'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></dd>
                </div>
                <div>
                    <dt>Fin</dt>
                    <dd><?= htmlspecialchars((string) ($focus['ended_at'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></dd>
                </div>
            </dl>

            <div class="bo-mcycle__actions">
                <?php if (($focus['status'] ?? '') === 'preparation'): ?>
                    <form method="post" action="<?= htmlspecialchars(url('back-office/atak/cycle-mission/' . (int) $focus['id'] . '/ouvrir'), ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                        <button type="submit" class="bo-mcycle__btn bo-mcycle__btn--solid">Ouvrir la mission</button>
                    </form>
                <?php elseif (($focus['status'] ?? '') === 'en_cours'): ?>
                    <form method="post" action="<?= htmlspecialchars(url('back-office/atak/cycle-mission/' . (int) $focus['id'] . '/cloturer'), ENT_QUOTES, 'UTF-8') ?>" class="bo-mcycle__close-form">
                        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                        <label class="bo-mcycle__field">
                            <span>Note de clôture (facultatif)</span>
                            <textarea name="aar_summary" rows="3" maxlength="4000" placeholder="Points saillants pour le bilan après-action…"></textarea>
                        </label>
                        <button type="submit" class="bo-mcycle__btn bo-mcycle__btn--warn">Clôturer la mission</button>
                    </form>
                <?php else: ?>
                    <div class="bo-mcycle__aar-links">
                        <?php if (!empty($focus['links']['replay'])): ?>
                            <a class="bo-mcycle__btn bo-mcycle__btn--solid" href="<?= htmlspecialchars((string) $focus['links']['replay'], ENT_QUOTES, 'UTF-8') ?>">Relecture sur le poste ATAK</a>
                        <?php endif; ?>
                        <?php if (!empty($focus['links']['aar'])): ?>
                            <a class="bo-mcycle__btn bo-mcycle__btn--ghost" href="<?= htmlspecialchars((string) $focus['links']['aar'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">Bilan après-action</a>
                        <?php endif; ?>
                        <?php if (!empty($focus['aar_summary'])): ?>
                            <p class="bo-mcycle__note"><?= nl2br(htmlspecialchars((string) $focus['aar_summary'], ENT_QUOTES, 'UTF-8')) ?></p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>
        <?php endif; ?>

        <section class="bo-mcycle__panel" id="bo-mcycle-create" aria-labelledby="bo-mcycle-create-title">
            <h2 id="bo-mcycle-create-title" class="bo-mcycle__panel-title">Créer une mission</h2>
            <p class="bo-mcycle__panel-lead">La mission démarre en préparation. Ouvrez-la quand le briefing est prêt.</p>
            <form method="post" action="<?= htmlspecialchars(url('back-office/atak/cycle-mission'), ENT_QUOTES, 'UTF-8') ?>" class="bo-mcycle__create-form">
                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                <label class="bo-mcycle__field">
                    <span>Titre de la mission</span>
                    <input type="text" name="title" maxlength="200" required placeholder="Ex. Opération Aube — secteur nord">
                </label>
                <label class="bo-mcycle__field">
                    <span>Carte / serveur</span>
                    <select name="map_id" required>
                        <?php foreach ($workspaces as $w): ?>
                            <option value="<?= (int) ($w['mapId'] ?? 1) ?>"><?= htmlspecialchars((string) ($w['label'] ?? 'Carte'), ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <button type="submit" class="bo-mcycle__btn bo-mcycle__btn--solid">Créer</button>
            </form>
        </section>

        <section class="bo-mcycle__panel" aria-labelledby="bo-mcycle-list-title">
            <h2 id="bo-mcycle-list-title" class="bo-mcycle__panel-title">Missions récentes</h2>
            <?php if ($list === []): ?>
                <p class="bo-mcycle__empty">Aucune mission pour le moment. Créez la première ci-dessus.</p>
            <?php else: ?>
                <ul class="bo-mcycle__list">
                    <?php foreach ($list as $m): ?>
                        <li class="bo-mcycle__list-item <?= ($focus && (int) $focus['id'] === (int) $m['id']) ? 'is-selected' : '' ?>">
                            <a href="<?= htmlspecialchars(url('back-office/atak/cycle-mission') . '?mission=' . (int) $m['id'], ENT_QUOTES, 'UTF-8') ?>">
                                <span class="bo-mcycle__list-title"><?= htmlspecialchars((string) $m['title'], ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="bo-mcycle__status <?= htmlspecialchars($statusClass((string) ($m['status'] ?? '')), ENT_QUOTES, 'UTF-8') ?>">
                                    <?= htmlspecialchars((string) ($m['status_label'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </div>
</div>
