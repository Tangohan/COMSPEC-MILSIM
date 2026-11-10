<?php
declare(strict_types=1);

use App\Support\SseIdCountryCatalog as K;
use App\Support\SseIdentityCatalog as C;
use App\Support\SseIdRealCountryCatalog as RC;

ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/** @var list<array<string,mixed>> $catalog SseIdentityRegistryController::realCatalogPreview() */
/** @var array<string,array<string,mixed>> $present pays déjà dans la communauté (code => pays) */
require __DIR__ . '/_subnav.php';
$sseExtraStyles .= '<link rel="stylesheet" href="' . $h(asset_url('assets/css/sse-id-real-countries.css')) . '">';
?>

<div class="page-heading">
    <div>
        <h1>Ajouter des pays réels (simulation)</h1>
        <p>Pour une communauté qui joue un univers réaliste : des pays réels stylisés, proposés et jamais imposés. Leurs papiers restent des documents de jeu.</p>
    </div>
</div>

<section class="panel idr-rules" role="note">
    <div class="panel-header"><div class="panel-title"><span class="panel-index">!</span> Garde-fous</div><div class="panel-meta">Non négociables</div></div>
    <div class="panel-body">
        <ul>
            <li>Gabarit générique par zone (Europe bordeaux, Amériques bleu, autres vert ou noir) : aucune reproduction des vrais modèles, armoiries, emblèmes, hologrammes, motifs de sécurité, polices ou mises en page officielles.</li>
            <li>Mention « <?= $h(RC::FACE_MENTION) ?> » sur chaque face ; l’image exportée garde le filigrane SIMULATION en diagonale et le bandeau « <?= $h(RC::EXPORT_BAND) ?> ».</li>
            <li>Numéros au format du jeu (code du pays, une lettre, six chiffres) : jamais le format exact d’un vrai titre. Le code de la bande de lecture est celui de l’OACI (Allemagne : « D<< »).</li>
            <li>Photos : uniquement des personnages du jeu.</li>
        </ul>
    </div>
</section>

<form method="post" action="<?= $h(url('atak/sse/papiers/pays/reels')) ?>">
    <?= \App\Core\Csrf::field() ?>
    <section class="panel">
        <div class="panel-header"><div class="panel-title"><span class="panel-index">01</span> Univers de la communauté</div></div>
        <div class="panel-body">
            <label class="idr-choice"><input type="radio" name="universe" value="<?= $h(RC::UNIVERSE) ?>" required> <strong><?= $h(K::THEMES[RC::UNIVERSE]) ?></strong> — notre communauté joue dans le monde réel, avec des papiers de jeu stylisés.</label>
            <label class="idr-choice"><input type="checkbox" name="guardrails" value="1" required> Nous nous engageons : documents de jeu marqués SIMULATION, sans valeur, gabarits génériques, photos de personnages du jeu uniquement.</label>
        </div>
    </section>

    <section class="panel">
        <div class="panel-header"><div class="panel-title"><span class="panel-index">02</span> Pays à ajouter</div><div class="panel-meta"><?= count($catalog) ?> pays proposés</div></div>
        <div class="panel-body">
            <div class="idr-grid">
                <?php foreach ($catalog as $p): ?>
                    <?php
                    $have = $present[$p['code']] ?? null;
                    $state = $have === null ? '' : (empty($have['real_sim']) ? 'Code déjà pris par un pays de la communauté' : ($have['active'] ? 'Déjà ajouté' : 'Archivé : sera rétabli'));
                    $isPassport = $p['specimen_type'] === 'passport';
                    ?>
                    <label class="idr-card<?= $have !== null && ($have['active'] || empty($have['real_sim'])) ? ' is-present' : '' ?>">
                        <span class="idr-card__head">
                            <input type="checkbox" name="codes[]" value="<?= $h($p['code']) ?>"<?= $have !== null && ($have['active'] || empty($have['real_sim'])) ? ' disabled' : '' ?>>
                            <img class="idr-flag" src="<?= $h((string) $p['flag']) ?>" alt="">
                            <span><strong><?= $h($p['name']) ?></strong> <span class="muted" translate="no">· <?= $h($p['name_en']) ?> · <?= $h($p['name_native']) ?></span></span>
                        </span>
                        <span class="idr-spec idr-zone--<?= $h($p['zone']) ?>" style="--idr-c1: <?= $h($p['color_primary']) ?>; --idr-c2: <?= $h($p['color_secondary']) ?>;" aria-label="Aperçu du gabarit stylisé">
                            <span class="idr-spec__band"><img src="<?= $h((string) $p['flag']) ?>" alt=""><b translate="no"><?= $h(mb_strtoupper((string) $p['name_native'])) ?></b><i translate="no"><?= $isPassport ? 'PASSEPORT · PASSPORT' : 'PERMIS · LICENCE' ?></i></span>
                            <span class="idr-spec__body">
                                <span class="idr-spec__photo" aria-hidden="true"></span>
                                <span class="idr-spec__lines" translate="no"><b>SPECIMEN</b><span>N° <?= $h($p['specimen_number']) ?></span><span><?= $h($p['authority']) ?></span></span>
                            </span>
                            <span class="idr-spec__mrz" translate="no"><?= $h(implode("\n", $p['mrz'])) ?></span>
                            <span class="idr-spec__mention" translate="no"><?= $h(RC::FACE_MENTION) ?></span>
                            <span class="idr-spec__wm" aria-hidden="true"><span>SIMULATION</span><span>SIMULATION</span><span>SIMULATION</span><span>SIMULATION</span></span>
                        </span>
                        <span class="idr-meta">
                            <span>Code <b translate="no"><?= $h($p['code']) ?></b> · MRZ <b translate="no"><?= $h(str_pad((string) $p['mrz_code'], 3, '<')) ?></b> · Numéros <b translate="no"><?= $h($p['number_format']) ?></b></span>
                            <span><?= $h(implode(', ', array_map(static fn (string $l): string => C::label(K::LANGUAGES, $l), $p['languages']))) ?> · <?= $h($p['zone_label']) ?></span>
                            <span><?= $h(implode(' · ', array_map(static fn (string $t, int $y): string => C::label(C::DOC_TYPES, $t) . ' ' . $y . ' ans', array_keys($p['validity']), array_values($p['validity'])))) ?></span>
                            <?php if ($state !== ''): ?><span class="badge badge--gray"><?= $h($state) ?></span><?php endif; ?>
                        </span>
                    </label>
                <?php endforeach; ?>
            </div>
            <div class="idp-actions" style="margin-top:12px">
                <button class="btn" type="submit">Ajouter les pays cochés</button>
                <a class="btn btn--ghost" href="<?= $h(url('atak/sse/papiers/pays')) ?>">Annuler</a>
            </div>
            <p class="muted" style="margin-top:8px">Les pays fictifs (Altis, Tanoa, Livonie, Malden…) ne changent pas. Un pays ajouté s’archive comme les autres ; ses papiers partent au serveur de jeu avec le paquet de la communauté.</p>
        </div>
    </section>
</form>
<?php
$sseContent = ob_get_clean();
require dirname(__DIR__) . '/_layout.php';
