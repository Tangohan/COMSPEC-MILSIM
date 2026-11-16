<?php
declare(strict_types=1);
ob_start();

use App\Services\Intel\IntelIntakeService;

/**
 * Bureau SSE › Outils et réglages › Réglages des remontées : délais de prise en compte, états de la file et
 * notifications Discord (anciennement dans « Intégrations › Bot Discord » du back-office, même réglage, mêmes champs).
 * Trois sections, une ligne de description chacune ; la modification des notifications s'ouvre dans une fenêtre et
 * n'est offerte qu'aux administrateurs de la communauté. Styles : remontees.css, sse-remontees.css, sse-fiches-etiquettes.css.
 *
 * @var bool $settingsCanEdit
 * @var bool $settingsReady
 * @var array{enabled: bool, channel_id: string, role_id: string, events: list<string>, enabled_at: string} $settingsDiscord
 * @var array<string, string> $settingsChannels
 * @var array<string, string> $settingsRoles
 * @var array{ready: bool, reason: string, channel: string, linked: bool} $settingsStatus
 * @var list<array{level: string, label: string, target: string}> $settingsDelays
 * @var string $csrfToken
 */
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$canEdit = !empty($settingsCanEdit);
$ready = !empty($settingsReady);
$s = (is_array($settingsDiscord ?? null) ? $settingsDiscord : []) + ['enabled' => false, 'channel_id' => '', 'role_id' => '', 'events' => [], 'enabled_at' => ''];
$channels = is_array($settingsChannels ?? null) ? $settingsChannels : [];
$roles = is_array($settingsRoles ?? null) ? $settingsRoles : [];
$status = (is_array($settingsStatus ?? null) ? $settingsStatus : []) + ['ready' => false, 'reason' => '', 'channel' => '', 'linked' => false];
$delays = is_array($settingsDelays ?? null) ? $settingsDelays : [];
$events = [
    'new' => ['Nouvelle remontée', 'Chaque compte rendu ou fiche qui arrive.'],
    'urgent' => ['Remontée urgente ou critique', 'Seulement les urgences hautes : remplace « Nouvelle remontée » pour elles.'],
    'ack' => ['Prise en compte', 'Quand quelqu’un répond de la remontée.'],
    'forward' => ['Transmise', 'Quand elle part vers un échelon ou un dossier.'],
    'late' => ['En retard', 'Quand le délai de prise en compte est dépassé.'],
];
$on = array_values(array_intersect(array_keys($events), (array) $s['events']));
$csrf = '<input type="hidden" name="_csrf_token" value="' . $h((string) ($csrfToken ?? '')) . '">';
$closeIco = '<svg class="rmt-i" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6L6 18"/></svg>';
$sseExtraStyles = ($sseExtraStyles ?? '') . '<link rel="stylesheet" href="' . $h(asset_url('assets/css/sse-fiches-etiquettes.css')) . '">'
    . '<link rel="stylesheet" href="' . $h(asset_url('assets/css/remontees.css')) . '">'
    . '<link rel="stylesheet" href="' . $h(asset_url('assets/css/sse-remontees.css')) . '">';
$sseExtraScripts = ($sseExtraScripts ?? '') . '<script defer src="' . $h(asset_url('assets/js/remontees.js')) . '"></script>';
?>
<div class="rmt rmt--sse rmt-settings" data-rmt data-rmt-copied="Copié dans le presse-papiers." data-rmt-copy-failed="La copie a échoué : sélectionnez le texte et copiez-le à la main.">
    <header class="fng-strip rmt-strip">
        <div class="fng-strip__halo" aria-hidden="true"></div>
        <div class="fng-strip__veil" aria-hidden="true"></div>
        <div class="fng-strip__body">
            <div class="fng-strip__identity">
                <span class="fng-strip__index" aria-hidden="true">R.09</span>
                <div>
                    <h1 class="fng-title">Réglages des remontées</h1>
                    <p class="fng-strip__desc">Les délais de prise en compte, les états de la file et les notifications Discord des remontées du terrain.</p>
                </div>
            </div>
            <div class="fng-strip__meta">
                <p class="fng-key"><strong><?= count($on) ?></strong><span>notifications actives</span></p>
                <dl class="fng-figs">
                    <div><dt>Discord</dt><dd><?= $s['enabled'] ? 'Activé' : 'Désactivé' ?></dd></div>
                    <div><dt>délais cibles</dt><dd><?= count($delays) ?></dd></div>
                    <div><dt>états de la file</dt><dd><?= count(IntelIntakeService::STATES) ?></dd></div>
                </dl>
            </div>
        </div>
    </header>
    <?php $rmtNav = 'settings'; require __DIR__ . '/_intake_nav.php'; ?>

    <div class="rmt-settings__grid">
        <section class="rmt-panel" aria-labelledby="rmt-set-delais">
            <h2 id="rmt-set-delais" class="rmt-h2">Délais de prise en compte</h2>
            <p class="rmt-desc">Temps laissé pour prendre en compte une remontée selon son urgence ; au-delà, elle passe « En retard ».</p>
            <?php if ($delays === []): ?>
                <p class="rmt-dim">Aucun délai défini.</p>
            <?php else: ?>
                <dl class="rmt-rows" aria-label="Délais cibles par urgence">
                    <?php foreach ($delays as $row): ?>
                        <div><dt><?= $h($row['label']) ?></dt><dd><strong><?= $h($row['target']) ?></strong></dd></div>
                    <?php endforeach; ?>
                </dl>
            <?php endif; ?>
        </section>

        <section class="rmt-panel" aria-labelledby="rmt-set-etats">
            <h2 id="rmt-set-etats" class="rmt-h2">États de la file</h2>
            <p class="rmt-desc">Le chemin d’une remontée, de son arrivée à son issue : les trois premiers états la gardent dans la file.</p>
            <ul class="rmt-list rmt-states">
                <?php foreach (IntelIntakeService::STATES as $code => $label): $open = in_array($code, IntelIntakeService::OPEN_STATES, true); ?>
                    <li>
                        <strong><?= $h($label) ?></strong>
                        <span class="rmt-dim"><?= $open ? 'Dans la file' : 'Hors de la file' ?> · <?= $h(IntelIntakeService::STATE_HINTS[$code] ?? '') ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>

        <section class="rmt-panel" aria-labelledby="rmt-set-discord" id="discord">
            <h2 id="rmt-set-discord" class="rmt-h2">Notifications Discord</h2>
            <p class="rmt-desc">Le bot prévient un salon quand une remontée arrive, presse ou change de main : jamais le texte, jamais un passage caviardé, jamais un nom ni un identifiant Steam.</p>
            <dl class="rmt-rows">
                <div><dt>État</dt><dd><?= $status['ready'] ? 'Prêt : les notifications partent dans <strong translate="no">' . $h($status['channel']) . '</strong>' : $h($status['reason'] !== '' ? $status['reason'] : 'Notifications désactivées.') ?></dd></div>
                <div><dt>Salon</dt><dd translate="no"><?= $s['channel_id'] !== '' ? $h('#' . ($channels[$s['channel_id']] ?? $s['channel_id'])) : '<span class="rmt-dim" translate="yes">Aucun salon</span>' ?></dd></div>
                <div><dt>Rôle mentionné</dt><dd translate="no"><?= $s['role_id'] !== '' ? $h('@' . ($roles[$s['role_id']] ?? $s['role_id'])) : '<span class="rmt-dim" translate="yes">Aucun rôle</span>' ?></dd></div>
                <div><dt>Événements</dt><dd><?= $on !== [] ? $h(implode(', ', array_map(static fn (string $k): string => $events[$k][0], $on))) : '<span class="rmt-dim">Aucun</span>' ?></dd></div>
            </dl>
            <?php if ($canEdit && $ready): ?>
                <p class="rmt-btnrow"><button type="button" class="rmt-btn rmt-btn--primary" data-rmt-open="rmt-dlg-discord-set" aria-haspopup="dialog">Modifier les notifications</button></p>
            <?php elseif (!$ready): ?>
                <p class="rmt-notice">Le bot Discord n’est pas encore installé pour la communauté : reliez d’abord un serveur depuis « Intégrations › Bot Discord ».</p>
            <?php else: ?>
                <p class="rmt-dim">Modifier ce réglage est réservé aux administrateurs de la communauté.</p>
            <?php endif; ?>
        </section>
    </div>

    <?php if ($canEdit && $ready): ?>
        <dialog class="rmt-dialog" id="rmt-dlg-discord-set" aria-labelledby="rmt-dlg-discord-set-title">
            <form method="post" action="<?= $h(url('atak/sse/remontees/reglages')) ?>" class="rmt-dialog__card">
                <?= $csrf ?>
                <header class="rmt-dialog__head"><div><h2 id="rmt-dlg-discord-set-title">Notifications Discord</h2><p class="rmt-desc">Rien de ce qui est arrivé avant l’activation n’est envoyé.</p></div><button type="button" class="rmt-iconbtn" data-rmt-close aria-label="Fermer la fenêtre" title="Fermer"><?= $closeIco ?></button></header>
                <div class="rmt-dialog__body">
                    <label class="rmt-label" for="rmt-set-channel">Salon des remontées</label>
                    <select id="rmt-set-channel" name="intake_channel_id" autofocus>
                        <option value="">Aucun salon</option>
                        <?php foreach ($channels as $cid => $cname): ?><option value="<?= $h((string) $cid) ?>"<?= (string) $cid === $s['channel_id'] ? ' selected' : '' ?> translate="no">#<?= $h($cname) ?></option><?php endforeach; ?>
                    </select>
                    <label class="rmt-label" for="rmt-set-role">Rôle mentionné (facultatif)</label>
                    <select id="rmt-set-role" name="intake_role_id">
                        <option value="">Aucun rôle</option>
                        <?php foreach ($roles as $rid => $rname): ?><option value="<?= $h((string) $rid) ?>"<?= (string) $rid === $s['role_id'] ? ' selected' : '' ?> translate="no">@<?= $h($rname) ?></option><?php endforeach; ?>
                    </select>
                    <fieldset class="rmt-checks">
                        <legend class="rmt-label">Notifier</legend>
                        <label class="rmt-opt" for="rmt-set-on"><input type="checkbox" id="rmt-set-on" name="intake_enabled" value="1"<?= $s['enabled'] ? ' checked' : '' ?>><span>Activer les notifications des remontées</span><small>Interrupteur général du réglage.</small></label>
                        <?php foreach ($events as $code => [$label, $help]): ?>
                            <label class="rmt-opt" for="rmt-set-<?= $h($code) ?>"><input type="checkbox" id="rmt-set-<?= $h($code) ?>" name="intake_events[]" value="<?= $h($code) ?>"<?= in_array($code, $on, true) ? ' checked' : '' ?>><span><?= $h($label) ?></span><small><?= $h($help) ?></small></label>
                        <?php endforeach; ?>
                    </fieldset>
                </div>
                <footer class="rmt-dialog__foot"><button type="button" class="rmt-btn" data-rmt-close>Annuler</button><button type="submit" class="rmt-btn rmt-btn--primary">Enregistrer</button></footer>
            </form>
        </dialog>
    <?php endif; ?>
</div>
<?php
$sseContent = ob_get_clean();
require __DIR__ . '/_layout.php';
