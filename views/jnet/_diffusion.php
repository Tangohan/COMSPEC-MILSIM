<?php
declare(strict_types=1);
/**
 * Bandeau de diffusion d'un espace : qui voit ce qui est publié ici, et le groupe date-heure.
 * @var string $diffusionText
 * @var string|null $jnetDtg
 */
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$dtg = trim((string) ($jnetDtg ?? ''));
?>
<p class="jn-diffusion" role="note">
    <span class="jn-diffusion__scope"><?= $h((string) ($diffusionText ?? '')) ?></span>
    <?php if ($dtg !== ''): ?>
        <time class="jn-diffusion__dtg" title="Groupe date-heure (heure Zulu)"><?= $h($dtg) ?></time>
    <?php endif; ?>
</p>
