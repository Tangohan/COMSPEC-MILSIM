<?php
declare(strict_types=1);

/**
 * Aperçu fidèle du post Discord de la vitrine des communautés (DiscordShowcaseService::buildPayload) :
 * titre du post forum, tags, auteur « Athena Communities », embed (couleur, titre, texte, champs, logo, bannière).
 * Toujours en thème sombre, comme Discord. Liens inactifs dans l'aperçu.
 *
 * @var array<string, mixed>|null $dsPayload
 * @var list<string> $dsTagLabels libellés des tags posés (facultatif)
 */
$payload = is_array($dsPayload ?? null) ? $dsPayload : null;
if ($payload === null) {
    return;
}
$embed = is_array($payload['embeds'][0] ?? null) ? $payload['embeds'][0] : [];
$e = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
/** Markdown Discord minimal : [texte](lien), **gras**, échappements \*. */
$md = static function (string $text) use ($e): string {
    $out = $e($text);
    $out = (string) preg_replace('/\[([^\]]+)\]\((https?:\/\/[^)\s]+)\)/', '<span class="dsc-msg__link" title="$2">$1</span>', $out);
    $out = (string) preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $out);
    $out = (string) preg_replace('/\\\\([*_~`|>\\\\])/', '$1', $out);

    return nl2br($out, false);
};
$color = sprintf('#%06x', (int) ($embed['color'] ?? 0x3b82f6));
$threadName = (string) ($payload['thread_name'] ?? '');
$tagLabels = is_array($dsTagLabels ?? null) ? $dsTagLabels : [];
$fields = is_array($embed['fields'] ?? null) ? $embed['fields'] : [];
$thumb = (string) ($embed['thumbnail']['url'] ?? '');
$image = (string) ($embed['image']['url'] ?? '');
?>
<figure class="dsc-msg" aria-label="Aperçu du post sur Discord">
    <?php if ($threadName !== ''): ?>
        <div class="dsc-msg__thread">
            <span class="dsc-msg__forum" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 5h16v11H9l-5 4z"/></svg>
            </span>
            <span class="dsc-msg__thread-name"><?= $e($threadName) ?></span>
            <?php foreach ($tagLabels as $tag): ?>
                <span class="dsc-msg__tag"><?= $e((string) $tag) ?></span>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <div class="dsc-msg__row">
        <span class="dsc-msg__avatar" aria-hidden="true">A</span>
        <div class="dsc-msg__body">
            <p class="dsc-msg__author">
                <span class="dsc-msg__name"><?= $e((string) ($payload['username'] ?? 'Athena')) ?></span>
                <span class="dsc-msg__app">APP</span>
                <span class="dsc-msg__time">Today</span>
            </p>
            <div class="dsc-embed" style="--dsc-color: <?= $e($color) ?>">
                <div class="dsc-embed__main">
                    <?php if (($embed['title'] ?? '') !== ''): ?>
                        <p class="dsc-embed__title"><?= $md((string) $embed['title']) ?></p>
                    <?php endif; ?>
                    <?php if (($embed['description'] ?? '') !== ''): ?>
                        <p class="dsc-embed__desc"><?= $md((string) $embed['description']) ?></p>
                    <?php endif; ?>
                    <?php if ($fields !== []): ?>
                        <div class="dsc-embed__fields">
                            <?php foreach ($fields as $f): ?>
                                <div class="dsc-embed__field<?= empty($f['inline']) ? ' dsc-embed__field--wide' : '' ?>">
                                    <p class="dsc-embed__fname"><?= $e((string) ($f['name'] ?? '')) ?></p>
                                    <p class="dsc-embed__fvalue"><?= $md((string) ($f['value'] ?? '')) ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($image !== ''): ?>
                        <img class="dsc-embed__image" src="<?= $e($image) ?>" alt="" loading="lazy">
                    <?php endif; ?>
                    <?php if (($embed['footer']['text'] ?? '') !== ''): ?>
                        <p class="dsc-embed__footer"><?= $e((string) $embed['footer']['text']) ?></p>
                    <?php endif; ?>
                </div>
                <?php if ($thumb !== ''): ?>
                    <img class="dsc-embed__thumb" src="<?= $e($thumb) ?>" alt="" loading="lazy">
                <?php endif; ?>
            </div>
        </div>
    </div>
</figure>
