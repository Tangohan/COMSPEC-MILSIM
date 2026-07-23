<?php
/** @var array<string, mixed>|null $donation */
/** @var bool $fulfilled */
/** @var string|null $amountLabel */
/** @var bool $isPaid */
/** @var bool $badgeGranted */
?>
<div class="min-h-[calc(80vh-2rem)] bg-gradient-to-b from-slate-100 via-slate-50 to-emerald-50/40">
    <div class="mx-auto max-w-xl px-4 py-10 sm:px-6 lg:py-14">
        <div class="overflow-hidden rounded-[2rem] border border-slate-200/80 bg-white shadow-[0_24px_80px_-32px_rgba(15,23,42,0.28)]">
            <div class="bg-gradient-to-br from-emerald-900 via-slate-900 to-slate-950 px-6 py-10 text-center sm:px-10">
                <p class="text-[11px] font-black uppercase tracking-[0.35em] text-emerald-300/95">Soutien ATAK</p>
                <h1 class="mt-3 text-3xl font-black tracking-tight text-white">Merci</h1>
                <p class="mx-auto mt-4 max-w-sm text-sm leading-relaxed text-slate-300">
                    Votre contribution aide à faire vivre le module ATAK pour toute la communauté.
                </p>
            </div>
            <div class="space-y-6 px-6 py-8 text-center sm:px-10">
                <?php if (!empty($isPaid) && !empty($amountLabel)): ?>
                <p class="text-sm text-slate-700">
                    Montant enregistré&nbsp;: <span class="font-bold text-slate-900"><?= htmlspecialchars((string) $amountLabel, ENT_QUOTES, 'UTF-8') ?></span>
                </p>
                <?php elseif (empty($donation)): ?>
                <p class="text-sm text-slate-600">Si vous venez de payer, le badge peut apparaître sous quelques instants sur votre profil.</p>
                <?php else: ?>
                <p class="text-sm text-slate-600">Nous finalisons l’enregistrement de votre soutien. Le badge sera ajouté dès confirmation du paiement.</p>
                <?php endif; ?>

                <?php if (!empty($badgeGranted) || (!empty($isPaid) && !empty($fulfilled))): ?>
                <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-4 text-sm text-emerald-900">
                    Le badge <strong>Donateur ATAK</strong> a été ajouté à votre profil.
                </div>
                <?php elseif (!empty($isPaid)): ?>
                <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-4 text-sm text-emerald-900">
                    Paiement confirmé. Le badge <strong>Donateur ATAK</strong> sera visible sous peu sur votre fiche.
                </div>
                <?php endif; ?>

                <div class="flex flex-col gap-3 sm:flex-row sm:justify-center">
                    <a href="<?= htmlspecialchars(url('personnel/me'), ENT_QUOTES, 'UTF-8') ?>"
                       class="inline-flex items-center justify-center rounded-2xl bg-slate-900 px-6 py-3.5 text-xs font-black uppercase tracking-[0.2em] text-white transition hover:bg-emerald-800">
                        Voir mon profil
                    </a>
                    <a href="<?= htmlspecialchars(url('atak'), ENT_QUOTES, 'UTF-8') ?>"
                       class="inline-flex items-center justify-center rounded-2xl border border-slate-200 px-6 py-3.5 text-xs font-black uppercase tracking-[0.2em] text-slate-700 transition hover:border-slate-300 hover:bg-slate-50">
                        Ouvrir ATAK
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
