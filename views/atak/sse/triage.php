<?php
declare(strict_types=1);
/**
 * Boîte « À trier » du bureau SSE : tout ce qui attend une décision humaine, toutes files confondues, regroupé automatiquement.
 *
 * Les éléments de même type tiennent en une ligne dépliable ; chaque ligne nomme son sujet (masqué selon les droits) et sa raison.
 * Acquitter, mettre en veille, assigner, règles Cerbère-Tri : réservés à ceux qui traitent (SseAccessPolicy), tout passe par des
 * POST avec jeton (assets/js/sse-triage.js). La décision métier reste dans la page de chaque file : ouvrir une ligne y mène.
 *
 * @var array<string, mixed> $triage
 * @var list<array<string, mixed>> $triageItems
 * @var list<array<string, mixed>> $triageGroups
 * @var string $triageTab
 * @var string $triageView
 * @var array<string, string> $triageViews
 * @var string $triageMode
 * @var array<string, string> $triageModes
 * @var bool $canTreat
 * @var bool $canRules
 * @var list<array<string, mixed>> $rulesView
 * @var list<array<string, mixed>> $journal
 * @var array<int, string> $assignees
 * @var int $userId
 * @var array<string, string> $sourceTypes
 * @var string $lang
 */
use App\Services\Sse\SseTriageGrouping;
use App\Services\Sse\SseTriageInbox;
use App\Support\SseTriageBadges;
use App\Support\SseTriagePresets;

ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$lang = (string) ($lang ?? 'fr') === 'en' ? 'en' : 'fr';
$triage = is_array($triage ?? null) ? $triage : ['total' => 0, 'sources' => [], 'items' => [], 'counts' => ['open' => 0, 'mine' => 0, 'snoozed' => 0, 'acknowledged' => 0], 'degraded' => false];
$triageItems = is_array($triageItems ?? null) ? $triageItems : [];
$triageGroups = is_array($triageGroups ?? null) ? $triageGroups : [];
$triageTab = (string) ($triageTab ?? '');
$triageView = (string) ($triageView ?? 'a-traiter');
$triageViews = is_array($triageViews ?? null) ? $triageViews : ['a-traiter' => 'À traiter'];
$triageMode = (string) ($triageMode ?? 'type');
$triageModes = is_array($triageModes ?? null) ? $triageModes : SseTriageGrouping::MODES;
$canTreat = !empty($canTreat);
$canRules = !empty($canRules);
$rulesView = is_array($rulesView ?? null) ? $rulesView : [];
$journal = is_array($journal ?? null) ? $journal : [];
$assignees = is_array($assignees ?? null) ? $assignees : [];
$userId = (int) ($userId ?? 0);
$sourceTypes = is_array($sourceTypes ?? null) ? $sourceTypes : [];
$counts = $triage['counts'] ?? ['open' => 0, 'mine' => 0, 'snoozed' => 0, 'acknowledged' => 0];
$degraded = !empty($triage['degraded']);
$now = time();
$base = url('atak/sse/a-trier');
$sseExtraStyles = ($sseExtraStyles ?? '') . '<link rel="stylesheet" href="' . $h(asset_url('assets/css/sse-aside-badges.css')) . '"><link rel="stylesheet" href="' . $h(asset_url('assets/css/sse-triage-auto.css')) . '">';
$sseExtraScripts = ($sseExtraScripts ?? '') . '<script defer src="' . $h(asset_url('assets/js/sse-triage.js')) . '"></script>';
$query = static fn (array $over = []): string => $base . '?' . http_build_query(array_filter(array_merge(['vue' => $triageView === 'a-traiter' ? '' : $triageView, 'source' => $triageTab, 'groupe' => $triageMode === 'type' ? '' : $triageMode], $over), static fn ($v): bool => $v !== '' && $v !== null));
$when = static function (?string $utc): string {
    $ts = $utc !== null && $utc !== '' ? strtotime($utc . ' UTC') : false;

    return $ts === false ? '' : gmdate('d/m H:i', $ts) . 'Z';
};
$age = static function (string $at) use ($now, $lang): string {
    $ts = $at !== '' ? strtotime($at . ' UTC') : false;
    if ($ts === false || $ts > $now) {
        return '';
    }
    $s = $now - $ts;
    if ($s < 3600) {
        return max(1, intdiv($s, 60)) . ' min';
    }
    if ($s < 86400) {
        return intdiv($s, 3600) . ' h';
    }

    return intdiv($s, 86400) . ($lang === 'en' ? ' d' : ' j');
};
$ruleNames = [];
foreach ($rulesView as $rv) {
    $ruleNames[(int) $rv['id']] = (string) $rv['name'];
}
$sourceLabel = static fn (string $key): string => (string) ($triage['sources'][$key]['label'] ?? $key);
$statusLine = static function (array $it) use ($when, $userId, $assignees, $ruleNames): string {
    $t = $it['triage'];
    $bits = [];
    if ($t['status'] === 'snoozed') {
        $bits[] = 'En veille jusqu’au ' . $when($t['snoozed_until']);
    } elseif ($t['status'] === 'acknowledged') {
        $bits[] = ($t['rule_id'] > 0 ? 'Acquitté par Cerbère-Tri' : 'Acquitté') . ($t['acknowledged_at'] ? ' le ' . $when($t['acknowledged_at']) : '');
    }
    if ($t['assigned_to'] > 0) {
        $bits[] = $t['assigned_to'] === $userId ? 'Assigné à vous' : 'Assigné à ' . ($assignees[$t['assigned_to']] ?? 'un analyste');
    }
    if ($t['note'] !== '' && $t['rule_id'] === 0) {
        $bits[] = $t['note'];
    }

    return implode(' · ', $bits);
};
$gid = 0;

$row = function (array $it, bool $showType, string $groupReason = '') use ($h, $age, $when, $canTreat, $triageView, $triageMode, $statusLine, $userId, $sourceLabel): void {
    $subject = is_array($it['subject'] ?? null) ? $it['subject'] : null;
    $label = $subject !== null ? (string) $subject['label'] : (string) $it['title'];
    $open = $subject !== null && (string) ($subject['href'] ?? '') !== '' ? (string) $subject['href'] : (string) $it['href'];
    $people = $subject !== null ? array_values($subject['people'] ?? []) : [];
    $system = [\App\Services\Sse\SseTriageSubjects::MASKED_CASE, \App\Services\Sse\SseTriageSubjects::MASKED_MEMBER];
    $reason = (string) ($it['reason'] ?? '');
    $tone = (int) ($it['priority'] ?? 0) >= 2 ? 'bad' : (SseTriageInbox::ageDays((string) ($it['at'] ?? ''), time()) > SseTriageInbox::AMBER_DAYS ? 'warn' : 'neutral');
    $status = $statusLine($it);
    ?>
    <li class="tr-row tr-row--<?= $h($tone) ?>" data-tr-row data-key="<?= $h($it['key']) ?>" data-open="<?= $h($open) ?>" data-score="<?= (int) $it['score'] ?>">
        <?php if ($canTreat): ?>
            <label class="tr-check"><input type="checkbox" data-tr-select aria-label="Sélectionner : <?= $h($label) ?>"></label>
        <?php endif; ?>
        <div class="tr-main">
            <a class="tr-subject" href="<?= $h($open) ?>" data-tr-open<?= in_array($label, $system, true) || $subject === null ? '' : ' translate="no"' ?>><?= $h($label) ?></a>
            <?php if ($people !== []): ?>
                <p class="tr-line"><?php foreach ($people as $pi => $pn): ?><?= $pi > 0 ? ', ' : '' ?><span<?= in_array($pn, $system, true) ? '' : ' translate="no"' ?>><?= $h($pn) ?></span><?php endforeach; ?></p>
            <?php elseif ((string) ($it['meta'] ?? '') !== ''): ?>
                <p class="tr-line"><?= $h($it['meta']) ?></p>
            <?php endif; ?>
            <?php if ($reason !== '' && $reason !== $groupReason): ?><p class="tr-line tr-line--reason"><?= $h($reason) ?></p><?php endif; ?>
            <p class="tr-meta">
                <?php if ($showType): ?><span class="tr-chip"><?= $h($it['type_label']) ?></span><?php endif; ?>
                <?php if ((int) ($it['merged'] ?? 0) > 0): ?><span class="tr-chip"><?= (int) $it['merged'] + 1 ?> observations regroupées</span><?php endif; ?>
                <?php if ($status !== ''): ?><span><?= $h($status) ?></span><?php endif; ?>
            </p>
        </div>
        <span class="tr-age" title="Âge de l’élément"><?= $h($age((string) ($it['at'] ?? ''))) ?></span>
        <details class="tr-why">
            <summary title="Pourquoi cet ordre" aria-label="Pourquoi cet ordre : priorité <?= (int) $it['score'] ?>"><span class="tr-score<?= (int) $it['score'] >= 60 ? ' is-high' : ((int) $it['score'] >= 35 ? ' is-mid' : '') ?>" data-score="<?= (int) $it['score'] ?>"><?= (int) $it['score'] ?></span></summary>
            <div class="tr-pop">
                <strong>Pourquoi cet ordre</strong>
                <ul>
                    <?php foreach ($it['why'] as $w): ?>
                        <li><span><?= $h($w['label']) ?></span><b><?= (int) $w['points'] >= 0 ? '+' : '' ?><?= (int) $w['points'] ?></b></li>
                    <?php endforeach; ?>
                </ul>
                <p>Priorité <?= (int) $it['score'] ?> sur 100 : les plus hautes sont en tête.</p>
            </div>
        </details>
        <details class="tr-menu">
            <summary aria-label="Actions pour : <?= $h($label) ?>"><span>Actions</span></summary>
            <div class="tr-pop tr-pop--menu">
                <a class="tr-btn" href="<?= $h($open) ?>">Ouvrir</a>
                <?php if ($open !== (string) $it['href']): ?><a class="tr-btn" href="<?= $h($it['href']) ?>">Voir dans <?= $h($sourceLabel((string) $it['source'])) ?></a><?php endif; ?>
                <?php if ($canTreat): ?>
                    <?php if ($triageView === 'a-traiter' || $triageView === 'mes'): ?>
                        <button type="button" class="tr-btn" data-tr-act="acknowledge" data-keys="<?= $h($it['key']) ?>">Acquitter</button>
                        <button type="button" class="tr-btn" data-tr-act="snooze" data-days="7" data-keys="<?= $h($it['key']) ?>">Mettre en veille 7 jours</button>
                        <button type="button" class="tr-btn" data-tr-act="snooze" data-days="30" data-keys="<?= $h($it['key']) ?>">Mettre en veille 30 jours</button>
                    <?php else: ?>
                        <button type="button" class="tr-btn" data-tr-act="recall" data-keys="<?= $h($it['key']) ?>">Rappeler dans la liste</button>
                    <?php endif; ?>
                    <?php if ((int) $it['triage']['assigned_to'] === $userId): ?>
                        <button type="button" class="tr-btn" data-tr-act="assign" data-assignee="0" data-keys="<?= $h($it['key']) ?>">Retirer mon assignation</button>
                    <?php else: ?>
                        <button type="button" class="tr-btn" data-tr-act="assign" data-assignee="me" data-keys="<?= $h($it['key']) ?>">Me l’assigner</button>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </details>
    </li>
    <?php
};

$ruleFacts = static function (array $rule) use ($sourceLabel, $sourceTypes): string {
    $bits = [];
    if ((string) ($rule['source_key'] ?? '') !== '') {
        $bits[] = $sourceLabel((string) $rule['source_key']);
    }
    if ((string) ($rule['type_key'] ?? '') !== '') {
        $tk = (string) $rule['type_key'];
        $bits[] = $sourceTypes[$tk] ?? (str_starts_with($tk, 'signal:') ? (\App\Repositories\SseSuggestionQueueRepository::SIGNAL_TYPES[explode(':', $tk)[1] ?? ''] ?? $tk) : (str_starts_with($tk, 'suggestion:') ? (\App\Repositories\SseSuggestionQueueRepository::KINDS[explode(':', $tk)[1] ?? ''] ?? $tk) : $tk));
    }
    if ((string) ($rule['keyword'] ?? '') !== '') {
        $bits[] = '« ' . $rule['keyword'] . ' »';
    }
    if ((int) ($rule['min_age_days'] ?? 0) > 0) {
        $bits[] = 'plus de ' . (int) $rule['min_age_days'] . ' jours';
    }
    $sev = SseTriagePresets::SEVERITIES;
    if (($rule['min_severity'] ?? null) !== null) {
        $bits[] = 'gravité d’au moins : ' . ($sev[(string) $rule['min_severity']] ?? $rule['min_severity']);
    }
    if (($rule['max_severity'] ?? null) !== null) {
        $bits[] = 'gravité au plus : ' . ($sev[(string) $rule['max_severity']] ?? $rule['max_severity']);
    }

    return $bits === [] ? 'Tous les éléments' : implode(' · ', $bits);
};
?>
<div class="page-heading">
    <div>
        <h1>À trier</h1>
        <p>Tout ce qui attend une décision dans le bureau, regroupé par type, les plus prioritaires d’abord. Ouvrez une ligne pour la traiter dans sa page.</p>
    </div>
    <div class="page-reference"><strong><?= (int) $triage['total'] ?> à trier</strong></div>
</div>

<div class="tr" data-tr
     data-tr-act-url="<?= $h($base . '/traiter') ?>" data-tr-undo-url="<?= $h($base . '/annuler') ?>" data-tr-rules-url="<?= $h($base . '/regles') ?>" data-tr-preview-url="<?= $h($base . '/regles/apercu') ?>"
     data-tr-csrf="<?= $h(\App\Core\Csrf::token()) ?>" data-tr-treat="<?= $canTreat ? '1' : '0' ?>" data-tr-view="<?= $h($triageView) ?>">
    <span hidden data-tr-msg="selected" data-label="%1 sélectionnés"></span>
    <span hidden data-tr-msg="selectedOne" data-label="1 sélectionné"></span>
    <span hidden data-tr-msg="acknowledge" data-label="%1 éléments acquittés."></span>
    <span hidden data-tr-msg="snooze" data-label="%1 éléments mis en veille."></span>
    <span hidden data-tr-msg="recall" data-label="%1 éléments rappelés dans la liste."></span>
    <span hidden data-tr-msg="assign" data-label="%1 éléments assignés."></span>
    <span hidden data-tr-msg="undone" data-label="Action annulée."></span>
    <span hidden data-tr-msg="failed" data-label="L’action n’a pas pu être enregistrée."></span>
    <span hidden data-tr-msg="undo" data-label="Annuler (%1 s)"></span>
    <span hidden data-tr-msg="confirmText" data-label="Les %1 éléments de « %2 » seront acquittés. Vous pourrez annuler pendant 15 secondes."></span>
    <span hidden data-tr-msg="openN" data-label="Ouvrir les %1 premières"></span>
    <span hidden data-tr-msg="keysOn" data-label="Raccourcis activés"></span>
    <span hidden data-tr-msg="keysOff" data-label="Raccourcis désactivés"></span>
    <span hidden data-tr-msg="previewN" data-label="Cette règle concernerait %1 éléments."></span>
    <span hidden data-tr-msg="previewOne" data-label="Cette règle concernerait 1 élément."></span>
    <span hidden data-tr-msg="previewNone" data-label="Cette règle ne concernerait aucun élément."></span>
    <span hidden data-tr-msg="ruleOn" data-label="Activée"></span>
    <span hidden data-tr-msg="ruleOff" data-label="Désactivée"></span>

    <?php if ($degraded): ?>
        <p class="tr-note" role="status">Le traitement des éléments (acquitter, veille, règles) sera disponible après la mise à jour de la base de données ; la liste est en lecture seule en attendant.</p>
    <?php endif; ?>

    <div class="tr-bar">
        <nav class="tr-tabs" aria-label="Vues de la liste">
            <?php foreach ($triageViews as $vk => $vl): ?>
                <?php $n = match ($vk) { 'mes' => (int) $counts['mine'], 'veille' => (int) $counts['snoozed'], 'acquittes' => (int) $counts['acknowledged'], default => (int) $counts['open'] }; ?>
                <a href="<?= $h($query(['vue' => $vk === 'a-traiter' ? '' : $vk])) ?>" <?= $triageView === $vk ? 'aria-current="true"' : '' ?>><?= $h($vl) ?> <span class="tr-count"><?= $n ?></span></a>
            <?php endforeach; ?>
        </nav>
        <div class="tr-tools">
            <?php if ($canRules): ?><button type="button" class="tr-btn" data-tr-open-dialog="tr-rules">Règles</button><?php endif; ?>
            <button type="button" class="tr-btn" data-tr-open-dialog="tr-help" aria-keyshortcuts="?">Raccourcis</button>
        </div>
    </div>

    <form class="tr-filter" method="get" action="<?= $h($base) ?>">
        <?php if ($triageView !== 'a-traiter'): ?><input type="hidden" name="vue" value="<?= $h($triageView) ?>"><?php endif; ?>
        <label>Source
            <select name="source" data-tr-autosubmit>
                <option value="">Toutes les files · <?= (int) $counts['open'] ?></option>
                <?php foreach ($triage['sources'] as $key => $src): ?>
                    <?php if ((int) $src['count'] < 1 && $triageTab !== $key) { continue; } ?>
                    <option value="<?= $h($key) ?>" <?= $triageTab === $key ? 'selected' : '' ?>><?= $h($src['label']) ?> · <?= (int) $src['count'] ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Regrouper par
            <select name="groupe" data-tr-autosubmit>
                <?php foreach ($triageModes as $mk => $ml): ?>
                    <option value="<?= $h($mk) ?>" <?= $triageMode === $mk ? 'selected' : '' ?>><?= $h($ml) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <noscript><button class="tr-btn" type="submit">Appliquer</button></noscript>
    </form>

    <?php if ($triageItems === []): ?>
        <div class="sb-empty">
            <?php if ($triageView === 'veille'): ?>
                <strong>Aucun élément en veille</strong><p>Les éléments mis en veille reviennent dans la liste à la fin de leur délai.</p>
            <?php elseif ($triageView === 'acquittes'): ?>
                <strong>Aucun élément acquitté</strong><p>Un élément acquitté ne revient que si sa condition réapparaît après avoir été résolue.</p>
            <?php elseif ($triageView === 'mes'): ?>
                <strong>Aucun élément ne vous est assigné</strong><p>Utilisez « Me l’assigner » dans le menu d’une ligne pour la suivre ici.</p>
            <?php else: ?>
                <strong>Rien à trier</strong><p>Aucune décision n’attend dans les files que vous pouvez lire.</p>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="tr-listbox" data-tr-list>
            <?php $flat = []; ?>
            <?php foreach ($triageGroups as $g): ?>
                <?php if ($g['count'] < 2): ?>
                    <?php $flat[] = $g['items'][0]; continue; ?>
                <?php endif; ?>
                <?php if ($flat !== []): ?>
                    <ul class="tr-list tr-list--flat"><?php foreach ($flat as $it) { $row($it, true); } ?></ul>
                    <?php $flat = []; ?>
                <?php endif; ?>
                <?php
                $gid++;
                $keys = array_map(static fn (array $i): string => (string) $i['key'], $g['items']);
                $countLabel = SseTriageGrouping::countLabel((int) $g['count'], (string) $g['noun']);
                $desc = $g['reason'] !== '' ? $g['reason'] : 'Éléments de même ' . ($triageMode === 'sujet' ? 'sujet' : ($triageMode === 'source' ? 'file' : 'nature')) . '.';
                ?>
                <section class="tr-group tr-group--<?= $h($g['tone']) ?>" data-tr-group data-keys="<?= $h(implode(',', $keys)) ?>" data-title="<?= $h($g['title']) ?>" data-count="<?= (int) $g['count'] ?>">
                    <div class="tr-group__head">
                        <?php if ($canTreat): ?><label class="tr-check"><input type="checkbox" data-tr-select-group aria-label="Sélectionner tout le groupe : <?= $h($g['title']) ?>"></label><?php endif; ?>
                        <button type="button" class="tr-group__toggle" aria-expanded="false" aria-controls="tr-g<?= $gid ?>" data-tr-toggle>
                            <span class="tr-chev" aria-hidden="true"></span>
                            <span class="tr-group__text">
                                <span class="tr-group__title"><?= $h($g['title']) ?> · <?= $h($countLabel) ?></span>
                                <span class="tr-group__desc"><span><?= $h($desc) ?></span> <span>Plus ancien : <?= (int) $g['oldest_days'] ?> j.</span></span>
                            </span>
                        </button>
                        <?php if ($canTreat && ($triageView === 'a-traiter' || $triageView === 'mes')): ?>
                            <button type="button" class="tr-btn tr-group__ack" data-tr-group-ack>Acquitter tout le groupe</button>
                        <?php elseif ($canTreat): ?>
                            <button type="button" class="tr-btn tr-group__ack" data-tr-act="recall" data-keys="<?= $h(implode(',', $keys)) ?>">Rappeler tout le groupe</button>
                        <?php endif; ?>
                    </div>
                    <ul class="tr-list" id="tr-g<?= $gid ?>" hidden>
                        <?php foreach ($g['items'] as $it) { $row($it, $triageMode !== 'type', (string) $g['reason']); } ?>
                    </ul>
                </section>
            <?php endforeach; ?>
            <?php if ($flat !== []): ?>
                <ul class="tr-list tr-list--flat"><?php foreach ($flat as $it) { $row($it, true); } ?></ul>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($canTreat): ?>
        <div class="tr-bulk" data-tr-bulk hidden role="region" aria-label="Actions sur la sélection">
            <span class="tr-bulk__count" data-tr-count aria-live="polite"></span>
            <?php if ($triageView === 'a-traiter' || $triageView === 'mes'): ?>
                <button type="button" class="tr-btn tr-btn--primary" data-tr-bulk-act="acknowledge">Acquitter</button>
                <button type="button" class="tr-btn" data-tr-bulk-act="snooze" data-days="7">Veille 7 jours</button>
                <button type="button" class="tr-btn" data-tr-bulk-act="snooze" data-days="30">Veille 30 jours</button>
            <?php else: ?>
                <button type="button" class="tr-btn tr-btn--primary" data-tr-bulk-act="recall">Rappeler dans la liste</button>
            <?php endif; ?>
            <details class="tr-menu tr-menu--up">
                <summary><span>Plus</span></summary>
                <div class="tr-pop tr-pop--menu">
                    <button type="button" class="tr-btn" data-tr-bulk-act="assign" data-assignee="me">Me l’assigner</button>
                    <?php if ($assignees !== []): ?>
                        <label class="tr-field">Assigner à
                            <select data-tr-assignee>
                                <option value="">Choisir…</option>
                                <?php foreach ($assignees as $aid => $aname): ?><option value="<?= (int) $aid ?>"><?= $h($aname) ?></option><?php endforeach; ?>
                            </select>
                        </label>
                    <?php endif; ?>
                    <button type="button" class="tr-btn" data-tr-bulk-act="assign" data-assignee="0">Retirer l’assignation</button>
                    <button type="button" class="tr-btn" data-tr-bulk-open data-tr-open-label>Ouvrir les premières</button>
                </div>
            </details>
            <button type="button" class="tr-btn tr-btn--ghost" data-tr-clear>Tout désélectionner</button>
        </div>
    <?php endif; ?>

    <div class="tr-undo" data-tr-undo hidden role="status" aria-live="polite">
        <span data-tr-undo-text></span>
        <button type="button" class="tr-btn" data-tr-undo-btn>Annuler</button>
    </div>
    <p class="tr-toast" data-tr-toast hidden role="status" aria-live="polite"></p>

    <dialog class="tr-dialog" id="tr-confirm" aria-labelledby="tr-confirm-title">
        <form method="dialog">
            <h2 id="tr-confirm-title">Acquitter tout le groupe ?</h2>
            <p data-tr-confirm-text></p>
            <div class="tr-dialog__foot">
                <button type="submit" value="cancel" class="tr-btn">Annuler</button>
                <button type="submit" value="ok" class="tr-btn tr-btn--primary">Acquitter</button>
            </div>
        </form>
    </dialog>

    <dialog class="tr-dialog" id="tr-help" aria-labelledby="tr-help-title">
        <form method="dialog">
            <h2 id="tr-help-title">Raccourcis clavier</h2>
            <p>Ils servent quand aucun champ de saisie n’a le focus. Vous pouvez les désactiver.</p>
            <dl class="tr-keys">
                <dt><kbd>j</kbd> <kbd>k</kbd></dt><dd>Ligne suivante, ligne précédente</dd>
                <dt><kbd>Entrée</kbd></dt><dd>Ouvrir la ligne</dd>
                <dt><kbd>x</kbd></dt><dd>Sélectionner la ligne</dd>
                <?php if ($canTreat): ?>
                    <dt><kbd>e</kbd></dt><dd>Acquitter la ligne ou la sélection</dd>
                    <dt><kbd>s</kbd></dt><dd>Mettre en veille 7 jours</dd>
                <?php endif; ?>
                <dt><kbd>?</kbd></dt><dd>Afficher cette aide</dd>
            </dl>
            <label class="tr-field tr-field--inline"><input type="checkbox" data-tr-keys-toggle> Désactiver les raccourcis clavier</label>
            <div class="tr-dialog__foot"><button type="submit" class="tr-btn tr-btn--primary">Fermer</button></div>
        </form>
    </dialog>

    <?php if ($canRules): ?>
        <dialog class="tr-dialog tr-drawer" id="tr-rules" aria-labelledby="tr-rules-title">
            <div class="tr-drawer__head">
                <div>
                    <h2 id="tr-rules-title">Règles Cerbère-Tri</h2>
                    <p>Cerbère-Tri applique ces règles aux éléments qui n’ont encore aucun état. Chaque action est journalisée et peut être annulée.</p>
                </div>
                <form method="dialog"><button type="submit" class="tr-btn" aria-label="Fermer les règles">Fermer</button></form>
            </div>
            <div class="tr-subtabs" role="tablist" aria-label="Règles Cerbère-Tri">
                <button type="button" role="tab" id="tr-tab-rules" aria-selected="true" aria-controls="tr-pane-rules" data-tr-tab="rules">Règles</button>
                <button type="button" role="tab" id="tr-tab-journal" aria-selected="false" aria-controls="tr-pane-journal" data-tr-tab="journal" tabindex="-1">Journal</button>
                <button type="button" role="tab" id="tr-tab-new" aria-selected="false" aria-controls="tr-pane-new" data-tr-tab="new" tabindex="-1">Nouvelle règle</button>
            </div>

            <div id="tr-pane-rules" role="tabpanel" aria-labelledby="tr-tab-rules" data-tr-pane="rules">
                <ul class="tr-rules">
                    <?php foreach ($rulesView as $rv): ?>
                        <li class="tr-rule" data-tr-rule data-preset="<?= $h($rv['preset']) ?>" data-id="<?= (int) $rv['id'] ?>">
                            <div class="tr-rule__body">
                                <h3><?= $h($rv['name']) ?></h3>
                                <?php if ($rv['hint'] !== ''): ?><p><?= $h($rv['hint']) ?></p><?php endif; ?>
                                <?php if (!$rv['engine']): ?>
                                    <p class="tr-rule__facts">Si : <?= $h($ruleFacts($rv['rule'])) ?> · Alors : <?= $h((string) $rv['rule']['action'] === 'snooze' ? 'Mettre en veille ' . (int) $rv['rule']['action_value'] . ' jours' : (SseTriagePresets::ACTIONS[(string) $rv['rule']['action']] ?? '')) ?> · Portée : <?= $h(SseTriagePresets::SCOPES[(string) ($rv['rule']['scope'] ?? 'all')] ?? '') ?></p>
                                <?php endif; ?>
                                <p class="tr-rule__stats">
                                    <?php if ($rv['engine']): ?>
                                        <span>Appliquée au passage de la nuit par Cerbère-Détection.</span>
                                    <?php elseif ($rv['preview'] === 0): ?>
                                        <span>Cette règle ne concernerait aucun élément.</span>
                                    <?php elseif ($rv['preview'] === 1): ?>
                                        <span>Cette règle concernerait 1 élément.</span>
                                    <?php else: ?>
                                        <span>Cette règle concernerait <?= (int) $rv['preview'] ?> éléments.</span>
                                    <?php endif; ?>
                                    <?php if ($rv['last_fired_at'] !== ''): ?>
                                        <span>Dernier déclenchement : <?= $h($when($rv['last_fired_at'])) ?></span> <span><?= (int) $rv['fired_count'] ?> traités.</span>
                                    <?php else: ?>
                                        <span>Jamais déclenchée.</span>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <button type="button" class="tr-switch" role="switch" aria-checked="<?= $rv['enabled'] ? 'true' : 'false' ?>" data-tr-rule-toggle aria-label="<?= $h($rv['name']) ?>">
                                <span class="tr-switch__knob" aria-hidden="true"></span><span class="tr-switch__text"><?= $rv['enabled'] ? 'Activée' : 'Désactivée' ?></span>
                            </button>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div id="tr-pane-journal" role="tabpanel" aria-labelledby="tr-tab-journal" data-tr-pane="journal" hidden>
                <?php if ($journal === []): ?>
                    <p class="tr-empty">Aucune action enregistrée pour le moment.</p>
                <?php else: ?>
                    <ul class="tr-journal">
                        <?php foreach ($journal as $e): ?>
                            <?php
                            $act = (string) $e['action'];
                            $auto = str_starts_with($act, 'auto_');
                            $verb = match (str_replace('auto_', '', $act)) {
                                'acknowledge' => 'Acquitté',
                                'snooze' => 'Mis en veille',
                                'priority' => 'Priorité majorée',
                                'assign' => 'Assigné',
                                'recall' => 'Rappelé',
                                'undo' => 'Annulé',
                                default => $act,
                            };
                            ?>
                            <li data-tr-event="<?= (int) $e['id'] ?>">
                                <div>
                                    <strong><?= $h($verb . ' par ' . ($auto ? 'Cerbère-Tri' . ($e['rule_name'] !== '' ? ' (règle « ' . $e['rule_name'] . ' »)' : '') : (string) $e['actor_label'])) ?></strong>
                                    <p><?= $h($e['subject']) ?><?= $e['type_label'] !== '' ? ' · ' . $h($e['type_label']) : '' ?> · <?= $h($when((string) $e['created_at'])) ?></p>
                                </div>
                                <?php if ($e['undone_at'] !== null): ?>
                                    <span class="tr-chip">Annulé</span>
                                <?php elseif ($act !== 'undo'): ?>
                                    <button type="button" class="tr-btn" data-tr-undo-event="<?= (int) $e['id'] ?>">Annuler</button>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

            <div id="tr-pane-new" role="tabpanel" aria-labelledby="tr-tab-new" data-tr-pane="new" hidden>
                <form class="tr-form" data-tr-rule-form>
                    <label class="tr-field">Nom de la règle<input type="text" name="name" maxlength="160" required></label>
                    <div class="tr-form__grid">
                        <label class="tr-field">File
                            <select name="source_key">
                                <option value="">Toutes</option>
                                <?php foreach ($triage['sources'] as $key => $src): ?><option value="<?= $h($key) ?>"><?= $h($src['label']) ?></option><?php endforeach; ?>
                            </select>
                        </label>
                        <label class="tr-field">Type
                            <select name="type_key">
                                <option value="">Tous</option>
                                <?php foreach ($sourceTypes as $tk => $tl): ?><option value="<?= $h($tk) ?>"><?= $h($tl) ?></option><?php endforeach; ?>
                            </select>
                        </label>
                        <label class="tr-field">Mot du titre<input type="text" name="keyword" maxlength="80"></label>
                        <label class="tr-field">Ancienneté minimale (jours)<input type="number" name="min_age_days" min="0" max="3650" value="0"></label>
                        <label class="tr-field">Gravité d’au moins
                            <select name="min_severity"><option value="">Aucune</option><?php foreach (SseTriagePresets::SEVERITIES as $sk => $sl): ?><option value="<?= $h($sk) ?>"><?= $h($sl) ?></option><?php endforeach; ?></select>
                        </label>
                        <label class="tr-field">Gravité au plus
                            <select name="max_severity"><option value="">Aucune</option><?php foreach (SseTriagePresets::SEVERITIES as $sk => $sl): ?><option value="<?= $h($sk) ?>"><?= $h($sl) ?></option><?php endforeach; ?></select>
                        </label>
                        <label class="tr-field">Portée
                            <select name="scope"><?php foreach (SseTriagePresets::SCOPES as $sk => $sl): ?><option value="<?= $h($sk) ?>"><?= $h($sl) ?></option><?php endforeach; ?></select>
                        </label>
                        <label class="tr-field">Action
                            <select name="rule_action" data-tr-rule-action>
                                <?php foreach (SseTriagePresets::ACTIONS as $ak => $al): ?><option value="<?= $h($ak) ?>"><?= $h($al) ?></option><?php endforeach; ?>
                            </select>
                        </label>
                        <label class="tr-field" data-tr-value-snooze hidden>Durée de veille (jours)<input type="number" min="1" max="365" value="7" data-tr-value="snooze"></label>
                        <label class="tr-field" data-tr-value-priority hidden>Points de priorité en plus<input type="number" min="1" max="50" value="10" data-tr-value="priority"></label>
                        <label class="tr-field" data-tr-value-assign hidden>Assigner à
                            <select data-tr-value="assign"><option value="">Choisir…</option><?php foreach ($assignees as $aid => $aname): ?><option value="<?= (int) $aid ?>"><?= $h($aname) ?></option><?php endforeach; ?></select>
                        </label>
                    </div>
                    <p class="tr-preview" data-tr-preview aria-live="polite"></p>
                    <div class="tr-dialog__foot">
                        <button type="button" class="tr-btn" data-tr-preview-btn>Voir combien d’éléments</button>
                        <button type="submit" class="tr-btn tr-btn--primary">Créer la règle (désactivée)</button>
                    </div>
                </form>
            </div>
        </dialog>
    <?php endif; ?>
</div>
<?php
$sseContent = ob_get_clean();
require __DIR__ . '/_layout.php';
