<?php
declare(strict_types=1);
use App\Support\SseAccessCatalog as C;

/**
 * Droits d'accès SSE : « que peut faire ce membre ? », avec l'origine de chaque droit, ses dérogations individuelles et
 * le formulaire pour en ajouter. Sans membre choisi : la recherche dans l'annuaire (membres visibles seulement).
 *
 * @var array{user_id: int, label: string, unit: string}|null $member
 * @var array{entry: bool, sections: list<array<string, mixed>>}|null $effective
 * @var list<array<string, mixed>>|null $mine
 * @var string|null $q
 * @var list<array{user_id: int, label: string, unit: string}>|null $results
 * @var array<string, mixed> $old
 * @var string $csrfToken
 * @var string $base
 */
ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$tab = 'membre';
require __DIR__ . '/_head.php';
$old = is_array($old ?? null) ? $old : [];
$member = $member ?? null;
?>
<div class="fx sar">
<?php if ($member === null): ?>
    <div class="fx-th">
        <div>
            <h1>Droits d’un membre</h1>
            <p>Cherchez un membre pour voir ce qu’il peut faire dans chaque section du bureau SSE, et d’où vient chaque droit. Seuls les membres que l’annuaire vous montre sont proposés.</p>
        </div>
    </div>
    <section class="fx-card" aria-labelledby="sar-search-title">
        <h2 id="sar-search-title" class="sar-h2">Chercher un membre</h2>
        <form method="get" action="<?= $h(url($base . '/membre')) ?>" class="sar-form" role="search">
            <label class="fx-f"><span>Nom, indicatif ou matricule</span><input class="fx-in" type="search" name="q" value="<?= $h($q ?? '') ?>" maxlength="60" autocomplete="off"></label>
            <div class="fx-row"><button type="submit" class="btn pri">Chercher</button></div>
        </form>
        <?php if (($q ?? '') !== ''): ?>
            <?php if (($results ?? []) === []): ?>
                <p class="sar-empty"><b>Aucun membre</b> Aucun membre visible ne correspond à cette recherche.</p>
            <?php else: ?>
                <ul class="sar-list">
                    <?php foreach ($results as $m): ?>
                        <li><a href="<?= $h(url($base . '/membre/' . (int) $m['user_id'])) ?>"><?= $h($m['label']) ?></a><?php if ($m['unit'] !== ''): ?> <small class="fx-help"><?= $h($m['unit']) ?></small><?php endif; ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        <?php endif; ?>
    </section>
<?php else: ?>
    <?php
    $sections = $effective['sections'] ?? [];
    $sourceLabels = ['legacy' => 'Droit d’origine', 'allow_rule' => 'Règle d’accord', 'deny_rule' => 'Refus explicite', 'door_closed' => 'Porte fermée', 'platform' => 'Administrateur', 'none' => 'Aucun droit'];
    $mine = $mine ?? [];
    $oldLevels = is_array($old['levels'] ?? null) ? array_map('strval', $old['levels']) : [];
    $self = $base . '/membre/' . (int) $member['user_id'];
    ?>
    <div class="fx-th">
        <div>
            <h1><?= $h($member['label']) ?></h1>
            <p>Droits effectifs dans le bureau SSE : le droit d’origine, étendu ou restreint par les règles de la communauté. L’engagement de confidentialité se juge à chaque session et n’est pas montré ici.</p>
        </div>
        <a class="btn" href="<?= $h(url($base . '/membre')) ?>">Chercher un autre membre</a>
    </div>

    <?php if (empty($effective['entry'])): ?>
        <div class="fx-note sar-warn" role="status"><b>Pas de droit d’entrer.</b><p>Ce membre n’a pas le droit d’entrer au bureau SSE : aucune règle ne le lui donne, et tous ses droits ci-dessous sont fermés.</p></div>
    <?php endif; ?>

    <section class="fx-card" aria-labelledby="sar-eff-title">
        <h2 id="sar-eff-title" class="sar-h2">Droits effectifs</h2>
        <p class="fx-help">Passez sur « Oui » ou « Non » pour lire l’explication complète ; les lecteurs d’écran la lisent directement.</p>
        <div class="fx-tbw">
            <table class="fx-tb sar-grid resp">
                <caption class="sr-only">Droits effectifs du membre, par section et par niveau</caption>
                <thead><tr><th scope="col">Section</th><?php foreach (C::LEVELS as $def): ?><th scope="col"><?= $h($def['label']) ?></th><?php endforeach; ?></tr></thead>
                <tbody>
                <?php foreach ($sections as $row): ?>
                    <tr>
                        <th scope="row" data-label="Section"><a href="<?= $h(url($base . '/section/' . $row['key'])) ?>"><?= $h($row['label']) ?></a></th>
                        <?php foreach (C::LEVELS as $lv => $def): $cell = $row['cells'][$lv]; ?>
                            <td data-label="<?= $h($def['label']) ?>">
                                <?php if (!$cell['applies']): ?>
                                    <span class="sar-na">Sans objet</span>
                                <?php else: ?>
                                    <span class="fx-flg <?= $cell['allowed'] ? 'sar-allow' : 'sar-deny' ?>" title="<?= $h($cell['reason']) ?>"><?= $cell['allowed'] ? 'Oui' : 'Non' ?></span>
                                    <small class="fx-help sar-why"><?= $h($sourceLabels[$cell['source']] ?? '') ?></small>
                                    <span class="sr-only"><?= $h($cell['reason']) ?></span>
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="fx-card" aria-labelledby="sar-mine-title">
        <h2 id="sar-mine-title" class="sar-h2">Dérogations individuelles</h2>
        <?php if ($mine === []): ?>
            <p class="sar-empty"><b>Aucune</b> Ce membre n’a aucune dérogation individuelle.</p>
        <?php else: ?>
            <div class="fx-tbw">
                <table class="fx-tb resp">
                    <thead><tr><th scope="col">Section</th><th scope="col">Niveau</th><th scope="col">Effet</th><th scope="col">Période</th><th scope="col">Motif</th><th scope="col"><span class="sr-only">Retirer</span></th></tr></thead>
                    <tbody>
                    <?php foreach ($mine as $r): ?>
                        <tr>
                            <td data-label="Section"><?= $h($r['section_label']) ?></td>
                            <td data-label="Niveau"><?= $h($r['level_label']) ?></td>
                            <td data-label="Effet"><span class="fx-flg <?= $r['effect'] === 'deny' ? 'sar-deny' : 'sar-allow' ?>"><?= $r['effect'] === 'deny' ? 'Refus' : 'Accord' ?></span></td>
                            <td data-label="Période"><?= $r['starts_at'] === null && $r['ends_at'] === null ? 'Sans limite' : $h(($r['starts_at'] !== null ? 'du ' . substr((string) $r['starts_at'], 0, 10) . ' ' : '') . ($r['ends_at'] !== null ? 'jusqu’au ' . substr((string) $r['ends_at'], 0, 10) : '')) ?></td>
                            <td data-label="Motif"><?= $h($r['reason']) ?></td>
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

    <section class="fx-card" aria-labelledby="sar-der-title">
        <h2 id="sar-der-title" class="sar-h2">Ajouter une dérogation</h2>
        <form method="post" action="<?= $h(url($self . '/derogation')) ?>" class="sar-form">
            <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
            <input type="hidden" name="do" value="preview">
            <div class="fx-g2 sar-g">
                <label class="fx-f"><span>Section</span>
                    <select class="fx-in" name="section" required>
                        <option value="">Choisir une section</option>
                        <?php foreach (C::SECTIONS as $key => $def): ?>
                            <option value="<?= $h($key) ?>"<?= ($old['section'] ?? '') === $key ? ' selected' : '' ?>><?= $h($def['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <fieldset class="sar-fs">
                    <legend>Effet</legend>
                    <label class="sar-check"><input type="radio" name="effect" value="allow" required<?= ($old['effect'] ?? 'allow') === 'allow' ? ' checked' : '' ?>> <span>Accorder</span></label>
                    <label class="sar-check"><input type="radio" name="effect" value="deny"<?= ($old['effect'] ?? '') === 'deny' ? ' checked' : '' ?>> <span>Refuser</span></label>
                </fieldset>
            </div>
            <fieldset class="sar-fs">
                <legend>Niveaux de droit</legend>
                <div class="sar-levels">
                    <?php foreach (C::LEVELS as $lv => $def): ?>
                        <label class="sar-check"><input type="checkbox" name="levels[]" value="<?= $h($lv) ?>"<?= in_array($lv, $oldLevels, true) ? ' checked' : '' ?>> <span><?= $h($def['label']) ?></span></label>
                    <?php endforeach; ?>
                </div>
            </fieldset>
            <div class="fx-g2 sar-g">
                <label class="fx-f"><span>À partir du (facultatif)</span><input class="fx-in" type="date" name="starts_at" value="<?= $h($old['starts_at'] ?? '') ?>"></label>
                <label class="fx-f"><span>Jusqu’au (facultatif)</span><input class="fx-in" type="date" name="ends_at" value="<?= $h($old['ends_at'] ?? '') ?>"></label>
            </div>
            <label class="fx-f"><span>Motif (obligatoire)</span><input class="fx-in" type="text" name="reason" maxlength="<?= (int) C::REASON_MAX ?>" required minlength="<?= (int) C::REASON_MIN ?>" value="<?= $h($old['reason'] ?? '') ?>"></label>
            <div class="fx-row"><button type="submit" class="btn pri">Voir l’aperçu de l’effet</button></div>
        </form>
    </section>
<?php endif; ?>
</div>
<?php
$sseContent = ob_get_clean();
require dirname(__DIR__) . '/_layout.php';
