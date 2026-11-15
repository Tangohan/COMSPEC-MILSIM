<?php
declare(strict_types=1);
/**
 * Liste « Documents émis » d'une fiche : référence, modèle, langue, diffusion, date, émetteur, re-téléchargement et texte
 * réellement imprimé. Un document classé au-dessus de l'habilitation du lecteur reste listé, sans téléchargement ni texte.
 *
 * @var list<array<string, mixed>> $issues  (ou $sheetMenu['issues'])
 * @var string $sheetLevel habilitation du lecteur
 */
use App\Services\Sse\SseRedactionService;
use App\Support\SseSheetCatalog as D;

$h = $h ?? static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$issuesList = is_array($issues ?? null) ? $issues : (array) ($sheetMenu['issues'] ?? []);
$ilang = ($lang ?? $sheetMenu['lang'] ?? 'fr') === 'en' ? 'en' : 'fr';
$ilevel = (string) ($sheetLevel ?? $sheetMenu['level'] ?? 'interne');
$ilabels = static function (string $model) use ($ilang): array {
    $out = [];
    foreach (D::texts($model) as [$key, , $labels]) {
        $out[$key] = $labels[$ilang === 'en' ? 1 : 0];
    }

    return $out;
};
$idt = static function (string $v): string {
    $ts = strtotime($v . ' UTC');

    return $ts === false ? '—' : gmdate('d/m/Y H:i', $ts);
};
?>
<section class="panel shx-issues"<?= empty($issuesEmbedded) ? ' id="documents-emis"' : '' ?> style="margin-top:12px">
    <div class="panel-header"><div class="panel-title">Documents émis</div><div class="panel-meta"><?= count($issuesList) ?> document(s)</div></div>
    <div class="panel-body">
        <?php if ($issuesList === []): ?>
            <p class="muted">Aucun document n’a encore été émis pour cette fiche. L’émission d’un PDF ne modifie jamais la fiche : elle est seulement notée ici et dans l’historique.</p>
        <?php else: ?>
            <div class="table-wrap"><table>
                <thead><tr><th>Référence</th><th>Modèle</th><th>Langue</th><th>Diffusion</th><th>Émis le</th><th>Par</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($issuesList as $i): ?>
                    <?php $open = SseRedactionService::levelRank((string) $i['classification']) <= SseRedactionService::levelRank($ilevel); ?>
                    <tr>
                        <td data-label="Référence" translate="no"><?= $h($i['ref']) ?></td>
                        <td data-label="Modèle"><?= $h(D::label((string) $i['model'], $ilang)) ?></td>
                        <td data-label="Langue"><?= $h(strtoupper((string) $i['lang'])) ?></td>
                        <td data-label="Diffusion"><?= $h(SseRedactionService::levelLabel((string) $i['classification'])) ?></td>
                        <td data-label="Émis le"><?= $h($idt((string) $i['issued_at'])) ?></td>
                        <td data-label="Par" translate="no"><?= $h($i['issued_by_label'] !== '' ? $i['issued_by_label'] : '—') ?></td>
                        <td>
                            <?php if ($open): ?>
                                <a class="btn shx__btn shx__btn--small" href="<?= $h(url('atak/sse/identites/emissions/' . (int) $i['id'] . '/pdf')) ?>">Télécharger</a>
                                <?php if ($i['texts'] !== []): ?>
                                    <details class="shx-printed">
                                        <summary>Texte imprimé</summary>
                                        <?php foreach ($i['texts'] as $k => $text): ?>
                                            <?php if (trim((string) $text) === '') { continue; } ?>
                                            <p class="shx-printed__k"><?= $h($ilabels((string) $i['model'])[$k] ?? $k) ?></p>
                                            <p class="shx-printed__t" translate="no"><?= nl2br($h(\App\Support\SseSheetText::plain((string) $text))) ?></p>
                                        <?php endforeach; ?>
                                    </details>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="muted" title="Ce document est classé au-dessus de votre habilitation.">Classé au-dessus de votre habilitation</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
        <?php endif; ?>
    </div>
</section>
