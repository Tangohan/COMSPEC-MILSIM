<?php
declare(strict_types=1);
ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/** @var list<array<string,mixed>> $campaigns */
$campaigns = is_array($campaigns ?? null) ? $campaigns : [];
$types = \App\Services\Sse\SseCampaignService::TYPES;
$hints = \App\Services\Sse\SseCampaignService::TYPE_HINTS;
$active = array_values(array_filter($campaigns, static fn (array $c): bool => ($c['status'] ?? '') !== 'archived'));
$archived = array_values(array_filter($campaigns, static fn (array $c): bool => ($c['status'] ?? '') === 'archived'));
$typeTone = static fn (string $t): string => match ($t) { 'temporaire' => 'badge--amber', 'recurrente' => 'badge--gray', default => '' };
?>
<link rel="stylesheet" href="<?= $h(asset_url('assets/css/sse_campaigns.css')) ?>">
<div class="page-heading">
    <div>
        <div class="page-heading-overline">Pilotage // Campagnes</div>
        <h1>Campagnes SSE</h1>
        <p>
            Les campagnes du mod COMSPEC SSE : personnages récurrents, organisations, ce que les joueurs
            en savent et registre des scellés. Elles arrivent du jeu (module Zeus « Campagne : envoyer à Athena »)
            ou d’un texte exporté, et repartent en jeu par « Campagne : récupérer depuis Athena ».
        </p>
    </div>
    <div class="cmp-heading-actions">
        <a class="btn btn--ghost" href="<?= $h(url('atak/sse/campagnes/partagees')) ?>">Catalogue partagé<?= !empty($sharedCount) ? ' (' . (int) $sharedCount . ')' : '' ?></a>
    </div>
</div>

<div class="cmp-types">
    <?php foreach ($types as $code => $label): ?>
        <div class="cmp-type">
            <span class="badge <?= $h($typeTone($code)) ?>"><?= $h($label) ?></span>
            <p><?= $h($hints[$code] ?? '') ?></p>
        </div>
    <?php endforeach; ?>
</div>

<section class="panel" style="margin-top:14px">
    <div class="panel-header">
        <div class="panel-title"><span class="panel-index">C.01</span> Campagnes de la communauté</div>
        <div class="panel-meta"><?= count($active) ?> active<?= count($active) > 1 ? 's' : '' ?></div>
    </div>
    <?php if ($active === []): ?>
        <div class="panel-body">
            <p class="muted">
                Aucune campagne pour l’instant. En jeu, posez le module Eden « Campagne SSE », sauvegardez,
                puis envoyez-la avec le module Zeus « Campagne : envoyer à Athena ». Sans liaison, exportez-la
                (« Campagne : exporter ») et collez le texte ci-dessous.
            </p>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Campagne</th>
                    <th>Type</th>
                    <th>Missions</th>
                    <th>Personnes</th>
                    <th>Organisations</th>
                    <th>Pièces</th>
                    <th>Mise à jour</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($active as $c): ?>
                    <tr>
                        <td>
                            <strong><?= $h($c['name'] ?: $c['campaign_key']) ?></strong>
                            <div class="cmp-sub"><?= $h($c['global_id'] ?? $c['campaign_key']) ?>
                                <?php if (!empty($c['shared'])): ?> · <span class="cmp-flag">partagée</span><?php endif; ?>
                                <?php if (!empty($c['source_campaign_id'])): ?> · <span class="cmp-flag">copie du catalogue</span><?php endif; ?>
                            </div>
                        </td>
                        <td><span class="badge <?= $h($typeTone((string) $c['type'])) ?>"><?= $h($types[$c['type']] ?? $c['type']) ?></span></td>
                        <td><?= (int) $c['missions'] ?></td>
                        <td><?= (int) $c['persons_count'] ?></td>
                        <td><?= (int) $c['orgs_count'] ?></td>
                        <td><?= (int) $c['pieces_count'] ?></td>
                        <td><?= $h($c['game_updated'] ?: ($c['updated_at'] ?? '')) ?></td>
                        <td><a class="btn-open" href="<?= $h(url('atak/sse/campagnes/' . (int) $c['id'])) ?>">Ouvrir</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php if ($archived !== []): ?>
<section class="panel" style="margin-top:14px">
    <div class="panel-header">
        <div class="panel-title"><span class="panel-index">C.02</span> Archivées</div>
        <div class="panel-meta"><?= count($archived) ?></div>
    </div>
    <div class="panel-body cmp-archived">
        <?php foreach ($archived as $c): ?>
            <a href="<?= $h(url('atak/sse/campagnes/' . (int) $c['id'])) ?>"><?= $h($c['name'] ?: $c['campaign_key']) ?> <span class="muted">(<?= $h($types[$c['type']] ?? $c['type']) ?>)</span></a>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php if ($canManage): ?>
<section class="panel" style="margin-top:14px">
    <div class="panel-header">
        <div class="panel-title"><span class="panel-index">C.03</span> Ajouter depuis un export</div>
        <div class="panel-meta">Texte du module Zeus « Campagne : exporter »</div>
    </div>
    <div class="panel-body">
        <form method="post" action="<?= $h(url('atak/sse/campagnes/importer')) ?>">
            <input type="hidden" name="_csrf_token" value="<?= $h(\App\Core\Csrf::token()) ?>">
            <label for="cmp-text">Texte exporté</label>
            <textarea id="cmp-text" name="text" rows="5" required placeholder='["COMSPEC_SSE_CAMPAIGN",1,["#M",[…]]]'></textarea>
            <label for="cmp-key">Identifiant (facultatif, sinon celui du jeu)</label>
            <input id="cmp-key" type="text" name="key" maxlength="64" placeholder="ex. ALTIS-2026">
            <p class="muted cmp-note">Si une campagne porte déjà cet identifiant, elle est mise à jour.</p>
            <button class="btn" type="submit">Ajouter la campagne</button>
        </form>
    </div>
</section>
<?php endif; ?>
<?php
$sseContent = ob_get_clean();
require __DIR__ . '/_layout.php';
