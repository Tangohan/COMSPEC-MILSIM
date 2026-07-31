<?php
declare(strict_types=1);
ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/** @var list<array<string,mixed>> $codes */
/** @var list<array<string,mixed>> $cases */
/** @var string|null $issuedPlain */
$activeCodes = 0;
foreach ($codes as $c) {
    if (!empty($c['active'])) {
        $activeCodes++;
    }
}
?>
<div class="breadcrumb">
    Athena / SSE / Renseignement /
    <strong>Codes d’accès</strong>
</div>

<div class="page-heading">
    <div>
        <div class="page-heading-overline">Commandement // Habilitation</div>
        <h1>Codes d’accès temporaires</h1>
        <p>
            Réservé au commandement. Communiquez chaque code par un canal sécurisé.
            Le code en clair n’est affiché qu’une seule fois à la génération.
        </p>
    </div>
    <div class="page-reference">
        <strong>Vue // Gestion des codes</strong>
        Réf. ATH-SSE-ACCES
    </div>
</div>

<?php if (!empty($issuedPlain)): ?>
    <div class="security-notice">
        <div class="security-notice-code">CODE</div>
        <div>
            <strong>Code à transmettre immédiatement</strong>
            <span>Notez-le maintenant — il ne sera plus réaffiché.</span>
        </div>
    </div>
    <div class="code-reveal"><?= $h($issuedPlain) ?></div>
<?php endif; ?>

<div class="metrics-grid">
    <div class="metric">
        <div class="metric-label">Codes émis</div>
        <div class="metric-value"><?= $h(str_pad((string) count($codes), 3, '0', STR_PAD_LEFT)) ?></div>
        <div class="metric-detail">Historique</div>
    </div>
    <div class="metric">
        <div class="metric-label">Actifs</div>
        <div class="metric-value"><?= $h(str_pad((string) $activeCodes, 3, '0', STR_PAD_LEFT)) ?></div>
        <div class="metric-detail">Encore valides</div>
    </div>
    <div class="metric">
        <div class="metric-label">Dossiers</div>
        <div class="metric-value"><?= $h(str_pad((string) count($cases), 3, '0', STR_PAD_LEFT)) ?></div>
        <div class="metric-detail">Cibles possibles</div>
    </div>
    <div class="metric">
        <div class="metric-label">Horodatage</div>
        <div class="metric-value"><?= $h(date('H:i')) ?></div>
        <div class="metric-detail">Heure locale</div>
    </div>
</div>

<section class="panel">
    <div class="panel-header">
        <div class="panel-title">
            <span class="panel-index">04.01</span>
            Délivrer un code
        </div>
    </div>
    <div class="panel-body">
        <form method="post" action="<?= $h(url('atak/sse/acces')) ?>">
            <?= \App\Core\Csrf::field() ?>
            <label for="label">Libellé</label>
            <input id="label" name="label" type="text" required value="Accès temporaire" maxlength="120">

            <label for="grant_type">Type d’accès</label>
            <select id="grant_type" name="grant_type">
                <option value="member">Membre habilité (compte + rôle)</option>
                <option value="guest">Invité (code seul)</option>
            </select>

            <label for="clearance_level">Habilitation de lecture accordée</label>
            <select id="clearance_level" name="clearance_level">
                <?php foreach (\App\Repositories\SseCaseRepository::CLASSIFICATION_LABELS as $ck => $clabel): ?>
                    <option value="<?= $h($ck) ?>" <?= $ck === 'interne' ? 'selected' : '' ?>><?= $h($clabel) ?></option>
                <?php endforeach; ?>
            </select>
            <p class="sse-note">
                Jusqu’où le porteur du code lit en clair sur les écrans expurgés. Au plus
                bas, il ne voit ni identité, ni lieu, ni source. N’accordez que ce que la
                personne doit voir : c’est un plafond, il ne se lève pas en cours de session.
            </p>

            <div class="grid-2">
                <div>
                    <label for="ttl_hours">Validité du code</label>
                    <select id="ttl_hours" name="ttl_hours">
                        <?php foreach ([1, 2, 4, 8, 12, 24, 48, 72] as $hval): ?>
                            <option value="<?= $hval ?>" <?= $hval === 4 ? 'selected' : '' ?>><?= $hval ?> h</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="session_ttl_minutes">Durée de session après saisie</label>
                    <select id="session_ttl_minutes" name="session_ttl_minutes">
                        <option value="60">1 heure</option>
                        <option value="120">2 heures</option>
                        <option value="240" selected>4 heures</option>
                        <option value="480">8 heures</option>
                        <option value="1440">24 heures</option>
                    </select>
                </div>
            </div>

            <label for="max_uses">Nombre d’utilisations</label>
            <select id="max_uses" name="max_uses">
                <?php for ($i = 1; $i <= 10; $i++): ?>
                    <option value="<?= $i ?>"><?= $i ?></option>
                <?php endfor; ?>
            </select>

            <label for="case_id">Limiter à un dossier (optionnel)</label>
            <select id="case_id" name="case_id">
                <option value="">Tous les dossiers</option>
                <?php foreach ($cases as $c): ?>
                    <option value="<?= (int) $c['id'] ?>"><?= $h($c['reference_code'] . ' — ' . $c['title']) ?></option>
                <?php endforeach; ?>
            </select>

            <button class="btn" type="submit">Générer le code</button>
        </form>
    </div>
</section>

<section class="panel">
    <div class="panel-header">
        <div class="panel-title">
            <span class="panel-index">04.02</span>
            Codes émis
        </div>
        <div class="panel-meta">Historique // révocation</div>
    </div>
    <?php if ($codes === []): ?>
        <div class="empty-state">
            <div class="empty-state-inner">
                <div class="empty-symbol">—</div>
                <strong>Aucun code</strong>
                <p>Aucun code délivré pour le moment.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Libellé</th>
                    <th>Type</th>
                    <th>Indice</th>
                    <th>Usages</th>
                    <th>Expire</th>
                    <th>État</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($codes as $c): ?>
                    <tr>
                        <td><span class="record-name"><?= $h($c['label']) ?></span></td>
                        <td><?= $h($c['grant_type_label']) ?></td>
                        <td class="record-id"><?= $h($c['code_hint']) ?></td>
                        <td class="record-id"><?= (int) $c['uses_count'] ?> / <?= (int) $c['max_uses'] ?></td>
                        <td class="record-id"><?= $h($c['expires_at'] ?? '') ?></td>
                        <td>
                            <span class="badge<?= empty($c['active']) ? ' badge--gray' : '' ?>">
                                <?= $h($c['status_label']) ?>
                            </span>
                        </td>
                        <td>
                            <?php if (!empty($c['active'])): ?>
                                <form method="post" action="<?= $h(url('atak/sse/acces/' . $c['id'] . '/revoquer')) ?>" style="display:inline">
                                    <?= \App\Core\Csrf::field() ?>
                                    <button class="btn btn--danger" type="submit">Révoquer</button>
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
<?php
$sseContent = ob_get_clean();
require __DIR__ . '/_layout.php';
