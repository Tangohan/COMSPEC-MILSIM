<?php
$user = $user ?? null;
$userProfile = is_array($userProfile ?? null) ? $userProfile : [];
$userRoleIds = $userRoleIds ?? [];
$roles = $roles ?? [];
$completenessAccount = $completenessAccount ?? ($completeness ?? ['score' => 0, 'missing' => [], 'sections_critiques' => []]);
$completenessPersonnel = $completenessPersonnel ?? null;
$isServiceAccount = $isServiceAccount ?? false;
$showPlatformDiagnostics = $showPlatformDiagnostics ?? false;
if (!$user) {
    echo '<p>Utilisateur introuvable.</p>';
    return;
}
$uid = (int) $user['id'];
$personnelEditUrl = url('personnel/' . $uid . '/edit');
$displayName = trim((string) ($user['display_name'] ?? ''));
$email = (string) ($user['email'] ?? '');
$avatarSrc = function_exists('user_media_public_url')
    ? user_media_public_url($user['avatar_url'] ?? null)
    : null;
$initialsSource = $displayName !== '' ? $displayName : $email;
$initials = function_exists('user_display_initials')
    ? user_display_initials($initialsSource, 2)
    : mb_strtoupper(mb_substr($initialsSource, 0, 2, 'UTF-8'), 'UTF-8');
$ust = (string) ($user['status'] ?? '');
$statusLabel = match ($ust) {
    'active' => 'Compte actif',
    'inactive' => 'Compte inactif',
    'pending_verification' => 'En attente de vérification de l’e-mail',
    default => $ust !== '' ? 'Statut à clarifier' : '—',
};
$statusBadgeMod = match ($ust) {
    'active' => 'bo-user-edit__badge--ok',
    'inactive' => 'bo-user-edit__badge--muted',
    default => 'bo-user-edit__badge--warn',
};

$levelBadge = static function (string $level): array {
    return match ($level) {
        \App\Services\Admin\ProfileCompletenessService::LEVEL_BLOCKING => [
            'text' => 'Indispensable',
            'class' => 'bg-rose-50 text-rose-900 ring-rose-200',
        ],
        \App\Services\Admin\ProfileCompletenessService::LEVEL_RECOMMENDED => [
            'text' => 'À prévoir',
            'class' => 'bg-amber-50 text-amber-900 ring-amber-200',
        ],
        \App\Services\Admin\ProfileCompletenessService::LEVEL_ADMINISTRATIVE => [
            'text' => 'Ergonomie du compte',
            'class' => 'bg-slate-100 text-slate-700 ring-slate-200',
        ],
        default => [
            'text' => 'À compléter',
            'class' => 'bg-slate-50 text-slate-600 ring-slate-200',
        ],
    };
};
$listUrl = url('back-office/users');
$editUrl = url('back-office/users/' . $uid . '/edit');
?>
<link href="<?= htmlspecialchars(asset_url('assets/css/back-office-users.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
<div class="bo-user-edit">
    <header class="bo-user-edit__hero">
        <div class="bo-user-edit__hero-inner">
            <div class="min-w-0">
                <a href="<?= htmlspecialchars($listUrl, ENT_QUOTES, 'UTF-8') ?>" class="bo-user-edit__back">← Liste des membres</a>
                <div class="bo-user-edit__identity">
                    <div class="bo-user-edit__avatar" aria-hidden="true">
                        <?php if ($avatarSrc): ?>
                            <img src="<?= htmlspecialchars($avatarSrc, ENT_QUOTES, 'UTF-8') ?>" alt="" class="h-full w-full object-cover" data-img-fallback="avatar" data-img-initials="<?= htmlspecialchars($initials, ENT_QUOTES, 'UTF-8') ?>">
                        <?php else: ?>
                            <?= htmlspecialchars($initials, ENT_QUOTES, 'UTF-8') ?>
                        <?php endif; ?>
                    </div>
                    <div class="min-w-0">
                        <p class="bo-user-edit__eyebrow">Membres · Fiche</p>
                        <h1 class="bo-user-edit__title"><?= htmlspecialchars($displayName !== '' ? $displayName : 'Fiche membre', ENT_QUOTES, 'UTF-8') ?></h1>
                        <p class="bo-user-edit__lead" style="margin-top:0.35rem"><?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?></p>
                        <div class="mt-3 flex flex-wrap items-center gap-2">
                            <span class="bo-user-edit__badge <?= htmlspecialchars($statusBadgeMod, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8') ?></span>
                            <?php if ($isServiceAccount): ?>
                            <span class="bo-user-edit__badge bo-user-edit__badge--muted">Compte technique</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="bo-user-edit__hero-actions">
                <a href="<?= htmlspecialchars($personnelEditUrl, ENT_QUOTES, 'UTF-8') ?>" class="bo-user-edit__btn bo-user-edit__btn--ghost">Fiche personnelle</a>
                <a href="<?= htmlspecialchars($editUrl, ENT_QUOTES, 'UTF-8') ?>" class="bo-user-edit__btn bo-user-edit__btn--solid">Réglages du compte</a>
            </div>
        </div>
    </header>

    <div class="bo-user-edit__deck">
        <?php if (($user['status'] ?? '') !== 'inactive' && !$isServiceAccount): ?>
        <section class="bo-user-edit__panel bo-user-edit__panel--amber mb-4" style="margin-bottom:1rem">
            <form method="post" action="<?= url('back-office/users/' . $uid . '/deactivate') ?>" class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between" onsubmit="return confirm('Désactiver l’accès de ce membre ?');">
                <?= \App\Core\Csrf::field() ?>
                <label class="flex max-w-xl items-start gap-2 text-xs text-slate-600">
                    <input type="checkbox" name="block_email_rejoin" value="1" class="mt-0.5 rounded border-slate-300">
                    <span>Empêcher aussi toute nouvelle inscription ou candidature avec la même adresse e-mail dans cette communauté.</span>
                </label>
                <button type="submit" class="inline-flex shrink-0 items-center justify-center rounded-xl border border-rose-200 bg-white px-4 py-2.5 text-sm font-semibold text-rose-800 transition hover:bg-rose-50">
                    Désactiver l’accès
                </button>
            </form>
        </section>
        <?php endif; ?>

        <?php $flashOk = \App\Core\Session::getFlash('success'); $flashErr = \App\Core\Session::getFlash('error'); $flashWarn = \App\Core\Session::getFlash('warning'); ?>
        <?php if ($flashOk): ?><div class="bo-user-edit__flash bo-user-edit__flash--ok" role="status"><?= htmlspecialchars((string) $flashOk) ?></div><?php endif; ?>
        <?php if ($flashWarn): ?><div class="bo-user-edit__flash bo-user-edit__flash--warn" role="status"><?= htmlspecialchars((string) $flashWarn) ?></div><?php endif; ?>
        <?php if ($flashErr): ?><div class="bo-user-edit__flash bo-user-edit__flash--err" role="alert"><?= htmlspecialchars((string) $flashErr) ?></div><?php endif; ?>

        <?php if ($showPlatformDiagnostics): ?>
        <div class="rounded-xl border border-indigo-200 bg-indigo-50/80 px-4 py-3 text-sm text-indigo-950 mb-4">
            <strong class="font-semibold">Vue diagnostic plateforme.</strong> Vous voyez des critères supplémentaires (ex. photo de profil) et la liste complète des contrôles dossier ; les administrateurs de l’unité sans ce niveau d’habilitation ont une vue simplifiée.
        </div>
        <?php endif; ?>

        <?php if ($isServiceAccount): ?>
        <div class="bo-user-edit__panel mb-4" style="margin-bottom:1rem">
            <strong class="text-slate-900">Compte technique</strong> — utilisé pour la modération automatique et les traitements internes. Il n’a pas de fiche personnage jouable.
        </div>
        <?php endif; ?>

        <?php if ($ust === 'pending_verification' && !$isServiceAccount): ?>
        <div class="bo-user-edit__panel bo-user-edit__panel--amber mb-4" style="margin-bottom:1rem">
            <p class="font-semibold text-amber-950">Compte en attente de confirmation</p>
            <p class="mt-1 text-sm text-amber-900/90">Le membre doit ouvrir le lien reçu par e-mail pour activer son accès. Vous pouvez renvoyer un nouveau lien s’il a expiré ou n’a pas été reçu.</p>
            <form method="post" action="<?= url('back-office/users/' . $uid . '/resend-verification') ?>" class="mt-3">
                <?= \App\Core\Csrf::field() ?>
                <button type="submit" class="inline-flex items-center rounded-lg bg-amber-800 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-900 transition-colors">
                    Renvoyer le lien de confirmation
                </button>
            </form>
        </div>
        <?php endif; ?>

        <?php if (!$isServiceAccount && (($completenessAccount['score'] ?? 100) < 100 || ($completenessPersonnel !== null && ($completenessPersonnel['score'] ?? 100) < 100))): ?>
        <div class="bo-user-edit__panel mb-4" style="margin-bottom:1rem">
            <p class="font-semibold text-amber-950">Profil incomplet — rappel par courriel</p>
            <p class="mt-1 text-sm text-slate-600">Un message est envoyé à l’adresse du compte avec un lien direct vers la fiche personnelle.</p>
            <form method="post" action="<?= url('back-office/users/' . $uid . '/notify-profile') ?>" class="mt-3">
                <?= \App\Core\Csrf::field() ?>
                <button type="submit" class="inline-flex items-center rounded-lg bg-amber-700 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-800 transition-colors">
                    Envoyer un rappel par e-mail
                </button>
            </form>
        </div>
        <?php endif; ?>

        <section aria-labelledby="legend-comp" class="bo-user-edit__panel" style="margin-bottom:1rem">
            <h2 id="legend-comp" class="bo-user-edit__panel-title">Légende — complétude du compte</h2>
            <div class="mt-3 flex flex-wrap gap-3 text-xs text-slate-600">
                <span class="inline-flex items-center gap-1.5 rounded-lg ring-1 ring-inset px-2.5 py-1 font-semibold bg-rose-50 text-rose-900 ring-rose-200">Indispensable</span>
                <span class="text-slate-500">bloque les vérifications essentielles tant que ce n’est pas renseigné.</span>
            </div>
            <div class="mt-2 flex flex-wrap gap-3 text-xs text-slate-600">
                <span class="inline-flex items-center gap-1.5 rounded-lg ring-1 ring-inset px-2.5 py-1 font-semibold bg-amber-50 text-amber-900 ring-amber-200">À prévoir</span>
                <span class="text-slate-500">recommandé pour une fiche exploitable par l’état-major.</span>
            </div>
            <?php if ($showPlatformDiagnostics): ?>
            <div class="mt-2 flex flex-wrap gap-3 text-xs text-slate-600">
                <span class="inline-flex items-center gap-1.5 rounded-lg ring-1 ring-inset px-2.5 py-1 font-semibold bg-slate-100 text-slate-700 ring-slate-200">Ergonomie du compte</span>
                <span class="text-slate-500">critère d’affichage côté produit, sans impact sur les habilitations.</span>
            </div>
            <?php endif; ?>
        </section>

        <div class="grid gap-4 lg:grid-cols-2" style="margin-top:1rem">
            <div class="bo-user-edit__panel flex flex-col">
                <h2 class="bo-user-edit__panel-title">Compte</h2>
                <p class="bo-user-edit__panel-lead">Connexion, identité affichée sur le portail, rôle dans l’unité.</p>

                <?php if (($completenessAccount['score'] ?? 100) < 100): ?>
                <?php $accScore = (int) ($completenessAccount['score'] ?? 0); ?>
                <div class="mb-5 mt-4 rounded-xl border border-slate-100 bg-slate-50/80 p-4">
                    <div class="flex items-center justify-between gap-3 mb-2">
                        <span class="text-sm font-bold text-slate-900">Complétude du compte</span>
                        <span class="text-lg font-black tabular-nums text-slate-900"><?= $accScore ?>%</span>
                    </div>
                    <div class="h-2 rounded-full bg-slate-200 overflow-hidden">
                        <div class="h-full rounded-full bg-slate-800 transition-all" style="width: <?= min(100, max(0, $accScore)) ?>%"></div>
                    </div>
                    <?php if (!empty($completenessAccount['missing'])): ?>
                    <ul class="mt-4 space-y-2 list-none">
                        <?php foreach ($completenessAccount['missing'] as $m):
                            $lv = (string) ($m['level'] ?? '');
                            if ($lv === \App\Services\Admin\ProfileCompletenessService::LEVEL_ADMINISTRATIVE && !$showPlatformDiagnostics) {
                                continue;
                            }
                            $b = $levelBadge($lv);
                            ?>
                        <li class="flex flex-wrap items-start gap-2 text-sm">
                            <span class="inline-flex shrink-0 rounded-md px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide ring-1 ring-inset <?= htmlspecialchars($b['class']) ?>"><?= htmlspecialchars($b['text']) ?></span>
                            <span class="text-slate-800 leading-snug"><?= htmlspecialchars((string) ($m['label'] ?? '')) ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <p class="text-sm font-medium text-emerald-800 mb-5 mt-4 flex items-center gap-2">
                    <span class="h-2 w-2 rounded-full bg-emerald-500" aria-hidden="true"></span>
                    Informations minimales du compte présentes.
                </p>
                <?php endif; ?>

                <dl class="space-y-3 text-sm border-t border-slate-100 pt-5 mt-auto">
                    <div>
                        <dt class="text-xs font-semibold text-slate-500">Adresse e-mail</dt>
                        <dd class="mt-0.5 text-slate-900 break-all"><?= htmlspecialchars($user['email']) ?></dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold text-slate-500">Nom affiché (portail)</dt>
                        <dd class="mt-0.5 text-slate-900"><?= htmlspecialchars($user['display_name'] ?? '—') ?></dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold text-slate-500">Indicatif (compte)</dt>
                        <dd class="mt-0.5 text-slate-900"><?= htmlspecialchars($user['callsign'] ?? '—') ?></dd>
                    </div>
                    <?php if ($showPlatformDiagnostics): ?>
                    <div>
                        <dt class="text-xs font-semibold text-slate-500">Prénom (état civil) <span class="font-normal normal-case text-slate-400">— vue plateforme</span></dt>
                        <dd class="mt-0.5 text-slate-900"><?= htmlspecialchars($userProfile['first_name'] ?? '—') ?></dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold text-slate-500">Nom (état civil) <span class="font-normal normal-case text-slate-400">— vue plateforme</span></dt>
                        <dd class="mt-0.5 text-slate-900"><?= htmlspecialchars($userProfile['last_name'] ?? '—') ?></dd>
                    </div>
                    <?php endif; ?>
                    <div>
                        <dt class="text-xs font-semibold text-slate-500">Rôles dans l’unité</dt>
                        <dd class="mt-0.5 text-slate-900">
                            <?php
                            $roleNames = [];
                            foreach ($roles as $rr) {
                                if (in_array((int) ($rr['id'] ?? 0), $userRoleIds, true)) {
                                    $roleNames[] = (string) ($rr['name'] ?? '');
                                }
                            }
                            echo $roleNames !== [] ? htmlspecialchars(implode(', ', $roleNames)) : '—';
                            ?>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold text-slate-500">Statut du compte</dt>
                        <dd class="mt-0.5">
                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold <?= $ust === 'active' ? 'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-200' : ($ust === 'inactive' ? 'bg-slate-100 text-slate-700 ring-1 ring-slate-200' : 'bg-amber-50 text-amber-900 ring-1 ring-amber-200') ?>"><?= htmlspecialchars($statusLabel) ?></span>
                        </dd>
                    </div>
                </dl>
            </div>

            <div class="bo-user-edit__panel flex flex-col">
                <h2 class="bo-user-edit__panel-title">Dossier opérationnel</h2>
                <p class="bo-user-edit__panel-lead">Personnage, affectation, clearance et qualifications — distinct du compte de connexion.</p>

                <?php if ($isServiceAccount): ?>
                <p class="mt-4 text-sm text-slate-500">Non applicable pour un compte technique.</p>
                <?php elseif ($completenessPersonnel !== null): ?>
                    <?php if (($completenessPersonnel['score'] ?? 100) < 100): ?>
                    <?php $pScore = (int) ($completenessPersonnel['score'] ?? 0); ?>
                <div class="mb-5 mt-4 rounded-xl border border-slate-100 bg-slate-50/80 p-4">
                    <div class="flex items-center justify-between gap-3 mb-2">
                        <span class="text-sm font-bold text-slate-900">Complétude du dossier</span>
                        <span class="text-lg font-black tabular-nums text-slate-900"><?= $pScore ?>%</span>
                    </div>
                    <div class="h-2 rounded-full bg-slate-200 overflow-hidden">
                        <div class="h-full rounded-full bg-blue-700 transition-all" style="width: <?= min(100, max(0, $pScore)) ?>%"></div>
                    </div>
                    <?php if (!empty($completenessPersonnel['sections_critiques'])): ?>
                    <p class="mt-4 text-xs font-bold uppercase tracking-wide text-rose-800">Points à traiter en priorité</p>
                    <ul class="mt-2 space-y-1.5 list-none text-sm text-rose-900">
                        <?php foreach ($completenessPersonnel['sections_critiques'] as $c): ?>
                        <li class="flex gap-2 items-start">
                            <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-rose-500" aria-hidden="true"></span>
                            <span><?= htmlspecialchars($c) ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                    <?php if (!empty($completenessPersonnel['missing_labels'])): ?>
                    <p class="mt-4 text-xs font-bold uppercase tracking-wide text-slate-600">Autres éléments à compléter</p>
                    <ul class="mt-2 space-y-1.5 list-none text-sm text-slate-700">
                        <?php foreach ($completenessPersonnel['missing_labels'] as $lbl): ?>
                        <li class="flex gap-2 items-start">
                            <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-slate-400" aria-hidden="true"></span>
                            <span><?= htmlspecialchars($lbl) ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                </div>
                    <?php else: ?>
                <p class="text-sm font-medium text-emerald-800 mb-5 mt-4 flex items-center gap-2">
                    <span class="h-2 w-2 rounded-full bg-emerald-500" aria-hidden="true"></span>
                    Éléments principaux du dossier renseignés.
                </p>
                    <?php endif; ?>
                <a href="<?= htmlspecialchars($personnelEditUrl, ENT_QUOTES, 'UTF-8') ?>" class="mt-auto inline-flex items-center justify-center rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-bold text-white hover:bg-slate-800 transition-colors">
                    Ouvrir la fiche personnelle
                </a>
                <?php endif; ?>
            </div>
        </div>

        <p class="bo-user-edit__footer-link" style="margin-top:1.25rem">
            <a href="<?= htmlspecialchars($listUrl, ENT_QUOTES, 'UTF-8') ?>">Retour à la liste des membres</a>
        </p>
    </div>
</div>
