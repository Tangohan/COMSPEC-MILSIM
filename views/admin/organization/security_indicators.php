<?php
declare(strict_types=1);
/** @var list<array<string, mixed>> $indicatorRows */

$typeLabel = static function (string $t): string {
    return match ($t) {
        'email' => 'Adresse e-mail (empreinte)',
        'ip' => 'Adresse réseau (empreinte)',
        default => $t,
    };
};
?>
<div class="max-w-5xl mx-auto px-6 py-12">
    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
        <h1 class="text-2xl font-black text-slate-900">Blocages portail & sécurité</h1>
        <a href="<?= url('back-office') ?>" class="text-sm text-slate-600 hover:underline">Retour back-office</a>
    </div>
    <div class="rounded-lg border border-sky-200 bg-sky-50/90 px-4 py-3 text-sm text-sky-950 mb-6">
        <p class="font-semibold">Liste locale à votre communauté</p>
        <p class="mt-1 text-sky-950/90">Les entrées actives bloquent l’accès au portail public (candidatures, suivi invité) pour l’indicateur concerné. Elles sont souvent créées par la <strong>modération automatique</strong> du portail recrutement. Les valeurs réelles ne sont pas affichées (empreinte seulement).</p>
    </div>
    <?php $f = \App\Core\Session::getFlash('error'); $s = \App\Core\Session::getFlash('success'); ?>
    <?php if ($f): ?><p class="text-red-600 text-sm mb-4"><?= htmlspecialchars($f, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
    <?php if ($s): ?><p class="text-emerald-700 text-sm mb-4"><?= htmlspecialchars($s, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>

    <h2 class="text-lg font-bold text-slate-800 mb-3">Blocages encore actifs</h2>
    <?php if ($indicatorRows === []): ?>
        <p class="text-sm text-slate-600 border border-slate-200 rounded-lg p-4 bg-white">Aucun blocage actif pour cette communauté.</p>
    <?php else: ?>
        <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left">
                    <tr>
                        <th class="p-3 font-semibold text-slate-700">Type</th>
                        <th class="p-3 font-semibold text-slate-700">Empreinte</th>
                        <th class="p-3 font-semibold text-slate-700">Motif</th>
                        <th class="p-3 font-semibold text-slate-700">Depuis</th>
                        <th class="p-3 font-semibold text-slate-700">Fin prévue</th>
                        <th class="p-3 font-semibold text-slate-700"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($indicatorRows as $r): ?>
                        <?php
                        $id = (int) ($r['id'] ?? 0);
                        $vh = (string) ($r['value_hash'] ?? '');
                        $fprint = $vh !== '' ? '…' . htmlspecialchars(substr($vh, -10), ENT_QUOTES, 'UTF-8') : '—';
                        $reason = trim((string) ($r['reason'] ?? ''));
                        $created = trim((string) ($r['created_at'] ?? ''));
                        $exp = trim((string) ($r['expires_at'] ?? ''));
                        $createdFmt = $created !== '' ? date('d/m/Y H:i', strtotime($created) ?: time()) : '—';
                        $expFmt = $exp !== '' ? date('d/m/Y H:i', strtotime($exp) ?: time()) : 'Sans limite affichée';
                        ?>
                        <tr class="border-t border-slate-100">
                            <td class="p-3 align-top"><?= htmlspecialchars($typeLabel((string) ($r['indicator_type'] ?? '')), ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="p-3 align-top font-mono text-xs text-slate-600"><?= $fprint ?></td>
                            <td class="p-3 align-top text-slate-700"><?= $reason !== '' ? htmlspecialchars($reason, ENT_QUOTES, 'UTF-8') : '—' ?></td>
                            <td class="p-3 align-top whitespace-nowrap text-slate-600"><?= htmlspecialchars($createdFmt, ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="p-3 align-top whitespace-nowrap text-slate-600"><?= htmlspecialchars($expFmt, ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="p-3 align-top text-right">
                                <form method="post" action="<?= url('back-office/security-indicators/revoke') ?>" class="inline" onsubmit="return confirm('Lever ce blocage pour toute la communauté ?');">
                                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="indicator_id" value="<?= $id ?>">
                                    <button type="submit" class="rounded-md border border-emerald-300 bg-emerald-50 px-3 py-1.5 text-xs font-bold text-emerald-900 hover:bg-emerald-100">Lever le blocage</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
