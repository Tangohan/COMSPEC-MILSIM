<?php
declare(strict_types=1);
/**
 * Onglet « Avis de recherche » de la fiche identité : avis actif (détail, notice PDF, levée avec motif), avis passés, bouton
 * pour en placer un. L'avis est un fait de jeu de rôle : aucune donnée réelle. Inclus par identity_show.php.
 *
 * @var array<string,mixed>|null $notice
 * @var list<array<string,mixed>> $notices
 * @var bool $wantedReady
 * @var array<string,mixed> $wantedConfig
 * @var int $iid
 */
use App\Core\Csrf;
use App\Services\Sse\SseWantedAccess;
use App\Support\SseWantedCatalog as W;

$h = $h ?? static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$dlang = (string) ($dlang ?? 'fr') === 'en' ? 'en' : 'fr';
$wr = ($wantedRights ?? null) instanceof SseWantedAccess ? $wantedRights : null;
$canWanted = $wr !== null && $wr->canManage();
$canPdf = $wr !== null && $wr->canGeneratePdf();
$notice = is_array($notice ?? null) ? $notice : null;
$notices = is_array($notices ?? null) ? $notices : [];
$wantedConfig = is_array($wantedConfig ?? null) ? $wantedConfig : [];
$fmt = static function (?string $v) use ($dlang): string {
    $v = trim((string) $v);
    $ts = $v !== '' ? strtotime($v . ' UTC') : false;

    return $ts === false ? '—' : gmdate($dlang === 'en' ? 'Y-m-d H:i' : 'd/m/Y H:i', $ts);
};
$self = 'atak/sse/papiers/identites/' . (int) $iid;
?>
<?php if (isset($wantedReadable) && !$wantedReadable): ?>
    <div class="dos-note dos-note--info">Les avis de recherche relèvent de la section « Fichiers et sujets » : votre accès actuel ne permet pas de les lire.</div>
<?php elseif (empty($wantedReady)): ?>
    <div class="dos-note dos-note--info">Les avis de recherche seront disponibles dès que l’étape de base de données « avis de recherche » aura été passée par l’équipe technique. Rien d’autre n’est modifié en attendant.</div>
<?php elseif ($notice === null): ?>
    <div class="dos-card">
        <div class="dos-empty">
            <strong>Aucun avis de recherche actif</strong>
            Un avis rend l’identité visible dans les contrôles du jeu : chaque apparition crée une détection, et une notice PDF réaliste peut être éditée pour le jeu de rôle.
            <?php if ($canWanted): ?><p><a class="btn btn--danger" href="<?= $h(url($self . '/avis/nouveau')) ?>">Placer un avis de recherche</a></p><?php endif; ?>
        </div>
    </div>
<?php else: ?>
    <article class="dos-card">
        <h3>Avis <span translate="no"><?= $h($notice['ref']) ?></span> · <?= $h(W::typeLabel((string) $notice['notice_type'], $wantedConfig, $dlang)) ?></h3>
        <div class="dos-card__body">
            <dl class="dos-kv">
                <dt>Niveau de menace</dt><dd><?= $h(W::label(W::THREATS, (string) $notice['threat_level'], $dlang)) ?></dd>
                <dt>Armé et dangereux</dt><dd><?= $h(W::label(W::ARMED, (string) $notice['armed'], $dlang)) ?></dd>
                <dt>Motifs et charges</dt><dd translate="no"><?= nl2br($h($notice['reason'] ?? '—')) ?></dd>
                <dt>Mesures à prendre</dt><dd translate="no"><?= nl2br($h($notice['instructions'] ?? '—')) ?></dd>
                <dt>Pays ou zone</dt><dd class="<?= ($notice['zone'] ?? null) === null ? 'is-empty' : '' ?>" translate="no"><?= $h($notice['zone'] ?? 'Non précisé') ?></dd>
                <dt>Dernier lieu connu</dt><dd class="<?= ($notice['last_seen_place'] ?? null) === null ? 'is-empty' : '' ?>" translate="no"><?= $h(($notice['last_seen_place'] ?? null) !== null ? $notice['last_seen_place'] . ($notice['last_seen_at'] !== null ? ' · ' . $fmt($notice['last_seen_at']) : '') : 'Inconnu') ?></dd>
                <dt>Service demandeur</dt><dd translate="no"><?= $h(trim((string) ($notice['contact_label'] ?? '') . (($notice['contact_detail'] ?? null) !== null ? ' — ' . $notice['contact_detail'] : '')) ?: '—') ?></dd>
                <dt>Autorité émettrice</dt><dd translate="no"><?= $h($notice['authority'] ?? '—') ?></dd>
                <dt>Émis le</dt><dd><?= $h($fmt($notice['issued_at'])) ?></dd>
                <dt>Expire le</dt><dd class="<?= $notice['expires_at'] === null ? 'is-empty' : '' ?>"><?= $notice['expires_at'] !== null ? $h($fmt($notice['expires_at'])) : 'Sans date d’expiration' ?></dd>
            </dl>
            <?php if ($canPdf): ?>
                <form class="dos-pdf" method="get" action="<?= $h(url('atak/sse/avis/' . (int) $notice['id'] . '/pdf')) ?>">
                    <label class="sr-only" for="pdf-lang">Langue de la notice</label>
                    <select id="pdf-lang" name="langue">
                        <option value="fr" <?= $dlang === 'fr' ? 'selected' : '' ?>>Français</option>
                        <option value="en" <?= $dlang === 'en' ? 'selected' : '' ?>>English</option>
                    </select>
                    <label class="sr-only" for="pdf-faces">Pages de la notice</label>
                    <select id="pdf-faces" name="faces">
                        <option value="auto">Recto, verso si utile</option>
                        <option value="1">Recto seul</option>
                        <option value="2">Recto et verso</option>
                    </select>
                    <button class="btn" type="submit">Télécharger la notice PDF</button>
                    <button class="btn btn--ghost" type="submit" name="mode" value="impression" formtarget="_blank">Imprimer</button>
                </form>
                <p class="muted">Accessoire de jeu de rôle : l’autorité émettrice est fictive et chaque page porte la mention « Document de fiction — jeu de rôle Arma 3 ». À ne jamais diffuser hors de la communauté.</p>
            <?php endif; ?>
            <?php if ($canWanted): ?>
                <details class="dos-lift">
                    <summary>Lever l’avis ou le clore par la capture</summary>
                    <form class="dos-form" method="post" action="<?= $h(url('atak/sse/avis/' . (int) $notice['id'] . '/lever')) ?>">
                        <?= Csrf::field() ?>
                        <div>
                            <label for="lift-status">Issue de l’avis</label>
                            <select id="lift-status" name="status">
                                <option value="lifted">Avis levé</option>
                                <option value="captured">Personne capturée</option>
                            </select>
                        </div>
                        <div>
                            <label for="lift-reason">Motif (obligatoire)</label>
                            <textarea id="lift-reason" name="reason" required minlength="3" maxlength="500"></textarea>
                        </div>
                        <div><button class="btn btn--danger" type="submit">Clore l’avis</button></div>
                    </form>
                </details>
            <?php endif; ?>
        </div>
    </article>
<?php endif; ?>

<?php if ($notices !== []): ?>
    <div class="panel" style="margin-top:1rem">
        <div class="panel-header"><div class="panel-title">Avis de cette identité</div><div class="panel-meta"><?= count($notices) ?> avis</div></div>
        <div class="table-wrap"><table>
            <thead><tr><th>Référence</th><th>Type</th><th>Niveau de menace</th><th>Statut</th><th>Émis le</th><th>Clos le</th><th>Motif de clôture</th><?php if ($canPdf): ?><th></th><?php endif; ?></tr></thead>
            <tbody>
            <?php foreach ($notices as $n): ?>
                <tr>
                    <td data-label="Référence" translate="no"><?= $h($n['ref']) ?></td>
                    <td data-label="Type"><?= $h(W::typeLabel((string) $n['notice_type'], $wantedConfig, $dlang)) ?></td>
                    <td data-label="Menace"><?= $h(W::label(W::THREATS, (string) $n['threat_level'], $dlang)) ?></td>
                    <td data-label="Statut"><span class="dos-status"><?= $h(W::label(W::STATUSES, (string) $n['status'], $dlang)) ?></span></td>
                    <td data-label="Émis le"><?= $h($fmt($n['issued_at'])) ?></td>
                    <td data-label="Clos le"><?= $h($n['closed_at'] !== null ? $fmt($n['closed_at']) : '—') ?></td>
                    <td data-label="Motif de clôture" translate="no"><?= $h($n['closed_reason'] ?? '—') ?></td>
                    <?php if ($canPdf): ?><td><a class="btn-open" href="<?= $h(url('atak/sse/avis/' . (int) $n['id'] . '/pdf')) ?>">PDF</a></td><?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    </div>
<?php endif; ?>
