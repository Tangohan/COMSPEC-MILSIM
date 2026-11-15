<?php
declare(strict_types=1);
use App\Support\SseAccessCatalog as C;

/**
 * Droits d'accès SSE : historique des changements de règles (qui, quand, quoi, motif).
 *
 * @var list<array<string, mixed>> $rows
 * @var list<array<string, mixed>> $audit
 * @var string $base
 */
ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$tab = 'historique';
require __DIR__ . '/_head.php';
$actions = ['create' => 'Règle créée', 'template' => 'Modèle posé', 'revoke' => 'Règle retirée'];
?>
<div class="fx sar">
    <div class="fx-th">
        <div>
            <h1>Historique des droits d’accès</h1>
            <p>Chaque changement de règle est inscrit ici (qui, quand, quoi, pourquoi) et dans le journal d’audit du bureau SSE. Rien n’est effacé.</p>
        </div>
    </div>
    <section class="fx-card" aria-labelledby="sar-hist-title">
        <h2 id="sar-hist-title" class="sar-h2">Derniers changements</h2>
        <?php if ($rows === []): ?>
            <p class="sar-empty"><b>Aucun changement</b> Aucune règle n’a encore été créée ou retirée.</p>
        <?php else: ?>
            <div class="fx-tbw">
                <table class="fx-tb resp">
                    <thead><tr><th scope="col">Date</th><th scope="col">Par</th><th scope="col">Changement</th><th scope="col">Section</th><th scope="col">Niveau</th><th scope="col">Bénéficiaire</th><th scope="col">Effet</th><th scope="col">Période</th><th scope="col">Motif</th></tr></thead>
                    <tbody>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <td data-label="Date" class="m"><?= $h(substr((string) $r['created_at'], 0, 16)) ?></td>
                            <td data-label="Par"><?= $h($r['actor_label']) ?></td>
                            <td data-label="Changement"><?= $h($actions[$r['action']] ?? $r['action']) ?></td>
                            <td data-label="Section"><?= $h(C::sectionLabel((string) $r['section'])) ?></td>
                            <td data-label="Niveau"><?= $h(C::levelLabel((string) $r['level'])) ?></td>
                            <td data-label="Bénéficiaire"><?= $h($r['subject_label']) ?></td>
                            <td data-label="Effet"><span class="fx-flg <?= $r['effect'] === 'deny' ? 'sar-deny' : 'sar-allow' ?>"><?= $r['effect'] === 'deny' ? 'Refus' : 'Accord' ?></span></td>
                            <td data-label="Période"><?= $r['starts_at'] === null && $r['ends_at'] === null ? 'Sans limite' : $h(($r['starts_at'] !== null ? 'du ' . substr((string) $r['starts_at'], 0, 10) . ' ' : '') . ($r['ends_at'] !== null ? 'jusqu’au ' . substr((string) $r['ends_at'], 0, 10) : '')) ?></td>
                            <td data-label="Motif"><?= $r['reason'] !== '' ? $h($r['reason']) : '—' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
    <?php if ($audit !== []): ?>
        <section class="fx-card" aria-labelledby="sar-audit-title">
            <h2 id="sar-audit-title" class="sar-h2">Journal d’audit SSE</h2>
            <p class="fx-help">Les mêmes changements, tels qu’ils figurent dans le journal d’audit du bureau.</p>
            <ul class="sar-list">
                <?php foreach ($audit as $a): ?>
                    <li><span class="m"><?= $h(substr((string) ($a['created_at'] ?? ''), 0, 16)) ?></span> · <?= $h($a['actor_label'] ?? '') ?> · <?= $h($a['action'] ?? '') ?><?php if (($a['reason'] ?? '') !== ''): ?> · <?= $h($a['reason']) ?><?php endif; ?></li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>
</div>
<?php
$sseContent = ob_get_clean();
require dirname(__DIR__) . '/_layout.php';
