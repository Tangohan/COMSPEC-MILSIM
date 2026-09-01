<?php
declare(strict_types=1);

/** Helpers d’interface partagés (documents, mobilité, vivier, alertes). */

$h = $h ?? static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

$rhTip = $rhTip ?? static function (string $id, string $label, string $text) use ($h): void {
    ?>
    <span class="eff-rh-tip">
        <button type="button" class="eff-rh-tip__btn" aria-describedby="<?= $h($id) ?>" aria-label="<?= $h($label) ?>">i</button>
        <span id="<?= $h($id) ?>" role="tooltip" class="eff-rh-tip__pop"><?= $h($text) ?></span>
    </span>
    <?php
};

$rhWhen = $rhWhen ?? static function (?string $raw): string {
    $raw = trim((string) $raw);
    if ($raw === '' || str_starts_with($raw, '0000-00-00')) {
        return '—';
    }
    $ts = strtotime($raw);

    return $ts ? date('d/m/Y', $ts) : $raw;
};

$rhUnavailable = $rhUnavailable ?? 'Cette fonction n’est pas encore disponible pour cette communauté. Demandez à un administrateur de lancer la mise à jour, puis rechargez la page.';
