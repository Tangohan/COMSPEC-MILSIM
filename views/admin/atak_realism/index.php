<?php
declare(strict_types=1);

$terminals = is_array($atakRealismTerminals ?? null) ? $atakRealismTerminals : [];
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
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<div class="max-w-6xl mx-auto px-4 sm:px-6 py-8 space-y-8">
    <header class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <p class="text-xs font-bold uppercase tracking-widest text-slate-500">ATAK · Parc</p>
        <h1 class="mt-2 text-2xl font-black text-slate-900">Parc de terminaux</h1>
        <p class="mt-2 text-sm text-slate-600">Inventaire des terminaux appairés : matériel, rattachement opérateur et dernier passage remarqués.</p>
        <div class="mt-4 flex flex-wrap gap-2">
            <a href="<?= $h(url('back-office/atak/certificats')) ?>" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-50">Voir les certificats</a>
            <a href="<?= $h(url('back-office/atak/operateurs')) ?>" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-50">Sessions & connexions</a>
        </div>
    </header>

    <section>
        <form method="post" action="<?= $h(url('back-office/atak/realisme/terminaux')) ?>" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm space-y-4 max-w-xl">
            <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
            <h2 class="text-sm font-black uppercase tracking-widest text-slate-900">Déclarer un terminal</h2>
            <input name="terminal_uid" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Identifiant du terminal" autocomplete="off">
            <input name="terminal_label" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Nom lisible du terminal" autocomplete="off">
            <input name="operator_callsign" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Indicatif opérateur" autocomplete="off">
            <input name="user_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Compte membre à lier (facultatif)" inputmode="numeric" autocomplete="off">
            <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-bold text-white">Enregistrer</button>
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
                        <th class="px-4 py-3 text-left">Fiche</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($terminals === []): ?>
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-slate-500">Aucun terminal enregistré pour le moment.</td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($terminals as $terminal):
                    $cs = trim((string) ($terminal['operator_callsign'] ?? ''));
                    $rawUid = trim((string) ($terminal['terminal_uid'] ?? ''));
                    $uidLower = strtolower($rawUid);
                    $uidBad = $rawUid === '' || in_array($uidLower, ['null', '<null>', '<nul>', 'nil'], true) || str_starts_with($uidLower, '<null');
                    $uidShow = $uidBad ? '—' : $rawUid;
                    $labelShow = trim((string) ($terminal['terminal_label'] ?? ''));
                    if ($labelShow === '') {
                        $labelShow = $uidBad ? 'Terminal ATAK' : $rawUid;
                    }
                    ?>
                    <tr class="border-t border-slate-100">
                        <td class="px-4 py-3"><?= $h($labelShow) ?><div class="text-xs text-slate-500 font-mono"><?= $h($uidShow) ?></div></td>
                        <td class="px-4 py-3"><?= $h($cs !== '' ? $cs : '—') ?><div class="text-xs text-slate-500"><?= $h($terminal['operator_military_id'] ?? '—') ?></div></td>
                        <td class="px-4 py-3"><?= $h($terminal['display_name'] ?? '—') ?></td>
                        <td class="px-4 py-3"><?= $h($terminalStatusFr($terminal['status'] ?? null)) ?></td>
                        <td class="px-4 py-3"><?= $h($terminal['last_seen_at'] ?? '—') ?></td>
                        <td class="px-4 py-3">
                            <?php if ($cs !== ''): ?>
                                <a class="font-semibold text-slate-900 underline decoration-slate-300 hover:decoration-slate-700" href="<?= $h(url('back-office/atak/fiche-operateur?indicatif=' . rawurlencode($cs))) ?>">Ouvrir</a>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
