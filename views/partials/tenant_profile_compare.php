<?php
declare(strict_types=1);

use App\Services\Community\TenantTypeConfig;

/**
 * Comparatif des profils de communauté : modules inclus (✓) ou masqués (—), à qui s’adresse chaque
 * profil. Utilisé par Paramètres → Profil et la page /documentation/profils.
 *
 * @var string|null $tpcCurrentType profil actuel à mettre en avant (null : aucun)
 */

$tpcCurrent = isset($tpcCurrentType) && $tpcCurrentType !== null ? TenantTypeConfig::normalizeType((string) $tpcCurrentType) : null;
$tpcTypes = [TenantTypeConfig::TYPE_ATAK, TenantTypeConfig::TYPE_EFFECTIFS, TenantTypeConfig::TYPE_FULL];
$tpcAllowed = TenantTypeConfig::allowedModulesByType();
$tpcH = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<div class="tpc">
    <div class="tpc__wrap">
        <table class="tpc__table">
            <caption class="tpc__sr">Modules inclus dans chaque profil de communauté</caption>
            <thead>
                <tr>
                    <th scope="col" class="tpc__mod">Module</th>
                    <?php foreach ($tpcTypes as $t): ?>
                        <?php $g = TenantTypeConfig::profileGuide($t); ?>
                        <th scope="col" class="tpc__type<?= $t === $tpcCurrent ? ' is-current' : '' ?>">
                            <span class="tpc__type-name"><?= $tpcH($g['label']) ?></span>
                            <?php if ($t === $tpcCurrent): ?><span class="tpc__badge">Profil actuel</span><?php endif; ?>
                        </th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach (TenantTypeConfig::moduleCatalog() as $mod => $meta): ?>
                    <tr>
                        <th scope="row" class="tpc__mod">
                            <span class="tpc__mod-name"><?= $tpcH($meta['label']) ?></span>
                            <span class="tpc__mod-desc"><?= $tpcH($meta['description']) ?></span>
                        </th>
                        <?php foreach ($tpcTypes as $t): ?>
                            <?php $in = in_array($mod, $tpcAllowed[$t] ?? [], true); ?>
                            <td class="tpc__cell<?= $t === $tpcCurrent ? ' is-current' : '' ?>">
                                <?php if ($in): ?>
                                    <span class="tpc__yes" aria-label="Inclus">✓</span>
                                <?php else: ?>
                                    <span class="tpc__no" aria-label="Non inclus">—</span>
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
                <tr class="tpc__for">
                    <th scope="row" class="tpc__mod"><span class="tpc__mod-name">Pour qui ?</span></th>
                    <?php foreach ($tpcTypes as $t): ?>
                        <td class="tpc__cell tpc__cell--text<?= $t === $tpcCurrent ? ' is-current' : '' ?>"><?= $tpcH(TenantTypeConfig::profileGuide($t)['ideal_for'][0] ?? '') ?></td>
                    <?php endforeach; ?>
                </tr>
            </tbody>
        </table>
    </div>
    <p class="tpc__note">Changer de profil ne supprime aucune donnée : les modules hors profil sont seulement masqués, et réapparaissent tels quels si vous revenez au profil Complet.</p>
</div>
<?php if (!defined('ATHENA_TPC_ASSETS')): define('ATHENA_TPC_ASSETS', true); ?>
<style>
.tpc { color: #0f172a; }
.tpc__wrap { overflow-x: auto; border: 1px solid #e2e8f0; border-radius: 0.9rem; background: #fff; }
.tpc__table { width: 100%; min-width: 40rem; border-collapse: collapse; font-size: 0.85rem; }
.tpc__table th, .tpc__table td { padding: 0.6rem 0.75rem; border-bottom: 1px solid #e2e8f0; text-align: center; vertical-align: middle; }
.tpc__table tbody tr:last-child th, .tpc__table tbody tr:last-child td { border-bottom: 0; }
.tpc__table thead th { background: #f8fafc; font-size: 0.8rem; }
.tpc__mod { text-align: left !important; width: 46%; }
.tpc__mod-name { display: block; font-weight: 700; }
.tpc__mod-desc { display: block; margin-top: 0.1rem; font-size: 0.75rem; font-weight: 500; color: #64748b; }
.tpc__type-name { display: block; font-weight: 800; }
.tpc__badge { display: inline-block; margin-top: 0.2rem; border-radius: 999px; background: #059669; color: #fff; padding: 0.05rem 0.5rem; font-size: 0.65rem; font-weight: 800; }
.is-current { background: rgba(5, 150, 105, 0.06); }
.tpc__table thead th.is-current { background: rgba(5, 150, 105, 0.12); }
.tpc__yes { color: #047857; font-weight: 900; font-size: 1rem; }
.tpc__no { color: #cbd5e1; font-weight: 700; }
.tpc__cell--text { font-size: 0.75rem; color: #475569; text-align: left !important; vertical-align: top !important; }
.tpc__note { margin: 0.6rem 0 0; font-size: 0.8rem; color: #64748b; }
.tpc__sr { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; }
</style>
<?php endif; ?>
