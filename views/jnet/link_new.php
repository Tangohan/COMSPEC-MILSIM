<?php
declare(strict_types=1);
/**
 * « Lier à… » sans JavaScript (JnetPortalController::linkNew) : on cherche dans ce que le lecteur voit déjà, puis un
 * bouton par résultat pose le lien (POST avec jeton CSRF, droits refaits côté serveur). Avec JavaScript, la même
 * recherche s'ouvre en fenêtre depuis le bouton « Lier à… ».
 *
 * @var array<string, mixed> $linkNew
 */
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$ln = is_array($linkNew ?? null) ? $linkNew : [];
$results = is_array($ln['results'] ?? null) ? $ln['results'] : [];
$q = (string) ($ln['q'] ?? '');
?>
<article class="jnet-panel jl-new">
    <div class="jnet-panel__head">
        <h2>Lier « <?= $h((string) ($ln['srcTitle'] ?? '')) ?> » à…</h2>
        <a class="jnet-btn" href="<?= $h((string) ($ln['srcHref'] ?? url('jnet'))) ?>">Retour</a>
    </div>
    <div class="jnet-panel__body">
        <?php if (empty($ln['ready'])): ?>
            <p class="jnet-lead">Les liens arrivent avec la prochaine mise à jour du site (étape jnet_links).</p>
        <?php else: ?>
        <form class="jl-search" method="get" action="<?= $h(url('jnet/liens/nouveau')) ?>" role="search">
            <input type="hidden" name="src" value="<?= $h((string) ($ln['src'] ?? '')) ?>">
            <input type="hidden" name="space" value="<?= (int) ($ln['space'] ?? 0) ?>">
            <input type="hidden" name="types" value="<?= $h((string) ($ln['types'] ?? '')) ?>">
            <label class="uw-field" for="jl-q">Chercher un ordre, une page, une tâche, une opération, une cible, un document, une unité ou un membre</label>
            <div class="jl-search__bar">
                <input id="jl-q" type="search" name="q" value="<?= $h($q) ?>" minlength="2" maxlength="80" required autocomplete="off" placeholder="Au moins deux lettres">
                <button class="ja-b1 sm" type="submit">Chercher</button>
            </div>
        </form>
        <?php if ($q !== '' && mb_strlen($q) < 2): ?>
            <p class="uw-note">Tapez au moins deux lettres.</p>
        <?php elseif ($q !== '' && $results === []): ?>
            <p class="uw-note">Aucun résultat pour « <?= $h($q) ?> » parmi ce que vous pouvez voir.</p>
        <?php endif; ?>
        <?php if ($results !== []): ?>
            <ul class="jl-list jl-list--results">
                <?php foreach ($results as $r): ?>
                    <li class="jl-row">
                        <span class="jl-row__a">
                            <span class="jl-row__k"><?= $h((string) $r['typeLabel']) ?></span>
                            <span class="jl-row__t"><b><?= $h((string) $r['title']) ?></b><?php if ((string) ($r['sub'] ?? '') !== ''): ?><small><?= $h((string) $r['sub']) ?></small><?php endif; ?></span>
                        </span>
                        <form method="post" action="<?= $h((string) ($ln['createUrl'] ?? url('jnet/liens'))) ?>">
                            <?= \App\Core\Csrf::field() ?>
                            <input type="hidden" name="src" value="<?= $h((string) $ln['src']) ?>">
                            <input type="hidden" name="dst" value="<?= $h((string) $r['ref']) ?>">
                            <input type="hidden" name="space" value="<?= (int) ($ln['space'] ?? 0) ?>">
                            <button class="ja-b2 sm" type="submit">Lier</button>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</article>
