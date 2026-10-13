<?php
/** @var list<array<string, mixed>> $servers */
/** @var list<array<string, mixed>> $presence */
/** @var list<array<string, mixed>> $unknownServers */
/** @var array<string, mixed>|null $editServer */
$servers = is_array($servers ?? null) ? $servers : [];
$presence = is_array($presence ?? null) ? $presence : [];
$unknownServers = is_array($unknownServers ?? null) ? $unknownServers : [];
$editServer = is_array($editServer ?? null) ? $editServer : null;
$e = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$csrf = \App\Core\Csrf::token();
$when = static function (mixed $raw): string {
    $s = trim((string) $raw);
    if ($s === '') {
        return '—';
    }
    try {
        return (new \DateTimeImmutable($s))->format('d/m H:i');
    } catch (\Throwable) {
        return $s;
    }
};
$away = array_values(array_filter($presence, static fn (array $p): bool => empty($p['matched_server_id'])));
$home = count($presence) - count($away);
?>
<div class="max-w-5xl mx-auto px-4 sm:px-6 py-10 space-y-10">
    <header>
        <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 mb-1">Gestion · Mod ATAK</p>
        <h1 class="text-2xl font-black text-slate-900 tracking-tight">Serveurs de jeu</h1>
        <p class="mt-3 text-sm text-slate-600 leading-relaxed max-w-2xl">
            Déclarez les serveurs Arma de votre communauté. Un joueur relié à Athena qui joue sur un autre serveur
            reçoit une notification sur son téléphone ATAK : ses positions, messages et comptes rendus partent quand même
            vers votre communauté.
        </p>
        <div class="mt-4 flex flex-wrap gap-3 text-sm">
            <a href="<?= $e(url('back-office/ressources/atak-config')) ?>" class="text-slate-600 hover:underline">Configuration ATAK</a>
            <span class="text-slate-300">·</span>
            <a href="<?= $e(url('back-office/ressources/atak-mod-blocks')) ?>" class="text-slate-600 hover:underline">Restrictions d’accès au mod</a>
        </div>
    </header>

    <?php $f = \App\Core\Session::getFlash('error'); $s = \App\Core\Session::getFlash('success'); ?>
    <?php if ($f): ?><p class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert"><?= $e($f) ?></p><?php endif; ?>
    <?php if ($s): ?><p class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900" role="status"><?= $e($s) ?></p><?php endif; ?>

    <div class="grid sm:grid-cols-3 gap-4">
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Serveurs déclarés</p>
            <p class="mt-1 text-2xl font-black text-slate-900"><?= count(array_filter($servers, static fn (array $r): bool => (int) ($r['is_active'] ?? 0) === 1)) ?></p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Joueurs sur vos serveurs (72 h)</p>
            <p class="mt-1 text-2xl font-black text-emerald-700"><?= (int) $home ?></p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Joueurs vus ailleurs (72 h)</p>
            <p class="mt-1 text-2xl font-black <?= $away !== [] && $servers !== [] ? 'text-amber-600' : 'text-slate-900' ?>"><?= count($away) ?></p>
        </div>
    </div>

    <section class="rounded-xl border border-slate-200 bg-white p-5 sm:p-8 shadow-sm space-y-6" id="formulaire">
        <div>
            <h2 class="text-sm font-bold text-slate-900"><?= $editServer ? 'Modifier « ' . $e($editServer['label'] ?? '') . ' »' : 'Ajouter un serveur' ?></h2>
            <p class="mt-1 text-xs text-slate-500 leading-relaxed">
                L’adresse IP est la plus fiable : elle est reconnue quand le serveur charge l’ATAK natif avec COMSPEC Link.
                Le nom du serveur (tel qu’il apparaît dans la liste des serveurs Arma) sert de repli, par exemple pour un serveur Linux.
                Un nom d’hôte (arma.macommu.fr) est accepté à la place de l’IP.
            </p>
        </div>
        <form method="post" action="<?= $e(url('back-office/ressources/serveurs-de-jeu/enregistrer')) ?>" class="grid md:grid-cols-2 gap-4">
            <input type="hidden" name="_csrf_token" value="<?= $e($csrf) ?>">
            <input type="hidden" name="server_id" value="<?= (int) ($editServer['id'] ?? 0) ?>">
            <div class="md:col-span-2">
                <label for="gs_label" class="block text-xs font-semibold text-slate-600 mb-1">Nom pour la communauté</label>
                <input type="text" name="label" id="gs_label" maxlength="120" value="<?= $e($editServer['label'] ?? '') ?>"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Ex. Serveur opérations, Serveur entraînement">
            </div>
            <div>
                <label for="gs_ip" class="block text-xs font-semibold text-slate-600 mb-1">Adresse IP ou nom d’hôte</label>
                <input type="text" name="ip_address" id="gs_ip" maxlength="80" value="<?= $e($editServer['ip_address'] ?? '') ?>"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-mono" placeholder="203.0.113.10" autocomplete="off">
            </div>
            <div>
                <label for="gs_port" class="block text-xs font-semibold text-slate-600 mb-1">Port de jeu (facultatif)</label>
                <input type="number" name="port" id="gs_port" min="1" max="65535" value="<?= $e($editServer['port'] ?? '') ?>"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-mono" placeholder="2302">
            </div>
            <div class="md:col-span-2">
                <label for="gs_name" class="block text-xs font-semibold text-slate-600 mb-1">Nom du serveur dans Arma</label>
                <input type="text" name="server_name" id="gs_name" maxlength="190" value="<?= $e($editServer['server_name'] ?? '') ?>"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Ex. [FR] COMSPEC | Opérations">
                <p class="mt-1 text-xs text-slate-500">Reconnu même si le serveur y ajoute la mission ou le nombre de places. Sans adresse, Athena la retrouve dans la liste publique des serveurs Arma.</p>
            </div>
            <div class="md:col-span-2 flex flex-wrap gap-3">
                <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800">
                    <?= $editServer ? 'Enregistrer les modifications' : 'Ajouter le serveur' ?>
                </button>
                <?php if ($editServer): ?>
                    <a href="<?= $e(url('back-office/ressources/serveurs-de-jeu')) ?>" class="inline-flex items-center rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Annuler</a>
                <?php endif; ?>
            </div>
        </form>
    </section>

    <section class="space-y-4">
        <h2 class="text-sm font-bold text-slate-900">Serveurs de la communauté</h2>
        <?php if ($servers === []): ?>
            <p class="text-sm text-slate-500">Aucun serveur déclaré : personne n’est prévenu tant que la liste est vide.</p>
        <?php else: ?>
            <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr><th class="px-4 py-3">Serveur</th><th class="px-4 py-3">Adresse</th><th class="px-4 py-3">Nom dans Arma</th><th class="px-4 py-3">État</th><th class="px-4 py-3 text-right">Actions</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                    <?php foreach ($servers as $row): $active = (int) ($row['is_active'] ?? 0) === 1; ?>
                        <tr>
                            <td class="px-4 py-3 font-semibold text-slate-900"><?= $e($row['label'] ?? '') ?></td>
                            <td class="px-4 py-3 font-mono text-xs text-slate-700"><?= $e(($row['ip_address'] ?? '') ?: '—') ?><?= !empty($row['port']) ? ':' . (int) $row['port'] : '' ?></td>
                            <td class="px-4 py-3 text-slate-600"><?= $e(($row['server_name'] ?? '') ?: '—') ?></td>
                            <td class="px-4 py-3">
                                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold <?= $active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' ?>"><?= $active ? 'Actif' : 'En pause' ?></span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap justify-end gap-2">
                                    <a href="<?= $e(url('back-office/ressources/serveurs-de-jeu') . '?modifier=' . (int) $row['id'] . '#formulaire') ?>" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">Modifier</a>
                                    <form method="post" action="<?= $e(url('back-office/ressources/serveurs-de-jeu/activer')) ?>">
                                        <input type="hidden" name="_csrf_token" value="<?= $e($csrf) ?>">
                                        <input type="hidden" name="server_id" value="<?= (int) $row['id'] ?>">
                                        <button type="submit" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50"><?= $active ? 'Mettre en pause' : 'Réactiver' ?></button>
                                    </form>
                                    <form method="post" action="<?= $e(url('back-office/ressources/serveurs-de-jeu/supprimer')) ?>" onsubmit="return confirm('Supprimer ce serveur ?');">
                                        <input type="hidden" name="_csrf_token" value="<?= $e($csrf) ?>">
                                        <input type="hidden" name="server_id" value="<?= (int) $row['id'] ?>">
                                        <button type="submit" class="rounded-lg border border-red-200 px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-50">Supprimer</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <?php if ($unknownServers !== []): ?>
    <section class="space-y-4">
        <div>
            <h2 class="text-sm font-bold text-slate-900">Autres serveurs où vos joueurs ont joué (72 h)</h2>
            <p class="mt-1 text-xs text-slate-500">Si l’un d’eux est à vous, ajoutez-le en un clic.</p>
        </div>
        <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr><th class="px-4 py-3">Nom dans Arma</th><th class="px-4 py-3">Adresse</th><th class="px-4 py-3">Joueurs</th><th class="px-4 py-3">Vu</th><th class="px-4 py-3 text-right"></th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                <?php foreach ($unknownServers as $u): $uAddr = ($u['server_ip'] ?? '') !== '' ? (str_contains((string) $u['server_ip'], ':') ? '[' . $u['server_ip'] . ']' : (string) $u['server_ip']) . (!empty($u['server_port']) ? ':' . (int) $u['server_port'] : '') : ''; ?>
                    <tr>
                        <td class="px-4 py-3 text-slate-800"><?= $e(($u['server_name'] ?? '') ?: '—') ?></td>
                        <td class="px-4 py-3 font-mono text-xs text-slate-700"><?= $e($uAddr ?: 'introuvable') ?></td>
                        <td class="px-4 py-3 text-slate-700"><?= (int) $u['players'] ?></td>
                        <td class="px-4 py-3 text-slate-500"><?= $e($when($u['last_seen'] ?? '')) ?></td>
                        <td class="px-4 py-3 text-right">
                            <form method="post" action="<?= $e(url('back-office/ressources/serveurs-de-jeu/enregistrer')) ?>">
                                <input type="hidden" name="_csrf_token" value="<?= $e($csrf) ?>">
                                <input type="hidden" name="ip_address" value="<?= $e($uAddr) ?>">
                                <input type="hidden" name="server_name" value="<?= $e($u['server_name'] ?? '') ?>">
                                <button type="submit" class="rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-800">C’est notre serveur</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
    <?php endif; ?>

    <section class="space-y-4">
        <h2 class="text-sm font-bold text-slate-900">Joueurs reliés à Athena (72 h)</h2>
        <?php if ($presence === []): ?>
            <p class="text-sm text-slate-500">Aucun joueur vu pour l’instant. Le téléphone ATAK natif (1.35.0 et plus, COMSPEC Link 2.0.75) se signale peu après l’arrivée en partie, puis toutes les 10 minutes.</p>
        <?php else: ?>
            <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr><th class="px-4 py-3">Joueur</th><th class="px-4 py-3">Serveur</th><th class="px-4 py-3">Mission</th><th class="px-4 py-3">Vu</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                    <?php foreach ($presence as $p): $ok = !empty($p['matched_server_id']); ?>
                        <tr>
                            <td class="px-4 py-3 font-semibold text-slate-900"><?= $e(($p['player_name'] ?? '') ?: ($p['steam_uid'] ?? '')) ?></td>
                            <td class="px-4 py-3">
                                <?php if ($ok): ?>
                                    <span class="inline-flex rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-700"><?= $e($p['matched_label'] ?? 'Notre serveur') ?></span>
                                <?php else: ?>
                                    <span class="inline-flex rounded-full bg-amber-50 px-2 py-0.5 text-xs font-semibold text-amber-700">Ailleurs</span>
                                    <span class="ml-1 text-xs text-slate-500"><?= $e(($p['server_name'] ?? '') ?: ($p['server_ip'] ?? '')) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-slate-600"><?= $e(trim(($p['mission'] ?? '') . ' ' . (($p['world'] ?? '') !== '' ? '(' . $p['world'] . ')' : ''))) ?: '—' ?></td>
                            <td class="px-4 py-3 text-slate-500"><?= $e($when($p['seen_at'] ?? '')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>
