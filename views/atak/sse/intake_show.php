<?php
declare(strict_types=1);
ob_start();

use App\Services\Intel\IntelIntakePresenter as P;

/**
 * Bureau SSE — un compte rendu du terrain et son suivi (une fiche a sa propre page, dont le suivi est un onglet).
 * Le document à gauche, le suivi à droite : même service que le back-office « Remontées » (IntelIntakeDesk).
 * Styles : public/assets/css/sse-fiche-rens.css (classes .fnr-*) ; script : public/assets/js/sse-fiche-rens.js.
 *
 * @var array<string, mixed> $intakeDesk
 * @var list<array{id: int, label: string}> $intakeMembers
 * @var array<string, string> $intakeTypes
 * @var bool $intakeBackOffice
 */
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$d = $intakeDesk;
$it = $d['item'];
$row = $it['row'];
$level = (string) $d['viewer']['level'];
$me = (int) $d['viewer']['id'];
$text = static fn (string $field): string => P::textHtml($row, $field, $it['redactions'], $level, $me, 'fnr-mark', 'fnr-barre');
$grid = trim((string) ($row['grid_reference'] ?? ''));
$classLabel = (string) $d['classification'];
$sseExtraStyles = ($sseExtraStyles ?? '') . '<link rel="stylesheet" href="' . $h(asset_url('assets/css/sse-fiche-rens.css')) . '">';
$sseExtraScripts = ($sseExtraScripts ?? '') . '<script defer src="' . $h(asset_url('assets/js/sse-fiche-rens.js')) . '"></script>';
?>
<div class="fnr fnr-cr" data-fnr data-fnr-copied="Copié dans le presse-papiers." data-fnr-copy-failed="La copie a échoué : sélectionnez le texte et copiez-le à la main.">
    <header class="fnr-head">
        <a class="fnr-back" href="<?= $h(url('atak/sse/remontees')) ?>" title="Revenir à la file des remontées"><svg class="fnr-i" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M15 5l-7 7 7 7"/></svg><span>File des remontées</span></a>
        <div class="fnr-head__main">
            <h1 class="fnr-title"><span class="fnr-title__ref"><?= $h($it['ref']) ?></span> <span class="fnr-title__kind"><?= $h($it['type_label']) ?></span></h1>
            <p class="fnr-desc">Compte rendu de <span translate="no"><?= $h($it['author'] !== '' ? $it['author'] : 'Terrain') ?></span>, reçu le <?= $h(P::when((string) $it['at'])) ?>.</p>
        </div>
        <ul class="fnr-chips" aria-label="État de la remontée">
            <li class="fnr-chip fnr-chip--state"><span class="fnr-dot" aria-hidden="true"></span><?= $h($it['state_label']) ?></li>
            <?php if ($classLabel !== ''): ?><li class="fnr-chip"><?= $h($classLabel) ?></li><?php endif; ?>
            <li class="fnr-chip">Priorité : <?= $h($d['urgency']['label']) ?></li>
        </ul>
    </header>

    <div class="fnr-layout">
        <article class="fnr-doc" aria-label="Compte rendu <?= $h($it['ref']) ?>">
            <div class="fnr-doc__inner">
                <div class="fnr-doc__head">
                    <div class="fnr-doc__headmain">
                        <h2 class="fnr-doc__title"><?= ($t = $text('summary')) !== '' ? $t : $h($it['type_label']) ?></h2>
                    </div>
                </div>
                <section class="fnr-sec">
                    <h3>1. Circonstances</h3>
                    <table class="fnr-facts">
                        <tbody>
                            <tr><th scope="row">Reçu le</th><td><?= $h(P::when((string) $it['at'])) ?></td></tr>
                            <?php if (!empty($row['event_timestamp'])): ?><tr><th scope="row">Événement</th><td><?= $h(P::when((string) $row['event_timestamp'])) ?></td></tr><?php endif; ?>
                            <tr><th scope="row">Lieu</th><td><?= ($pl = $text('location_description')) !== '' ? $pl : 'Non précisé' ?></td></tr>
                            <tr><th scope="row">Position</th><td><?= $grid !== '' ? 'Repère <span class="fnr-mono">' . $h($grid) . '</span>' : '<span>Aucune position transmise</span>' ?></td></tr>
                            <tr><th scope="row">Rédacteur</th><td translate="no"><?= $h($it['author']) ?><?= trim((string) ($row['submitter_unit'] ?? '')) !== '' ? ' · ' . $h($row['submitter_unit']) : '' ?></td></tr>
                        </tbody>
                    </table>
                </section>
                <section class="fnr-sec fnr-sec--text">
                    <h3>2. Compte rendu</h3>
                    <?php $any = false; foreach (['details', 'remarks'] as $field): $html = $text($field); if ($html === '') { continue; } $any = true; ?>
                        <p><?= $html ?></p>
                    <?php endforeach; ?>
                    <?php if (!$any): ?><p class="fnr-void">Texte vide.</p><?php endif; ?>
                </section>
                <?php if ($it['structured'] !== []): ?>
                    <section class="fnr-sec">
                        <h3>3. Champs du compte rendu</h3>
                        <table class="fnr-facts">
                            <tbody>
                                <?php foreach ($it['structured'] as $k => $v): if (is_array($v)) { $v = implode(', ', array_map(static fn ($x): string => is_scalar($x) ? (string) $x : 'liste', $v)); } ?>
                                    <tr><th scope="row"><?= $h(\App\Support\AtakIcemanReportCatalog::fieldLabelFr((string) $it['type'], (string) $k)) ?></th><td translate="no"><?= $h((string) $v) ?></td></tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </section>
                <?php endif; ?>
            </div>
        </article>

        <aside class="fnr-side" aria-label="Suivi du compte rendu">
            <section class="fnr-panel" id="suivi">
                <h2 class="fnr-panel__title">Suivi</h2>
                <p class="fnr-desc">Où en est le compte rendu, qui en répond et ce que le bureau en a fait.</p>
                <?php $intakePart = 'panel'; require __DIR__ . '/_intake_suivi.php'; ?>
            </section>
        </aside>
    </div>
    <?php $intakePart = 'dialogs'; require __DIR__ . '/_intake_suivi.php'; ?>
</div>
<?php
$sseContent = ob_get_clean();
require __DIR__ . '/_layout.php';
