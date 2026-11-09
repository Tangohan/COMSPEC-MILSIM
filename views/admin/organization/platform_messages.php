<?php
declare(strict_types=1);

/**
 * Back-office › Messages de la plateforme (centre de lecture des messages de l'administration du site).
 *
 * @var list<array<string, mixed>> $platformMessages
 * @var bool $canAckPlatformMessages
 */
$ok = \App\Core\Session::getFlash('success');
$err = \App\Core\Session::getFlash('error');
?>
<div class="pm-page">
    <?php if ($ok): ?><p class="pm-flash pm-flash--ok" role="status"><?= htmlspecialchars((string) $ok, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
    <?php if ($err): ?><p class="pm-flash pm-flash--err" role="alert"><?= htmlspecialchars((string) $err, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
    <?php if (($platformMessages ?? []) === []): ?>
        <div class="pm-empty">
            <p class="pm-empty__title">Aucun message pour le moment</p>
            <p class="pm-empty__text">Quand l’équipe de la plateforme vous écrit, le message apparaît ici et en haut de l’accueil.</p>
        </div>
    <?php else: ?>
        <?php require base_path('views/partials/platform_messages_cards.php'); ?>
    <?php endif; ?>
</div>
