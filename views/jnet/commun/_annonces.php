<?php
/**
 * Salon « Annonces & ordres du jour » : fil chronologique inversé des échanges de l'organisation, groupé par jour comme un
 * fil de discussion. Chaque annonce reprend l'échange JNET (auteur, unité émettrice, accusés de lecture « lu par x / N »,
 * pièce jointe) ; le bouton « Publier » n'apparaît que pour qui publie déjà au niveau de l'organisation.
 *
 * @var array<string, mixed> $view
 * @var string $salon
 * @var bool $canPin
 */

use App\Services\Jnet\JnetCommonRules;

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$type = (string) ($view['type'] ?? '');
$scope = (string) ($view['scope'] ?? '');
$kinds = is_array($view['kinds'] ?? null) ? $view['kinds'] : [];
$counts = is_array($view['counts'] ?? null) ? $view['counts'] : [];
$days = is_array($view['days'] ?? null) ? $view['days'] : [];
$visibleKinds = $scope !== '' ? array_keys($kinds) : JnetCommonRules::ANNOUNCE_KINDS;
$typeUrl = static fn (string $kind): string => url(JnetCommonRules::path('annonces', ['type' => $kind, 'portee' => $scope]));
$scopeUrl = static fn (string $portee): string => url(JnetCommonRules::path('annonces', ['portee' => $portee]));
$scopes = ['' => ['Organisation', 'Les échanges adressés à toute l’organisation ou émis par elle'], 'tout' => ['Toutes les unités', 'Tous les échanges des unités que vous êtes autorisé à voir'], 'unite' => ['Mon unité', 'Les échanges émis ou reçus par votre unité, son échelon supérieur ou ses sous-unités']];
?>
<div class="jc-body">
    <?php if (!empty($view['canPost'])): ?>
        <div class="jc-compose">
            <?php
            $space = ['id' => 0, 'label' => (string) ($cm['orgLabel'] ?? 'Organisation'), 'isOrg' => true];
            $postTargets = is_array($view['postTargets'] ?? null) ? $view['postTargets'] : [];
            $exchangeKinds = $kinds;
            $composeLabel = 'Publier une annonce, un ordre ou un document…';
            require base_path('views/jnet/_exchange_composer.php');
            ?>
        </div>
    <?php endif; ?>

    <div class="jc-bar">
        <span class="jc-bar__l" aria-hidden="true">Type</span>
        <nav class="jc-chips" aria-label="Type d’échange">
            <a class="jc-chip<?= $type === '' ? ' is-on' : '' ?>" href="<?= $h($typeUrl('')) ?>" data-jc-salon="annonces"<?= $type === '' ? ' aria-current="true"' : '' ?>>Tout<small><?= (int) ($view['total'] ?? 0) ?></small></a>
            <?php foreach ($visibleKinds as $kind): ?>
                <a class="jc-chip<?= $type === $kind ? ' is-on' : '' ?>" href="<?= $h($typeUrl((string) $kind)) ?>" data-jc-salon="annonces"<?= $type === $kind ? ' aria-current="true"' : '' ?>><?= $h((string) ($kinds[$kind] ?? $kind)) ?><small><?= (int) ($counts[$kind] ?? 0) ?></small></a>
            <?php endforeach; ?>
        </nav>
        <span class="jc-bar__l" aria-hidden="true">Portée</span>
        <nav class="jc-chips" aria-label="Portée">
            <?php foreach ($scopes as $key => [$label, $hint]): ?>
                <a class="jc-chip<?= $scope === $key ? ' is-on' : '' ?>" href="<?= $h($scopeUrl($key)) ?>" data-jc-salon="annonces"<?= $scope === $key ? ' aria-current="true"' : '' ?> title="<?= $h($hint) ?>"><?= $h($label) ?></a>
            <?php endforeach; ?>
        </nav>
    </div>

    <?php if (empty($view['ready'])): ?>
        <div class="jc-empty">
            <p><strong>Les échanges entre espaces ne sont pas encore activés.</strong></p>
            <p>Un administrateur doit appliquer les migrations.</p>
        </div>
    <?php elseif ($days === []): ?>
        <div class="jc-empty">
            <p><strong><?= $type !== '' || $scope !== '' ? 'Aucune annonce pour ce filtre.' : 'Aucune annonce pour le moment.' ?></strong></p>
            <p>Les ordres, informations et documents publiés apparaîtront ici, du plus récent au plus ancien.</p>
        </div>
    <?php else: ?>
        <div class="jc-feed" data-jc-feed>
            <?php foreach ($days as $day): ?>
                <section class="jc-day">
                    <h3 class="jc-day__t"><span><?= $h((string) $day['label']) ?></span></h3>
                    <?php foreach ($day['items'] as $ex): ?>
                        <?php require base_path('views/jnet/commun/_announce.php'); ?>
                    <?php endforeach; ?>
                </section>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
