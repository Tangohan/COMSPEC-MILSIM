<?php
declare(strict_types=1);

/**
 * Noms et trigrammes — format d’affichage des noms de la communauté et registre des trigrammes.
 *
 * @var bool $memberNamesSchemaReady
 * @var array{list_format: string, card_format: string, show_grade: bool, trigrams_enabled: bool} $memberNamesSettings
 * @var list<array<string, mixed>> $memberNamesMembers
 * @var string $memberNamesFilter
 * @var string $memberNamesCsrf
 */

use App\Support\MemberName;

$schemaReady = !empty($memberNamesSchemaReady);
$settings = is_array($memberNamesSettings ?? null) ? $memberNamesSettings : \App\Services\Personnel\MemberNameSettings::DEFAULTS;
$members = is_array($memberNamesMembers ?? null) ? $memberNamesMembers : [];
$onlyMissing = ($memberNamesFilter ?? '') === 'sans';
$csrf = htmlspecialchars((string) ($memberNamesCsrf ?? ''), ENT_QUOTES, 'UTF-8');
$flashOk = \App\Core\Session::getFlash('success');
$flashErr = \App\Core\Session::getFlash('error');
$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

$sample = ['first_name' => 'Jean', 'last_name' => 'Dupont', 'display_name' => 'Jeanjean', 'callsign' => 'Viper 2-1', 'trigram' => 'JDU', 'grade_short' => 'Sgt'];
$formats = [
    MemberName::SHORT => 'Prénom et initiale du nom',
    MemberName::FULL => 'Prénom et nom complet',
    MemberName::TRIGRAM => 'Trigramme',
    MemberName::CALLSIGN => 'Indicatif radio',
];
$missing = 0;
foreach ($members as $m) {
    if ((string) ($m['trigram'] ?? '') === '') {
        $missing++;
    }
}
$total = count($members);
?>
<?php if ($flashOk): ?>
<p class="ath-flash ath-flash--ok" role="status"><?= $h((string) $flashOk) ?></p>
<?php endif; ?>
<?php if ($flashErr): ?>
<p class="ath-flash ath-flash--err" role="alert"><?= $h((string) $flashErr) ?></p>
<?php endif; ?>

<div class="bo-mn">
<form method="post" action="<?= $h(url('back-office/organisation/noms')) ?>" class="bo-mn-rules ath-rise">
    <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
    <section class="bo-mn-panel">
        <header class="bo-mn-panel__head">
            <h2 class="bo-mn-panel__title">Affichage des noms</h2>
        </header>
        <div class="bo-mn-panel__body">
            <p class="bo-mn-sub">Partout sur le site, le prénom et le nom du personnage passent avant l’indicatif et le pseudo, qui restent visibles en second. La carte tactique, ATAK et les ordres gardent l’indicatif en premier.</p>

            <p class="bo-mn-sub">Dans les listes et les espaces serrés</p>
            <div class="ath-choice-grid">
                <?php foreach ($formats as $f => $fLabel): ?>
                <label class="ath-choice">
                    <input type="radio" name="list_format" value="<?= $h($f) ?>" <?= $settings['list_format'] === $f ? 'checked' : '' ?>>
                    <span class="ath-choice__body">
                        <span class="ath-choice__name"><?= $h($fLabel) ?></span>
                        <span class="ath-choice__desc"><?= $h(MemberName::format($sample, $f)) ?></span>
                    </span>
                </label>
                <?php endforeach; ?>
            </div>

            <p class="bo-mn-sub">Sur la fiche d’un membre</p>
            <div class="ath-choice-grid">
                <?php foreach ($formats as $f => $fLabel): ?>
                <label class="ath-choice">
                    <input type="radio" name="card_format" value="<?= $h($f) ?>" <?= $settings['card_format'] === $f ? 'checked' : '' ?>>
                    <span class="ath-choice__body">
                        <span class="ath-choice__name"><?= $h($fLabel) ?></span>
                        <span class="ath-choice__desc"><?= $h(MemberName::format($sample, $f)) ?></span>
                    </span>
                </label>
                <?php endforeach; ?>
            </div>

            <label class="bo-mn-check"><input type="checkbox" name="show_grade" value="1" <?= $settings['show_grade'] ? 'checked' : '' ?>> Ajouter le grade devant le nom (<?= $h(MemberName::format($sample, MemberName::SHORT, true)) ?>) là où le grade n’est pas déjà affiché à côté</label>
            <label class="bo-mn-check"><input type="checkbox" name="trigrams_enabled" value="1" <?= $settings['trigrams_enabled'] ? 'checked' : '' ?>> Utiliser les trigrammes (fiches, organigramme, annuaire, paraphes des documents)</label>
            <p class="bo-mn-sub">Si un membre n’a renseigné ni prénom ni nom, son pseudo s’affiche à la place. Un membre masqué reste masqué : ni son nom ni son trigramme n’apparaissent.</p>
        </div>
        <footer class="bo-mn-panel__foot">
            <button type="submit" class="ath-btn ath-btn--solid">Enregistrer l’affichage</button>
        </footer>
    </section>
</form>

<?php if (!$schemaReady): ?>
<div class="ath-note">
    <div class="ath-note__title">Trigrammes en préparation</div>
    <div class="ath-note__text">Les trigrammes seront disponibles après la prochaine mise à jour de la plateforme.</div>
</div>
<?php else: ?>
<section class="bo-mn-panel ath-rise" aria-labelledby="mnames-reg-title">
    <header class="bo-mn-panel__head">
        <h2 class="bo-mn-panel__title" id="mnames-reg-title">Trigrammes des membres</h2>
        <span class="bo-mn-panel__hint"><?= (int) $total ?> membre(s), <?= (int) $missing ?> sans trigramme</span>
        <span class="bo-mn-spacer" aria-hidden="true"></span>
        <?php if ($onlyMissing): ?>
        <a class="ath-btn" href="<?= $h(url('back-office/organisation/noms')) ?>">Voir tous les membres</a>
        <?php elseif ($missing > 0): ?>
        <a class="ath-btn" href="<?= $h(url('back-office/organisation/noms?filtre=sans')) ?>">Voir les membres sans trigramme</a>
        <?php endif; ?>
        <?php if ($missing > 0): ?>
        <form method="post" action="<?= $h(url('back-office/organisation/noms/attribuer')) ?>">
            <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
            <button type="submit" class="ath-btn ath-btn--solid">Attribuer les trigrammes manquants (<?= (int) $missing ?>)</button>
        </form>
        <?php endif; ?>
    </header>
    <div class="bo-mn-panel__body">
        <p class="bo-mn-sub">Règle d’état-major : 1re lettre du prénom et 2 premières lettres du nom (Jean DUPONT → JDU), sans accents ni particules. Si le trigramme est déjà pris dans la communauté, la variante suivante est proposée (JDP, JDT, JED…). Un trigramme attribué ne change plus, même si le nom change : modifiez-le ici ou recalculez-le sur demande.</p>
    </div>
    <?php if ($members === []): ?>
    <p class="bo-mn-empty">Aucun membre dans la communauté pour l’instant.</p>
    <?php else: ?>
    <div class="ath-table-wrap">
        <table class="ath-table bo-mn-table">
            <thead>
                <tr><th>MEMBRE</th><th>INDICATIF</th><th>TRIGRAMME</th><th>ACTION</th></tr>
            </thead>
            <tbody>
            <?php foreach ($members as $m):
                $id = (int) ($m['id'] ?? 0);
                $tri = (string) ($m['trigram'] ?? '');
                if ($onlyMissing && $tri !== '') {
                    continue;
                }
                $name = MemberName::full($m);
                $second = MemberName::secondary($m, $name);
            ?>
                <tr class="ath-row">
                    <td>
                        <a class="bo-mn-name" href="<?= $h(url('personnel/' . $id)) ?>"><?= $h($name) ?></a>
                        <?php if ($second !== ''): ?><span class="bo-mn-callsign"><?= $h($second) ?></span><?php endif; ?>
                    </td>
                    <td class="bo-mn-muted"><?= $h(MemberName::callsign($m) !== '' ? MemberName::callsign($m) : '—') ?></td>
                    <td class="bo-mn-mono">
                        <form method="post" action="<?= $h(url('back-office/organisation/noms/trigramme')) ?>" class="bo-mn-actions">
                            <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
                            <input type="hidden" name="user_id" value="<?= $id ?>">
                            <input class="ath-field__input bo-mn-mono" id="mnames-t-<?= $id ?>" aria-label="Trigramme de <?= $h($name) ?>" name="trigram" value="<?= $h($tri) ?>" maxlength="3" size="4" pattern="[A-Za-z]{3}" autocomplete="off" spellcheck="false" placeholder="—" style="max-width:5.5rem;text-transform:uppercase">
                            <button type="submit" class="ath-row-action">Enregistrer</button>
                        </form>
                    </td>
                    <td class="bo-mn-actions">
                        <form method="post" action="<?= $h(url('back-office/organisation/noms/trigramme')) ?>">
                            <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
                            <input type="hidden" name="user_id" value="<?= $id ?>">
                            <input type="hidden" name="action" value="recalculer">
                            <button type="submit" class="ath-row-action" title="Recalculer depuis le prénom et le nom actuels"><?= $tri === '' ? 'Attribuer' : 'Recalculer depuis le nom' ?></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</section>
<?php endif; ?>
</div>
