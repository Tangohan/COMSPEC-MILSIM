<?php
declare(strict_types=1);

/**
 * Bot Discord Athena (back-office communauté) : proposer l'ajout au serveur Discord, état de la connexion, salons et rôles,
 * test, retrait. Contrôleur : OrganizationDiscordBotController. Styles : back-office-discord-bot.css.
 *
 * @var array{ready:bool, status:string, guild:?array<string,mixed>, settings:array<string,mixed>, channels:array<string,string>,
 *            roles:array<string,string>, invite_url:string, with_roles:bool, service_ready:bool, oauth_ready:bool} $bot
 */

$h = $h ?? static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$bot = is_array($bot ?? null) ? $bot : [];
$status = (string) ($bot['status'] ?? 'none');
$guild = is_array($bot['guild'] ?? null) ? $bot['guild'] : null;
$s = is_array($bot['settings'] ?? null) ? $bot['settings'] : [];
$channels = is_array($bot['channels'] ?? null) ? $bot['channels'] : [];
$roles = is_array($bot['roles'] ?? null) ? $bot['roles'] : [];
$withRoles = !empty($bot['with_roles']);
$statusLabels = [
    'none' => ['Non relié', 'off'],
    'pending' => ['En attente du bot', 'warn'],
    'connected' => ['Connecté', 'ok'],
    'absent' => ['Bot absent', 'bad'],
];
[$statusText, $statusTone] = $statusLabels[$status] ?? $statusLabels['none'];
$permissions = [
    ['Voir les salons', 'Pour savoir où il peut répondre.'],
    ['Envoyer des messages', 'Pour répondre à vos commandes et publier les rappels.'],
    ['Intégrer des liens', 'Pour afficher des fiches lisibles (événements, candidatures).'],
    ['Voir les anciens messages', 'Pour retrouver ses propres annonces sans les republier en double.'],
    ['Ajouter des réactions', 'Pour les réponses présent, absent, peut-être aux événements.'],
];
$commands = [
    ['/effectifs', 'Membres actifs, en attente, absences, candidatures et intégrations en cours.', 'Droit sur les effectifs'],
    ['/membre', 'Fiche courte d’un membre : indicatif, grade, statut, ancienneté.', 'Droit sur les fiches membres'],
    ['/candidatures', 'Candidatures ouvertes et leur statut, avec un lien vers le site pour décider.', 'Droit de recrutement'],
    ['/evenements', 'Prochains événements et votre réponse.', 'Tout membre relié'],
    ['/integration', 'Où en est votre intégration ; pour la gestion, ce qui attend l’équipe.', 'Tout membre relié'],
];
$select = static function (string $name, string $id, array $options, ?string $current, string $none) use ($h): string {
    $html = '<select class="dbot-select" id="' . $h($id) . '" name="' . $h($name) . '"><option value="">' . $h($none) . '</option>';
    foreach ($options as $value => $label) {
        $html .= '<option value="' . $h((string) $value) . '"' . ((string) $value === (string) $current ? ' selected' : '') . '>' . $h((string) $label) . '</option>';
    }

    return $html . '</select>';
};
$check = static function (string $name, string $id, bool $on, string $label, string $help) use ($h): string {
    return '<label class="dbot-check" for="' . $h($id) . '"><input type="checkbox" id="' . $h($id) . '" name="' . $h($name) . '" value="1"' . ($on ? ' checked' : '') . '>'
        . '<span><b>' . $h($label) . '</b><small>' . $h($help) . '</small></span></label>';
};
$pill = '<span class="dbot-pill dbot-pill--' . $h($statusTone) . '">' . $h($statusText) . '</span>';
?>
<div class="dbot">
    <?php if (empty($bot['ready'])): ?>
        <div class="dbot-note dbot-note--bad" role="alert">Le bot Discord n’est pas encore installé sur le site : la mise à jour de la base doit être lancée par l’administrateur de la plateforme.</div>
    <?php endif; ?>
    <?php if (empty($bot['service_ready'])): ?>
        <div class="dbot-note dbot-note--warn" role="status">Le service du bot n’est pas encore activé sur la plateforme : vous pouvez préparer la connexion, il répondra dès que l’administrateur l’aura démarré.</div>
    <?php endif; ?>

    <?php if ($guild === null): ?>
    <section class="ath-card dbot-card" aria-labelledby="dbot-add-title">
        <div class="dbot-server">
            <h2 id="dbot-add-title" class="dbot-h2">Ajouter le bot Athena à votre serveur Discord</h2>
            <?= $pill ?>
        </div>
        <p>Vous êtes redirigé vers Discord pour choisir votre serveur et autoriser le bot. Au retour, Athena relie ce serveur à votre communauté : un serveur pour une communauté.</p>
        <div class="dbot-cols">
            <div>
                <h3 class="dbot-h3">Ce que fera le bot</h3>
                <ul class="dbot-list">
                    <li>Répondre à vos commandes <code>/effectifs</code>, <code>/membre</code>, <code>/candidatures</code>, <code>/evenements</code> et <code>/integration</code>, avec les droits que vos membres ont déjà sur le site.</li>
                    <li>Rappeler les nouvelles candidatures, les intégrations à valider, les bilans 30 jours, les qualifications qui arrivent à échéance et la campagne de fiabilisation (J-14, J-3, jour J).</li>
                    <li>Publier vos événements et recueillir les réponses présent, absent, peut-être.</li>
                    <li>Accueillir les nouveaux arrivants avec le lien pour relier leur compte Athena (si vous l’activez).</li>
                </ul>
                <p class="dbot-fine">Il ne lit pas vos conversations, ne crée ni salon ni rôle, ne modère personne et n’a jamais la permission Administrateur. Les décisions (accepter, refuser) se prennent toujours sur le site.</p>
            </div>
            <div>
                <h3 class="dbot-h3">Permissions demandées et pourquoi</h3>
                <ul class="dbot-perms">
                    <?php foreach ($permissions as [$perm, $why]): ?>
                        <li><b><?= $h($perm) ?></b><span><?= $h($why) ?></span></li>
                    <?php endforeach; ?>
                    <?php if ($withRoles): ?>
                        <li class="is-extra"><b>Gérer les rôles</b><span>Seulement pour copier le niveau d’accès du site vers les deux rôles que vous désignez. Le bot ne touche à aucun autre rôle.</span></li>
                    <?php endif; ?>
                </ul>
                <p class="dbot-fine">
                    <?php if ($withRoles): ?>
                        Synchronisation des rôles incluse. <a href="<?= $h(url('back-office/discord-bot')) ?>">Sans cette permission</a>
                    <?php else: ?>
                        Vous voulez aussi synchroniser des rôles Discord avec vos niveaux d’accès ? <a href="<?= $h(url('back-office/discord-bot') . '?roles=1') ?>">Ajouter la permission Gérer les rôles</a>
                    <?php endif; ?>
                </p>
            </div>
        </div>
        <?php if (!empty($bot['invite_url']) && !empty($bot['ready'])): ?>
            <p class="dbot-actions">
                <a class="ath-btn ath-btn--solid" href="<?= $h((string) $bot['invite_url']) ?>" rel="noopener">Ajouter à mon serveur Discord</a>
                <span class="dbot-fine">Il faut la permission « Gérer le serveur » sur Discord. Le lien est valable 15 minutes.</span>
            </p>
        <?php elseif (empty($bot['oauth_ready'])): ?>
            <div class="dbot-note dbot-note--warn" role="status">L’application Discord de la plateforme n’est pas encore réglée : l’administrateur doit renseigner son identifiant et son secret dans les intégrations de la plateforme.</div>
        <?php endif; ?>
    </section>
    <?php else: ?>
    <section class="ath-card dbot-card" aria-labelledby="dbot-server-title">
        <div class="dbot-server">
            <div>
                <h2 id="dbot-server-title" class="dbot-h2"><?= $h((string) $guild['guild_name']) ?></h2>
                <p class="dbot-fine">
                    <?php if ($status === 'pending'): ?>
                        Serveur relié. Le bot n’a pas encore répondu : ajoutez-le si ce n’est pas fait, vérifiez que son service tourne, puis patientez quelques minutes.
                    <?php elseif ($status === 'absent'): ?>
                        Le bot n’a pas donné signe de vie depuis plus de deux heures : il a peut-être été retiré du serveur ou son service est arrêté.
                    <?php else: ?>
                        Le bot est présent et répond pour votre communauté.
                    <?php endif; ?>
                </p>
            </div>
            <div class="dbot-server__btns">
                <?= $pill ?>
                <?php if (!empty($bot['invite_url']) && in_array($status, ['pending', 'absent'], true)): ?>
                    <a class="ath-btn" href="<?= $h((string) $bot['invite_url']) ?>" rel="noopener">Ajouter le bot au serveur</a>
                <?php endif; ?>
                <form method="post" action="<?= $h(url('back-office/discord-bot/tester')) ?>">
                    <?= \App\Core\Csrf::field() ?>
                    <button class="ath-btn" type="submit">Tester la connexion</button>
                </form>
            </div>
        </div>
    </section>

    <form method="post" action="<?= $h(url('back-office/discord-bot/reglages')) ?>" class="ath-card dbot-card dbot-form">
        <?= \App\Core\Csrf::field() ?>
        <h2 class="dbot-h2">Salons et rôles</h2>
        <?php if ($channels === []): ?>
            <div class="dbot-note dbot-note--warn" role="status">Les salons apparaîtront ici dès que le bot se sera présenté sur votre serveur.</div>
        <?php endif; ?>
        <div class="dbot-grid">
            <div class="dbot-field"><label for="dbot-announce">Annonces</label>
                <?= $select('announce_channel_id', 'dbot-announce', $channels, (string) ($s['announce_channel_id'] ?? ''), 'Aucun salon') ?>
                <small>Accueil des nouveaux arrivants et annonces générales.</small></div>
            <div class="dbot-field"><label for="dbot-apps">Candidatures</label>
                <?= $select('applications_channel_id', 'dbot-apps', $channels, (string) ($s['applications_channel_id'] ?? ''), 'Aucun salon') ?>
                <small>Salon de l’équipe de recrutement : nouvelles candidatures et rappels de gestion. Réservez-le à votre équipe.</small></div>
            <div class="dbot-field"><label for="dbot-events">Événements</label>
                <?= $select('events_channel_id', 'dbot-events', $channels, (string) ($s['events_channel_id'] ?? ''), 'Aucun salon') ?>
                <small>Publication de vos événements et réponses présent, absent, peut-être.</small></div>
            <div class="dbot-field"><label for="dbot-log">Journal</label>
                <?= $select('log_channel_id', 'dbot-log', $channels, (string) ($s['log_channel_id'] ?? ''), 'Aucun salon') ?>
                <small>Utilisé pour les rappels de gestion si aucun salon de candidatures n’est choisi.</small></div>
            <div class="dbot-field"><label for="dbot-member-role">Rôle des membres</label>
                <?= $select('member_role_id', 'dbot-member-role', $roles, (string) ($s['member_role_id'] ?? ''), 'Aucun rôle') ?>
                <small>Donné aux membres actifs qui ont relié leur compte Athena.</small></div>
            <div class="dbot-field"><label for="dbot-manager-role">Rôle des responsables</label>
                <?= $select('manager_role_id', 'dbot-manager-role', $roles, (string) ($s['manager_role_id'] ?? ''), 'Aucun rôle') ?>
                <small>Donné à ceux qui administrent la communauté sur le site. Discord ne donne jamais de droit sur le site.</small></div>
        </div>
        <div class="dbot-checks">
            <?= $check('reminders_enabled', 'dbot-reminders', !empty($s['reminders_enabled']), 'Rappels automatiques', 'Candidatures, intégrations, bilans 30 jours, qualifications, campagne de fiabilisation.') ?>
            <?= $check('welcome_enabled', 'dbot-welcome', !empty($s['welcome_enabled']), 'Accueil des nouveaux arrivants', 'Demande l’autorisation « Membres du serveur » dans le portail Discord, à activer par l’administrateur de la plateforme.') ?>
            <?= $check('sync_roles', 'dbot-sync', !empty($s['sync_roles']), 'Synchroniser les deux rôles', 'Demande la permission Gérer les rôles. Les rôles sont copiés depuis le site, jamais l’inverse.') ?>
        </div>
        <p class="dbot-actions"><button class="ath-btn ath-btn--solid" type="submit">Enregistrer</button></p>
    </form>

    <?php
    $intake = is_array($bot['intake'] ?? null) ? $bot['intake'] : [];
    $intakeEvents = is_array($intake['events'] ?? null) ? $intake['events'] : [];
    ?>
    <form method="post" action="<?= $h(url('back-office/discord-bot/remontees')) ?>" class="ath-card dbot-card dbot-form" id="dbot-remontees">
        <?= \App\Core\Csrf::field() ?>
        <h2 class="dbot-h2">Remontées du renseignement</h2>
        <p class="dbot-fine">Le bot prévient un salon quand une remontée arrive, presse ou change de main. Le message donne la référence, le type, l’urgence et un lien vers la fiche : jamais le texte, jamais un passage caviardé, jamais un nom ni un identifiant Steam.</p>
        <div class="dbot-grid">
            <div class="dbot-field"><label for="dbot-intake-channel">Salon des remontées</label>
                <?= $select('intake_channel_id', 'dbot-intake-channel', $channels, (string) ($intake['channel_id'] ?? ''), 'Aucun salon') ?>
            </div>
            <div class="dbot-field"><label for="dbot-intake-role">Rôle mentionné (facultatif)</label>
                <?= $select('intake_role_id', 'dbot-intake-role', $roles, (string) ($intake['role_id'] ?? ''), 'Aucun rôle') ?>
            </div>
        </div>
        <div class="dbot-checks">
            <?= $check('intake_enabled', 'dbot-intake-on', !empty($intake['enabled']), 'Activer les notifications des remontées', 'Rien de ce qui est arrivé avant l’activation n’est envoyé.') ?>
            <?php foreach ([
                'new' => ['Nouvelle remontée', 'Chaque compte rendu ou fiche qui arrive.'],
                'urgent' => ['Remontée urgente ou critique', 'Seulement les urgences hautes : remplace « Nouvelle remontée » pour elles.'],
                'ack' => ['Prise en compte', 'Quand quelqu’un répond de la remontée.'],
                'forward' => ['Transmise', 'Quand elle part vers un échelon ou un dossier.'],
                'late' => ['En retard', 'Quand le délai de prise en compte est dépassé.'],
            ] as $code => [$label, $help]): ?>
                <label class="dbot-check" for="dbot-intake-<?= $h($code) ?>"><input type="checkbox" id="dbot-intake-<?= $h($code) ?>" name="intake_events[]" value="<?= $h($code) ?>"<?= in_array($code, $intakeEvents, true) ? ' checked' : '' ?>><span><b><?= $h($label) ?></b><small><?= $h($help) ?></small></span></label>
            <?php endforeach; ?>
        </div>
        <p class="dbot-actions"><button class="ath-btn ath-btn--solid" type="submit">Enregistrer</button></p>
    </form>

    <section class="ath-card dbot-card" aria-labelledby="dbot-cmd-title">
        <h2 id="dbot-cmd-title" class="dbot-h2">Ce que vos équipes peuvent faire sur Discord</h2>
        <table class="dbot-table">
            <thead><tr><th scope="col">Commande</th><th scope="col">Réponse</th><th scope="col">Qui peut la lancer</th></tr></thead>
            <tbody>
            <?php foreach ($commands as [$cmd, $what, $who]): ?>
                <tr><td><code><?= $h($cmd) ?></code></td><td><?= $h($what) ?></td><td><?= $h($who) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <p class="dbot-fine">La personne qui lance une commande doit avoir relié son compte Discord à Athena (profil › Discord) et avoir, sur le site, le droit correspondant. Aucune réponse ne contient d’état civil, d’e-mail, de photo ni de papier.</p>
    </section>

    <section class="ath-card dbot-card dbot-danger" aria-labelledby="dbot-remove-title">
        <h2 id="dbot-remove-title" class="dbot-h2">Retirer le serveur</h2>
        <p>Le bot cesse de répondre pour cette communauté et ses réglages sont effacés. Vos membres gardent leur compte Athena.</p>
        <form method="post" action="<?= $h(url('back-office/discord-bot/delier')) ?>" class="dbot-actions">
            <?= \App\Core\Csrf::field() ?>
            <label class="dbot-check" for="dbot-confirm"><input type="checkbox" id="dbot-confirm" name="confirm" value="1"><span>Je confirme le retrait de ce serveur.</span></label>
            <button class="ath-btn" type="submit">Retirer le serveur</button>
        </form>
    </section>
    <?php endif; ?>
</div>
