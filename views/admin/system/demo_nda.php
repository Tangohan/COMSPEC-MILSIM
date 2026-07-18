<?php
declare(strict_types=1);

/** @var bool $gateEnabled */
/** @var int $ttlHours */
/** @var string $accessCode */
/** @var bool $accessCodeFromEnv */
/** @var list<string> $envBypassIps */
/** @var list<string> $adminBypassIps */
/** @var string $clientIp */
/** @var list<array<string, mixed>> $visits */

$statusLabel = static function (string $status): string {
    return match ($status) {
        'pending' => 'En attente du code',
        'granted' => 'Accès ouvert',
        'expired' => 'Fermé',
        default => 'Inconnu',
    };
};
$accessCodeFromEnv = !empty($accessCodeFromEnv);
?>
<div class="max-w-5xl mx-auto px-4 sm:px-6 py-10 space-y-10">
    <header>
        <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 mb-1">Administration plateforme</p>
        <h1 class="text-2xl font-black text-slate-900">Accès démonstration</h1>
        <p class="mt-2 text-sm text-slate-600 leading-relaxed max-w-3xl">
            Portail d’engagement affiché à la première visite : texte de confidentialité, code d’accès, une connexion = une fenêtre limitée,
            puis fermeture définitive. Votre adresse peut être exemptée pour piloter le site sans passer par l’engagement.
        </p>
        <a href="<?= url('admin') ?>" class="inline-block mt-4 text-sm text-slate-600 hover:underline">Retour au centre opérateur site</a>
    </header>

    <?php $f = \App\Core\Session::getFlash('error'); $s = \App\Core\Session::getFlash('success'); ?>
    <?php if ($f): ?><p class="text-red-600 text-sm"><?= htmlspecialchars((string) $f, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
    <?php if ($s): ?><p class="text-emerald-700 text-sm"><?= htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>

    <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm space-y-3">
        <h2 class="text-sm font-bold text-slate-800">État du dispositif</h2>
        <?php if ($gateEnabled): ?>
            <p class="text-sm text-emerald-800 bg-emerald-50 border border-emerald-100 rounded-lg px-3 py-2">
                Actif (variable d’environnement). Durée : <strong><?= (int) $ttlHours ?> h</strong> pour saisir le code après la première visite, puis <strong><?= (int) $ttlHours ?> h</strong> d’accès après validation.
            </p>
        <?php else: ?>
            <p class="text-sm text-amber-900 bg-amber-50 border border-amber-100 rounded-lg px-3 py-2">
                Désactivé. Pour l’activer, définissez <span class="font-mono text-xs">DEMO_NDA_GATE_ENABLED=true</span> et
                <span class="font-mono text-xs">DEMO_NDA_GATE_ACCESS_CODE</span> dans l’environnement. Ajoutez votre adresse dans
                <span class="font-mono text-xs">DEMO_NDA_GATE_BYPASS_IPS</span> pour garder l’admin sans passer par le portail.
            </p>
        <?php endif; ?>
        <p class="text-xs text-slate-500">Adresse observée pour cette session d’administration : <span class="font-mono"><?= htmlspecialchars($clientIp, ENT_QUOTES, 'UTF-8') ?></span></p>
    </section>

    <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="text-sm font-bold text-slate-800 mb-3">Code d’accès à communiquer</h2>
        <?php if ($accessCode !== ''): ?>
            <p class="text-3xl font-black tracking-[0.2em] text-slate-900 font-mono"><?= htmlspecialchars($accessCode, ENT_QUOTES, 'UTF-8') ?></p>
            <?php if ($accessCodeFromEnv): ?>
                <p class="mt-3 text-sm text-slate-600">
                    Issu de <span class="font-mono text-xs">DEMO_NDA_GATE_ACCESS_CODE</span> dans le fichier d’environnement.
                    Pour le changer, modifiez cette valeur puis rechargez l’application.
                </p>
            <?php else: ?>
                <form method="post" action="<?= url('admin/system/demo-nda/regenerate-code') ?>" class="mt-4">
                    <?= \App\Core\Csrf::field() ?>
                    <button type="submit" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                        Générer un nouveau code
                    </button>
                </form>
                <p class="mt-3 text-xs text-slate-500">Aucun code dans le .env : celui-ci est stocké côté plateforme. Préférez le définir dans <span class="font-mono">DEMO_NDA_GATE_ACCESS_CODE</span>.</p>
            <?php endif; ?>
        <?php else: ?>
            <p class="text-sm text-amber-900 bg-amber-50 border border-amber-100 rounded-lg px-3 py-2">
                Aucun code défini. Ajoutez <span class="font-mono text-xs">DEMO_NDA_GATE_ACCESS_CODE=XXXX-XXXX</span> dans le fichier d’environnement.
            </p>
            <form method="post" action="<?= url('admin/system/demo-nda/regenerate-code') ?>" class="mt-4">
                <?= \App\Core\Csrf::field() ?>
                <button type="submit" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                    Générer un code (secours)
                </button>
            </form>
        <?php endif; ?>
    </section>

    <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm space-y-6">
        <div>
            <h2 class="text-sm font-bold text-slate-800">Adresses exemptées</h2>
            <p class="mt-1 text-sm text-slate-600">Ces connexions ne voient pas le portail d’engagement et ne consomment pas de visite.</p>
        </div>

        <?php if ($envBypassIps !== []): ?>
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500 mb-2">Via l’environnement</p>
                <ul class="flex flex-wrap gap-2">
                    <?php foreach ($envBypassIps as $ip): ?>
                        <li class="rounded-md bg-slate-100 px-2.5 py-1 text-xs font-mono text-slate-700"><?= htmlspecialchars($ip, ENT_QUOTES, 'UTF-8') ?></li>
                    <?php endforeach; ?>
                </ul>
                <p class="mt-2 text-xs text-slate-500">Modifiables uniquement dans le fichier d’environnement.</p>
            </div>
        <?php endif; ?>

        <div>
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500 mb-2">Gérées ici</p>
            <?php if ($adminBypassIps === []): ?>
                <p class="text-sm text-slate-500">Aucune adresse pour le moment.</p>
            <?php else: ?>
                <ul class="space-y-2">
                    <?php foreach ($adminBypassIps as $ip): ?>
                        <li class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-slate-100 bg-slate-50 px-3 py-2">
                            <span class="font-mono text-sm text-slate-800"><?= htmlspecialchars($ip, ENT_QUOTES, 'UTF-8') ?></span>
                            <form method="post" action="<?= url('admin/system/demo-nda/remove-bypass') ?>">
                                <?= \App\Core\Csrf::field() ?>
                                <input type="hidden" name="bypass_ip" value="<?= htmlspecialchars($ip, ENT_QUOTES, 'UTF-8') ?>">
                                <button type="submit" class="text-xs font-semibold text-red-700 hover:underline">Retirer</button>
                            </form>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <div class="grid gap-3 sm:grid-cols-2">
            <form method="post" action="<?= url('admin/system/demo-nda/add-bypass') ?>" class="space-y-2">
                <?= \App\Core\Csrf::field() ?>
                <label class="block text-xs text-slate-500">Ajouter une adresse</label>
                <input type="text" name="bypass_ip" required placeholder="ex. 203.0.113.10" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <button type="submit" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-50">Enregistrer</button>
            </form>
            <form method="post" action="<?= url('admin/system/demo-nda/add-my-ip') ?>" class="flex flex-col justify-end">
                <?= \App\Core\Csrf::field() ?>
                <button type="submit" class="rounded-lg bg-slate-900 px-3 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                    Exempter mon adresse actuelle
                </button>
            </form>
        </div>
    </section>

    <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="text-sm font-bold text-slate-800 mb-4">Visites récentes</h2>
        <?php if ($visits === []): ?>
            <p class="text-sm text-slate-500">Aucune visite enregistrée.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="text-xs uppercase tracking-wider text-slate-500 border-b border-slate-100">
                        <tr>
                            <th class="py-2 pr-3 font-semibold">Adresse</th>
                            <th class="py-2 pr-3 font-semibold">État</th>
                            <th class="py-2 pr-3 font-semibold">Première visite</th>
                            <th class="py-2 pr-3 font-semibold">Limite saisie / session</th>
                            <th class="py-2 font-semibold"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($visits as $row): ?>
                            <?php
                            $st = (string) ($row['status'] ?? '');
                            $first = (string) ($row['first_seen_at'] ?? '');
                            $claim = (string) ($row['claim_expires_at'] ?? '');
                            $sess = (string) ($row['session_expires_at'] ?? '');
                            $limit = $st === 'granted' && $sess !== '' ? $sess : $claim;
                            ?>
                            <tr>
                                <td class="py-2.5 pr-3 font-mono text-xs text-slate-800"><?= htmlspecialchars((string) ($row['ip_address'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="py-2.5 pr-3 text-slate-700"><?= htmlspecialchars($statusLabel($st), ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="py-2.5 pr-3 text-slate-600 whitespace-nowrap"><?= htmlspecialchars($first, ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="py-2.5 pr-3 text-slate-600 whitespace-nowrap"><?= htmlspecialchars($limit, ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="py-2.5 text-right">
                                    <form method="post" action="<?= url('admin/system/demo-nda/reset-visit') ?>" class="inline">
                                        <?= \App\Core\Csrf::field() ?>
                                        <input type="hidden" name="visit_id" value="<?= (int) ($row['id'] ?? 0) ?>">
                                        <button type="submit" class="text-xs font-semibold text-slate-700 hover:underline">Rouvrir</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>
