<?php
declare(strict_types=1);

/** @var list<array{call_sign:string,label:string}> $atakOperatorChoices */
/** @var string $atakOperatorSelected */
/** @var array<string,mixed>|null $atakOperatorLive */
/** @var array<string,mixed>|null $atakOperatorTerminal */
/** @var array<string,mixed>|null $atakOperatorCertificate */
/** @var string $atakOperatorMilitaryId */

$choices = is_array($atakOperatorChoices ?? null) ? $atakOperatorChoices : [];
$selected = trim((string) ($atakOperatorSelected ?? ''));
$live = is_array($atakOperatorLive ?? null) ? $atakOperatorLive : null;
$terminal = is_array($atakOperatorTerminal ?? null) ? $atakOperatorTerminal : null;
$certificate = is_array($atakOperatorCertificate ?? null) ? $atakOperatorCertificate : null;
$militaryId = trim((string) ($atakOperatorMilitaryId ?? ''));
if ($militaryId === '' && is_array($live)) {
    $militaryId = trim((string) ($live['military_id'] ?? ''));
}
if ($militaryId === '' && is_array($terminal)) {
    $militaryId = trim((string) ($terminal['operator_military_id'] ?? ''));
}

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$clean = static function (mixed $v): string {
    $s = trim((string) $v);
    if ($s === '') {
        return '';
    }
    $lower = strtolower($s);
    if (in_array($lower, ['null', '<null>', '<nul>', 'nil', 'undefined'], true)) {
        return '';
    }
    if (str_contains($lower, '<null') || str_contains($lower, '<nul>')) {
        return '';
    }

    return $s;
};
$show = static function (mixed $v) use ($clean, $h): string {
    $s = $clean($v);

    return $s !== '' ? $h($s) : '—';
};

$statusMeta = static function (?string $status): array {
    return match ((string) $status) {
        'linked' => ['label' => 'En liaison', 'class' => 'bg-emerald-100 text-emerald-900 border-emerald-200'],
        'delayed' => ['label' => 'Signal faible', 'class' => 'bg-amber-100 text-amber-950 border-amber-200'],
        'active' => ['label' => 'Actif', 'class' => 'bg-emerald-100 text-emerald-900 border-emerald-200'],
        'pending' => ['label' => 'En attente', 'class' => 'bg-amber-100 text-amber-950 border-amber-200'],
        'issued' => ['label' => 'Émis', 'class' => 'bg-sky-100 text-sky-900 border-sky-200'],
        'expired' => ['label' => 'Expiré', 'class' => 'bg-rose-100 text-rose-900 border-rose-200'],
        'revoked' => ['label' => 'Révoqué', 'class' => 'bg-rose-100 text-rose-900 border-rose-200'],
        'offline' => ['label' => 'Hors ligne', 'class' => 'bg-slate-100 text-slate-700 border-slate-200'],
        default => ['label' => 'Hors ligne', 'class' => 'bg-slate-100 text-slate-700 border-slate-200'],
    };
};

$profileUrl = url('back-office/atak/fiche-operateur');
$displayName = '';
if (is_array($live)) {
    $displayName = trim((string) ($live['linked_display_name'] ?? ''));
}
if ($displayName === '' && is_array($terminal)) {
    $displayName = trim((string) ($terminal['display_name'] ?? ''));
}
$liveStatus = $statusMeta(is_array($live) ? (string) ($live['status'] ?? 'offline') : 'offline');
$termStatus = $statusMeta(is_array($terminal) ? (string) ($terminal['status'] ?? '') : '');
$certStatus = $statusMeta(is_array($certificate) ? (string) ($certificate['status'] ?? '') : '');
?>
<div class="max-w-5xl mx-auto px-4 sm:px-6 py-8 space-y-6">
    <header class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-500">ATAK · Fiche opérateur</p>
        <h1 class="mt-2 text-2xl font-black text-slate-900">
            <?= $selected !== '' ? ('Fiche ATAK — ' . $h($selected)) : 'Fiche opérateur' ?>
        </h1>
        <p class="mt-2 text-sm text-slate-600 max-w-3xl">
            Vue consolidée des données ATAK rattachées à un opérateur : identité réseau, terminal, certificat et liaison en cours.
        </p>
        <form method="get" action="<?= $h($profileUrl) ?>" class="mt-5 flex flex-col gap-3 sm:flex-row sm:items-end">
            <div class="flex-1 min-w-[14rem]">
                <label for="atak-fiche-indicatif" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Opérateur</label>
                <select id="atak-fiche-indicatif" name="indicatif"
                        class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200">
                    <?php if ($choices === []): ?>
                        <option value="">Aucun opérateur disponible</option>
                    <?php endif; ?>
                    <?php foreach ($choices as $choice): ?>
                        <option value="<?= $h($choice['call_sign']) ?>" <?= strtoupper((string) $choice['call_sign']) === strtoupper($selected) ? 'selected' : '' ?>>
                            <?= $h($choice['label']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Afficher</button>
            <a href="<?= $h(url('back-office/atak/operateurs')) ?>" class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-50">Sessions</a>
        </form>
    </header>

    <?php if ($selected === '' || $choices === []): ?>
        <section class="rounded-2xl border border-slate-200 bg-white p-8 shadow-sm text-center text-sm text-slate-500">
            Aucun opérateur à afficher pour le moment. Déclarez un terminal ou attendez une première liaison.
        </section>
    <?php else: ?>
        <section class="grid gap-4 md:grid-cols-2">
            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm space-y-3">
                <h2 class="text-sm font-black uppercase tracking-widest text-slate-900">Identité</h2>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Indicatif</dt><dd class="font-semibold text-slate-900"><?= $h($selected) ?></dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">ID militaire</dt><dd class="font-mono text-slate-800"><?= $militaryId !== '' ? $h($militaryId) : '—' ?></dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Compte lié</dt><dd class="text-slate-800">
                        <?php
                        $linkedUrl = is_array($live) ? trim((string) ($live['linked_url'] ?? '')) : '';
                        if ($linkedUrl !== '' && $displayName !== ''): ?>
                            <a class="font-semibold underline decoration-slate-300 hover:decoration-slate-700" href="<?= $h($linkedUrl) ?>"><?= $h($displayName) ?></a>
                        <?php else: ?>
                            <?= $displayName !== '' ? $h($displayName) : 'Non lié' ?>
                        <?php endif; ?>
                    </dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Rôle</dt><dd class="text-slate-800"><?= $h(is_array($live) && trim((string) ($live['role_label'] ?? '')) !== '' ? $live['role_label'] : '—') ?></dd></div>
                </dl>
            </article>

            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm space-y-3">
                <h2 class="text-sm font-black uppercase tracking-widest text-slate-900">Liaison en cours</h2>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between gap-3 items-center">
                        <dt class="text-slate-500">Statut</dt>
                        <dd><span class="inline-flex items-center rounded-md border px-2 py-0.5 text-[11px] font-semibold <?= $h($liveStatus['class']) ?>"><?= $h($liveStatus['label']) ?></span></dd>
                    </div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Grille</dt><dd class="font-mono text-slate-800"><?= $h(is_array($live) && trim((string) ($live['grid_ref'] ?? '')) !== '' ? $live['grid_ref'] : '—') ?></dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Cap</dt><dd class="text-slate-800"><?= $h(is_array($live) && trim((string) ($live['heading_label'] ?? '')) !== '' ? $live['heading_label'] : '—') ?></dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Dernière MAJ</dt><dd class="text-slate-800"><?= $h(is_array($live) ? ($live['updated_at_label'] ?? '—') : '—') ?></dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Carte</dt><dd>
                        <?php if (is_array($live)): ?>
                            <a class="font-semibold underline decoration-slate-300 hover:decoration-slate-700" href="<?= $h($live['map_url'] ?? url('atak')) ?>">Ouvrir</a>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </dd></div>
                </dl>
            </article>

            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm space-y-3">
                <h2 class="text-sm font-black uppercase tracking-widest text-slate-900">Terminal</h2>
                <?php if (!is_array($terminal)): ?>
                    <p class="text-sm text-slate-500">Aucun terminal rattaché à cet indicatif.</p>
                    <a class="inline-flex text-sm font-semibold underline decoration-slate-300 hover:decoration-slate-700" href="<?= $h(url('back-office/atak/realisme')) ?>">Aller au parc de terminaux</a>
                <?php else: ?>
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between gap-3"><dt class="text-slate-500">Nom</dt><dd class="font-semibold text-slate-900"><?= $show($terminal['terminal_label'] ?? '') ?></dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-slate-500">Identifiant</dt><dd class="font-mono text-slate-800"><?= $show($terminal['terminal_uid'] ?? '') ?></dd></div>
                        <div class="flex justify-between gap-3 items-center"><dt class="text-slate-500">Statut</dt><dd><span class="inline-flex items-center rounded-md border px-2 py-0.5 text-[11px] font-semibold <?= $h($termStatus['class']) ?>"><?= $h($termStatus['label']) ?></span></dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-slate-500">Dernier passage</dt><dd class="text-slate-800"><?= $show($terminal['last_seen_at'] ?? '') ?></dd></div>
                    </dl>
                <?php endif; ?>
            </article>

            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm space-y-3">
                <h2 class="text-sm font-black uppercase tracking-widest text-slate-900">Certificat</h2>
                <?php if (!is_array($certificate)): ?>
                    <p class="text-sm text-slate-500">Aucun certificat associé pour le moment.</p>
                    <a class="inline-flex text-sm font-semibold underline decoration-slate-300 hover:decoration-slate-700" href="<?= $h(url('back-office/atak/certificats')) ?>">Gérer les certificats</a>
                <?php else: ?>
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between gap-3"><dt class="text-slate-500">Référence</dt><dd class="font-semibold text-slate-900"><?= $show($certificate['certificate_ref'] ?? '') ?></dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-slate-500">Autorité</dt><dd class="text-slate-800"><?= $show($certificate['authority_label'] ?? '') ?></dd></div>
                        <div class="flex justify-between gap-3 items-center"><dt class="text-slate-500">Statut</dt><dd><span class="inline-flex items-center rounded-md border px-2 py-0.5 text-[11px] font-semibold <?= $h($certStatus['class']) ?>"><?= $h($certStatus['label']) ?></span></dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-slate-500">Échéance</dt><dd class="text-slate-800"><?= $show($certificate['expires_at'] ?? '') ?></dd></div>
                    </dl>
                <?php endif; ?>
            </article>
        </section>
    <?php endif; ?>
</div>
