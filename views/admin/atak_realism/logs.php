<?php
declare(strict_types=1);

use App\Support\AtakDeviceLog;

/** @var array<string, mixed> $atakDeviceLogTerminal */
/** @var list<array<string, mixed>> $atakDeviceLogRows */
/** @var int $atakDeviceLogTotal */
/** @var string $atakDeviceLogLevel */
/** @var string $atakDeviceLogQuery */
/** @var bool $atakDeviceLogHasMore */

$terminal = is_array($atakDeviceLogTerminal ?? null) ? $atakDeviceLogTerminal : [];
$rows = is_array($atakDeviceLogRows ?? null) ? $atakDeviceLogRows : [];
$total = (int) ($atakDeviceLogTotal ?? 0);
$level = trim((string) ($atakDeviceLogLevel ?? ''));
$search = trim((string) ($atakDeviceLogQuery ?? ''));
$hasMore = !empty($atakDeviceLogHasMore);
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$tid = (int) ($terminal['id'] ?? 0);
$uid = trim((string) ($terminal['terminal_uid'] ?? ''));
$label = trim((string) ($terminal['terminal_label'] ?? ''));
if ($label === '') {
    $label = $uid !== '' ? $uid : 'Terminal ATAK';
}
$cs = trim((string) ($terminal['operator_callsign'] ?? ''));
$journalUrl = url('back-office/atak/realisme/terminaux/' . $tid . '/journal');
$oldestId = 0;
if ($rows !== []) {
    $oldestId = (int) ($rows[array_key_last($rows)]['id'] ?? 0);
}
$fmt = static function (mixed $raw): string {
    $s = trim((string) $raw);
    if ($s === '') {
        return '—';
    }
    try {
        return (new DateTimeImmutable($s))->format('d/m/Y H:i:s');
    } catch (Throwable) {
        return $s;
    }
};
$levelClass = static function (string $lvl): string {
    return match (AtakDeviceLog::normalizeLevel($lvl)) {
        AtakDeviceLog::LEVEL_ERROR => 'bg-rose-100 text-rose-900 border-rose-200',
        AtakDeviceLog::LEVEL_WARN => 'bg-amber-100 text-amber-950 border-amber-200',
        AtakDeviceLog::LEVEL_DEBUG => 'bg-slate-100 text-slate-600 border-slate-200',
        default => 'bg-sky-50 text-sky-900 border-sky-200',
    };
};
$sourceLabel = static function (mixed $raw): string {
    return match (strtolower(trim((string) $raw))) {
        'web' => 'Carte',
        'system' => 'Liaison',
        default => 'Jeu',
    };
};
?>
<div class="max-w-6xl mx-auto px-4 sm:px-6 py-8 space-y-6">
    <header class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <p class="text-xs font-bold uppercase tracking-widest text-slate-500">ATAK · Parc</p>
        <h1 class="mt-2 text-2xl font-black text-slate-900">Journal — <?= $h($label) ?></h1>
        <p class="mt-2 text-sm text-slate-600 max-w-3xl">
            Traces de cet appareil : erreurs, coupures de liaison, état de l’écran et messages du poste de jeu.
            Ce sont les mêmes informations que celles enregistrées sur l’ordinateur du joueur.
        </p>
        <?php $liaison = \App\Repositories\AtakRealismRepository::liaisonIdentity($terminal); ?>
        <dl class="mt-4 flex flex-wrap gap-x-6 gap-y-1 text-sm text-slate-600">
            <div><span class="text-slate-500">Indicatif</span> · <span class="font-semibold text-slate-900"><?= $h($cs !== '' ? $cs : '—') ?></span></div>
            <div><span class="text-slate-500">Identifiant</span> · <span class="font-mono text-slate-800"><?= $h($uid !== '' ? $uid : '—') ?></span></div>
            <div><span class="text-slate-500">Chaîne de confiance</span> · <span class="font-semibold text-slate-900"><?= $h($liaison['trust']) ?></span></div>
            <div><span class="text-slate-500">Autorité</span> · <span class="font-semibold text-slate-900"><?= $h($liaison['authority']) ?></span></div>
            <div><span class="text-slate-500">Versions</span> · <span class="font-semibold text-slate-900"><?= $h($liaison['versions']) ?></span></div>
            <div><span class="text-slate-500">Signature serveur</span> · <span class="font-mono text-slate-800"><?= $h($liaison['signature']) ?><?php if ($liaison['host'] !== ''): ?> <span class="text-slate-500"><?= $h($liaison['host']) ?></span><?php endif; ?></span></div>
            <div><span class="text-slate-500">IP</span> · <span class="font-mono text-slate-800"><?= $h($liaison['ip']) ?></span></div>
            <div><span class="text-slate-500">Entrées</span> · <span class="font-semibold text-slate-900"><?= (int) $total ?></span></div>
        </dl>
        <div class="mt-4 flex flex-wrap gap-2">
            <a href="<?= $h(url('back-office/atak/realisme')) ?>" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-50">Retour au parc</a>
            <?php if ($cs !== ''): ?>
                <a href="<?= $h(url('back-office/atak/fiche-operateur?indicatif=' . rawurlencode($cs))) ?>" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-50">Fiche opérateur</a>
            <?php endif; ?>
        </div>
    </header>

    <form method="get" action="<?= $h($journalUrl) ?>" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm flex flex-col gap-3 sm:flex-row sm:items-end">
        <div class="sm:w-48">
            <label for="atak-journal-niveau" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Gravité</label>
            <select id="atak-journal-niveau" name="niveau" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900">
                <option value="" <?= $level === '' ? 'selected' : '' ?>>Toutes</option>
                <option value="error" <?= $level === 'error' ? 'selected' : '' ?>>Erreurs</option>
                <option value="warn" <?= $level === 'warn' ? 'selected' : '' ?>>Alertes</option>
                <option value="info" <?= $level === 'info' ? 'selected' : '' ?>>Informations</option>
            </select>
        </div>
        <div class="flex-1">
            <label for="atak-journal-q" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Recherche</label>
            <input id="atak-journal-q" name="q" value="<?= $h($search) ?>" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Message, canal…" autocomplete="off">
        </div>
        <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Filtrer</button>
    </form>

    <section class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <?php if ($rows === []): ?>
            <p class="px-6 py-10 text-center text-sm text-slate-500">
                Aucune trace pour le moment. Les lignes apparaissent dès que l’appareil est en liaison
                (erreurs, déconnexion, état de l’écran). Si le journal reste vide, le pack Overwatch du poste de jeu n’est peut-être pas à jour.
            </p>
        <?php else: ?>
            <ul class="divide-y divide-slate-100">
                <?php foreach ($rows as $row):
                    $lvl = (string) ($row['level'] ?? 'info');
                    ?>
                    <li class="px-4 py-3 sm:px-6">
                        <div class="flex flex-wrap items-center gap-2 text-xs">
                            <time class="font-mono text-slate-500"><?= $h($fmt($row['logged_at'] ?? '')) ?></time>
                            <span class="inline-flex rounded-full border px-2 py-0.5 font-semibold <?= $h($levelClass($lvl)) ?>"><?= $h(AtakDeviceLog::levelLabel($lvl)) ?></span>
                            <span class="rounded-full bg-slate-50 px-2 py-0.5 font-medium text-slate-600"><?= $h(AtakDeviceLog::channelLabel((string) ($row['channel'] ?? ''))) ?></span>
                            <span class="text-slate-400"><?= $h($sourceLabel($row['source'] ?? '')) ?></span>
                        </div>
                        <p class="mt-1 text-sm text-slate-900"><?= $h($row['message'] ?? '') ?></p>
                        <?php $detail = trim((string) ($row['detail_text'] ?? '')); ?>
                        <?php if ($detail !== ''): ?>
                            <p class="mt-0.5 text-xs text-slate-500 whitespace-pre-wrap"><?= $h($detail) ?></p>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php if ($hasMore && $oldestId > 0): ?>
                <div class="border-t border-slate-100 px-6 py-3">
                    <a class="text-sm font-semibold text-slate-800 underline decoration-slate-300 hover:decoration-slate-700" href="<?= $h($journalUrl . '?' . http_build_query(array_filter([
                        'niveau' => $level !== '' ? $level : null,
                        'q' => $search !== '' ? $search : null,
                        'avant' => $oldestId,
                    ]))) ?>">Voir les traces plus anciennes</a>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </section>
</div>
