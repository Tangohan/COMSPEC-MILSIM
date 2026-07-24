<?php
declare(strict_types=1);

$inviteCodes = $inviteCodes ?? [];
$showAll = $showAll ?? false;
?>

<div class="space-y-8">
    <!-- En-tête avec actions -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Codes d'invitation</h1>
            <p class="mt-1 text-sm text-slate-600">Permettez à vos membres de migrer rapidement en leur donnant un code qui valide automatiquement leur candidature.</p>
        </div>
        <a href="<?= htmlspecialchars(url('back-office/recruitments/codes-invitation/creer'), ENT_QUOTES, 'UTF-8') ?>" 
           class="inline-flex items-center justify-center gap-2 rounded-lg bg-slate-900 px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-500 focus-visible:ring-offset-2">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Créer un code
        </a>
    </div>

    <!-- Filtres -->
    <div class="flex gap-3">
        <a href="<?= htmlspecialchars(url('back-office/recruitments/codes-invitation'), ENT_QUOTES, 'UTF-8') ?>" 
           class="inline-flex items-center gap-2 rounded-lg border px-4 py-2 text-sm font-medium transition <?= !$showAll ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-50' ?>">
            Codes actifs
        </a>
        <a href="<?= htmlspecialchars(url('back-office/recruitments/codes-invitation') . '?all=1', ENT_QUOTES, 'UTF-8') ?>" 
           class="inline-flex items-center gap-2 rounded-lg border px-4 py-2 text-sm font-medium transition <?= $showAll ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-50' ?>">
            Tous les codes
        </a>
    </div>

    <?php if (empty($inviteCodes)): ?>
        <!-- État vide -->
        <div class="rounded-xl border-2 border-dashed border-slate-200 bg-slate-50/50 p-12 text-center">
            <svg class="mx-auto h-12 w-12 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
            </svg>
            <h3 class="mt-4 text-base font-semibold text-slate-900">Aucun code d'invitation</h3>
            <p class="mt-2 text-sm text-slate-600">
                <?= $showAll ? 'Vous n\'avez encore créé aucun code d\'invitation.' : 'Aucun code actif pour le moment.' ?>
            </p>
            <a href="<?= htmlspecialchars(url('back-office/recruitments/codes-invitation/creer'), ENT_QUOTES, 'UTF-8') ?>" 
               class="mt-6 inline-flex items-center gap-2 rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Créer le premier code
            </a>
        </div>
    <?php else: ?>
        <!-- Liste des codes -->
        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-700">Code</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-700">Libellé</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-700">Utilisations</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-700">Statut</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-700">Expiration</th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-700">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        <?php foreach ($inviteCodes as $code): 
                            $codeId = (int) ($code['id'] ?? 0);
                            $codeValue = htmlspecialchars((string) ($code['code'] ?? ''), ENT_QUOTES, 'UTF-8');
                            $label = htmlspecialchars((string) ($code['label'] ?? 'Sans libellé'), ENT_QUOTES, 'UTF-8');
                            $usesCount = (int) ($code['uses_count'] ?? 0);
                            $maxUses = $code['max_uses'] !== null ? (int) $code['max_uses'] : null;
                            $expiresAt = $code['expires_at'] ?? null;
                            $autoAccept = !empty($code['auto_accept']);
                            
                            $isExpired = $expiresAt !== null && strtotime((string) $expiresAt) <= time();
                            $isMaxedOut = $maxUses !== null && $usesCount >= $maxUses;
                            $isActive = !$isExpired && !$isMaxedOut;
                        ?>
                            <tr class="hover:bg-slate-50/50 transition">
                                <td class="whitespace-nowrap px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <code class="rounded bg-slate-100 px-2 py-1 text-sm font-mono font-semibold text-slate-900"><?= $codeValue ?></code>
                                        <?php if ($autoAccept): ?>
                                            <span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700 ring-1 ring-emerald-600/20">
                                                Validation auto
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm font-medium text-slate-900"><?= $label ?></div>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4">
                                    <div class="text-sm text-slate-900">
                                        <?= $usesCount ?><?= $maxUses !== null ? ' / ' . $maxUses : '' ?>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4">
                                    <?php if ($isActive): ?>
                                        <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700 ring-1 ring-emerald-600/20">
                                            <svg class="mr-1 h-3 w-3" fill="currentColor" viewBox="0 0 8 8">
                                                <circle cx="4" cy="4" r="3"/>
                                            </svg>
                                            Actif
                                        </span>
                                    <?php elseif ($isExpired): ?>
                                        <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600 ring-1 ring-slate-300">
                                            <svg class="mr-1 h-3 w-3" fill="currentColor" viewBox="0 0 8 8">
                                                <circle cx="4" cy="4" r="3"/>
                                            </svg>
                                            Expiré
                                        </span>
                                    <?php elseif ($isMaxedOut): ?>
                                        <span class="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700 ring-1 ring-amber-600/20">
                                            <svg class="mr-1 h-3 w-3" fill="currentColor" viewBox="0 0 8 8">
                                                <circle cx="4" cy="4" r="3"/>
                                            </svg>
                                            Limite atteinte
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-600">
                                    <?= $expiresAt !== null ? date('d/m/Y', strtotime((string) $expiresAt)) : '—' ?>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-right text-sm">
                                    <a href="<?= htmlspecialchars(url('back-office/recruitments/codes-invitation/' . $codeId), ENT_QUOTES, 'UTF-8') ?>" 
                                       class="inline-flex items-center gap-1 font-medium text-sky-600 hover:text-sky-700">
                                        Voir les détails
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                        </svg>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <!-- Aide -->
    <div class="rounded-lg border border-sky-200 bg-sky-50 p-6">
        <div class="flex gap-4">
            <svg class="h-6 w-6 flex-shrink-0 text-sky-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <div class="flex-1">
                <h3 class="text-sm font-semibold text-sky-900">À propos des codes d'invitation</h3>
                <p class="mt-2 text-sm text-sky-800">
                    Les codes d'invitation permettent à vos membres existants de migrer rapidement vers votre communauté. 
                    Lorsqu'un candidat utilise un code avec validation automatique, sa candidature est acceptée immédiatement.
                </p>
                <p class="mt-2 text-sm text-sky-800">
                    Vous pouvez limiter le nombre d'utilisations et définir une date d'expiration pour chaque code.
                </p>
            </div>
        </div>
    </div>
</div>
