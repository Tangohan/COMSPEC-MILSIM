<?php
declare(strict_types=1);

/**
 * Avatar utilisateur (photo réelle ou initiale de repli).
 *
 * @var string|null $avatarSrc URL publique (déjà résolue) ou null
 * @var string $initials
 * @var string $class Classes du conteneur
 * @var string|null $imgClass Classes de l’image
 * @var string $alt Texte alternatif
 */
$avatarSrc = isset($avatarSrc) ? trim((string) $avatarSrc) : '';
$initials = (string) ($initials ?? '?');
$class = (string) ($class ?? '');
$imgClass = (string) ($imgClass ?? 'h-full w-full object-cover');
$alt = (string) ($alt ?? '');
?>
<span class="<?= htmlspecialchars($class, ENT_QUOTES, 'UTF-8') ?>">
<?php if ($avatarSrc !== ''): ?>
    <img
        src="<?= htmlspecialchars($avatarSrc, ENT_QUOTES, 'UTF-8') ?>"
        alt="<?= htmlspecialchars($alt, ENT_QUOTES, 'UTF-8') ?>"
        class="<?= htmlspecialchars($imgClass, ENT_QUOTES, 'UTF-8') ?>"
        loading="lazy"
        decoding="async"
    >
<?php else: ?>
    <?= htmlspecialchars($initials, ENT_QUOTES, 'UTF-8') ?>
<?php endif; ?>
</span>
