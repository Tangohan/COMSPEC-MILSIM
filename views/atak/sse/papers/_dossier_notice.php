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
use App\Services\Sse\SseWantedAttachments;
use App\Support\SseWantedCatalog as W;
use App\Support\SseWantedPhysical as WP;

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
$noticeExtras = is_array($noticeExtras ?? null) ? $noticeExtras : [];
$noticeAttachments = is_array($noticeAttachments ?? null) ? $noticeAttachments : [];
$noticeAttachLock = is_array($noticeAttachLock ?? null) ? $noticeAttachLock : ['allowed' => true];
$libraryPool = array_values(array_filter(is_array($filePhotos ?? null) ? $filePhotos : [], static fn (array $p): bool => ($p['visibility'] ?? 'known') !== 'hidden'));
// Badges de codes : le code, son libellé du jour de la pose, un lien vers la liste filtrée.
$codeBadges = static function (array $list, string $kindLabel) use ($h, $dlang): string {
    if ($list === []) {
        return '';
    }
    $out = '<ul class="wc-codes wc-codes--kind" aria-label="' . $h($kindLabel) . '">';
    foreach ($list as $c) {
        $name = (string) ($dlang === 'en' ? $c['en'] : $c['fr']);
        $out .= '<li><a class="wc-codelink" translate="no" href="' . $h(url('atak/sse/avis') . '?statut=&code=' . rawurlencode((string) $c['code'])) . '" title="' . $h($name) . '"><b>' . $h($c['code']) . '</b>' . $h($name) . '</a></li>';
    }

    return $out . '</ul>';
};
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
                <?php $nx = $noticeExtras[(int) $notice['id']] ?? ['codes' => ['reasons' => [], 'measures' => []], 'physical' => []]; ?>
                <dt>Motifs et charges</dt><dd><?= $codeBadges($nx['codes']['reasons'], 'Codes de motifs') ?><span translate="no"><?= nl2br($h($notice['reason'] ?? '—')) ?></span></dd>
                <dt>Mesures à prendre</dt><dd><?= $codeBadges($nx['codes']['measures'], 'Codes de mesures') ?><span translate="no"><?= nl2br($h($notice['instructions'] ?? '—')) ?></span></dd>
                <dt>Pays ou zone</dt><dd class="<?= ($notice['zone'] ?? null) === null ? 'is-empty' : '' ?>" translate="no"><?= $h($notice['zone'] ?? 'Non précisé') ?></dd>
                <dt>Dernier lieu connu</dt><dd class="<?= ($notice['last_seen_place'] ?? null) === null ? 'is-empty' : '' ?>" translate="no"><?= $h(($notice['last_seen_place'] ?? null) !== null ? $notice['last_seen_place'] . ($notice['last_seen_at'] !== null ? ' · ' . $fmt($notice['last_seen_at']) : '') : 'Inconnu') ?></dd>
                <dt>Service demandeur</dt><dd translate="no"><?= $h(trim((string) ($notice['contact_label'] ?? '') . (($notice['contact_detail'] ?? null) !== null ? ' — ' . $notice['contact_detail'] : '')) ?: '—') ?></dd>
                <dt>Autorité émettrice</dt><dd translate="no"><?= $h($notice['authority'] ?? '—') ?></dd>
                <dt>Émis le</dt><dd><?= $h($fmt($notice['issued_at'])) ?></dd>
                <dt>Expire le</dt><dd class="<?= $notice['expires_at'] === null ? 'is-empty' : '' ?>"><?= $notice['expires_at'] !== null ? $h($fmt($notice['expires_at'])) : 'Sans date d’expiration' ?></dd>
            </dl>
            <?php $physRows = WP::rows($nx['physical'], $dlang); ?>
            <?php if ($physRows !== []): ?>
                <details class="dos-lift" open>
                    <summary>Signalement physique<?= isset($nx['physical']['snapshot_at']) ? ' · relevé le ' . $h($fmt((string) $nx['physical']['snapshot_at'])) : '' ?></summary>
                    <dl class="wc-phys">
                        <?php foreach ($physRows as $pr): ?><div class="wc-phys__row"><dt><?= $h($pr['label']) ?></dt><dd translate="no"><?= $h($pr['value']) ?></dd></div><?php endforeach; ?>
                    </dl>
                </details>
            <?php endif; ?>
            <?php if (!empty($attachmentsReady)): ?>
                <details class="dos-lift" <?= $noticeAttachments !== [] ? 'open' : '' ?>>
                    <summary>Images jointes (<?= count($noticeAttachments) ?>)</summary>
                    <?php if (empty($noticeAttachLock['allowed'])): ?>
                        <p class="muted">Cette identité est celle d’un membre (<?= $h(\App\Services\Sse\SseMemberImageGate::LABEL) ?>) : les images de l’avis ne s’affichent qu’avec l’autorisation de la hiérarchie du membre.</p>
                    <?php else: ?>
                        <?php if ($noticeAttachments === []): ?><p class="muted">Aucune image jointe à cet avis.</p><?php else: ?>
                            <ul class="wc-gal" data-wc-gal>
                                <?php foreach ($noticeAttachments as $ai => $att): ?>
                                    <li class="wc-gal__item">
                                        <button class="wc-gal__open" type="button" data-wc-gal-open="<?= (int) $ai ?>" data-full="<?= $h($att['url']) ?>" data-cap="<?= $h(trim((string) ($dlang === 'en' ? $att['kind_en'] : $att['kind_fr']) . ((string) ($att['caption'] ?? '') !== '' ? ' · ' . $att['caption'] : ''))) ?>" aria-label="<?= $h('Ouvrir l’image : ' . ($dlang === 'en' ? $att['kind_en'] : $att['kind_fr'])) ?>">
                                            <img src="<?= $h($att['thumb_url']) ?>" alt="<?= $h(($att['caption'] ?? '') !== '' ? $att['caption'] : ($dlang === 'en' ? $att['kind_en'] : $att['kind_fr'])) ?>" loading="lazy">
                                        </button>
                                        <?php if (!empty($att['is_main'])): ?><span class="wc-gal__tag">PDF</span><?php endif; ?>
                                        <p class="wc-gal__cap"><strong><?= $h($dlang === 'en' ? $att['kind_en'] : $att['kind_fr']) ?></strong><?= ($att['caption'] ?? '') !== '' ? ' · ' . $h($att['caption']) : '' ?></p>
                                        <?php if ($canWanted): ?>
                                            <div class="wc-gal__act">
                                                <?php if (empty($att['is_main'])): ?><form method="post" action="<?= $h(url('atak/sse/avis/pieces/' . (int) $att['id'] . '/principale')) ?>"><?= Csrf::field() ?><button class="btn btn--ghost btn--sm" type="submit">Principale</button></form><?php endif; ?>
                                                <form method="post" action="<?= $h(url('atak/sse/avis/pieces/' . (int) $att['id'] . '/retirer')) ?>"><?= Csrf::field() ?><button class="btn btn--ghost btn--sm" type="submit">Retirer</button></form>
                                            </div>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                        <?php if ($canWanted && count($noticeAttachments) < 6): ?>
                            <form class="dos-form wc-addform" method="post" enctype="multipart/form-data" action="<?= $h(url('atak/sse/avis/' . (int) $notice['id'] . '/pieces')) ?>">
                                <?= Csrf::field() ?>
                                <?php for ($slot = 0; $slot < 2; $slot++): ?>
                                    <div class="dos-form__row">
                                        <div><label for="att-f-<?= $slot ?>">Image</label><input id="att-f-<?= $slot ?>" type="file" name="att_file[]" accept="image/jpeg,image/png,image/webp"></div>
                                        <div><label for="att-k-<?= $slot ?>">Type</label><select id="att-k-<?= $slot ?>" name="att_kind[]"><?php foreach (SseWantedAttachments::KINDS as $k => $labels): ?><option value="<?= $h($k) ?>"><?= $h($labels[$dlang === 'en' ? 1 : 0]) ?></option><?php endforeach; ?></select></div>
                                        <div><label for="att-c-<?= $slot ?>">Légende</label><input id="att-c-<?= $slot ?>" type="text" name="att_caption[]" maxlength="160"></div>
                                    </div>
                                <?php endfor; ?>
                                <?php if ($libraryPool !== []): ?>
                                    <fieldset class="wc-library">
                                        <legend>Joindre depuis la photothèque</legend>
                                        <div class="wc-library__grid">
                                            <?php foreach (array_slice($libraryPool, 0, 12) as $ph): $pid = (int) $ph['id']; ?>
                                                <div class="wc-photo"><label class="wc-photo__pick"><input type="checkbox" name="att_photo[]" value="<?= $pid ?>"><img src="<?= $h(\App\Services\Sse\SseSeekPhotoService::photoUrl($ph) ?? '') ?>" alt="<?= $h($ph['caption'] ?? 'Photo #' . $pid) ?>" loading="lazy"><span class="wc-photo__cap"><?= $h($ph['caption'] ?? 'Photo #' . $pid) ?></span></label></div>
                                            <?php endforeach; ?>
                                        </div>
                                    </fieldset>
                                <?php endif; ?>
                                <p class="hint">JPEG, PNG ou WebP, 5 Mo au plus, six images par avis. Les métadonnées sont retirées ; les images restent dans l’espace privé et vont au PDF, jamais au jeu.</p>
                                <div><button class="btn btn--sm" type="submit">Joindre à l’avis</button></div>
                            </form>
                        <?php endif; ?>
                    <?php endif; ?>
                </details>
            <?php endif; ?>
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
            <thead><tr><th>Référence</th><th>Type et codes</th><th>Niveau de menace</th><th>Statut</th><th>Émis le</th><th>Clos le</th><th>Motif de clôture</th><?php if ($canPdf): ?><th></th><?php endif; ?></tr></thead>
            <tbody>
            <?php foreach ($notices as $n): ?>
                <tr>
                    <td data-label="Référence" translate="no"><?= $h($n['ref']) ?></td>
                    <?php $px = $noticeExtras[(int) $n['id']]['codes'] ?? ['reasons' => [], 'measures' => []]; ?>
                    <td data-label="Type"><?= $h(W::typeLabel((string) $n['notice_type'], $wantedConfig, $dlang)) ?><?= $codeBadges(array_merge($px['reasons'], $px['measures']), 'Codes') ?></td>
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
