<?php
/** @var list<array<string, mixed>> $rows */
/** @var int $total */
$rows = is_array($rows ?? null) ? $rows : [];
$total = (int) ($total ?? count($rows));

$fmtDate = static function (mixed $raw): string {
    $s = trim((string) $raw);
    if ($s === '') {
        return '—';
    }
    try {
        return (new \DateTimeImmutable($s))->format('d/m/Y H:i');
    } catch (\Throwable) {
        return $s;
    }
};

$maskSteam = static function (mixed $raw): string {
    $s = trim((string) $raw);
    if ($s === '') {
        return '—';
    }
    if (strlen($s) < 6) {
        return 'Steam …';
    }

    return '…' . substr($s, -6);
};

$maskIp = static function (mixed $raw): string {
    $ip = trim((string) $raw);
    if ($ip === '') {
        return '—';
    }
    if (str_contains($ip, ':')) {
        $parts = explode(':', $ip);

        return 'Réseau …' . substr((string) (end($parts) ?: ''), -4);
    }
    $octets = explode('.', $ip);
    if (count($octets) === 4) {
        return $octets[0] . '.' . $octets[1] . '.*.' . $octets[3];
    }

    return 'Réseau …' . substr($ip, -4);
};
?>
<div class="max-w-5xl mx-auto px-4 sm:px-6 py-10 space-y-8">
    <header>
        <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 mb-1">Tactique · Mod Arma</p>
        <h1 class="text-2xl font-black text-slate-900 tracking-tight">Accès anticipé Overwatch</h1>
        <p class="mt-3 text-sm text-slate-600 leading-relaxed max-w-2xl">
            Liste des joueurs qui ont lancé le pack en version d’accès anticipé et accepté la note d’accès
            (menu principal Arma). Utile pour suivre qui a essayé le mod et depuis quel réseau.
        </p>
        <div class="mt-4 flex flex-wrap gap-3 text-sm">
            <a href="<?= htmlspecialchars(url('admin/atak-mod'), ENT_QUOTES, 'UTF-8') ?>" class="text-slate-600 hover:underline">Pack Overwatch</a>
            <span class="text-slate-300">·</span>
            <a href="<?= htmlspecialchars(url('admin/atak-mod-blocks'), ENT_QUOTES, 'UTF-8') ?>" class="text-slate-600 hover:underline">Restrictions d’accès au mod</a>
            <span class="text-slate-300">·</span>
            <a href="<?= htmlspecialchars(url('admin/atak-config'), ENT_QUOTES, 'UTF-8') ?>" class="text-slate-600 hover:underline">Configuration ATAK</a>
        </div>
    </header>

    <section class="rounded-xl border border-amber-200 bg-amber-50/70 px-4 py-3 text-sm text-amber-950" role="status">
        <strong class="font-semibold"><?= (int) $total ?></strong> inscription<?= $total > 1 ? 's' : '' ?> enregistrée<?= $total > 1 ? 's' : '' ?>
        (affichage des <?= count($rows) ?> plus récentes).
    </section>

    <section class="space-y-4">
        <h2 class="text-sm font-bold text-slate-900">Inscriptions récentes</h2>
        <?php if ($rows === []): ?>
            <p class="text-sm text-slate-500">Aucune inscription pour le moment. Elles apparaîtront dès qu’un joueur lancera le mod et confirmera la note d’accès.</p>
        <?php else: ?>
            <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="p-3">Identifiant Steam</th>
                            <th class="p-3">Nom en jeu</th>
                            <th class="p-3">Adresse réseau</th>
                            <th class="p-3">Version Overwatch</th>
                            <th class="p-3">Build Arma</th>
                            <th class="p-3">Première fois</th>
                            <th class="p-3">Dernière activité</th>
                            <th class="p-3">Passages</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $r): ?>
                            <?php
                            $acked = trim((string) ($r['acknowledged_at'] ?? '')) !== '';
                            $playerName = trim((string) ($r['player_name'] ?? ''));
                            $modVer = trim((string) ($r['mod_version'] ?? ''));
                            $armaBuild = trim((string) ($r['arma_build'] ?? ''));
                            $armaBranch = trim((string) ($r['arma_branch'] ?? ''));
                            $buildLabel = $armaBuild !== '' ? $armaBuild : '—';
                            if ($armaBranch !== '') {
                                $buildLabel .= ' · ' . $armaBranch;
                            }
                            ?>
                            <tr class="border-t border-slate-100">
                                <td class="p-3 font-medium text-slate-800">
                                    <?= htmlspecialchars($maskSteam($r['steam_uid'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                    <?php if ($acked): ?>
                                        <span class="ml-1 inline-flex items-center rounded bg-emerald-50 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-emerald-800">Accepté</span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-3 text-slate-600"><?= htmlspecialchars($playerName !== '' ? $playerName : '—', ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="p-3 text-slate-600"><?= htmlspecialchars($maskIp($r['client_ip'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="p-3 text-slate-600"><?= htmlspecialchars($modVer !== '' ? $modVer : '—', ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="p-3 text-slate-600"><?= htmlspecialchars($buildLabel, ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="p-3 text-slate-600"><?= htmlspecialchars($fmtDate($r['first_seen_at'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="p-3 text-slate-600"><?= htmlspecialchars($fmtDate($r['last_seen_at'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="p-3 text-slate-600"><?= (int) ($r['hit_count'] ?? 1) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="text-xs text-slate-500">
                Les repères Steam et réseau sont partiellement masqués pour limiter la diffusion inutile d’identifiants complets dans l’interface.
            </p>
        <?php endif; ?>
    </section>
</div>
