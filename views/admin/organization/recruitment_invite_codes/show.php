<?php
declare(strict_types=1);

$inviteCode = $inviteCode ?? [];
$inviteCodeStats = $inviteCodeStats ?? ['uses' => 0, 'last_used_at' => null, 'enlistments' => []];
$inviteCodeValid = $inviteCodeValid ?? false;
$linkedRecruitmentOpening = $linkedRecruitmentOpening ?? null;
$csrfToken = \App\Core\Csrf::token();

$codeId = (int) ($inviteCode['id'] ?? 0);
$codeValue = htmlspecialchars((string) ($inviteCode['code'] ?? ''), ENT_QUOTES, 'UTF-8');
$label = htmlspecialchars((string) ($inviteCode['label'] ?? 'Sans libellé'), ENT_QUOTES, 'UTF-8');
$usesCount = (int) ($inviteCode['uses_count'] ?? 0);
$maxUses = $inviteCode['max_uses'] !== null ? (int) $inviteCode['max_uses'] : null;
$expiresAt = $inviteCode['expires_at'] ?? null;
$autoAccept = !empty($inviteCode['auto_accept']);
$createdAt = $inviteCode['created_at'] ?? null;
$enlistments = $inviteCodeStats['enlistments'] ?? [];
?>

<div class="space-y-8">
    <!-- En-tête -->
    <div>
        <a href="<?= htmlspecialchars(url('back-office/recruitments/codes-invitation'), ENT_QUOTES, 'UTF-8') ?>" 
           class="inline-flex items-center gap-2 text-sm font-medium text-slate-600 hover:text-slate-900 transition">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            Retour aux codes
        </a>
        <div class="mt-4 flex items-start justify-between gap-4">
            <div class="flex-1">
                <h1 class="text-2xl font-bold text-slate-900"><?= $label ?></h1>
                <div class="mt-2 flex items-center gap-3">
                    <code class="inline-flex items-center rounded-lg bg-slate-100 px-3 py-1.5 text-base font-mono font-bold text-slate-900"><?= $codeValue ?></code>
                    <?php if ($inviteCodeValid): ?>
                        <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700 ring-1 ring-emerald-600/20">
                            <svg class="mr-1 h-3 w-3" fill="currentColor" viewBox="0 0 8 8">
                                <circle cx="4" cy="4" r="3"/>
                            </svg>
                            Actif
                        </span>
                    <?php else: ?>
                        <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600 ring-1 ring-slate-300">
                            <svg class="mr-1 h-3 w-3" fill="currentColor" viewBox="0 0 8 8">
                                <circle cx="4" cy="4" r="3"/>
                            </svg>
                            Inactif
                        </span>
                    <?php endif; ?>
                    <?php if ($autoAccept): ?>
                        <span class="inline-flex items-center rounded-full bg-sky-50 px-2.5 py-1 text-xs font-semibold text-sky-700 ring-1 ring-sky-600/20">
                            Validation automatique
                        </span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="flex gap-3">
                <a href="<?= htmlspecialchars(url('back-office/recruitments/codes-invitation/' . $codeId . '/modifier'), ENT_QUOTES, 'UTF-8') ?>" 
                   class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                    Modifier
                </a>
            </div>
        </div>
    </div>

    <!-- Statistiques -->
    <div class="grid gap-6 sm:grid-cols-3">
        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-slate-600">Utilisations</p>
                    <p class="mt-2 text-3xl font-bold text-slate-900">
                        <?= $usesCount ?><?= $maxUses !== null ? '<span class="text-lg text-slate-500"> / ' . $maxUses . '</span>' : '' ?>
                    </p>
                </div>
                <svg class="h-8 w-8 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                </svg>
            </div>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-slate-600">Expiration</p>
                    <p class="mt-2 text-base font-semibold text-slate-900">
                        <?= $expiresAt !== null ? date('d/m/Y', strtotime((string) $expiresAt)) : 'Aucune' ?>
                    </p>
                </div>
                <svg class="h-8 w-8 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
            </div>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-slate-600">Créé le</p>
                    <p class="mt-2 text-base font-semibold text-slate-900">
                        <?= $createdAt !== null ? date('d/m/Y', strtotime((string) $createdAt)) : '—' ?>
                    </p>
                </div>
                <svg class="h-8 w-8 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
        </div>
    </div>

    <!-- Paramètres -->
    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
        <h2 class="text-lg font-semibold text-slate-900">Paramètres du code</h2>
        <dl class="mt-6 space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                <dt class="text-sm font-medium text-slate-600">Validation automatique</dt>
                <dd class="text-sm font-semibold text-slate-900"><?= $autoAccept ? 'Oui' : 'Non' ?></dd>
            </div>
            <?php if ($linkedRecruitmentOpening !== null): ?>
                <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                    <dt class="text-sm font-medium text-slate-600">Offre liée</dt>
                    <dd class="text-sm font-semibold text-slate-900">
                        <?= htmlspecialchars((string) ($linkedRecruitmentOpening['title'] ?? 'Sans titre'), ENT_QUOTES, 'UTF-8') ?>
                    </dd>
                </div>
            <?php endif; ?>
            <?php if (!empty($inviteCode['default_specialty'])): ?>
                <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                    <dt class="text-sm font-medium text-slate-600">Spécialité par défaut</dt>
                    <dd class="text-sm font-semibold text-slate-900">
                        <?= htmlspecialchars((string) $inviteCode['default_specialty'], ENT_QUOTES, 'UTF-8') ?>
                    </dd>
                </div>
            <?php endif; ?>
        </dl>
    </div>

    <!-- Candidatures -->
    <?php if (!empty($enlistments)): ?>
        <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 p-6">
                <h2 class="text-lg font-semibold text-slate-900">Candidatures avec ce code</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-700">Candidat</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-700">Email</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-700">Statut</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-700">Utilisé le</th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-700">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        <?php foreach ($enlistments as $enlistment): ?>
                            <tr class="hover:bg-slate-50/50 transition">
                                <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-slate-900">
                                    <?= htmlspecialchars(trim((string) ($enlistment['first_name'] ?? '') . ' ' . (string) ($enlistment['last_name'] ?? '')), ENT_QUOTES, 'UTF-8') ?>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-600">
                                    <?= htmlspecialchars((string) ($enlistment['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4">
                                    <?php
                                    $status = (string) ($enlistment['status'] ?? 'submitted');
                                    $statusLabel = match ($status) {
                                        'reviewed' => 'Acceptée',
                                        'rejected' => 'Refusée',
                                        'blocked' => 'Non admis',
                                        default => 'À traiter',
                                    };
                                    $statusClass = match ($status) {
                                        'reviewed' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
                                        'rejected' => 'bg-rose-50 text-rose-700 ring-rose-600/20',
                                        'blocked' => 'bg-slate-800 text-white ring-slate-600',
                                        default => 'bg-amber-50 text-amber-700 ring-amber-600/20',
                                    };
                                    ?>
                                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold ring-1 <?= $statusClass ?>">
                                        <?= $statusLabel ?>
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-600">
                                    <?= isset($enlistment['used_at']) ? date('d/m/Y à H:i', strtotime((string) $enlistment['used_at'])) : '—' ?>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-right text-sm">
                                    <a href="<?= htmlspecialchars(url('back-office/recruitments/' . (int) ($enlistment['id'] ?? 0)), ENT_QUOTES, 'UTF-8') ?>" 
                                       class="font-medium text-sky-600 hover:text-sky-700">
                                        Voir le dossier
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <!-- Actions dangereuses -->
    <div class="rounded-xl border border-rose-200 bg-rose-50 p-6">
        <h3 class="text-sm font-semibold text-rose-900">Zone de danger</h3>
        <p class="mt-2 text-sm text-rose-800">
            Désactiver ce code l'empêchera immédiatement d'être utilisé. Cette action est irréversible.
        </p>
        <form method="POST" action="<?= htmlspecialchars(url('back-office/recruitments/codes-invitation/' . $codeId . '/desactiver'), ENT_QUOTES, 'UTF-8') ?>" 
              class="mt-4"
              onsubmit="return confirm('Êtes-vous sûr de vouloir désactiver ce code ? Cette action est irréversible.');">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <button type="submit" 
                    class="inline-flex items-center gap-2 rounded-lg border border-rose-300 bg-white px-4 py-2 text-sm font-medium text-rose-700 shadow-sm transition hover:bg-rose-50">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                Désactiver ce code
            </button>
        </form>
    </div>
</div>
