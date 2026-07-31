<?php
declare(strict_types=1);
ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/** @var array<string,mixed> $case */
/** @var string $flash */
/** @var string $initial */
/** @var string $level */
/** @var bool $partial */
/** @var string $clearanceOrigin */
?>
<div class="breadcrumb">
    Athena / SSE /
    <a class="link" href="<?= $h(url('atak/sse/dossiers')) ?>">Dossiers</a> /
    <a class="link" href="<?= $h(url('atak/sse/dossiers/' . (int) ($case['id'] ?? 0))) ?>"><?= $h($case['reference_code'] ?? '') ?></a> /
    <strong>Compte rendu</strong>
</div>

<div class="page-heading">
    <div>
        <div class="page-heading-overline">Produit de renseignement // Exploitation</div>
        <h1>Compte rendu d’exploitation</h1>
        <p>
            Généré à la lecture depuis les événements enregistrés : personnes rattachées,
            relevés, verdicts d’identité, sites et saisies. Le document reflète toujours
            l’état réel du dossier — il n’est pas figé à la rédaction.
        </p>
    </div>
    <div class="page-reference">
        <strong><?= $h($case['reference_code'] ?? '') ?></strong>
        <?= $h($case['title'] ?? '') ?>
    </div>
</div>

<?php if (!empty($partial)): ?>
<div class="security-notice is-refused">
    <div class="security-notice-code">HAB</div>
    <div>
        <strong>Compte rendu partiel — habilitation « <?= $h(\App\Services\Sse\SseRedactionService::levelLabel($level)) ?> »</strong>
        <span>
            <?= $h($clearanceOrigin ?? '') ?>
            Les éléments au-dessus de votre habilitation sont noircis ci-dessous.
            Ce n’est pas la version intégrale du dossier.
        </span>
    </div>
</div>
<?php endif; ?>

<div class="security-notice">
    <div class="security-notice-code">SEC-07</div>
    <div>
        <strong>Diffusion restreinte</strong>
        <span>
            Ce compte rendu porte des éléments de scénario non vérifiés. Un score de
            similarité ou un verdict de terminal n’est pas une identification établie.
        </span>
    </div>
</div>

<section class="panel">
    <div class="panel-header">
        <div class="panel-title">
            <span class="panel-index">01.03</span>
            Flash
        </div>
        <div class="panel-meta">Ce qui justifie d’interrompre le poste de commandement</div>
    </div>
    <div class="panel-body">
        <pre class="sse-report" id="sse-flash"><?= $h($flash) ?></pre>
        <button class="btn btn--ghost btn--sm" type="button" data-copy="#sse-flash">Copier</button>
    </div>
</section>

<section class="panel">
    <div class="panel-header">
        <div class="panel-title">
            <span class="panel-index">01.04</span>
            Compte rendu initial
        </div>
        <div class="panel-meta">Situation · Site · Personnel · Matériel · Faits marquants</div>
    </div>
    <div class="panel-body">
        <pre class="sse-report" id="sse-initial"><?= $h($initial) ?></pre>
        <button class="btn btn--ghost btn--sm" type="button" data-copy="#sse-initial">Copier</button>
        <p class="sse-note">
            Ce document est servi au niveau de votre habilitation. Pour produire une
            version destinée à un cercle plus large, passez par la
            <a class="link" href="<?= $h(url('atak/sse/dossiers/' . (int) ($case['id'] ?? 0) . '/declassification')) ?>">déclassification</a> :
            elle caviarde automatiquement tout ce qui dépasse le niveau visé.
        </p>
    </div>
</section>

<script>
// Copie sans dépendance : le portail ne charge aucune bibliothèque front.
document.querySelectorAll('[data-copy]').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var el = document.querySelector(btn.getAttribute('data-copy'));
        if (!el) { return; }
        var done = function () {
            var old = btn.textContent;
            btn.textContent = 'Copié';
            setTimeout(function () { btn.textContent = old; }, 1600);
        };
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(el.textContent).then(done);
            return;
        }
        // Repli hors contexte sécurisé (HTTP local).
        var ta = document.createElement('textarea');
        ta.value = el.textContent;
        ta.setAttribute('readonly', '');
        ta.style.position = 'absolute';
        ta.style.left = '-9999px';
        document.body.appendChild(ta);
        ta.select();
        try { document.execCommand('copy'); done(); } catch (e) { /* ignoré */ }
        document.body.removeChild(ta);
    });
});
</script>
<?php
$sseContent = ob_get_clean();
require __DIR__ . '/_layout.php';
