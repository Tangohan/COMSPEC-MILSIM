<?php
declare(strict_types=1);

/**
 * Kits par rôle — liste. Chaque kit est publié comme tenue d’arsenal dans la collection « Kits par rôle ».
 *
 * @var bool $kitsReady
 * @var list<array<string, mixed>> $kits
 * @var array<int, string> $roleOptions
 * @var int $catalogueSize
 * @var string $csrf
 */

$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$kits = is_array($kits ?? null) ? $kits : [];
$roleOptions = is_array($roleOptions ?? null) ? $roleOptions : [];
$catalogueSize = (int) ($catalogueSize ?? 0);
$csrf = (string) ($csrf ?? '');

$groups = [];
foreach ($kits as $k) {
    $key = $k['job_role_id'] !== null ? (string) ($k['role_name'] ?? 'Rôle supprimé') : 'Tous les rôles';
    $groups[$key][] = $k;
}
$fmtDate = static function (string $raw): string {
    $ts = $raw !== '' ? strtotime($raw) : false;

    return $ts ? date('d/m/Y', $ts) : '—';
};
?>
<div class="bo-ge" data-ge>
    <div class="bo-ge__toolbar">
        <a class="ath-btn ath-btn--solid" href="<?= $h(url('back-office/kits-roles/nouveau')) ?>">Nouveau kit</a>
        <a class="ath-btn" href="<?= $h(url('back-office/equipement-joueurs')) ?>">Équipement des joueurs</a>
        <span class="bo-ge__spacer"></span>
        <span class="bo-ge__window"><?= (int) $catalogueSize ?> objet(s) observés en jeu disponibles pour composer les kits</span>
    </div>

    <?php if (empty($kitsReady)): ?>
    <section class="bo-ge-note bo-ge-note--warn" role="status">
        <div>
            <p class="bo-ge-note__title">Mise à jour à appliquer</p>
            <p class="bo-ge-note__text">La table des kits n’existe pas encore sur ce serveur. Lancez <code>php run-migrations.php</code>, puis rechargez la page.</p>
        </div>
    </section>
    <?php endif; ?>

    <section class="bo-ge-note">
        <div>
            <p class="bo-ge-note__title">Comment ça marche</p>
            <p class="bo-ge-note__text">Un kit est composé à partir de l’équipement que les joueurs portent réellement en jeu. À l’enregistrement, il devient une tenue de la collection « Kits par rôle » : chaque joueur la retrouve dans l’arsenal (Tenues Athena, onglet Communauté) et peut l’enfiler. Les membres voient aussi les kits de leur fonction sur la page Équipement.</p>
        </div>
    </section>

    <?php if ($kits === []): ?>
    <p class="bo-ge-empty">Aucun kit pour le moment. <?= $roleOptions === [] ? 'Créez d’abord les fonctions de la communauté (page « Fonctions et emplois ») pour proposer un kit par rôle, ou créez un kit pour tous les rôles.' : 'Commencez par le kit d’un rôle clé : médecin, chef de groupe, tireur d’élite…' ?></p>
    <?php endif; ?>

    <?php foreach ($groups as $roleName => $rows): ?>
    <section class="bo-ge-section" aria-label="<?= $h($roleName) ?>">
        <header class="bo-ge-section__head">
            <div>
                <h2 class="bo-ge-section__title" translate="no"><?= $h($roleName) ?></h2>
                <p class="bo-ge-section__lead"><?= count($rows) ?> kit(s)</p>
            </div>
        </header>
        <div class="bo-ge-kits">
            <?php foreach ($rows as $k): ?>
            <article class="bo-ge-kit">
                <header class="bo-ge-kit__head">
                    <h3 class="bo-ge-kit__name" translate="no"><a href="<?= $h(url('back-office/kits-roles/' . (int) $k['id'])) ?>"><?= $h((string) $k['name']) ?></a></h3>
                    <?php if ($k['wardrobe_id'] !== null): ?>
                    <span class="bo-ge-status bo-ge-status--ok">Dans l’arsenal</span>
                    <?php else: ?>
                    <span class="bo-ge-status bo-ge-status--warn">Non publié</span>
                    <?php endif; ?>
                </header>
                <?php if ((string) $k['description'] !== ''): ?>
                <p class="bo-ge-kit__desc"><?= $h((string) $k['description']) ?></p>
                <?php endif; ?>
                <?php
                $kitIcons = [];
                foreach (\App\Support\ArsenalLoadoutItems::classes((string) ($k['payload_text'] ?? '')) as $kc) {
                    $u = \App\Repositories\GameItemIconRepository::urlFor($kc);
                    if ($u !== null && !in_array($u, $kitIcons, true)) {
                        $kitIcons[$kc] = $u;
                    }
                    if (count($kitIcons) >= 8) {
                        break;
                    }
                }
                ?>
                <?php if ($kitIcons !== []): ?>
                <div class="bo-ge-kit__icons" aria-hidden="true">
                    <?php foreach ($kitIcons as $kc => $u): ?><img src="<?= $h($u) ?>" alt="" title="<?= $h((string) $kc) ?>" loading="lazy" decoding="async" width="40" height="40"><?php endforeach; ?>
                </div>
                <?php endif; ?>
                <p class="bo-ge-kit__meta"><?= (int) ($k['item_count'] ?? 0) ?> objet(s) · modifié le <?= $h($fmtDate((string) $k['updated_at'])) ?></p>
                <div class="bo-ge-kit__mods">
                    <span class="bo-ge-kit__label">Mods requis</span>
                    <?php if ($k['required_mods'] === []): ?>
                    <span class="bo-ge-chip bo-ge-chip--soft">Arma 3 seul</span>
                    <?php else: ?>
                        <?php foreach ($k['required_mods'] as $mod): ?>
                        <span class="bo-ge-chip" translate="no"><?= $h((string) $mod) ?></span>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <footer class="bo-ge-kit__actions">
                    <a class="ath-btn ath-btn--sm" href="<?= $h(url('back-office/kits-roles/' . (int) $k['id'])) ?>">Modifier</a>
                    <?php if ($k['wardrobe_id'] !== null): ?>
                    <a class="ath-btn ath-btn--sm" href="<?= $h(url('equipment/tenues/' . (int) $k['wardrobe_id'])) ?>">Voir la tenue</a>
                    <?php endif; ?>
                    <form method="post" action="<?= $h(url('back-office/kits-roles/' . (int) $k['id'] . '/publier')) ?>" class="bo-ge-inline">
                        <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
                        <button type="submit" class="ath-btn ath-btn--sm"><?= $k['wardrobe_id'] !== null ? 'Republier' : 'Publier' ?></button>
                    </form>
                    <form method="post" action="<?= $h(url('back-office/kits-roles/' . (int) $k['id'] . '/supprimer')) ?>" class="bo-ge-inline" data-ge-confirm="Supprimer ce kit ? Sa tenue sera aussi retirée de l’arsenal en jeu.">
                        <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
                        <button type="submit" class="ath-btn ath-btn--sm ath-btn--danger">Supprimer</button>
                    </form>
                </footer>
            </article>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endforeach; ?>
</div>
<script src="<?= $h(asset_url('assets/js/back-office-game-equipment.js')) ?>" defer></script>
