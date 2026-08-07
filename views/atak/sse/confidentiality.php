<?php
declare(strict_types=1);
$title = (string) ($title ?? 'Engagement de confidentialité');
$error = $error ?? \App\Core\Session::getFlash('error');
$success = $success ?? \App\Core\Session::getFlash('success');
$sseTheme = sse_ui_theme_normalize((string) ($sseTheme ?? sse_ui_theme()));
$operatorName = (string) ($operatorName ?? 'Opérateur');
$operatorMeta = (string) ($operatorMeta ?? 'Session SSE');
$operatorInitial = (string) ($operatorInitial ?? 'O');
$sessionKind = (string) ($sessionKind ?? 'Session authentifiée');
$expiresLabel = (string) ($expiresLabel ?? '');
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$eagleSrc = asset_url('assets/img/atak-eagle-logo.png');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title><?= $h($title) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= $h(asset_url('assets/css/sse_portal.css')) ?>?v=202608062310">
</head>
<body class="sse-theme-bureau sse-gate sse-ack">
<div class="sse-gate-hub sse-gate-hub--bureau sse-ack-hub" id="sse-ack-hub">
    <div class="sse-gate-hub__stage sse-ack-hub__stage">
        <aside class="sse-gate-hub__visual" aria-hidden="true">
            <div class="sse-gate-hub__visual-glow"></div>
            <div class="sse-gate-hub__gridlines"></div>
            <div class="sse-gate-hub__eagle">
                <img
                    class="sse-gate-hub__eagle-img"
                    src="<?= $h($eagleSrc) ?>"
                    alt=""
                    width="512"
                    height="512"
                    decoding="async"
                >
                <span class="sse-gate-hub__eagle-sweep"></span>
            </div>
            <div class="sse-gate-hub__brand">
                <span class="sse-gate-hub__brand-main">ATHENA</span>
                <span class="sse-gate-hub__brand-sub">SSE</span>
            </div>
            <p class="sse-gate-hub__tagline">
                Compartiment classifié · engagement avant entrée
            </p>
        </aside>

        <section class="sse-gate-hub__panel sse-ack-panel">
            <div class="sse-ack-banner" role="status">
                <strong>CONFIDENTIEL</strong>
                <span>Diffusion restreinte — personnel habilité uniquement</span>
            </div>

            <div class="sse-gate-hub__identity">
                <span class="sse-gate-hub__avatar" aria-hidden="true"><?= $h($operatorInitial) ?></span>
                <div class="sse-gate-hub__identity-text">
                    <strong><?= $h($operatorName) ?></strong>
                    <span><?= $h($operatorMeta) ?> · <?= $h($sessionKind) ?><?= $expiresLabel !== '' ? ' · expire ' . $h($expiresLabel) : '' ?></span>
                </div>
            </div>

            <p class="sse-gate-hub__kicker">Sas d’entrée</p>
            <h1 class="sse-gate-hub__title">Engagement de confidentialité</h1>
            <p class="sse-gate-hub__lead">
                Avant d’ouvrir le bureau de renseignement interpersonnel, vous devez
                reconnaître les règles de conservation, de diffusion et de traçabilité
                qui s’appliquent à cette session.
            </p>

            <?php if ($error): ?><div class="flash flash--error"><?= $h($error) ?></div><?php endif; ?>
            <?php if ($success): ?><div class="flash flash--ok"><?= $h($success) ?></div><?php endif; ?>

            <div class="sse-ack-scroll" tabindex="0">
                <ol class="sse-ack-rules">
                    <li>
                        <strong>Besoin d’en connaître</strong>
                        Vous ne consultez que les éléments nécessaires à votre mission.
                        Toute curiosité hors périmètre est une faute.
                    </li>
                    <li>
                        <strong>Pas de diffusion hors canal</strong>
                        Aucune capture d’écran, extrait, photo ou paraphrase hors des
                        circuits prévus. Les comptes rendus passent par l’atelier de rédaction.
                    </li>
                    <li>
                        <strong>Classification respectée</strong>
                        Un document « Confidentiel » ou « Diffusion très restreinte »
                        ne descend jamais de niveau sans caviardage et validation.
                    </li>
                    <li>
                        <strong>Traçabilité</strong>
                        Chaque ouverture de session, chaque consultation sensible et
                        chaque rédaction sont journalisées. Vous en êtes responsable.
                    </li>
                    <li>
                        <strong>Fin de session</strong>
                        Quittez le compartiment dès que le travail est terminé.
                        Ne laissez pas un poste ouvert sans surveillance.
                    </li>
                </ol>
            </div>

            <form method="post" action="<?= $h(url('atak/sse/confidentialite')) ?>" id="sse-ack-form" class="sse-ack-form">
                <?= \App\Core\Csrf::field() ?>

                <label class="sse-ack-check" for="sse-ack-box">
                    <span class="sse-ack-check__box" aria-hidden="true"></span>
                    <input type="checkbox" name="acknowledge" value="1" required id="sse-ack-box">
                    <span class="sse-ack-check__text">
                        <strong>J’ai lu et j’accepte ces règles</strong>
                        Je m’engage à conserver le secret des informations
                        auxquelles j’accède pendant cette session.
                    </span>
                </label>

                <div class="sse-ack-actions">
                    <button type="submit" class="sse-ack-submit" id="sse-ack-submit" disabled>
                        Valider et entrer dans le bureau
                    </button>
                    <a class="sse-ack-cancel" href="<?= $h(url('atak/sse/quitter')) ?>">
                        Annuler et fermer la session
                    </a>
                </div>
            </form>

            <p class="sse-ack-legal">
                Engagement <?= $h(\App\Services\Sse\SseAccessCodeService::CONFIDENTIALITY_VERSION) ?>
                <span>·</span>
                Acceptation journalisée
            </p>
        </section>
    </div>
</div>
<script>
(function () {
    var box = document.getElementById('sse-ack-box');
    var btn = document.getElementById('sse-ack-submit');
    var wrap = document.querySelector('.sse-ack-check');
    if (!box || !btn) return;
    var sync = function () {
        btn.disabled = !box.checked;
        if (wrap) wrap.classList.toggle('is-checked', box.checked);
    };
    box.addEventListener('change', sync);
    sync();
})();
</script>
</body>
</html>
