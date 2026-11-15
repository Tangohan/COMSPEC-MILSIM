<?php
declare(strict_types=1);
use App\Support\SseAccessCatalog as C;

/**
 * Droits d'accès SSE : aperçu de l'effet avant enregistrement. Rien n'est écrit tant qu'on n'a pas confirmé ; le formulaire
 * de confirmation reprend la saisie telle que validée.
 *
 * @var list<array<string, mixed>> $rules
 * @var list<string> $skipped
 * @var array<string, mixed> $sim
 * @var string $effectLine l'effet en une phrase
 * @var array<string, mixed> $input
 * @var string|null $template
 * @var string $action chemin de la route d'enregistrement
 * @var string $back
 * @var string $csrfToken
 * @var string $base
 */
ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$tab = 'quoi';
require __DIR__ . '/_head.php';
$count = count($rules);
$hidden = [];
foreach (['section', 'effect', 'starts_at', 'ends_at', 'reason', 'template', 'restrict_rest', 'access', 'recipe'] as $k) {
    if (isset($input[$k]) && $input[$k] !== '') {
        $hidden[$k] = (string) $input[$k];
    }
}
if (isset($input['subject_type'])) {
    $hidden['subject_ref'] = C::subjectRef((string) $input['subject_type'], (int) ($input['subject_id'] ?? 0));
}
$hiddenLists = ['sections' => array_values(array_map('strval', (array) ($input['sections'] ?? [])))];
$hiddenMap = [];
foreach ((array) ($input['ref'] ?? []) as $i => $ref) {
    $hiddenMap['ref[' . (int) $i . ']'] = (string) $ref;
}
$levels = array_values(array_map('strval', (array) ($input['levels'] ?? [])));
?>
<div class="fx sar">
    <div class="fx-th">
        <div>
            <h1>Aperçu avant enregistrement</h1>
            <p>Rien n’est encore enregistré. Vérifiez l’effet, puis confirmez.</p>
        </div>
    </div>

    <section class="fx-card" aria-labelledby="sar-prev-title">
        <h2 id="sar-prev-title" class="sar-h2">Effet sur les membres</h2>
        <p class="sar-effect" role="status"><?= $h($effectLine) ?></p>
        <dl class="sar-stats">
            <div><dt>Membres qui gagneraient l’accès</dt><dd><?= (int) $sim['gained'] ?></dd></div>
            <div><dt>Membres qui perdraient l’accès</dt><dd><?= (int) $sim['lost'] ?></dd></div>
            <div><dt>Membres concernés</dt><dd><?= (int) $sim['concerned'] ?></dd></div>
        </dl>
        <p class="fx-help">Seuls les membres ayant le droit d’entrer au bureau SSE sont comptés.</p>
        <?php if (!empty($sim['truncated'])): ?>
            <p class="fx-note" role="status">Seuls les premiers membres concernés sont évalués.</p>
        <?php endif; ?>
        <?php if ($sim['sample_gained'] !== []): ?>
            <p><b>Gagneraient l’accès :</b> <?= $h(implode(', ', $sim['sample_gained'])) ?></p>
        <?php endif; ?>
        <?php if ($sim['sample_lost'] !== []): ?>
            <p><b>Perdraient l’accès :</b> <?= $h(implode(', ', $sim['sample_lost'])) ?></p>
        <?php endif; ?>
        <?php if ((int) $sim['protected'] > 0): ?>
            <p class="fx-help">Membres dont l’identité est protégée par l’annuaire (non nommés) : <b><?= (int) $sim['protected'] ?></b></p>
        <?php endif; ?>
    </section>

    <section class="fx-card" aria-labelledby="sar-prev-rules">
        <h2 id="sar-prev-rules" class="sar-h2">Règles à enregistrer : <?= $count ?></h2>
        <div class="fx-tbw">
            <table class="fx-tb resp">
                <thead><tr><th scope="col">Section</th><th scope="col">Niveau</th><th scope="col">Bénéficiaire</th><th scope="col">Effet</th><th scope="col">Période</th></tr></thead>
                <tbody>
                <?php foreach ($rules as $r): ?>
                    <tr>
                        <td data-label="Section"><?= $h(C::sectionLabel((string) $r['section'])) ?></td>
                        <td data-label="Niveau"><?= $h(C::levelLabel((string) $r['level'])) ?></td>
                        <td data-label="Bénéficiaire"><?= $h($r['subject_label']) ?></td>
                        <td data-label="Effet"><span class="fx-flg <?= $r['effect'] === 'deny' ? 'sar-deny' : 'sar-allow' ?>"><?= $r['effect'] === 'deny' ? 'Refus' : 'Accord' ?></span></td>
                        <td data-label="Période"><?= $r['starts_at'] === null && $r['ends_at'] === null ? 'Sans limite' : $h(($r['starts_at'] !== null ? 'du ' . substr((string) $r['starts_at'], 0, 10) . ' ' : '') . ($r['ends_at'] !== null ? 'jusqu’au ' . substr((string) $r['ends_at'], 0, 10) : '')) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($skipped !== []): ?>
            <h3 class="sar-h3">Non créées</h3>
            <ul class="sar-list">
                <?php foreach ($skipped as $s): ?><li><?= $h($s) ?></li><?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <form method="post" action="<?= $h(url($action)) ?>" class="sar-form">
            <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
            <input type="hidden" name="do" value="save">
            <?php foreach ($hidden as $k => $v): ?><input type="hidden" name="<?= $h($k) ?>" value="<?= $h($v) ?>"><?php endforeach; ?>
            <?php foreach ($levels as $lv): ?><input type="hidden" name="levels[]" value="<?= $h($lv) ?>"><?php endforeach; ?>
            <?php foreach ($hiddenLists['sections'] as $sec): ?><input type="hidden" name="sections[]" value="<?= $h($sec) ?>"><?php endforeach; ?>
            <?php foreach ($hiddenMap as $k => $v): ?><input type="hidden" name="<?= $h($k) ?>" value="<?= $h($v) ?>"><?php endforeach; ?>
            <div class="fx-row">
                <button type="submit" class="btn pri">Enregistrer</button>
                <a class="btn" href="<?= $h(url($back)) ?>">Modifier</a>
            </div>
        </form>
    </section>
</div>
<?php
$sseContent = ob_get_clean();
require dirname(__DIR__) . '/_layout.php';
