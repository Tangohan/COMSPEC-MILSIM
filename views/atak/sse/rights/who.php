<?php
declare(strict_types=1);
use App\Support\SseAccessCatalog as C;

/**
 * Droits d'accès SSE : « qui a accès à cette section, à ce niveau ? ». Membres nommés comme dans l'annuaire ; les membres
 * anonymisés par l'annuaire ne sont que comptés, jamais nommés.
 *
 * @var string $section
 * @var string $level
 * @var array{members: list<array<string, mixed>>, anonymized: int, truncated: bool, evaluated: int}|null $result
 * @var string $base
 */
ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$tab = 'qui';
require __DIR__ . '/_head.php';
?>
<div class="fx sar">
    <div class="fx-th">
        <div>
            <h1>Qui a accès</h1>
            <p>Choisissez une section et un niveau : la liste montre les membres qui ont ce droit aujourd’hui, avec l’origine du droit. Elle suit les mêmes règles de visibilité que l’annuaire.</p>
        </div>
    </div>
    <section class="fx-card" aria-labelledby="sar-who-title">
        <h2 id="sar-who-title" class="sar-h2">Section et niveau</h2>
        <form method="get" action="<?= $h(url($base . '/qui')) ?>" class="sar-form">
            <div class="fx-g2 sar-g">
                <label class="fx-f"><span>Section</span>
                    <select class="fx-in" name="section" required>
                        <option value="">Choisir une section</option>
                        <?php foreach (C::SECTIONS as $key => $def): ?>
                            <option value="<?= $h($key) ?>"<?= $section === $key ? ' selected' : '' ?>><?= $h($def['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="fx-f"><span>Niveau</span>
                    <select class="fx-in" name="niveau">
                        <?php foreach (C::LEVELS as $lv => $def): ?>
                            <option value="<?= $h($lv) ?>"<?= $level === $lv ? ' selected' : '' ?>><?= $h($def['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
            <div class="fx-row"><button type="submit" class="btn pri">Afficher</button></div>
        </form>
    </section>
    <?php if ($result !== null): ?>
        <section class="fx-card" aria-labelledby="sar-who-res">
            <h2 id="sar-who-res" class="sar-h2"><?= $h(C::sectionLabel($section)) ?> · <?= $h(C::levelLabel($level)) ?></h2>
            <p class="fx-help">Membres ayant ce droit : <b><?= count($result['members']) + (int) $result['anonymized'] ?></b> · Membres examinés : <b><?= (int) $result['evaluated'] ?></b></p>
            <p class="fx-help">Seuls les membres ayant le droit d’entrer au bureau SSE sont comptés.</p>
            <?php if ($result['truncated']): ?>
                <p class="fx-note" role="status">La communauté compte plus de membres que la liste n’en examine : seuls les premiers (par ordre alphabétique) sont évalués.</p>
            <?php endif; ?>
            <?php if ($result['members'] === [] && (int) $result['anonymized'] === 0): ?>
                <p class="sar-empty"><b>Personne</b> Aucun membre visible n’a ce droit.</p>
            <?php else: ?>
                <div class="fx-tbw">
                    <table class="fx-tb resp">
                        <thead><tr><th scope="col">Membre</th><th scope="col">Unité</th><th scope="col">Origine du droit</th></tr></thead>
                        <tbody>
                        <?php foreach ($result['members'] as $m): ?>
                            <tr>
                                <td data-label="Membre"><a href="<?= $h(url($base . '/membre/' . (int) $m['user_id'])) ?>"><?= $h($m['label']) ?></a></td>
                                <td data-label="Unité"><?= $m['unit'] !== '' ? $h($m['unit']) : '—' ?></td>
                                <td data-label="Origine du droit"><?= $h($m['reason']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ((int) $result['anonymized'] > 0): ?>
                    <p class="fx-help">Membres dont l’identité est protégée par l’annuaire (non nommés) : <b><?= (int) $result['anonymized'] ?></b></p>
                <?php endif; ?>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</div>
<?php
$sseContent = ob_get_clean();
require dirname(__DIR__) . '/_layout.php';
