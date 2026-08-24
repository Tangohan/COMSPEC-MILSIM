<?php
declare(strict_types=1);

$terminals = is_array($atakRealismTerminals ?? null) ? $atakRealismTerminals : [];
$webSessions = is_array($atakRealismWebSessions ?? null) ? $atakRealismWebSessions : [];
$canManage = !empty($canManageAtakTerminals);
$csrfToken = (string) ($csrfToken ?? \App\Core\Csrf::token());
$terminalStatusFr = static function (?string $status): string {
    return match ((string) $status) {
        'active' => 'Actif',
        'pending' => 'En attente',
        'expired' => 'Expiré',
        'revoked' => 'Révoqué',
        'offline' => 'Hors liaison',
        'inactive' => 'Inactif',
        'lost' => 'Perdu',
        default => 'En attente',
    };
};
$terminalKindFr = static function (?string $type): string {
    return match ((string) $type) {
        'phone' => 'Téléphone',
        'tablet' => 'Tablette',
        'radio' => 'Radio',
        'vehicle' => 'Véhicule',
        'desktop' => 'Ordinateur',
        'web' => 'Session web',
        default => ((string) $type !== '' ? (string) $type : 'Terminal'),
    };
};
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$renderTable = static function (array $rows, string $emptyLabel, bool $web) use ($h, $csrfToken, $terminalStatusFr, $terminalKindFr, $canManage): void {
    $formId = $web ? 'atak-parc-web' : 'atak-parc-phys';
    ?>
    <form method="post" action="<?= $h(url('back-office/atak/realisme/terminaux/supprimer-selection')) ?>" id="<?= $h($formId) ?>">
        <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
        <?php if ($canManage && $rows !== []): ?>
            <div class="flex flex-wrap items-center gap-2 border-b border-slate-100 px-4 py-3 sm:px-6">
                <button type="submit" class="inline-flex items-center rounded-lg border border-rose-200 bg-white px-3 py-1.5 text-xs font-bold uppercase tracking-wide text-rose-800 hover:bg-rose-50" onclick="return confirm('Retirer du parc les appareils cochés ?');">Retirer la sélection</button>
                <span class="text-xs text-slate-500">Cochez une ou plusieurs lignes, puis confirmez.</span>
            </div>
        <?php endif; ?>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[920px] text-sm">
                <thead class="bg-slate-50 text-slate-600">
                    <tr>
                        <?php if ($canManage): ?>
                            <th class="px-4 py-3 text-left w-10">
                                <input type="checkbox" class="h-4 w-4 rounded border-slate-300 text-slate-900" title="Tout sélectionner" onclick="var on=this.checked; document.querySelectorAll('#<?= $h($formId) ?> input[name=\'ids[]\']').forEach(function(c){c.checked=on;});">
                                <span class="sr-only">Tout sélectionner</span>
                            </th>
                        <?php endif; ?>
                        <th class="px-4 py-3 text-left"><?= $web ? 'Session' : 'Terminal' ?></th>
                        <th class="px-4 py-3 text-left">Nature</th>
                        <th class="px-4 py-3 text-left">Indicatif</th>
                        <th class="px-4 py-3 text-left">Compte lié</th>
                        <th class="px-4 py-3 text-left">Statut</th>
                        <th class="px-4 py-3 text-left">Dernier passage</th>
                        <th class="px-4 py-3 text-left">Fiche</th>
                        <th class="sticky right-0 bg-slate-50 px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($rows === []): ?>
                    <tr>
                        <td colspan="<?= $canManage ? 9 : 8 ?>" class="px-4 py-8 text-center text-slate-500"><?= $h($emptyLabel) ?></td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($rows as $terminal):
                    $cs = trim((string) ($terminal['operator_callsign'] ?? ''));
                    $rawUid = trim((string) ($terminal['terminal_uid'] ?? ''));
                    $uidLower = strtolower($rawUid);
                    $uidBad = $rawUid === '' || in_array($uidLower, ['null', '<null>', '<nul>', 'nil'], true) || str_starts_with($uidLower, '<null');
                    $uidShow = $uidBad ? '—' : $rawUid;
                    $labelShow = trim((string) ($terminal['terminal_label'] ?? ''));
                    if ($labelShow === '') {
                        $labelShow = $uidBad ? ($web ? 'Session web' : 'Terminal ATAK') : $rawUid;
                    }
                    $tid = (int) ($terminal['id'] ?? 0);
                    $confirm = $web
                        ? 'Retirer la session web « ' . $labelShow . ' » du parc ?'
                        : 'Retirer le terminal « ' . $labelShow . ' » du parc ? Les certificats liés resteront, sans appareil rattaché.';
                    ?>
                    <tr class="border-t border-slate-100">
                        <?php if ($canManage): ?>
                            <td class="px-4 py-3">
                                <?php if ($tid > 0): ?>
                                    <input type="checkbox" name="ids[]" value="<?= $tid ?>" class="h-4 w-4 rounded border-slate-300 text-slate-900" title="Sélectionner <?= $h($labelShow) ?>">
                                <?php endif; ?>
                            </td>
                        <?php endif; ?>
                        <td class="px-4 py-3"><?= $h($labelShow) ?><div class="text-xs text-slate-500 font-mono"><?= $h($uidShow) ?></div></td>
                        <td class="px-4 py-3"><?= $h($terminalKindFr($terminal['terminal_type'] ?? null)) ?></td>
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
                        <td class="sticky right-0 bg-white px-4 py-3 text-right">
                            <?php if ($canManage && $tid > 0): ?>
                                <button type="submit" formaction="<?= $h(url('back-office/atak/realisme/terminaux/' . $tid . '/supprimer')) ?>" class="inline-flex rounded-md border border-rose-200 bg-white px-3 py-1.5 text-xs font-bold uppercase tracking-wide text-rose-800 hover:bg-rose-50" onclick="return confirm(<?= $h(json_encode($confirm, JSON_UNESCAPED_UNICODE)) ?>);">Retirer</button>
                            <?php elseif (!$canManage): ?>
                                <span class="text-xs text-slate-400">—</span>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </form>
    <?php
};
?>
<div class="max-w-6xl mx-auto px-4 sm:px-6 py-8 space-y-8">
    <header class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <p class="text-xs font-bold uppercase tracking-widest text-slate-500">ATAK · Parc</p>
        <h1 class="mt-2 text-2xl font-black text-slate-900">Parc de terminaux</h1>
        <p class="mt-2 text-sm text-slate-600">Inventaire des appareils terrain (jeu, tablette, téléphone appairé). Les ouvertures de la carte dans le navigateur sont listées à part : ce ne sont pas des terminaux. Vous pouvez retirer un appareil du parc à tout moment : il disparaît de cette liste, sans toucher au compte de l’opérateur.</p>
        <div class="mt-4 flex flex-wrap gap-2">
            <a href="<?= $h(url('back-office/atak/certificats')) ?>" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-50">Voir les certificats</a>
            <a href="<?= $h(url('back-office/atak/operateurs')) ?>" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-50">Sessions & connexions</a>
        </div>
    </header>

    <?php if (!$canManage): ?>
        <p class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950">Vous consultez le parc. Le retrait d’appareils est réservé aux responsables ATAK de la communauté.</p>
    <?php endif; ?>

    <?php if ($canManage): ?>
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
    <?php endif; ?>

    <section class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <div class="border-b border-slate-100 px-6 py-4">
            <h2 class="text-sm font-black text-slate-900">Terminaux enregistrés</h2>
            <p class="mt-1 text-xs text-slate-500">Appareils du jeu ou déclarés ici. <?= count($terminals) ?> entrée<?= count($terminals) > 1 ? 's' : '' ?>.</p>
        </div>
        <?php $renderTable($terminals, 'Aucun terminal enregistré pour le moment.', false); ?>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <div class="border-b border-slate-100 px-6 py-4 flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-sm font-black text-slate-900">Sessions web</h2>
                <p class="mt-1 text-xs text-slate-500">Ouvertures de la carte Athena dans un navigateur. Elles ne reçoivent ni certificat ni alertes d’appareil. <?= count($webSessions) ?> session<?= count($webSessions) > 1 ? 's' : '' ?>.</p>
            </div>
            <?php if ($canManage && $webSessions !== []): ?>
                <form method="post" action="<?= $h(url('back-office/atak/realisme/terminaux/sessions-web/supprimer')) ?>" onsubmit="return confirm('Retirer toutes les sessions web du parc ? Les appareils terrain ne sont pas concernés.');">
                    <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                    <button type="submit" class="inline-flex rounded-lg border border-rose-200 bg-white px-3 py-1.5 text-xs font-bold uppercase tracking-wide text-rose-800 hover:bg-rose-50">Retirer toutes les sessions web</button>
                </form>
            <?php endif; ?>
        </div>
        <?php $renderTable($webSessions, 'Aucune session web recensée dans le parc.', true); ?>
    </section>
</div>
