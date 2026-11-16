<?php
declare(strict_types=1);
/**
 * Fiche sujet (vue opérateur) : mise en garde, contacts antérieurs (« déjà contrôlé »), identité et alias, signalement,
 * photos (édition en modales : _subject_modals.php), papiers, adresses / véhicules / téléphones / personnes liées, mentions, recoupements, fiches proches,
 * extractions de téléphones, annotations du dossier et message brut du terminal. Imprimable.
 *
 * @var array<string,mixed> $record
 * @var bool $watching
 */
use App\Support\SseFileSubjectCatalog as S;
use App\Support\SseFileText;
use App\Support\SseRecordsFormat as RF;
use App\Support\SseRecordsCountryPacks as Packs;

ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
require __DIR__ . '/_nav.php';
$sseExtraScripts = ($sseExtraScripts ?? '') . '<script defer src="' . $h(asset_url('assets/js/sse-files-modal.js')) . '"></script>';
// Erreur de validation du serveur : elle s'affiche dans la modale du formulaire envoyé (sinon, en haut de la page).
$fxFlashError = \App\Core\Session::getFlash('error');
$r = $record;
$i = $r['identity'];
$p = $r['profile'] ?? [];
$c = (string) $r['country'];
$iid = (int) $i['id'];
$self = 'atak/sse/fichiers/sujet/' . $iid;
$csrf = '<input type="hidden" name="_csrf_token" value="' . $h($csrfToken) . '">';
$age = \App\Services\Sse\SseFileSearchService::age($i['birth_date'] ?? null, gmdate('Y-m-d'));
$red = array_values(array_filter($r['flags'], static fn (array $f): bool => $f['tone'] === 'red'));
$amber = array_values(array_filter($r['flags'], static fn (array $f): bool => $f['tone'] === 'amber'));
$toneCls = ['red' => 'r', 'amber' => 'a', 'blue' => 'b'];
$photos = $r['photos'];
$front = null;
$profile = null;
foreach ($photos as $ph) {
    if ($front === null && in_array($ph['kind'], ['front', 'field', 'document'], true)) {
        $front = $ph;
    } elseif ($profile === null && $ph['kind'] === 'profile') {
        $profile = $ph;
    }
}
$legacyPhoto = $i['photo_src'] ?? null; // calculé par SseFileRecordService : route protégée pour un membre, rien si l'image est masquée
$imageLock = is_array($r['image_lock'] ?? null) ? $r['image_lock'] : null;
$shot = static function (?array $ph, string $label, ?int $height) use ($h, $legacyPhoto, $imageLock): string {
    $src = $ph !== null ? \App\Services\Sse\SseSeekPhotoService::photoUrl($ph) : ($label === 'FACE' ? $legacyPhoto : null);
    $top = $height !== null ? max(4, (int) round((200 - $height) * 1.5)) : 20;
    if ($imageLock !== null) {
        $inner = '<span class="fx-lk" role="img" aria-label="Image masquée : très secret (TS)"><svg class="i" aria-hidden="true"><use href="#fx-lock"></use></svg><b>TRÈS SECRET (TS)</b></span>';
    } else {
        $inner = $src !== null ? '<img src="' . $h($src) . '" alt="Photo ' . $h(mb_strtolower($label)) . '">'
            : '<div class="hc"></div><div class="sil" style="top:' . $top . 'px"><i></i><u></u></div>';
    }

    return '<div class="fx-shot">' . $inner . '<div class="lb"><span>' . $h($label) . '</span><span>' . ($height !== null ? (int) $height : '') . '</span></div></div>';
};
$val = static fn (mixed $v): string => $v === null || $v === '' ? '—' : (string) $v;
$canManage = !empty($filesCanManage);
$canWrite = !empty($filesCanWrite);
$xref = $r['xref'];
// Icône « Modifier » (crayon) d'une section : ouvre sa modale ; sans JavaScript, le lien mène à la modale par son ancre.
$edit = static fn (string $modal, string $label, bool $allowed): string => $allowed
    ? '<a class="fx-edit" href="#' . $h($modal) . '" data-fx-modal-open="' . $h($modal) . '" aria-haspopup="dialog" aria-label="' . $h($label) . '" title="' . $h($label) . '"><svg class="i" aria-hidden="true"><use href="#fx-pen"></use></svg></a>'
    : '';
$conf = ['exact' => 'r', 'probable' => 'a', 'possible' => 'b'];
?>
<div class="fx">
    <div class="fx-th">
        <div>
            <h1 translate="no"><?= $h(mb_strtoupper((string) $i['last_name'])) ?><?= ($i['first_name'] ?? '') !== '' ? ', ' . $h($i['first_name']) : '' ?></h1>
            <p>Fiche <?= $h($i['ref']) ?> · consultée par <?= $h(\App\Core\Session::get('callsign') ?? \App\Core\Session::get('display_name') ?? 'vous') ?> à <?= gmdate('H:i') ?>Z, inscrite au journal. Ce que la fiche contient est ce que les fichiers savent : rien de plus.</p>
        </div>
        <div class="fx-acts">
            <a class="btn" href="<?= $h(url('atak/sse/fichiers')) ?>"><svg class="i"><use href="#fx-search"></use></svg>Nouvelle requête</a>
            <a class="btn" href="<?= $h(url($self . '/imprimer')) ?>" target="_blank" rel="noopener"><svg class="i"><use href="#fx-print"></use></svg>Fiche imprimable</a>
            <?php if ($canWrite): ?>
                <form method="post" action="<?= $h(url($self . '/surveiller')) ?>"><?= $csrf ?><button class="btn<?= $watching ? ' pri' : '' ?>" type="submit"><svg class="i"><use href="#fx-eye"></use></svg><?= $watching ? 'Surveillée' : 'Surveiller' ?></button></form>
            <?php endif; ?>
        </div>
    </div>

    <?php if (is_string($fxFlashError) && $fxFlashError !== ''): ?>
        <p class="fx-flasherr" role="alert" data-fx-flash-error><?= $h($fxFlashError) ?></p>
    <?php endif; ?>

    <div class="fx-twocol">
        <article class="fx-card" aria-label="Fiche du sujet">
            <?php if ($red !== []): ?>
                <div class="fx-caut" role="alert"><svg class="i"><use href="#fx-alert"></use></svg><?= $h(implode(' · ', array_map(static fn (array $f): string => $f['label'], $red))) ?></div>
            <?php elseif ($amber !== []): ?>
                <div class="fx-caut a"><svg class="i"><use href="#fx-alert"></use></svg><?= $h(implode(' · ', array_map(static fn (array $f): string => $f['label'], $amber))) ?></div>
            <?php endif; ?>
            <div class="fx-rh">
                <span class="fx-tag <?= $r['response']['tone'] === 'red' ? 'r' : '' ?>"><?= $h($r['response']['system']) ?></span>
                <b><?= $h($r['response']['text']) ?></b>
                <span class="sp"></span>
                <small>Formats : <?= $h(Packs::label($c)) ?></small>
            </div>
            <?php if ($r['contacts']['last'] !== null): ?>
                <?php $lc = $r['contacts']['last']; ?>
                <div class="fx-seenbar"><svg class="i"><use href="#fx-clock"></use></svg><span><b>Déjà contrôlé</b> <?= $h($lc['ago']) ?><?= $lc['where'] !== '' ? ' en ' . $h($lc['where']) : '' ?><?= $lc['who'] !== '' ? ' par ' . $h($lc['who']) : '' ?><?= $lc['outcome'] !== '' ? ' · ' . $h($lc['outcome']) : '' ?></span><span class="fx-flg"><?= (int) $r['contacts']['total'] ?> contrôle<?= $r['contacts']['total'] > 1 ? 's' : '' ?> au total</span><a href="#contacts">Voir l’historique</a></div>
            <?php endif; ?>
            <div class="fx-idb">
                <div class="fx-shots"><?= $shot($front, 'FACE', $p['height_cm'] ?? null) ?><?= $shot($profile, 'PROFIL', $p['height_cm'] ?? null) ?></div>
                <div style="min-width:0">
                    <h2 translate="no"><?= $h(mb_strtoupper((string) $i['last_name'])) ?><?= ($i['first_name'] ?? '') !== '' ? ', ' . $h($i['first_name']) : '' ?></h2>
                    <div class="fx-flgs">
                        <?php foreach ($r['flags'] as $f): ?><span class="fx-flg <?= $toneCls[$f['tone']] ?? '' ?>"><?= $h($f['label']) ?></span><?php endforeach; ?>
                        <?php if ($r['flags'] === []): ?><span class="fx-flg">Aucune mention</span><?php endif; ?>
                    </div>
                    <dl class="fx-idg">
                        <div><dt>Naissance</dt><dd><?= $h(($i['birth_date'] ?? null) ? RF::date((string) $i['birth_date'], $c) : '—') ?></dd></div>
                        <div><dt>Âge</dt><dd><?= $age !== null ? $age . ' ans' : '—' ?></dd></div>
                        <div><dt>Sexe</dt><dd><?= $h(['M' => 'Homme', 'F' => 'Femme'][$i['sex']] ?? '—') ?></dd></div>
                        <div><dt>Nationalité</dt><dd><?= $h(($i['nationality'] ?? null) ? Packs::label((string) $i['nationality']) : '—') ?></dd></div>
                        <div><dt>Lieu de naissance</dt><dd translate="no"><?= $h($val($i['birth_place'] ?? null)) ?></dd></div>
                        <div><dt>Profession</dt><dd translate="no"><?= $h($val($i['profession'] ?? null)) ?></dd></div>
                        <div><dt>Taille</dt><dd><?= $h(($p['height_cm'] ?? null) !== null ? RF::height((int) $p['height_cm'], $c) : '—') ?></dd></div>
                        <div><dt>Poids</dt><dd><?= $h(($p['weight_kg'] ?? null) !== null ? RF::weight((int) $p['weight_kg'], $c) : '—') ?></dd></div>
                        <div><dt>Corpulence</dt><dd><?= $h(S::label(S::BUILDS, $p['build'] ?? null)) ?></dd></div>
                        <div><dt>Cheveux</dt><dd><?= $h(S::label(S::HAIR, $p['hair'] ?? null)) ?></dd></div>
                        <div><dt>Yeux</dt><dd><?= $h(S::label(S::EYES, $p['eyes'] ?? null)) ?></dd></div>
                        <div><dt>Camp, affiliation</dt><dd translate="no"><?= $h($val($p['affiliation'] ?? null)) ?></dd></div>
                    </dl>
                </div>
            </div>

            <section class="fx-ds" id="identite">
                <h3><span>Alias et variantes <small><?= count($r['aliases']) ?></small></span><?= $edit('fx-m-alias', 'Ajouter un alias', $canManage) ?></h3>
                <p class="fx-sd">Les autres noms sous lesquels le sujet est connu.</p>
                <div class="fx-akl">
                    <?php foreach ($r['aliases'] as $a): ?>
                        <span translate="no"><?= $h($a['alias']) ?> <small><?= $h($a['kind_label']) ?></small>
                            <?php if ($canManage && !in_array($a['kind'], ['legal', 'registry'], true)): ?>
                                <form method="post" action="<?= $h(url('atak/sse/fichiers/alias/' . (int) $a['id'] . '/supprimer')) ?>"><?= $csrf ?><button class="fx-x" type="submit" aria-label="Retirer l’alias <?= $h($a['alias']) ?>">×</button></form>
                            <?php endif; ?>
                        </span>
                    <?php endforeach; ?>
                    <?php if ($r['aliases'] === [] && !$canManage): ?><span>Aucun alias connu.</span><?php endif; ?>
                </div>
            </section>

            <section class="fx-ds" id="signalement">
                <h3><span>Signes particuliers</span><?= $edit('fx-m-signalement', 'Modifier le signalement', $canManage) ?></h3>
                <p class="fx-sd">Ce qui permet de reconnaître le sujet, et les codes de prudence.</p>
                <p translate="no"><?= $h($p['marks'] ?? 'Aucun signe particulier noté.') ?></p>
                <?php if (($p['caution_codes'] ?? []) !== []): ?>
                    <div class="fx-flgs"><?php foreach ($p['caution_codes'] as $cc): ?><span class="fx-flg a"><?= $h(S::label(S::CAUTIONS, (string) $cc)) ?></span><?php endforeach; ?></div>
                <?php endif; ?>
            </section>

            <section class="fx-ds" id="photos">
                <h3><span>Photos <small><?= $imageLock !== null ? 0 : count($photos) ?></small></span><?= $edit('fx-m-photo', 'Ajouter une photo', $canWrite) ?></h3>
                <p class="fx-sd">Les clichés rangés sur la fiche, sans reconnaissance faciale.</p>
                <?php if (!empty($r['image_banner'])): ?><span class="fx-cband"><svg class="i" aria-hidden="true"><use href="#fx-lock"></use></svg>Très secret (TS)</span><?php endif; ?>
                <?php if ($imageLock !== null): ?>
                    <p><strong>TRÈS SECRET (TS)</strong> : les images de ce membre sont réservées à lui-même et à sa hiérarchie.</p>
                    <?php $lock = $imageLock; $lockBack = $self; require __DIR__ . '/_image_request.php'; ?>
                <?php elseif ($photos !== []): ?>
                    <div class="fx-book" style="padding:0">
                        <?php foreach ($photos as $ph): ?>
                            <div class="fx-pc">
                                <span class="ph"><img src="<?= $h(\App\Services\Sse\SseSeekPhotoService::photoUrl($ph)) ?>" alt="<?= $h($ph['caption'] ?? 'Photo') ?>" loading="lazy"></span>
                                <small><?= $h(['front' => 'Face', 'profile' => 'Profil', 'field' => 'Terrain', 'document' => 'Papier'][$ph['kind']] ?? 'Photo') ?> · <?= $h(['upload' => 'envoi d’un membre', 'atak' => 'ATAK', 'seek' => 'SEEK II', 'person' => 'fiche personne', 'extraction' => 'extraction'][$ph['source']] ?? $ph['source']) ?></small>
                                <?php if (($ph['caption'] ?? '') !== ''): ?><small translate="no"><?= $h($ph['caption']) ?></small><?php endif; ?>
                                <?php if ($canManage): ?><form method="post" action="<?= $h(url('atak/sse/fichiers/photos/' . (int) $ph['id'] . '/rattacher')) ?>"><?= $csrf ?><input type="hidden" name="detach" value="1"><button class="btn sm link" type="submit">Retirer de la fiche</button></form><?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p>Aucune photo aux fichiers.</p>
                <?php endif; ?>
            </section>

            <section class="fx-ds" id="papiers">
                <h3>Papiers délivrés <small><?= count($r['documents']) ?></small></h3>
                <?php if ($r['documents'] === []): ?><p>Aucun papier délivré aux fichiers.</p><?php else: ?>
                    <div class="fx-tbw"><table class="fx-tb resp"><thead><tr><th>Papier</th><th>Pays</th><th>Numéro</th><th>Expire le</th><th>État</th></tr></thead><tbody>
                    <?php foreach ($r['documents'] as $d): ?>
                        <tr class="<?= in_array($d['state'], ['stolen', 'lost', 'revoked'], true) ? 'fl' : '' ?>">
                            <td><?= $h($d['doc_type_label'] ?? $d['doc_type']) ?></td>
                            <td class="m"><?= $h($d['country_code']) ?></td>
                            <td class="m" translate="no"><a href="<?= $h(url('atak/sse/fichiers') . '?' . http_build_query(['t' => 'qa', 'number' => $d['doc_number'], 'country' => $d['country_code']])) ?>"><?= $h($d['doc_number']) ?></a></td>
                            <td class="m"><?= $h(($d['expires_on'] ?? null) ? RF::date((string) $d['expires_on'], (string) $d['country_code']) : '—') ?></td>
                            <td><span class="fx-flg <?= $d['state'] === 'ok' ? 'g' : (in_array($d['state'], ['stolen', 'lost', 'revoked'], true) ? 'r' : 'a') ?>"><?= $h($d['state_label']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody></table></div>
                <?php endif; ?>
            </section>

            <section class="fx-ds" id="liens">
                <h3><span>Adresses, véhicules, téléphones, personnes liées</span><?= $edit('fx-m-liens', 'Ajouter à la fiche', $canManage) ?></h3>
                <p class="fx-sd">Les éléments rattachés au sujet dans les fichiers.</p>
                <?php $hasLinks = false; ?>
                <div class="fx-lk">
                    <?php foreach (['address', 'vehicle', 'phone', 'email', 'associate'] as $kind): ?>
                        <?php foreach ($r['links'][$kind] as $l): ?>
                            <?php $hasLinks = true; $other = $l['kind'] === 'associate' ? ($r['associates'][!empty($l['reverse']) ? $l['identity_id'] : (int) $l['linked_identity_id']] ?? null) : null; ?>
                            <div>
                                <span style="min-width:0"><b class="<?= in_array($kind, ['vehicle', 'phone'], true) ? 'mono' : '' ?>" translate="no">
                                    <?php if ($other !== null): ?><a href="<?= $h(url('atak/sse/fichiers/sujet/' . (int) $other['id'])) ?>"><?= $h($other['display_name']) ?></a><?php else: ?><?= $h($l['value']) ?><?php endif; ?>
                                </b><br><span><?= $h($l['kind_label']) ?><?= $l['relation_label'] !== '' ? ' · ' . $h($l['relation_label']) : '' ?><?= $kind === 'vehicle' && ($l['detail']['make'] ?? '') !== '' ? ' · ' . $h(trim(($l['detail']['make'] ?? '') . ' ' . ($l['detail']['model'] ?? '') . ' ' . ($l['detail']['color'] ?? ''))) : '' ?><?= $l['flag_label'] !== '' ? ' · ' . $h($l['flag_label']) : '' ?></span></span>
                                <?php if ($canManage && empty($l['reverse'])): ?><form method="post" action="<?= $h(url('atak/sse/fichiers/liens/' . (int) $l['id'] . '/supprimer')) ?>"><?= $csrf ?><button class="fx-x" type="submit" aria-label="Retirer">×</button></form><?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </div>
                <?php if (!$hasLinks): ?><p>Aucune adresse, aucun véhicule, aucun téléphone ni personne liée aux fichiers.</p><?php endif; ?>
            </section>

            <section class="fx-ds" id="mentions">
                <h3><span>Mentions aux fichiers <small><?= count($r['mentions']) ?> active<?= count($r['mentions']) > 1 ? 's' : '' ?></small></span><?= $edit('fx-m-mention', 'Poser une mention', $canManage) ?></h3>
                <p class="fx-sd">Mandats, recherches et signalements en cours sur le sujet.</p>
                <div class="fx-mn">
                    <?php foreach ($r['mentions'] as $m): ?>
                        <div class="fx-mi"><div class="fx-row"><b><?= $h($m['mention_label']) ?></b>
                            <?php if ($canManage): ?><form method="post" action="<?= $h(url('atak/sse/fichiers/mentions/' . (int) $m['id'] . '/lever')) ?>"><?= $csrf ?><input type="hidden" name="back" value="<?= $h($self) ?>"><button class="btn sm" type="submit">Lever</button></form><?php endif; ?></div>
                            <?php if (($m['note'] ?? '') !== ''): ?><p translate="no"><?= $h($m['note']) ?></p><?php endif; ?>
                            <small><?= ($m['reference'] ?? '') !== '' ? $h($m['reference']) . ' · ' : '' ?>posée le <?= $h(substr((string) $m['created_at'], 0, 16)) ?></small></div>
                    <?php endforeach; ?>
                    <?php if ($r['mentions'] === []): ?><p>Aucune mention active : ni mandat, ni recherche, ni signalement.</p><?php endif; ?>
                </div>
            </section>

            <?php if ($r['extractions'] !== []): ?>
                <section class="fx-ds" id="extractions">
                    <h3>Extractions de téléphones <small><?= count($r['extractions']) ?></small></h3>
                    <div class="fx-lk">
                        <?php foreach ($r['extractions'] as $e): ?>
                            <a class="fx-lki" href="<?= $h(url('atak/sse/fichiers/telephones/' . (int) $e['id'])) ?>"><b class="mono"><?= $h($e['ref']) ?></b><span><?= !empty($e['via_number']) ? 'contient un numéro du sujet' : 'rattachée à la fiche' ?></span></a>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <section class="fx-ds" id="annotations">
                <h3><span>Annotations du dossier <small><?= count($r['notes']) ?></small></span><?= $edit('fx-m-note', 'Annoter le dossier', $canWrite) ?></h3>
                <p class="fx-sd">Les remarques libres des membres, visibles de ceux qui lisent les fichiers.</p>
                <div class="fx-mn">
                    <?php foreach ($r['notes'] as $n): ?>
                        <div class="fx-mi"><div class="fx-row"><b translate="no"><?= $h($n['author_label'] ?? 'Membre') ?></b>
                            <?php if ($canManage || (int) ($n['user_id'] ?? 0) === (int) \App\Core\Session::get('user_id')): ?><form method="post" action="<?= $h(url('atak/sse/fichiers/notes/' . (int) $n['id'] . '/supprimer')) ?>"><?= $csrf ?><button class="fx-x" type="submit" aria-label="Supprimer l’annotation">×</button></form><?php endif; ?></div>
                            <p translate="no"><?= nl2br($h($n['body'])) ?></p><small><?= $h(substr((string) $n['created_at'], 0, 16)) ?>Z</small></div>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="fx-ds">
                <h3>Message brut <button class="btn sm" type="button" data-fx-copy="#fx-raw"><svg class="i"><use href="#fx-copy"></use></svg>Copier</button></h3>
                <pre class="fx-raw" id="fx-raw" translate="no"><?= $h($r['raw']) ?></pre>
            </section>
        </article>

        <aside class="fx-col" aria-label="Recoupements et historique">
            <section class="fx-card" id="recoupements">
                <div class="fx-ch"><h2>Recoupements</h2><small><?= count($xref['items']) ?> proposé<?= count($xref['items']) > 1 ? 's' : '' ?></small></div>
                <?php if ($xref['items'] === [] && $xref['attached'] === []): ?>
                    <p class="fx-empty">Aucun document ne cite ce sujet : ni compte rendu, ni fiche, ni contrôle, ni extraction de téléphone.</p>
                <?php endif; ?>
                <div class="fx-fs" style="gap:8px">
                    <?php foreach ($xref['items'] as $x): ?>
                        <div class="fx-mi">
                            <div class="fx-row"><b><?= $h($x['reason']) ?></b><span class="fx-flg <?= $conf[$x['confidence']] ?>"><?= $h(S::CONFIDENCE_LABELS[$x['confidence']]) ?></span></div>
                            <p translate="no"><?= SseFileText::snippetHtml((string) $x['snippet']) ?></p>
                            <small><?= $h($x['label']) ?> · <?= $h($x['title']) ?><?= ($x['at'] ?? '') !== '' ? ' · ' . $h(substr((string) $x['at'], 0, 10)) : '' ?></small>
                            <div class="fx-acts">
                                <a class="btn sm" href="<?= $h(url($x['path'])) ?>">Voir</a>
                                <?php if ($canWrite): ?>
                                    <?php foreach (['attach' => 'Rattacher à cette fiche', 'reject' => 'Ce n’est pas lui'] as $dec => $label): ?>
                                        <form method="post" action="<?= $h(url($self . '/recoupements')) ?>"><?= $csrf ?><input type="hidden" name="source_type" value="<?= $h($x['type']) ?>"><input type="hidden" name="source_id" value="<?= (int) $x['id'] ?>"><input type="hidden" name="decision" value="<?= $dec ?>"><button class="btn sm<?= $dec === 'reject' ? ' link' : '' ?>" type="submit"><?= $h($label) ?></button></form>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if ($xref['attached'] !== []): ?>
                        <h3>Rattachés à la fiche</h3>
                        <?php foreach ($xref['attached'] as $x): ?>
                            <div class="fx-li" style="padding:6px 0"><a href="<?= $h(url($x['path'])) ?>"><?= $h($x['label'] . ' · ' . $x['title']) ?></a>
                                <?php if ($canWrite): ?><form method="post" action="<?= $h(url($self . '/recoupements')) ?>"><?= $csrf ?><input type="hidden" name="source_type" value="<?= $h($x['type']) ?>"><input type="hidden" name="source_id" value="<?= (int) $x['id'] ?>"><input type="hidden" name="decision" value="undo"><button class="fx-x" type="submit" aria-label="Détacher">×</button></form><?php endif; ?></div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>

            <section class="fx-card" id="contacts">
                <div class="fx-ch"><h2>Contacts antérieurs</h2><small><?= (int) $r['contacts']['total'] ?> contrôle<?= $r['contacts']['total'] > 1 ? 's' : '' ?> · <?= count($r['contacts']['list']) ?> en tout</small></div>
                <?php if ($r['contacts']['list'] === []): ?><p class="fx-empty">Premier contact : ni contrôle ni consultation enregistrés avant celle-ci.</p><?php else: ?>
                    <ul class="fx-tl" style="padding:0 16px">
                        <?php foreach (array_slice($r['contacts']['list'], 0, 25) as $ct): ?>
                            <li><time datetime="<?= $h($ct['at']) ?>" title="<?= $h($ct['at']) ?> UTC"><?= $h($ct['ago']) ?></time>
                                <span><?= $h($ct['label']) ?><?= $ct['where'] !== '' ? ' · ' . $h($ct['where']) : '' ?><?= $ct['who'] !== '' ? ' · ' . $h($ct['who']) : '' ?></span>
                                <span class="fx-flg"><?= $h($ct['outcome'] !== '' ? $ct['outcome'] : $ct['ref']) ?></span></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>

            <?php if ($r['similar'] !== []): ?>
                <section class="fx-card" id="proches">
                    <div class="fx-ch"><h2>Fiches proches</h2><small>doublons possibles</small></div>
                    <?php foreach ($r['similar'] as $s): ?>
                        <div class="fx-li">
                            <span style="min-width:0"><a href="<?= $h(url('atak/sse/fichiers/sujet/' . (int) $s['identity_id'])) ?>" translate="no"><?= $h($s['name']) ?></a><br><small style="white-space:normal"><?= $h($s['reason']) ?></small></span>
                            <span style="display:flex;gap:6px;align-items:center"><?php if ($s['duplicate']): ?><span class="fx-flg a">Doublon ?</span><?php endif; ?><a class="btn sm" href="<?= $h(url('atak/sse/fichiers/comparer') . '?a=' . $iid . '&b=' . (int) $s['identity_id']) ?>">Comparer</a></span>
                        </div>
                    <?php endforeach; ?>
                    <?php if (!empty($filesCanAdmin)): ?><p class="fx-empty">Fusionner deux fiches : back-office › Renseignement › Fichiers SSE.</p><?php endif; ?>
                </section>
            <?php endif; ?>
        </aside>
    </div>
    <?php require __DIR__ . '/_subject_modals.php'; ?>
</div>
<?php
$sseContent = ob_get_clean();
require dirname(__DIR__) . '/_layout.php';
