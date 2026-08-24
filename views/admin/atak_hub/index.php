<?php
declare(strict_types=1);

$cases = is_array($atakHubCases ?? null) ? $atakHubCases : [];
$livePeople = is_array($atakHubLivePeople ?? null) ? $atakHubLivePeople : [];
$maps = is_array($atakHubMaps ?? null) ? $atakHubMaps : [];
$mapId = (int) ($atakHubMapId ?? 1);
$canManage = !empty($canManageAtakHub);
$csrfToken = (string) ($csrfToken ?? \App\Core\Csrf::token());
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$statusFr = static function (?string $status): string {
    return \App\Repositories\SsePersonRepository::statusLabel((string) $status);
};
?>
<div class="max-w-6xl mx-auto px-4 sm:px-6 py-8 space-y-8">
    <header class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <p class="text-xs font-bold uppercase tracking-widest text-slate-500">ATAK · Poste</p>
        <h1 class="mt-2 text-2xl font-black text-slate-900">Poste de situation</h1>
        <p class="mt-2 text-sm text-slate-600 max-w-3xl">Vue rapide des dossiers SSE déjà pourvus d’une identité, et placement d’un téléphone sous localisation — le contact apparaît alors sur la carte, comme depuis Zeus.</p>
        <div class="mt-4 flex flex-wrap gap-2">
            <a href="<?= $h(url('atak')) ?>" class="inline-flex items-center rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Ouvrir la carte</a>
            <a href="<?= $h(url('atak/sse/dossiers')) ?>" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-50">Tous les dossiers SSE</a>
            <a href="<?= $h(url('back-office/atak/realisme')) ?>" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-50">Parc de terminaux</a>
        </div>
    </header>

    <?php if (count($maps) > 1): ?>
        <form method="get" action="<?= $h(url('back-office/atak')) ?>" class="flex flex-wrap items-center gap-2">
            <label for="atak-hub-carte" class="text-sm font-semibold text-slate-700">Carte suivie</label>
            <select id="atak-hub-carte" name="carte" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm" onchange="this.form.submit()">
                <?php foreach ($maps as $m): ?>
                    <option value="<?= (int) $m['id'] ?>"<?= (int) $m['id'] === $mapId ? ' selected' : '' ?>><?= $h($m['label']) ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    <?php endif; ?>

    <section class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <div class="border-b border-slate-100 px-6 py-4">
            <h2 class="text-sm font-black uppercase tracking-widest text-slate-900">Dossiers SSE avec identité</h2>
            <p class="mt-1 text-xs text-slate-500">Uniquement les dossiers ouverts qui ont au moins une personne rattachée. <?= count($cases) ?> dossier<?= count($cases) > 1 ? 's' : '' ?>.</p>
        </div>
        <?php if ($cases === []): ?>
            <p class="px-6 py-8 text-sm text-slate-500">Aucun dossier avec une identité pour le moment. Les fiches se remplissent depuis le recueil terrain.</p>
        <?php else: ?>
            <ul class="divide-y divide-slate-100">
                <?php foreach ($cases as $case):
                    $cid = (int) ($case['id'] ?? 0);
                    $title = trim((string) ($case['title'] ?? ''));
                    if ($title === '') {
                        $title = 'Dossier sans titre';
                    }
                    $ref = trim((string) ($case['reference_code'] ?? ''));
                    $idents = is_array($case['identities'] ?? null) ? $case['identities'] : [];
                    ?>
                    <li class="px-6 py-4 space-y-3">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-bold text-slate-900"><?= $h($title) ?></p>
                                <p class="text-xs text-slate-500"><?= $h($case['status_label'] ?? 'Ouvert') ?><?= $ref !== '' ? ' · ' . $h($ref) : '' ?> · <?= count($idents) ?> identité<?= count($idents) > 1 ? 's' : '' ?></p>
                            </div>
                            <?php if ($cid > 0): ?>
                                <a class="text-sm font-semibold text-slate-900 underline decoration-slate-300 hover:decoration-slate-700" href="<?= $h(url('atak/sse/dossiers/' . $cid)) ?>">Ouvrir le dossier</a>
                            <?php endif; ?>
                        </div>
                        <ul class="space-y-2">
                            <?php foreach ($idents as $person):
                                $pid = (int) ($person['id'] ?? 0);
                                $pname = trim((string) ($person['display_name'] ?? 'Personne sans nom'));
                                $hasNet = trim((string) ($person['target_unit_netid'] ?? '')) !== '';
                                ?>
                                <li class="flex flex-wrap items-center justify-between gap-2 rounded-lg bg-slate-50 px-3 py-2">
                                    <div>
                                        <p class="text-sm font-semibold text-slate-900"><?= $h($pname) ?></p>
                                        <p class="text-xs text-slate-500"><?= $h($statusFr($person['status'] ?? null)) ?><?= $hasNet ? '' : ' · personne encore à rattacher au terrain' ?></p>
                                    </div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <?php if ($pid > 0): ?>
                                            <a class="text-xs font-semibold uppercase tracking-wide text-slate-700 underline decoration-slate-300 hover:decoration-slate-700" href="<?= $h(url('atak/sse/identites/' . $pid)) ?>">Fiche</a>
                                        <?php endif; ?>
                                        <?php if ($canManage && $pid > 0): ?>
                                            <form method="post" action="<?= $h(url('back-office/atak/localisation-telephone')) ?>" onsubmit="return confirm('Placer le téléphone de <?= $h($pname) ?> sous localisation ? Le contact apparaîtra sur la carte.');">
                                                <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                                                <input type="hidden" name="map_id" value="<?= $mapId ?>">
                                                <input type="hidden" name="source" value="person">
                                                <input type="hidden" name="person_id" value="<?= $pid ?>">
                                                <input type="hidden" name="action" value="on">
                                                <button type="submit" class="inline-flex rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-bold uppercase tracking-wide text-slate-800 hover:bg-slate-100">Localiser le téléphone</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <div class="border-b border-slate-100 px-6 py-4">
            <h2 class="text-sm font-black uppercase tracking-widest text-slate-900">Contacts actuellement visibles</h2>
            <p class="mt-1 text-xs text-slate-500">Personnes déjà sur la carte. Vous pouvez placer un téléphone sous localisation, ou l’arrêter s’il l’est déjà.</p>
        </div>
        <?php if ($livePeople === []): ?>
            <p class="px-6 py-8 text-sm text-slate-500">Aucun contact en liaison sur cette carte pour le moment.</p>
        <?php else: ?>
            <ul class="divide-y divide-slate-100">
                <?php foreach ($livePeople as $row):
                    $uid = (int) ($row['id'] ?? 0);
                    $ulabel = (string) ($row['label'] ?? 'Contact');
                    $tracked = !empty($row['tracked']);
                    ?>
                    <li class="flex flex-wrap items-center justify-between gap-2 px-6 py-3">
                        <div>
                            <p class="text-sm font-semibold text-slate-900"><?= $h($ulabel) ?></p>
                            <p class="text-xs text-slate-500"><?= $tracked ? 'Téléphone déjà sous localisation' : 'Pas encore localisé comme téléphone' ?></p>
                        </div>
                        <?php if ($canManage && $uid > 0): ?>
                            <form method="post" action="<?= $h(url('back-office/atak/localisation-telephone')) ?>" onsubmit="return confirm(<?= $h(json_encode($tracked ? ('Arrêter la localisation de « ' . $ulabel . ' » ?') : ('Placer le téléphone de « ' . $ulabel . ' » sous localisation ?'), JSON_UNESCAPED_UNICODE)) ?>);">
                                <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                                <input type="hidden" name="map_id" value="<?= $mapId ?>">
                                <input type="hidden" name="source" value="unit">
                                <input type="hidden" name="unit_id" value="<?= $uid ?>">
                                <input type="hidden" name="action" value="<?= $tracked ? 'off' : 'on' ?>">
                                <button type="submit" class="inline-flex rounded-md border <?= $tracked ? 'border-rose-200 text-rose-800 hover:bg-rose-50' : 'border-slate-300 text-slate-800 hover:bg-slate-100' ?> bg-white px-3 py-1.5 text-xs font-bold uppercase tracking-wide"><?= $tracked ? 'Arrêter' : 'Localiser' ?></button>
                            </form>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <?php if (!$canManage): ?>
            <p class="border-t border-slate-100 px-6 py-3 text-xs text-slate-500">Le placement sous localisation est réservé aux responsables ATAK de la communauté.</p>
        <?php endif; ?>
    </section>
</div>
