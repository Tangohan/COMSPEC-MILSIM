<?php

declare(strict_types=1);

/**
 * Passerelle ATAK inter-équipes — page dédiée (code unique + validation bilatérale).
 *
 * @var list<array<string, mixed>> $gateway_items
 * @var bool $gateway_can_manage
 * @var bool $gateway_schema_ready
 * @var list<array{id:int,label:string}> $gateway_maps
 * @var string|null $flash_success
 * @var string|null $flash_error
 */

$items = is_array($gateway_items ?? null) ? $gateway_items : [];
$canManage = !empty($gateway_can_manage);
$schemaReady = !empty($gateway_schema_ready);
$maps = is_array($gateway_maps ?? null) ? $gateway_maps : [];
$flashOk = trim((string) ($flash_success ?? ''));
$flashErr = trim((string) ($flash_error ?? ''));

$statusClass = static function (string $status): string {
    return match ($status) {
        'active' => 'bg-emerald-50 text-emerald-800 border-emerald-200',
        'pending_validation' => 'bg-amber-50 text-amber-900 border-amber-200',
        'open' => 'bg-sky-50 text-sky-900 border-sky-200',
        'revoked', 'expired' => 'bg-slate-100 text-slate-600 border-slate-200',
        default => 'bg-slate-50 text-slate-700 border-slate-200',
    };
};
?>
<div class="bg-slate-50 pb-16 sm:pb-24">
    <div class="border-b border-slate-200 bg-white">
        <div class="mx-auto max-w-4xl px-4 py-10 sm:px-6 lg:px-8">
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-emerald-700">Inter-équipes</p>
            <h1 class="mt-2 text-2xl font-black tracking-tight text-slate-900 sm:text-3xl">Passerelle carte ATAK</h1>
            <p class="mt-3 max-w-2xl text-sm leading-relaxed text-slate-600">
                Reliez temporairement la carte de votre communauté à celle d’une autre unité.
                Un code unique est échangé, puis <strong>les deux côtés doivent valider</strong> avant tout partage de positions.
            </p>
            <div class="mt-5 flex flex-wrap gap-3">
                <a href="<?= htmlspecialchars(url('atak'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-800 hover:bg-slate-50">Ouvrir la carte</a>
                <a href="<?= htmlspecialchars(url('federation'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-800 hover:bg-slate-50">Fédération</a>
                <a href="<?= htmlspecialchars(url('back-office/cooperation/missions'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-800 hover:bg-slate-50">Coopérations</a>
            </div>
        </div>
    </div>

    <div class="mx-auto max-w-4xl space-y-6 px-4 py-10 sm:px-6 lg:px-8">
        <?php if ($flashOk !== ''): ?>
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-900" role="status"><?= htmlspecialchars($flashOk, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <?php if ($flashErr !== ''): ?>
            <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-900" role="alert"><?= htmlspecialchars($flashErr, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <?php if (!$schemaReady): ?>
            <section class="rounded-2xl border border-amber-200 bg-amber-50 p-6 shadow-sm">
                <h2 class="text-lg font-bold text-amber-950">Mise à jour requise</h2>
                <p class="mt-2 text-sm text-amber-900">
                    La passerelle n’est pas encore activée sur ce serveur. Un responsable technique doit lancer les migrations, puis réessayez.
                </p>
            </section>
        <?php else: ?>

        <section class="grid gap-6 lg:grid-cols-2">
            <article class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-lg font-bold text-slate-900">Créer une passerelle</h2>
                <p class="mt-1 text-sm text-slate-600">Générez un code à transmettre à l’autre unité. Vous validerez ensuite leur rattachement.</p>
                <?php if ($canManage): ?>
                <form method="post" action="<?= htmlspecialchars(url('atak/passerelle/creer'), ENT_QUOTES, 'UTF-8') ?>" class="mt-5 space-y-4">
                    <?= \App\Core\Csrf::field() ?>
                    <div>
                        <label for="gw-label" class="block text-sm font-medium text-slate-700">Intitulé (facultatif)</label>
                        <input id="gw-label" type="text" name="label" maxlength="160" placeholder="Ex. Exercise week-end Bleu/Rouge"
                            class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm">
                    </div>
                    <div>
                        <label for="gw-host-map" class="block text-sm font-medium text-slate-700">Carte de référence</label>
                        <select id="gw-host-map" name="host_map_id" class="<?= htmlspecialchars(bo_select_class('mt-1'), ENT_QUOTES, 'UTF-8') ?>">
                            <?php foreach ($maps as $m): ?>
                                <option value="<?= (int) ($m['id'] ?? 0) ?>"><?= htmlspecialchars((string) ($m['label'] ?? 'Carte'), ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                            <?php if ($maps === []): ?>
                                <option value="1">Carte principale</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <fieldset class="space-y-2">
                        <legend class="text-sm font-medium text-slate-700">Éléments partagés</legend>
                        <label class="flex items-start gap-2 text-sm text-slate-700">
                            <input type="checkbox" name="share_units" value="1" checked class="mt-0.5 rounded border-slate-300 text-emerald-700">
                            <span>Positions des opérateurs (suivi terrain)</span>
                        </label>
                        <label class="flex items-start gap-2 text-sm text-slate-700">
                            <input type="checkbox" name="share_markers" value="1" class="mt-0.5 rounded border-slate-300 text-emerald-700">
                            <span>Marqueurs tactiques</span>
                        </label>
                    </fieldset>
                    <button type="submit" class="inline-flex rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-bold text-white hover:bg-emerald-700">
                        Générer un code
                    </button>
                </form>
                <?php else: ?>
                <p class="mt-4 rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm text-slate-600">
                    Seuls les responsables de la communauté peuvent créer une passerelle.
                </p>
                <?php endif; ?>
            </article>

            <article class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-lg font-bold text-slate-900">Rejoindre avec un code</h2>
                <p class="mt-1 text-sm text-slate-600">Saisissez le code reçu de l’autre communauté, puis validez votre côté. L’activation n’a lieu qu’après les deux confirmations.</p>
                <?php if ($canManage): ?>
                <form method="post" action="<?= htmlspecialchars(url('atak/passerelle/rejoindre'), ENT_QUOTES, 'UTF-8') ?>" class="mt-5 space-y-4">
                    <?= \App\Core\Csrf::field() ?>
                    <div>
                        <label for="gw-code" class="block text-sm font-medium text-slate-700">Code de liaison</label>
                        <input id="gw-code" type="text" name="join_code" required maxlength="16" autocomplete="off"
                            placeholder="Ex. K7M2NPQ4"
                            class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 font-mono text-sm uppercase tracking-widest shadow-sm">
                    </div>
                    <div>
                        <label for="gw-partner-map" class="block text-sm font-medium text-slate-700">Votre carte</label>
                        <select id="gw-partner-map" name="partner_map_id" class="<?= htmlspecialchars(bo_select_class('mt-1'), ENT_QUOTES, 'UTF-8') ?>">
                            <?php foreach ($maps as $m): ?>
                                <option value="<?= (int) ($m['id'] ?? 0) ?>"><?= htmlspecialchars((string) ($m['label'] ?? 'Carte'), ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                            <?php if ($maps === []): ?>
                                <option value="1">Carte principale</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <button type="submit" class="inline-flex rounded-xl bg-emerald-700 px-4 py-2.5 text-sm font-bold text-white hover:bg-emerald-800">
                        Rattacher ma communauté
                    </button>
                </form>
                <?php else: ?>
                <p class="mt-4 rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm text-slate-600">
                    Seuls les responsables peuvent rattacher un code reçu.
                </p>
                <?php endif; ?>
            </article>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-bold text-slate-900">Passerelles de votre communauté</h2>
            <p class="mt-1 text-sm text-slate-600">Suivi des codes, validations et liaisons actives.</p>

            <?php if ($items === []): ?>
                <p class="mt-5 rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4 py-6 text-center text-sm text-slate-600">
                    Aucune passerelle pour le moment.
                </p>
            <?php else: ?>
                <ul class="mt-5 space-y-4">
                    <?php foreach ($items as $gw):
                        if (!is_array($gw)) {
                            continue;
                        }
                        $st = (string) ($gw['status'] ?? '');
                        $code = (string) ($gw['join_code'] ?? '');
                        $peer = trim((string) ($gw['peer_name'] ?? ''));
                        $gid = (int) ($gw['id'] ?? 0);
                        ?>
                    <li class="rounded-xl border border-slate-200 p-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="font-bold text-slate-900">
                                    <?= htmlspecialchars(($gw['label'] ?? '') !== '' ? (string) $gw['label'] : 'Passerelle sans intitulé', ENT_QUOTES, 'UTF-8') ?>
                                </p>
                                <p class="mt-1 text-sm text-slate-600">
                                    <?= $peer !== ''
                                        ? 'Avec ' . htmlspecialchars($peer, ENT_QUOTES, 'UTF-8')
                                        : 'En attente de l’autre communauté' ?>
                                    · Code <span class="font-mono font-semibold tracking-wider text-slate-800"><?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?></span>
                                </p>
                                <p class="mt-2 text-xs text-slate-500">
                                    Partage :
                                    <?= !empty($gw['share_units']) ? 'positions' : '' ?>
                                    <?= !empty($gw['share_units']) && !empty($gw['share_markers']) ? ' · ' : '' ?>
                                    <?= !empty($gw['share_markers']) ? 'marqueurs' : '' ?>
                                    <?= empty($gw['share_units']) && empty($gw['share_markers']) ? 'aucun' : '' ?>
                                </p>
                                <p class="mt-1 text-xs text-slate-500">
                                    Votre validation : <?= !empty($gw['viewer_accepted']) ? 'oui' : 'en attente' ?>
                                    · Autre côté : <?= !empty($gw['peer_accepted']) ? 'oui' : 'en attente' ?>
                                </p>
                            </div>
                            <span class="inline-flex rounded-full border px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide <?= htmlspecialchars($statusClass($st), ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars((string) ($gw['status_label'] ?? $st), ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </div>
                        <?php if ($canManage && (!empty($gw['can_accept']) || !empty($gw['can_revoke']))): ?>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <?php if (!empty($gw['can_accept'])): ?>
                            <form method="post" action="<?= htmlspecialchars(url('atak/passerelle/' . $gid . '/valider'), ENT_QUOTES, 'UTF-8') ?>">
                                <?= \App\Core\Csrf::field() ?>
                                <button type="submit" class="rounded-lg bg-emerald-700 px-3 py-2 text-xs font-bold text-white hover:bg-emerald-800">Valider notre côté</button>
                            </form>
                            <?php endif; ?>
                            <?php if (!empty($gw['can_revoke'])): ?>
                            <form method="post" action="<?= htmlspecialchars(url('atak/passerelle/' . $gid . '/annuler'), ENT_QUOTES, 'UTF-8') ?>"
                                onsubmit="return confirm('Couper cette passerelle maintenant ?');">
                                <?= \App\Core\Csrf::field() ?>
                                <button type="submit" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">Annuler</button>
                            </form>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-slate-100/80 p-5 text-sm text-slate-600">
            <p class="font-semibold text-slate-800">Rappel</p>
            <ul class="mt-2 list-disc space-y-1 pl-5">
                <li>Le code expire s’il n’est pas utilisé à temps.</li>
                <li>Aucune clé d’accès n’est échangée : le partage passe uniquement par cette passerelle validée.</li>
                <li>Chaque communauté peut couper le lien à tout moment.</li>
            </ul>
        </section>
        <?php endif; ?>
    </div>
</div>
