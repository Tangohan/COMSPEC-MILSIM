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
$currentMapId = (int) ($team['map_id'] ?? 1);
$currentUnitId = (int) ($team['unit_id'] ?? 0);
?>
<div class="max-w-4xl mx-auto px-4 sm:px-6 py-8 space-y-6">
    <header class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-500">Tactique · ATAK</p>
        <h1 class="mt-2 text-2xl font-black text-slate-900">
            <?= $isEdit ? $h($team['label'] ?? 'Équipe de feu') : 'Nouvelle équipe de feu' ?>
        </h1>
        <p class="mt-2 text-sm text-slate-600">
            <?= $isPermanent
                ? 'Équipe durable, rattachée à une unité de l’organigramme.'
                : 'Équipe temporaire pour une carte / mission ATAK. Dissolvez-la en fin de session.' ?>
        </p>
        <div class="mt-4 flex flex-wrap gap-2">
            <a href="<?= $h(url('back-office/atak/fire-teams')) ?>" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Retour à la liste</a>
            <?php if ($isEdit && !$dissolved): ?>
                <form method="post" action="<?= $h(url('back-office/atak/fire-teams/' . (int) $team['id'] . '/dissolve')) ?>" onsubmit="return confirm('Dissoudre cette équipe ?');">
                    <input type="hidden" name="_csrf_token" value="<?= $h(\App\Core\Csrf::token()) ?>">
                    <button type="submit" class="rounded-lg border border-amber-300 px-3 py-2 text-sm font-semibold text-amber-900 hover:bg-amber-50">Dissoudre</button>
                </form>
                <form method="post" action="<?= $h(url('back-office/atak/fire-teams/' . (int) $team['id'] . '/delete')) ?>" onsubmit="return confirm('Retirer définitivement cette équipe de la liste ?');">
                    <input type="hidden" name="_csrf_token" value="<?= $h(\App\Core\Csrf::token()) ?>">
                    <button type="submit" class="rounded-lg border border-rose-300 px-3 py-2 text-sm font-semibold text-rose-800 hover:bg-rose-50">Retirer</button>
                </form>
            <?php endif; ?>
        </div>
    </header>

    <?php if ($flashSuccess): ?>
        <p class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800"><?= $h($flashSuccess) ?></p>
    <?php endif; ?>
    <?php if ($flashError): ?>
        <p class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-800"><?= $h($flashError) ?></p>
    <?php endif; ?>

    <?php if ($dissolved): ?>
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950">
            Cette équipe est <strong>dissoute</strong>. Vous pouvez la consulter, mais plus la modifier.
        </div>
    <?php endif; ?>

    <?php if (!$ready && !$isEdit): ?>
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950">
            Les équipes de feu ne sont pas encore disponibles (migrations à exécuter).
        </div>
    <?php else: ?>
        <form method="post" action="<?= $h($action) ?>" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm space-y-6">
            <input type="hidden" name="_csrf_token" value="<?= $h(\App\Core\Csrf::token()) ?>">
            <?php if (!$isEdit): ?>
                <input type="hidden" name="kind" value="<?= $h($kind) ?>">
            <?php endif; ?>

            <fieldset class="space-y-4" <?= $dissolved ? 'disabled' : '' ?>>
                <legend class="text-sm font-bold text-slate-900">Identité</legend>
                <div>
                    <label for="ft-label" class="block text-sm font-semibold text-slate-800">Nom de l’équipe</label>
                    <input id="ft-label" name="label" required maxlength="120"
                           value="<?= $h($team['label'] ?? '') ?>"
                           class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                           placeholder="Ex. Alpha, FT-1, Éclaireurs…">
                </div>
                <div>
                    <label for="ft-color" class="block text-sm font-semibold text-slate-800">Couleur d’identification</label>
                    <select id="ft-color" name="color" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <?php foreach ($colors as $c):
                            $val = strtoupper((string) ($c['value'] ?? ''));
                            ?>
                            <option value="<?= $h($val) ?>" <?= $val === $currentColor ? 'selected' : '' ?>>
                                <?= $h($c['label'] ?? $val) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="ft-notes" class="block text-sm font-semibold text-slate-800">Notes (optionnel)</label>
                    <textarea id="ft-notes" name="notes" rows="2" maxlength="500"
                              class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                              placeholder="Consignes, secteur, fréquences…"><?= $h($team['notes'] ?? '') ?></textarea>
                </div>
            </fieldset>

            <?php if ($isPermanent): ?>
                <fieldset class="space-y-3" <?= $dissolved ? 'disabled' : '' ?>>
                    <legend class="text-sm font-bold text-slate-900">Rattachement organigramme</legend>
                    <p class="text-xs text-slate-500">Choisissez une équipe existante de l’organigramme (optionnel). Cela ne modifie pas l’ORBAT.</p>
                    <div>
                        <label for="ft-unit" class="block text-sm font-semibold text-slate-800">Unité liée</label>
                        <select id="ft-unit" name="unit_id" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            <option value="">Aucune (équipe libre)</option>
                            <?php foreach ($units as $u):
                                $uid = (int) ($u['id'] ?? 0);
                                ?>
                                <option value="<?= $uid ?>" <?= $uid === $currentUnitId ? 'selected' : '' ?>>
                                    <?= $h($u['name'] ?? ('Unité #' . $uid)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </fieldset>
            <?php else: ?>
                <fieldset class="space-y-3" <?= $dissolved ? 'disabled' : '' ?>>
                    <legend class="text-sm font-bold text-slate-900">Mission cartographique</legend>
                    <div>
                        <label for="ft-map" class="block text-sm font-semibold text-slate-800">Carte</label>
                        <select id="ft-map" name="map_id" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            <?php if ($maps === []): ?>
                                <option value="1">Carte par défaut</option>
                            <?php else: ?>
                                <?php foreach ($maps as $m):
                                    $mid = (int) ($m['id'] ?? 0);
                                    $mlabel = (string) ($m['label'] ?? $m['slug'] ?? ('Carte #' . $mid));
                                    ?>
                                    <option value="<?= $mid ?>" <?= $mid === $currentMapId ? 'selected' : '' ?>><?= $h($mlabel) ?></option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div>
                        <label for="ft-mission-key" class="block text-sm font-semibold text-slate-800">Référence de session (optionnel)</label>
                        <input id="ft-mission-key" name="mission_key" maxlength="64"
                               value="<?= $h($team['mission_key'] ?? '') ?>"
                               class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                               placeholder="Ex. Opération Nuit, Session du 22/07…">
                        <p class="mt-1 text-xs text-slate-500">Libellé libre pour distinguer plusieurs sessions sur la même carte.</p>
                    </div>
                </fieldset>
            <?php endif; ?>

            <fieldset class="space-y-3" <?= $dissolved ? 'disabled' : '' ?>>
                <legend class="text-sm font-bold text-slate-900">Composition</legend>
                <p class="text-xs text-slate-500">Cochez les membres parmi les effectifs de la communauté. Désignez un chef d’équipe.</p>

                <?php if ($users === []): ?>
                    <p class="text-sm text-slate-500">Aucun membre actif trouvé.</p>
                <?php else: ?>
                    <div class="max-h-72 overflow-y-auto rounded-xl border border-slate-200 divide-y divide-slate-100">
                        <?php foreach ($users as $u):
                            $uid = (int) ($u['id'] ?? 0);
                            $cs = trim((string) ($u['callsign'] ?? ''));
                            $dn = trim((string) ($u['display_name'] ?? ''));
                            $label = $cs !== '' ? $cs : ($dn !== '' ? $dn : ('Membre #' . $uid));
                            $sub = ($cs !== '' && $dn !== '' && strcasecmp($cs, $dn) !== 0) ? $dn : '';
                            $checked = in_array($uid, $memberIds, true);
                            ?>
                            <label class="flex items-center gap-3 px-3 py-2 hover:bg-slate-50 cursor-pointer">
                                <input type="checkbox" name="member_user_ids[]" value="<?= $uid ?>" class="rounded border-slate-300 text-emerald-700"
                                    <?= $checked ? 'checked' : '' ?>>
                                <span class="flex-1 min-w-0">
                                    <span class="block text-sm font-semibold text-slate-900 truncate"><?= $h($label) ?></span>
                                    <?php if ($sub !== ''): ?>
                                        <span class="block text-xs text-slate-500 truncate"><?= $h($sub) ?></span>
                                    <?php endif; ?>
                                </span>
                                <span class="shrink-0 flex items-center gap-1.5 text-xs text-slate-600">
                                    <input type="radio" name="leader_user_id" value="<?= $uid ?>"
                                           class="border-slate-300 text-emerald-700"
                                           <?= $leaderId === $uid ? 'checked' : '' ?>
                                           title="Chef d’équipe">
                                    Chef
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </fieldset>

            <?php if (!$dissolved): ?>
                <div class="flex flex-wrap gap-2 pt-2">
                    <button type="submit" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                        <?= $isEdit ? 'Enregistrer' : 'Créer l’équipe' ?>
                    </button>
                    <a href="<?= $h(url('back-office/atak/fire-teams')) ?>" class="rounded-lg px-4 py-2 text-sm font-semibold text-slate-600 hover:underline">Annuler</a>
                </div>
            <?php endif; ?>
        </form>
    <?php endif; ?>
</div>
