<?php
declare(strict_types=1);

/** @var string $token */
/** @var array<string,mixed>|null $invitation */
/** @var bool $generic */

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$inv = is_array($invitation ?? null) ? $invitation : null;
$ok = \App\Core\Session::getFlash('success');
$err = \App\Core\Session::getFlash('error');
?>
<link href="<?= $h(asset_url('assets/css/member-integration.css')) ?>" rel="stylesheet">
<div class="mi-panel" style="max-width:36rem;margin:2rem auto">
    <h1>Répondre à l’invitation</h1>
    <?php if ($ok): ?><p><?= $h($ok) ?></p><?php endif; ?>
    <?php if ($err): ?><p><?= $h($err) ?></p><?php endif; ?>
    <?php if (!empty($generic) || $inv === null): ?>
        <p>Ce lien n’est plus valable. Demandez une nouvelle invitation à votre encadrement.</p>
    <?php else: ?>
        <p><strong><?= $h($inv['title'] ?? '') ?></strong></p>
        <p class="mi-muted"><?= $h($inv['starts_at'] ?? '') ?><?= !empty($inv['location']) ? ' · ' . $h($inv['location']) : '' ?></p>
        <form method="post" action="<?= $h(url('integration/invitation/repondre')) ?>" class="mi-form">
            <?= \App\Core\Csrf::field() ?>
            <input type="hidden" name="token" value="<?= $h($token ?? '') ?>">
            <label>Commentaire (facultatif) <textarea name="comment" rows="2"></textarea></label>
            <div class="mi-actions">
                <button class="mi-btn" name="reponse" value="oui" type="submit">Oui</button>
                <button class="mi-btn mi-btn--ghost" name="reponse" value="peut-etre" type="submit">Peut-être</button>
                <button class="mi-btn mi-btn--warn" name="reponse" value="non" type="submit">Non</button>
            </div>
        </form>
        <p style="margin-top:1rem"><a href="<?= $h(url('integration/invitation/calendrier') . '?token=' . rawurlencode((string) ($token ?? ''))) ?>">Ajouter au calendrier</a></p>
    <?php endif; ?>
</div>
