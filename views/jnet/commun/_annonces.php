<?php
/**
 * Salon « Annonces & ordres du jour » : fil chronologique inversé des échanges de l'organisation, groupé par jour comme un
 * fil de discussion. Chaque message reprend l'échange JNET (auteur, unité émettrice, accusés de lecture « lu par x / N »,
 * pièce jointe) ; le bouton « Publier » n'apparaît que pour qui publie déjà au niveau de l'organisation.
 *
 * @var array<string, mixed> $view
 * @var string $salon
 * @var bool $canPin
 */

use App\Services\Jnet\JnetCommonRules;

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$type = (string) ($view['type'] ?? '');
$scopeAll = !empty($view['scopeAll']);
$kinds = is_array($view['kinds'] ?? null) ? $view['kinds'] : [];
$days = is_array($view['days'] ?? null) ? $view['days'] : [];
$visibleKinds = $scopeAll ? array_keys($kinds) : JnetCommonRules::ANNOUNCE_KINDS;
$filterUrl = static fn (string $kind, bool $all): string => url(JnetCommonRules::path('annonces', ['type' => $kind, 'portee' => $all ? 'tout' : '']));
?>
<div class="jc-body">
    <div class="jc-bar">
        <span class="jc-bar__l" aria-hidden="true">Type</span>
        <nav class="jc-chips" aria-label="Type d’échange">
            <a class="jc-chip<?= $type === '' ? ' is-on' : '' ?>" href="<?= $h($filterUrl('', $scopeAll)) ?>" data-jc-salon="annonces"<?= $type === '' ? ' aria-current="true"' : '' ?>>Tout</a>
            <?php foreach ($visibleKinds as $kind): ?>
                <a class="jc-chip<?= $type === $kind ? ' is-on' : '' ?>" href="<?= $h($filterUrl((string) $kind, $scopeAll)) ?>" data-jc-salon="annonces"<?= $type === $kind ? ' aria-current="true"' : '' ?>><?= $h((string) ($kinds[$kind] ?? $kind)) ?></a>
            <?php endforeach; ?>
        </nav>
        <span class="jc-bar__l" aria-hidden="true">Portée</span>
        <nav class="jc-chips" aria-label="Portée">
            <a class="jc-chip<?= !$scopeAll ? ' is-on' : '' ?>" href="<?= $h($filterUrl('', false)) ?>" data-jc-salon="annonces"<?= !$scopeAll ? ' aria-current="true"' : '' ?> title="Les échanges adressés à toute l’organisation ou émis par elle">Organisation</a>
            <a class="jc-chip<?= $scopeAll ? ' is-on' : '' ?>" href="<?= $h($filterUrl('', true)) ?>" data-jc-salon="annonces"<?= $scopeAll ? ' aria-current="true"' : '' ?> title="Tous les échanges des unités que vous êtes autorisé à voir">Toutes les unités</a>
        </nav>
    </div>

    <?php if (!empty($view['canPost'])): ?>
        <div class="jc-compose">
            <?php
            $space = ['id' => 0, 'label' => (string) ($cm['orgLabel'] ?? 'Organisation'), 'isOrg' => true];
            $postTargets = is_array($view['postTargets'] ?? null) ? $view['postTargets'] : [];
            $exchangeKinds = $kinds;
            $composeLabel = 'Publier pour toute l’organisation';
            require base_path('views/jnet/_exchange_composer.php');
            ?>
        </div>
    <?php endif; ?>

    <?php if (empty($view['ready'])): ?>
        <div class="jc-empty">
            <p><strong>Les échanges entre espaces ne sont pas encore activés.</strong></p>
            <p>Un administrateur doit appliquer les migrations.</p>
        </div>
    <?php elseif ($days === []): ?>
        <div class="jc-empty">
            <p><strong><?= $type !== '' ? 'Aucun échange de ce type.' : 'Aucune annonce pour le moment.' ?></strong></p>
            <p>Les ordres, informations et documents publiés pour l’organisation apparaîtront ici, du plus récent au plus ancien.</p>
        </div>
    <?php else: ?>
        <div class="jc-feed" data-jc-feed>
            <?php foreach ($days as $day): ?>
                <section class="jc-day">
                    <h2 class="jc-day__t"><span><?= $h((string) $day['label']) ?></span></h2>
                    <?php foreach ($day['items'] as $ex): ?>
                        <div class="jc-msg<?= (int) $ex['pinId'] > 0 ? ' is-pinned' : '' ?>">
                            <?php $exVariant = 'chat'; $exPerspective = ''; $exBack = url(JnetCommonRules::path('annonces')); require base_path('views/jnet/_exchange.php'); ?>
                            <div class="jc-msg__tools">
                                <?php $pinType = 'echange'; $pinTarget = (string) $ex['id']; $pinId = (int) $ex['pinId']; require base_path('views/jnet/commun/_pin.php'); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </section>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
