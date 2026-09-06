<?php
declare(strict_types=1);

$people = is_array($platformAdmins ?? null) ? $platformAdmins : [];
$confirmGrant = (string) ($platformAdminConfirmGrant ?? 'OUVRIR LE SITE');
$confirmRevoke = (string) ($platformAdminConfirmRevoke ?? 'RETIRER L ACCES');
$flashOk = \App\Core\Session::getFlash('success');
$flashErr = \App\Core\Session::getFlash('error');
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$csrf = $h(\App\Core\Csrf::token());
?>
<div class="min-h-0 flex-1 bg-slate-50">
    <div class="mx-auto max-w-3xl px-4 py-8 sm:px-6 lg:px-8 space-y-6">
        <nav class="text-sm text-slate-500">
            <a href="<?= $h(url('admin')) ?>" class="font-semibold text-emerald-800 hover:text-emerald-950">Administration du site</a>
            <span class="mx-2" aria-hidden="true">/</span>
            <span class="text-slate-800">Administrateurs du site</span>
        </nav>

        <header class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-500">Liste fermée</p>
            <h1 class="text-2xl font-black text-slate-900">Administrateurs du site</h1>
            <p class="mt-2 text-sm text-slate-600">
                Seules les personnes de cette liste peuvent ouvrir l’administration du site.
                Ce n’est pas un niveau d’accès de communauté, et ce n’est pas un choix sur chaque dossier.
            </p>
        </header>

        <?php if ($flashOk): ?>
            <p class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-950" role="status"><?= $h($flashOk) ?></p>
        <?php endif; ?>
        <?php if ($flashErr): ?>
            <p class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-950" role="alert"><?= $h($flashErr) ?></p>
        <?php endif; ?>

        <section class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
            <div class="border-b border-slate-100 px-5 py-3">
                <h2 class="text-base font-bold text-slate-900">Personnes habilitées</h2>
                <p class="mt-0.5 text-sm text-slate-600"><?= count($people) ?> personne(s) — le site doit en garder au moins une.</p>
            </div>
            <?php if ($people === []): ?>
                <p class="px-5 py-6 text-sm text-slate-600">Aucune personne n’est encore inscrite. Ajoutez-en une ci-dessous.</p>
            <?php else: ?>
                <ul class="divide-y divide-slate-100">
                    <?php foreach ($people as $person): ?>
                        <?php
                        $mail = (string) ($person['email'] ?? '');
                        $name = trim((string) ($person['display_name'] ?? ''));
                        $isLast = count($people) <= 1;
                        ?>
                        <li class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p class="font-semibold text-slate-900"><?= $name !== '' ? $h($name) : $h($mail) ?></p>
                                <?php if ($name !== ''): ?>
                                    <p class="text-sm text-slate-500"><?= $h($mail) ?></p>
                                <?php endif; ?>
                            </div>
                            <?php if (!$isLast): ?>
                                <form method="post" action="<?= $h(url('admin/users/platform-admin')) ?>" class="flex flex-col gap-2 sm:items-end">
                                    <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
                                    <input type="hidden" name="action" value="revoke">
                                    <input type="hidden" name="email" value="<?= $h($mail) ?>">
                                    <label class="text-xs text-slate-500">
                                        Recopiez <span class="font-mono font-semibold text-slate-800"><?= $h($confirmRevoke) ?></span>
                                        <input type="text" name="confirmation" required autocomplete="off"
                                               class="mt-1 block w-full min-w-[16rem] rounded-lg border border-slate-300 px-3 py-1.5 text-sm">
                                    </label>
                                    <button type="submit" class="text-sm font-semibold text-rose-700 hover:underline">Retirer l’accès</button>
                                </form>
                            <?php else: ?>
                                <p class="text-xs text-slate-500">Dernière personne habilitée.</p>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <section class="rounded-xl border border-amber-200 bg-amber-50/70 p-5 shadow-sm">
            <h2 class="text-base font-bold text-amber-950">Ajouter une personne</h2>
            <p class="mt-1 text-sm text-amber-900/90">
                Elle aura accès à tout le site : communautés, comptes, maintenance et outils techniques.
                Réservé à ceux qui tiennent vraiment la plateforme.
            </p>
            <form method="post" action="<?= $h(url('admin/users/platform-admin')) ?>" class="mt-4 space-y-3">
                <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
                <input type="hidden" name="action" value="grant">
                <div>
                    <label for="platform-admin-email" class="text-xs font-bold uppercase tracking-wider text-amber-950">Adresse du compte</label>
                    <input type="email" id="platform-admin-email" name="email" required autocomplete="off"
                           class="mt-1 w-full rounded-lg border border-amber-200 bg-white px-3 py-2 text-sm text-slate-900"
                           placeholder="personne@exemple.fr">
                </div>
                <div>
                    <label for="platform-admin-confirm" class="text-xs font-bold uppercase tracking-wider text-amber-950">
                        Recopiez <?= $h($confirmGrant) ?>
                    </label>
                    <input type="text" id="platform-admin-confirm" name="confirmation" required autocomplete="off"
                           class="mt-1 w-full rounded-lg border border-amber-200 bg-white px-3 py-2 text-sm text-slate-900">
                </div>
                <button type="submit" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                    Ouvrir l’administration du site
                </button>
            </form>
        </section>
    </div>
</div>
