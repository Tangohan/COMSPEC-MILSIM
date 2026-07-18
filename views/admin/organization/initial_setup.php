<?php
declare(strict_types=1);

/** @var array $tenant */
/** @var array<string, mixed> $community */
/** @var array<string, mixed> $branding */
/** @var string $logoUrl */
/** @var array<string, mixed> $setupAnalysis */
/** @var list<array{slug: string, name: string}> $roleOptions */
/** @var string $defaultGuestRoleSlug */

$c = $community ?? [];
$analysis = is_array($setupAnalysis ?? null) ? $setupAnalysis : [];
$items = is_array($analysis['items'] ?? null) ? $analysis['items'] : [];
$optional = is_array($analysis['optional'] ?? null) ? $analysis['optional'] : [];
$percent = (int) ($analysis['percent'] ?? 0);
$done = (int) ($analysis['done'] ?? 0);
$total = (int) ($analysis['total'] ?? 0);
$completed = !empty($analysis['completed']);
$roles = is_array($roleOptions ?? null) ? $roleOptions : [];
$guestSlug = (string) ($defaultGuestRoleSlug ?? 'invite');
$logo = trim((string) ($logoUrl ?? ''));
$pm = is_array($c['public_modules'] ?? null) ? $c['public_modules'] : [];
$registrationMode = ($c['registration_mode'] ?? 'milsim') === 'simple' ? 'simple' : 'milsim';
$slugHint = trim((string) ($tenant['slug'] ?? ''));
$publicPageUrl = $slugHint !== '' ? url('c/' . rawurlencode($slugHint)) : '';
$communityName = trim((string) ($tenant['name'] ?? ''));

$err = \App\Core\Session::getFlash('error');
$ok = \App\Core\Session::getFlash('success');
?>
<div class="min-h-0 flex-1 bg-slate-50">
<div class="max-w-5xl mx-auto px-4 sm:px-6 py-10 lg:py-12 space-y-8">

    <header class="relative overflow-hidden rounded-2xl border border-emerald-200/80 bg-gradient-to-br from-emerald-50/90 via-white to-slate-50 shadow-sm">
        <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,_var(--tw-gradient-stops))] from-emerald-100/50 via-transparent to-transparent pointer-events-none" aria-hidden="true"></div>
        <div class="relative px-5 sm:px-8 py-7 lg:py-8 flex flex-col lg:flex-row lg:items-start lg:justify-between gap-6">
            <div class="min-w-0 flex-1">
                <p class="text-[11px] font-black uppercase tracking-[0.22em] text-emerald-800/90">Premiers pas</p>
                <h1 class="mt-2 text-2xl lg:text-3xl font-black tracking-tight text-slate-900">
                    <?php if ($completed): ?>
                        Configuration initiale
                    <?php else: ?>
                        Bienvenue<?= $communityName !== '' ? ' — ' . htmlspecialchars($communityName, ENT_QUOTES, 'UTF-8') : '' ?>
                    <?php endif; ?>
                </h1>
                <p class="mt-2 text-sm text-slate-600 max-w-2xl leading-relaxed">
                    Votre communauté est déjà en place. Complétez les derniers réglages essentiels : logo, contact, inscription, modules visibles et rôle d’accueil.
                    Vous pouvez enregistrer, reporter ou terminer à tout moment.
                </p>
            </div>
            <div class="shrink-0 w-full lg:w-56 rounded-xl border border-slate-200/80 bg-white/90 p-4 shadow-sm">
                <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-400 mb-2">Profil renseigné</p>
                <p class="text-3xl font-black text-slate-900"><?= $percent ?>%</p>
                <p class="mt-1 text-xs text-slate-600"><?= $done ?>/<?= $total ?> éléments essentiels</p>
                <div class="mt-3 h-2 rounded-full bg-slate-100 overflow-hidden">
                    <div class="h-full rounded-full bg-emerald-500 transition-all" style="width:<?= $percent ?>%"></div>
                </div>
            </div>
        </div>
    </header>

    <?php if ($err): ?><div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-800"><?= htmlspecialchars((string) $err, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <?php if ($ok): ?><div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800"><?= htmlspecialchars((string) $ok, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Checklist</p>
        <div class="mt-3 flex flex-wrap gap-2">
            <?php foreach ($items as $label => $isDone): ?>
                <span class="inline-flex items-center gap-1.5 rounded-full border <?= $isDone ? 'border-emerald-300 bg-emerald-50 text-emerald-900' : 'border-amber-200 bg-amber-50 text-amber-950' ?> px-2.5 py-1 text-[11px] font-semibold">
                    <span aria-hidden="true"><?= $isDone ? '✓' : '!' ?></span><?= htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8') ?>
                </span>
            <?php endforeach; ?>
            <?php foreach ($optional as $label => $isDone): ?>
                <span class="inline-flex items-center gap-1.5 rounded-full border <?= $isDone ? 'border-slate-200 bg-slate-50 text-slate-700' : 'border-slate-200 bg-white text-slate-500' ?> px-2.5 py-1 text-[11px] font-semibold">
                    <span aria-hidden="true"><?= $isDone ? '✓' : '·' ?></span><?= htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8') ?> <span class="font-normal opacity-70">(optionnel)</span>
                </span>
            <?php endforeach; ?>
        </div>
    </section>

    <form method="post" action="<?= htmlspecialchars(url('back-office/configuration-initiale'), ENT_QUOTES, 'UTF-8') ?>" enctype="multipart/form-data" class="space-y-6" id="initial-setup-form">
        <?= \App\Core\Csrf::field() ?>

        <section class="rounded-2xl border border-slate-200 bg-white p-6 sm:p-8 shadow-sm space-y-5">
            <div>
                <h2 class="text-sm font-black uppercase tracking-widest text-slate-900">Identité visuelle</h2>
                <p class="mt-1 text-sm text-slate-600">Le logo apparaît sur la page publique et dans le portail.</p>
            </div>
            <div class="flex flex-col sm:flex-row gap-5 items-start">
                <div class="shrink-0 w-24 h-24 rounded-2xl border border-slate-200 bg-slate-50 overflow-hidden flex items-center justify-center">
                    <?php if ($logo !== ''): ?>
                        <img src="<?= htmlspecialchars($logo, ENT_QUOTES, 'UTF-8') ?>" alt="Logo actuel" class="max-w-full max-h-full object-contain">
                    <?php else: ?>
                        <span class="text-[11px] font-semibold text-slate-400 text-center px-2">Pas encore de logo</span>
                    <?php endif; ?>
                </div>
                <div class="flex-1 space-y-3 w-full">
                    <label class="block text-xs font-bold text-slate-700" for="org_logo">Ajouter ou remplacer le logo</label>
                    <input id="org_logo" type="file" name="org_logo" accept="image/jpeg,image/png,image/webp" class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-emerald-50 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-emerald-900 hover:file:bg-emerald-100">
                    <p class="text-xs text-slate-500">JPG, PNG ou WebP · 12 Mo max.</p>
                    <?php if ($logo !== ''): ?>
                        <label class="inline-flex items-center gap-2 text-sm text-slate-700 cursor-pointer">
                            <input type="checkbox" name="remove_org_logo" value="1" class="rounded border-slate-300 text-emerald-700">
                            Retirer le logo actuel
                        </label>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-6 sm:p-8 shadow-sm space-y-5">
            <div>
                <h2 class="text-sm font-black uppercase tracking-widest text-slate-900">Présentation</h2>
                <p class="mt-1 text-sm text-slate-600">Texte court visible sur la page publique de votre unité.</p>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1.5" for="welcome_text">Message d’accueil</label>
                <textarea id="welcome_text" name="welcome_text" rows="4" maxlength="500" class="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-3 text-sm leading-relaxed shadow-sm focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100" placeholder="Présentez votre unité en quelques phrases…"><?= htmlspecialchars((string) ($c['welcome_text'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-6 sm:p-8 shadow-sm space-y-5">
            <div>
                <h2 class="text-sm font-black uppercase tracking-widest text-slate-900">Contact</h2>
                <p class="mt-1 text-sm text-slate-600">Coordonnées affichées aux candidats et visiteurs.</p>
            </div>
            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1.5" for="contact_email">E-mail de contact</label>
                    <input id="contact_email" type="email" name="contact_email" value="<?= htmlspecialchars((string) ($c['contact_email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" maxlength="255" class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100" placeholder="contact@votre-unite.fr" autocomplete="email">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1.5" for="contact_discord_url">Lien Discord <span class="font-normal text-slate-400">(optionnel)</span></label>
                    <input id="contact_discord_url" type="url" name="contact_discord_url" value="<?= htmlspecialchars((string) ($c['contact_discord_url'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" maxlength="500" class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100" placeholder="https://discord.gg/…">
                </div>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-6 sm:p-8 shadow-sm space-y-5">
            <div>
                <h2 class="text-sm font-black uppercase tracking-widest text-slate-900">Inscription &amp; recrutement</h2>
                <p class="mt-1 text-sm text-slate-600">Comment les candidats rejoignent votre communauté.</p>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1.5" for="registration_mode">Mode du formulaire de candidature</label>
                <select id="registration_mode" name="registration_mode" class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100">
                    <option value="milsim" <?= $registrationMode === 'milsim' ? 'selected' : '' ?>>MilSim complet (dossier détaillé)</option>
                    <option value="simple" <?= $registrationMode === 'simple' ? 'selected' : '' ?>>Mode simple (champs réduits)</option>
                </select>
            </div>
            <div class="space-y-3">
                <label class="flex items-start gap-3 cursor-pointer rounded-xl border border-slate-200 bg-slate-50/50 px-4 py-3">
                    <input type="hidden" name="community_locked" value="0">
                    <input type="checkbox" name="community_locked" value="1" class="mt-1 rounded border-slate-300 text-emerald-700" <?= !empty($c['community_locked']) ? 'checked' : '' ?>>
                    <span class="text-sm text-slate-700 leading-relaxed"><strong class="font-semibold text-slate-900">Fermer le recrutement</strong> — les nouvelles candidatures ne sont plus acceptées.</span>
                </label>
                <label class="flex items-start gap-3 cursor-pointer rounded-xl border border-slate-200 bg-slate-50/50 px-4 py-3">
                    <input type="hidden" name="require_ai_ack" value="0">
                    <input type="checkbox" name="require_ai_ack" value="1" class="mt-1 rounded border-slate-300 text-emerald-700" <?= !array_key_exists('require_ai_ack', $c) || !empty($c['require_ai_ack']) ? 'checked' : '' ?>>
                    <span class="text-sm text-slate-700 leading-relaxed">Exiger l’accusé de réception des règles avant dépôt d’une candidature</span>
                </label>
                <label class="flex items-start gap-3 cursor-pointer rounded-xl border border-slate-200 bg-slate-50/50 px-4 py-3">
                    <input type="hidden" name="public_recruitment_badge_open" value="0">
                    <input type="checkbox" name="public_recruitment_badge_open" value="1" class="mt-1 rounded border-slate-300 text-emerald-700" <?= !empty($c['public_recruitment_badge_open']) ? 'checked' : '' ?>>
                    <span class="text-sm text-slate-700 leading-relaxed">Afficher le badge « recrutement ouvert » sur la fiche publique</span>
                </label>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-6 sm:p-8 shadow-sm space-y-5">
            <div>
                <h2 class="text-sm font-black uppercase tracking-widest text-slate-900">Modules visibles sur la page publique</h2>
                <p class="mt-1 text-sm text-slate-600">Choisissez ce que les visiteurs peuvent apercevoir depuis l’extérieur.</p>
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
                <?php
                $modLabels = [
                    'forum' => 'Forum',
                    'documents' => 'Documents',
                    'events' => 'Événements',
                    'roster' => 'Effectifs',
                    'training' => 'Formations',
                    'analytics' => 'Indicateurs',
                ];
                foreach ($modLabels as $modKey => $modLabel):
                    $checked = !empty($pm[$modKey]);
                ?>
                <label class="flex items-center gap-3 cursor-pointer rounded-xl border border-slate-200 bg-slate-50/50 px-4 py-3">
                    <input type="checkbox" name="public_mod_<?= htmlspecialchars($modKey, ENT_QUOTES, 'UTF-8') ?>" value="1" class="rounded border-slate-300 text-emerald-700" <?= $checked ? 'checked' : '' ?>>
                    <span class="text-sm font-semibold text-slate-800"><?= htmlspecialchars($modLabel, ENT_QUOTES, 'UTF-8') ?></span>
                </label>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-6 sm:p-8 shadow-sm space-y-5">
            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
                <div>
                    <h2 class="text-sm font-black uppercase tracking-widest text-slate-900">Rôles</h2>
                    <p class="mt-1 text-sm text-slate-600">
                        <?= count($roles) ?> rôle<?= count($roles) > 1 ? 's' : '' ?> déjà créé<?= count($roles) > 1 ? 's' : '' ?> pour votre communauté.
                        Choisissez le rôle attribué aux nouveaux inscrits (invités).
                    </p>
                </div>
                <a href="<?= htmlspecialchars(url('back-office/roles'), ENT_QUOTES, 'UTF-8') ?>" class="shrink-0 inline-flex items-center rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">Gérer les rôles</a>
            </div>
            <?php if ($roles !== []): ?>
                <ul class="flex flex-wrap gap-2">
                    <?php foreach (array_slice($roles, 0, 12) as $role): ?>
                        <li class="rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-[11px] font-semibold text-slate-700"><?= htmlspecialchars((string) ($role['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></li>
                    <?php endforeach; ?>
                    <?php if (count($roles) > 12): ?>
                        <li class="rounded-full border border-slate-200 px-2.5 py-1 text-[11px] font-semibold text-slate-500">+<?= count($roles) - 12 ?></li>
                    <?php endif; ?>
                </ul>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1.5" for="default_guest_role_slug">Rôle attribué aux nouveaux inscrits</label>
                    <select id="default_guest_role_slug" name="default_guest_role_slug" class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100">
                        <?php foreach ($roles as $role): ?>
                            <?php $rs = (string) ($role['slug'] ?? ''); ?>
                            <option value="<?= htmlspecialchars($rs, ENT_QUOTES, 'UTF-8') ?>" <?= $guestSlug === $rs ? 'selected' : '' ?>><?= htmlspecialchars((string) ($role['name'] ?? $rs), ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <p class="text-xs text-slate-500">
                    Pour appliquer un kit de permissions prêt à l’emploi,
                    <a href="<?= htmlspecialchars(url('back-office/roles/presets'), ENT_QUOTES, 'UTF-8') ?>" class="font-semibold text-emerald-800 underline decoration-emerald-300">ouvrez les modèles de rôles</a>.
                </p>
            <?php else: ?>
                <p class="text-sm text-amber-900 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3">Aucun rôle n’a encore été créé. Passez par la gestion des rôles pour en définir.</p>
            <?php endif; ?>
        </section>

        <div class="flex flex-col sm:flex-row sm:flex-wrap gap-3 pt-2">
            <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-emerald-700 px-5 py-3 text-sm font-bold text-white shadow-sm hover:bg-emerald-800">Enregistrer</button>
            <button type="submit" formaction="<?= htmlspecialchars(url('back-office/configuration-initiale/complete'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center justify-center rounded-xl border border-emerald-300 bg-emerald-50 px-5 py-3 text-sm font-bold text-emerald-950 hover:bg-emerald-100" name="save_before_complete" value="1">Terminer et accéder au back-office</button>
            <?php if (!$completed): ?>
            <button type="submit" formaction="<?= htmlspecialchars(url('back-office/configuration-initiale/dismiss'), ENT_QUOTES, 'UTF-8') ?>" formnovalidate class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-5 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50" name="redirect_to" value="setup">Plus tard</button>
            <?php endif; ?>
            <a href="<?= htmlspecialchars(url('back-office'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center justify-center rounded-xl border border-transparent px-5 py-3 text-sm font-semibold text-slate-500 hover:text-slate-800">Retour au back-office</a>
        </div>
    </form>

    <section class="rounded-2xl border border-slate-200 bg-white p-6 sm:p-8 shadow-sm">
        <h2 class="text-sm font-black uppercase tracking-widest text-slate-900">Pour aller plus loin</h2>
        <p class="mt-1 text-sm text-slate-600 mb-5">Ces étapes ne sont pas obligatoires ici — ouvrez-les quand vous êtes prêt.</p>
        <div class="grid gap-3 sm:grid-cols-3">
            <a href="<?= htmlspecialchars(url('orbat'), ENT_QUOTES, 'UTF-8') ?>" class="rounded-xl border border-slate-200 bg-slate-50/80 px-4 py-4 hover:border-emerald-300 hover:bg-emerald-50/40 transition">
                <p class="text-sm font-bold text-slate-900">Organisation (ORBAT)</p>
                <p class="mt-1 text-xs text-slate-600 leading-relaxed">Affinez la structure des unités et des postes.</p>
            </a>
            <a href="<?= htmlspecialchars(url('back-office/invitations'), ENT_QUOTES, 'UTF-8') ?>" class="rounded-xl border border-slate-200 bg-slate-50/80 px-4 py-4 hover:border-emerald-300 hover:bg-emerald-50/40 transition">
                <p class="text-sm font-bold text-slate-900">Invitations</p>
                <p class="mt-1 text-xs text-slate-600 leading-relaxed">Invitez vos premiers membres par e-mail.</p>
            </a>
            <a href="<?= htmlspecialchars(url('back-office/community/presentation'), ENT_QUOTES, 'UTF-8') ?>" class="rounded-xl border border-slate-200 bg-slate-50/80 px-4 py-4 hover:border-emerald-300 hover:bg-emerald-50/40 transition">
                <p class="text-sm font-bold text-slate-900">Vitrine &amp; candidature</p>
                <p class="mt-1 text-xs text-slate-600 leading-relaxed">Textes détaillés de la page d’accueil publique.</p>
            </a>
        </div>
        <?php if ($publicPageUrl !== ''): ?>
            <p class="mt-4 text-sm text-slate-600">
                <a href="<?= htmlspecialchars($publicPageUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener" class="font-semibold text-emerald-800 underline decoration-emerald-300">Voir la page publique</a>
            </p>
        <?php endif; ?>
    </section>
</div>
</div>
