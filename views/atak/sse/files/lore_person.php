<?php
declare(strict_types=1);
/**
 * Personne d'une session de lore (MJ) : fiche éditable (identité, signalement, camp, couverture, présence aux fichiers),
 * ce qu'elle sait et réponses de l'IA, connu / caché donnée par donnée avec phase de révélation, alias et liens (adresses,
 * véhicules, téléphones, personnes de la session), retrait de la session.
 *
 * @var array<string,mixed> $session
 * @var array<string,mixed> $record vue MJ (SseFileRecordService::record gm)
 * @var array<int,array<string,mixed>> $others personnes de la session
 * @var list<string> $countries
 */
use App\Support\SseFileSubjectCatalog as S;
use App\Support\SseRecordsCountryPacks as Packs;

ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
require __DIR__ . '/_nav.php';
$s = $session;
$r = $record;
$i = $r['identity'];
$p = $r['raw_profile'] ?? [];
$iid = (int) $i['id'];
$self = url('atak/sse/fichiers/lore/' . (int) $s['id'] . '/personnes/' . $iid);
$csrf = '<input type="hidden" name="_csrf_token" value="' . $h($csrfToken) . '">';
$locked = (bool) $s['locked'];
$dis = $locked ? ' disabled' : '';
$vis = $p['visibility'] ?? [];
$sel = static fn (mixed $a, mixed $b): string => (string) $a === (string) $b ? ' selected' : '';
$answers = $p['ai_answers'] ?? [];
$allLinks = array_merge(...array_values(array_map(static fn (array $l): array => array_values(array_filter($l, static fn (array $x): bool => empty($x['reverse']))), $r['links'])));
$visSelect = static function (string $name, string $v, int $phase, string $phaseName) use ($h, $dis): string {
    return '<select class="fx-in" name="' . $h($name) . '"' . $dis . ' aria-label="Visibilité"><option value="known"' . ($v !== 'hidden' ? ' selected' : '') . '>Connu des fichiers</option><option value="hidden"' . ($v === 'hidden' ? ' selected' : '') . '>Caché (MJ)</option></select>'
        . '<input class="fx-in mono" type="number" min="0" max="20" name="' . $h($phaseName) . '" value="' . $phase . '"' . $dis . ' aria-label="Phase de révélation" title="Visible à partir de cette phase (0 : tout de suite)">';
};
?>
<div class="fx">
    <div class="fx-th">
        <div><h1 translate="no"><?= $h($i['display_name']) ?></h1><p><?= $h($i['ref']) ?> · session <?= $h($s['op_key']) ?> (phase <?= (int) $s['phase'] ?>)<?= ($p['ai_uid'] ?? null) ? ' · IA ' . $h($p['ai_uid']) : '' ?>. <?= $locked ? 'Session verrouillée : lecture seule.' : 'Chaque changement redescend en jeu à la prochaine récupération.' ?></p></div>
        <div class="fx-acts">
            <a class="btn" href="<?= $h(url('atak/sse/fichiers/lore/' . (int) $s['id'])) ?>">Retour à la session</a>
            <a class="btn" href="<?= $h(url('atak/sse/fichiers/sujet/' . $iid)) ?>">Voir comme un opérateur</a>
        </div>
    </div>
    <div class="fx-twocol">
        <div class="fx-col">
            <form class="fx-card" id="identity" method="post" action="<?= $h($self) ?>"><?= $csrf ?><input type="hidden" name="section" value="identity">
                <div class="fx-ch"><h2>Fiche</h2><small><?= $h(Packs::label((string) ($p['country'] ?? $i['nationality'] ?? 'USA'))) ?></small></div>
                <div class="fx-fs">
                    <div class="fx-g2"><label class="fx-f"><span>Nom</span><input class="fx-in m" name="last_name" maxlength="120" value="<?= $h($i['last_name']) ?>"<?= $dis ?>></label><label class="fx-f"><span>Prénom</span><input class="fx-in" name="first_name" maxlength="120" value="<?= $h($i['first_name']) ?>"<?= $dis ?>></label></div>
                    <div class="fx-g3">
                        <label class="fx-f"><span>Sexe</span><select class="fx-in" name="sex"<?= $dis ?>><option value="M"<?= $sel($i['sex'], 'M') ?>>Homme</option><option value="F"<?= $sel($i['sex'], 'F') ?>>Femme</option><option value="X"<?= $sel($i['sex'], 'X') ?>>Non précisé</option></select></label>
                        <label class="fx-f"><span>Naissance</span><input class="fx-in" type="date" name="birth_date" value="<?= $h($i['birth_date'] ?? '') ?>"<?= $dis ?>></label>
                        <label class="fx-f"><span>Nationalité</span><input class="fx-in m" name="nationality" maxlength="3" value="<?= $h($i['nationality'] ?? '') ?>" placeholder="USA"<?= $dis ?>></label>
                    </div>
                    <div class="fx-g2"><label class="fx-f"><span>Lieu de naissance</span><input class="fx-in" name="birth_place" maxlength="120" value="<?= $h($i['birth_place'] ?? '') ?>"<?= $dis ?>></label><label class="fx-f"><span>Profession</span><input class="fx-in" name="profession" maxlength="120" value="<?= $h($i['profession'] ?? '') ?>"<?= $dis ?>></label></div>
                    <label class="fx-f"><span>Pays du sujet</span><select class="fx-in" name="country"<?= $dis ?>><?php foreach ($countries as $code): ?><option value="<?= $h($code) ?>"<?= $sel($p['country'] ?? '', $code) ?>><?= $h(Packs::label($code)) ?></option><?php endforeach; ?></select><small>Règle les formats affichés (dates, unités, numéros). Ex. États-Unis : taille en pieds-pouces.</small></label>
                    <div class="fx-g3">
                        <label class="fx-f"><span>Taille</span><input class="fx-in mono" name="height" value="<?= $h($p['height_cm'] ?? '') ?>" placeholder="180 ou 5'11&quot;"<?= $dis ?>></label>
                        <label class="fx-f"><span>Poids</span><input class="fx-in mono" name="weight" value="<?= $h($p['weight_kg'] ?? '') ?>" placeholder="78 ou 172 lb"<?= $dis ?>></label>
                        <label class="fx-f"><span>Corpulence</span><select class="fx-in" name="build"<?= $dis ?>><option value="">—</option><?php foreach (S::BUILDS as $k => $l): ?><option value="<?= $h($k) ?>"<?= $sel($p['build'] ?? '', $k) ?>><?= $h($l) ?></option><?php endforeach; ?></select></label>
                    </div>
                    <div class="fx-g2">
                        <label class="fx-f"><span>Cheveux</span><select class="fx-in" name="hair"<?= $dis ?>><option value="">—</option><?php foreach (S::HAIR as $k => $l): ?><option value="<?= $h($k) ?>"<?= $sel($p['hair'] ?? '', $k) ?>><?= $h($l) ?></option><?php endforeach; ?></select></label>
                        <label class="fx-f"><span>Yeux</span><select class="fx-in" name="eyes"<?= $dis ?>><option value="">—</option><?php foreach (S::EYES as $k => $l): ?><option value="<?= $h($k) ?>"<?= $sel($p['eyes'] ?? '', $k) ?>><?= $h($l) ?></option><?php endforeach; ?></select></label>
                    </div>
                    <label class="fx-f"><span>Signes particuliers</span><textarea class="fx-in" name="marks" maxlength="1000"<?= $dis ?>><?= $h($p['marks'] ?? '') ?></textarea></label>
                    <label class="fx-f"><span>Camp, affiliation</span><input class="fx-in" name="affiliation" maxlength="160" value="<?= $h($p['affiliation'] ?? '') ?>" placeholder="ex. civil · réseau logistique"<?= $dis ?>></label>
                    <label class="fx-f"><span>Couverture</span><textarea class="fx-in" name="cover_note" maxlength="1000" placeholder="ex. se présente comme chauffeur livreur"<?= $dis ?>><?= $h($p['cover_note'] ?? '') ?></textarea></label>
                    <div class="fx-f"><span>Codes de prudence</span><div class="fx-tg"><?php foreach (S::CAUTIONS as $k => $l): ?><label class="fx-sw"><input type="checkbox" name="cautions[]" value="<?= $h($k) ?>"<?= in_array($k, $p['caution_codes'] ?? [], true) ? ' checked' : '' ?><?= $dis ?>><i></i><?= $h($l) ?></label><?php endforeach; ?></div></div>
                    <div class="fx-tg"><input type="hidden" name="in_files" value="0"><label class="fx-sw"><input type="checkbox" name="in_files" value="1"<?= ($p['in_files'] ?? true) ? ' checked' : '' ?><?= $dis ?>><i></i>Inscrite aux fichiers</label></div>
                    <small class="fx-help">Décochée : la personne existe pour le MJ mais un passage aux fichiers répond « aucune inscription ». Ex. un civil inconnu des services.</small>
                </div>
                <?php if (!$locked): ?><div class="fx-ff"><button class="btn pri" type="submit">Enregistrer la fiche</button></div><?php endif; ?>
            </form>

            <form class="fx-card" id="secrets" method="post" action="<?= $h($self) ?>"><?= $csrf ?><input type="hidden" name="section" value="secrets">
                <div class="fx-ch"><h2>Ce qu’elle sait, réponses de l’IA</h2><span class="fx-pill h">Caché (MJ)</span></div>
                <div class="fx-fs">
                    <label class="fx-f"><span>Ce que la personne sait</span><textarea class="fx-in" name="knowledge" maxlength="8000" placeholder="ex. Connaît l’emplacement du dépôt d’armes du hangar 4."<?= $dis ?>><?= $h($p['knowledge'] ?? '') ?></textarea><small>Jamais montré aux opérateurs ni aux joueurs ; envoyé en jeu seulement au compte qui a le droit de préparer le lore.</small></label>
                    <label class="fx-f"><span>Réponses de l’IA (une par ligne)</span><textarea class="fx-in" name="answers" maxlength="6000" placeholder="ex. Je travaille au marché depuis dix ans."<?= $dis ?>><?= $h(implode("\n", (array) ($answers['answers'] ?? []))) ?></textarea><small>Servies au serveur de jeu : l’IA répond d’après elles quand un joueur l’interroge.</small></label>
                    <div class="fx-g2">
                        <label class="fx-f"><span>Sujets évoqués</span><select class="fx-in" name="evoke"<?= $dis ?>><?php foreach (['auto' => 'Automatique', 'none' => 'Aucun', 'known' => 'Ce qu’elle sait', 'free' => 'Libre'] as $k => $l): ?><option value="<?= $h($k) ?>"<?= $sel(implode('', (array) ($answers['evoke'] ?? ['auto'])), $k) ?>><?= $h($l) ?></option><?php endforeach; ?></select><small>Comme le réglage « Réponses de l’IA » du mod.</small></label>
                        <label class="fx-f"><span>Sujets interdits (une par ligne)</span><textarea class="fx-in" name="banned" maxlength="600" style="min-height:42px" placeholder="armed, explosives…"<?= $dis ?>><?= $h(implode("\n", (array) ($answers['banned'] ?? []))) ?></textarea></label>
                    </div>
                </div>
                <?php if (!$locked): ?><div class="fx-ff"><button class="btn" type="submit">Enregistrer</button></div><?php endif; ?>
            </form>
        </div>

        <aside class="fx-col">
            <form class="fx-card" id="visibility" method="post" action="<?= $h($self) ?>"><?= $csrf ?><input type="hidden" name="section" value="visibility">
                <div class="fx-ch"><h2>Connu ou caché</h2><small>phase de révélation</small></div>
                <div class="fx-fs">
                    <small class="fx-help">« Connu des fichiers » se lit au passage aux fichiers, au SEEK II, sur l’ATAK et par les joueurs. Une phase supérieure à 0 retarde la révélation : visible quand la session atteint cette phase.</small>
                    <div class="fx-vis">
                        <?php foreach (S::VIS_FIELDS as $f => $label): ?>
                            <span><?= $h($label) ?></span><?= $visSelect('vis_' . $f, (string) ($vis[$f]['v'] ?? 'known'), (int) ($vis[$f]['phase'] ?? 0), 'phase_' . $f) ?>
                        <?php endforeach; ?>
                        <?php foreach ($r['aliases'] as $a): ?>
                            <?php if (in_array($a['kind'], ['legal', 'registry'], true)) { continue; } ?>
                            <span translate="no">Alias « <?= $h($a['alias']) ?> »</span><?= $visSelect('row_vis[alias:' . (int) $a['id'] . ']', (string) $a['visibility'], (int) $a['reveal_phase'], 'row_phase[alias:' . (int) $a['id'] . ']') ?>
                        <?php endforeach; ?>
                        <?php foreach ($allLinks as $l): ?>
                            <span translate="no"><?= $h($l['kind_label'] . ' « ' . $l['value'] . ' »') ?></span><?= $visSelect('row_vis[link:' . (int) $l['id'] . ']', (string) $l['visibility'], (int) $l['reveal_phase'], 'row_phase[link:' . (int) $l['id'] . ']') ?>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php if (!$locked): ?><div class="fx-ff"><button class="btn pri" type="submit">Enregistrer la visibilité</button></div><?php endif; ?>
            </form>

            <section class="fx-card" id="alias">
                <div class="fx-ch"><h2>Alias</h2><small><?= count(array_filter($r['aliases'], static fn (array $a): bool => !in_array($a['kind'], ['legal', 'registry'], true))) ?></small></div>
                <?php foreach ($r['aliases'] as $a): ?>
                    <?php if (in_array($a['kind'], ['legal', 'registry'], true)) { continue; } ?>
                    <div class="fx-li"><span translate="no"><?= $h($a['alias']) ?> <small><?= $h($a['kind_label']) ?> · <span class="fx-pill <?= $a['visibility'] === 'hidden' ? 'h' : 'k' ?>"><?= $a['visibility'] === 'hidden' ? 'caché' : 'connu' ?></span></small></span>
                        <?php if (!$locked): ?><form method="post" action="<?= $h($self) ?>"><?= $csrf ?><input type="hidden" name="section" value="remove_alias"><input type="hidden" name="row_id" value="<?= (int) $a['id'] ?>"><button class="fx-x" type="submit" aria-label="Retirer l’alias">×</button></form><?php endif; ?></div>
                <?php endforeach; ?>
                <?php if (!$locked): ?>
                    <form class="fx-fs" method="post" action="<?= $h($self) ?>"><?= $csrf ?><input type="hidden" name="section" value="alias">
                        <label class="fx-f"><span>Nouvel alias</span><input class="fx-in" name="alias" maxlength="160" required placeholder="ex. « Le Comptable »"></label>
                        <div class="fx-g3"><select class="fx-in" name="kind" aria-label="Type"><option value="aka">Alias</option><option value="nickname">Surnom</option><option value="spelling">Variante</option></select><select class="fx-in" name="visibility" aria-label="Visibilité"><option value="known">Connu</option><option value="hidden">Caché</option></select><input class="fx-in mono" type="number" name="reveal_phase" min="0" max="20" value="0" aria-label="Phase de révélation"></div>
                        <div><button class="btn" type="submit">Ajouter</button></div>
                    </form>
                <?php endif; ?>
            </section>

            <section class="fx-card" id="link">
                <div class="fx-ch"><h2>Liens</h2><small><?= count($allLinks) ?></small></div>
                <?php foreach ($allLinks as $l): ?>
                    <?php $other = $l['linked_identity_id'] !== null ? ($others[$l['linked_identity_id']] ?? null) : null; ?>
                    <div class="fx-li"><span translate="no"><?= $h($l['kind_label']) ?> · <?= $h($other !== null ? $other['display_name'] : $l['value']) ?><?= $l['relation_label'] !== '' ? ' (' . $h($l['relation_label']) . ')' : '' ?></span>
                        <?php if (!$locked): ?><form method="post" action="<?= $h($self) ?>"><?= $csrf ?><input type="hidden" name="section" value="remove_link"><input type="hidden" name="row_id" value="<?= (int) $l['id'] ?>"><button class="fx-x" type="submit" aria-label="Retirer le lien">×</button></form><?php endif; ?></div>
                <?php endforeach; ?>
                <?php if (!$locked): ?>
                    <form class="fx-fs" method="post" action="<?= $h($self) ?>"><?= $csrf ?><input type="hidden" name="section" value="link">
                        <div class="fx-g2"><label class="fx-f"><span>Type</span><select class="fx-in" name="kind"><?php foreach (S::LINK_KINDS as $k => $l): ?><option value="<?= $h($k) ?>"><?= $h($l) ?></option><?php endforeach; ?></select></label><label class="fx-f"><span>Valeur</span><input class="fx-in" name="value" maxlength="255" placeholder="Adresse, plaque, numéro…"></label></div>
                        <label class="fx-f"><span>Personne de la session (lien familial, associé, cellule)</span><select class="fx-in" name="linked_identity_id"><option value="0">—</option><?php foreach ($others as $o): ?><option value="<?= (int) $o['id'] ?>"><?= $h($o['display_name']) ?></option><?php endforeach; ?></select></label>
                        <div class="fx-g2"><label class="fx-f"><span>Lien</span><select class="fx-in" name="relation"><option value="">—</option><?php foreach (S::RELATIONS as $k => $l): ?><option value="<?= $h($k) ?>"><?= $h($l) ?></option><?php endforeach; ?></select></label><label class="fx-f"><span>Mention (véhicule)</span><select class="fx-in" name="flag"><option value="">Aucune</option><?php foreach (S::VEHICLE_FLAGS as $k => $l): ?><option value="<?= $h($k) ?>"><?= $h($l) ?></option><?php endforeach; ?></select></label></div>
                        <div class="fx-g3"><input class="fx-in" name="make" maxlength="60" placeholder="Marque" aria-label="Marque"><input class="fx-in" name="model" maxlength="60" placeholder="Modèle" aria-label="Modèle"><input class="fx-in" name="color" maxlength="40" placeholder="Couleur" aria-label="Couleur"></div>
                        <div class="fx-g2"><select class="fx-in" name="visibility" aria-label="Visibilité"><option value="known">Connu des fichiers</option><option value="hidden">Caché (MJ)</option></select><input class="fx-in mono" type="number" name="reveal_phase" min="0" max="20" value="0" aria-label="Phase de révélation"></div>
                        <small class="fx-help">Ex. véhicule « <?= $h(Packs::get((string) ($p['country'] ?? 'USA'))['plate']['example'] ?? '') ?> », téléphone « <?= $h(Packs::get((string) ($p['country'] ?? 'USA'))['phone']['example'] ?? '') ?> ».</small>
                        <div><button class="btn" type="submit">Ajouter</button></div>
                    </form>
                    <form class="fx-ff" method="post" action="<?= $h($self . '/retirer') ?>"><?= $csrf ?><button class="btn danger" type="submit">Retirer de la session</button><small class="fx-help">Sa fiche reste dans les fichiers.</small></form>
                <?php endif; ?>
            </section>
        </aside>
    </div>
</div>
<?php
$sseContent = ob_get_clean();
require dirname(__DIR__) . '/_layout.php';
