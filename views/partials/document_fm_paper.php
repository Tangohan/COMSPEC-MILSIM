<?php
use App\Support\DocumentManuscript;

$documentTitle = (string) ($documentTitle ?? ($document['title'] ?? 'Untitled'));
$manuscript = $manuscript ?? DocumentManuscript::defaults($documentTitle, '');
$live = !empty($fmLivePreview);
$codes = $manuscript['publication_codes'] ?? [];
$filledSigs = $live ? ($manuscript['signatures'] ?? []) : DocumentManuscript::filledSignatures($manuscript);
if ($live && $filledSigs === []) {
    $filledSigs = [['name' => 'Signature', 'rank' => 'Grade / fonction', 'command' => 'Commandement']];
}
$bodyHtml = (string) ($manuscript['body'] ?? '');
$wrapClass = $live ? 'fm-preview-wrap' : 'fm-print-root';
?>
<div class="<?= $wrapClass ?>" id="<?= $live ? 'fm-live-preview' : 'fm-document' ?>">
    <div class="fm-stack">
        <article class="fm-page fm-page--cover">
            <div class="fm-codes" data-fm="codes">
                <?php if ($codes === []): ?>
                    <?php if ($live): ?><div>FM …</div><?php endif; ?>
                <?php else: ?>
                    <?php foreach ($codes as $code): ?>
                    <div><?= htmlspecialchars((string) $code) ?></div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <div class="fm-cover-main">
                <h1 class="fm-title" data-fm="title"><?= htmlspecialchars($documentTitle) ?></h1>
                <p class="fm-date" data-fm="date"><?= htmlspecialchars((string) ($manuscript['issue_date'] ?? '')) ?></p>
            </div>
            <div class="fm-legal">
                <p class="fm-legal-label">Distribution restriction:</p>
                <p data-fm="restriction"><?= htmlspecialchars((string) ($manuscript['distribution_restriction'] ?? '')) ?></p>
            </div>
            <div class="fm-legal">
                <p class="fm-legal-label">Destruction notice:</p>
                <p data-fm="destruction"><?= htmlspecialchars((string) ($manuscript['destruction_notice'] ?? '')) ?></p>
            </div>
            <p class="fm-hq" data-fm="hq"><?= htmlspecialchars((string) ($manuscript['issuing_authority'] ?? '')) ?></p>
        </article>

        <article class="fm-page fm-page--foreword">
            <h2 class="fm-foreword-title">Foreword</h2>
            <p class="fm-foreword-intro" data-fm="foreword"><?= htmlspecialchars((string) ($manuscript['foreword'] ?? '')) ?></p>
            <div class="fm-sigs" data-fm="sigs">
                <?php foreach ($filledSigs as $sig): ?>
                <?php if (!$live && trim((string) ($sig['name'] ?? '')) === '') { continue; } ?>
                <div class="fm-sig">
                    <p class="fm-sig-script"><?= htmlspecialchars((string) ($sig['name'] ?? '')) ?></p>
                    <p class="fm-sig-name"><?= htmlspecialchars((string) ($sig['name'] ?? '')) ?></p>
                    <?php if (trim((string) ($sig['rank'] ?? '')) !== ''): ?>
                    <p class="fm-sig-rank"><?= htmlspecialchars((string) $sig['rank']) ?></p>
                    <?php endif; ?>
                    <?php if (trim((string) ($sig['command'] ?? '')) !== ''): ?>
                    <p class="fm-sig-cmd"><?= htmlspecialchars((string) $sig['command']) ?></p>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </article>

        <?php if ($live || trim(strip_tags($bodyHtml)) !== ''): ?>
        <article class="fm-page fm-page--body">
            <div class="fm-body" data-fm="body">
                <?php if (trim(strip_tags($bodyHtml)) === ''): ?>
                <p class="fm-body-empty">Le corps du document apparaîtra ici.</p>
                <?php else: ?>
                <?= $bodyHtml ?>
                <?php endif; ?>
            </div>
        </article>
        <?php endif; ?>
    </div>
</div>
