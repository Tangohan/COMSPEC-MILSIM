<?php
declare(strict_types=1);

$email = (string) ($personEmail ?? '');
$displayName = trim((string) ($personDisplayName ?? ''));
$callsign = trim((string) ($personCallsign ?? ''));
$steamId = trim((string) ($personSteamId ?? ''));
$athenaId = trim((string) ($personAthenaId ?? ''));
$civil = is_array($personCivil ?? null) ? $personCivil : [];
$hasLiveOrg = !empty($personHasLiveOrg);
$memberships = is_array($personMemberships ?? null) ? $personMemberships : [];
$mergePreview = is_array($personMergePreview ?? null) ? $personMergePreview : [];
$mergeable = !empty($mergePreview['mergeable']);
$mergeRows = is_array($mergePreview['rows'] ?? null) ? $mergePreview['rows'] : [];
$suggestedSurvivorId = (int) ($mergePreview['suggested_survivor_id'] ?? 0);
$identityFills = is_array($mergePreview['identity_fills'] ?? null) ? $mergePreview['identity_fills'] : [];
$identityTableFills = is_array($mergePreview['identity_table_fills'] ?? null) ? $mergePreview['identity_table_fills'] : [];
$steamCollisions = is_array($mergePreview['steam_collisions'] ?? null) ? $mergePreview['steam_collisions'] : [];
$csrf = $h(\App\Core\Csrf::token());
$selectedSurvivorId = (int) ($_GET['survivor_id'] ?? $suggestedSurvivorId);
$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$empty = static function (?string $v) use ($h): string {
    $t = trim((string) $v);
    return $t !== '' ? $h($t) : '<span class="text-slate-400">—</span>';
};
$statusLabel = static function (string $status): string {
    return match ($status) {
        'active' => 'Compte actif',
        'inactive' => 'Compte inactif',
        'pending_verification' => 'En attente de vérification de l’e-mail',
        default => $status !== '' ? $status : 'Inconnu',
    };
};
$primary = $callsign !== '' ? $callsign : ($displayName !== '' ? $displayName : $email);
?>
<div class="min-h-0 flex-1 bg-slate-50">
    <div class="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8 space-y-6">
        <nav class="text-sm text-slate-500">
            <a href="<?= $h(url('admin')) ?>" class="font-semibold text-emerald-800 hover:text-emerald-950">Administration plateforme</a>
            <span class="mx-2" aria-hidden="true">/</span>
            <a href="<?= $h(url('admin/users')) ?>" class="font-semibold text-emerald-800 hover:text-emerald-950">Comptes utilisateurs</a>
            <span class="mx-2" aria-hidden="true">/</span>
            <span class="text-slate-800">Dossier personne</span>
        </nav>

        <header class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm space-y-3">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-500">Identité plateforme</p>
                    <h1 class="text-2xl font-black text-slate-900"><?= $h($primary) ?></h1>
                    <?php if ($displayName !== '' && strcasecmp($displayName, $primary) !== 0): ?>
                        <p class="text-sm text-slate-600"><?= $h($displayName) ?></p>
                    <?php endif; ?>
                    <p class="mt-1 font-mono text-xs text-slate-500"><?= $h($email) ?></p>
                </div>
                <div class="text-right space-y-1">
                    <?php if ($hasLiveOrg): ?>
                        <span class="inline-flex rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-900">Appartenance active</span>
                    <?php else: ?>
                        <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">Orphelin (plus d’orga active)</span>
                    <?php endif; ?>
                    <p class="text-xs text-slate-500"><?= count($memberships) ?> dossier(s) communautaire(s) — chacun reste séparé</p>
                </div>
            </div>

            <dl class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 text-sm">
                <div>
                    <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Identifiant plateforme</dt>
                    <dd class="mt-0.5 font-mono font-semibold text-slate-900"><?= $empty($athenaId) ?></dd>
                </div>
                <div>
                    <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Steam ID</dt>
                    <dd class="mt-0.5 font-mono text-slate-900"><?= $empty($steamId) ?></dd>
                </div>
                <div>
                    <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Prénom / Nom</dt>
                    <dd class="mt-0.5 text-slate-900"><?= $empty(trim(($civil['first_name'] ?? '') . ' ' . ($civil['last_name'] ?? ''))) ?></dd>
                </div>
                <div>
                    <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Téléphone</dt>
                    <dd class="mt-0.5 text-slate-900"><?= $empty($civil['phone'] ?? null) ?></dd>
                </div>
            </dl>
        </header>

        <?php if (!$hasLiveOrg): ?>
            <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950">
                Cette personne n’a plus d’appartenance active à une communauté réelle
                (compte orphelin). Elle reste visible dans l’annuaire et la recherche plateforme.
            </div>
        <?php endif; ?>

        <?php if ($mergeable): ?>
            <section id="fusion-comptes" class="rounded-xl border border-amber-300 bg-amber-50/70 shadow-sm overflow-hidden">
                <div class="border-b border-amber-200 px-4 py-3">
                    <h2 class="text-base font-bold text-amber-950">Fusionner les comptes</h2>
                    <p class="mt-1 text-sm text-amber-900/90">
                        Plusieurs fiches <code class="font-mono text-xs">users</code> partagent cette adresse.
                        La fusion regroupe identité plateforme, profils et références sous un compte survivant.
                        Les champs déjà renseignés ne sont pas écrasés ; les zones vides sont complétées.
                    </p>
                </div>
                <form method="post" action="<?= $h(url('admin/users/merge')) ?>" class="space-y-4 p-4"
                      onsubmit="return confirm('Confirmer la fusion définitive de ces comptes ?\n\nLes fiches absorbées seront marquées « fusionnées » et leurs données rattachées au survivant.');">
                    <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
                    <input type="hidden" name="email" value="<?= $h($email) ?>">

                    <fieldset class="space-y-2">
                        <legend class="text-xs font-bold uppercase tracking-wider text-amber-950">Compte survivant</legend>
                        <?php foreach ($mergeRows as $row): ?>
                            <?php
                            $rid = (int) ($row['id'] ?? 0);
                            $rtid = (int) ($row['tenant_id'] ?? 0);
                            $rlabel = trim((string) ($row['display_name'] ?? ''));
                            if ($rlabel === '') {
                                $rlabel = trim((string) ($row['callsign'] ?? ''));
                            }
                            if ($rlabel === '') {
                                $rlabel = 'Compte #' . $rid;
                            }
                            $checked = $rid === $selectedSurvivorId || ($selectedSurvivorId < 1 && $rid === $suggestedSurvivorId);
                            ?>
                            <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-amber-200 bg-white px-3 py-2.5 hover:border-amber-400">
                                <input type="radio" name="survivor_user_id" value="<?= $rid ?>" class="mt-1" <?= $checked ? 'checked' : '' ?>
                                       onchange="window.location.href='<?= $h(url('admin/users/person') . '?email=' . rawurlencode($email) . '&survivor_id=') ?>' + this.value + '#fusion-comptes';">
                                <span class="text-sm">
                                    <span class="font-semibold text-slate-900"><?= $h($rlabel) ?></span>
                                    <span class="block font-mono text-xs text-slate-500">#<?= $rid ?> · tenant #<?= $rtid ?> · <?= $h((string) ($row['status'] ?? '')) ?></span>
                                    <?php if ($rid === $suggestedSurvivorId): ?>
                                        <span class="mt-1 inline-block rounded bg-emerald-100 px-1.5 py-0.5 text-[10px] font-bold uppercase text-emerald-900">Recommandé</span>
                                    <?php endif; ?>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </fieldset>

                    <?php if ($identityFills !== [] || $identityTableFills !== []): ?>
                        <div class="rounded-lg border border-emerald-200 bg-emerald-50/80 px-3 py-2 text-sm text-emerald-950">
                            <p class="font-semibold">Données à compléter sur le survivant</p>
                            <ul class="mt-1 list-disc pl-5 text-xs">
                                <?php foreach ($identityFills as $key => $value): ?>
                                    <li>Identité plateforme — <?= $h((string) $key) ?></li>
                                <?php endforeach; ?>
                                <?php foreach ($identityTableFills as $table => $fills): ?>
                                    <?php if (is_array($fills)): ?>
                                        <?php foreach (array_keys($fills) as $field): ?>
                                            <li><?= $h((string) $table) ?> — <?= $h((string) $field) ?></li>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <?php if ($steamCollisions !== []): ?>
                        <div class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-950">
                            <p class="font-semibold"><?= count($steamCollisions) ?> conflit(s) Steam détecté(s)</p>
                            <p class="mt-1 text-xs">Le Steam du survivant sera conservé ; les autres identifiants seront journalisés.</p>
                        </div>
                    <?php endif; ?>

                    <div class="grid gap-2 sm:grid-cols-[1fr_auto] sm:items-end">
                        <div>
                            <label for="confirm_email" class="block text-xs font-bold uppercase tracking-wider text-amber-950">
                                Confirmer l’adresse e-mail
                            </label>
                            <input type="email" name="confirm_email" id="confirm_email" required autocomplete="off"
                                   class="mt-1 w-full rounded-lg border border-amber-300 bg-white px-3 py-2 text-sm"
                                   placeholder="<?= $h($email) ?>">
                        </div>
                        <button type="submit" class="rounded-lg bg-amber-700 px-4 py-2.5 text-sm font-bold text-white hover:bg-amber-800">
                            Fusionner les comptes
                        </button>
                    </div>
                </form>
            </section>
        <?php endif; ?>

        <div class="space-y-4">
            <?php foreach ($memberships as $pack): ?>
                <?php
                $m = is_array($pack['user'] ?? null) ? $pack['user'] : [];
                $pp = is_array($pack['personnel_profile'] ?? null) ? $pack['personnel_profile'] : [];
                $ex = is_array($pack['personnel_extras'] ?? null) ? $pack['personnel_extras'] : [];
                $grade = is_array($pack['grade'] ?? null) ? $pack['grade'] : null;
                $assign = is_array($pack['primary_assignment'] ?? null) ? $pack['primary_assignment'] : null;
                $legal = is_array($pack['legal'] ?? null) ? $pack['legal'] : [];
                $uid = (int) ($m['id'] ?? 0);
                $tid = (int) ($m['tenant_id'] ?? 0);
                $tenantName = (string) ($m['tenant_name'] ?? '');
                $tenantSlug = (string) ($m['tenant_slug'] ?? '');
                $st = (string) ($m['status'] ?? '');
                $isDeleted = !empty($m['deleted_at']);
                $isDefault = $tenantSlug === 'default';
                $matricule = trim((string) ($pp['matricule_internal'] ?? '')) ?: trim((string) ($ex['service_number'] ?? ''));
                $character = trim((string) ($pp['character_name'] ?? ''));
                $gradeLabel = '';
                if ($grade) {
                    $gradeLabel = trim((string) ($grade['short_name'] ?? $grade['name'] ?? $grade['label_long'] ?? ''));
                }
                $unitName = trim((string) ($assign['unit_name'] ?? ''));
                $roleNames = is_array($pack['role_names'] ?? null) ? $pack['role_names'] : [];
                ?>
                <article class="rounded-xl border <?= $isDeleted ? 'border-rose-200 bg-rose-50/40' : ($isDefault ? 'border-slate-200 bg-slate-50' : 'border-slate-200 bg-white') ?> shadow-sm overflow-hidden">
                    <div class="flex flex-wrap items-start justify-between gap-2 border-b border-slate-100 px-4 py-3">
                        <div>
                            <h2 class="text-base font-bold text-slate-900">Dossier — <?= $h($tenantName !== '' ? $tenantName : 'Communauté') ?></h2>
                            <p class="text-xs text-slate-500">Vous éditez le dossier de cette communauté uniquement. Grade, matricule et fiche RH des autres communautés ne sont pas concernés.</p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <?php if ($isDeleted): ?>
                                <span class="rounded-full bg-rose-100 px-2.5 py-1 text-xs font-semibold text-rose-900">Anonymisé</span>
                            <?php else: ?>
                                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-800"><?= $h($statusLabel($st)) ?></span>
                            <?php endif; ?>
                            <?php if ($isDefault): ?>
                                <span class="rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-900">Tenant système</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="grid gap-4 p-4 sm:grid-cols-2 lg:grid-cols-3 text-sm">
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Nom affiché</p>
                            <p class="font-semibold text-slate-900"><?= $empty($m['display_name'] ?? null) ?></p>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Indicatif</p>
                            <p class="font-semibold text-slate-900"><?= $empty($m['callsign'] ?? null) ?></p>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Rôle</p>
                            <p class="text-slate-900"><?= $roleNames !== [] ? $h(implode(', ', $roleNames)) : $empty($m['role_name'] ?? null) ?></p>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Grade</p>
                            <p class="text-slate-900"><?= $empty($gradeLabel) ?></p>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Matricule dossier</p>
                            <p class="font-mono text-slate-900"><?= $empty($matricule) ?></p>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Affectation</p>
                            <p class="text-slate-900"><?= $empty($unitName) ?></p>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Personnage</p>
                            <p class="text-slate-900"><?= $empty($character) ?></p>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Athena ID (fiche)</p>
                            <p class="font-mono text-slate-900"><?= $empty($m['athena_identifier'] ?? null) ?></p>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Steam (fiche)</p>
                            <p class="font-mono text-slate-900"><?= $empty($m['steam_id'] ?? null) ?></p>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Identité civile (fiche)</p>
                            <p class="text-slate-900"><?= $empty(trim(($legal['first_name'] ?? '') . ' ' . ($legal['last_name'] ?? ''))) ?></p>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Créé / MAJ</p>
                            <p class="text-xs text-slate-600"><?= $h((string) ($m['created_at'] ?? '—')) ?> · <?= $h((string) ($m['updated_at'] ?? '—')) ?></p>
                        </div>
                        <?php if (!$isDeleted && $uid > 0): ?>
                        <div class="sm:col-span-2 lg:col-span-3 flex flex-wrap gap-2 pt-1">
                            <a href="<?= $h(url('admin/users/' . $uid . '/edit')) ?>" class="inline-flex rounded-lg bg-emerald-800 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-900">Modifier la fiche</a>
                            <?php if (!$isDefault): ?>
                            <a href="<?= $h(url('personnel/' . $uid)) ?>" class="text-xs font-semibold text-emerald-800 hover:underline">Fiche personnel</a>
                            <a href="<?= $h(url('back-office/users/' . $uid)) ?>" class="text-xs font-semibold text-slate-600 hover:underline">Fiche communauté</a>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <p class="text-sm">
            <a href="<?= $h(url('admin/users')) ?>" class="font-semibold text-emerald-800 hover:underline">← Retour à l’annuaire</a>
        </p>
    </div>
</div>
