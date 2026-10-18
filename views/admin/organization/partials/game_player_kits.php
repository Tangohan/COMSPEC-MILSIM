<?php
declare(strict_types=1);

/**
 * Équipement des joueurs › « Kits portés » : dernier kit réellement porté en jeu par chaque joueur
 * (App\Services\GameEquipment\PlayerKitService), enregistrable en tenue d’arsenal sans saisie.
 * Inclus depuis game_equipment.php ($h, $int, $icon, $fmtDate, $geDays viennent de la vue parente).
 * Styles : back-office-player-kits.css ; filtre : back-office-player-kits.js.
 *
 * @var list<array<string, mixed>> $gePlayerKits
 * @var string $csrf
 */

$gePlayerKits = is_array($gePlayerKits ?? null) ? $gePlayerKits : [];
$pkCsrf = (string) ($csrf ?? '');
$pkWhen = static function (string $raw): string {
    $ts = $raw !== '' ? strtotime($raw) : false;

    return $ts ? date('d/m/Y H:i', $ts) : '—';
};
?>
<link rel="stylesheet" href="<?= $h(asset_url('assets/css/back-office-player-kits.css')) ?>">
<section class="bo-ge-section bo-pk" id="kits-portes" aria-labelledby="ge-kits-t" data-pk>
    <header class="bo-ge-section__head">
        <span class="bo-ge-section__icon"><?= $icon('kit') ?></span>
        <div>
            <h2 class="bo-ge-section__title" id="ge-kits-t">Kits portés par les joueurs <span class="bo-ge-count"><?= count($gePlayerKits) ?></span></h2>
            <p class="bo-ge-section__lead">Dernier équipement complet de chaque joueur remonté par le mod. Un kit s’enregistre en tenue d’arsenal en un clic : il apparaît alors sur la page Équipement et se récupère dans l’arsenal en jeu.</p>
        </div>
    </header>

    <?php if ($gePlayerKits === []): ?>
    <p class="bo-ge-empty">Aucun kit porté remonté sur les <?= (int) $geDays ?> derniers jours. Le mod l’envoie à la connexion du joueur, puis à chaque changement de tenue.</p>
    <?php else: ?>
    <div class="bo-ge-tablebar">
        <label class="bo-ge-search">
            <?= $icon('search') ?>
            <input type="search" placeholder="Filtrer par joueur, rôle, arme ou objet" aria-label="Filtrer les kits portés" data-pk-filter>
        </label>
    </div>
    <ul class="bo-pk__list">
        <?php foreach ($gePlayerKits as $kit): ?>
        <?php
            $saved = $kit['wardrobe_id'] !== null ? (int) $kit['wardrobe_id'] : 0;
            $meta = array_values(array_filter([(string) $kit['role'], (string) $kit['group_name']], static fn (string $v): bool => $v !== ''));
        ?>
        <li class="bo-pk__item" data-pk-row>
            <details class="bo-pk__card">
                <summary class="bo-pk__head">
                    <span class="bo-pk__who">
                        <span class="bo-pk__name" translate="no"><?= $h((string) $kit['player_name']) ?></span>
                        <span class="bo-pk__meta"><?= $meta !== [] ? '<span translate="no">' . $h(implode(' · ', $meta)) . '</span> · ' : '' ?>vu le <?= $h($pkWhen((string) $kit['last_seen_at'])) ?></span>
                    </span>
                    <span class="bo-pk__weapon" translate="no"><?= $h((string) $kit['primary_weapon'] !== '' ? (string) $kit['primary_weapon'] : 'Sans arme principale') ?></span>
                    <span class="bo-pk__count"><?= $h($int((int) $kit['item_count'])) ?> objet(s)</span>
                    <?php if ($saved > 0): ?><span class="bo-ge-tag bo-pk__saved">Tenue</span><?php endif; ?>
                </summary>
                <div class="bo-pk__body">
                    <div class="bo-pk__sections">
                        <?php foreach ($kit['sections'] as $sec): ?>
                        <div class="bo-pk__sec">
                            <h3 class="bo-pk__sec-title"><?= $h((string) $sec['title']) ?></h3>
                            <ul class="bo-pk__objs">
                                <?php foreach ($sec['items'] as $it): ?>
                                <li>
                                    <span class="bo-pk__obj" translate="no" title="<?= $h((string) ($it['class'] ?? '')) ?>"><?= $h((string) $it['name']) ?></span>
                                    <?php if ((int) $it['qty'] > 1): ?><span class="bo-pk__qty">×<?= (int) $it['qty'] ?></span><?php endif; ?>
                                    <?php if ((int) ($it['players'] ?? 0) > 1): ?><span class="bo-pk__players" title="Joueurs qui l’ont porté sur la période"><?= (int) $it['players'] ?> joueurs</span><?php endif; ?>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="bo-pk__actions">
                        <form method="post" action="<?= $h(url('back-office/equipement-joueurs/kits/' . rawurlencode((string) $kit['steam_id']) . '/tenue')) ?>">
                            <input type="hidden" name="_csrf_token" value="<?= $h($pkCsrf) ?>">
                            <input type="hidden" name="jours" value="<?= (int) $geDays ?>">
                            <button type="submit" class="ath-btn<?= $saved > 0 ? '' : ' ath-btn--solid' ?>"><?= $saved > 0 ? 'Mettre à jour la tenue' : 'Enregistrer comme tenue' ?></button>
                        </form>
                        <?php if ($saved > 0): ?>
                        <a class="ath-btn" href="<?= $h(url('equipment/tenues/' . $saved)) ?>">Voir la tenue</a>
                        <?php endif; ?>
                        <?php if (!empty($kit['mission_name'])): ?>
                        <span class="bo-pk__mission">Mission <span translate="no"><?= $h((string) $kit['mission_name']) ?></span></span>
                        <?php endif; ?>
                    </div>
                </div>
            </details>
        </li>
        <?php endforeach; ?>
    </ul>
    <p class="bo-ge-empty bo-ge-empty--sm" data-pk-none hidden>Aucun kit ne correspond.</p>
    <?php endif; ?>
</section>
<script src="<?= $h(asset_url('assets/js/back-office-player-kits.js')) ?>" defer></script>
