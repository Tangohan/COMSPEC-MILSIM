<?php
declare(strict_types=1);
/**
 * Contenu d'une page JNET (flashs, page, retours de chargement), repris par la coque views/jnet/_page.php.
 * @var string $jnetInnerView
 * @var string $activeNav
 */
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$inner = (string) ($jnetInnerView ?? 'jnet.home');
$activeNav = (string) ($activeNav ?? 'home');
// Pages du membre (/jnet/moi) : le contrôleur a déjà lu ses messages (success/error) pour les donner à la vue ; ils sont
// affichés ici, une seule fois (la vue du corps ne les revoit pas), avec ceux restés en session.
$memberShell = !empty($jnetMemberShell);
$passedError = $memberShell && is_scalar($error ?? null) ? trim((string) $error) : '';
$passedSuccess = $memberShell && is_scalar($success ?? null) ? trim((string) $success) : '';
$error = \App\Core\Session::getFlash('error');
$success = \App\Core\Session::getFlash('success');
if ($memberShell) {
    $error = $error ?: ($passedError !== '' ? $passedError : null);
    $success = $success ?: ($passedSuccess !== '' ? $passedSuccess : null);
}
// Les pages du membre affichaient avant, dans la coque du back-office, aussi les avertissements, les informations et les
// refus d'accès (AccessDeniedStayMiddleware renvoie sur la page d'origine avec le message en session) : ils sont repris ici.
$extraFlashes = [];
if ($memberShell) {
    foreach (['toast_error' => 'jnet-flash--err', 'toast_warning' => 'jnet-flash--err', 'warning' => '', 'info' => ''] as $flashKey => $flashClass) {
        $flashText = \App\Core\Session::getFlash($flashKey);
        if (is_scalar($flashText) && trim((string) $flashText) !== '') {
            $extraFlashes[] = ['class' => $flashClass, 'text' => trim((string) $flashText)];
        }
    }
}

?>
<div class="jnet-embed">
    <?php if ($error): ?>
        <div class="jnet-flash jnet-flash--err" role="alert"><?= $h((string) $error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="jnet-flash jnet-flash--ok" role="status"><?= $h((string) $success) ?></div>
    <?php endif; ?>
    <?php foreach ($extraFlashes as $extraFlash): ?>
        <div class="jnet-flash<?= $extraFlash['class'] !== '' ? ' ' . $h($extraFlash['class']) : '' ?>" role="<?= $extraFlash['class'] !== '' ? 'alert' : 'status' ?>"><?= $h($extraFlash['text']) ?></div>
    <?php endforeach; ?>

    <div class="jnet-stage jnet-stage--bo">
        <?php
        if ($memberShell) {
            // Affichés ci-dessus : la vue du corps ne les affiche pas une seconde fois.
            unset($error, $success);
        }
        $viewFile = base_path('views/' . str_replace('.', '/', $inner) . '.php');
        if (is_file($viewFile)) {
            require $viewFile;
        } else {
            echo '<p class="jnet-empty">Écran indisponible.</p>';
        }
        ?>
    </div>
</div>
<script src="<?= $h(asset_url('assets/js/jnet_spaces.js?r=202610141000')) ?>" defer></script>
