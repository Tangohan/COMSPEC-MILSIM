<?php
declare(strict_types=1);

use App\Services\Intel\IntelIntakeService;

/** @var bool $intakeReady */
/** @var list<array<string, mixed>> $intakeItems */
/** @var array{open: int, closed: int, mine: int, deleted: int} $intakeCounts */
/** @var string $intakeTab */
/** @var array{source: string, type: string, q: string, assignee: string} $intakeFilters */
/** @var array<string, string> $intakeTypes */
/** @var list<array{id: int, label: string}> $intakeMembers */

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$items = is_array($intakeItems ?? null) ? $intakeItems : [];
$counts = $intakeCounts ?? ['open' => 0, 'closed' => 0, 'mine' => 0, 'deleted' => 0];
$tab = (string) ($intakeTab ?? 'open');
$f = $intakeFilters ?? ['source' => '', 'type' => '', 'q' => '', 'assignee' => ''];
$members = $intakeMembers ?? [];

$link = static function (array $over) use ($f, $tab): string {
    $q = array_filter(array_merge(['etat' => $tab, 'source' => $f['source'], 'type' => $f['type'], 'q' => $f['q'], 'attribue' => $f['assignee']], $over), static fn ($v): bool => $v !== '' && $v !== null);

    return url('back-office/remontees' . ($q !== [] ? '?' . http_build_query($q) : ''));
};
$ago = static function (string $ts): string {
    $t = strtotime($ts);
    if ($t === false) {
        return '';
    }
    $d = time() - $t;

    return match (true) {
        $d < 90 => 'à l’instant',
        $d < 3600 => 'il y a ' . (int) round($d / 60) . ' min',
        $d < 86400 => 'il y a ' . (int) round($d / 3600) . ' h',
        $d < 86400 * 30 => 'il y a ' . (int) round($d / 86400) . ' j',
        default => 'le ' . date('d/m/Y', $t),
    };
};
$stateIcon = static fn (string $s): string => match ($s) {
    'a_traiter' => '<svg viewBox="0 0 16 16" aria-hidden="true"><circle cx="8" cy="8" r="6" fill="none" stroke="currentColor" stroke-width="1.6"/><circle cx="8" cy="8" r="1.6" fill="currentColor"/></svg>',
    'en_cours' => '<svg viewBox="0 0 16 16" aria-hidden="true"><circle cx="8" cy="8" r="6" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="M8 2a6 6 0 0 1 0 12z" fill="currentColor"/></svg>',
    'exploitee' => '<svg viewBox="0 0 16 16" aria-hidden="true"><circle cx="8" cy="8" r="6.8" fill="currentColor"/><path d="M5 8.2l2 2 4-4.2" fill="none" stroke="#fff" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>',
    'non_exploitable' => '<svg viewBox="0 0 16 16" aria-hidden="true"><circle cx="8" cy="8" r="6" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="M4.2 11.8l7.6-7.6" stroke="currentColor" stroke-width="1.6"/></svg>',
    default => '<svg viewBox="0 0 16 16" aria-hidden="true"><circle cx="8" cy="8" r="6.8" fill="currentColor"/><path d="M5.5 5.5l5 5m0-5l-5 5" stroke="#fff" stroke-width="1.6" stroke-linecap="round"/></svg>',
};
// Priorité du compte rendu ou urgence de la fiche ; routine et normal ne sont pas affichés.
$prioLabel = ['FLASH' => 'Flash', 'IMMEDIATE' => 'Immédiat', 'PRIORITY' => 'Prioritaire', 'critique' => 'Critique', 'urgent' => 'Urgent'];
?>
<div class="rmt">
    <div class="rmt__frame">
        <header class="rmt__hero">
            <p class="rmt__kicker">Renseignement · Suivi</p>
            <h1 class="rmt__title">Remontées</h1>
            <p class="rmt__lead">Tous les comptes rendus et toutes les fiches de renseignement (FRS : FRM, FRO, FRC, FRA, FRT) arrivés du terrain, du téléphone ATAK et du bureau SSE. Ouvrez une remontée pour la commenter, l’attribuer, changer son type, corriger ses données, la caviarder ou la marquer exploitée.</p>
        </header>

        <?php if (empty($intakeReady)): ?>
            <p class="rmt__notice">Les tables du suivi des remontées ne sont pas encore créées : un administrateur plateforme doit lancer <strong>run-migrations.php</strong>.</p>
        <?php else: ?>

        <form class="rmt__filters" method="get" action="<?= $h(url('back-office/remontees')) ?>" role="search">
            <input type="hidden" name="etat" value="<?= $h($tab) ?>">
            <label class="rmt__search">
                <span class="rmt__sr">Rechercher</span>
                <svg viewBox="0 0 16 16" aria-hidden="true"><circle cx="7" cy="7" r="4.6" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="M10.5 10.5L14 14" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
                <input type="search" name="q" value="<?= $h($f['q']) ?>" placeholder="Référence, texte, indicatif…">
            </label>
            <label class="rmt__select"><span class="rmt__sr">Source</span>
                <select name="source">
                    <option value="">Toutes les sources</option>
                    <?php foreach (IntelIntakeService::SOURCES as $k => $label): ?>
                        <option value="<?= $h($k) ?>"<?= $f['source'] === $k ? ' selected' : '' ?>><?= $h($label) ?>s</option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="rmt__select"><span class="rmt__sr">Type</span>
                <select name="type">
                    <option value="">Tous les types</option>
                    <?php foreach ($intakeTypes as $code => $label): ?>
                        <option value="<?= $h($code) ?>"<?= $f['type'] === $code ? ' selected' : '' ?>><?= $h($code . ' · ' . $label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="rmt__select"><span class="rmt__sr">Attribution</span>
                <select name="attribue">
                    <option value="">Attribuée à tous</option>
                    <option value="none"<?= $f['assignee'] === 'none' ? ' selected' : '' ?>>Non attribuée</option>
                    <?php foreach ($members as $m): ?>
                        <option value="<?= (int) $m['id'] ?>"<?= $f['assignee'] === (string) $m['id'] ? ' selected' : '' ?>><?= $h($m['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button type="submit" class="rmt__btn">Filtrer</button>
            <?php if ($f['q'] !== '' || $f['source'] !== '' || $f['type'] !== '' || $f['assignee'] !== ''): ?>
                <a class="rmt__clear" href="<?= $h(url('back-office/remontees?etat=' . $tab)) ?>">Effacer les filtres</a>
            <?php endif; ?>
        </form>

        <section class="rmt__box" aria-label="Remontées">
            <nav class="rmt__tabs" aria-label="État">
                <?php foreach ([
                    'open' => ['Ouvertes', $counts['open'], 'a_traiter'],
                    'closed' => ['Traitées', $counts['closed'], 'exploitee'],
                    'mine' => ['Attribuées à moi', $counts['mine'], 'en_cours'],
                    'deleted' => ['Supprimées', $counts['deleted'], 'close'],
                ] as $k => [$label, $n, $icon]): ?>
                    <a href="<?= $h($link(['etat' => $k])) ?>" class="rmt__tab rmt__tab--<?= $h($k) ?><?= $tab === $k ? ' is-active' : '' ?>"<?= $tab === $k ? ' aria-current="page"' : '' ?>>
                        <span class="rmt__state rmt__state--<?= $h($icon) ?>"><?= $stateIcon($icon) ?></span>
                        <strong><?= (int) $n ?></strong> <?= $h($label) ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <?php if ($items === []): ?>
                <div class="rmt__empty">
                    <p class="rmt__empty-title">Rien ici.</p>
                    <p>Les comptes rendus arrivent de l’app Comptes rendus du téléphone ATAK, les fiches de l’app FRS et du bureau SSE.</p>
                </div>
            <?php else: ?>
                <ul class="rmt__list">
                    <?php foreach ($items as $it):
                        $href = url('back-office/remontees/' . $it['source'] . '/' . $it['id']);
                        $prio = $prioLabel[(string) $it['priority']] ?? '';
                    ?>
                        <li class="rmt__row">
                            <span class="rmt__state rmt__state--<?= $h($it['state']) ?>" title="<?= $h($it['state_label']) ?>"><?= $stateIcon((string) $it['state']) ?><span class="rmt__sr"><?= $h($it['state_label']) ?></span></span>
                            <div class="rmt__main">
                                <a class="rmt__link" href="<?= $h($href) ?>"><?= $h($it['title'] !== '' ? $it['title'] : $it['type_label']) ?></a>
                                <span class="rmt__label rmt__label--<?= $h($it['source']) ?>"><?= $h($it['type']) ?></span>
                                <?php if ($prio !== ''): ?><span class="rmt__label rmt__label--hot"><?= $h($prio) ?></span><?php endif; ?>
                                <?php if (!empty($it['redacted'])): ?><span class="rmt__label">Caviardée</span><?php endif; ?>
                                <p class="rmt__meta">
                                    <?= $h($it['ref']) ?> · <?= $h($it['type_label']) ?> · remontée <?= $h($ago((string) $it['at'])) ?> par <strong><?= $h($it['author']) ?></strong>
                                    · <?= $h($it['state_label']) ?>
                                </p>
                            </div>
                            <div class="rmt__side">
                                <?php if ($it['assignee_user_id'] !== null): ?>
                                    <span class="rmt__avatar" title="Attribuée à <?= $h($it['assignee_label']) ?>"><?= $h(mb_strtoupper(mb_substr((string) $it['assignee_label'], 0, 2))) ?></span>
                                <?php endif; ?>
                                <?php if ((int) $it['comments'] > 0): ?>
                                    <a class="rmt__comments" href="<?= $h($href) ?>#fil" title="<?= (int) $it['comments'] ?> commentaire(s)">
                                        <svg viewBox="0 0 16 16" aria-hidden="true"><path d="M2.5 3.5h11v7h-6l-3 2.5v-2.5h-2z" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/></svg><?= (int) $it['comments'] ?>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
        <?php endif; ?>
    </div>
</div>
