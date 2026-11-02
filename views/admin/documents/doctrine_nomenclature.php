<?php
declare(strict_types=1);

/**
 * Doctrine > Nomenclature : domaines (OPS, RH…) qui composent la référence des documents.
 *
 * @var list<array<string, mixed>> $domains
 */

require_once base_path('views/admin/documents/partials/doctrine_ui.php');

$domains = is_array($domains ?? null) ? $domains : [];
$csrf = dct_e((string) ($csrf_token ?? ''));
$can = is_array($can ?? null) ? $can : [];
$back = '<a class="dct-btn" href="' . dct_e(url('back-office/doctrine')) . '">' . dct_icon('back') . '<span>Retour à la doctrine</span></a>';
$headHtml = dct_head(
    'Opérations · Doctrine',
    'Nomenclature',
    'Les domaines classent vos documents par grand sujet (opérations, personnel, formation…). Un document rangé dans un domaine reçoit une référence de la forme ABRÉVIATION/CODE/ANNÉE-NUMÉRO, par exemple S3/OPS/2026-004.',
    $back,
    'nomenclature',
    $can
    );
?>
<div class="dct">
    <?= $headHtml ?>
    <?= dct_flashes() ?>

    <div class="dct-split">
        <section class="dct-panel" aria-labelledby="dct-nom-title">
            <h2 id="dct-nom-title" class="dct-sr">Domaines enregistrés</h2>
            <?php if ($domains === []): ?>
                <div class="dct-empty">
                    <div class="dct-empty__ill"><?= dct_icon('layers', 'dct-i--lg') ?></div>
                    <b>Aucun domaine pour l’instant</b>
                    <p>Sans domaine, Athena numérote chaque document d’après son type, par exemple NS-2026-004. Ajoutez des domaines (Opérations, Personnel…) pour classer et numéroter par sujet.</p>
                </div>
            <?php else: ?>
                <div class="dct-table-wrap" style="border:0;border-radius:0">
                    <table class="dct-table">
                        <thead><tr><th scope="col">Domaine</th><th scope="col">Code</th><th scope="col">Abréviation</th><th scope="col">Sous-domaines</th><th scope="col">État</th></tr></thead>
                        <tbody>
                        <?php foreach ($domains as $d): ?>
                            <tr>
                                <td><b style="display:inline-flex;gap:8px;align-items:center"><?= dct_swatch(preg_match('/^#[0-9a-fA-F]{3,8}$/', (string) ($d['color'] ?? '')) === 1 ? (string) $d['color'] : '') ?><?= dct_e((string) ($d['label'] ?? '')) ?></b></td>
                                <td class="dct-mono" translate="no"><?= dct_e((string) ($d['code'] ?? '')) ?></td>
                                <td class="dct-mono" translate="no"><b><?= dct_e((string) ($d['doc_prefix'] ?? '')) ?></b></td>
                                <td>
                                    <?php $subs = is_array($d['subdomains'] ?? null) ? $d['subdomains'] : []; ?>
                                    <?php if ($subs === []): ?><span class="dct-hint">—</span><?php endif; ?>
                                    <?php foreach ($subs as $s): ?>
                                        <small><span class="dct-mono" translate="no"><?= dct_e((string) ($s['code'] ?? '')) ?></span> · <?= dct_e((string) ($s['label'] ?? '')) ?></small>
                                    <?php endforeach; ?>
                                </td>
                                <td><?= array_key_exists('is_active', $d) && empty($d['is_active']) ? dct_state_pill('archived', 'Désactivé') : dct_state_pill('published', 'Actif') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <aside class="dct-aside">
            <form method="post" action="<?= dct_e(url('back-office/documents/nomenclature')) ?>" class="dct-card">
                <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
                <h2 class="dct-card__t">Ajouter un domaine</h2>
                <p class="dct-card__lead">Court et stable : une référence déjà attribuée ne change pas si vous renommez le domaine.</p>
                <label class="dct-fl"><span>Libellé</span><input name="label" required maxlength="120" placeholder="Opérations"></label>
                <div class="dct-grid dct-grid--2">
                    <label class="dct-fl"><span>Code</span><input class="dct-mono" name="code" required maxlength="32" placeholder="OPS"></label>
                    <label class="dct-fl"><span>Abréviation</span><input class="dct-mono" name="doc_prefix" required maxlength="16" placeholder="OPS"></label>
                </div>
                <label class="dct-fl"><span>Couleur <small>repère dans les listes</small></span><input type="color" name="color" value="#059669"></label>
                <div class="dct-form__f"><button type="submit" class="dct-btn is-dark"><?= dct_icon('plus') ?><span>Ajouter</span></button></div>
            </form>
            <div class="dct-card">
                <h2 class="dct-card__t">Lire une référence</h2>
                <p class="dct-ref-preview" translate="no">S3/OPS/2026-004</p>
                <ol class="dct-stepbar">
                    <li><span><b>S3</b> : l’abréviation du domaine (ou celle de l’unité émettrice, si elle en a une)</span></li>
                    <li><span><b>OPS</b> : le code du domaine, ou du sous-domaine choisi</span></li>
                    <li><span><b>2026-004</b> : l’année puis le numéro d’ordre, attribué automatiquement</span></li>
                </ol>
            </div>
        </aside>
    </div>
</div>
