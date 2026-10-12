<?php
declare(strict_types=1);
ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/** @var list<array<string,mixed>> $catalog */
$catalog = is_array($catalog ?? null) ? $catalog : [];
/** @var array<int, array<string,mixed>> $mine copies déjà faites, par id source */
$mine = is_array($mine ?? null) ? $mine : [];
$types = \App\Services\Sse\SseCampaignService::TYPES;
$scopes = ['structure' => 'Structure seule', 'complete' => 'Campagne complète'];
?>
<link rel="stylesheet" href="<?= $h(asset_url('assets/css/sse_campaigns.css')) ?>">
<div class="page-heading">
    <div>
        <div class="page-heading-overline"><a href="<?= $h(url('atak/sse/campagnes')) ?>">Campagnes</a> // Catalogue partagé</div>
        <h1>Campagnes partagées</h1>
        <p>
            Les campagnes que d’autres communautés ont choisi de partager. Une copie garde la graine biométrique
            de l’original : ses personnages ont la même biométrie et le même identifiant global partout.
            Chaque communauté décide de ce qu’elle publie ; rien n’est partagé par défaut.
        </p>
    </div>
</div>

<section class="panel">
    <div class="panel-header">
        <div class="panel-title"><span class="panel-index">K.01</span> Catalogue</div>
        <div class="panel-meta"><?= count($catalog) ?> campagne<?= count($catalog) > 1 ? 's' : '' ?></div>
    </div>
    <div class="panel-body">
        <?php if ($catalog === []): ?>
            <p class="muted">Aucune autre communauté ne partage de campagne pour l’instant. Pour partager les vôtres, ouvrez une campagne et réglez « Partager avec les autres communautés ».</p>
        <?php else: ?>
            <div class="cmp-catalog">
                <?php foreach ($catalog as $c): ?>
                    <?php $copy = $mine[(int) $c['id']] ?? null; ?>
                    <article class="cmp-card">
                        <header>
                            <strong><?= $h($c['name'] ?: $c['campaign_key']) ?></strong>
                            <span class="cmp-sub"><?= $h($c['tenant_name'] ?? 'Communauté') ?> · <?= $h(strtoupper((string) (($c['tenant_code'] ?? '') ?: ($c['tenant_slug'] ?? '')))) ?>:<?= $h($c['campaign_key']) ?></span>
                        </header>
                        <p class="cmp-card-tags">
                            <span class="badge <?= $c['type'] === 'temporaire' ? 'badge--amber' : ($c['type'] === 'recurrente' ? 'badge--gray' : '') ?>"><?= $h($types[$c['type']] ?? $c['type']) ?></span>
                            <span class="badge badge--gray"><?= $h($scopes[$c['share_scope']] ?? $c['share_scope']) ?></span>
                        </p>
                        <?php if (trim((string) $c['share_note']) !== ''): ?><p><?= nl2br($h($c['share_note'])) ?></p><?php endif; ?>
                        <p class="cmp-sub"><?= (int) $c['persons_count'] ?> personnage(s) · <?= (int) $c['orgs_count'] ?> organisation(s) · <?= (int) $c['missions'] ?> mission(s) · mise à jour <?= $h($c['updated_at'] ?? $c['created_at']) ?></p>
                        <?php if ($canManage): ?>
                            <form method="post" action="<?= $h(url('atak/sse/campagnes/partagees/' . (int) $c['id'] . '/copier')) ?>">
                                <input type="hidden" name="_csrf_token" value="<?= $h(\App\Core\Csrf::token()) ?>">
                                <?php if ($copy === null): ?>
                                    <button class="btn" type="submit">Copier dans ma communauté</button>
                                <?php elseif (($copy['source_hash'] ?? '') !== ($c['payload_hash'] ?? '')): ?>
                                    <button class="btn" type="submit">Mettre ma copie à jour</button>
                                    <a class="btn btn--ghost" href="<?= $h(url('atak/sse/campagnes/' . (int) $copy['id'])) ?>">Ouvrir ma copie</a>
                                <?php else: ?>
                                    <a class="btn btn--ghost" href="<?= $h(url('atak/sse/campagnes/' . (int) $copy['id'])) ?>">Ma copie est à jour</a>
                                <?php endif; ?>
                            </form>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php
$sseContent = ob_get_clean();
require __DIR__ . '/_layout.php';
