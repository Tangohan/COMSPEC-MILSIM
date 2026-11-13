<?php
declare(strict_types=1);
/**
 * Passage aux fichiers — terminal (kit « SSE Fichiers ») : onglets Personne (QW), Véhicule (QV), Document (QA),
 * Biométrie (QB), Appareil ATAK (QD) ; requête à gauche, réponse du terminal à droite ; recherches et surveillances.
 * Filtres instantanés (envoi automatique, sans bouton « Appliquer »), raccourcis « / » (recherche) et Ctrl+Entrée.
 */
use App\Controllers\Web\SseFileController;
use App\Support\SseFileSubjectCatalog as S;
use App\Support\SseRecordsCountryPacks as Packs;

ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
require __DIR__ . '/_nav.php';
/** @var string $tab @var array<string,string> $params @var string $purpose */
$p = $params;
$pack = Packs::get((string) $terminalCountry);
$rec = $pack['records'];
$sel = static fn (string $a, string $b): string => $a === $b ? ' selected' : '';
$tabs = [
    'qw' => ['Personne', 'QW', 'fx-user'], 'qv' => ['Véhicule', 'QV', 'fx-car'], 'qa' => ['Document', 'QA', 'fx-doc'],
    'qb' => ['Biométrie', 'QB', 'fx-face'], 'qd' => ['Appareil ATAK', 'QD', 'fx-phone'],
];
$base = url('atak/sse/fichiers');
$motifQs = $purpose !== '' ? '&motif=' . rawurlencode($purpose) : '';
$heights = [['', '165', '− 165'], ['165', '175', '165–175'], ['175', '185', '175–185'], ['185', '', '185 +']];
$purposeSelect = static function () use ($h, $purpose, $purposeRequired): string {
    $o = '<option value="">Choisir…</option>';
    foreach (S::PURPOSES as $k => $label) {
        $o .= '<option value="' . $h($k) . '"' . ($purpose === $k ? ' selected' : '') . '>' . $h($k . ' · ' . $label) . '</option>';
    }

    return '<div class="fx-fs"><h3>Motif de la consultation' . ($purposeRequired ? ' <em>obligatoire</em>' : '') . '</h3>'
        . '<label class="fx-f"><span>Code motif</span><select class="fx-in" name="motif" data-fx-remember="motif">' . $o . '</select>'
        . '<small>Inscrit au journal avec la requête, comme sur un vrai terminal. Ex. « CS » pour un contrôle routier.</small></label></div>';
};
?>
<div class="fx">
    <div class="fx-th">
        <div>
            <h1>Passage aux fichiers</h1>
            <p>Interrogez les fichiers comme depuis un terminal de patrouille. Chaque requête porte un motif et entre au journal ; une correspondance se confirme auprès du service d’origine avant d’agir.</p>
        </div>
        <div class="fx-ws">
            <div><small>Système</small><b><?= $h($rec['system']) ?></b></div>
            <div><small>Pays du terminal</small><b><?= $h(Packs::label((string) $terminalCountry)) ?></b></div>
            <div><small>Opérateur</small><b translate="no"><?= $h(\App\Core\Session::get('callsign') ?? \App\Core\Session::get('display_name') ?? '—') ?></b></div>
        </div>
    </div>

    <nav class="fx-qt" aria-label="Type de requête">
        <?php foreach ($tabs as $k => [$label, $code, $icon]): ?>
            <a href="<?= $h($base . '?t=' . $k . $motifQs) ?>" class="<?= $tab === $k ? 'on' : '' ?>"<?= $tab === $k ? ' aria-current="page"' : '' ?>><svg class="i"><use href="#<?= $icon ?>"></use></svg><?= $h($label) ?><em><?= $h($code) ?></em></a>
        <?php endforeach; ?>
    </nav>

    <div class="fx-wk">
        <div class="fx-col">
        <form class="fx-card" method="get" action="<?= $h($base) ?>" data-fx-instant role="search" aria-label="Requête">
            <input type="hidden" name="t" value="<?= $h($tab) ?>">
            <?php if ($tab === 'qw'): ?>
                <div class="fx-ch"><h2>Requête personne</h2><small>QW · nom, alias, signalement</small></div>
                <div class="fx-fs">
                    <label class="fx-f"><span>Recherche <kbd>/</kbd></span>
                        <input class="fx-in" name="q" value="<?= $h($p['q']) ?>" autocomplete="off" placeholder="Nom, alias, n° de papier, plaque, téléphone…" data-fx-focus>
                        <small>Une seule case : nom ou surnom (« Abou Rami »), numéro de papier ou plaque (« 7ABC123 »), téléphone (« +1 555 014 2398 »), date de naissance ou référence « IDT-12 ».</small>
                    </label>
                </div>
                <div class="fx-fs">
                    <h3>Identité</h3>
                    <div class="fx-g2">
                        <label class="fx-f"><span>Nom</span><input class="fx-in m" name="last" value="<?= $h($p['last']) ?>" autocomplete="off" placeholder="ex. Walker"></label>
                        <label class="fx-f"><span>Prénom</span><input class="fx-in" name="first" value="<?= $h($p['first']) ?>" autocomplete="off" placeholder="ex. Daniel"></label>
                    </div>
                    <div class="fx-g2">
                        <label class="fx-f"><span>Date de naissance</span><input class="fx-in" type="date" name="dob" value="<?= $h(\App\Services\Sse\SseFileSearchService::normDate($p['dob']) ?? '') ?>"></label>
                        <label class="fx-f"><span>Tolérance</span><select class="fx-in" name="dob_tol"><option value="0"<?= $sel($p['dob_tol'], '0') ?>>Date exacte</option><option value="1"<?= $sel($p['dob_tol'], '1') ?>>± 1 an</option><option value="5"<?= $sel($p['dob_tol'], '5') . ($p['dob_tol'] === '' ? ' selected' : '') ?>>± 5 ans</option></select></label>
                    </div>
                    <div class="fx-tg">
                        <input type="hidden" name="aliases" value="0"><label class="fx-sw"><input type="checkbox" name="aliases" value="1"<?= $p['aliases'] !== '0' ? ' checked' : '' ?>><i></i>Inclure les alias</label>
                        <input type="hidden" name="fuzzy" value="0"><label class="fx-sw"><input type="checkbox" name="fuzzy" value="1"<?= $p['fuzzy'] !== '0' ? ' checked' : '' ?>><i></i>Orthographe approchante</label>
                    </div>
                    <small class="fx-help">L’orthographe approchante rapproche les noms qui se prononcent pareil : « Hadad » trouve aussi HADDAD, « Mercer » trouve MERCIER.</small>
                </div>
                <div class="fx-fs">
                    <h3>Signalement</h3>
                    <div class="fx-f"><span>Sexe</span>
                        <div class="fx-seg" role="radiogroup" aria-label="Sexe">
                            <?php foreach (['' => 'Indifférent', 'M' => 'Homme', 'F' => 'Femme'] as $v => $label): ?>
                                <label><input type="radio" name="sex" value="<?= $h($v) ?>"<?= $p['sex'] === $v ? ' checked' : '' ?>><span><?= $h($label) ?></span></label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="fx-f"><span>Taille <em><?= $p['h_min'] !== '' || $p['h_max'] !== '' ? $h(($p['h_min'] ?: '…') . ' – ' . ($p['h_max'] ?: '…') . ' cm') : 'indifférente' ?></em></span>
                        <div class="fx-rng"><input class="fx-in mono" name="h_min" value="<?= $h($p['h_min']) ?>" placeholder="min" aria-label="Taille minimale"><span>à</span><input class="fx-in mono" name="h_max" value="<?= $h($p['h_max']) ?>" placeholder="max" aria-label="Taille maximale"></div>
                        <div class="fx-pre"><?php foreach ($heights as [$a, $b, $label]): ?><button type="button" data-fx-set="h_min=<?= $h($a) ?>&amp;h_max=<?= $h($b) ?>" class="<?= $p['h_min'] === $a && $p['h_max'] === $b && ($a . $b) !== '' ? 'on' : '' ?>"><?= $h($label) ?></button><?php endforeach; ?></div>
                        <small>En centimètres ou en pieds-pouces : « 178 », « 1,78 m » ou « 5'10" ». Ex. un individu vu de loin : 175 à 185.</small>
                    </div>
                    <div class="fx-g2">
                        <div class="fx-f"><span>Poids</span><div class="fx-rng"><input class="fx-in mono" name="w_min" value="<?= $h($p['w_min']) ?>" placeholder="min" aria-label="Poids minimal"><span>à</span><input class="fx-in mono" name="w_max" value="<?= $h($p['w_max']) ?>" placeholder="max" aria-label="Poids maximal"></div><small>kg, ou « 170 lb ».</small></div>
                        <div class="fx-f"><span>Âge</span><div class="fx-rng"><input class="fx-in mono" name="age_min" value="<?= $h($p['age_min']) ?>" inputmode="numeric" placeholder="min" aria-label="Âge minimal"><span>à</span><input class="fx-in mono" name="age_max" value="<?= $h($p['age_max']) ?>" inputmode="numeric" placeholder="max" aria-label="Âge maximal"></div><small>Ex. 25 à 35 ans.</small></div>
                    </div>
                    <div class="fx-g3">
                        <label class="fx-f"><span>Corpulence</span><select class="fx-in" name="build"><option value="">Indifférente</option><?php foreach (S::BUILDS as $k => $l): ?><option value="<?= $h($k) ?>"<?= $sel($p['build'], $k) ?>><?= $h($l) ?></option><?php endforeach; ?></select></label>
                        <label class="fx-f"><span>Cheveux</span><select class="fx-in" name="hair"><option value="">Indifférents</option><?php foreach (S::HAIR as $k => $l): ?><option value="<?= $h($k) ?>"<?= $sel($p['hair'], $k) ?>><?= $h($l) ?></option><?php endforeach; ?></select></label>
                        <label class="fx-f"><span>Yeux</span><select class="fx-in" name="eyes"><option value="">Indifférents</option><?php foreach (S::EYES as $k => $l): ?><option value="<?= $h($k) ?>"<?= $sel($p['eyes'], $k) ?>><?= $h($l) ?></option><?php endforeach; ?></select></label>
                    </div>
                    <label class="fx-f"><span>Signes particuliers</span><input class="fx-in" name="marks" value="<?= $h($p['marks']) ?>" placeholder="ex. tatouage aigle, cicatrice, barbe"><small>Un ou plusieurs mots cherchés dans les signes notés aux fichiers.</small></label>
                    <div class="fx-tg">
                        <input type="hidden" name="photo" value=""><label class="fx-sw"><input type="checkbox" name="photo" value="1"<?= $p['photo'] === '1' ? ' checked' : '' ?>><i></i>Avec photo seulement</label>
                    </div>
                </div>
                <?= $purposeSelect() ?>
            <?php elseif ($tab === 'qv'): ?>
                <div class="fx-ch"><h2>Requête véhicule</h2><small>QV · plaque, description</small></div>
                <div class="fx-fs">
                    <h3>Véhicule</h3>
                    <label class="fx-f"><span>Immatriculation <kbd>/</kbd></span><input class="fx-in m" name="plate" value="<?= $h($vehicleQuery['plate']) ?>" placeholder="<?= $h('ex. ' . ($pack['plate']['example'] ?? 'AB-123-CD')) ?>" data-fx-focus><small>Complète ou partielle : « 4471 » suffit. Format du pays du terminal : <?= $h($pack['plate']['example'] ?? '') ?>.</small></label>
                    <label class="fx-f"><span>Description</span><input class="fx-in" name="desc" value="<?= $h($vehicleQuery['text']) ?>" placeholder="ex. pick-up beige, Ford F-150"><small>Marque, modèle ou couleur, sans plaque si vous ne l’avez pas relevée.</small></label>
                </div>
                <?= $purposeSelect() ?>
            <?php elseif ($tab === 'qa' || $tab === 'qb'): ?>
                <?php if ($tab === 'qa'): ?>
                    <div class="fx-ch"><h2>Requête document</h2><small>QA · papiers perdus ou volés</small></div>
                    <div class="fx-fs">
                        <h3>Document</h3>
                        <label class="fx-f"><span>Numéro du document <kbd>/</kbd></span><input class="fx-in m" name="number" value="<?= $h($criteria['number']) ?>" placeholder="<?= $h('ex. ' . ($pack['ids']['passport']['example'] ?? 'X1234567')) ?>" data-fx-focus><small>Le numéro suffit ; le pays et le type affinent. Contrôle qu’un papier n’est ni volé, ni perdu, ni porté par quelqu’un d’autre.</small></label>
                        <div class="fx-g2">
                            <label class="fx-f"><span>Type</span><select class="fx-in" name="doc_type"><option value="">Tous les types</option><?php foreach ($docTypes as $k => $l): ?><option value="<?= $h($k) ?>"<?= $sel($criteria['doc_type'], (string) $k) ?>><?= $h($l) ?></option><?php endforeach; ?></select></label>
                            <label class="fx-f"><span>Pays émetteur</span><input class="fx-in m" name="country" value="<?= $h($criteria['country']) ?>" maxlength="3" placeholder="ex. USA, DEU, FRA"></label>
                        </div>
                        <label class="fx-f"><span>Nom présenté (facultatif)</span><input class="fx-in m" name="last" value="<?= $h($criteria['last']) ?>" placeholder="Nom lu sur le papier"><small>Signale un papier porté par une autre personne que son titulaire.</small></label>
                    </div>
                <?php else: ?>
                    <div class="fx-ch"><h2>Recherche biométrique</h2><small>QB · fiche SEEK II, clé biométrique</small></div>
                    <div class="fx-fs">
                        <h3>Relevé biométrique</h3>
                        <label class="fx-f"><span>N° d’enregistrement SEEK II ou clé <kbd>/</kbd></span><input class="fx-in m" name="number" value="<?= $h($criteria['number']) ?>" placeholder="ex. REC-000412" data-fx-focus><small>Le relevé fait en jeu avec le SEEK II : empreintes, iris et visage enregistrés par le mod.</small></label>
                        <div class="fx-note"><svg class="i"><use href="#fx-alert"></use></svg><span>Le site ne fait aucune reconnaissance faciale : pour comparer des visages, ouvrez la <a href="<?= $h(url('atak/sse/fichiers/photos')) ?>">photothèque</a> et filtrez par signalement.</span></div>
                    </div>
                <?php endif; ?>
                <?= $purposeSelect() ?>
            <?php else: ?>
                <div class="fx-ch"><h2>Appareils ATAK</h2><small>QD · téléphones du jeu</small></div>
                <div class="fx-fs">
                    <h3>Synchroniser un téléphone</h3>
                    <ol class="fx-steps">
                        <li>En jeu, sur votre téléphone : <b>menu ACE › Téléphone › Synchroniser avec le web</b>.</li>
                        <li>La première fois, acceptez la synchronisation dans les réglages du téléphone (désactivée par défaut).</li>
                        <li>Pour un téléphone saisi sur une IA : même action, sur le téléphone saisi.</li>
                        <li>L’extraction apparaît ici et dans <a href="<?= $h(url('atak/sse/fichiers/telephones')) ?>">Téléphones du jeu</a>.</li>
                    </ol>
                    <small class="fx-help">Seules les données du personnage en jeu sont copiées, jamais celles de votre vrai téléphone. Le MJ peut couper la synchronisation pour une mission.</small>
                </div>
            <?php endif; ?>
            <?php if ($tab !== 'qd'): ?>
                <div class="fx-ff">
                    <button class="btn pri" type="submit"><svg class="i"><use href="#fx-search"></use></svg><span>Interroger les fichiers</span><kbd>Ctrl ↵</kbd></button>
                    <a class="btn" href="<?= $h($base . '?t=' . $tab . $motifQs) ?>">Réinitialiser les filtres</a>
                </div>
            <?php endif; ?>
        </form>

        <section class="fx-card" id="surveillances" aria-labelledby="fx-watch-h">
            <div class="fx-ch"><h2 id="fx-watch-h">Surveillances</h2><small>alerte quand un document cite</small></div>
            <?php if ($watches === []): ?><p class="fx-empty">Aucune surveillance. Ajoutez un nom, un prénom ou un numéro : vous serez prévenu dès qu’un compte rendu, un contrôle ou une extraction le cite.</p><?php endif; ?>
            <?php foreach ($watches as $w): ?>
                <div class="fx-li">
                    <span translate="no"><?= $h($w['value']) ?><?= (int) $w['hits'] > 0 ? ' · ' . (int) $w['hits'] . ' alerte' . ((int) $w['hits'] > 1 ? 's' : '') : '' ?></span>
                    <form method="post" action="<?= $h(url('atak/sse/fichiers/surveillances/' . (int) $w['id'] . '/supprimer')) ?>">
                        <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>"><input type="hidden" name="back" value="atak/sse/fichiers?t=<?= $h($tab) ?>">
                        <button class="fx-x" type="submit" aria-label="Arrêter la surveillance de <?= $h($w['value']) ?>">×</button>
                    </form>
                </div>
            <?php endforeach; ?>
            <?php if ($filesCanWrite): ?>
                <form class="fx-fs" method="post" action="<?= $h(url('atak/sse/fichiers/surveillances')) ?>">
                    <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>"><input type="hidden" name="back" value="atak/sse/fichiers?t=<?= $h($tab) ?>">
                    <div class="fx-inline"><label class="fx-f"><span>Surveiller</span><input class="fx-in" name="value" maxlength="120" placeholder="ex. Nikos, +1 555 014 2398"></label><button class="btn" type="submit">Ajouter</button></div>
                </form>
            <?php endif; ?>
        </section>

        <?php if ($savedSearches !== [] || $recentSearches !== []): ?>
            <section class="fx-card" aria-labelledby="fx-saved-h">
                <div class="fx-ch"><h2 id="fx-saved-h">Mes recherches</h2></div>
                <?php foreach ([['Enregistrées', $savedSearches], ['Récentes', $recentSearches]] as [$label, $list]): ?>
                    <?php foreach ($list as $s): ?>
                        <?php $sLabel = $s['label'] ?? implode(' · ', array_slice(array_values(array_filter($s['params'])), 0, 4)); ?>
                        <div class="fx-li">
                            <a href="<?= $h($base . '?t=qw' . $motifQs . '&' . http_build_query($s['params'])) ?>" translate="no"><?= $h($sLabel !== '' ? $sLabel : 'Recherche') ?></a>
                            <small><?= $h($s['saved'] ? 'enregistrée' : \App\Services\Sse\SseFileRecordService::ago($s['last_used_at'], gmdate('Y-m-d H:i:s'))) ?></small>
                            <?php if ($s['saved']): ?>
                                <form method="post" action="<?= $h(url('atak/sse/fichiers/recherches/' . (int) $s['id'] . '/supprimer')) ?>"><input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>"><input type="hidden" name="back" value="atak/sse/fichiers"><button class="fx-x" type="submit" aria-label="Oublier cette recherche">×</button></form>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>
        </div>

        <section class="fx-card fx-out" aria-live="polite" aria-label="Réponse du terminal">
            <?php if ($needPurpose): ?>
                <div class="fx-ch"><h2>Réponse du terminal</h2><small><?= $h(strtoupper($tab)) ?> · en attente</small></div>
                <p class="fx-err" style="margin-top:16px">Choisissez un motif de consultation : il est inscrit au journal avec la requête.</p>
            <?php elseif ($tab === 'qw' && is_array($search)): ?>
                <div class="fx-rh">
                    <span class="fx-tag">QW</span>
                    <b><?= $search['total'] > 0 ? (int) $search['total'] . ' correspondance' . ($search['total'] > 1 ? 's' : '') : 'Aucune correspondance' ?></b>
                    <small><?= gmdate('H:i') ?>Z<?= $purpose !== '' ? ' · motif ' . $h($purpose) : '' ?></small>
                    <span class="sp"></span>
                    <?php if ($filesCanWrite && $search['total'] > 0): ?>
                        <form method="post" action="<?= $h(url('atak/sse/fichiers/recherches')) ?>">
                            <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>"><input type="hidden" name="back" value="<?= $h('atak/sse/fichiers?' . http_build_query(['t' => 'qw', 'motif' => $purpose] + array_filter($p))) ?>">
                            <?php foreach (array_filter($p) as $k => $v): ?><input type="hidden" name="p[<?= $h($k) ?>]" value="<?= $h($v) ?>"><?php endforeach; ?>
                            <button class="btn sm" type="submit"><svg class="i"><use href="#fx-check"></use></svg>Enregistrer la recherche</button>
                        </form>
                    <?php endif; ?>
                </div>
                <?php if ($search['total'] > 0): ?>
                    <?php $top = $search['hits'][0] ?? null; ?>
                    <?php if ($top !== null && $top['confidence'] !== 'exact'): ?>
                        <div class="fx-caut a"><svg class="i"><use href="#fx-alert"></use></svg><?= $h($rec['possible']) ?> <small>Vérifiez l’identité avant toute action.</small></div>
                    <?php endif; ?>
                    <div>
                        <?php foreach ($search['hits'] as $hit): ?><?php require __DIR__ . '/_hit.php'; ?><?php endforeach; ?>
                    </div>
                    <?php if ($search['pages'] > 1): ?>
                        <nav class="fx-pager" aria-label="Pages de résultats">
                            <span>Page <?= (int) $search['page'] ?> sur <?= (int) $search['pages'] ?></span>
                            <span>
                                <?php if ($search['page'] > 1): ?><a class="btn sm" href="<?= $h($base . '?' . http_build_query(['t' => 'qw', 'motif' => $purpose, 'page' => $search['page'] - 1] + array_filter($p))) ?>">Précédente</a><?php endif; ?>
                                <?php if ($search['page'] < $search['pages']): ?><a class="btn sm" href="<?= $h($base . '?' . http_build_query(['t' => 'qw', 'motif' => $purpose, 'page' => $search['page'] + 1] + array_filter($p))) ?>">Suivante</a><?php endif; ?>
                            </span>
                        </nav>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="fx-nr"><b><?= $h($rec['no_record']) ?> · AUCUNE INSCRIPTION</b><p>Aucune fiche ne correspond à ces critères. L’absence de résultat ne vaut pas vérification d’identité : activez les alias et l’orthographe approchante, élargissez la tolérance, ou retirez un critère de signalement.</p></div>
                <?php endif; ?>
            <?php elseif ($tab === 'qv' && is_array($vehicles)): ?>
                <div class="fx-rh"><span class="fx-tag">QV</span><b><?= $vehicles !== [] ? count($vehicles) . ' véhicule' . (count($vehicles) > 1 ? 's' : '') : 'Aucun véhicule' ?></b><small><?= $h($rec['vehicle_system'] ?? '') ?> · <?= gmdate('H:i') ?>Z</small></div>
                <?php foreach ($vehicles as $v): ?>
                    <?php $l = $v['link']; ?>
                    <div class="fx-vh">
                        <span class="fx-plate" translate="no"><?= $h($l['value']) ?></span>
                        <div style="min-width:0">
                            <div class="fx-hn"><b translate="no"><?= $h(trim(($l['detail']['make'] ?? '') . ' ' . ($l['detail']['model'] ?? '')) ?: 'Véhicule') ?><?= ($l['detail']['color'] ?? '') !== '' ? ' · ' . $h($l['detail']['color']) : '' ?></b>
                                <?php if ($l['flag'] !== null): ?><span class="fx-flg <?= $l['flag'] === 'stolen' ? 'r' : 'a' ?>"><?= $h($l['flag_label']) ?></span><?php endif; ?>
                                <?php if ($v['exact']): ?><span class="fx-flg g">Plaque exacte</span><?php endif; ?>
                            </div>
                            <?php if (($l['detail']['note'] ?? '') !== ''): ?><span class="fx-aka" translate="no"><?= $h($l['detail']['note']) ?></span><?php endif; ?>
                        </div>
                        <?php if ($v['identity'] !== null): ?><a class="btn sm" href="<?= $h(url('atak/sse/fichiers/sujet/' . (int) $v['identity']['id']) . ($purpose !== '' ? '?motif=' . rawurlencode($purpose) : '')) ?>"><svg class="i"><use href="#fx-user"></use></svg><span translate="no"><?= $h($v['identity']['display_name']) ?></span></a><?php endif; ?>
                    </div>
                <?php endforeach; ?>
                <?php if ($vehicles === []): ?><div class="fx-nr"><b><?= $h($rec['no_record']) ?> · AUCUNE INSCRIPTION</b><p>Aucun véhicule des fichiers ne correspond. Une plaque partielle (« 4471 ») ou la seule description élargit la recherche.</p></div><?php endif; ?>
            <?php elseif (($tab === 'qa' || $tab === 'qb') && is_array($result)): ?>
                <?php require __DIR__ . '/_result.php'; ?>
            <?php elseif ($tab === 'qd'): ?>
                <div class="fx-ch"><h2>Extractions récentes</h2><small><a href="<?= $h(url('atak/sse/fichiers/telephones')) ?>">Tout voir</a></small></div>
                <?php if ($devices === []): ?>
                    <div class="fx-idle"><div><h3>Aucun téléphone synchronisé</h3><p>Les téléphones du jeu remontent ici quand un joueur lance « Synchroniser avec le web ». Vous y lirez contacts, appels, messages, notes, photos, positions et lieux, comme dans un rapport d’exploitation.</p></div></div>
                <?php else: ?>
                    <?php foreach (array_slice($devices, 0, 12) as $e): ?>
                        <?php require __DIR__ . '/_extraction_row.php'; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            <?php else: ?>
                <?php
                $idle = [
                    'qw' => ['Qui cherchez-vous ?', 'Saisissez un nom, un alias ou un simple signalement : une taille, un tatouage, une couleur d’yeux suffisent. Les noms mal orthographiés sont rapprochés à la prononciation.',
                        [[$rec['system'], 'Personnes recherchées', 'mandats, recherches, disparus'], ['AKA', 'Fichier des alias', 'surnoms, variantes, fausses graphies'], ['CTRL', 'Contacts antérieurs', 'contrôles et consultations'], ['XREF', 'Recoupements', 'comptes rendus, extractions, fiches']],
                        ['« Abou Rami » renvoie toutes les personnes connues sous ce surnom', '« Hadad » trouve aussi HADDAD grâce à l’orthographe approchante', 'Taille seule et sexe pour un individu aperçu de loin']],
                    'qv' => ['Contrôle de véhicule', 'Plaque complète ou partielle, ou simple description. Le propriétaire connu s’ouvre en un clic.',
                        [[(string) ($rec['vehicle_system'] ?? 'DMV'), 'Immatriculations', 'véhicules notés aux fichiers'], ['STOLEN', 'Véhicules volés', 'signalés par les services']],
                        ['Une plaque partielle « 4471 » est acceptée', 'Recherchez « pick-up beige » sans plaque']],
                    'qa' => ['Vérification de papiers', 'Contrôlez qu’un papier présenté n’est ni volé, ni perdu, ni utilisé sous une autre identité.',
                        [['DOC', 'Papiers perdus ou volés', 'tous pays émetteurs'], ['REG', 'Titres émis', 'registre de la communauté']],
                        ['Le numéro suffit, le type et le pays affinent']],
                    'qb' => ['Relevé biométrique', 'Retrouvez la personne enregistrée par un SEEK II en jeu, par son numéro d’enregistrement ou sa clé biométrique.',
                        [['SEEK II', 'Biométrie terrain', 'empreintes, iris, visage enregistrés en jeu'], ['PHOTO', 'Photothèque', 'comparaison à l’œil, sans reconnaissance faciale']],
                        ['Le numéro « REC-… » est affiché par le SEEK II après l’enregistrement']],
                ][$tab];
                ?>
                <div class="fx-ch"><h2>Réponse du terminal</h2><small><?= $h(strtoupper($tab)) ?> · en attente</small></div>
                <div class="fx-idle">
                    <div class="rdy">PRÊT · <?= $h($rec['system']) ?> · <?= $h(strtoupper($tab)) ?></div>
                    <div><h3><?= $h($idle[0]) ?></h3><p><?= $h($idle[1]) ?></p></div>
                    <div class="fx-dbs"><?php foreach ($idle[2] as [$code, $label, $hint]): ?><div class="fx-db"><b><?= $h($code) ?></b><span><?= $h($label) ?></span><small><?= $h($hint) ?></small></div><?php endforeach; ?></div>
                    <ul class="fx-tips"><?php foreach ($idle[3] as $tip): ?><li><svg class="i"><use href="#fx-check"></use></svg><?= $h($tip) ?></li><?php endforeach; ?></ul>
                </div>
                <?php if ($recent !== []): ?>
                    <div class="fx-ch" style="border-top:1px solid var(--fx-ln)"><h2>Dernières consultations</h2><small><a href="<?= $h(url('atak/sse/fichiers/journal')) ?>">Tout le journal</a></small></div>
                    <div class="fx-tbw"><table class="fx-tb resp"><thead><tr><th>Requête</th><th>Heure</th><th>Type</th><th>Source</th><th>Réponse</th><th></th></tr></thead><tbody>
                    <?php foreach ($recent as $r): ?>
                        <tr>
                            <td class="m" translate="no"><?= $h($r['qrn']) ?></td>
                            <td class="m"><?= $h(substr((string) $r['created_at'], 0, 16)) ?></td>
                            <td><?= $h($r['query_type_label']) ?> <span class="fx-flg"><?= $h($r['query_type']) ?></span></td>
                            <td><?= $h($r['source_label']) ?></td>
                            <td><span class="fx-flg <?= $r['hit'] ? 'r' : '' ?>"><?= $r['hit'] ? 'HIT' : 'NO HIT' ?></span></td>
                            <td><a href="<?= $h(SseFileController::replayUrl((string) $r['query_type'], SseFileController::replayCriteria($r['criteria']))) ?>">Rejouer</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody></table></div>
                <?php endif; ?>
            <?php endif; ?>
        </section>
    </div>
</div>
<?php
$sseContent = ob_get_clean();
require dirname(__DIR__) . '/_layout.php';
