<?php
declare(strict_types=1);
/**
 * Fiche du mur, version pilotage (back-office) : seules les informations renseignées sont affichées,
 * une action principale selon l’état, les autres dans « Plus d’actions ».
 *
 * @var array<string, mixed> $entry
 * @var string $boardToday
 * @var array<string, string> $entryTypeLabels
 * @var array<string, string> $operationalLabels
 * @var array<string, string> $priorityShort
 * @var array<string, string> $phaseLabels
 * @var callable $temporalBucket
 */
$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$csrf = $h(\App\Core\Csrf::token());

$eid = (int) ($entry['id'] ?? 0);
$etype = (string) ($entry['entry_type'] ?? 'task');
$priority = (string) ($entry['priority'] ?? 'normal');
$opKey = (string) ($entry['operational_status'] ?? 'planned');
$phaseKey = (string) ($entry['phase_current'] ?? 'phase_1');
$valStat = (string) ($entry['validation_status'] ?? 'draft');
$pubRow = (string) ($entry['status'] ?? 'active');
$isDraft = ($pubRow === 'draft' || $valStat === 'draft');
$isRetired = $pubRow === 'cancelled';
$tags = array_values(array_filter(array_map('trim', explode(',', (string) ($entry['tags_list'] ?? '')))));
$tagBlob = strtolower(implode(' ', $tags));
$title = trim((string) ($entry['title'] ?? ''));
$title = $title !== '' ? $title : 'Sans titre';
$desc = trim((string) ($entry['description'] ?? ''));
$editUrl = url('back-office/tableau-operationnel/fiche/' . $eid);

$search = strtolower(trim(implode(' ', array_filter([
    $title,
    $desc,
    (string) ($entry['chief_name'] ?? ''),
    (string) ($entry['deputy_name'] ?? ''),
    (string) ($entry['replacement_name'] ?? ''),
    (string) ($entry['operation_zone'] ?? ''),
    $tagBlob,
]))));

$fmtDate = static function (mixed $raw): string {
    $s = trim((string) ($raw ?? ''));
    if ($s === '') {
        return '';
    }
    $ts = strtotime($s);

    return $ts ? date('d/m', $ts) : $s;
};
$start = $fmtDate($entry['start_date'] ?? null);
$end = $fmtDate($entry['end_date'] ?? null);
$bucket = $temporalBucket($entry, $boardToday);
$whenLabel = match ($bucket) {
    'en_cours' => 'En cours',
    'aujourdhui' => 'Aujourd’hui',
    'a_venir' => 'À venir',
    'passe' => 'Terminée',
    default => 'Sans date',
};
$whenDates = $start !== '' ? ($end !== '' && $end !== $start ? $start . ' → ' . $end : $start) : '';

$typeTone = match ($etype) {
    'flash_info', 'flash_info_detailed' => 'flash',
    'permanence' => 'perm',
    'info' => 'info',
    'manifestation' => 'event',
    default => 'mission',
};

$people = array_filter([
    'Responsable' => trim((string) ($entry['chief_name'] ?? '')),
    'Adjoint' => trim((string) ($entry['deputy_name'] ?? '')),
    'Remplaçant' => trim((string) ($entry['replacement_name'] ?? '')),
], static fn (string $v): bool => $v !== '' && $v !== '—');
$zone = trim((string) ($entry['operation_zone'] ?? ''));
$mapLink = trim((string) ($entry['map_link'] ?? ''));
$checkDone = (int) ($entry['checklist_done'] ?? 0);
$checkReq = (int) ($entry['checklist_required'] ?? 0);
$dossier = trim((string) ($entry['dossier_ref'] ?? ''));
$flashNotes = $etype === 'flash_info_detailed' && is_array($entry['notes'] ?? null) ? $entry['notes'] : [];
?>
<article class="bo-tbo-card entry-card bo-tbo-card--<?= $h($typeTone) ?><?= $priority === 'critical' ? ' is-critical' : ($priority === 'high' ? ' is-high' : '') ?><?= $isDraft ? ' is-draft' : '' ?>"
         data-entry_type="<?= $h($etype) ?>"
         data-operational_status="<?= $h($opKey) ?>"
         data-priority="<?= $h($priority) ?>"
         data-tag="<?= $h($tagBlob) ?>"
         data-search="<?= $h($search) ?>">
    <div class="bo-tbo-card__top">
        <span class="bo-tbo-chip bo-tbo-chip--<?= $h($typeTone) ?>"><?= $h($entryTypeLabels[$etype] ?? $etype) ?></span>
        <span class="bo-tbo-chip bo-tbo-chip--when bo-tbo-chip--<?= $h($bucket) ?>"><?= $h($whenLabel) ?><?php if ($whenDates !== ''): ?> · <?= $h($whenDates) ?><?php endif; ?></span>
        <?php if ($priority === 'critical' || $priority === 'high'): ?>
            <span class="bo-tbo-chip bo-tbo-chip--prio-<?= $h($priority) ?>">Priorité <?= $h(mb_strtolower($priorityShort[$priority] ?? $priority)) ?></span>
        <?php endif; ?>
        <?php if ($isDraft): ?>
            <span class="bo-tbo-chip bo-tbo-chip--draft" title="Visible seulement ici. Les membres ne la voient pas encore.">Brouillon</span>
        <?php elseif ($isRetired): ?>
            <span class="bo-tbo-chip bo-tbo-chip--retired" title="Retirée du mur : les membres ne la voient plus.">Retirée</span>
        <?php elseif ($valStat === 'validated'): ?>
            <span class="bo-tbo-chip bo-tbo-chip--draft" title="Approuvée, mais pas encore visible par les membres.">Approuvée, pas en ligne</span>
        <?php endif; ?>
    </div>

    <h4 class="bo-tbo-card__title">
        <?php if ($eid > 0): ?>
            <a class="bo-tbo-card__link" href="<?= $h($editUrl) ?>"><?= $h($title) ?></a>
        <?php else: ?>
            <?= $h($title) ?>
        <?php endif; ?>
    </h4>

    <?php if ($desc !== ''): ?>
        <p class="bo-tbo-card__desc entry-card__body"><?= nl2br($h($desc)) ?></p>
    <?php endif; ?>

    <?php if ($flashNotes !== []): ?>
        <ul class="bo-tbo-card__notes entry-card__body">
            <?php foreach ($flashNotes as $note):
                $content = trim((string) ($note['content'] ?? ''));
                if ($content === '') {
                    continue;
                }
                $noteLabel = match ((string) ($note['note_type'] ?? 'info')) {
                    'consigne' => 'Action',
                    'restriction' => 'Restriction',
                    'brief' => 'Brief',
                    default => 'Info',
                };
                ?>
                <li><strong><?= $h($noteLabel) ?></strong> <?= nl2br($h($content)) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <?php if ($people !== [] || $zone !== '' || $checkReq > 0 || $dossier !== '' || $tags !== [] || $opKey !== 'planned' || $phaseKey !== 'phase_1'): ?>
    <dl class="bo-tbo-card__meta entry-card__meta">
        <?php foreach ($people as $role => $name): ?>
            <div><dt><?= $h($role) ?></dt><dd><?= $h($name) ?></dd></div>
        <?php endforeach; ?>
        <?php if ($zone !== ''): ?>
            <div><dt>Lieu</dt><dd><?= $h($zone) ?><?php if ($mapLink !== ''): ?> · <a class="bo-tbo-card__inline-link" href="<?= $h($mapLink) ?>" target="_blank" rel="noopener">carte</a><?php endif; ?></dd></div>
        <?php endif; ?>
        <?php if ($opKey !== 'planned'): ?>
            <div><dt>État</dt><dd><?= $h($operationalLabels[$opKey] ?? $opKey) ?></dd></div>
        <?php endif; ?>
        <?php if ($phaseKey !== 'phase_1'): ?>
            <div><dt>Étape</dt><dd><?= $h($phaseLabels[$phaseKey] ?? $phaseKey) ?></dd></div>
        <?php endif; ?>
        <?php if ($checkReq > 0): ?>
            <div><dt>Vérifications</dt><dd><?= $checkDone ?> sur <?= $checkReq ?> faites</dd></div>
        <?php endif; ?>
        <?php if ($dossier !== ''): ?>
            <div><dt>Dossier</dt><dd><?= $h($dossier) ?></dd></div>
        <?php endif; ?>
        <?php if ($tags !== []): ?>
            <div><dt>Étiquettes</dt><dd><?= $h(implode(', ', $tags)) ?></dd></div>
        <?php endif; ?>
    </dl>
    <?php endif; ?>

    <?php if ($eid > 0): ?>
    <div class="bo-tbo-card__actions entry-card-actions">
        <?php if ($isDraft || $valStat === 'validated'): ?>
            <form method="post" action="<?= $h(url('back-office/tableau-operationnel/' . $eid . '/validation')) ?>">
                <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
                <input type="hidden" name="validation_status" value="active">
                <button type="submit" class="bo-tbo-btn bo-tbo-btn--primary bo-tbo-btn--sm" title="Les membres concernés la verront sur leur mur.">Mettre en ligne</button>
            </form>
        <?php endif; ?>
        <a href="<?= $h($editUrl) ?>" class="bo-tbo-btn bo-tbo-btn--sm<?= $isDraft ? '' : ' bo-tbo-btn--outline' ?>">Modifier</a>
        <details class="bo-tbo-more">
            <summary class="bo-tbo-btn bo-tbo-btn--ghost bo-tbo-btn--sm">Plus d’actions</summary>
            <div class="bo-tbo-more__menu">
                <?php if ($valStat === 'draft'): ?>
                    <form method="post" action="<?= $h(url('back-office/tableau-operationnel/' . $eid . '/validation')) ?>">
                        <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
                        <input type="hidden" name="validation_status" value="validated">
                        <button type="submit"><strong>Approuver sans publier</strong><span>Marque la fiche comme relue. Elle reste invisible pour les membres.</span></button>
                    </form>
                    <form method="post" action="<?= $h(url('back-office/tableau-operationnel/' . $eid . '/validation')) ?>">
                        <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
                        <input type="hidden" name="validation_status" value="rejected">
                        <button type="submit" class="is-danger"><strong>Refuser</strong><span>La fiche ne sera pas publiée.</span></button>
                    </form>
                <?php endif; ?>
                <form method="post" action="<?= $h(url('back-office/tableau-operationnel/fiche/' . $eid . '/dupliquer')) ?>">
                    <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
                    <button type="submit"><strong>Dupliquer</strong><span>Crée un brouillon avec le même contenu.</span></button>
                </form>
                <form method="post" action="<?= $h(url('back-office/tableau-operationnel/' . $eid . '/frago')) ?>">
                    <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
                    <button type="submit"><strong>Annoncer un changement (FRAGO)</strong><span>Crée un brouillon « [FRAGO] » à corriger puis publier, pour signaler une modification.</span></button>
                </form>
                <?php if (!in_array($opKey, ['completed', 'cancelled'], true)): ?>
                    <form method="post" action="<?= $h(url('back-office/tableau-operationnel/' . $eid . '/status')) ?>">
                        <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
                        <input type="hidden" name="operational_status" value="completed">
                        <button type="submit"><strong>Marquer comme terminée</strong><span>L’activité est finie. La fiche passe en « Terminé ».</span></button>
                    </form>
                <?php endif; ?>
            </div>
        </details>
    </div>
    <?php endif; ?>
</article>
