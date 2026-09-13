<?php
declare(strict_types=1);

use App\Repositories\SseCaseRepository;

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/** @var list<array<string,mixed>> $codes */
/** @var list<array<string,mixed>> $cases */
/** @var list<array<string,mixed>> $actionLog */
/** @var string|null $issuedPlain */
$codes = is_array($codes ?? null) ? $codes : [];
$cases = is_array($cases ?? null) ? $cases : [];
$actionLog = is_array($actionLog ?? null) ? $actionLog : [];
$issuedPlain = $issuedPlain ?? null;
$classificationLabels = is_array($classificationLabels ?? null)
    ? $classificationLabels
    : SseCaseRepository::CLASSIFICATION_LABELS;

$activeCodes = 0;
foreach ($codes as $c) {
    if (!empty($c['active'])) {
        $activeCodes++;
    }
}

$fmtLogTime = static function (array $row): string {
    $raw = (string) ($row['created_at'] ?? '');
    if ($raw === '') {
        $ts = (int) ($row['ts'] ?? 0);

        return $ts > 0 ? date('d/m H:i', $ts) : '—';
    }
    $ts = strtotime($raw);

    return $ts ? date('d/m H:i', $ts) : $raw;
};

$clearanceLabel = static function (array $c) use ($classificationLabels): string {
    $code = (string) ($c['clearance_level'] ?? '');

    return (string) ($classificationLabels[$code] ?? ($code !== '' ? $code : '—'));
};
?>
<div class="max-w-6xl mx-auto px-4 sm:px-6 py-8 space-y-8">
    <header class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <p class="text-xs font-bold uppercase tracking-widest text-slate-500">ATAK · Renseignement</p>
        <h1 class="mt-2 text-2xl font-black text-slate-900">Accès renseignement</h1>
        <p class="mt-2 text-sm text-slate-600 max-w-3xl">
            Délivrez des codes temporaires pour les membres habilités et les invités.
            Le commandement et les membres déjà autorisés entrent au portail sans code.
            Le niveau de lecture d’un opérateur peut aussi être fixé sur sa fiche effectifs.
        </p>
        <div class="mt-4 flex flex-wrap gap-2">
            <a href="<?= $h(url('atak/sse')) ?>" class="inline-flex items-center rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Ouvrir le portail</a>
            <a href="<?= $h(url('atak/sse/commandement')) ?>" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-50">Entrer comme commandement</a>
        </div>
    </header>

    <section class="rounded-2xl border border-amber-200 bg-amber-50/60 p-5 text-sm text-amber-950 leading-relaxed">
        <p class="font-bold mb-1">Niveaux de diffusion</p>
        <p>
            Sur la fiche d’un opérateur, le champ « Niveau de diffusion renseignement » fixe jusqu’où il peut lire
            le renseignement classifié. Les rôles de la communauté peuvent aussi porter ces habilitations.
            L’entrée au portail reste distincte : droits d’accès pour les membres, code temporaire pour les invités.
        </p>
    </section>

    <?php if (!empty($issuedPlain)): ?>
        <div class="rounded-2xl border border-emerald-300 bg-emerald-50 p-5">
            <p class="text-xs font-bold uppercase tracking-wider text-emerald-800">Code à transmettre immédiatement</p>
            <p class="mt-1 text-sm text-emerald-900">Notez-le maintenant — il ne sera plus réaffiché.</p>
            <p class="mt-3 font-mono text-2xl font-black tracking-widest text-emerald-950"><?= $h($issuedPlain) ?></p>
        </div>
    <?php endif; ?>

    <div class="grid gap-4 sm:grid-cols-3">
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-[10px] font-black uppercase tracking-wider text-slate-500">Codes émis</p>
            <p class="mt-1 text-2xl font-black text-slate-900"><?= $h((string) count($codes)) ?></p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-[10px] font-black uppercase tracking-wider text-slate-500">Encore valides</p>
            <p class="mt-1 text-2xl font-black text-slate-900"><?= $h((string) $activeCodes) ?></p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-[10px] font-black uppercase tracking-wider text-slate-500">Journal récent</p>
            <p class="mt-1 text-2xl font-black text-slate-900"><?= $h((string) count($actionLog)) ?></p>
        </div>
    </div>

    <section class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <div class="border-b border-slate-100 px-6 py-4">
            <h2 class="text-sm font-black uppercase tracking-widest text-slate-900">Délivrer un code</h2>
            <p class="mt-1 text-xs text-slate-500">Communiquez chaque code par un canal sécurisé.</p>
        </div>
        <form method="post" action="<?= $h(url('back-office/renseignement/acces')) ?>" class="grid gap-4 p-6 md:grid-cols-2">
            <?= \App\Core\Csrf::field() ?>
            <div class="md:col-span-2">
                <label for="label" class="block text-xs font-bold text-slate-600 mb-1">Libellé</label>
                <input id="label" name="label" type="text" required maxlength="120" value="Accès temporaire"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label for="grant_type" class="block text-xs font-bold text-slate-600 mb-1">Type d’accès</label>
                <select id="grant_type" name="grant_type" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="member">Membre habilité</option>
                    <option value="guest">Invité</option>
                </select>
            </div>
            <div>
                <label for="clearance_level" class="block text-xs font-bold text-slate-600 mb-1">Niveau de lecture accordé</label>
                <select id="clearance_level" name="clearance_level" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <?php foreach ($classificationLabels as $ck => $clabel): ?>
                        <option value="<?= $h($ck) ?>" <?= $ck === 'interne' ? 'selected' : '' ?>><?= $h($clabel) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="ttl_hours" class="block text-xs font-bold text-slate-600 mb-1">Validité du code</label>
                <select id="ttl_hours" name="ttl_hours" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <?php foreach ([1, 2, 4, 8, 12, 24, 48, 72] as $hval): ?>
                        <option value="<?= $hval ?>" <?= $hval === 4 ? 'selected' : '' ?>><?= $hval ?> h</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="session_ttl_minutes" class="block text-xs font-bold text-slate-600 mb-1">Durée de session après saisie</label>
                <select id="session_ttl_minutes" name="session_ttl_minutes" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="60">1 heure</option>
                    <option value="120">2 heures</option>
                    <option value="240" selected>4 heures</option>
                    <option value="480">8 heures</option>
                    <option value="1440">24 heures</option>
                </select>
            </div>
            <div>
                <label for="max_uses" class="block text-xs font-bold text-slate-600 mb-1">Nombre d’utilisations</label>
                <select id="max_uses" name="max_uses" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <?php for ($i = 1; $i <= 10; $i++): ?>
                        <option value="<?= $i ?>"><?= $i ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div>
                <label for="case_id" class="block text-xs font-bold text-slate-600 mb-1">Limiter à un dossier (facultatif)</label>
                <select id="case_id" name="case_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">Tous les dossiers</option>
                    <?php foreach ($cases as $c): ?>
                        <option value="<?= (int) ($c['id'] ?? 0) ?>"><?= $h(($c['reference_code'] ?? '') . ' — ' . ($c['title'] ?? '')) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="md:col-span-2">
                <button type="submit" class="inline-flex rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">Générer le code</button>
            </div>
        </form>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <div class="border-b border-slate-100 px-6 py-4">
            <h2 class="text-sm font-black uppercase tracking-widest text-slate-900">Codes émis</h2>
        </div>
        <?php if ($codes === []): ?>
            <p class="px-6 py-8 text-sm text-slate-500">Aucun code délivré pour le moment.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Libellé</th>
                        <th class="px-4 py-3">Type</th>
                        <th class="px-4 py-3">Niveau de lecture</th>
                        <th class="px-4 py-3">Indice</th>
                        <th class="px-4 py-3">Usages</th>
                        <th class="px-4 py-3">Expire</th>
                        <th class="px-4 py-3">État</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                    <?php foreach ($codes as $c): ?>
                        <tr>
                            <td class="px-4 py-3 font-semibold text-slate-900"><?= $h($c['label'] ?? '') ?></td>
                            <td class="px-4 py-3"><?= $h($c['grant_type_label'] ?? '') ?></td>
                            <td class="px-4 py-3"><?= $h($clearanceLabel($c)) ?></td>
                            <td class="px-4 py-3 font-mono text-xs"><?= $h($c['code_hint'] ?? '') ?></td>
                            <td class="px-4 py-3"><?= (int) ($c['uses_count'] ?? 0) ?> / <?= (int) ($c['max_uses'] ?? 1) ?></td>
                            <td class="px-4 py-3"><?= $h((string) ($c['expires_at'] ?? '—')) ?></td>
                            <td class="px-4 py-3"><?= $h($c['status_label'] ?? '') ?></td>
                            <td class="px-4 py-3 text-right">
                                <?php if (!empty($c['active'])): ?>
                                    <form method="post" action="<?= $h(url('back-office/renseignement/acces/' . (int) ($c['id'] ?? 0) . '/revoquer')) ?>" class="inline">
                                        <?= \App\Core\Csrf::field() ?>
                                        <button type="submit" class="text-xs font-bold text-rose-700 hover:underline"
                                                onclick="return confirm('Révoquer ce code ?');">Révoquer</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <div class="border-b border-slate-100 px-6 py-4">
            <h2 class="text-sm font-black uppercase tracking-widest text-slate-900">Journal récent</h2>
        </div>
        <?php if ($actionLog === []): ?>
            <p class="px-6 py-8 text-sm text-slate-500">Aucune action enregistrée pour le moment.</p>
        <?php else: ?>
            <ul class="divide-y divide-slate-100">
                <?php foreach ($actionLog as $row): ?>
                    <li class="px-6 py-3 text-sm">
                        <div class="flex flex-wrap items-center gap-2 text-xs text-slate-500">
                            <time><?= $h($fmtLogTime($row)) ?></time>
                            <span class="rounded bg-slate-100 px-2 py-0.5 font-semibold text-slate-700"><?= $h($row['event_label'] ?? 'Action') ?></span>
                            <?php if (!empty($row['actor'])): ?>
                                <span><?= $h($row['actor']) ?></span>
                            <?php endif; ?>
                        </div>
                        <p class="mt-1 text-slate-800"><?= $h(($row['detail'] ?? '') !== '' ? $row['detail'] : ($row['event_label'] ?? 'Action enregistrée')) ?></p>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</div>
