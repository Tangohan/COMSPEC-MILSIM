<?php
declare(strict_types=1);
/**
 * Générer du contenu (gestion des fichiers SSE) : choix des kits (pays des papiers) et des réseaux, réglages, aperçu sans
 * écriture, création d'un lot, liste des lots et suppression en bloc. Tout ce qui est créé ici est fictif.
 *
 * @var array<string, array{kit: array<string,mixed>, sizes: array<string,int>}> $kits
 * @var array<string, array<string,mixed>> $themes
 * @var array<string,mixed> $params paramètres normalisés
 * @var int $seed graine de l'aperçu affiché (0 : aucun aperçu)
 * @var array<string,mixed>|null $preview
 * @var list<array<string,mixed>> $batches
 * @var array{community: int, pack: int} $room
 * @var list<array<string,mixed>> $sessions sessions de lore de la communauté
 * @var array{people: int, community: int} $limits
 */
use App\Support\SseFileSubjectCatalog as S;
use App\Support\SseIdentityCatalog as IC;
use App\Support\SseIdWorkshopCatalog as W;
use App\Support\SseKitCatalog as K;

ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
require __DIR__ . '/_nav.php';
$sseExtraStyles = ($sseExtraStyles ?? '') . '<link rel="stylesheet" href="' . $h(asset_url('assets/css/sse-generate.css')) . '">';
$sseExtraScripts = ($sseExtraScripts ?? '') . '<script defer src="' . $h(asset_url('assets/js/sse-generate.js')) . '"></script>';
$csrf = '<input type="hidden" name="_csrf_token" value="' . $h($csrfToken) . '">';
$uiLang = locale() === 'en' ? 'en' : 'fr';
$base = url('atak/sse/fichiers/generer');
$civil = $themes[K::CIVIL_THEME] ?? null;
$sliders = [
    'people' => ['Nombre de personnes', 1, (int) $limits['people'], 1, '', 'Deux cents personnes au plus par lot.'],
    'civil_pct' => ['Part de population civile', 0, 100, 5, ' %', 'Familles et habitants sans lien avec un réseau : le bruit de fond. Sans réseau choisi, tout le lot est civil.'],
    'link_density' => ['Densité des liens', 0, 100, 5, ' %', 'Associés dans une organisation, contacts entre organisations, parentés entre réseau et population.'],
    'alias_pct' => ['Membres connus sous un surnom', 0, 100, 5, ' %', 'Part des membres de réseau qui portent un alias ; les chefs d’abord.'],
    'forged_pct' => ['Membres porteurs d’un faux papier', 0, 100, 5, ' %', 'Un papier volontairement discordant : dates impossibles, numéro hors format, autorité d’un autre pays, faux nom ou photo remplacée.'],
    'wanted_pct' => ['Membres recherchés', 0, 100, 5, ' %', 'Part des membres de réseau placés parmi les recherchés ; les plus menaçants d’abord.'],
    'vehicles_pp' => ['Véhicules pour dix personnes', 0, 20, 1, '', 'Un véhicule par foyer d’abord, puis aux membres de réseau. Plaque au format du pays.'],
    'docs_pp' => ['Papiers par personne', 1, 4, 1, '', 'Carte d’identité, puis permis et carte grise des conducteurs, passeport, carte militaire selon le rôle.'],
];
$hiddenParams = static function (array $p, int $seed) use ($h): string {
    $out = '<input type="hidden" name="seed" value="' . $seed . '">';
    foreach (['kits', 'themes'] as $k) {
        foreach ($p[$k] as $v) {
            $out .= '<input type="hidden" name="' . $k . '[]" value="' . $h($v) . '">';
        }
    }
    foreach (['people', 'civil_pct', 'link_density', 'forged_pct', 'alias_pct', 'wanted_pct', 'vehicles_pp', 'docs_pp', 'lang', 'label', 'op_key', 'lore_session_id'] as $k) {
        $out .= '<input type="hidden" name="' . $k . '" value="' . $h($p[$k]) . '">';
    }

    return $out;
};
$summary = $preview['summary'] ?? null;
$blockers = $preview['blockers'] ?? [];
?>
<div class="fx fx-gen">
    <div class="fx-th">
        <div>
            <h1>Générer du contenu</h1>
            <p>Peuplez votre communauté avec un réseau fictif cohérent : familles qui partagent nom et adresse, organisations inventées avec leur hiérarchie, véhicules, téléphones et papiers au format du pays. Un aperçu d’abord, puis un lot que vous pouvez supprimer en bloc.</p>
        </div>
        <div class="fx-gen-room">
            <span>Place restante dans la communauté : <b><?= (int) $room['community'] ?></b></span>
            <span>Papiers encore possibles dans le paquet du jeu : <b><?= (int) $room['pack'] ?></b></span>
        </div>
    </div>

    <div class="fx-wk fx-gen-wk">
        <form class="fx-card" method="post" action="<?= $h($base) ?>" data-gen-form><?= $csrf ?>
            <div class="fx-ch"><h2>Kits par pays</h2><small><?= count($kits) ?></small></div>
            <div class="fx-fs">
                <p class="fx-help">Un kit donne les prénoms et noms du pays, ses lieux inventés, ses véhicules courants, ses plaques et ses papiers. Choisissez-en un ou plusieurs (six au plus).</p>
                <div class="fx-gen-kits">
                    <?php foreach ($kits as $code => $row): $kit = $row['kit']; $sz = $row['sizes']; $on = in_array($code, $params['kits'], true); ?>
                        <label class="fx-gen-kit">
                            <input type="checkbox" name="kits[]" value="<?= $h($code) ?>"<?= $on ? ' checked' : '' ?>>
                            <span class="fx-gen-kit-b">
                                <span class="fx-gen-kit-n"><b translate="no"><?= $h(K::text($kit['name'], $uiLang)) ?></b><span class="fx-flg<?= $kit['status'] === 'new' ? ' g' : '' ?>" translate="no"><?= $h($code) ?></span></span>
                                <span class="fx-gen-kit-s" translate="no"><?= $h(K::text($kit['summary'], $uiLang)) ?></span>
                                <span class="fx-gen-kit-k"><span><b><?= (int) $sz['male'] + (int) $sz['female'] ?></b> prénoms</span><span><b><?= (int) $sz['last'] ?></b> noms</span><span><b><?= (int) $sz['vehicles'] ?></b> véhicules</span><span><b><?= (int) $sz['documents'] ?></b> papiers</span></span>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="fx-ch"><h2>Réseaux</h2><small><?= count($themes) - 1 ?></small></div>
            <div class="fx-fs">
                <?php if ($civil !== null): ?>
                    <p class="fx-help"><b translate="no"><?= $h(K::text($civil['name'], $uiLang)) ?></b> <span translate="no"><?= $h(K::text($civil['summary'], $uiLang)) ?></span> <span>Toujours présente ; réglez sa part plus bas.</span></p>
                <?php endif; ?>
                <div class="fx-gen-themes">
                    <?php foreach ($themes as $key => $theme): if ($key === K::CIVIL_THEME) { continue; } $on = in_array($key, $params['themes'], true); ?>
                        <label class="fx-gen-kit">
                            <input type="checkbox" name="themes[]" value="<?= $h($key) ?>"<?= $on ? ' checked' : '' ?>>
                            <span class="fx-gen-kit-b">
                                <span class="fx-gen-kit-n"><b translate="no"><?= $h(K::text($theme['name'], $uiLang)) ?></b></span>
                                <span class="fx-gen-kit-s" translate="no"><?= $h(K::text($theme['summary'], $uiLang)) ?></span>
                                <span class="fx-gen-kit-k"><span><b><?= count($theme['roles']) ?></b> rôles</span><span translate="no"><?= $h(implode(' ', $theme['kits'])) ?></span></span>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="fx-ch"><h2>Réglages</h2></div>
            <div class="fx-fs fx-gen-sls">
                <?php foreach ($sliders as $name => [$label, $min, $max, $step, $unit, $help]): $id = 'gen-' . $name; ?>
                    <label class="fx-f fx-gen-sl" for="<?= $h($id) ?>">
                        <span><?= $h($label) ?> <em><output for="<?= $h($id) ?>" data-gen-out="<?= $h($id) ?>"><?= (int) $params[$name] ?></output><?= $h($unit) ?></em></span>
                        <input type="range" id="<?= $h($id) ?>" name="<?= $h($name) ?>" min="<?= (int) $min ?>" max="<?= (int) $max ?>" step="<?= (int) $step ?>" value="<?= (int) $params[$name] ?>" data-gen-range>
                        <small><?= $h($help) ?></small>
                    </label>
                <?php endforeach; ?>
            </div>
            <div class="fx-ch"><h2>Lot</h2></div>
            <div class="fx-fs">
                <label class="fx-f"><span>Étiquette du lot</span><input class="fx-in" name="label" maxlength="90" value="<?= $h($params['label']) ?>" placeholder="ex. Vallée nord, population et réseau"><small>Sert à retrouver le lot et à le supprimer. La mention « fictif, généré » est ajoutée d’office.</small></label>
                <div class="fx-g2">
                    <label class="fx-f"><span>Langue des récits</span><select class="fx-in" name="lang"><option value="fr"<?= $params['lang'] === 'fr' ? ' selected' : '' ?>>Français</option><option value="en"<?= $params['lang'] === 'en' ? ' selected' : '' ?>>Anglais</option></select></label>
                    <label class="fx-f"><span>Graine</span><input class="fx-in m" name="seed" inputmode="numeric" pattern="[0-9]{0,8}" value="<?= $seed > 0 ? (int) $seed : '' ?>" placeholder="au hasard"><small>Même graine, même jeu.</small></label>
                </div>
                <label class="fx-f"><span>Code d’opération (facultatif)</span><input class="fx-in m" name="op_key" maxlength="64" pattern="[A-Za-z0-9_.:\-]{2,64}" value="<?= $h($params['op_key']) ?>" placeholder="ex. OP-2611-07"><small>Vide : le lot rejoint le paquet commun que le jeu charge sur toutes les missions. Avec un code : seulement la mission qui porte ce code.</small></label>
                <?php if ($sessions !== []): ?>
                    <label class="fx-f"><span>Session de lore (facultatif)</span>
                        <select class="fx-in" name="lore_session_id">
                            <option value="0">Aucune</option>
                            <?php foreach ($sessions as $s): ?>
                                <option value="<?= (int) $s['id'] ?>"<?= (int) $params['lore_session_id'] === (int) $s['id'] ? ' selected' : '' ?>><?= $h($s['title']) ?> (<?= $h($s['op_key']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                        <small>Les personnes du lot rejoignent cette session : vous réglez ensuite ce que les fichiers en savent, donnée par donnée.</small>
                    </label>
                <?php endif; ?>
            </div>
            <div class="fx-ff"><button class="btn pri" type="submit">Voir l’aperçu</button></div>
        </form>

        <section class="fx-card fx-out" id="apercu">
            <?php if ($summary === null): ?>
                <div class="fx-ch"><h2>Aperçu</h2></div>
                <div class="fx-idle">
                    <div><h3>Aucun aperçu pour l’instant</h3><p>Choisissez vos kits et vos réseaux, réglez les curseurs, puis demandez l’aperçu : vous verrez les compteurs, les organisations et un échantillon de fiches. Rien n’est enregistré tant que vous ne créez pas le lot.</p></div>
                    <div><h3>Ce qu’un lot contient</h3><p>Des identités marquées « générées », leurs papiers émis, leur signalement, leurs adresses, téléphones et véhicules, leurs liens de famille et de réseau, et un court récit de contexte par fiche. Les identités entrent dans le paquet que le jeu charge déjà.</p></div>
                </div>
            <?php else: ?>
                <div class="fx-ch"><h2>Aperçu</h2><small>Graine : <b translate="no"><?= (int) $preview['seed'] ?></b></small></div>
                <div class="fx-fs">
                    <div class="fx-gen-nums">
                        <span><b><?= (int) $summary['people'] ?></b> personnes</span>
                        <span><b><?= (int) $summary['households'] ?></b> foyers</span>
                        <span><b><?= (int) $summary['orgs'] ?></b> organisations</span>
                        <span><b><?= (int) $summary['documents'] ?></b> papiers</span>
                        <span><b><?= (int) $summary['vehicles'] ?></b> véhicules</span>
                        <span><b><?= (int) $summary['phones'] ?></b> téléphones</span>
                        <span><b><?= (int) $summary['links'] ?></b> liens</span>
                        <span><b><?= (int) $summary['aliases'] ?></b> surnoms</span>
                        <span><b><?= (int) $summary['wanted'] ?></b> recherchés</span>
                        <span><b><?= (int) $summary['by_flag']['forged'] ?></b> faux papiers</span>
                        <span><b><?= (int) $summary['by_flag']['expired'] ?></b> papiers expirés</span>
                    </div>
                    <div class="fx-flgs">
                        <?php foreach ($summary['by_kit'] as $code => $n): ?><span class="fx-flg" translate="no"><?= $h($code) ?> <?= (int) $n ?></span><?php endforeach; ?>
                        <?php foreach ($summary['by_theme'] as $key => $n): ?><span class="fx-flg b" translate="no"><?= $h(K::text($themes[$key]['name'] ?? [], $uiLang)) ?> <?= (int) $n ?></span><?php endforeach; ?>
                        <?php foreach ($summary['by_category'] as $cat => $n): ?><span class="fx-flg g"><?= $h(W::label(W::POP_CATEGORIES, (string) $cat, (string) $cat)) ?> <?= (int) $n ?></span><?php endforeach; ?>
                    </div>
                </div>
                <?php if ($blockers !== []): ?>
                    <div class="fx-fs">
                        <?php foreach ($blockers as $b): ?><p class="fx-gen-stop" role="alert"><?= $h($b) ?></p><?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <div class="fx-ff">
                    <?php if ($blockers === []): ?>
                        <form method="post" action="<?= $h($base . '/creer') ?>"><?= $csrf ?><?= $hiddenParams($params, (int) $preview['seed']) ?>
                            <button class="btn pri" type="submit">Créer ce lot</button>
                        </form>
                    <?php endif; ?>
                    <form method="post" action="<?= $h($base) ?>"><?= $csrf ?><?= $hiddenParams($params, 0) ?>
                        <button class="btn" type="submit">Tirer un autre jeu</button>
                    </form>
                </div>
                <?php if (($preview['orgs'] ?? []) !== []): ?>
                    <div class="fx-fs">
                        <h3>Organisations inventées</h3>
                        <?php foreach ($preview['orgs'] as $o): ?>
                            <p class="fx-gen-org"><b translate="no"><?= $h($o['name']) ?></b> <span class="fx-flg" translate="no"><?= $h($o['kit']) ?></span> <span translate="no"><?= $h(K::text($themes[$o['theme']]['name'] ?? [], $uiLang)) ?></span> <span><b><?= (int) $o['members'] ?></b> membres</span> <span translate="no"><?= $h($o['place']) ?></span></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <div class="fx-fs">
                    <h3>Échantillon de fiches</h3>
                    <?php foreach ($preview['sample'] as $p): ?>
                        <article class="fx-gen-p">
                            <div class="fx-hn">
                                <b translate="no"><?= $h($p['last_name']) ?>, <?= $h($p['first_name']) ?></b>
                                <span class="fx-flg" translate="no"><?= $h($p['kit']) ?></span>
                                <?php if ($p['alias'] !== ''): ?><span class="fx-flg b" translate="no">« <?= $h($p['alias']) ?> »</span><?php endif; ?>
                                <?php if ($p['wanted_level'] !== null): ?><span class="fx-flg r"><?= $h(W::label(W::WANTED_LEVELS, (string) $p['wanted_level'], 'Recherché')) ?></span><?php endif; ?>
                                <?php if ($p['forged']): ?><span class="fx-flg a">Faux papier</span><?php endif; ?>
                            </div>
                            <div class="fx-kv">
                                <span><?= $h(IC::label(IC::SEXES, (string) $p['sex'])) ?></span><span><b><?= (int) $p['age'] ?></b> ans</span>
                                <span translate="no"><?= $h($p['role_label']) ?><?= $p['org'] !== '' ? ' · ' . $h($p['org']) : '' ?></span>
                                <span translate="no"><?= $h($p['profession']) ?></span>
                                <span><b><?= (int) $p['height_cm'] ?></b> cm</span><span>Cheveux : <b><?= $h(S::HAIR[$p['hair']] ?? '') ?></b></span><span>Yeux : <b><?= $h(S::EYES[$p['eyes']] ?? '') ?></b></span>
                            </div>
                            <div class="fx-kv">
                                <span translate="no"><?= $h($p['address']) ?></span>
                                <?php foreach ($p['phones'] as $ph): ?><span translate="no"><?= $h($ph['number']) ?></span><?php endforeach; ?>
                                <?php foreach ($p['vehicles'] as $v): ?><span translate="no"><?= $h($v['make'] . ' ' . $v['model']) ?> <b><?= $h($v['plate']) ?></b></span><?php endforeach; ?>
                            </div>
                            <div class="fx-kv">
                                <?php foreach ($p['documents'] as $d): ?>
                                    <span><?= $h(IC::label(IC::DOC_TYPES, (string) $d['doc_type'])) ?> <b translate="no"><?= $h($d['doc_number']) ?></b><?php if ($d['flag'] !== 'genuine'): ?> <?php if ($d['flag'] === 'forged'): ?><span class="fx-flg a">Faux</span><?php elseif ($d['flag'] === 'expired'): ?><span class="fx-flg">Expiré</span><?php else: ?><span class="fx-flg">Volé</span><?php endif; ?><?php endif; ?></span>
                                <?php endforeach; ?>
                            </div>
                            <p translate="no"><?= $h($p['story']) ?></p>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>

    <section class="fx-card fx-gen-lots" id="lots">
        <div class="fx-ch"><h2>Lots créés</h2><small><?= count($batches) ?></small></div>
        <?php if ($batches === []): ?>
            <div class="fx-idle"><div><h3>Aucun lot</h3><p>Les lots que vous créez apparaissent ici, avec leur auteur, leur date et leur graine. Chacun se supprime en bloc, sans toucher au reste du registre.</p></div></div>
        <?php endif; ?>
        <?php foreach ($batches as $b): $bp = $b['params']; ?>
            <div class="fx-gen-lot">
                <div style="min-width:0">
                    <span class="fx-hn"><b translate="no"><?= $h($b['label']) ?></b><span class="fx-flg" translate="no"><?= $h($b['ref']) ?></span><?php if ($b['op_key'] !== null): ?><span class="fx-flg b" translate="no"><?= $h($b['op_key']) ?></span><?php endif; ?></span>
                    <span class="fx-kv">
                        <span><b><?= (int) $b['identities_count'] ?></b> personnes</span>
                        <span><b><?= (int) $b['documents_count'] ?></b> papiers</span>
                        <span translate="no"><?= $h(implode(' ', (array) ($bp['kits'] ?? []))) ?></span>
                        <span>Créé le <b translate="no"><?= $h(substr((string) $b['created_at'], 0, 16)) ?></b></span><?php if (($bp['author'] ?? '') !== ''): ?><span>Auteur : <b translate="no"><?= $h($bp['author']) ?></b></span><?php endif; ?>
                        <span>Graine : <b translate="no"><?= (int) $b['seed'] ?></b></span>
                    </span>
                </div>
                <form method="post" action="<?= $h($base . '/lots/' . (int) $b['id'] . '/supprimer') ?>" data-gen-delete data-confirm="Supprimer ce lot et tout ce qu’il a créé ? Cette action ne s’annule pas."><?= $csrf ?>
                    <button class="btn sm danger" type="submit">Supprimer le lot</button>
                </form>
            </div>
        <?php endforeach; ?>
    </section>
</div>
<?php
$sseContent = ob_get_clean();
require dirname(__DIR__) . '/_layout.php';
