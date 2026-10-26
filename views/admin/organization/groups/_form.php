<?php
declare(strict_types=1);

/**
 * Formulaire commun création / modification d’un groupe : trois étapes et l’aperçu en direct de la carte publique.
 *
 * @var array<string, mixed> $formGroup  valeurs (vide en création)
 * @var string $formAction
 * @var string $formSubmit
 * @var string $formCancel
 * @var string $formIdPrefix
 * @var list<array<string, mixed>> $parents
 * @var list<array<string, mixed>> $users
 * @var array<string, array<string, mixed>> $unitTypes
 * @var string $currentType
 */

$fg = is_array($formGroup ?? null) ? $formGroup : [];
$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$isEdit = isset($fg['id']);
$tagsLines = '';
$pt = $fg['public_tags'] ?? null;
if (is_string($pt) && $pt !== '') {
    $dec = json_decode($pt, true);
    $tagsLines = is_array($dec) ? implode("\n", $dec) : $pt;
}
$sop = array_key_exists('show_on_public_page', $fg) ? (int) $fg['show_on_public_page'] === 1 : true;
$slots = $fg['public_open_slots'] ?? null;
$slotsValue = $slots === null || $slots === '' ? '' : ((int) $slots === -1 ? 'ouvert' : (string) (int) $slots);
$accent = trim((string) ($fg['public_accent_color'] ?? ''));
$palette = ['#1e4f80' => 'Bleu', '#0b8a5c' => 'Vert', '#7c3aed' => 'Violet', '#b45309' => 'Ocre', '#b91c1c' => 'Rouge', '#334155' => 'Ardoise', '#0e7490' => 'Cyan'];
$nameOf = static fn (array $u): string => trim((string) (($u['callsign'] ?? '') !== '' ? $u['callsign'] . ' · ' . ($u['display_name'] ?? '') : ($u['display_name'] ?? $u['email'] ?? '')), ' ·');
$users = is_array($users ?? null) ? $users : [];
usort($users, static fn (array $a, array $b): int => strcasecmp($nameOf($a), $nameOf($b)));
?>
<form method="post" action="<?= $h($formAction) ?>" class="bo-grp-form" data-grp-form>
    <?= \App\Core\Csrf::field() ?>
    <div class="bo-grp-form__col">
        <section class="bo-grp-panel"><div class="bo-grp-panel__body">
            <fieldset class="bo-grp-fieldset" aria-label="Le groupe">
                <div class="bo-grp-fieldset__step"><span class="bo-grp-fieldset__num">1</span><span class="bo-grp-fieldset__legend">Le groupe</span></div>
                <div class="bo-grp-row">
                    <div class="bo-grp-field" style="grid-column: span 2;">
                        <label for="name">Nom du groupe *</label>
                        <input type="text" id="name" name="name" required maxlength="190" value="<?= $h((string) ($fg['name'] ?? '')) ?>" placeholder="1er Groupe de Combat" data-grp-in="name">
                    </div>
                    <div class="bo-grp-field">
                        <label for="code">Code court</label>
                        <input type="text" id="code" name="code" maxlength="20" value="<?= $h((string) ($fg['code'] ?? '')) ?>" placeholder="GC1" data-grp-in="code">
                        <small>Affiché dans le badge.</small>
                    </div>
                </div>
                <div class="bo-grp-row">
                    <div class="bo-grp-field">
                        <label for="parent_id">Rattaché à</label>
                        <select id="parent_id" name="parent_id">
                            <option value="">Aucun (au sommet)</option>
                            <?php foreach ($parents as $p): ?>
                            <option value="<?= (int) $p['id'] ?>" <?= (int) ($fg['parent_id'] ?? 0) === (int) $p['id'] ? 'selected' : '' ?>><?= $h((string) $p['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="bo-grp-field">
                        <label for="commander_user_id">Chef de groupe</label>
                        <select id="commander_user_id" name="commander_user_id">
                            <option value="">À désigner plus tard</option>
                            <?php foreach ($users as $u): ?>
                            <option value="<?= (int) $u['id'] ?>" <?= (int) ($fg['commander_user_id'] ?? 0) === (int) $u['id'] ? 'selected' : '' ?>><?= $h($nameOf($u)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </fieldset>
        </div></section>

        <section class="bo-grp-panel"><div class="bo-grp-panel__body">
            <fieldset class="bo-grp-fieldset" aria-label="La carte sur la page publique">
                <div class="bo-grp-fieldset__step"><span class="bo-grp-fieldset__num">2</span><span class="bo-grp-fieldset__legend">La carte sur la page publique</span></div>
                <label class="bo-grp-toggle">
                    <input type="checkbox" name="show_on_public_page" value="1" <?= $sop ? 'checked' : '' ?> data-grp-in="visible">
                    <span><strong>Afficher ce groupe sur la page publique</strong><br><small style="color:var(--ath-muted,#64748b);">Décochez pour une cellule interne.</small></span>
                </label>
                <div class="bo-grp-field">
                    <label for="public_blurb">Présentation</label>
                    <textarea id="public_blurb" name="public_blurb" rows="3" maxlength="600" placeholder="Groupe d’assaut de la compagnie, spécialisé dans le combat en zone urbaine." data-grp-in="blurb"><?= $h((string) ($fg['public_blurb'] ?? '')) ?></textarea>
                    <small>Deux phrases suffisent : le rôle du groupe et ce qu’il cherche.</small>
                </div>
                <div class="bo-grp-field">
                    <span class="bo-grp-label" id="<?= $h($formIdPrefix) ?>-color-label">Couleur</span>
                    <div class="bo-grp-colors" role="group" aria-labelledby="<?= $h($formIdPrefix) ?>-color-label">
                        <?php foreach ($palette as $hex => $label): ?>
                        <button type="button" class="bo-grp-swatch" style="background: <?= $h($hex) ?>" title="<?= $h($label) ?>" aria-label="<?= $h($label) ?>" aria-pressed="<?= strcasecmp($accent, $hex) === 0 ? 'true' : 'false' ?>" data-grp-swatch="<?= $h($hex) ?>"></button>
                        <?php endforeach; ?>
                        <input type="color" value="<?= $h(preg_match('/^#[0-9a-f]{6}$/i', $accent) ? $accent : '#1e4f80') ?>" aria-label="Autre couleur" data-grp-picker>
                        <input type="text" id="public_accent_color" name="public_accent_color" maxlength="7" value="<?= $h($accent) ?>" placeholder="#1E4F80" aria-label="Code couleur" data-grp-in="accent">
                    </div>
                </div>
                <div class="bo-grp-row">
                    <div class="bo-grp-field">
                        <label for="public_capacity">Effectif maximum</label>
                        <input type="number" min="0" id="public_capacity" name="public_capacity" value="<?= $h((string) ($fg['public_capacity'] ?? '')) ?>" placeholder="8" data-grp-in="capacity">
                    </div>
                    <div class="bo-grp-field">
                        <label for="public_open_slots">Places ouvertes</label>
                        <input type="text" id="public_open_slots" name="public_open_slots" value="<?= $h($slotsValue) ?>" placeholder="2, ou « ouvert »" data-grp-in="slots">
                        <small>Laissez vide pour ne rien afficher.</small>
                    </div>
                </div>
                <div class="bo-grp-field">
                    <label for="public_tags">Mots-clés (un par ligne)</label>
                    <textarea id="public_tags" name="public_tags" rows="3" placeholder="Assaut&#10;Combat urbain" data-grp-in="tags"><?= $h($tagsLines) ?></textarea>
                </div>
            </fieldset>
        </div></section>

        <section class="bo-grp-panel"><div class="bo-grp-panel__body">
            <details class="bo-grp-more">
                <summary>3. Options avancées (type, adresse, ordre, dates)</summary>
                <div class="bo-grp-fieldset">
                    <div class="bo-grp-row">
                        <div class="bo-grp-field">
                            <label for="struct_type">Type de structure</label>
                            <select id="struct_type" name="struct_type">
                                <?php foreach ($unitTypes as $typeId => $meta): ?>
                                <option value="<?= $h((string) $typeId) ?>" <?= (string) $typeId === $currentType ? 'selected' : '' ?>><?= $h((string) ($meta['label'] ?? $typeId)) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small>Aligné sur l’ORBAT, modifiable plus tard.</small>
                        </div>
                        <div class="bo-grp-field">
                            <label for="slug">Adresse courte dans l’URL</label>
                            <input type="text" id="slug" name="slug" value="<?= $h((string) ($fg['slug'] ?? '')) ?>" placeholder="Créée depuis le nom si vide">
                        </div>
                        <div class="bo-grp-field">
                            <label for="display_order">Ordre d’affichage</label>
                            <input type="number" id="display_order" name="display_order" value="<?= (int) ($fg['display_order'] ?? 0) ?>">
                            <small>Les petits nombres passent en premier.</small>
                        </div>
                    </div>
                    <?php
                    $unitRow = $fg;
                    $idPrefix = $formIdPrefix;
                    require base_path('views/admin/organization/partials/unit_public_dates_fields.php');
                    ?>
                </div>
            </details>
        </div></section>
    </div>

    <aside class="bo-grp-form__aside" aria-label="Aperçu">
        <p class="bo-grp-label" style="margin:0;font-size:12px;font-weight:800;letter-spacing:.04em;text-transform:uppercase;color:var(--ath-muted,#64748b);">Aperçu en direct</p>
        <?php $vitrineGroup = $fg; require __DIR__ . '/_vitrine_preview.php'; ?>
        <p class="bo-grp-note" style="margin:0;">La carte telle qu’elle apparaîtra sur la vitrine de la communauté.</p>
        <div class="bo-grp-submit">
            <button type="submit" class="ath-btn ath-btn--solid"><?= $h($formSubmit) ?></button>
            <a href="<?= $h($formCancel) ?>" class="ath-btn">Annuler</a>
        </div>
    </aside>
</form>
<script>
(function () {
    var form = document.querySelector('[data-grp-form]');
    if (!form) { return; }
    var pv = form.querySelector('[data-grp-pv]');
    var q = function (k) { return form.querySelector('[data-grp-in="' + k + '"]'); };
    var el = function (k) { return pv.querySelector('[data-grp-pv-' + k + ']'); };
    var hexOk = function (v) { return /^#[0-9a-f]{6}$/i.test(v); };
    function render() {
        var name = q('name').value.trim();
        el('name').textContent = name || 'Nom du groupe';
        el('code').textContent = q('code').value.trim();
        var blurb = q('blurb').value.trim();
        var b = el('blurb');
        b.textContent = blurb || 'Pas encore de présentation : écrivez deux phrases sur le rôle du groupe.';
        b.classList.toggle('bo-grp-vitrine__blurb--empty', blurb === '');
        var tags = el('tags');
        tags.textContent = '';
        q('tags').value.split(/\r?\n/).map(function (t) { return t.trim(); }).filter(Boolean).forEach(function (t) {
            var s = document.createElement('span'); s.className = 'bo-grp-vitrine__tag'; s.textContent = t; tags.appendChild(s);
        });
        var cap = parseInt(q('capacity').value, 10);
        el('capacity').innerHTML = cap > 0 ? 'Effectif max <b>' + cap + '</b>' : '';
        var slots = q('slots').value.trim().toLowerCase();
        var n = parseInt(slots, 10);
        var label = slots === '' ? '' : (slots === 'ouvert' || n === -1 ? 'Recrutement ouvert' : (n > 0 ? n + (n > 1 ? ' places ouvertes' : ' place ouverte') : ''));
        el('slots').textContent = '';
        if (label) { var bb = document.createElement('b'); bb.textContent = label; el('slots').appendChild(bb); }
        el('hidden').hidden = q('visible').checked;
        var acc = q('accent').value.trim();
        pv.style.setProperty('--grp-accent', hexOk(acc) ? acc : '#1e4f80');
        form.querySelectorAll('[data-grp-swatch]').forEach(function (s) {
            s.setAttribute('aria-pressed', s.getAttribute('data-grp-swatch').toLowerCase() === acc.toLowerCase() ? 'true' : 'false');
        });
    }
    form.querySelectorAll('[data-grp-swatch]').forEach(function (s) {
        s.addEventListener('click', function () { q('accent').value = s.getAttribute('data-grp-swatch'); render(); });
    });
    var picker = form.querySelector('[data-grp-picker]');
    if (picker) { picker.addEventListener('input', function () { q('accent').value = picker.value; render(); }); }
    form.addEventListener('input', render);
    form.addEventListener('change', render);
})();
</script>
