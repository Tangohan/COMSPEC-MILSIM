<?php
declare(strict_types=1);

$code = (string) ($code ?? '');
$linked = (bool) ($linked ?? false);
$success = $success ?? null;
$error = $error ?? null;
$gameRequest = is_array($gameRequest ?? null) ? $gameRequest : null;
$watch = (int) ($watch ?? 0);
$steamMoved = (int) ($steamMoved ?? 0);
$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

$accountNavKey = 'devices';
$accountTitle = 'Lier le jeu';
$accountLead = 'Connectez le mod COMSPEC à votre compte Athena avec le code affiché dans Arma. Aucune clé ni identifiant de communauté à saisir.';
require base_path('views/partials/account/shell_open.php');
?>

<section class="account-hub__panel">
    <div class="account-hub__panel-head">
        <p class="account-hub__panel-kicker">Arma 3 · COMSPEC ATAK</p>
        <?php if ($linked): ?>
            <h2 class="account-hub__panel-title">Votre jeu est lié</h2>
            <p class="account-hub__panel-desc">Le téléphone récupère votre profil, votre communauté et vos réglages. Aux prochaines parties, la connexion se fera seule avec votre compte Steam.</p>
            <?php if ($watch > 0): ?>
            <p class="account-hub__hint" data-link-watch="<?= $h(url('lier/etat')) ?>" role="status" aria-live="polite" style="display:flex;gap:.5rem;align-items:center;margin-top:.75rem;font-weight:700">
                <span data-link-dot style="width:.6rem;height:.6rem;border-radius:50%;background:#d97706;flex:none"></span>
                <span data-link-text>En attente du jeu : gardez Arma ouvert, le téléphone reprend la session dans quelques secondes.</span>
            </p>
            <?php endif; ?>
            <?php if ($steamMoved > 0): ?>
            <p class="account-hub__hint">Ce compte Steam était aussi rattaché à un autre compte de votre communauté. Il ne mène plus qu’à celui-ci, pour que la connexion automatique ouvre toujours le bon compte.</p>
            <?php endif; ?>
        <?php else: ?>
            <h2 class="account-hub__panel-title">Saisissez le code du jeu</h2>
            <p class="account-hub__panel-desc">Dans Arma, ouvrez l’app Athena du téléphone : un code de 8 caractères s’affiche (par exemple <strong>ABCD-2345</strong>). Il reste valable 10 minutes.</p>
        <?php endif; ?>
    </div>
    <?php if (!$linked): ?>
    <div class="account-hub__panel-body">
        <?php if ($gameRequest !== null && $gameRequest['state'] === 'pending'): ?>
        <?php
            $steamTail = $gameRequest['steam'] !== '' ? '…' . substr($gameRequest['steam'], -5) : 'non transmis';
        ?>
        <div style="border:1px solid #d1d5db;border-radius:.75rem;padding:.85rem 1rem;margin-bottom:1rem;background:rgba(4,120,87,.05)">
            <p style="margin:0 0 .4rem;font-weight:800">Demande reçue du jeu</p>
            <ul style="margin:0;padding-left:1.1rem;line-height:1.6">
                <li>Compte Steam : <strong style="font-family:ui-monospace,Menlo,monospace"><?= $h($steamTail) ?></strong><?= $gameRequest['steam_here'] ? ' (déjà le vôtre)' : '' ?></li>
                <?php if ($gameRequest['mod_version'] !== '' && $gameRequest['mod_version'] !== 'native'): ?>
                <li>Version du mod : <?= $h($gameRequest['mod_version']) ?></li>
                <?php endif; ?>
                <?php if ($gameRequest['asked_min'] < 1): ?>
                <li>Code demandé à l’instant, encore valable <?= (int) $gameRequest['left_min'] ?> min</li>
                <?php else: ?>
                <li>Code demandé il y a <?= (int) $gameRequest['asked_min'] ?> min, encore valable <?= (int) $gameRequest['left_min'] ?> min</li>
                <?php endif; ?>
            </ul>
            <?php if ($gameRequest['steam_elsewhere']): ?>
            <p style="margin:.6rem 0 0;color:#b45309;font-weight:700">Ce compte Steam est déjà rattaché à un autre compte de votre communauté. En autorisant, il passera sur le compte avec lequel vous êtes connecté ici.</p>
            <?php endif; ?>
            <p style="margin:.6rem 0 0;font-size:.8125rem">Vérifiez que c’est bien votre jeu : n’autorisez jamais un code reçu de quelqu’un d’autre, il lui donnerait accès à votre compte.</p>
        </div>
        <?php elseif ($gameRequest !== null): ?>
        <p class="account-hub__hint" style="color:#b45309;font-weight:700;margin-bottom:1rem">Ce code a déjà été utilisé ou a expiré. Dans Arma, demandez un nouveau code dans l’app Athena du téléphone.</p>
        <?php endif; ?>
        <form method="post" action="<?= htmlspecialchars(url('lier'), ENT_QUOTES, 'UTF-8') ?>" class="account-hub__form-grid">
            <?= \App\Core\Csrf::field() ?>
            <div>
                <label class="account-hub__label" for="code">Code affiché dans Arma</label>
                <input type="text" name="code" id="code" required maxlength="9" autocomplete="one-time-code" autocapitalize="characters" spellcheck="false"
                       value="<?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?>" placeholder="XXXX-XXXX"
                       style="font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:1.6rem;letter-spacing:.2em;text-transform:uppercase;max-width:16rem">
                <p class="account-hub__hint">Le jeu sera rattaché au compte et à la communauté avec lesquels vous êtes connecté ici.</p>
                <p class="account-hub__hint">Votre compte Steam est relié en même temps, même si votre profil Steam est privé.</p>
            </div>
            <div style="display:flex;flex-wrap:wrap;gap:.75rem;align-items:center;margin-top:.25rem">
                <button type="submit" class="account-hub__btn account-hub__btn--ink">Autoriser ce jeu</button>
                <a href="<?= htmlspecialchars(url('account/security/devices'), ENT_QUOTES, 'UTF-8') ?>" style="font-size:.8125rem;font-weight:700;color:#047857;text-decoration:underline;text-underline-offset:2px">Voir mes appareils liés</a>
            </div>
        </form>
    </div>
    <?php endif; ?>
</section>

<p class="account-hub__footer-note">
    Vous n’avez pas demandé ce code ? Ne validez rien : sans votre accord, le jeu reste déconnecté.
</p>

<?php if ($linked && $watch > 0): ?>
<script>
(function () {
    var box = document.querySelector('[data-link-watch]');
    if (!box) { return; }
    var dot = box.querySelector('[data-link-dot]');
    var text = box.querySelector('[data-link-text]');
    var tries = 0;
    function show(color, message) { dot.style.background = color; text.textContent = message; }
    function poll() {
        tries++;
        fetch(box.getAttribute('data-link-watch'), { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d.state === 'consumed') { show('#059669', 'Jeu connecté : votre téléphone a repris la session. Vous pouvez fermer cette page.'); return; }
                if (d.state === 'expired') { show('#dc2626', 'Le jeu n’a pas repris la session à temps. Dans Arma, demandez un nouveau code dans l’app Athena.'); return; }
                if (d.state === 'unknown') { show('#6b7280', 'Suivi indisponible. Vérifiez dans Arma, app Athena du téléphone.'); return; }
                if (tries < 150) { setTimeout(poll, 2000); } else { show('#6b7280', 'Toujours en attente du jeu. Vérifiez qu’Arma est ouvert et que COMSPEC Link est autorisé (BattlEye désactivé).'); }
            })
            .catch(function () { if (tries < 150) { setTimeout(poll, 4000); } });
    }
    setTimeout(poll, 1500);
})();
</script>
<?php endif; ?>

<?php require base_path('views/partials/account/shell_close.php'); ?>
