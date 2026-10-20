<?php
declare(strict_types=1);

/**
 * Panneau « ATAK & liaison jeu » de la fiche compte (administration du site).
 * Données : AdminUserAtakService::overview(). Actions : SystemUserAtakController (POST /admin/users/{id}/atak/{action}).
 * Hors du formulaire principal de la fiche (formulaires imbriqués interdits).
 *
 * @var array<string, mixed>|null $platformEditAtak
 * @var int $uid
 * @var callable $h
 */

use App\Repositories\AtakAdminControlRepository;

$atak = is_array($platformEditAtak ?? null) ? $platformEditAtak : null;
$base = url('admin/users/' . (int) $uid . '/atak');
$csrf = \App\Core\Csrf::field();
$fmt = static function (?string $raw, bool $utc = false): string {
    $raw = trim((string) $raw);
    if ($raw === '' || str_starts_with($raw, '0000')) {
        return '—';
    }
    $ts = strtotime($raw . ($utc ? ' UTC' : ''));

    return $ts === false ? $raw : date('d/m/Y H:i', $ts);
};
$tone = static fn (string $s): string => match ($s) {
    'ok' => 'border-emerald-200 bg-emerald-50 text-emerald-800',
    'warn', 'idle' => 'border-amber-200 bg-amber-50 text-amber-800',
    'bad' => 'border-rose-200 bg-rose-50 text-rose-800',
    default => 'border-slate-200 bg-slate-50 text-slate-700',
};
$btn = 'inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-50';
$btnPrimary = 'inline-flex items-center rounded-lg bg-emerald-800 px-3 py-2 text-sm font-bold text-white hover:bg-emerald-900';
$btnDanger = 'inline-flex items-center rounded-lg border border-rose-300 bg-white px-3 py-2 text-sm font-semibold text-rose-700 hover:bg-rose-50';
$field = 'mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600';
$label = 'block text-xs font-semibold uppercase tracking-wide text-slate-500';
$th = 'px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500';
$td = 'px-3 py-2 align-top text-sm text-slate-800';
?>
<section id="atak" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm space-y-6 scroll-mt-24">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h2 class="text-base font-bold text-slate-900">ATAK &amp; liaison jeu</h2>
            <p class="text-sm text-slate-600">Les ATAK de ce compte, l’état de sa liaison avec le jeu, et les outils pour la dépanner ou la forcer.</p>
        </div>
        <?php if ($atak !== null): ?>
            <?php $v = $atak['verdict']; ?>
            <span class="rounded-full border px-3 py-1 text-xs font-bold <?= $tone($v['state']) ?>">
                <?= $h(['ok' => 'Liaison opérationnelle', 'idle' => 'Liée, pas de session active', 'bad' => 'Liaison à réparer'][$v['state']] ?? $v['state']) ?>
            </span>
        <?php endif; ?>
    </div>

    <?php if ($atak === null): ?>
        <p class="rounded-lg border border-dashed border-slate-300 p-4 text-sm text-slate-600">Les données ATAK de ce compte ne sont pas disponibles.</p>
    <?php else: ?>
        <?php
        $user = $atak['user'];
        $steam = $atak['steam'];
        $account = $atak['account'];
        $terminals = $atak['terminals'];
        $sessions = $atak['sessions'];
        $lastSession = $sessions[0] ?? null;
        $activeCode = null;
        foreach ($atak['link_codes'] as $c) {
            if ((int) $c['usable'] === 1) {
                $activeCode = $c;
                break;
            }
        }
        ?>

        <?php if ($atak['verdict']['problems'] !== []): ?>
            <ul class="space-y-1 rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm text-rose-800">
                <?php foreach ($atak['verdict']['problems'] as $p): ?>
                    <li>• <?= $h($p) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <!-- Identité de liaison -->
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-lg border border-slate-200 p-3">
                <p class="<?= $label ?>">Steam de la fiche</p>
                <p class="mt-1 font-mono text-sm font-semibold text-slate-900"><?= $h($steam ?? 'Aucun') ?></p>
                <?php if ($steam !== null): ?><a class="text-xs text-emerald-700 hover:underline" href="https://steamcommunity.com/profiles/<?= $h($steam) ?>" target="_blank" rel="noopener">Profil Steam</a><?php endif; ?>
            </div>
            <div class="rounded-lg border border-slate-200 p-3">
                <p class="<?= $label ?>">Compte Athena (jeu)</p>
                <?php if ($account === null): ?>
                    <p class="mt-1 text-sm text-slate-600">Pas encore créé (créé à la première liaison)</p>
                <?php else: ?>
                    <p class="mt-1 font-mono text-sm font-semibold text-slate-900"><?= $h(($account['steam_id'] ?? '') ?: 'sans Steam') ?></p>
                    <p class="text-xs text-slate-500">#<?= (int) $account['id'] ?> · <?= $h($account['status'] ?? '') ?></p>
                <?php endif; ?>
            </div>
            <div class="rounded-lg border border-slate-200 p-3">
                <p class="<?= $label ?>">Sessions de jeu</p>
                <p class="mt-1 text-sm font-semibold text-slate-900"><?= (int) $atak['sessions_usable'] ?> active(s) · <?= count($sessions) ?> récente(s)</p>
                <?php if ($lastSession !== null): ?>
                    <p class="text-xs text-slate-500">Dernière : <?= $h($fmt($lastSession['last_seen_at'] ?? $lastSession['created_at'])) ?> · mod <?= $h($lastSession['mod_version'] ?: '?') ?> · Link <?= $h($lastSession['extension_version'] ?: '?') ?></p>
                <?php endif; ?>
            </div>
            <div class="rounded-lg border border-slate-200 p-3">
                <p class="<?= $label ?>">Blocage ATAK</p>
                <?php if ($atak['active_block'] === null): ?>
                    <p class="mt-1 text-sm font-semibold text-emerald-700">Aucun</p>
                <?php else: ?>
                    <p class="mt-1 text-sm font-semibold text-rose-700">Actif<?= !empty($atak['active_block']['expires_at']) ? ' jusqu’au ' . $h($fmt($atak['active_block']['expires_at'], true)) : '' ?></p>
                    <p class="text-xs text-slate-500"><?= $h($atak['active_block']['reason'] ?? '') ?></p>
                <?php endif; ?>
            </div>
        </div>

        <?php if (is_array($atak['diagnosis'])): ?>
            <p class="rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700"><span class="font-semibold">Diagnostic de session :</span> <?= $h($atak['diagnosis']['text'] ?? '') ?><?= !empty($atak['diagnosis']['no_access_key']) ? ' La communauté n’a pas de clé d’accès ATAK.' : '' ?></p>
        <?php endif; ?>

        <?php if ($atak['conflicts'] !== []): ?>
            <div class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
                <p class="font-semibold">Ce Steam est aussi rattaché à :</p>
                <ul class="mt-1 space-y-0.5">
                    <?php foreach ($atak['conflicts'] as $c): ?>
                        <li>• <?= $h($c['kind'] === 'fiche' ? 'Fiche' : 'Compte Athena') ?> #<?= (int) $c['id'] ?> — <?= $h($c['label'] ?? '') ?><?= !empty($c['tenant_name']) ? ' (' . $h($c['tenant_name']) . ')' : '' ?>
                            <?php if ($c['kind'] === 'fiche'): ?> · <a class="underline" href="<?= $h(url('admin/users/' . (int) $c['id'] . '/edit')) ?>#atak">ouvrir</a><?php endif; ?></li>
                    <?php endforeach; ?>
                </ul>
                <p class="mt-1 text-xs">« Forcer la liaison » ci-dessous retire ce Steam de ces fiches et comptes.</p>
            </div>
        <?php endif; ?>

        <!-- Terminaux -->
        <div class="space-y-2">
            <h3 class="text-sm font-bold text-slate-900">ATAK du compte (<?= count($terminals) ?>)</h3>
            <?php if ($terminals === []): ?>
                <p class="rounded-lg border border-dashed border-slate-300 p-4 text-sm text-slate-600">Aucun ATAK rattaché à ce compte dans sa communauté.</p>
            <?php else: ?>
                <div class="overflow-x-auto rounded-lg border border-slate-200">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50"><tr>
                            <th class="<?= $th ?>">ATAK</th><th class="<?= $th ?>">Liaison</th><th class="<?= $th ?>">Contrôles</th><th class="<?= $th ?>">Versions</th><th class="<?= $th ?>">Vu</th><th class="<?= $th ?>"></th>
                        </tr></thead>
                        <tbody class="divide-y divide-slate-100">
                        <?php foreach ($terminals as $t): ?>
                            <tr>
                                <td class="<?= $td ?>">
                                    <p class="font-semibold"><?= $h($t['terminal_label'] ?? 'ATAK') ?></p>
                                    <p class="font-mono text-xs text-slate-500"><?= $h($t['terminal_uid'] ?? '') ?> · <?= $h($t['terminal_type'] ?? '') ?></p>
                                </td>
                                <td class="<?= $td ?>">
                                    <span class="whitespace-nowrap rounded-full border px-2 py-0.5 text-xs font-bold <?= $tone($t['verdict']) ?>"><?= $h(['ok' => 'OK', 'warn' => 'À surveiller', 'bad' => 'En défaut'][$t['verdict']] ?? $t['verdict']) ?></span>
                                    <p class="mt-1 text-xs text-slate-500">Statut <?= $h($t['status'] ?? '') ?><?= ($t['compromise_state'] ?? 'none') !== 'none' ? ' · ' . $h($t['compromise_state']) : '' ?></p>
                                </td>
                                <td class="<?= $td ?>">
                                    <div class="flex max-w-md flex-wrap gap-1">
                                        <?php foreach ($t['checks'] as $c): ?>
                                            <span class="rounded border px-1.5 py-0.5 text-[11px] font-semibold <?= $tone($c['state'] === 'ok' ? 'ok' : ($c['state'] === 'warn' ? 'warn' : 'bad')) ?>" title="<?= $h($c['detail'] ?? '') ?>"><?= $h($c['label']) ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                </td>
                                <td class="<?= $td ?>"><p>Mod <?= $h(($t['mod_version'] ?? '') ?: '?') ?></p><p class="text-xs text-slate-500">Link <?= $h(($t['extension_version'] ?? '') ?: '?') ?></p></td>
                                <td class="<?= $td ?>"><p><?= $h($t['link']['label'] ?? '') ?></p><p class="text-xs text-slate-500"><?= $h($t['link']['ago'] ?? '') ?></p></td>
                                <td class="<?= $td ?> whitespace-nowrap"><a class="text-sm font-semibold text-emerald-700 hover:underline" href="<?= $h(url('admin/parc-atak/fiche') . '?id=' . (int) $t['id']) ?>">Fiche parc ATAK →</a></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Forcer la liaison -->
        <div class="grid gap-4 lg:grid-cols-2">
            <form method="post" action="<?= $h($base . '/forcer') ?>" class="space-y-3 rounded-lg border border-emerald-200 bg-emerald-50/40 p-4"
                  onsubmit="return confirm('Forcer la liaison de ce Steam sur ce compte ? Il sera retiré de toute autre fiche ou compte, et leurs sessions de jeu seront fermées.');">
                <div>
                    <?= $csrf ?>
                    <h3 class="text-sm font-bold text-slate-900">Forcer la liaison</h3>
                    <p class="text-xs text-slate-600">Rattache ce Steam (et un ATAK) à ce compte sans code. Le joueur relance simplement la connexion dans Arma.</p>
                </div>
                <div>
                    <label for="atak-steam" class="<?= $label ?>">Steam</label>
                    <input id="atak-steam" name="steam_id" type="text" class="<?= $field ?>" value="<?= $h($steam ?? '') ?>" placeholder="7656119… ou adresse du profil Steam" required>
                </div>
                <div>
                    <label for="atak-terminal" class="<?= $label ?>">ATAK (facultatif)</label>
                    <input id="atak-terminal" name="terminal_uid" type="text" class="<?= $field ?> font-mono" list="atak-terminal-list" placeholder="Identifiant du terminal">
                    <datalist id="atak-terminal-list">
                        <?php foreach ($terminals as $t): ?><option value="<?= $h($t['terminal_uid']) ?>"><?= $h($t['terminal_label'] ?? '') ?></option><?php endforeach; ?>
                        <?php foreach ($atak['pending'] as $p): ?><?php if (!empty($p['terminal_uid'])): ?><option value="<?= $h($p['terminal_uid']) ?>">Demande en attente · Steam <?= $h($p['steam_id']) ?></option><?php endif; ?><?php endforeach; ?>
                    </datalist>
                </div>
                <label class="flex items-center gap-2 text-sm text-slate-800"><input type="checkbox" name="lift_blocks" value="1" class="rounded border-slate-300" <?= $atak['active_block'] !== null ? 'checked' : '' ?>> Lever aussi les blocages ATAK actifs</label>
                <button type="submit" class="<?= $btnPrimary ?>">Forcer la liaison</button>
            </form>

            <div class="space-y-3 rounded-lg border border-slate-200 p-4">
                <div>
                    <h3 class="text-sm font-bold text-slate-900">Valider une demande de liaison</h3>
                    <p class="text-xs text-slate-600">Le joueur lance la liaison dans Arma : un code s’affiche. Saisissez-le ici, ou validez une demande ci-dessous.</p>
                </div>
                <form method="post" action="<?= $h($base . '/valider') ?>" class="flex flex-wrap items-end gap-2">
                    <?= $csrf ?>
                    <div class="flex-1">
                        <label for="atak-code" class="<?= $label ?>">Code affiché dans Arma</label>
                        <input id="atak-code" name="user_code" type="text" class="<?= $field ?> font-mono uppercase" placeholder="XXXX-XXXX" maxlength="12" autocomplete="off">
                    </div>
                    <button type="submit" class="<?= $btnPrimary ?>">Valider pour ce compte</button>
                </form>
                <div class="border-t border-slate-200 pt-3">
                    <p class="<?= $label ?>">Ou donner un code au joueur</p>
                    <?php if ($activeCode !== null): ?>
                        <p class="mt-1 text-sm">Code en cours : <span class="font-mono text-lg font-bold tracking-widest text-slate-900"><?= $h($activeCode['code']) ?></span> <span class="text-xs text-slate-500">jusqu’à <?= $h($fmt($activeCode['expires_at'], true)) ?></span></p>
                    <?php endif; ?>
                    <form method="post" action="<?= $h($base . '/code') ?>" class="mt-2"><?= $csrf ?><button type="submit" class="<?= $btn ?>"><?= $activeCode !== null ? 'Générer un nouveau code' : 'Générer un code de liaison' ?></button></form>
                    <p class="mt-1 text-xs text-slate-500">Valable 30 min, à saisir dans Arma (Liaison Athena).</p>
                </div>
            </div>
        </div>

        <!-- Demandes en attente -->
        <div class="space-y-2">
            <h3 class="text-sm font-bold text-slate-900">Demandes de liaison en cours (toutes communautés)</h3>
            <?php if ($atak['pending'] === []): ?>
                <p class="rounded-lg border border-dashed border-slate-300 p-3 text-sm text-slate-600">Aucune demande en attente. Demandez au joueur de lancer la liaison dans Arma, puis rechargez la page.</p>
            <?php else: ?>
                <div class="overflow-x-auto rounded-lg border border-slate-200">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50"><tr><th class="<?= $th ?>">Steam</th><th class="<?= $th ?>">ATAK</th><th class="<?= $th ?>">Mod</th><th class="<?= $th ?>">Steam déjà lié à</th><th class="<?= $th ?>">Expire</th><th class="<?= $th ?>"></th></tr></thead>
                        <tbody class="divide-y divide-slate-100">
                        <?php foreach ($atak['pending'] as $p): ?>
                            <tr class="<?= $p['matches_user'] ? 'bg-emerald-50/60' : '' ?>">
                                <td class="<?= $td ?> font-mono text-xs"><?= $h($p['steam_id'] ?: '—') ?><?= $p['matches_user'] ? ' <span class="font-sans font-bold text-emerald-700">(ce compte)</span>' : '' ?></td>
                                <td class="<?= $td ?> font-mono text-xs"><?= $h($p['terminal_uid'] ?: '—') ?></td>
                                <td class="<?= $td ?>"><?= $h($p['mod_version'] ?: '?') ?></td>
                                <td class="<?= $td ?>"><?= is_array($p['current_owner']) ? $h($p['current_owner']['label']) . ' <span class="text-xs text-slate-500">(' . $h($p['current_owner']['tenant_name'] ?? '') . ')</span>' : '<span class="text-slate-400">personne</span>' ?></td>
                                <td class="<?= $td ?>"><?= max(0, (int) round(((int) $p['left_s']) / 60)) ?> min</td>
                                <td class="<?= $td ?>">
                                    <form method="post" action="<?= $h($base . '/valider') ?>" onsubmit="return confirm('Valider cette demande pour ce compte ? Le jeu sera relié à cette fiche.');">
                                        <?= $csrf ?><input type="hidden" name="challenge_id" value="<?= (int) $p['id'] ?>">
                                        <button type="submit" class="<?= $p['matches_user'] ? $btnPrimary : $btn ?>">Valider pour ce compte</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Dépannage -->
        <div class="space-y-2">
            <h3 class="text-sm font-bold text-slate-900">Dépannage</h3>
            <div class="flex flex-wrap gap-2">
                <form method="post" action="<?= $h($base . '/sessions') ?>" onsubmit="return confirm('Fermer toutes les sessions de jeu de ce compte ? Le jeu devra se reconnecter.');"><?= $csrf ?><button type="submit" class="<?= $btn ?>">Forcer la reconnexion (fermer les sessions)</button></form>
                <?php if ($atak['active_block'] !== null): ?>
                    <form method="post" action="<?= $h($base . '/debloquer') ?>"><?= $csrf ?><button type="submit" class="<?= $btn ?>">Lever les blocages ATAK</button></form>
                <?php endif; ?>
                <?php if ($steam !== null): ?>
                    <form method="post" action="<?= $h($base . '/delier') ?>" onsubmit="return confirm('Délier le Steam de ce compte ? Ses sessions de jeu seront fermées et il devra refaire la liaison.');"><?= $csrf ?><button type="submit" class="<?= $btnDanger ?>">Délier le Steam</button></form>
                <?php endif; ?>
            </div>
            <p class="text-xs text-slate-500">Toutes les actions sont tracées dans l’historique ATAK du compte et dans le journal d’audit.</p>
        </div>

        <!-- Journal, incidents, historique -->
        <div class="grid gap-4 lg:grid-cols-2">
            <div class="space-y-2">
                <h3 class="text-sm font-bold text-slate-900">Journal de l’appareil</h3>
                <?php if ($atak['logs'] === []): ?>
                    <p class="rounded-lg border border-dashed border-slate-300 p-3 text-sm text-slate-600">Aucune ligne remontée par le mod.</p>
                <?php else: ?>
                    <ul class="max-h-72 divide-y divide-slate-100 overflow-y-auto rounded-lg border border-slate-200 text-sm">
                        <?php foreach ($atak['logs'] as $l): ?>
                            <?php $lv = strtolower((string) ($l['level'] ?? '')); ?>
                            <li class="px-3 py-2">
                                <span class="font-mono text-xs text-slate-500"><?= $h($fmt($l['logged_at'])) ?></span>
                                <span class="ml-1 rounded px-1 text-[11px] font-bold <?= in_array($lv, ['error', 'fatal'], true) ? 'bg-rose-100 text-rose-700' : ($lv === 'warn' || $lv === 'warning' ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-600') ?>"><?= $h(strtoupper($lv ?: 'info')) ?></span>
                                <span class="ml-1 text-xs text-slate-500"><?= $h($l['channel'] ?? '') ?></span>
                                <p class="text-slate-800"><?= $h($l['message'] ?? '') ?></p>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
            <div class="space-y-4">
                <div class="space-y-2">
                    <h3 class="text-sm font-bold text-slate-900">Incidents remontés par le mod</h3>
                    <?php if ($atak['incidents'] === []): ?>
                        <p class="rounded-lg border border-dashed border-slate-300 p-3 text-sm text-slate-600">Aucun ticket in-game-reports pour ce Steam.</p>
                    <?php else: ?>
                        <ul class="divide-y divide-slate-100 rounded-lg border border-slate-200 text-sm">
                            <?php foreach ($atak['incidents'] as $i): ?>
                                <li class="px-3 py-2">
                                    <span class="font-bold">#<?= (int) $i['id'] ?></span>
                                    <span class="ml-1 rounded px-1 text-[11px] font-bold <?= ($i['workflow_status'] ?? '') === 'new' ? 'bg-rose-100 text-rose-700' : 'bg-slate-100 text-slate-600' ?>"><?= $h($i['workflow_status'] ?? '') ?></span>
                                    <span class="ml-1 text-xs text-slate-500"><?= $h($fmt($i['last_seen_at'])) ?> · ×<?= (int) $i['hit_count'] ?> · mod <?= $h($i['mod_version'] ?? '?') ?></span>
                                    <p class="truncate text-slate-800" title="<?= $h($i['message'] ?? '') ?>"><?= $h($i['message'] ?? '') ?></p>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <a class="text-xs font-semibold text-emerald-700 hover:underline" href="<?= $h(url('admin/atak-mod-reports') . '?q=' . rawurlencode((string) $steam)) ?>">Tous les rapports de ce Steam →</a>
                    <?php endif; ?>
                </div>
                <div class="space-y-2">
                    <h3 class="text-sm font-bold text-slate-900">Historique des actions ATAK</h3>
                    <?php if ($atak['actions'] === []): ?>
                        <p class="rounded-lg border border-dashed border-slate-300 p-3 text-sm text-slate-600">Aucune action d’administration.</p>
                    <?php else: ?>
                        <ul class="divide-y divide-slate-100 rounded-lg border border-slate-200 text-sm">
                            <?php foreach ($atak['actions'] as $a): ?>
                                <li class="px-3 py-2">
                                    <span class="font-semibold"><?= $h(AtakAdminControlRepository::ACTIONS[$a['action']] ?? $a['action']) ?></span>
                                    <span class="ml-1 text-xs text-slate-500"><?= $h($fmt($a['created_at'] ?? '', true)) ?> · <?= $h($a['actor_label'] ?? '') ?></span>
                                    <?php if (trim((string) ($a['detail'] ?? '')) !== ''): ?><p class="text-xs text-slate-600"><?= $h($a['detail']) ?></p><?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</section>
