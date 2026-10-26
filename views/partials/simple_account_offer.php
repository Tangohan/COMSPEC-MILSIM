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
.sao { margin: 1.25rem auto 0; max-width: 72rem; padding: 0 1.5rem; }
.sao__box { display: flex; flex-wrap: wrap; align-items: center; gap: 1rem 1.25rem; padding: 1rem 1.25rem; border: 1px solid #a7f3d0; border-radius: 1rem; background: linear-gradient(135deg, #ecfdf5 0%, #ffffff 70%); color: #0f172a; box-shadow: 0 1px 2px rgba(15, 23, 42, .05); }
.sao__icon { flex: 0 0 auto; display: grid; place-items: center; width: 2.75rem; height: 2.75rem; border-radius: .85rem; background: #059669; color: #fff; }
.sao__text { flex: 1 1 20rem; min-width: 0; }
.sao__kicker { margin: 0 0 .15rem; font-size: 10px; font-weight: 800; letter-spacing: .25em; text-transform: uppercase; color: #047857; }
.sao__title { margin: 0 0 .25rem; font-size: 1rem; font-weight: 800; }
.sao__lead { margin: 0; font-size: .85rem; line-height: 1.55; color: #475569; }
.sao__actions { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; }
.sao__actions form { margin: 0; }
.sao__btn { display: inline-flex; align-items: center; min-height: 2.5rem; padding: .55rem 1.1rem; border: 1px solid #059669; border-radius: .75rem; background: #059669; color: #fff; font: inherit; font-size: 11px; font-weight: 800; letter-spacing: .12em; text-transform: uppercase; cursor: pointer; transition: background .15s; }
.sao__btn:hover { background: #047857; border-color: #047857; }
.sao__btn--ghost { background: #fff; color: #334155; border-color: #cbd5e1; }
.sao__btn--ghost:hover { background: #f1f5f9; border-color: #94a3b8; }
@media (max-width: 640px) { .sao { padding: 0 1rem; } .sao__actions { width: 100%; } .sao__actions form, .sao__btn { flex: 1 1 auto; justify-content: center; } }
</style>
<section class="sao" aria-labelledby="sao-title">
    <div class="sao__box">
        <span class="sao__icon" aria-hidden="true">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/></svg>
        </span>
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
