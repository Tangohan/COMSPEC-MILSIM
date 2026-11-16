<?php
declare(strict_types=1);
/**
 * Éditeur d'une fiche : bouton-icône « Visibilité par niveau » et fenêtre (tableau rubriques × niveaux). Les choix voyagent avec le
 * formulaire d'émission (visibility[rubrique] = niveau) : ils ne valent que pour CE document, au-dessus du réglage du modèle de la
 * communauté, et jamais sous le plancher de sécurité d'une rubrique (le serveur le garantit). Un aperçu du PDF est proposé à chaque
 * niveau de l'opérateur.
 *
 * @var array{rows: list<array<string, mixed>>, levels: list<array<string, string>>} $visTable SseSheetVisibility::table()
 * @var string $base adresse de l'éditeur
 * @var list<string> $levels niveaux de l'opérateur
 * @var bool $canIssue
 */
use App\Support\SseUsClassification;

$h = $h ?? static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
if (!$canIssue || !is_array($visTable ?? null)) {
    return;
}
$visEn = (($readerLang ?? 'fr') === 'en');
$sseExtraStyles = ($sseExtraStyles ?? '') . '<link rel="stylesheet" href="' . $h(asset_url('assets/css/sse-visibility.css')) . '">';
$sseExtraScripts = ($sseExtraScripts ?? '') . '<script src="' . $h(asset_url('assets/js/sse-visibility.js')) . '" defer></script>';
if (!str_contains($sseExtraScripts, 'sse-sheet-redaction.js')) {
    $sseExtraScripts .= '<script src="' . $h(asset_url('assets/js/sse-sheet-redaction.js')) . '" defer></script>';
}
$changed = 0;
foreach ((array) $visTable['rows'] as $row) {
    if ((string) $row['level'] !== (string) $row['default']) { ++$changed; }
}
?>
<div class="svl-preview">
    <button type="button" class="svl-trigger" data-shx-open="shx-vis" aria-haspopup="dialog" title="Régler, rubrique par rubrique, à partir de quel niveau elle est lisible dans ce document">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M3 12s3.6-6.5 9-6.5S21 12 21 12s-3.6 6.5-9 6.5S3 12 3 12z"/><circle cx="12" cy="12" r="2.6"/><path d="M4 20L20 4"/></svg>
        <span>Visibilité par niveau</span>
    </button>
    <?php if ($changed > 0): ?><span class="svl-summary" role="status"><?= (int) $changed ?> rubrique(s) réglée(s) autrement que le modèle, pour ce document seulement.</span><?php endif; ?>
</div>
<dialog class="svl-dialog" id="shx-vis" aria-labelledby="shx-vis-t">
    <div class="svl-dialog__box">
        <h3 id="shx-vis-t" class="svl-dialog__title">Visibilité par niveau</h3>
        <p class="svl-dialog__sub">Pour chaque rubrique, le niveau de diffusion à partir duquel elle est lisible en clair dans ce document. Ce réglage ne vaut que pour ce document et part avec lui ; les niveaux de la communauté ne changent pas.</p>
        <?php $vt = $visTable; $vtName = 'visibility'; $vtId = 'shx-vis'; $vtEdit = true; require __DIR__ . '/_sheet_visibility_table.php'; ?>
        <p class="svl-dialog__sub">Aperçu du PDF à chaque niveau (avec les réglages ci-dessus) :</p>
        <div class="svl-preview">
            <?php foreach ($levels as $l): ?>
                <button type="submit" class="svl-trigger" formmethod="post" formaction="<?= $h($base . '/apercu') ?>" formtarget="_blank" formnovalidate name="preview_level" value="<?= $h($l) ?>" title="<?= $h(SseUsClassification::label((string) $l, $visEn ? 'en' : 'fr')) ?>">Aperçu <span translate="no"><?= $h(SseUsClassification::abbr((string) $l)) ?></span></button>
            <?php endforeach; ?>
        </div>
        <div class="svl-dialog__foot">
            <button type="button" class="btn shx__btn shx__btn--primary" data-shx-close>Appliquer à ce document</button>
        </div>
    </div>
</dialog>
