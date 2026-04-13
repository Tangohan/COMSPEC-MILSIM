<?php
/** @var list<array<string, mixed>> $integration_keys */
/** @var string|null $new_integration_key_plain */
/** @var list<string> $available_scopes */
$integration_keys = $integration_keys ?? [];
$new_integration_key_plain = $new_integration_key_plain ?? null;
$available_scopes = $available_scopes ?? ['events:read'];

/**
 * @param mixed $raw
 * @return list<string>
 */
$decodeScopes = static function ($raw): array {
    if (!is_string($raw) || trim($raw) === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return [];
    }

    return array_values(array_filter(array_map(static fn ($scope): string => trim((string) $scope), $decoded), static fn (string $scope): bool => $scope !== ''));
};

$scopeLabelFr = static function (string $scope): string {
    return match ($scope) {
        'events:read' => 'Lecture des événements',
        default => $scope,
    };
};
?>
<div class="mx-auto max-w-6xl space-y-8 px-4 py-8">
    <header class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h1 class="text-2xl font-black text-slate-900">Centre d’intégrations</h1>
        <p class="mt-2 text-sm text-slate-600">Gérez les jetons d’accès pour vos outils externes : quotas journaliers, renouvellement des secrets et suivi de consommation.</p>
        <div class="mt-4 grid gap-3 text-xs text-slate-500 md:grid-cols-3">
            <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">Chaque outil présente un <strong class="text-slate-800">jeton confidentiel</strong> à chaque requête.</div>
            <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">Le flux d’événements est décrit dans la <strong class="text-slate-800">notice d’intégration</strong> fournie avec la plateforme.</div>
            <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">La référence détaillée des échanges accompagne le <strong class="text-slate-800">dossier de déploiement</strong>.</div>
        </div>
    </header>

    <?php if ($new_integration_key_plain): ?>
    <div class="rounded-xl border border-amber-200 bg-amber-50 p-4">
        <p class="text-sm font-bold text-amber-900">Copiez ce jeton maintenant : il ne sera plus affiché en clair.</p>
        <code class="mt-2 block break-all rounded bg-white p-3 text-xs text-slate-800"><?= htmlspecialchars($new_integration_key_plain, ENT_QUOTES, 'UTF-8') ?></code>
        <p class="mt-2 text-xs text-amber-900">Conseil sécurité: stockez-la dans un gestionnaire de secrets et jamais en clair dans le code source.</p>
    </div>
    <?php endif; ?>

    <form method="post" action="<?= htmlspecialchars(url('back-office/integrations/api-keys'), ENT_QUOTES, 'UTF-8') ?>" class="space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <?= \App\Core\Csrf::field() ?>
        <div class="grid gap-4 md:grid-cols-2">
            <div>
                <label for="integ_name" class="block text-xs font-bold uppercase tracking-wider text-slate-500">Nom du service</label>
                <input type="text" id="integ_name" name="name" maxlength="120" required placeholder="Ex. Site vitrine, outil logistique…" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
            </div>
            <div>
                <label for="quota_per_day" class="block text-xs font-bold uppercase tracking-wider text-slate-500">Quota / jour</label>
                <input type="number" id="quota_per_day" name="quota_per_day" min="100" max="500000" value="10000" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
            </div>
        </div>
        <fieldset>
            <legend class="block text-xs font-bold uppercase tracking-wider text-slate-500">Types de données autorisés pour ce jeton</legend>
            <div class="mt-2 grid gap-2 md:grid-cols-2">
                <?php foreach ($available_scopes as $scope): ?>
                    <label class="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700">
                        <input type="checkbox" name="scopes[]" value="<?= htmlspecialchars($scope, ENT_QUOTES, 'UTF-8') ?>" checked class="rounded border-slate-300 text-slate-900 focus:ring-slate-700">
                        <span><?= htmlspecialchars($scopeLabelFr($scope), ENT_QUOTES, 'UTF-8') ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </fieldset>
        <button type="submit" class="rounded-lg bg-slate-900 px-4 py-2 text-xs font-black uppercase tracking-wider text-white hover:bg-slate-800">Créer un jeton d’accès</button>
    </form>

    <section>
        <h2 class="text-sm font-black uppercase tracking-wider text-slate-800">Jetons existants</h2>
        <?php if ($integration_keys === []): ?>
            <p class="mt-3 text-sm text-slate-500">Aucun jeton pour l’instant.</p>
        <?php else: ?>
            <ul class="mt-4 space-y-4">
                <?php foreach ($integration_keys as $k): ?>
                <?php
                    $quota = max(1, (int) ($k['quota_per_day'] ?? 10000));
                    $today = max(0, (int) ($k['today_request_count'] ?? 0));
                    $ratio = min(100, (int) round(($today / $quota) * 100));
                    $isRevoked = !empty($k['revoked_at']);
                    $scopes = $decodeScopes($k['scopes_json'] ?? '');
                ?>
                <li class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="font-bold text-slate-900"><?= htmlspecialchars((string) ($k['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                            <p class="text-xs text-slate-500">Préfixe <?= htmlspecialchars((string) ($k['key_prefix'] ?? ''), ENT_QUOTES, 'UTF-8') ?>…</p>
                            <p class="text-xs text-slate-500">Créée le <?= htmlspecialchars((string) ($k['created_at'] ?? '—'), ENT_QUOTES, 'UTF-8') ?> · Dernière utilisation <?= htmlspecialchars((string) ($k['last_used_at'] ?? 'Jamais'), ENT_QUOTES, 'UTF-8') ?></p>
                        </div>
                        <?php if ($isRevoked): ?>
                            <span class="rounded-full bg-rose-100 px-3 py-1 text-xs font-bold text-rose-700">Révoquée</span>
                        <?php else: ?>
                            <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-bold text-emerald-700">Active</span>
                        <?php endif; ?>
                    </div>

                    <div class="mt-4 rounded-lg border border-slate-200 bg-slate-50 p-3">
                        <div class="flex items-center justify-between text-xs text-slate-600">
                            <span>Consommation du jour</span>
                            <strong class="text-slate-900"><?= $today ?> / <?= $quota ?></strong>
                        </div>
                        <div class="mt-2 h-2 overflow-hidden rounded bg-slate-200">
                            <div class="h-full bg-slate-900" style="width: <?= $ratio ?>%;"></div>
                        </div>
                    </div>

                    <div class="mt-3 flex flex-wrap gap-2">
                        <?php foreach ($scopes as $scope): ?>
                            <span class="rounded-full border border-slate-300 px-2 py-1 text-[11px] font-semibold text-slate-700"><?= htmlspecialchars($scopeLabelFr($scope), ENT_QUOTES, 'UTF-8') ?></span>
                        <?php endforeach; ?>
                    </div>

                    <?php if (!$isRevoked): ?>
                    <form method="post" action="<?= htmlspecialchars(url('back-office/integrations/api-keys/' . (int) ($k['id'] ?? 0) . '/update'), ENT_QUOTES, 'UTF-8') ?>" class="mt-4 grid gap-3 md:grid-cols-3">
                        <?= \App\Core\Csrf::field() ?>
                        <input type="text" name="name" maxlength="120" required value="<?= htmlspecialchars((string) ($k['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
                        <input type="number" name="quota_per_day" min="100" max="500000" value="<?= (int) ($k['quota_per_day'] ?? 10000) ?>" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
                        <button type="submit" class="rounded-lg border border-slate-300 px-4 py-2 text-xs font-black uppercase tracking-wider text-slate-700 hover:bg-slate-100">Enregistrer</button>
                        <div class="md:col-span-3 grid gap-2 md:grid-cols-2">
                            <?php foreach ($available_scopes as $scope): ?>
                                <label class="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700">
                                    <input type="checkbox" name="scopes[]" value="<?= htmlspecialchars($scope, ENT_QUOTES, 'UTF-8') ?>" <?= in_array($scope, $scopes, true) ? 'checked' : '' ?> class="rounded border-slate-300 text-slate-900 focus:ring-slate-700">
                                    <span><?= htmlspecialchars($scopeLabelFr($scope), ENT_QUOTES, 'UTF-8') ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </form>

                    <div class="mt-4 flex flex-wrap gap-3">
                        <form method="post" action="<?= htmlspecialchars(url('back-office/integrations/api-keys/' . (int) ($k['id'] ?? 0) . '/rotate'), ENT_QUOTES, 'UTF-8') ?>" onsubmit="return confirm('Renouveler ce jeton ? Un nouveau secret sera généré et l’ancien cessera de fonctionner immédiatement.');">
                            <?= \App\Core\Csrf::field() ?>
                            <button type="submit" class="rounded-lg border border-amber-300 px-3 py-2 text-xs font-bold uppercase tracking-wider text-amber-700 hover:bg-amber-50">Rotation</button>
                        </form>
                        <form method="post" action="<?= htmlspecialchars(url('back-office/integrations/api-keys/' . (int) ($k['id'] ?? 0) . '/revoke'), ENT_QUOTES, 'UTF-8') ?>" onsubmit="return confirm('Révoquer ce jeton ? Les outils qui l’utilisent cesseront de fonctionner.');">
                            <?= \App\Core\Csrf::field() ?>
                            <button type="submit" class="rounded-lg border border-rose-300 px-3 py-2 text-xs font-bold uppercase tracking-wider text-rose-700 hover:bg-rose-50">Révoquer</button>
                        </form>
                    </div>
                    <?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</div>
