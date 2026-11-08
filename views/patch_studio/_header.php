<header class="hd"><div class="w">
<a class="brand" href="<?= $e(url('')) ?>" aria-label="Retour à ATHENA"><img src="<?= $e(asset_url('assets/brand/athena-embleme-blanc.svg')) ?>" alt="" width="30" height="26">ATHENA<em>.</em></a>
<span class="hd-title" translate="no">PATCH STUDIO</span>
<div class="hr"><?php if (!empty($compactHeader)): ?><span class="ps-preview-badge" title="Fonctions en cours de validation">PRÉVERSION</span><?php endif; ?><span class="ps-plan-badge" title="Formule minimale requise : Pro+">PRO+</span><a class="cta" href="<?= $e(url('account')) ?>">Mon compte</a></div>
</div></header>
<?php if (empty($compactHeader)): ?>
<section class="hero ps-hero"><div class="w">
<div class="htop"><div><div class="kk"><i></i>ATHENA · COMSPEC ATAK · CRÉATION GRAPHIQUE</div><h1>Patch Studio<span class="dot">.</span></h1><p class="lede">Concevez vos écussons, composez vos calques et préparez vos exports.</p></div><span class="ps-preview-badge">PRÉVERSION</span></div>
</div></section>
<?php endif; ?>
