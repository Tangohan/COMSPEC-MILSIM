<?php
declare(strict_types=1);
/**
 * Bandeau d'avertissement de la fiche d'une personne ou d'une identité : une fiche de vigilance est en cours. Bande en couches
 * (fond, halo de la couleur du niveau, voile, contenu). Ne montre que le niveau, la consigne principale, l'échéance et le lien :
 * jamais les motifs, la zone ni le service (déjà bornés à l'habilitation du lecteur par SseVigilanceFollow::forSubject).
 *
 * @var array<string, mixed> $m données de SseSheetMenu::build (clé « banner »)
 */
use App\Services\Sse\SseVigilanceFollow;

$h = $h ?? static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$b = is_array($m['banner'] ?? null) ? $m['banner'] : null;
if ($b === null) {
    return;
}
$sseExtraStyles = ($sseExtraStyles ?? '') . '<link rel="stylesheet" href="' . $h(asset_url('assets/css/sse-vigilance.css')) . '">';
$days = $b['days_left'];
$linked = ($m['reason'] ?? null) === null && !empty($m['ready']);
?>
<section class="vig-banner vig-lv--<?= $h($b['level']) ?>" style="--lv:<?= $h($b['hex']) ?>;--lv-ink:<?= $h($b['ink']) ?>" aria-label="Avertissement : fiche de vigilance en cours">
    <span class="vig-banner__bg" aria-hidden="true"></span>
    <span class="vig-banner__halo" aria-hidden="true"></span>
    <span class="vig-banner__veil" aria-hidden="true"></span>
    <div class="vig-banner__body">
        <div class="vig-banner__icon"><?= SseVigilanceFollow::shapeSvg((string) $b['shape'], 64) ?></div>
        <div class="vig-banner__main">
            <h2 class="vig-banner__title"><?= $h($b['title']) ?></h2>
            <?php if ($b['order_label'] !== ''): ?><p class="vig-banner__order"><?= $h($b['order_label']) ?></p><?php endif; ?>
            <ul class="vig-banner__facts">
                <li class="vig-banner__rank"><span>Niveau</span> <strong><?= (int) $b['rank'] ?>/4</strong> <?= SseVigilanceFollow::pips((int) $b['rank']) ?></li>
                <?php if (!empty($b['until_lifted'])): ?>
                    <li><strong>Jusqu’à levée</strong></li>
                <?php else: ?>
                    <li><span>Jusqu’au</span> <strong><?= $h($b['until_label']) ?></strong>
                        <?php if ($b['exact'] && $b['hours_left'] !== null && $b['hours_left'] >= 1): ?><span class="vig-banner__left">· <span>encore</span> <strong><?= (int) $b['hours_left'] ?></strong> <span>h</span></span>
                        <?php elseif ($days !== null && $days >= 1): ?><span class="vig-banner__left">· <span>encore</span> <strong><?= (int) $days ?></strong> <span>jour(s)</span></span>
                        <?php elseif ($days !== null): ?><span class="vig-banner__left">· <span>dernier jour</span></span><?php endif; ?></li>
                <?php endif; ?>
                <?php if (!empty($b['perishable'])): ?><li class="vig-banner__flag"><span>Périssable</span></li><?php endif; ?>
                <?php if ((int) $b['others'] > 0): ?><li><span>Autres fiches en cours :</span> <strong><?= (int) $b['others'] ?></strong></li><?php endif; ?>
            </ul>
        </div>
        <?php if ($linked): ?>
            <div class="vig-banner__side">
                <?php if ($b['measures'] !== []): ?>
                    <ul class="vig-banner__chips" aria-label="Conduite à tenir">
                        <?php foreach (array_slice($b['measures'], 0, 3) as $mm): ?><li><?= $h($mm) ?></li><?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <div class="vig-banner__actions">
                    <a class="vig-btn vig-btn--light" href="#documents-emis" data-dos-go data-vig-goto="vigilances">Voir la fiche</a>
                    <a class="vig-btn vig-btn--ghost-light" href="<?= $h($b['pdf_url']) ?>">PDF</a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>
