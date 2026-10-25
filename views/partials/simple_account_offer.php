<?php
/**
 * Proposition « passer en compte simple » : responsable resté seul membre de son organisation.
 *
 * @var array{tenant_id: int, tenant_name: string}|null $simple_account_offer
 */
$simpleOffer = is_array($simple_account_offer ?? null) ? $simple_account_offer : null;
if ($simpleOffer === null) {
    return;
}
$sao = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$saoCsrf = \App\Core\Csrf::token();
?>
<style>
.sao { margin: 1rem auto 0; max-width: 100rem; padding: 0 1rem; }
.sao__box { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 1rem; padding: 1.1rem 1.25rem; border: 1px solid #d4d4d4; border-left: 3px solid #047857; background: #ffffff; color: #161616; }
.sao__text { flex: 1 1 22rem; min-width: 0; }
.sao__kicker { margin: 0 0 0.25rem; font-size: 10px; font-weight: 700; letter-spacing: 0.2em; text-transform: uppercase; color: #047857; }
.sao__title { margin: 0 0 0.3rem; font-size: 1rem; font-weight: 800; }
.sao__lead { margin: 0; font-size: 0.86rem; line-height: 1.55; color: #4a4a4a; }
.sao__actions { display: flex; flex-wrap: wrap; gap: 0.5rem; }
.sao__actions form { margin: 0; }
.sao__btn { display: inline-flex; align-items: center; padding: 0.65rem 1rem; border: 1px solid #161616; background: #161616; color: #fff; font: inherit; font-size: 11px; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; cursor: pointer; }
.sao__btn:hover { background: #047857; border-color: #047857; }
.sao__btn--ghost { background: transparent; color: #161616; border-color: #d4d4d4; }
.sao__btn--ghost:hover { background: #f6f6f6; color: #161616; border-color: #d4d4d4; }
</style>
<section class="sao" aria-labelledby="sao-title">
    <div class="sao__box">
        <div class="sao__text">
            <p class="sao__kicker">Compte simple</p>
            <h2 class="sao__title" id="sao-title">Vous êtes le seul membre de <?= $sao($simpleOffer['tenant_name']) ?></h2>
            <p class="sao__lead">Si vous utilisez Athena seul, passez en compte simple : l’organisation est archivée (rien n’est supprimé, l’équipe Athena peut la restaurer) et votre compte reste le même, sans organisation à gérer.</p>
        </div>
        <div class="sao__actions">
            <form method="post" action="<?= $sao(url('account/compte-simple')) ?>"
                  onsubmit="return confirm('Archiver <?= $sao(addslashes($simpleOffer['tenant_name'])) ?> et passer en compte simple ? Vos données sont conservées et l’organisation peut être restaurée par l’équipe Athena.');">
                <input type="hidden" name="_csrf_token" value="<?= $sao($saoCsrf) ?>">
                <button type="submit" class="sao__btn">Passer en compte simple</button>
            </form>
            <form method="post" action="<?= $sao(url('account/compte-simple/plus-tard')) ?>">
                <input type="hidden" name="_csrf_token" value="<?= $sao($saoCsrf) ?>">
                <button type="submit" class="sao__btn sao__btn--ghost">Garder mon organisation</button>
            </form>
        </div>
    </div>
</section>
