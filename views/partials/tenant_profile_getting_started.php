<?php
declare(strict_types=1);

use App\Services\Community\TenantTypeConfig;

/**
 * Tutoriel « Bien démarrer » d’un profil de communauté (Complet, Bureau des effectifs, Carte ATAK).
 * Chaque étape indique qui peut la faire (chaque membre, RH, Gestionnaire) ; le lien n’est proposé
 * qu’à ceux qui en ont le droit, les autres voient à qui s’adresser.
 * Repliable : le choix est gardé dans le navigateur (clé par profil).
 *
 * @var string $tpgType type de communauté
 * @var bool|null $tpgCollapsible repliable (tableau de bord) ; false = toujours ouvert (documentation)
 */

$tpgType = TenantTypeConfig::normalizeType((string) ($tpgType ?? 'full'));
$tpgGuide = TenantTypeConfig::profileGuide($tpgType);
$tpgCollapsible = ($tpgCollapsible ?? true) !== false;
$tpgH = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

$tpgCan = static fn (string $p): bool => function_exists('can') && can($p);
$tpgIsManager = $tpgCan('admin.organization') || $tpgCan('admin.access');
$tpgIsHr = $tpgIsManager || $tpgCan('invitations.send') || $tpgCan('admin.members.manage') || $tpgCan('personnel.profile.update');
$tpgViewerLevel = $tpgIsManager ? 'manager' : ($tpgIsHr ? 'hr' : 'member');
$tpgLevelLabel = ['member' => 'Membre', 'hr' => 'RH', 'manager' => 'Gestionnaire'];
$tpgAudienceLabel = ['member' => 'Chaque membre', 'hr' => 'RH ou Gestionnaire', 'manager' => 'Gestionnaire'];
?>
<section class="tpg" aria-labelledby="tpg-title-<?= $tpgH($tpgType) ?>">
    <details class="tpg__box"<?= $tpgCollapsible ? ' data-tpg-key="athena.tpg.' . $tpgH($tpgType) . '"' : '' ?> open>
        <summary class="tpg__head"<?= $tpgCollapsible ? '' : ' tabindex="-1"' ?>>
            <span>
                <span class="tpg__kicker">Bien démarrer · profil <?= $tpgH($tpgGuide['label']) ?></span>
                <span class="tpg__title" id="tpg-title-<?= $tpgH($tpgType) ?>"><?= $tpgH($tpgGuide['tagline']) ?></span>
            </span>
            <?php if ($tpgCollapsible): ?><span class="tpg__toggle" aria-hidden="true"></span><?php endif; ?>
        </summary>

        <p class="tpg__me">
            Votre niveau d’accès : <strong><?= $tpgH($tpgLevelLabel[$tpgViewerLevel]) ?></strong> —
            <?= $tpgH($tpgGuide['levels'][$tpgViewerLevel] ?? '') ?>
        </p>

        <ol class="tpg__steps">
            <?php foreach ($tpgGuide['steps'] as $i => $step): ?>
                <?php
                $need = (string) $step['audience'];
                $allowed = TenantTypeConfig::levelRank($tpgViewerLevel) >= TenantTypeConfig::levelRank($need)
                    && TenantTypeConfig::uriAllowed($tpgType, (string) $step['path']);
                ?>
                <li class="tpg__step<?= $allowed ? '' : ' is-locked' ?>">
                    <span class="tpg__num" aria-hidden="true"><?= (int) $i + 1 ?></span>
                    <span class="tpg__body">
                        <span class="tpg__step-title"><?= $tpgH($step['title']) ?></span>
                        <span class="tpg__step-text"><?= $tpgH($step['text']) ?></span>
                        <span class="tpg__who"><?= $tpgH($tpgAudienceLabel[$need] ?? '') ?></span>
                    </span>
                    <?php if ($allowed): ?>
                        <a class="tpg__go" href="<?= $tpgH(url((string) $step['path'])) ?>">Ouvrir<span class="tpg__sr"> : <?= $tpgH($step['title']) ?></span></a>
                    <?php else: ?>
                        <span class="tpg__lock">Réservé : <?= $tpgH(strtolower($tpgAudienceLabel[$need] ?? '')) ?></span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ol>

        <p class="tpg__foot">
            <a href="<?= $tpgH(url('documentation/profils')) ?>">Comprendre les profils et les niveaux d’accès</a>
            <?php if ($tpgIsManager): ?>
                · <a href="<?= $tpgH(url('back-office/organisation/parametres') . '?onglet=profil#profil') ?>">Changer de profil</a>
            <?php endif; ?>
        </p>
    </details>
</section>
<?php if (!defined('ATHENA_TPG_ASSETS')): define('ATHENA_TPG_ASSETS', true); ?>
<style>
.tpg { --tpg-line: #e2e8f0; --tpg-muted: #64748b; --tpg-ink: #0f172a; --tpg-accent: #059669; color: var(--tpg-ink); }
.tpg__box { border: 1px solid var(--tpg-line); border-radius: 1rem; background: #fff; padding: 0 1.1rem; }
.tpg__head { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; padding: 1rem 0; cursor: pointer; list-style: none; }
.tpg__head::-webkit-details-marker { display: none; }
.tpg__kicker { display: block; font-size: 0.68rem; font-weight: 800; letter-spacing: 0.16em; text-transform: uppercase; color: var(--tpg-accent); }
.tpg__title { display: block; margin-top: 0.2rem; font-size: 1rem; font-weight: 800; line-height: 1.35; }
.tpg__toggle { flex-shrink: 0; width: 1.6rem; height: 1.6rem; border-radius: 50%; border: 1px solid var(--tpg-line); position: relative; }
.tpg__toggle::before { content: ""; position: absolute; inset: 0; margin: auto; width: 0.45rem; height: 0.45rem; border-right: 2px solid var(--tpg-muted); border-bottom: 2px solid var(--tpg-muted); transform: translateY(-2px) rotate(45deg); }
.tpg__box[open] .tpg__toggle::before { transform: translateY(2px) rotate(-135deg); }
.tpg__me { margin: 0 0 0.8rem; padding: 0.55rem 0.75rem; border-radius: 0.6rem; background: #f8fafc; font-size: 0.84rem; color: #334155; }
.tpg__steps { list-style: none; margin: 0; padding: 0; display: grid; gap: 0.5rem; }
@media (min-width: 900px) { .tpg__steps { grid-template-columns: 1fr 1fr; } }
.tpg__step { display: flex; align-items: flex-start; gap: 0.7rem; padding: 0.7rem 0.75rem; border: 1px solid var(--tpg-line); border-radius: 0.75rem; }
.tpg__step.is-locked { background: #f8fafc; }
.tpg__step.is-locked .tpg__num { background: #cbd5e1; }
.tpg__num { flex-shrink: 0; display: grid; place-items: center; width: 1.6rem; height: 1.6rem; border-radius: 50%; background: var(--tpg-ink); color: #fff; font-size: 0.75rem; font-weight: 800; }
.tpg__body { flex: 1 1 auto; min-width: 0; display: grid; gap: 0.15rem; }
.tpg__step-title { font-size: 0.9rem; font-weight: 800; }
.tpg__step-text { font-size: 0.8rem; line-height: 1.45; color: #475569; }
.tpg__who { font-size: 0.68rem; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; color: var(--tpg-muted); }
.tpg__go { flex-shrink: 0; align-self: center; padding: 0.35rem 0.75rem; border-radius: 0.5rem; background: var(--tpg-accent); color: #fff; font-size: 0.78rem; font-weight: 800; text-decoration: none; white-space: nowrap; }
.tpg__go:hover { background: #047857; color: #fff; }
.tpg__go:focus-visible, .tpg__foot a:focus-visible, .tpg__head:focus-visible { outline: 2px solid var(--tpg-accent); outline-offset: 2px; }
.tpg__lock { flex-shrink: 0; align-self: center; font-size: 0.72rem; font-weight: 600; color: var(--tpg-muted); max-width: 8rem; text-align: right; }
.tpg__foot { margin: 0.8rem 0 1rem; font-size: 0.8rem; color: var(--tpg-muted); }
.tpg__foot a { color: #047857; font-weight: 700; text-decoration: none; }
.tpg__foot a:hover { text-decoration: underline; }
.tpg__sr { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; }
</style>
<script>
(function () {
    document.querySelectorAll('details[data-tpg-key]').forEach(function (d) {
        var key = d.getAttribute('data-tpg-key');
        try {
            if (localStorage.getItem(key) === 'closed') {
                d.open = false;
            }
        } catch (e) {}
        d.addEventListener('toggle', function () {
            try {
                localStorage.setItem(key, d.open ? 'open' : 'closed');
            } catch (e) {}
        });
    });
})();
</script>
<?php endif; ?>
