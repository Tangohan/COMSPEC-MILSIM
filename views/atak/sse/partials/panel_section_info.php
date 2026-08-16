<?php
declare(strict_types=1);
/**
 * Contrôle d’aide « i » pour un en-tête de rubrique dossier SSE.
 *
 * @var string $sectionKey Clé catalogue (ex. 01.10)
 */
$sectionKey = (string) ($sectionKey ?? '');
$help = \App\Support\SseCaseSectionHelp::for($sectionKey);
if ($help === null) {
    return;
}

$h = $h ?? static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$safeId = 'sse-help-' . preg_replace('/[^a-zA-Z0-9_-]+/', '-', $sectionKey);
$title = (string) $help['title'];
?>
<details class="sse-section-info" data-sse-section-info>
    <summary
        class="sse-section-info__btn"
        aria-label="<?= $h('À propos de la rubrique « ' . $title . ' »') ?>"
        title="<?= $h('Principe, utilité et usage de cette rubrique') ?>"
    >
        <span class="sse-section-info__glyph" aria-hidden="true">i</span>
    </summary>
    <div class="sse-section-info__panel" id="<?= $h($safeId) ?>" role="region" aria-label="<?= $h('Aide — ' . $title) ?>">
        <p class="sse-section-info__lead"><?= $h($title) ?></p>
        <p><strong>Principe.</strong> <?= $h($help['principe']) ?></p>
        <p><strong>Utilité.</strong> <?= $h($help['utilite']) ?></p>
        <p><strong>À quoi sert la donnée.</strong> <?= $h($help['donnee']) ?></p>
    </div>
</details>
<?php
// Script unique par page : ferme les autres panneaux et gère Échap.
if (empty($GLOBALS['sse_section_info_js'])):
    $GLOBALS['sse_section_info_js'] = true;
?>
<script>
(function () {
    if (window.__sseSectionInfoBound) return;
    window.__sseSectionInfoBound = true;
    document.addEventListener('toggle', function (ev) {
        var t = ev.target;
        if (!(t instanceof HTMLDetailsElement) || !t.classList.contains('sse-section-info') || !t.open) return;
        document.querySelectorAll('details.sse-section-info[open]').forEach(function (other) {
            if (other !== t) other.open = false;
        });
    }, true);
    document.addEventListener('keydown', function (ev) {
        if (ev.key !== 'Escape') return;
        document.querySelectorAll('details.sse-section-info[open]').forEach(function (d) {
            d.open = false;
        });
    });
})();
</script>
<?php endif; ?>
