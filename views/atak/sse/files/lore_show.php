<?php
declare(strict_types=1);
/**
 * Session de lore (MJ) : état de la synchronisation avec le jeu, réglages (phase, rafraîchissement, verrou), personnes de
 * la mission, ajout à la main ou généré pour un pays, import / export JSON et CSV, historique des modifications.
 *
 * @var array<string,mixed> $session
 * @var list<array<string,mixed>> $rows
 * @var list<array<string,mixed>> $history
 * @var list<string> $countries
 */
use App\Support\SseRecordsCountryPacks as Packs;

ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
require __DIR__ . '/_nav.php';
$s = $session;
$sid = (int) $s['id'];
$base = url('atak/sse/fichiers/lore/' . $sid);
$csrf = '<input type="hidden" name="_csrf_token" value="' . $h($csrfToken) . '">';
$at = static fn (?string $d): string => $d ? substr($d, 0, 16) . 'Z · ' . \App\Services\Sse\SseFileRecordService::ago($d, gmdate('Y-m-d H:i:s')) : 'jamais';
$locked = (bool) $s['locked'];
?>
<div class="fx">
    <div class="fx-th">
        <div><h1 translate="no"><?= $h($s['title']) ?></h1><p>Session <?= $h($s['op_key']) ?> · phase <?= (int) $s['phase'] ?><?= $locked ? ' · verrouillée' : '' ?>. Ce qui est marqué « connu » se lit au passage aux fichiers, au SEEK II et sur l’ATAK ; le « caché » reste au MJ.</p></div>
        <div class="fx-acts">
            <a class="btn" href="<?= $h(url('atak/sse/fichiers/lore')) ?>">Toutes les sessions</a>
            <a class="btn" href="<?= $h($base . '/export?format=json') ?>">Exporter (JSON)</a>
            <a class="btn" href="<?= $h($base . '/export?format=csv') ?>">Exporter (CSV)</a>
            <form method="post" action="<?= $h($base) ?>"><?= $csrf ?><input type="hidden" name="action" value="<?= $locked ? 'unlock' : 'lock' ?>"><button class="btn<?= $locked ? '' : ' pri' ?>" type="submit"><?= $locked ? 'Déverrouiller' : 'Verrouiller pour la mission' ?></button></form>
        </div>
    </div>

    <section class="fx-card" style="margin-bottom:16px">
        <div class="fx-sync">
            <div><small>Dernière remontée du jeu</small><b><?= $h($at($s['last_push_at'])) ?></b></div>
            <div><small>Dernière récupération par le jeu</small><b><?= $h($at($s['last_pull_at'])) ?></b></div>
            <div><small>Dernière modification ici</small><b><?= $h($at($s['content_updated_at'])) ?></b></div>
            <div><small>Personnes</small><b><?= count($rows) ?></b></div>
        </div>
        <?php if (($s['content_updated_at'] ?? '') > ($s['last_pull_at'] ?? '')): ?>
            <div class="fx-caut a"><svg class="i"><use href="#fx-sync"></use></svg>Modifié depuis la dernière récupération <small>Le jeu le prendra à la prochaine récupération (module « Récupérer depuis Athena » ou rafraîchissement automatique).</small></div>
        <?php endif; ?>
    </section>

    <div class="fx-twocol">
        <section class="fx-card">
            <form class="fx-rh" method="get" action="<?= $h($base) ?>" data-fx-instant role="search">
                <b>Personnes</b><span class="sp"></span>
                <label class="sr-only" for="fx-lq">Chercher une personne</label><input class="fx-in" id="fx-lq" name="q" value="<?= $h($q) ?>" placeholder="Nom ou identifiant d’IA" style="max-width:240px;height:34px" data-fx-focus>
            </form>
            <?php if ($rows === []): ?><div class="fx-idle"><div><h3>Aucune personne</h3><p>Ajoutez des personnes à droite, importez un fichier, ou lancez « Synchroniser les IA avec Athena » depuis le jeu.</p></div></div><?php endif; ?>
            <?php foreach ($rows as $row): ?>
                <?php $i = $row['identity']; $p = $row['profile']; $m = $row['member']; ?>
                <a class="fx-hit" href="<?= $h($base . '/personnes/' . (int) $i['id']) ?>" style="grid-template-columns:minmax(0,1fr) auto">
                    <span style="min-width:0">
                        <span class="fx-hn"><b translate="no"><?= $h($i['display_name']) ?></b>
                            <span class="fx-flg <?= $m['origin'] === 'game' ? 'b' : '' ?>"><?= $m['origin'] === 'game' ? 'IA du jeu' : 'Créée ici' ?></span>
                            <?php if ($p !== null && !$p['in_files']): ?><span class="fx-flg a">Hors fichiers</span><?php endif; ?>
                            <?php if ($row['hidden'] > 0): ?><span class="fx-pill h"><?= (int) $row['hidden'] ?> donnée<?= $row['hidden'] > 1 ? 's' : '' ?> cachée<?= $row['hidden'] > 1 ? 's' : '' ?></span><?php endif; ?>
                        </span>
                        <span class="fx-kv"><span><?= $h($i['ref']) ?></span><?php if ($m['ai_uid']): ?><span>IA <b translate="no"><?= $h($m['ai_uid']) ?></b></span><?php endif; ?><?php if ($p['affiliation'] ?? null): ?><span translate="no"><?= $h($p['affiliation']) ?></span><?php endif; ?><?php if ($m['last_seen_at']): ?><span>vue en jeu <?= $h(\App\Services\Sse\SseFileRecordService::ago((string) $m['last_seen_at'], gmdate('Y-m-d H:i:s'))) ?></span><?php endif; ?></span>
                    </span>
                    <span class="fx-sc"><small><?= $h(($p['country'] ?? null) ? Packs::label((string) $p['country']) : '') ?></small></span>
                </a>
            <?php endforeach; ?>
        </section>

        <aside class="fx-col">
            <form class="fx-card" method="post" action="<?= $h($base) ?>"><?= $csrf ?><input type="hidden" name="action" value="<?= $locked ? 'phase' : 'settings' ?>">
                <div class="fx-ch"><h2>Réglages de la session</h2></div>
                <div class="fx-fs">
                    <label class="fx-f"><span>Phase en cours <em><?= (int) $s['phase'] ?></em></span><input class="fx-in mono" type="number" name="phase" min="1" max="20" value="<?= (int) $s['phase'] ?>"><small>Une donnée « visible à partir de la phase 2 » se révèle quand vous passez ici en phase 2. Ex. l’alias du chef n’apparaît qu’après l’interrogatoire.</small></label>
                    <?php if (!$locked): ?>
                        <label class="fx-f"><span>Titre</span><input class="fx-in" name="title" maxlength="160" value="<?= $h($s['title']) ?>"></label>
                        <label class="fx-f"><span>Pays par défaut</span><select class="fx-in" name="country"><?php foreach ($countries as $code): ?><option value="<?= $h($code) ?>"<?= ($s['country'] ?? '') === $code ? ' selected' : '' ?>><?= $h(Packs::label($code)) ?></option><?php endforeach; ?></select></label>
                        <label class="fx-f"><span>Rafraîchissement automatique proposé au jeu</span><select class="fx-in" name="auto_refresh_s"><?php foreach ([0 => 'Désactivé', 60 => 'Toutes les minutes', 300 => 'Toutes les 5 minutes', 900 => 'Toutes les 15 minutes'] as $v => $l): ?><option value="<?= $v ?>"<?= (int) $s['auto_refresh_s'] === $v ? ' selected' : '' ?>><?= $h($l) ?></option><?php endforeach; ?></select><small>Le serveur de jeu récupère tout seul vos changements à cet intervalle, si le module l’autorise. Désactivé par défaut.</small></label>
                        <label class="fx-f"><span>Résumé (MJ)</span><textarea class="fx-in" name="summary" maxlength="2000"><?= $h($s['summary'] ?? '') ?></textarea></label>
                    <?php endif; ?>
                </div>
                <div class="fx-ff"><button class="btn" type="submit">Enregistrer</button></div>
            </form>

            <?php if (!$locked): ?>
                <form class="fx-card" method="post" action="<?= $h($base . '/personnes') ?>"><?= $csrf ?>
                    <div class="fx-ch"><h2>Ajouter des personnes</h2></div>
                    <div class="fx-fs">
                        <div class="fx-g2"><label class="fx-f"><span>Nom</span><input class="fx-in m" name="last" maxlength="120"></label><label class="fx-f"><span>Prénom</span><input class="fx-in" name="first" maxlength="120"></label></div>
                        <div class="fx-g2"><label class="fx-f"><span>Sexe</span><select class="fx-in" name="sex"><option value="M">Homme</option><option value="F">Femme</option></select></label><label class="fx-f"><span>Naissance</span><input class="fx-in" type="date" name="dob"></label></div>
                        <label class="fx-f"><span>Pays</span><select class="fx-in" name="country"><?php foreach ($countries as $code): ?><option value="<?= $h($code) ?>"<?= ($s['country'] ?? '') === $code ? ' selected' : '' ?>><?= $h(Packs::label($code)) ?></option><?php endforeach; ?></select></label>
                        <div class="fx-g2" style="align-items:end"><input type="hidden" name="generate" value=""><label class="fx-sw"><input type="checkbox" name="generate" value="1"><i></i>Générer pour ce pays</label><label class="fx-f"><span>Combien</span><input class="fx-in mono" type="number" name="count" min="1" max="20" value="1"></label></div>
                        <small class="fx-help">Générer crée des personnes plausibles du pays (noms courants, adresse, téléphone, plaque, papiers aux bons formats), toutes fictives. Ex. 5 habitants d’un quartier.</small>
                    </div>
                    <div class="fx-ff"><button class="btn pri" type="submit">Ajouter</button></div>
                </form>
                <form class="fx-card" method="post" action="<?= $h($base . '/import') ?>" enctype="multipart/form-data"><?= $csrf ?>
                    <div class="fx-ch"><h2>Importer</h2></div>
                    <div class="fx-fs">
                        <label class="fx-drop"><input type="file" name="file" accept=".json,.csv,application/json,text/csv" required><b>Fichier JSON ou CSV</b><span>Export d’Athena, ou CSV avec en-têtes uid;last;first;sex;dob;country;aliases…</span></label>
                        <small class="fx-help">Les personnes du fichier remplacent leurs données web (même identifiant d’IA). 500 personnes et 2 Mo au plus.</small>
                    </div>
                    <div class="fx-ff"><button class="btn" type="submit"><svg class="i"><use href="#fx-up"></use></svg>Importer</button></div>
                </form>
            <?php endif; ?>

            <section class="fx-card">
                <div class="fx-ch"><h2>Historique</h2><small><?= count($history) ?></small></div>
                <?php if ($history === []): ?><p class="fx-empty">Aucune modification enregistrée.</p><?php endif; ?>
                <ul class="fx-tl" style="padding:0 16px">
                    <?php foreach ($history as $ev): ?>
                        <li><time><?= $h(substr((string) $ev['created_at'], 0, 16)) ?></time><span translate="no"><?= $h($ev['reason'] ?? $ev['action']) ?><?= ($ev['object_id'] ?? null) && ($ev['object_type'] ?? '') === 'sse_identity' ? ' · IDT-' . (int) $ev['object_id'] : '' ?></span><span class="fx-flg" translate="no"><?= $h($ev['actor_label'] ?? '') ?></span></li>
                    <?php endforeach; ?>
                </ul>
            </section>
        </aside>
    </div>
</div>
<?php
$sseContent = ob_get_clean();
require dirname(__DIR__) . '/_layout.php';
