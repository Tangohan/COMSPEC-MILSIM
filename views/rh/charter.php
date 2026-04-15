<?php
declare(strict_types=1);

$activeVersion = is_array($activeVersion ?? null) ? $activeVersion : null;
$hasAcceptedActiveVersion = !empty($hasAcceptedActiveVersion);
$latestAcceptance = is_array($latestAcceptance ?? null) ? $latestAcceptance : null;
$lmsTracks = is_array($lmsTracks ?? null) ? $lmsTracks : [];
$flashSuccess = \App\Core\Session::getFlash('success');
$flashError = \App\Core\Session::getFlash('error');
?>

<div class="mx-auto max-w-5xl px-4 py-10 sm:px-6 lg:px-8">
    <header class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <p class="text-xs font-black uppercase tracking-[0.28em] text-emerald-700">SIRH • LMS • Conformité</p>
        <h1 class="mt-2 text-3xl font-black tracking-tight text-slate-900">Charte RH & parcours obligatoire</h1>
        <p class="mt-3 max-w-3xl text-sm leading-relaxed text-slate-600">
            Cette page centralise la <strong>charte d’utilisation RH</strong> et le <strong>parcours LMS</strong> minimum attendu pour les accès SIRH.
            La signature électronique enregistre la date, l’adresse IP et l’agent utilisateur afin de garantir la traçabilité.
        </p>
    </header>

    <?php if ($flashSuccess): ?>
        <div class="mt-6 rounded-xl border border-emerald-300 bg-emerald-50 px-4 py-3 text-sm text-emerald-900"><?= htmlspecialchars((string) $flashSuccess) ?></div>
    <?php endif; ?>
    <?php if ($flashError): ?>
        <div class="mt-6 rounded-xl border border-rose-300 bg-rose-50 px-4 py-3 text-sm text-rose-900"><?= htmlspecialchars((string) $flashError) ?></div>
    <?php endif; ?>

    <section class="mt-6 grid gap-6 lg:grid-cols-[2fr,1fr]">
        <article class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-6 py-4">
                <h2 class="text-lg font-bold text-slate-900">Charte active</h2>
                <?php if ($activeVersion): ?>
                    <p class="text-sm text-slate-600">Version <?= htmlspecialchars((string) ($activeVersion['code'] ?? '—')) ?> — entrée en vigueur le <?= htmlspecialchars((string) ($activeVersion['effective_at'] ?? '—')) ?>.</p>
                <?php else: ?>
                    <p class="text-sm text-amber-700">Aucune charte active n’est publiée actuellement.</p>
                <?php endif; ?>
            </div>

            <div id="charter-scrollbox" class="max-h-[420px] overflow-auto px-6 py-5 text-sm leading-relaxed text-slate-700">
                <?php if ($activeVersion): ?>
                    <?php $md = (string) ($activeVersion['content_markdown'] ?? ''); ?>
                    <?= nl2br(htmlspecialchars($md, ENT_QUOTES, 'UTF-8')) ?>
                <?php else: ?>
                    <p>Publiez une charte active pour démarrer le dispositif.</p>
                <?php endif; ?>
            </div>

            <div class="border-t border-slate-100 px-6 py-5">
                <form method="post" action="<?= url('rh/charte/accepter') ?>" class="space-y-4" id="charter-accept-form">
                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
                    <label class="flex items-start gap-3 rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700">
                        <input type="checkbox" id="confirm_acceptance" name="confirm_acceptance" value="1" class="mt-0.5 h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                        <span>Je confirme avoir pris connaissance de la charte, m’engage à la respecter et accepte la traçabilité des accès effectués.</span>
                    </label>
                    <button
                        type="submit"
                        id="accept-button"
                        class="inline-flex items-center justify-center rounded-xl bg-slate-900 px-5 py-3 text-sm font-bold text-white transition hover:bg-slate-700 disabled:cursor-not-allowed disabled:opacity-40"
                        disabled
                    >
                        Signer la charte
                    </button>
                </form>
            </div>
        </article>

        <aside class="space-y-4">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="text-sm font-black uppercase tracking-[0.2em] text-slate-500">Statut utilisateur</h3>
                <?php if ($hasAcceptedActiveVersion): ?>
                    <p class="mt-3 inline-flex rounded-full bg-emerald-100 px-3 py-1 text-xs font-bold text-emerald-800">Conforme (version active signée)</p>
                <?php else: ?>
                    <p class="mt-3 inline-flex rounded-full bg-amber-100 px-3 py-1 text-xs font-bold text-amber-800">Action requise</p>
                <?php endif; ?>

                <?php if ($latestAcceptance): ?>
                    <dl class="mt-4 space-y-2 text-xs text-slate-600">
                        <div><dt class="font-semibold text-slate-900">Dernière signature</dt><dd><?= htmlspecialchars((string) ($latestAcceptance['accepted_at'] ?? '—')) ?></dd></div>
                        <div><dt class="font-semibold text-slate-900">Adresse IP</dt><dd><?= htmlspecialchars((string) ($latestAcceptance['ip_address'] ?? '—')) ?></dd></div>
                    </dl>
                <?php endif; ?>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="text-sm font-black uppercase tracking-[0.2em] text-slate-500">Parcours LMS obligatoire</h3>
                <ul class="mt-3 space-y-2 text-sm">
                    <?php foreach ($lmsTracks as $track): ?>
                        <li class="rounded-lg border border-slate-100 bg-slate-50 px-3 py-2">
                            <p class="font-semibold text-slate-900"><?= htmlspecialchars((string) ($track['title'] ?? 'Module')) ?></p>
                            <p class="text-xs text-slate-600"><?= htmlspecialchars((string) ($track['duration'] ?? '—')) ?> • requis</p>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </aside>
    </section>
</div>

<script defer src="<?= url('assets/js/rh-charter.js') ?>"></script>
