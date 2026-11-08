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
<div class="bo-org">
<?php if ($flashOk): ?>
<p class="ath-flash ath-flash--ok" role="status"><?= $h((string) $flashOk) ?></p>
<?php endif; ?>
<?php if ($flashErr): ?>
<p class="ath-flash ath-flash--err" role="alert"><?= $h((string) $flashErr) ?></p>
<?php endif; ?>

<form method="post" action="<?= $h(url('back-office/organisation/noms')) ?>" class="ath-rise">
    <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
    <section class="bo-org-panel">
        <header class="bo-org-panel__head">
            <div class="bo-org-panel__titles">
                <h2 class="bo-org-panel__title">Affichage des noms</h2>
                <p class="bo-org-panel__hint">Partout sur le site, le prénom et le nom du personnage passent avant l’indicatif et le pseudo, qui restent visibles en second. La carte tactique, ATAK et les ordres gardent l’indicatif en premier.</p>
            </div>
        </header>
        <div class="bo-org-panel__body">
            <div class="bo-org-group">
                <h3 class="bo-org-group__title">Dans les listes et les espaces serrés</h3>
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
            </div>

            <div class="bo-org-group">
                <h3 class="bo-org-group__title">Sur la fiche d’un membre</h3>
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
            </div>

            <div class="bo-org-group">
                <h3 class="bo-org-group__title">Options</h3>
                <div class="bo-org-switches">
                    <label class="bo-org-switch"><input type="checkbox" name="show_grade" value="1" <?= $settings['show_grade'] ? 'checked' : '' ?>><span><b>Ajouter le grade devant le nom (<?= $h(MemberName::format($sample, MemberName::SHORT, true)) ?>)</b><small>Uniquement là où le grade n’est pas déjà affiché à côté.</small></span></label>
                    <label class="bo-org-switch"><input type="checkbox" name="trigrams_enabled" value="1" <?= $settings['trigrams_enabled'] ? 'checked' : '' ?>><span><b>Utiliser les trigrammes</b><small>Fiches, organigramme, annuaire, paraphes des documents.</small></span></label>
                </div>
            </div>
        </div>
        <footer class="bo-org-panel__foot">
            <span class="bo-org-panel__hint" style="margin:0 auto 0 0">Si un membre n’a renseigné ni prénom ni nom, son pseudo s’affiche à la place. Un membre masqué reste masqué : ni son nom ni son trigramme n’apparaissent.</span>
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
<div class="bo-org-stats">
    <div class="bo-org-stat"><span class="bo-org-stat__label">Membres</span><span class="bo-org-stat__value"><?= (int) $total ?></span></div>
    <div class="bo-org-stat bo-org-stat--ok"><span class="bo-org-stat__label">Avec trigramme</span><span class="bo-org-stat__value"><?= (int) ($total - $missing) ?></span></div>
    <div class="bo-org-stat<?= $missing > 0 ? ' bo-org-stat--warn' : '' ?>"><span class="bo-org-stat__label">Sans trigramme</span><span class="bo-org-stat__value"><?= (int) $missing ?></span></div>
</div>

<section class="bo-org-panel ath-rise" aria-labelledby="mnames-reg-title">
    <header class="bo-org-panel__head">
        <div class="bo-org-panel__titles">
            <h2 class="bo-org-panel__title" id="mnames-reg-title">Trigrammes des membres</h2>
            <p class="bo-org-panel__hint">Règle d’état-major : 1re lettre du prénom et 2 premières lettres du nom (Jean DUPONT → JDU), sans accents ni particules. Si le trigramme est déjà pris dans la communauté, la variante suivante est proposée (JDP, JDT, JED…). Un trigramme attribué ne change plus, même si le nom change : modifiez-le ici ou recalculez-le sur demande.</p>
        </div>
        <?php if ($missing > 0): ?>
        <div class="bo-org-panel__acts">
            <form method="post" action="<?= $h(url('back-office/organisation/noms/attribuer')) ?>">
                <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
                <button type="submit" class="ath-btn ath-btn--solid">Attribuer les trigrammes manquants (<?= (int) $missing ?>)</button>
            </form>
        </div>
        <?php endif; ?>
    </header>
    <?php if ($members === []): ?>
    <div class="bo-org-empty">
        <p class="bo-org-empty__title">Aucun membre</p>
        <p class="bo-org-empty__text">Aucun membre dans la communauté pour l’instant.</p>
    </div>
    <?php else: ?>
    <div class="bo-org-bar">
        <input type="search" class="ath-field__input bo-org-search" id="mn-search" placeholder="Rechercher un membre" aria-label="Rechercher un membre" autocomplete="off">
        <div class="bo-org-chips">
            <a class="bo-org-chip" href="<?= $h(url('back-office/organisation/noms')) ?>"<?= $onlyMissing ? '' : ' aria-current="true"' ?>>Tous (<?= (int) $total ?>)</a>
            <a class="bo-org-chip" href="<?= $h(url('back-office/organisation/noms?filtre=sans')) ?>"<?= $onlyMissing ? ' aria-current="true"' : '' ?>>Sans trigramme (<?= (int) $missing ?>)</a>
        </div>
    </div>
    <div class="ath-table-wrap">
        <table class="bo-org-table">
            <thead>
                <tr><th>Membre</th><th>Indicatif</th><th>Trigramme</th><th>Action</th></tr>
            </thead>
            <tbody id="mn-body">
            <?php foreach ($members as $m):
                $id = (int) ($m['id'] ?? 0);
                $tri = (string) ($m['trigram'] ?? '');
                if ($onlyMissing && $tri !== '') {
                    continue;
                }
                $name = MemberName::full($m);
                $second = MemberName::secondary($m, $name);
            ?>
                <tr data-q="<?= $h(mb_strtolower($name . ' ' . $second . ' ' . MemberName::callsign($m) . ' ' . $tri)) ?>">
                    <td>
                        <a class="bo-org-name" href="<?= $h(url('personnel/' . $id)) ?>"><?= $h($name) ?></a>
                        <?php if ($second !== ''): ?><span class="bo-org-second"><?= $h($second) ?></span><?php endif; ?>
                    </td>
                    <td data-label="Indicatif" class="bo-org-muted"><?= $h(MemberName::callsign($m) !== '' ? MemberName::callsign($m) : '—') ?></td>
                    <td data-label="Trigramme">
                        <form method="post" action="<?= $h(url('back-office/organisation/noms/trigramme')) ?>" class="bo-org-tri">
                            <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
                            <input type="hidden" name="user_id" value="<?= $id ?>">
                            <input class="ath-field__input bo-org-mono" id="mnames-t-<?= $id ?>" aria-label="Trigramme de <?= $h($name) ?>" name="trigram" value="<?= $h($tri) ?>" maxlength="3" size="4" pattern="[A-Za-z]{3}" autocomplete="off" spellcheck="false" placeholder="—">
                            <button type="submit" class="ath-btn">Enregistrer</button>
                        </form>
                    </td>
                    <td>
                        <form method="post" action="<?= $h(url('back-office/organisation/noms/trigramme')) ?>">
                            <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
                            <input type="hidden" name="user_id" value="<?= $id ?>">
                            <input type="hidden" name="action" value="recalculer">
                            <button type="submit" class="ath-btn" title="Recalculer depuis le prénom et le nom actuels"><?= $tri === '' ? 'Attribuer' : 'Recalculer depuis le nom' ?></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <p class="bo-org-nomatch" id="mn-nomatch" hidden>Aucun membre ne correspond à la recherche.</p>
    <?php endif; ?>
</section>
<script>
(function () {
  var q = document.getElementById('mn-search'), body = document.getElementById('mn-body'), none = document.getElementById('mn-nomatch');
  if (!q || !body) return;
  q.addEventListener('input', function () {
    var v = q.value.trim().toLowerCase(), shown = 0;
    body.querySelectorAll('tr').forEach(function (r) {
      var ok = v === '' || (r.getAttribute('data-q') || '').indexOf(v) !== -1;
      r.hidden = !ok;
      if (ok) shown++;
    });
    none.hidden = shown !== 0;
  });
})();
</script>
<?php endif; ?>
</div>
