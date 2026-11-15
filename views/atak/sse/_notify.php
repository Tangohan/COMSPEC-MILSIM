<?php
declare(strict_types=1);
/**
 * Bureau SSE — zone unique des messages (erreurs, validations, succès, avertissements) du portail.
 * Les messages du serveur (flash) sont rendus ici ; sse-notify.js ajoute les messages du navigateur
 * (échec d'un appel réseau, formulaire invalide) dans la même zone et fournit window.SseNotify.
 * Les libellés vivent dans le bloc caché ci-dessous : le script ne contient aucun texte (la traduction
 * de la page s'applique aux textes des balises, pas à ceux des scripts).
 * @var string|null $error   message d'erreur du serveur
 * @var string|null $success message de réussite du serveur
 * @var callable    $h       échappement HTML
 */
$notes = [];
if (is_string($error ?? null) && $error !== '') {
    $notes[] = ['error', 'Action impossible', $error];
}
if (is_string($success ?? null) && $success !== '') {
    $notes[] = ['success', 'Opération réussie', $success];
}
$icons = [
    'error' => '<svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="2"/><path d="M12 7v6" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/><circle cx="12" cy="16.8" r="1.4" fill="currentColor"/></svg>',
    'success' => '<svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="2"/><path d="M7.5 12.4l3 3 6-6.4" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>',
];
$closeIcon = '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/></svg>';
?>
<section class="sse-notify" id="sse-notify" aria-label="Messages du portail">
    <div class="sse-notify__stack" data-sse-stack="error" role="alert">
        <?php foreach ($notes as [$kind, $title, $text]): if ($kind !== 'error') { continue; } ?>
            <div class="sse-note sse-toast sse-note--error" data-sse-note="error" tabindex="-1">
                <span class="sse-note__icon"><?= $icons['error'] ?></span>
                <div class="sse-note__body"><strong class="sse-note__title"><?= $h($title) ?></strong><p class="sse-note__detail"><?= $h($text) ?></p></div>
                <button type="button" class="sse-note__close" data-sse-close aria-label="Fermer ce message"><?= $closeIcon ?></button>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="sse-notify__stack" data-sse-stack="status" role="status">
        <?php foreach ($notes as [$kind, $title, $text]): if ($kind !== 'success') { continue; } ?>
            <div class="sse-note sse-toast sse-note--success" data-sse-note="success" tabindex="-1">
                <span class="sse-note__icon"><?= $icons['success'] ?></span>
                <div class="sse-note__body"><strong class="sse-note__title"><?= $h($title) ?></strong><p class="sse-note__detail"><?= $h($text) ?></p></div>
                <button type="button" class="sse-note__close" data-sse-close aria-label="Fermer ce message"><?= $closeIcon ?></button>
            </div>
        <?php endforeach; ?>
    </div>
    <div hidden id="sse-notify-labels">
        <span data-k="error">Action impossible</span>
        <span data-k="success">Opération réussie</span>
        <span data-k="warning">Attention</span>
        <span data-k="close">Fermer ce message</span>
        <span data-k="network">Le serveur n’a pas répondu. Vérifiez la connexion puis réessayez.</span>
        <span data-k="http">La demande a échoué (code %1). Réessayez ; si le problème continue, prévenez un administrateur.</span>
        <span data-k="form">Le formulaire contient %1 erreur(s) à corriger</span>
        <span data-k="field">Champ à corriger</span>
    </div>
</section>
