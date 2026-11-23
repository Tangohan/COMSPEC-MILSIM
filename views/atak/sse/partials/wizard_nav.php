<?php
declare(strict_types=1);
/**
 * Barre d'actions de l'assistant.
 * @var string $wzCancelUrl
 * @var list<array{label:string, when?:string}> $wzSubmits bouton(s) de validation (un seul visible à la fois selon data-wz-when)
 */
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<div class="wz-nav">
    <button type="button" class="btn btn--ghost" data-wz-prev hidden>Retour</button>
    <a class="btn btn--ghost" href="<?= $h($wzCancelUrl) ?>">Annuler</a>
    <span class="wz-saved" data-wz-saved hidden>Brouillon enregistré dans ce navigateur</span>
    <span class="wz-nav__spacer"></span>
    <button type="button" class="btn" data-wz-next>Continuer</button>
    <span data-wz-submit-wrap>
        <?php foreach ($wzSubmits as $sub): ?>
            <button class="btn" type="submit"<?= !empty($sub['when']) ? ' data-wz-when="' . $h($sub['when']) . '"' : '' ?>><?= $h($sub['label']) ?></button>
        <?php endforeach; ?>
    </span>
</div>
