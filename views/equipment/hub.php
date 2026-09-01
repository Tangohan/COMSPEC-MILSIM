<?php
declare(strict_types=1);
$wardrobes = $wardrobes ?? [];
$mineWardrobes = $mineWardrobes ?? [];
$collections = $collections ?? [];
$equipmentClasses = $equipmentClasses ?? [];
$migrationMissing = !empty($migrationMissing);
$csrfToken = (string) ($csrfToken ?? \App\Core\Csrf::token());
$flashOk = trim((string) ($flash_success ?? ''));
$flashErr = trim((string) ($flash_error ?? ''));
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$visibilityLabel = static function (string $v): string {
    return match ($v) {
        'unit' => 'Unité',
        'tenant' => 'Communauté',
        default => 'Personnel',
    };
};
?>
<div class="eq-hub">
    <header class="eq-hub__hero">
        <div class="eq-hub__hero-inner">
            <p class="eq-hub__kicker">Communauté</p>
            <h1>Équipement</h1>
            <p class="eq-hub__lead">Les tenues envoyées depuis l’arsenal, regroupées en collections. Ajoutez une photo de présentation pour que tout le monde reconnaisse le kit d’un coup d’œil.</p>
        </div>
    </header>

    <div class="eq-hub__body">
        <?php if ($flashOk !== ''): ?><p class="eq-hub__flash eq-hub__flash--ok"><?= $h($flashOk) ?></p><?php endif; ?>
        <?php if ($flashErr !== ''): ?><p class="eq-hub__flash eq-hub__flash--err"><?= $h($flashErr) ?></p><?php endif; ?>

        <?php if ($migrationMissing): ?>
        <p class="eq-hub__empty">Cette page n’est pas encore prête sur cette instance. Demandez à l’administration d’appliquer la mise à jour, puis rechargez.</p>
        <?php else: ?>

        <section class="eq-hub__panel" id="nouvelle-collection">
            <h2>Nouvelle collection</h2>
            <form method="post" action="<?= $h(url('equipment/collections')) ?>" enctype="multipart/form-data" class="eq-hub__form">
                <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                <label>Nom
                    <input type="text" name="name" required maxlength="120" placeholder="Assaut nocturne">
                </label>
                <label>Présentation
                    <textarea name="description" rows="2" maxlength="500" placeholder="Quand porter ce kit, pour qui, contraintes."></textarea>
                </label>
                <label>Qui peut s’en servir
                    <select name="visibility" class="bo-select">
                        <option value="personal">Moi seulement</option>
                        <option value="unit">Mon unité</option>
                        <option value="tenant">Toute la communauté</option>
                    </select>
                </label>
                <label>Photo de présentation
                    <input type="file" name="cover" accept="image/jpeg,image/png,image/webp">
                    <span class="eq-hub__hint">JPG, PNG ou WebP, 8 Mo maximum.</span>
                </label>
                <?php if ($mineWardrobes !== []): ?>
                <fieldset>
                    <legend>Tenues à inclure</legend>
                    <div class="eq-hub__checks">
                        <?php foreach ($mineWardrobes as $w): ?>
                        <label class="eq-hub__check">
                            <input type="checkbox" name="wardrobe_ids[]" value="<?= (int) $w['id'] ?>">
                            <?= $h($w['name'] ?? 'Tenue') ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </fieldset>
                <?php endif; ?>
                <button type="submit" class="eq-hub__btn">Créer la collection</button>
            </form>
            <?php if ($mineWardrobes === []): ?>
            <p class="eq-hub__hint">En jeu, ouvrez l’arsenal, puis le bandeau Athena en haut de l’écran, et envoyez vos tenues. Elles apparaîtront ici pour les ranger dans une collection.</p>
            <?php endif; ?>
        </section>

        <section>
            <h2>Collections</h2>
            <?php if ($collections === []): ?>
            <p class="eq-hub__empty">Aucune collection pour le moment. Créez-en une ci-dessus.</p>
            <?php else: ?>
            <ul class="eq-hub__grid">
                <?php foreach ($collections as $c): ?>
                <li>
                    <a class="eq-hub__card" href="<?= $h(url('equipment/collections/' . (int) $c['id'])) ?>">
                        <span class="eq-hub__cover">
                            <?php if (!empty($c['cover_url'])): ?>
                            <img src="<?= $h($c['cover_url']) ?>" alt="">
                            <?php else: ?>
                            <span class="eq-hub__cover-ph">Sans photo</span>
                            <?php endif; ?>
                        </span>
                        <span class="eq-hub__card-body">
                            <strong><?= $h($c['name'] ?? '') ?></strong>
                            <span><?= $h($visibilityLabel((string) ($c['visibility'] ?? 'personal'))) ?> · <?= (int) ($c['wardrobe_count'] ?? 0) ?> tenue(s)</span>
                        </span>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </section>

        <section>
            <h2>Toutes les tenues</h2>
            <?php if ($wardrobes === []): ?>
            <p class="eq-hub__empty">Aucune tenue n’a encore été envoyée depuis l’arsenal.</p>
            <?php else: ?>
            <ul class="eq-hub__grid">
                <?php foreach ($wardrobes as $w): ?>
                <li>
                    <a class="eq-hub__card" href="<?= $h(url('equipment/tenues/' . (int) $w['id'])) ?>">
                        <span class="eq-hub__cover">
                            <?php if (!empty($w['cover_url'])): ?>
                            <img src="<?= $h($w['cover_url']) ?>" alt="">
                            <?php else: ?>
                            <span class="eq-hub__cover-ph">Sans photo</span>
                            <?php endif; ?>
                        </span>
                        <span class="eq-hub__card-body">
                            <strong><?= $h($w['name'] ?? '') ?><?php if (!empty($w['is_favorite'])): ?> ★<?php endif; ?></strong>
                            <span><?= $h(($w['owner_label'] ?? '') !== '' ? $w['owner_label'] : 'Membre') ?>
                                · <?= $h($w['collection_name'] ?? 'Sans collection') ?></span>
                        </span>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </section>

        <?php if ($equipmentClasses !== []): ?>
        <section>
            <h2>Fiches matériel</h2>
            <p class="eq-hub__hint">Référentiels et documents associés, en complément des tenues.</p>
            <ul class="eq-hub__docs">
                <?php foreach ($equipmentClasses as $c): ?>
                <li><a href="<?= $h(url('equipment/' . ($c['slug'] ?? ''))) ?>"><?= $h($c['name'] ?? '') ?></a></li>
                <?php endforeach; ?>
            </ul>
        </section>
        <?php endif; ?>

        <?php endif; ?>
    </div>
</div>
