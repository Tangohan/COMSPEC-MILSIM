<?php
declare(strict_types=1);
/**
 * Rapport d'extraction d'un téléphone du jeu : synthèse (appareil, propriétaire, lieu, empreinte SHA-256), onglets par
 * catégorie, recherche dans l'extraction, numéros connus aux fichiers, rattachement à une fiche, export JSON ou CSV.
 *
 * @var array<string,mixed> $ext
 * @var string $tab
 * @var string $q
 * @var array<string,int> $counts
 * @var list<array<string,mixed>> $items
 * @var array<string,mixed>|null $subject
 */
use App\Services\Sse\SsePhoneExtractionService as X;
use App\Support\SseFileText;

ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
require __DIR__ . '/_nav.php';
$self = url('atak/sse/fichiers/telephones/' . (int) $ext['id']);
$csrf = '<input type="hidden" name="_csrf_token" value="' . $h($csrfToken) . '">';
$known = count(array_filter($items, static fn (array $it): bool => $it['known_identity_id'] !== null));
$mark = static function (?string $text) use ($h, $q): string {
    $t = $h((string) $text);
    if ($q === '' || $t === '') {
        return $t;
    }

    return (string) preg_replace('/(' . preg_quote($h($q), '/') . ')/iu', '<mark>$1</mark>', $t);
};
$dir = ['in' => 'Entrant', 'out' => 'Sortant', 'missed' => 'Manqué'];
?>
<div class="fx">
    <div class="fx-th">
        <div><h1 translate="no"><?= $h($ext['ref']) ?> · <?= $h($ext['device_label'] ?? $ext['device_uid']) ?></h1><p>Rapport d’exploitation d’un téléphone du jeu. Copie <?= $ext['complete'] ? 'complète' : 'partielle (des parties manquent encore)' ?>, empreinte SHA-256 de la copie reçue.</p></div>
        <div class="fx-acts">
            <a class="btn" href="<?= $h(url('atak/sse/fichiers/telephones')) ?>">Tous les téléphones</a>
            <a class="btn" href="<?= $h($self . '/export?format=json') ?>">Exporter (JSON)</a>
            <a class="btn" href="<?= $h($self . '/export?format=csv') ?>">Exporter (CSV)</a>
        </div>
    </div>
    <article class="fx-card">
        <div class="fx-xs">
            <div><small>Appareil</small><b translate="no"><?= $h($ext['device_model'] ?? $ext['device_uid']) ?></b></div>
            <div><small>Propriétaire</small><b translate="no"><?= $h($ext['owner_label'] ?? 'inconnu') ?><?= $ext['owner_kind'] === 'ai' ? ' (IA)' : '' ?></b></div>
            <div><small>Extrait</small><b><?= $h(substr((string) $ext['created_at'], 0, 16)) ?>Z<?= ($ext['grid'] ?? null) ? ' · ' . $h($ext['grid']) : '' ?></b></div>
            <div><small>SHA-256</small><b><?= $h(substr((string) $ext['sha256'], 0, 8) . '…' . substr((string) $ext['sha256'], -4)) ?></b></div>
        </div>
        <div class="fx-rh">
            <?php if ($subject !== null): ?>
                <span>Rattachée à <a href="<?= $h(url('atak/sse/fichiers/sujet/' . (int) $subject['id'])) ?>" translate="no"><?= $h($subject['display_name']) ?></a></span>
            <?php else: ?><span class="fx-help">Non rattachée à une fiche.</span><?php endif; ?>
            <span class="sp"></span>
            <?php if (!empty($filesCanManage)): ?>
                <form method="post" action="<?= $h($self . '/rattacher') ?>" style="display:flex;gap:6px;align-items:center"><?= $csrf ?>
                    <label class="sr-only" for="fx-att">Fiche à rattacher</label><input class="fx-in mono" id="fx-att" name="target" placeholder="IDT-…" style="height:32px;width:120px">
                    <button class="btn sm" type="submit"><?= $subject !== null ? 'Changer' : 'Rattacher à une fiche' ?></button>
                </form>
            <?php endif; ?>
        </div>
        <form class="fx-rh" method="get" action="<?= $h($self) ?>" role="search">
            <input type="hidden" name="onglet" value="<?= $h($tab) ?>">
            <label class="fx-f" style="flex:1 1 240px"><span>Chercher dans l’extraction</span><input class="fx-in" name="q" value="<?= $h($q) ?>" placeholder="ex. un nom, un numéro, « garage »" data-fx-focus></label>
            <button class="btn" type="submit"><svg class="i"><use href="#fx-search"></use></svg>Chercher</button>
            <?php if ($q !== ''): ?><a class="btn link" href="<?= $h($self . '?onglet=' . rawurlencode($tab)) ?>">Effacer</a><?php endif; ?>
        </form>
        <nav class="fx-xt" aria-label="Catégories">
            <?php foreach (X::CATEGORIES as $k => $label): ?>
                <a href="<?= $h($self . '?onglet=' . $k) ?>" class="<?= $tab === $k && $q === '' ? 'on' : '' ?>"><?= $h($label) ?><em><?= (int) ($counts[$k] ?? 0) ?></em></a>
            <?php endforeach; ?>
            <a href="<?= $h($self . '?onglet=reseau') ?>" class="<?= $tab === 'reseau' ? 'on' : '' ?>">Réseau</a>
        </nav>
        <?php if ($q !== ''): ?><div class="fx-rh"><b><?= count($items) ?> résultat<?= count($items) > 1 ? 's' : '' ?></b><small>pour « <?= $h($q) ?> », toutes catégories</small></div><?php endif; ?>
        <?php if ($tab === 'reseau'): ?>
            <div class="fx-ds" style="border-top:0">
                <?php if ($ext['network'] === []): ?><p>Aucune donnée réseau dans cette copie.</p><?php else: ?>
                    <dl class="fx-idg">
                        <?php foreach (['carrier' => 'Opérateur', 'number' => 'Numéro', 'imei' => 'IMEI (jeu)', 'mac' => 'MAC (jeu)'] as $k => $label): ?><div><dt><?= $h($label) ?></dt><dd translate="no"><?= $h($ext['network'][$k] ?? '—') ?></dd></div><?php endforeach; ?>
                        <div><dt>Réseaux Wi-Fi vus</dt><dd translate="no"><?= $h(implode(', ', (array) ($ext['network']['wifi'] ?? [])) ?: '—') ?></dd></div>
                    </dl>
                <?php endif; ?>
            </div>
        <?php elseif ($items === []): ?>
            <p class="fx-empty">Rien dans cette catégorie.</p>
        <?php elseif ($tab === 'messages' && $q === ''): ?>
            <div class="fx-msg"><?php foreach ($items as $it): ?><div class="fx-mg<?= $it['direction'] === 'out' ? ' me' : '' ?>"><small><?= $h($it['at'] ?? '') ?> · <?= $h($it['title'] ?? '') ?><?= ($it['number'] ?? '') !== '' ? ' · ' . $h($it['number']) : '' ?></small><span translate="no"><?= $mark($it['body']) ?></span></div><?php endforeach; ?></div>
        <?php else: ?>
            <div class="fx-tbw"><table class="fx-tb resp">
                <thead><tr><?= $q !== '' ? '<th>Catégorie</th>' : '' ?><th>Heure</th><th>Nom, titre</th><th>Détail</th><th>Numéro, grille</th><th>Aux fichiers</th></tr></thead>
                <tbody>
                <?php foreach ($items as $it): ?>
                    <tr class="<?= $it['known_identity_id'] !== null ? 'fl' : '' ?>">
                        <?= $q !== '' ? '<td>' . $h(X::CATEGORIES[$it['category']] ?? $it['category']) . '</td>' : '' ?>
                        <td class="m"><?= $h($it['at'] ?? '—') ?></td>
                        <td translate="no"><b><?= $mark($it['title']) ?></b><?= $it['direction'] !== null && $it['category'] === 'calls' ? ' <span class="fx-flg">' . $h($dir[$it['direction']] ?? '') . '</span>' : '' ?></td>
                        <td translate="no"><?= $mark($it['body']) ?></td>
                        <td class="m" translate="no"><?= $mark($it['number'] ?? $it['grid'] ?? '') ?></td>
                        <td><?php if ($it['known_identity_id'] !== null): ?><a href="<?= $h(url('atak/sse/fichiers/sujet/' . (int) $it['known_identity_id'])) ?>">Fiche IDT-<?= (int) $it['known_identity_id'] ?></a><?php else: ?><span class="fx-help">—</span><?php endif; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
        <?php endif; ?>
        <?php if ($known > 0): ?><div class="fx-drf"><span class="fx-flg r"><?= $known ?> numéro<?= $known > 1 ? 's' : '' ?> connu<?= $known > 1 ? 's' : '' ?> aux fichiers</span></div><?php endif; ?>
    </article>
</div>
<?php
$sseContent = ob_get_clean();
require dirname(__DIR__) . '/_layout.php';
