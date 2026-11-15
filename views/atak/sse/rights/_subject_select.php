<?php
declare(strict_types=1);
use App\Support\SseAccessCatalog as C;

/**
 * Liste de choix d'un bénéficiaire : niveaux d'accès, unités, unités et sous-unités (de la communauté seulement).
 *
 * @var array{roles: list<array{id: int, label: string}>, units: list<array{id: int, label: string}>} $options
 * @var string $selectName
 * @var string $selectId
 * @var string $selected valeur « type:id » à présélectionner
 */
$h = $h ?? static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$selected = (string) ($selected ?? '');
?>
<select class="fx-in" name="<?= $h($selectName) ?>" id="<?= $h($selectId) ?>" required>
    <option value="">Choisir un bénéficiaire</option>
    <?php if ($options['roles'] !== []): ?>
        <optgroup label="Niveaux d’accès">
            <?php foreach ($options['roles'] as $o): $ref = C::subjectRef(C::SUBJECT_ROLE, $o['id']); ?>
                <option value="<?= $h($ref) ?>"<?= $selected === $ref ? ' selected' : '' ?>><?= $h($o['label']) ?></option>
            <?php endforeach; ?>
        </optgroup>
    <?php endif; ?>
    <?php if ($options['units'] !== []): ?>
        <optgroup label="Unités (membres directs)">
            <?php foreach ($options['units'] as $o): $ref = C::subjectRef(C::SUBJECT_UNIT, $o['id']); ?>
                <option value="<?= $h($ref) ?>"<?= $selected === $ref ? ' selected' : '' ?>><?= $h($o['label']) ?></option>
            <?php endforeach; ?>
        </optgroup>
        <optgroup label="Unités et sous-unités">
            <?php foreach ($options['units'] as $o): $ref = C::subjectRef(C::SUBJECT_UNIT_TREE, $o['id']); ?>
                <option value="<?= $h($ref) ?>"<?= $selected === $ref ? ' selected' : '' ?>><?= $h($o['label']) ?> et sous-unités</option>
            <?php endforeach; ?>
        </optgroup>
    <?php endif; ?>
</select>
