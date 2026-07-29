<?php
declare(strict_types=1);

$terminals = is_array($atakRealismTerminals ?? null) ? $atakRealismTerminals : [];
$certificates = is_array($atakRealismCertificates ?? null) ? $atakRealismCertificates : [];
$domains = is_array($atakCryptoDomains ?? null) ? $atakCryptoDomains : [];
$csrfToken = (string) ($csrfToken ?? \App\Core\Csrf::token());
$certificateStatusFr = static function (?string $status): string {
    return match ((string) $status) {
        'active' => 'Actif',
        'issued' => 'Émis',
        'expired' => 'Expiré',
        'revoked' => 'Révoqué',
        default => 'Émis',
    };
};
$certificateTypeFr = static function (?string $type): string {
    return match ((string) $type) {
        'server' => 'Serveur',
        'client' => 'Client',
        'device' => 'Appareil',
        'operator' => 'Opérateur',
        'gateway' => 'Passerelle',
        'test' => 'Test',
        default => ((string) $type !== '' ? (string) $type : 'Appareil'),
    };
};
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<div class="max-w-6xl mx-auto px-4 sm:px-6 py-8 space-y-8">
    <header class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <p class="text-xs font-bold uppercase tracking-widest text-slate-500">ATAK · Sécurité</p>
        <h1 class="mt-2 text-2xl font-black text-slate-900">Certificats & data packages</h1>
        <p class="mt-2 text-sm text-slate-600">Cycle de vie des certificats client, réseaux de chiffrement et échéances à surveiller.</p>
        <div class="mt-4 flex flex-wrap gap-2">
            <a href="<?= $h(url('back-office/atak/realisme')) ?>" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-50">Parc de terminaux</a>
            <a href="<?= $h(url('admin/atak/roleplay#intel-scramble')) ?>" class="inline-flex items-center rounded-lg border border-amber-200 bg-amber-50 px-4 py-2 text-sm font-semibold text-amber-950 hover:bg-amber-100">Données chiffrées</a>
        </div>
    </header>

    <section class="grid gap-6 lg:grid-cols-2">
        <form method="post" action="<?= $h(url('back-office/atak/reseaux-chiffrement')) ?>" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm space-y-4">
            <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
            <h2 class="text-sm font-black uppercase tracking-widest text-slate-900">Réseau de chiffrement</h2>
            <p class="text-xs text-slate-600">Les appareils d’un même réseau peuvent lire le trafic. Un appareil hors réseau voit des données illisibles.</p>
            <input name="label" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Nom du réseau (ex. Réseau ami)" autocomplete="off">
            <input name="domain_ref" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Référence courte (facultatif)" autocomplete="off">
            <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-bold text-white">Enregistrer le réseau</button>
            <?php if ($domains !== []): ?>
                <ul class="mt-2 space-y-1 text-sm text-slate-700">
                    <?php foreach ($domains as $domain): ?>
                        <li class="rounded-lg border border-slate-100 bg-slate-50 px-3 py-2">
                            <?= $h($domain['label'] ?? 'Réseau') ?>
                            <span class="text-xs text-slate-500">(<?= ($domain['status'] ?? '') === 'active' ? 'Actif' : 'Inactif' ?>)</span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </form>

        <form method="post" action="<?= $h(url('back-office/atak/certificats')) ?>" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm space-y-4">
            <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
            <h2 class="text-sm font-black uppercase tracking-widest text-slate-900">Émettre un certificat</h2>
            <input name="certificate_ref" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Référence certificat" autocomplete="off">
            <input name="authority_label" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Autorité émettrice" autocomplete="off">
            <select name="terminal_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">Terminal lié (facultatif)</option>
                <?php foreach ($terminals as $terminal): ?>
                    <option value="<?= (int) ($terminal['id'] ?? 0) ?>"><?= $h($terminal['terminal_label'] ?? $terminal['terminal_uid'] ?? 'Terminal') ?></option>
                <?php endforeach; ?>
            </select>
            <select name="crypto_domain_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">Réseau de chiffrement</option>
                <?php foreach ($domains as $domain): ?>
                    <option value="<?= (int) ($domain['id'] ?? 0) ?>"><?= $h($domain['label'] ?? 'Réseau') ?></option>
                <?php endforeach; ?>
            </select>
            <input name="user_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Compte membre à lier (facultatif)" inputmode="numeric" autocomplete="off">
            <input name="expires_at" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Échéance (AAAA-MM-JJ HH:MM:SS)" autocomplete="off">
            <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-bold text-white">Émettre</button>
        </form>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <div class="border-b border-slate-100 px-6 py-4">
            <h2 class="text-sm font-black text-slate-900">Certificats enregistrés</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[1100px] text-sm">
                <thead class="bg-slate-50 text-slate-600">
                    <tr>
                        <th class="px-4 py-3 text-left">Référence</th>
                        <th class="px-4 py-3 text-left">Autorité</th>
                        <th class="px-4 py-3 text-left">Type</th>
                        <th class="px-4 py-3 text-left">Réseau</th>
                        <th class="px-4 py-3 text-left">Terminal</th>
                        <th class="px-4 py-3 text-left">Statut</th>
                        <th class="px-4 py-3 text-left">Échéance</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($certificates === []): ?>
                    <tr>
                        <td colspan="8" class="px-4 py-8 text-center text-slate-500">Aucun certificat enregistré pour le moment.</td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($certificates as $certificate): ?>
                    <?php
                    $certId = (int) ($certificate['id'] ?? 0);
                    $certStatus = (string) ($certificate['status'] ?? '');
                    $certRef = (string) ($certificate['certificate_ref'] ?? '');
                    $canRevoke = $certId > 0 && !in_array($certStatus, ['revoked'], true);
                    $label = $certRef !== '' ? $certRef : ('#' . $certId);
                    $confirmRevoke = 'Révoquer le certificat « ' . $label . ' » ? Le terminal ne pourra plus s’en servir tant qu’un nouveau certificat n’est pas émis.';
                    $confirmDelete = 'Supprimer définitivement le certificat « ' . $label . ' » ? Cette action est irréversible.';
                    ?>
                    <tr class="border-t border-slate-100">
                        <td class="px-4 py-3"><?= $h($certRef !== '' ? $certRef : '—') ?></td>
                        <td class="px-4 py-3"><?= $h($certificate['authority_label'] ?? '—') ?></td>
                        <td class="px-4 py-3"><?= $h($certificateTypeFr($certificate['certificate_type'] ?? null)) ?></td>
                        <td class="px-4 py-3"><?= $h($certificate['crypto_domain_label'] ?? '—') ?></td>
                        <td class="px-4 py-3"><?= $h($certificate['terminal_label'] ?? '—') ?></td>
                        <td class="px-4 py-3"><?= $h($certificateStatusFr($certStatus !== '' ? $certStatus : null)) ?></td>
                        <td class="px-4 py-3"><?= $h($certificate['expires_at'] ?? '—') ?></td>
                        <td class="px-4 py-3">
                            <?php if ($certId > 0): ?>
                                <div class="flex flex-wrap items-center justify-end gap-2">
                                    <?php if ($canRevoke): ?>
                                        <form method="post" action="<?= $h(url('back-office/atak/certificats/' . $certId . '/revoquer')) ?>" class="inline" onsubmit="return confirm(<?= $h(json_encode($confirmRevoke, JSON_UNESCAPED_UNICODE)) ?>);">
                                            <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                                            <button type="submit" class="inline-flex rounded-md border border-amber-200 bg-white px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide text-amber-900 hover:bg-amber-50">Révoquer</button>
                                        </form>
                                    <?php endif; ?>
                                    <form method="post" action="<?= $h(url('back-office/atak/certificats/' . $certId . '/supprimer')) ?>" class="inline" onsubmit="return confirm(<?= $h(json_encode($confirmDelete, JSON_UNESCAPED_UNICODE)) ?>);">
                                        <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                                        <button type="submit" class="inline-flex rounded-md border border-rose-200 bg-white px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide text-rose-800 hover:bg-rose-50">Supprimer</button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
