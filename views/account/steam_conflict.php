<?php
declare(strict_types=1);

/** Page de choix imposée : le Steam du joueur est aussi sur le compte Athena d'une autre personne. */
$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$steamEnd = (string) ($steamEnd ?? '');
$otherHints = is_array($otherHints ?? null) ? $otherHints : [];
$canKeep = !empty($canKeep);

$accountNavKey = 'steam-conflict';
$accountTitle = 'Votre compte Steam est relié à deux comptes Athena';
$accountLead = 'Choisissez lequel le garde. Tant que ce n’est pas fait, le jeu refuse la connexion par Steam et cette page revient à chaque visite.';
$accountHideDefaultActions = true;
require base_path('views/partials/account/shell_open.php');
?>
<style>
    .sdc-map { display: grid; grid-template-columns: auto 1fr; gap: .5rem 1rem; align-items: center; margin: 1rem 0 .25rem; }
    .sdc-steam { grid-row: span <?= 1 + count($otherHints) ?>; align-self: stretch; display: flex; align-items: center; padding: .75rem 1rem; border-radius: .75rem; background: #0f172a; color: #f8fafc; font-weight: 700; white-space: nowrap; }
    .sdc-acc { position: relative; padding: .6rem .9rem; border: 1px solid #cbd5e1; border-radius: .6rem; font-size: .875rem; color: #0f172a; }
    .sdc-acc::before { content: ""; position: absolute; left: -1rem; top: 50%; width: 1rem; border-top: 2px solid #e11d48; }
    .sdc-acc strong { display: block; }
    .sdc-acc span { color: #475569; }
    .sdc-choices { display: grid; gap: .875rem; }
    .sdc-choice { border: 1px solid #e2e8f0; border-radius: .75rem; padding: 1rem 1.1rem; }
    .sdc-choice h3 { margin: 0; font-size: 1rem; font-weight: 700; color: #0f172a; }
    .sdc-choice p { margin: .35rem 0 .8rem; font-size: .875rem; line-height: 1.55; color: #334155; max-width: 68ch; }
    .sdc-choice--main { border-color: #059669; box-shadow: inset 4px 0 0 #059669; }
    .sdc-note { font-size: .8125rem; color: #9f1239; margin: 0; }
    @media (max-width: 520px) {
        .sdc-map { grid-template-columns: 1fr; }
        .sdc-steam { grid-row: auto; }
        .sdc-acc::before { display: none; }
    }
</style>

<section class="account-hub__panel">
    <div class="account-hub__panel-head">
        <p class="account-hub__panel-kicker">Ce qui se passe</p>
        <h2 class="account-hub__panel-title">Le jeu ne sait pas quel compte ouvrir</h2>
        <p class="account-hub__panel-desc">
            Dans Arma 3, ATAK vous reconnaît grâce à votre compte Steam. Celui qui se termine par <strong><?= $h($steamEnd) ?></strong>
            est enregistré sur ce compte Athena, et aussi sur <?= count($otherHints) > 1 ? 'd’autres comptes Athena' : 'un autre compte Athena' ?>.
            Pour ne pas vous connecter au mauvais compte, le jeu refuse la connexion par Steam tant qu’un seul compte ne le porte pas.
        </p>
    </div>
    <div class="account-hub__panel-body">
        <div class="sdc-map" aria-label="Comptes reliés à ce compte Steam">
            <div class="sdc-steam">Steam …<?= $h($steamEnd) ?></div>
            <div class="sdc-acc"><strong>Ce compte</strong><span>celui avec lequel vous êtes connecté</span></div>
            <?php foreach ($otherHints as $hint): ?>
            <div class="sdc-acc"><strong>Autre compte Athena</strong><span><?= $h((string) $hint) ?></span></div>
            <?php endforeach; ?>
        </div>
        <p class="account-hub__hint">Cela arrive quand on a créé deux comptes avec deux adresses e-mail, ou quand quelqu’un a saisi ce compte Steam par erreur. Pour protéger l’autre personne, nous n’en montrons pas plus.</p>
    </div>
</section>

<section class="account-hub__panel">
    <div class="account-hub__panel-head">
        <p class="account-hub__panel-kicker">Votre choix</p>
        <h2 class="account-hub__panel-title">Quel compte garde Steam ?</h2>
    </div>
    <div class="account-hub__panel-body sdc-choices">
        <div class="sdc-choice sdc-choice--main">
            <h3>C’est mon compte Steam, je le garde ici</h3>
            <p>Vous vous connectez à Steam pour prouver qu’il est à vous. Il est alors retiré de l’autre compte Athena, qui reçoit un avis. Rien d’autre n’est supprimé.</p>
            <?php if ($canKeep): ?>
            <form method="post" action="<?= $h(url('account/steam-doublon/garder')) ?>">
                <?= \App\Core\Csrf::field() ?>
                <button type="submit" class="account-hub__btn account-hub__btn--ink">Prouver avec Steam et garder ici</button>
            </form>
            <?php else: ?>
            <p class="sdc-note">L’autre compte est suspendu : seule l’administration peut trancher. Choisissez « Je ne reconnais pas l’autre compte » pour la prévenir.</p>
            <?php endif; ?>
        </div>

        <div class="sdc-choice">
            <h3>Je garde mon autre compte Athena</h3>
            <p>Steam est retiré de ce compte-ci, puis vous êtes déconnecté pour vous connecter à l’autre. Le jeu ouvrira l’autre compte. Ce compte-ci reste intact, simplement sans Steam.</p>
            <form method="post" action="<?= $h(url('account/steam-doublon/autre-compte')) ?>" onsubmit="return confirm('Retirer Steam de ce compte et vous déconnecter ?');">
                <?= \App\Core\Csrf::field() ?>
                <button type="submit" class="account-hub__btn account-hub__btn--ghost">Retirer Steam d’ici et changer de compte</button>
            </form>
        </div>

        <div class="sdc-choice">
            <h3>Je ne reconnais pas l’autre compte</h3>
            <p>Steam est mis de côté sur ce compte et l’administration est prévenue pour vérifier. Rien n’est supprimé, et l’autre compte n’est pas touché sans preuve. Si ce compte Steam est bien le vôtre, le premier choix règle le problème tout de suite.</p>
            <form method="post" action="<?= $h(url('account/steam-doublon/signaler')) ?>">
                <?= \App\Core\Csrf::field() ?>
                <button type="submit" class="account-hub__btn account-hub__btn--ghost">Prévenir l’administration</button>
            </form>
        </div>
    </div>
</section>

<?php require base_path('views/partials/account/shell_close.php'); ?>
