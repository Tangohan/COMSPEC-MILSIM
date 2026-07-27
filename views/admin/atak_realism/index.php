<?php
declare(strict_types=1);

$terminals = is_array($atakRealismTerminals ?? null) ? $atakRealismTerminals : [];
$certificates = is_array($atakRealismCertificates ?? null) ? $atakRealismCertificates : [];
$csrfToken = (string) ($csrfToken ?? \App\Core\Csrf::token());
$terminalStatusFr = static function (?string $status): string {
    return match ((string) $status) {
        'active' => 'Actif',
        'pending' => 'En attente',
        'expired' => 'Expiré',
        'revoked' => 'Révoqué',
        'offline' => 'Hors liaison',
        default => 'En attente',
    };
};
$certificateStatusFr = static function (?string $status): string {
    return match ((string) $status) {
        'active' => 'Actif',
        'issued' => 'Émis',
        'expired' => 'Expiré',
        'revoked' => 'Révoqué',
        default => 'Émis',
    };
};
?>
<div class="max-w-6xl mx-auto px-4 sm:px-6 py-8 space-y-8">
    <header class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <p class="text-xs font-bold uppercase tracking-widest text-slate-500">ATAK</p>
        <h1 class="mt-2 text-2xl font-black text-slate-900">Certificats et terminaux</h1>
        <p class="mt-2 text-sm text-slate-600">Registre métier des terminaux ATAK, des rattachements opérateur et du cycle de vie des certificats.</p>
    </header>

    <section class="grid gap-6 lg:grid-cols-2">
        <form method="post" action="<?= htmlspecialchars(url('back-office/atak/realisme/terminaux'), ENT_QUOTES, 'UTF-8') ?>" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm space-y-4">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <h2 class="text-sm font-black uppercase tracking-widest text-slate-900">Déclarer un terminal</h2>
            <input name="terminal_uid" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Identifiant du terminal">
            <input name="terminal_label" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Nom lisible du terminal">
            <input name="operator_callsign" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Indicatif opérateur">
            <input name="user_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Compte membre à lier (facultatif)">
            <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-bold text-white">Enregistrer</button>
        </form>

        <form method="post" action="<?= htmlspecialchars(url('back-office/atak/realisme/certificats'), ENT_QUOTES, 'UTF-8') ?>" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm space-y-4">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <h2 class="text-sm font-black uppercase tracking-widest text-slate-900">Émettre un certificat</h2>
            <input name="certificate_ref" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Référence certificat">
            <input name="authority_label" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Autorité émettrice">
            <select name="terminal_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">Terminal lié (facultatif)</option>
                <?php foreach ($terminals as $terminal): ?>
                    <option value="<?= (int) ($terminal['id'] ?? 0) ?>"><?= htmlspecialchars((string) ($terminal['terminal_label'] ?? $terminal['terminal_uid'] ?? 'Terminal'), ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
            <input name="user_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Compte membre à lier (facultatif)">
            <input name="expires_at" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Échéance (YYYY-MM-DD HH:MM:SS)">
            <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-bold text-white">Émettre</button>
        </form>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <div class="border-b border-slate-100 px-6 py-4">
            <h2 class="text-sm font-black text-slate-900">Terminaux enregistrés</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[760px] text-sm">
                <thead class="bg-slate-50 text-slate-600">
                    <tr>
                        <th class="px-4 py-3 text-left">Terminal</th>
                        <th class="px-4 py-3 text-left">Indicatif</th>
                        <th class="px-4 py-3 text-left">Compte lié</th>
                        <th class="px-4 py-3 text-left">Statut</th>
                        <th class="px-4 py-3 text-left">Dernier passage</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($terminals as $terminal): ?>
                    <tr class="border-t border-slate-100">
                        <td class="px-4 py-3"><?= htmlspecialchars((string) ($terminal['terminal_label'] ?? $terminal['terminal_uid'] ?? '—'), ENT_QUOTES, 'UTF-8') ?><div class="text-xs text-slate-500"><?= htmlspecialchars((string) ($terminal['terminal_uid'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div></td>
                        <td class="px-4 py-3"><?= htmlspecialchars((string) ($terminal['operator_callsign'] ?? '—'), ENT_QUOTES, 'UTF-8') ?><div class="text-xs text-slate-500"><?= htmlspecialchars((string) ($terminal['operator_military_id'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></div></td>
                        <td class="px-4 py-3"><?= htmlspecialchars((string) ($terminal['display_name'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="px-4 py-3"><?= htmlspecialchars($terminalStatusFr($terminal['status'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="px-4 py-3"><?= htmlspecialchars((string) ($terminal['last_seen_at'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <div class="border-b border-slate-100 px-6 py-4">
            <h2 class="text-sm font-black text-slate-900">Certificats enregistrés</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[860px] text-sm">
                <thead class="bg-slate-50 text-slate-600">
                    <tr>
                        <th class="px-4 py-3 text-left">Référence</th>
                        <th class="px-4 py-3 text-left">Autorité</th>
                        <th class="px-4 py-3 text-left">Type</th>
                        <th class="px-4 py-3 text-left">Terminal</th>
                        <th class="px-4 py-3 text-left">Statut</th>
                        <th class="px-4 py-3 text-left">Échéance</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($certificates as $certificate): ?>
                    <tr class="border-t border-slate-100">
                        <td class="px-4 py-3"><?= htmlspecialchars((string) ($certificate['certificate_ref'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="px-4 py-3"><?= htmlspecialchars((string) ($certificate['authority_label'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="px-4 py-3"><?= htmlspecialchars((string) ($certificate['certificate_type'] ?? 'device'), ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="px-4 py-3"><?= htmlspecialchars((string) ($certificate['terminal_label'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="px-4 py-3"><?= htmlspecialchars($certificateStatusFr($certificate['status'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="px-4 py-3"><?= htmlspecialchars((string) ($certificate['expires_at'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
