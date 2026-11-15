<?php
declare(strict_types=1);
use App\Support\SseAccessCatalog as C;

/**
 * Droits d'accès SSE : les règles d'une section, niveau par niveau, et le formulaire pour en ajouter.
 *
 * @var string $sectionKey
 * @var array<string, mixed> $section
 * @var array<string, list<array<string, mixed>>> $rules
 * @var array{roles: list<array{id: int, label: string}>, units: list<array{id: int, label: string}>} $options
 * @var bool $ready
 * @var array<string, mixed> $old
 * @var string $focus
 * @var string $csrfToken
 * @var string $base
 */
ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$tab = 'grille';
require __DIR__ . '/_head.php';
$old = is_array($old ?? null) ? $old : [];
$oldLevels = is_array($old['levels'] ?? null) ? array_map('strval', $old['levels']) : ($focus !== '' ? [$focus] : []);
$stateLabels = ['active' => 'En cours', 'scheduled' => 'À venir', 'expired' => 'Échue'];
$self = $base . '/section/' . $sectionKey;
?>
<div class="fx sar">
    <div class="fx-th">
        <div>
            <h1><?= $h($section['label']) ?></h1>
            <p><?= $h($section['hint']) ?></p>
        </div>
        <a class="btn" href="<?= $h(url($base)) ?>">Retour à la grille</a>
    </div>

    <?php foreach (C::LEVELS as $lv => $def): ?>
        <?php if (!C::applies($sectionKey, $lv)) { continue; } ?>
        <?php $levelRules = $rules[$lv] ?? []; ?>
        <section class="fx-card sar-level<?= $focus === $lv ? ' is-focus' : '' ?>" id="niv-<?= $h($lv) ?>" aria-labelledby="niv-<?= $h($lv) ?>-t">
            <h2 id="niv-<?= $h($lv) ?>-t" class="sar-h2"><?= $h($def['label']) ?></h2>
            <p class="fx-help"><?= $h($def['hint']) ?></p>
            <p class="fx-help">Droit d’origine : <b><?= $h(C::PROFILES[C::legacyProfile($sectionKey, $lv)]['label']) ?></b></p>
            <?php if ($levelRules === []): ?>
                <p class="sar-empty"><b>Par défaut</b> Aucune règle : seul le droit d’origine s’applique.</p>
            <?php else: ?>
                <div class="fx-tbw">
                    <table class="fx-tb resp">
                        <thead><tr><th scope="col">Bénéficiaire</th><th scope="col">Effet</th><th scope="col">Période</th><th scope="col">Motif</th><th scope="col">État</th><th scope="col"><span class="sr-only">Retirer</span></th></tr></thead>
                        <tbody>
                        <?php foreach ($levelRules as $r): ?>
                            <tr>
                                <td data-label="Bénéficiaire"><?= $h($r['subject_label']) ?> <small class="fx-help"><?= $h($r['subject_type_label']) ?></small></td>
                                <td data-label="Effet"><span class="fx-flg <?= $r['effect'] === 'deny' ? 'sar-deny' : 'sar-allow' ?>"><?= $r['effect'] === 'deny' ? 'Refus' : 'Accord' ?></span></td>
                                <td data-label="Période"><?= $r['starts_at'] === null && $r['ends_at'] === null ? 'Sans limite' : $h(($r['starts_at'] !== null ? 'du ' . substr((string) $r['starts_at'], 0, 10) . ' ' : '') . ($r['ends_at'] !== null ? 'jusqu’au ' . substr((string) $r['ends_at'], 0, 10) : '')) ?></td>
                                <td data-label="Motif"><?= $r['reason'] !== '' ? $h($r['reason']) : '—' ?><?php if (($r['source'] ?? '') !== ''): ?> <small class="fx-help">Modèle : <?= $h(C::TEMPLATES[$r['source']]['label'] ?? $r['source']) ?></small><?php endif; ?></td>
                                <td data-label="État"><?= $h($stateLabels[$r['state']] ?? $r['state']) ?></td>
                                <td data-label="Retirer">
                                    <form method="post" action="<?= $h(url($base . '/regle/' . (int) $r['id'] . '/retirer')) ?>" class="sar-inline">
                                        <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                                        <input type="hidden" name="back" value="<?= $h($self) ?>">
                                        <label class="sr-only" for="rev-<?= (int) $r['id'] ?>">Motif du retrait</label>
                                        <input class="fx-in sar-small" id="rev-<?= (int) $r['id'] ?>" type="text" name="reason" maxlength="<?= (int) C::REASON_MAX ?>" placeholder="Motif du retrait">
                                        <button type="submit" class="btn sm danger">Retirer</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    <?php endforeach; ?>

    <section class="fx-card" aria-labelledby="sar-add-title">
        <h2 id="sar-add-title" class="sar-h2">Ajouter une règle</h2>
        <form method="post" action="<?= $h(url($self . '/regle')) ?>" class="sar-form">
            <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
            <input type="hidden" name="do" value="preview">
            <div class="fx-g2 sar-g">
                <label class="fx-f"><span>Bénéficiaire</span>
                    <?php $selectName = 'subject_ref'; $selectId = 'sar-subject'; $selected = isset($old['subject_type'], $old['subject_id']) ? C::subjectRef((string) $old['subject_type'], (int) $old['subject_id']) : ''; require __DIR__ . '/_subject_select.php'; ?>
                </label>
                <fieldset class="sar-fs">
                    <legend>Effet</legend>
                    <label class="sar-check"><input type="radio" name="effect" value="allow" required<?= ($old['effect'] ?? 'allow') === 'allow' ? ' checked' : '' ?>> <span>Accorder</span></label>
                    <label class="sar-check"><input type="radio" name="effect" value="deny"<?= ($old['effect'] ?? '') === 'deny' ? ' checked' : '' ?>> <span>Refuser (l’emporte sur tout le reste)</span></label>
                </fieldset>
            </div>
            <fieldset class="sar-fs">
                <legend>Niveaux de droit</legend>
                <div class="sar-levels">
                    <?php foreach (C::LEVELS as $lv => $def): ?>
                        <?php if (!C::applies($sectionKey, $lv)) { continue; } ?>
                        <label class="sar-check"><input type="checkbox" name="levels[]" value="<?= $h($lv) ?>"<?= in_array($lv, $oldLevels, true) ? ' checked' : '' ?>> <span><?= $h($def['label']) ?></span></label>
                    <?php endforeach; ?>
                </div>
            </fieldset>
            <div class="fx-g2 sar-g">
                <label class="fx-f"><span>À partir du (facultatif)</span><input class="fx-in" type="date" name="starts_at" value="<?= $h($old['starts_at'] ?? '') ?>"></label>
                <label class="fx-f"><span>Jusqu’au (facultatif)</span><input class="fx-in" type="date" name="ends_at" value="<?= $h($old['ends_at'] ?? '') ?>"></label>
            </div>
            <label class="fx-f"><span>Motif (obligatoire pour un refus, une dérogation individuelle ou le niveau Gérer)</span><input class="fx-in" type="text" name="reason" maxlength="<?= (int) C::REASON_MAX ?>" value="<?= $h($old['reason'] ?? '') ?>"></label>
            <p class="fx-help">Pour une dérogation à un membre précis, ouvrez sa page dans l’onglet « Droits d’un membre ».</p>
            <div class="fx-row"><button type="submit" class="btn pri"<?= !empty($ready) ? '' : ' disabled' ?>>Voir l’aperçu de l’effet</button></div>
        </form>
    </section>
</div>
<?php
$sseContent = ob_get_clean();
require dirname(__DIR__) . '/_layout.php';
