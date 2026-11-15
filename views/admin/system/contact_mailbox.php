<?php
declare(strict_types=1);

use App\Core\Csrf;
use App\Core\Session;
use App\Services\ContactMail\ContactMailRepository;
use App\Support\UtcDateTime;

$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$state = (string) ($mbState ?? 'unconfigured');
$missing = is_array($mbMissing ?? null) ? $mbMissing : [];
$keys = is_array($mbKeys ?? null) ? $mbKeys : [];
$address = (string) ($mbAddress ?? '');
$base = url('admin/system/boite-contact');
$ok = Session::getFlash('success');
$err = Session::getFlash('error');
$threads = is_array($mbThreads ?? null) ? $mbThreads : [];
$total = (int) ($mbTotal ?? 0);
$page = (int) ($mbPage ?? 1);
$perPage = (int) ($mbPerPage ?? ContactMailRepository::PER_PAGE);
$folder = (string) ($mbFolder ?? 'inbox');
$query = (string) ($mbQuery ?? '');
$counts = is_array($mbCounts ?? null) ? $mbCounts : [];
$sync = is_array($mbSync ?? null) ? $mbSync : [];
$folders = [
    'inbox' => 'Boîte de réception',
    'unread' => 'Non lus',
    'todo' => 'À traiter',
    'starred' => 'Étoilés',
    'archived' => 'Archivés',
    'draft' => 'Brouillons',
    'spam' => 'Spam',
    'trash' => 'Corbeille',
];
$fmtShort = static function (mixed $raw): string {
    $t = UtcDateTime::timestamp($raw);
    if ($t === null) {
        return '';
    }

    return date('Y-m-d', $t) === date('Y-m-d') ? date('H:i', $t) : date('d/m/Y', $t);
};
$fmtLong = static function (mixed $raw): string {
    $t = UtcDateTime::timestamp($raw);

    return $t === null ? '' : date('d/m/Y H:i', $t);
};
?>
<div class="pa">
    <div class="pa__frame">
        <header class="pa-hero">
            <h1 class="pa-hero__title">Boîte de contact</h1>
            <p class="pa-hero__lead">
                Les messages reçus sur <?= $h($address) ?> arrivent ici, regroupés en fils de discussion. Vous y répondez
                depuis le site, avec cette adresse comme expéditeur.
            </p>
            <?php if ($state === 'ready'): ?>
                <div class="pa-hero__actions">
                    <form method="post" action="<?= $h($base . '/actualiser') ?>">
                        <?= Csrf::field() ?>
                        <button type="submit" class="pa-btn pa-btn--ghost">Actualiser</button>
                    </form>
                    <a class="pa-btn pa-btn--mint" href="<?= $h($base . '/nouveau') ?>">Nouveau message</a>
                </div>
            <?php endif; ?>
        </header>

        <div class="pa-panel">
            <?php if ($ok): ?>
                <p class="pa-flash pa-flash--ok"><?= $h((string) $ok) ?></p>
            <?php endif; ?>
            <?php if ($err): ?>
                <p class="pa-flash pa-flash--err"><?= $h((string) $err) ?></p>
            <?php endif; ?>

            <?php if ($state === 'unconfigured'): ?>
                <section class="pa-card">
                    <h2 class="pa-card__title">Boîte non configurée</h2>
                    <p class="pa-card__help">
                        Renseignez CONTACT_MAIL_PASSWORD dans le fichier .env du serveur (le mot de passe de la boîte
                        <?= $h($address) ?>), puis rechargez cette page. Tant que ce mot de passe manque, le site ne se connecte
                        à aucun serveur de messagerie et ne lit rien.
                    </p>
                    <ul class="cm-keys">
                        <?php foreach ($keys as $key => $required): ?>
                            <li>
                                <code><?= $h((string) $key) ?></code>
                                <span class="pa-pill <?= $required ? 'pa-pill--amber' : 'pa-pill--slate' ?>"><?= $required ? 'Obligatoire' : 'Facultatif' ?></span>
                                <?php if (in_array((string) $key, $missing, true)): ?><span class="pa-pill pa-pill--rose">À renseigner</span><?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <p class="pa-card__help">Le détail de chaque clé et les réglages Hostinger sont dans docs/boite-contact.md.</p>
                </section>
            <?php elseif ($state === 'no_tables'): ?>
                <section class="pa-card">
                    <h2 class="pa-card__title">Base de données à mettre à jour</h2>
                    <p class="pa-card__help">
                        La boîte est configurée, mais ses tables n’existent pas encore. Lancez les migrations
                        (étape « contact_mailbox ») depuis Pilotage du site, puis rechargez cette page.
                    </p>
                </section>
            <?php else: ?>
                <ul class="cm-tabs" aria-label="Dossiers">
                    <?php foreach ($folders as $key => $label): ?>
                        <li>
                            <a class="cm-tab" href="<?= $h($base . '?dossier=' . $key) ?>"<?= $folder === $key ? ' aria-current="page"' : '' ?>>
                                <?= $h($label) ?>
                                <?php if ((int) ($counts[$key] ?? 0) > 0): ?><span class="cm-tab__n"><?= (int) $counts[$key] ?></span><?php endif; ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <form class="cm-search" method="get" action="<?= $h($base) ?>" role="search">
                    <input type="hidden" name="dossier" value="<?= $h($folder) ?>">
                    <input type="search" name="q" value="<?= $h($query) ?>" maxlength="100" placeholder="Rechercher un expéditeur, un objet, un texte" aria-label="Rechercher dans la boîte">
                    <button type="submit" class="pa-btn pa-btn--line">Rechercher</button>
                </form>

                <p class="cm-status">
                    <?php if (($sync['last_error'] ?? '') !== ''): ?>
                        Dernière synchronisation en échec : <?= $h((string) $sync['last_error']) ?>
                    <?php elseif (!empty($sync['last_ok_at'])): ?>
                        Dernière synchronisation : <?= $h($fmtLong($sync['last_ok_at'])) ?>.
                    <?php else: ?>
                        Aucune synchronisation pour l’instant : utilisez « Actualiser » ou attendez la tâche automatique.
                    <?php endif; ?>
                </p>

                <?php if ($threads === []): ?>
                    <p class="pa-empty"><?= $query !== '' ? 'Aucun fil ne correspond à cette recherche.' : 'Aucun fil dans ce dossier.' ?></p>
                <?php else: ?>
                    <ul class="cm-list">
                        <?php foreach ($threads as $t): ?>
                            <?php
                            $tid = (int) $t['id'];
                            $unread = (int) $t['unread_count'] > 0;
                            $who = trim((string) $t['participant_name']) !== '' ? (string) $t['participant_name'] : (string) $t['participant_email'];
                            $starred = (int) $t['starred'] === 1;
                            ?>
                            <li class="cm-row<?= $unread ? ' cm-row--unread' : '' ?>">
                                <form class="cm-row__star" method="post" action="<?= $h($base . '/fil/' . $tid . '/action') ?>">
                                    <?= Csrf::field() ?>
                                    <input type="hidden" name="action" value="<?= $starred ? 'unstar' : 'star' ?>">
                                    <input type="hidden" name="dossier" value="<?= $h($folder) ?>">
                                    <button type="submit" class="cm-star" aria-pressed="<?= $starred ? 'true' : 'false' ?>" aria-label="<?= $starred ? 'Retirer l’étoile' : 'Mettre une étoile' ?>">
                                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3l2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1-4.4-4.3 6.1-.9z"></path></svg>
                                    </button>
                                </form>
                                <a class="cm-row__link" href="<?= $h($base . '/fil/' . $tid) ?>">
                                    <span class="cm-row__who" translate="no"><?= $h($who !== '' ? $who : '—') ?></span>
                                    <span class="cm-row__text">
                                        <span class="cm-row__subject" translate="no"><?= $h((string) $t['subject'] !== '' ? (string) $t['subject'] : '(sans objet)') ?></span>
                                        <span class="cm-row__snippet" translate="no"><?= $h((string) $t['last_snippet']) ?></span>
                                    </span>
                                    <span class="cm-row__meta">
                                        <span><?= $h($fmtShort($t['last_message_at'])) ?></span>
                                        <span>
                                            <?php if ((int) $t['message_count'] > 1): ?><span class="pa-pill pa-pill--slate"><?= (int) $t['message_count'] ?> messages</span><?php endif; ?>
                                            <?php if ((int) $t['todo'] === 1 && (string) $t['status'] === 'inbox'): ?><span class="pa-pill pa-pill--amber">À traiter</span><?php endif; ?>
                                            <?php if ((string) $t['source'] === 'form'): ?><span class="pa-pill pa-pill--blue">Formulaire</span><?php endif; ?>
                                        </span>
                                    </span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <?php $pages = max(1, (int) ceil($total / max(1, $perPage))); ?>
                    <?php if ($pages > 1): ?>
                        <nav class="cm-pager" aria-label="Pages">
                            <?php $qs = '&dossier=' . rawurlencode($folder) . ($query !== '' ? '&q=' . rawurlencode($query) : ''); ?>
                            <?php if ($page > 1): ?><a class="pa-btn pa-btn--line pa-btn--sm" href="<?= $h($base . '?page=' . ($page - 1) . $qs) ?>">Précédent</a><?php endif; ?>
                            <span>Page <?= $page ?> sur <?= $pages ?> (<?= $total ?> fils)</span>
                            <?php if ($page < $pages): ?><a class="pa-btn pa-btn--line pa-btn--sm" href="<?= $h($base . '?page=' . ($page + 1) . $qs) ?>">Suivant</a><?php endif; ?>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
