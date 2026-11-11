<?php
declare(strict_types=1);
/**
 * Photothèque (planche de photos signalétiques) : « qui ressemble à ça ? » d'après le signalement d'un témoin. Filtres
 * instantanés (sexe, âge, taille, corpulence, cheveux, yeux, signes), comparaison de deux fiches côte à côte, envoi
 * d'une photo et rattachement à une fiche. Aucune reconnaissance faciale.
 *
 * @var array<string,string> $params
 * @var array<string,mixed> $result
 * @var list<array<string,mixed>> $unattached
 * @var bool $photoUpload
 */
use App\Support\SseFileSubjectCatalog as S;
use App\Support\SseRecordsFormat as RF;

ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
require __DIR__ . '/_nav.php';
$p = $params;
$sel = static fn (string $a, string $b): string => $a === $b ? ' selected' : '';
$csrf = '<input type="hidden" name="_csrf_token" value="' . $h($csrfToken) . '">';
$all = ($_GET['toutes'] ?? '') === '1';
?>
<div class="fx">
    <div class="fx-th">
        <div><h1>Photothèque</h1><p>Faites défiler les photos signalétiques filtrées par le signalement d’un témoin, puis comparez deux fiches côte à côte. Le site ne fait aucune reconnaissance faciale : la comparaison se fait à l’œil.</p></div>
    </div>
    <div class="fx-wk">
        <div class="fx-col">
            <form class="fx-card" method="get" action="<?= $h(url('atak/sse/fichiers/photos')) ?>" data-fx-instant aria-label="Filtres de la photothèque">
                <div class="fx-ch"><h2>Signalement</h2><a href="<?= $h(url('atak/sse/fichiers/photos')) ?>">Réinitialiser les filtres</a></div>
                <div class="fx-fs">
                    <div class="fx-f"><span>Sexe</span><div class="fx-seg" role="radiogroup" aria-label="Sexe"><?php foreach (['' => 'Indifférent', 'M' => 'Homme', 'F' => 'Femme'] as $v => $l): ?><label><input type="radio" name="sex" value="<?= $h($v) ?>"<?= $p['sex'] === $v ? ' checked' : '' ?>><span><?= $h($l) ?></span></label><?php endforeach; ?></div></div>
                    <div class="fx-g2">
                        <div class="fx-f"><span>Âge</span><div class="fx-rng"><input class="fx-in mono" name="age_min" value="<?= $h($p['age_min']) ?>" placeholder="min" aria-label="Âge minimal"><span>à</span><input class="fx-in mono" name="age_max" value="<?= $h($p['age_max']) ?>" placeholder="max" aria-label="Âge maximal"></div><small>Ex. 30 à 40.</small></div>
                        <div class="fx-f"><span>Taille</span><div class="fx-rng"><input class="fx-in mono" name="h_min" value="<?= $h($p['h_min']) ?>" placeholder="min" aria-label="Taille minimale"><span>à</span><input class="fx-in mono" name="h_max" value="<?= $h($p['h_max']) ?>" placeholder="max" aria-label="Taille maximale"></div><small>cm ou « 5'10" ».</small></div>
                    </div>
                    <div class="fx-g3">
                        <label class="fx-f"><span>Corpulence</span><select class="fx-in" name="build"><option value="">Indifférente</option><?php foreach (S::BUILDS as $k => $l): ?><option value="<?= $h($k) ?>"<?= $sel($p['build'], $k) ?>><?= $h($l) ?></option><?php endforeach; ?></select></label>
                        <label class="fx-f"><span>Cheveux</span><select class="fx-in" name="hair"><option value="">Indifférents</option><?php foreach (S::HAIR as $k => $l): ?><option value="<?= $h($k) ?>"<?= $sel($p['hair'], $k) ?>><?= $h($l) ?></option><?php endforeach; ?></select></label>
                        <label class="fx-f"><span>Yeux</span><select class="fx-in" name="eyes"><option value="">Indifférents</option><?php foreach (S::EYES as $k => $l): ?><option value="<?= $h($k) ?>"<?= $sel($p['eyes'], $k) ?>><?= $h($l) ?></option><?php endforeach; ?></select></label>
                    </div>
                    <label class="fx-f"><span>Signes particuliers</span><input class="fx-in" name="marks" value="<?= $h($p['marks']) ?>" placeholder="ex. barbe, tatouage"></label>
                    <div class="fx-tg"><input type="hidden" name="toutes" value=""><label class="fx-sw"><input type="checkbox" name="toutes" value="1"<?= $all ? ' checked' : '' ?>><i></i>Montrer aussi les fiches sans photo</label></div>
                </div>
                <div class="fx-ff"><button class="btn pri" type="submit"><svg class="i"><use href="#fx-grid"></use></svg><span>Afficher la planche</span></button></div>
            </form>

            <?php if ($photoUpload): ?>
                <form class="fx-card" method="post" action="<?= $h(url('atak/sse/fichiers/photos')) ?>" enctype="multipart/form-data"><?= $csrf ?>
                    <input type="hidden" name="back" value="atak/sse/fichiers/photos">
                    <div class="fx-ch"><h2>Envoyer une photo</h2></div>
                    <div class="fx-fs">
                        <label class="fx-drop"><input type="file" name="photo" accept="image/jpeg,image/png,image/webp" required><b>Déposez une photo</b><span>Capture prise en jeu (app Photo de l’ATAK), photo de papiers. JPEG, PNG ou WebP.</span></label>
                        <label class="fx-f"><span>Légende</span><input class="fx-in" name="caption" maxlength="255" placeholder="ex. homme au checkpoint nord, 09:12Z"></label>
                        <small class="fx-help">La photo est rangée telle quelle, sans reconnaissance faciale ; rattachez-la à une fiche quand vous savez qui c’est.</small>
                    </div>
                    <div class="fx-ff"><button class="btn" type="submit"><svg class="i"><use href="#fx-up"></use></svg>Envoyer</button></div>
                </form>
            <?php endif; ?>
        </div>

        <section class="fx-card fx-out" aria-live="polite" aria-label="Planche">
            <div class="fx-rh"><span class="fx-tag">PHOTO</span><b><?= (int) $result['total'] ?> fiche<?= $result['total'] > 1 ? 's' : '' ?></b><small>cochez deux fiches pour les comparer</small></div>
            <?php if ($result['hits'] === []): ?>
                <div class="fx-nr"><b>AUCUNE PHOTO</b><p>Aucune fiche avec photo ne correspond à ce signalement. Retirez un critère, ou montrez aussi les fiches sans photo.</p></div>
            <?php else: ?>
                <form method="get" action="<?= $h(url('atak/sse/fichiers/comparer')) ?>" data-fx-compare>
                    <div class="fx-book">
                        <?php foreach ($result['hits'] as $hit): ?>
                            <?php $i = $hit['identity']; $pp = $hit['profile'] ?? []; $src = isset($hit['photo']['path']) ? user_media_public_url((string) $hit['photo']['path']) : (($i['photo_path'] ?? null) ? user_media_public_url((string) $i['photo_path']) : null); $cc = (string) ($pp['country'] ?? $i['nationality'] ?? 'USA'); ?>
                            <div class="fx-pc">
                                <a class="ph" href="<?= $h(url('atak/sse/fichiers/sujet/' . (int) $i['id'])) ?>"><?php if ($src !== null): ?><img src="<?= $h($src) ?>" alt="Photo de <?= $h($i['display_name']) ?>" loading="lazy"><?php else: ?><?= $h(mb_strtoupper(mb_substr((string) $i['first_name'], 0, 1) . mb_substr((string) $i['last_name'], 0, 1))) ?><?php endif; ?></a>
                                <b translate="no"><?= $h($i['display_name']) ?></b>
                                <small><?= $h($i['sex'] ?? 'X') ?><?= ($pp['height_cm'] ?? null) !== null ? ' · ' . $h(RF::height((int) $pp['height_cm'], $cc)) : '' ?></small>
                                <label><input type="checkbox" name="pick[]" value="<?= (int) $i['id'] ?>"> Comparer</label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <input type="hidden" name="a" value=""><input type="hidden" name="b" value="">
                    <div class="fx-ff"><button class="btn" type="submit" data-fx-compare-go disabled>Comparer les deux fiches cochées</button></div>
                </form>
                <?php if ($result['pages'] > 1): ?>
                    <nav class="fx-pager" aria-label="Pages"><span>Page <?= (int) $result['page'] ?> sur <?= (int) $result['pages'] ?></span><span>
                        <?php if ($result['page'] > 1): ?><a class="btn sm" href="<?= $h(url('atak/sse/fichiers/photos') . '?' . http_build_query(array_filter($p) + ['page' => $result['page'] - 1, 'toutes' => $all ? '1' : ''])) ?>">Précédente</a><?php endif; ?>
                        <?php if ($result['page'] < $result['pages']): ?><a class="btn sm" href="<?= $h(url('atak/sse/fichiers/photos') . '?' . http_build_query(array_filter($p) + ['page' => $result['page'] + 1, 'toutes' => $all ? '1' : ''])) ?>">Suivante</a><?php endif; ?>
                    </span></nav>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ($unattached !== []): ?>
                <div class="fx-ch" style="border-top:1px solid var(--fx-ln)"><h2>Photos à rattacher</h2><small><?= count($unattached) ?></small></div>
                <div class="fx-book">
                    <?php foreach ($unattached as $ph): ?>
                        <div class="fx-pc">
                            <span class="ph"><img src="<?= $h(user_media_public_url((string) $ph['path'])) ?>" alt="<?= $h($ph['caption'] ?? 'Photo à rattacher') ?>" loading="lazy"></span>
                            <small translate="no"><?= $h($ph['caption'] ?? substr((string) $ph['created_at'], 0, 16)) ?></small>
                            <form method="post" action="<?= $h(url('atak/sse/fichiers/photos/' . (int) $ph['id'] . '/rattacher')) ?>" style="display:flex;gap:6px"><?= $csrf ?>
                                <input class="fx-in mono" name="target" placeholder="IDT-…" aria-label="Fiche à rattacher" style="height:32px">
                                <button class="btn sm" type="submit">OK</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>
</div>
<?php
$sseContent = ob_get_clean();
require dirname(__DIR__) . '/_layout.php';
