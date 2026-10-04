<?php
declare(strict_types=1);

/*
 * Parc de terminaux · Téléphones des opérateurs (game_phone_identities).
 * Variables : $atakGamePhones, $canManageAtakTerminals, $csrfToken.
 */
use App\Repositories\GamePhoneIdentityRepository;

$phones = is_array($atakGamePhones ?? null) ? $atakGamePhones : [];
$phoneCanManage = !empty($canManageAtakTerminals);
$phoneCsrf = (string) ($csrfToken ?? \App\Core\Csrf::token());
$e = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$seenLabel = static function (?string $at): array {
    $ts = $at !== null && $at !== '' ? strtotime($at) : false;
    if ($ts === false) {
        return ['Jamais remonté', '', false];
    }
    $ago = max(0, time() - $ts);
    $online = $ago <= 180;
    $rel = match (true) {
        $ago < 60 => 'à l’instant',
        $ago < 3600 => 'il y a ' . intdiv($ago, 60) . ' min',
        $ago < 86400 => 'il y a ' . intdiv($ago, 3600) . ' h',
        default => 'il y a ' . intdiv($ago, 86400) . ' j',
    };

    return [$rel, date('d/m/Y H:i', $ts), $online];
};
$stateTone = static fn (string $s): string => match ($s) {
    'OK' => 'border-emerald-200 bg-emerald-50 text-emerald-800',
    'CRACKED' => 'border-amber-200 bg-amber-50 text-amber-900',
    'OFF' => 'border-slate-300 bg-slate-100 text-slate-700',
    'BROKEN' => 'border-rose-200 bg-rose-50 text-rose-800',
    default => 'border-slate-200 bg-white text-slate-500',
};
?>
<section id="telephones" class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
    <div class="border-b border-slate-100 px-6 py-4">
        <h2 class="text-sm font-black text-slate-900">Téléphones des opérateurs</h2>
        <p class="mt-1 text-xs text-slate-500">Un téléphone par opérateur, créé à sa première connexion depuis le jeu. Le numéro, son type, l’IMEI et l’adresse MAC sont gardés dans Athena : une modification enregistrée ici est reprise par le téléphone en jeu à la synchronisation suivante (moins d’une minute), puis à chaque connexion. Batterie et état sont ceux remontés par le jeu. <?= count($phones) ?> téléphone<?= count($phones) > 1 ? 's' : '' ?>.</p>
    </div>
    <?php if ($phoneCanManage): ?>
        <?php foreach ($phones as $p): $uid = (int) ($p['user_id'] ?? 0); ?>
            <form method="post" id="phone-<?= $uid ?>" action="<?= $e(url('back-office/atak/realisme/telephones/' . $uid . '/enregistrer')) ?>" class="hidden">
                <input type="hidden" name="_csrf_token" value="<?= $e($phoneCsrf) ?>">
            </form>
        <?php endforeach; ?>
    <?php endif; ?>
    <div class="overflow-x-auto">
        <table class="w-full min-w-[1280px] text-sm">
            <thead class="bg-slate-50 text-slate-600">
                <tr>
                    <th class="px-4 py-3 text-left">Opérateur</th>
                    <th class="px-4 py-3 text-left">Numéro</th>
                    <th class="px-4 py-3 text-left">Type de numéro</th>
                    <th class="px-4 py-3 text-left">IMEI</th>
                    <th class="px-4 py-3 text-left">Adresse MAC</th>
                    <th class="px-4 py-3 text-left">Batterie</th>
                    <th class="px-4 py-3 text-left">État</th>
                    <th class="px-4 py-3 text-left">Dernier signe</th>
                    <th class="sticky right-0 bg-slate-50 px-4 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($phones === []): ?>
                <tr>
                    <td colspan="9" class="px-4 py-8 text-center text-slate-500">Aucun téléphone pour le moment. Il apparaît ici dès qu’un opérateur se connecte depuis le jeu.</td>
                </tr>
            <?php endif; ?>
            <?php foreach ($phones as $p):
                $uid = (int) ($p['user_id'] ?? 0);
                $fid = 'phone-' . $uid;
                $name = trim((string) ($p['display_name'] ?? ''));
                $cs = trim((string) ($p['callsign'] ?? ''));
                $format = (string) ($p['phone_format'] ?? 'FR');
                $locked = (int) ($p['format_locked'] ?? 0) === 1;
                $battery = $p['battery_pct'] ?? null;
                $battery = $battery === null || $battery === '' ? null : max(0, min(100, (int) $battery));
                $state = strtoupper((string) ($p['device_state'] ?? ''));
                $stateLabel = GamePhoneIdentityRepository::DEVICE_STATES[$state] ?? 'Inconnu';
                $reason = trim((string) ($p['device_reason'] ?? ''));
                $model = trim((string) ($p['device_model'] ?? ''));
                $bars = $p['signal_bars'] ?? null;
                [$seenRel, $seenAbs, $online] = $seenLabel(isset($p['device_seen_at']) ? (string) $p['device_seen_at'] : null);
                $liveImei = trim((string) ($p['live_imei'] ?? ''));
                $liveMac = trim((string) ($p['live_mac'] ?? ''));
                $liveNumber = trim((string) ($p['live_number'] ?? ''));
                $drift = ($liveImei !== '' && $liveImei !== (string) $p['imei'])
                    || ($liveMac !== '' && $liveMac !== (string) $p['mac'])
                    || ($liveNumber !== '' && $liveNumber !== (string) $p['phone_number']);
                $barTone = $battery === null ? 'bg-slate-300' : ($battery <= 20 ? 'bg-rose-500' : ($battery <= 50 ? 'bg-amber-500' : 'bg-emerald-500'));
                $input = 'w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm font-mono';
                ?>
                <tr class="border-t border-slate-100 align-top">
                    <td class="px-4 py-3">
                        <div class="font-semibold text-slate-900"><?= $e($name !== '' ? $name : ('Compte n° ' . $uid)) ?></div>
                        <div class="text-xs text-slate-500"><?= $e($cs !== '' ? $cs : '—') ?><?php if ($model !== ''): ?> · <?= $e($model) ?><?php endif; ?></div>
                    </td>
                    <td class="px-4 py-3" style="min-width: 170px">
                        <?php if ($phoneCanManage): ?>
                            <input form="<?= $e($fid) ?>" name="phone_number" value="<?= $e($p['phone_number'] ?? '') ?>" class="<?= $input ?>" maxlength="24" autocomplete="off" aria-label="Numéro de <?= $e($name !== '' ? $name : $cs) ?>">
                        <?php else: ?>
                            <span class="font-mono"><?= $e($p['phone_number'] ?? '—') ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3" style="min-width: 190px">
                        <?php if ($phoneCanManage): ?>
                            <select form="<?= $e($fid) ?>" name="phone_format" class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm" aria-label="Type de numéro">
                                <?php foreach (GamePhoneIdentityRepository::FORMATS as $code => $label): ?>
                                    <option value="<?= $e($code) ?>"<?= $code === $format ? ' selected' : '' ?>><?= $e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php else: ?>
                            <?= $e(GamePhoneIdentityRepository::FORMATS[$format] ?? $format) ?>
                        <?php endif; ?>
                        <div class="mt-1 text-xs text-slate-500"><?= $locked ? 'Choisi ici' : 'Suit la communauté' ?></div>
                    </td>
                    <td class="px-4 py-3" style="min-width: 200px">
                        <?php if ($phoneCanManage): ?>
                            <input form="<?= $e($fid) ?>" name="imei" value="<?= $e($p['imei'] ?? '') ?>" class="<?= $input ?>" maxlength="20" autocomplete="off" aria-label="IMEI">
                        <?php else: ?>
                            <span class="font-mono"><?= $e($p['imei'] ?? '—') ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3" style="min-width: 180px">
                        <?php if ($phoneCanManage): ?>
                            <input form="<?= $e($fid) ?>" name="mac" value="<?= $e($p['mac'] ?? '') ?>" class="<?= $input ?>" maxlength="17" autocomplete="off" aria-label="Adresse MAC">
                        <?php else: ?>
                            <span class="font-mono"><?= $e($p['mac'] ?? '—') ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3" style="min-width: 120px">
                        <?php if ($battery === null): ?>
                            <span class="text-xs text-slate-400">—</span>
                        <?php else: ?>
                            <div class="flex items-center gap-2" title="Batterie <?= $battery ?> %">
                                <div class="h-2.5 w-16 overflow-hidden rounded-full bg-slate-100 ring-1 ring-slate-200" role="meter" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= $battery ?>" aria-label="Batterie">
                                    <div class="h-full <?= $barTone ?>" style="width: <?= $battery ?>%"></div>
                                </div>
                                <span class="tabular-nums text-xs font-semibold text-slate-700"><?= $battery ?> %</span>
                            </div>
                        <?php endif; ?>
                        <?php if ($bars !== null && $bars !== ''): ?>
                            <div class="mt-1 text-xs text-slate-500">Signal <?= (int) $bars ?>/4</div>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3">
                        <span class="inline-flex rounded-full border px-2 py-0.5 text-xs font-semibold <?= $stateTone($state) ?>"><?= $e($stateLabel) ?></span>
                        <?php if ($reason !== '' && $state !== 'OK'): ?>
                            <div class="mt-1 text-xs text-slate-500"><?= $e($reason) ?></div>
                        <?php endif; ?>
                        <?php if ($drift): ?>
                            <div class="mt-1 text-xs text-amber-800" title="En jeu : <?= $e(trim($liveNumber . ' · ' . $liveImei . ' · ' . $liveMac, ' ·')) ?>">Valeurs en jeu différentes, en attente de synchronisation</div>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap">
                        <div class="flex items-center gap-1.5">
                            <span class="inline-block h-2 w-2 rounded-full <?= $online ? 'bg-emerald-500' : 'bg-slate-300' ?>" aria-hidden="true"></span>
                            <span><?= $e($seenRel) ?></span>
                        </div>
                        <?php if ($seenAbs !== ''): ?><div class="text-xs text-slate-500"><?= $e($seenAbs) ?></div><?php endif; ?>
                    </td>
                    <td class="sticky right-0 bg-white px-4 py-3 text-right">
                        <?php if ($phoneCanManage && $uid > 0): ?>
                            <div class="inline-flex flex-wrap justify-end gap-1">
                                <button type="submit" form="<?= $e($fid) ?>" formaction="<?= $e(url('back-office/atak/realisme/telephones/' . $uid . '/regenerer')) ?>" class="inline-flex rounded-md border border-slate-200 bg-white px-3 py-1.5 text-xs font-bold uppercase tracking-wide text-slate-700 hover:bg-slate-50" onclick="return confirm(<?= $e(json_encode('Attribuer un nouveau numéro au téléphone de ' . ($name !== '' ? $name : $cs) . ' ? L’ancien ne répondra plus.', JSON_UNESCAPED_UNICODE)) ?>);">Régénérer le numéro</button>
                                <button type="submit" form="<?= $e($fid) ?>" class="inline-flex rounded-md bg-slate-900 px-3 py-1.5 text-xs font-bold uppercase tracking-wide text-white hover:bg-slate-800">Enregistrer</button>
                            </div>
                        <?php else: ?>
                            <span class="text-xs text-slate-400">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <p class="border-t border-slate-100 px-6 py-3 text-xs text-slate-500">Changer le type sans toucher au numéro en attribue un nouveau dans ce format. Laisser l’IMEI ou la MAC vide garde la valeur actuelle. Un changement d’appareil fait en jeu remplace l’IMEI et la MAC ici ; le numéro suit la carte SIM et ne change pas.</p>
</section>
