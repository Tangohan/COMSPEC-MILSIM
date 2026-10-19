<?php
declare(strict_types=1);

/** @var array<string,mixed>|null $fireTeam */
/** @var string $fireTeamKind */
/** @var list<array<string,mixed>> $fireTeamUnits */
/** @var list<array<string,mixed>> $fireTeamMaps */
/** @var list<array<string,mixed>> $fireTeamUsers */
/** @var list<array{value:string,label:string}> $fireTeamColors */
/** @var list<int> $fireTeamMemberIds */
/** @var int|null $fireTeamLeaderId */
/** @var bool $fireTeamsReady */

$team = is_array($fireTeam ?? null) ? $fireTeam : null;
$isEdit = $team !== null;
$kind = (string) ($fireTeamKind ?? ($team['kind'] ?? 'ephemeral'));
$isPermanent = $kind === 'permanent';
$units = is_array($fireTeamUnits ?? null) ? $fireTeamUnits : [];
$maps = is_array($fireTeamMaps ?? null) ? $fireTeamMaps : [];
$users = is_array($fireTeamUsers ?? null) ? $fireTeamUsers : [];
$colors = is_array($fireTeamColors ?? null) ? $fireTeamColors : [];
$memberIds = array_map('intval', is_array($fireTeamMemberIds ?? null) ? $fireTeamMemberIds : []);
$leaderId = isset($fireTeamLeaderId) && $fireTeamLeaderId !== null ? (int) $fireTeamLeaderId : 0;
$ready = !empty($fireTeamsReady);
$flashSuccess = \App\Core\Session::getFlash('success');
$flashError = \App\Core\Session::getFlash('error');
$dissolved = $isEdit && !empty($team['dissolved_at']);

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

$action = $isEdit
    ? url('back-office/atak/fire-teams/' . (int) ($team['id'] ?? 0) . '/update')
    : url('back-office/atak/fire-teams/store');

$currentColor = strtoupper((string) ($team['color'] ?? '#2563EB'));
if (preg_match('/^#[0-9A-F]{6}$/', $currentColor) !== 1) {
    $currentColor = '#2563EB';
}
$currentMapId = (int) ($team['map_id'] ?? 1);
$currentUnitId = (int) ($team['unit_id'] ?? 0);
$csrf = \App\Core\Csrf::token();
?>
<div class="bo-ft">
    <div class="bo-ft__frame">
        <header class="bo-ft__hero">
            <div class="bo-ft__hero-main">
                <p class="bo-ft__kicker"><a href="<?= $h(url('back-office/atak/fire-teams')) ?>">Équipes de feu</a> · <?= $isPermanent ? 'Organigramme' : 'Mission' ?></p>
                <h1 class="bo-ft__title"><?= $isEdit ? $h($team['label'] ?? 'Équipe de feu') : 'Nouvelle équipe de feu' ?></h1>
                <p class="bo-ft__lead">
                    <?= $isPermanent
                        ? 'Équipe durable, rattachée à une unité de l’organigramme. Elle ne modifie pas l’ORBAT.'
                        : 'Équipe le temps d’une mission sur une carte ATAK. Dissolvez-la en fin de session.' ?>
                </p>
            </div>
            <?php if ($isEdit && !$dissolved): ?>
                <div class="bo-ft__hero-actions">
                    <form method="post" action="<?= $h(url('back-office/atak/fire-teams/' . (int) $team['id'] . '/dissolve')) ?>" onsubmit="return confirm('Dissoudre cette équipe ?');">
                        <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
                        <button type="submit" class="bo-ft__btn">Dissoudre</button>
                    </form>
                    <form method="post" action="<?= $h(url('back-office/atak/fire-teams/' . (int) $team['id'] . '/delete')) ?>" onsubmit="return confirm('Retirer définitivement cette équipe de la liste ?');">
                        <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
                        <button type="submit" class="bo-ft__btn bo-ft__btn--danger">Retirer</button>
                    </form>
                </div>
            <?php endif; ?>
        </header>

        <?php if ($flashSuccess): ?>
            <p class="bo-ft__flash bo-ft__flash--ok" role="status"><?= $h($flashSuccess) ?></p>
        <?php endif; ?>
        <?php if ($flashError): ?>
            <p class="bo-ft__flash bo-ft__flash--err" role="alert"><?= $h($flashError) ?></p>
        <?php endif; ?>
        <?php if ($dissolved): ?>
            <p class="bo-ft__flash bo-ft__flash--warn" role="status">Cette équipe est <strong>dissoute</strong> : elle reste consultable mais n’est plus modifiable.</p>
        <?php endif; ?>

        <?php if (!$ready && !$isEdit): ?>
            <p class="bo-ft__flash bo-ft__flash--warn" role="status">Les équipes de feu ne sont pas encore disponibles (migrations à exécuter).</p>
        <?php else: ?>
            <form method="post" action="<?= $h($action) ?>" class="bo-ft__layout" data-ft-form>
                <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
                <?php if (!$isEdit): ?>
                    <input type="hidden" name="kind" value="<?= $h($kind) ?>">
                <?php endif; ?>

                <div class="bo-ft__main">
                    <fieldset class="bo-ft__section" <?= $dissolved ? 'disabled' : '' ?>>
                        <legend class="bo-ft__section-title"><span>1</span> Identité</legend>
                        <div class="bo-ft__field">
                            <label for="ft-label">Nom de l’équipe</label>
                            <input id="ft-label" name="label" required maxlength="120" value="<?= $h($team['label'] ?? '') ?>" placeholder="Ex. Alpha, FT-1, Éclaireurs…" data-ft-name>
                        </div>
                        <div class="bo-ft__field">
                            <span class="bo-ft__field-label" id="ft-color-label">Couleur sur la carte</span>
                            <div class="bo-ft__swatches" role="radiogroup" aria-labelledby="ft-color-label">
                                <?php foreach ($colors as $c): ?>
                                    <?php $val = strtoupper((string) ($c['value'] ?? '')); ?>
                                    <label class="bo-ft__swatch-pick" style="--team: <?= $h($val) ?>" title="<?= $h($c['label'] ?? $val) ?>">
                                        <input type="radio" name="color" value="<?= $h($val) ?>" <?= $val === $currentColor ? 'checked' : '' ?> data-ft-color>
                                        <span aria-hidden="true"></span>
                                        <em><?= $h($c['label'] ?? $val) ?></em>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="bo-ft__field">
                            <label for="ft-notes">Notes <small>(facultatif)</small></label>
                            <textarea id="ft-notes" name="notes" rows="2" maxlength="500" placeholder="Consignes, secteur, fréquences…"><?= $h($team['notes'] ?? '') ?></textarea>
                        </div>
                    </fieldset>

                    <fieldset class="bo-ft__section" <?= $dissolved ? 'disabled' : '' ?>>
                        <?php if ($isPermanent): ?>
                            <legend class="bo-ft__section-title"><span>2</span> Rattachement</legend>
                            <div class="bo-ft__field">
                                <label for="ft-unit">Unité de l’organigramme <small>(facultatif)</small></label>
                                <select id="ft-unit" name="unit_id" data-ft-attach>
                                    <option value="">Aucune (équipe libre)</option>
                                    <?php foreach ($units as $u): ?>
                                        <?php $uid = (int) ($u['id'] ?? 0); ?>
                                        <option value="<?= $uid ?>" <?= $uid === $currentUnitId ? 'selected' : '' ?>><?= $h($u['name'] ?? ('Unité #' . $uid)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php else: ?>
                            <legend class="bo-ft__section-title"><span>2</span> Mission</legend>
                            <div class="bo-ft__row">
                                <div class="bo-ft__field">
                                    <label for="ft-map">Carte</label>
                                    <select id="ft-map" name="map_id" data-ft-attach>
                                        <?php if ($maps === []): ?>
                                            <option value="1">Carte par défaut</option>
                                        <?php else: ?>
                                            <?php foreach ($maps as $m): ?>
                                                <?php $mid = (int) ($m['id'] ?? 0); ?>
                                                <option value="<?= $mid ?>" <?= $mid === $currentMapId ? 'selected' : '' ?>><?= $h($m['label'] ?? $m['slug'] ?? ('Carte #' . $mid)) ?></option>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </select>
                                </div>
                                <div class="bo-ft__field">
                                    <label for="ft-mission-key">Session <small>(facultatif)</small></label>
                                    <input id="ft-mission-key" name="mission_key" maxlength="64" value="<?= $h($team['mission_key'] ?? '') ?>" placeholder="Ex. Opération Nuit, 22/07…">
                                </div>
                            </div>
                        <?php endif; ?>
                    </fieldset>

                    <fieldset class="bo-ft__section" <?= $dissolved ? 'disabled' : '' ?>>
                        <legend class="bo-ft__section-title"><span>3</span> Composition</legend>
                        <?php if ($users === []): ?>
                            <p class="bo-ft__muted">Aucun membre actif dans la communauté.</p>
                        <?php else: ?>
                            <div class="bo-ft__picker-bar">
                                <label class="bo-ft__search">
                                    <span class="bo-ft__sr">Rechercher un membre</span>
                                    <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 1 0 3.4 9.83l3.13 3.14a.75.75 0 1 0 1.06-1.06l-3.13-3.14A5.5 5.5 0 0 0 9 3.5ZM5 9a4 4 0 1 1 8 0 4 4 0 0 1-8 0Z" clip-rule="evenodd"/></svg>
                                    <input type="search" placeholder="Indicatif ou nom…" data-ft-filter autocomplete="off">
                                </label>
                                <label class="bo-ft__only"><input type="checkbox" data-ft-only> Sélectionnés seulement</label>
                            </div>
                            <ul class="bo-ft__picker" aria-label="Membres de la communauté">
                                <?php foreach ($users as $u): ?>
                                    <?php
                                    $uid = (int) ($u['id'] ?? 0);
                                    $cs = trim((string) ($u['callsign'] ?? ''));
                                    $dn = trim((string) ($u['display_name'] ?? ''));
                                    $name = $cs !== '' ? $cs : ($dn !== '' ? $dn : ('Membre #' . $uid));
                                    $sub = ($cs !== '' && $dn !== '' && strcasecmp($cs, $dn) !== 0) ? $dn : '';
                                    $checked = in_array($uid, $memberIds, true) || $leaderId === $uid;
                                    ?>
                                    <li class="bo-ft__pick<?= $checked ? ' is-on' : '' ?>" data-ft-pick="<?= $h(mb_strtolower($name . ' ' . $sub)) ?>" data-ft-name="<?= $h($name) ?>">
                                        <label class="bo-ft__pick-main">
                                            <input type="checkbox" name="member_user_ids[]" value="<?= $uid ?>" <?= $checked ? 'checked' : '' ?> data-ft-member>
                                            <span class="bo-ft__pick-text">
                                                <strong><?= $h($name) ?></strong>
                                                <?php if ($sub !== ''): ?><small><?= $h($sub) ?></small><?php endif; ?>
                                            </span>
                                        </label>
                                        <label class="bo-ft__pick-lead" title="Chef d’équipe">
                                            <input type="radio" name="leader_user_id" value="<?= $uid ?>" <?= $leaderId === $uid ? 'checked' : '' ?> data-ft-leader>
                                            <span>Chef</span>
                                        </label>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                            <p class="bo-ft__hint">Cochez les membres, puis désignez le chef : il est ajouté automatiquement s’il n’était pas coché.</p>
                        <?php endif; ?>
                    </fieldset>
                </div>

                <aside class="bo-ft__side">
                    <div class="bo-ft__card bo-ft__preview" style="--team: <?= $h($currentColor) ?>" data-ft-preview>
                        <p class="bo-ft__label">Aperçu</p>
                        <div class="bo-ft__card-head">
                            <span class="bo-ft__swatch" aria-hidden="true"></span>
                            <div class="bo-ft__card-id">
                                <p class="bo-ft__card-title" data-ft-p-name><?= $h(($team['label'] ?? '') !== '' ? $team['label'] : 'Nom de l’équipe') ?></p>
                                <p class="bo-ft__card-sub" data-ft-p-attach>—</p>
                            </div>
                        </div>
                        <div class="bo-ft__lead-row">
                            <span class="bo-ft__label">Chef</span>
                            <span class="bo-ft__leader" data-ft-p-leader>—</span>
                            <span class="bo-ft__count" data-ft-p-count>0 membre</span>
                        </div>
                        <ul class="bo-ft__members" data-ft-p-members></ul>
                    </div>
                    <?php if (!$dissolved): ?>
                        <div class="bo-ft__submit">
                            <button type="submit" class="bo-ft__btn bo-ft__btn--primary bo-ft__btn--block"><?= $isEdit ? 'Enregistrer' : 'Créer l’équipe' ?></button>
                            <a href="<?= $h(url('back-office/atak/fire-teams')) ?>" class="bo-ft__btn bo-ft__btn--quiet bo-ft__btn--block">Annuler</a>
                        </div>
                    <?php else: ?>
                        <a href="<?= $h(url('back-office/atak/fire-teams')) ?>" class="bo-ft__btn bo-ft__btn--block">Retour à la liste</a>
                    <?php endif; ?>
                </aside>
            </form>
        <?php endif; ?>
    </div>
</div>
<script>
(function () {
    var form = document.querySelector('[data-ft-form]');
    if (!form) {
        return;
    }
    var preview = form.querySelector('[data-ft-preview]');
    var picks = Array.prototype.slice.call(form.querySelectorAll('[data-ft-pick]'));
    var filter = form.querySelector('[data-ft-filter]');
    var only = form.querySelector('[data-ft-only]');

    function text(sel, value) {
        var el = form.querySelector(sel);
        if (el) {
            el.textContent = value;
        }
    }

    function refresh() {
        var name = form.querySelector('[data-ft-name]');
        text('[data-ft-p-name]', name && name.value.trim() !== '' ? name.value.trim() : 'Nom de l’équipe');
        var color = form.querySelector('[data-ft-color]:checked');
        if (color && preview) {
            preview.style.setProperty('--team', color.value);
        }
        var attach = form.querySelector('[data-ft-attach]');
        text('[data-ft-p-attach]', attach && attach.selectedIndex >= 0 ? attach.options[attach.selectedIndex].text.trim() : '—');

        var leader = '—';
        var others = [];
        picks.forEach(function (li) {
            var box = li.querySelector('[data-ft-member]');
            var lead = li.querySelector('[data-ft-leader]');
            li.classList.toggle('is-on', box.checked);
            if (lead.checked) {
                leader = li.getAttribute('data-ft-name');
            } else if (box.checked) {
                others.push(li.getAttribute('data-ft-name'));
            }
        });
        var total = others.length + (leader !== '—' ? 1 : 0);
        text('[data-ft-p-leader]', leader);
        text('[data-ft-p-count]', total + ' membre' + (total > 1 ? 's' : ''));
        var list = form.querySelector('[data-ft-p-members]');
        if (list) {
            list.innerHTML = '';
            others.forEach(function (n) {
                var li = document.createElement('li');
                li.textContent = n;
                list.appendChild(li);
            });
        }
        applyFilter();
    }

    function applyFilter() {
        var q = filter ? filter.value.trim().toLowerCase() : '';
        var onlyOn = only && only.checked;
        picks.forEach(function (li) {
            var match = q === '' || (li.getAttribute('data-ft-pick') || '').indexOf(q) !== -1;
            li.hidden = !match || (onlyOn && !li.classList.contains('is-on'));
        });
    }

    form.addEventListener('input', refresh);
    form.addEventListener('change', function (ev) {
        // Désigner un chef le coche comme membre ; décocher le chef retire aussi sa désignation.
        var t = ev.target;
        var li = t && t.closest ? t.closest('[data-ft-pick]') : null;
        if (li && t.matches('[data-ft-leader]') && t.checked) {
            li.querySelector('[data-ft-member]').checked = true;
        }
        if (li && t.matches('[data-ft-member]') && !t.checked) {
            li.querySelector('[data-ft-leader]').checked = false;
        }
        refresh();
    });
    refresh();
})();
</script>
