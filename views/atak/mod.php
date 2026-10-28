<?php
declare(strict_types=1);

/**
 * Page membre /atak/mod : installer le mod Athena depuis le Steam Workshop.
 * Le site n'héberge plus d'archive ni de modpack (App\Support\SteamWorkshop).
 *
 * @var list<array{key:string,id:string,name:string,role:string,required:bool,description:string}> $modItems
 * @var array<string, mixed> $modSet
 */
use App\Support\SteamWorkshop;

$e = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$items = is_array($modItems ?? null) ? $modItems : SteamWorkshop::items();
$set = is_array($modSet ?? null) ? $modSet : [];
$main = $items[0] ?? ['id' => SteamWorkshop::ATAK_NATIVE_ID, 'name' => 'COMSPEC ATAK Native'];
$version = (string) ($modVersion ?? '');
$versionName = (string) ($modVersionName ?? '');
$codename = $versionName !== '' && preg_match('/-\s*(\S.*)$/', $versionName, $m) === 1 ? trim($m[1]) : '';
$linkVersion = (string) ($modLinkVersion ?? '');
$notes = is_array($modNotes ?? null) ? $modNotes : ['title' => '', 'bullets' => []];
$minVersion = (string) ($modMinVersion ?? '');
$maintenance = !empty($modMaintenance);
$steamLinked = !empty($modSteamLinked);
$canManage = !empty($modCanManage);
$clean = static fn (string $s): string => trim(str_replace(['`', '**', '|'], '', $s));
$ico = static function (string $name): string {
    $p = [
        'steam' => '<circle cx="12" cy="12" r="9"/><circle cx="15.5" cy="9" r="2.6"/><circle cx="9" cy="15" r="2"/><path d="m10.7 14 3.3-3.4"/>',
        'launcher' => '<rect x="3" y="4" width="18" height="14" rx="1"/><path d="M3 8h18"/><path d="m10 11 4 2.5-4 2.5z"/>',
        'mods' => '<rect x="3.5" y="3.5" width="7" height="7"/><rect x="13.5" y="3.5" width="7" height="7"/><rect x="3.5" y="13.5" width="7" height="7"/><path d="m14.5 17 2 2 3.5-4"/>',
        'phone' => '<rect x="7" y="2.5" width="10" height="19" rx="1.5"/><path d="M10.5 18.5h3"/><path d="M9.5 7h5M9.5 10h5M9.5 13h3"/>',
        'link' => '<path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1"/><path d="M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1"/>',
        'download' => '<path d="M12 4v11M7 10l5 5 5-5"/><path d="M4 20h16"/>',
        'external' => '<path d="M14 4h6v6M20 4l-9 9"/><path d="M18 14v6H4V6h6"/>',
        'check' => '<path d="m5 12.5 4.5 4.5L19 7.5"/>',
        'warn' => '<path d="M12 3 2 20h20L12 3Z"/><path d="M12 10v4.5M12 17v.5"/>',
        'collection' => '<rect x="3" y="7" width="14" height="13"/><path d="M7 3h14v13"/>',
        'book' => '<path d="M4 4h7a3 3 0 0 1 3 3v13a2.5 2.5 0 0 0-2.5-2.5H4z"/><path d="M20 4h-4a2 2 0 0 0-2 2"/><path d="M20 4v13.5h-5"/>',
        'refresh' => '<path d="M20 11a8 8 0 0 0-14.5-4.5L4 8"/><path d="M4 4v4h4"/><path d="M4 13a8 8 0 0 0 14.5 4.5L20 16"/><path d="M20 20v-4h-4"/>',
    ];

    return '<svg class="amw-ico" viewBox="0 0 24 24" aria-hidden="true">' . ($p[$name] ?? $p['check']) . '</svg>';
};
$steps = [
    ['steam', 'S’abonner sur Steam', 'Ouvrez la fiche « ' . $main['name'] . ' » et cliquez sur « S’abonner ». Steam propose d’ajouter les dépendances (CBA_A3) : acceptez.'],
    ['launcher', 'Ouvrir le launcher d’Arma 3', 'Lancez Arma 3 depuis Steam : le launcher s’ouvre. Steam télécharge le mod en arrière-plan, attendez la fin du téléchargement.'],
    ['mods', 'Activer les mods', 'Onglet « Mods » : cochez ' . $main['name'] . ' et CBA_A3' . (!empty($set['has_collection']) ? ', puis les mods de la collection de votre communauté' : '') . '. Enregistrez le preset pour la prochaine fois.'],
    ['phone', 'Lancer et sortir le téléphone', 'Rejoignez le serveur de votre communauté. En mission, sortez le téléphone ATAK depuis votre équipement.'],
    ['link', 'Lier votre compte', 'Suivez « Première liaison » : un code à saisir dans le téléphone, aucune clé ni adresse à recopier.'],
];
$faq = [
    ['Le mod n’apparaît pas dans le launcher', 'Vérifiez dans Steam que le téléchargement est terminé (Bibliothèque › Arma 3 › Workshop). Fermez puis rouvrez le launcher. En dernier recours : propriétés d’Arma 3 › Fichiers installés › Vérifier l’intégrité.'],
    ['Message « Mod périmé » (MOD_OUTDATED) à la connexion', 'Votre communauté exige une version minimale' . ($minVersion !== '' ? ' (' . $minVersion . ')' : '') . '. Steam met le mod à jour seul : quittez Arma complètement, laissez Steam terminer la mise à jour, puis relancez.'],
    ['« Clé invalide » ou liaison refusée', 'La clé de la communauté a pu être régénérée. Refaites « Première liaison » depuis la Tacmap : le code transmet la nouvelle clé automatiquement.'],
    ['« Maintenance » à l’ouverture', 'Votre état-major a mis la carte et la liaison en maintenance. Réessayez plus tard ; le message affiché précise en général la durée.'],
    ['La mise à jour ne s’applique pas', 'Steam ne remplace un mod que lorsque le jeu est fermé. Quittez Arma (et le launcher), attendez la fin du téléchargement dans Steam, puis relancez.'],
    ['Faut-il encore télécharger un fichier sur le site ?', 'Non. Le mod et le modset de la communauté sont servis uniquement par le Steam Workshop : Steam les tient à jour tout seul.'],
];
?>
<link href="<?= $e(asset_url('assets/css/atak-mod-download.css')) ?>" rel="stylesheet">

<div class="amw">
    <header class="amw-hero">
        <div class="amw-hero__grid" aria-hidden="true"></div>
        <div class="amw-wrap amw-hero__inner">
            <div class="amw-hero__copy">
                <p class="amw-kicker">ATAK · Arma 3 · Steam Workshop</p>
                <h1 class="amw-title">Installer le mod Athena</h1>
                <p class="amw-lead">Un seul abonnement Steam : téléphone ATAK en jeu, tablette Athena et liaison avec la Tacmap de votre communauté. Steam le tient à jour tout seul, rien à télécharger sur le site.</p>
                <div class="amw-cta">
                    <a class="amw-btn amw-btn--primary" href="<?= $e(SteamWorkshop::clientUrl($main['id'])) ?>"><?= $ico('steam') ?> S’abonner sur Steam</a>
                    <a class="amw-btn" href="<?= $e(SteamWorkshop::webUrl($main['id'])) ?>" target="_blank" rel="noopener noreferrer"><?= $ico('external') ?> Ouvrir la fiche dans le navigateur</a>
                </div>
                <p class="amw-hint">Le bouton ouvre la fiche dans l’application Steam. Rien ne se passe ? Utilisez le lien navigateur, connectez-vous à Steam puis cliquez « S’abonner ».</p>
            </div>
            <aside class="amw-version" aria-label="Version publiée">
                <p class="amw-version__label">Dernière version</p>
                <p class="amw-version__value" translate="no"><?= $e($version !== '' ? $version : '—') ?><?php if ($codename !== ''): ?> <small><?= $e($codename) ?></small><?php endif; ?></p>
                <dl class="amw-version__meta">
                    <?php if ($linkVersion !== ''): ?><div><dt>COMSPEC Link</dt><dd translate="no"><?= $e($linkVersion) ?></dd></div><?php endif; ?>
                    <?php if ($minVersion !== ''): ?><div><dt>Minimum exigé par votre communauté</dt><dd translate="no"><?= $e($minVersion) ?></dd></div><?php endif; ?>
                    <div><dt>Votre compte Steam</dt><dd class="<?= $steamLinked ? 'is-ok' : 'is-warn' ?>"><?= $steamLinked ? 'Lié à Athena' : 'Pas encore lié' ?></dd></div>
                </dl>
            </aside>
        </div>
    </header>

    <div class="amw-wrap amw-body">
        <?php if ($maintenance): ?>
        <div class="amw-banner is-warn" role="status"><?= $ico('warn') ?><p><b>Maintenance en cours.</b> La Tacmap et la liaison en jeu sont temporairement fermées par votre état-major. Vous pouvez installer le mod dès maintenant.</p></div>
        <?php endif; ?>

        <section class="amw-section" aria-labelledby="amw-steps-h">
            <h2 class="amw-h2" id="amw-steps-h">Installation en 5 étapes</h2>
            <ol class="amw-steps">
                <?php foreach ($steps as $i => [$icon, $title, $text]): ?>
                <li class="amw-step">
                    <span class="amw-step__n"><?= $i + 1 ?></span>
                    <span class="amw-step__ico"><?= $ico($icon) ?></span>
                    <h3><?= $e($title) ?></h3>
                    <p><?= $e($text) ?></p>
                    <?php if ($i === 0): ?><a class="amw-link" href="<?= $e(SteamWorkshop::clientUrl($main['id'])) ?>">Ouvrir dans Steam →</a><?php endif; ?>
                    <?php if ($i === 4): ?><a class="amw-link" href="<?= $e(url('atak/premiere-liaison')) ?>">Première liaison →</a><?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ol>
        </section>

        <section class="amw-section" aria-labelledby="amw-items-h">
            <h2 class="amw-h2" id="amw-items-h">Ce qu’il faut installer</h2>
            <ul class="amw-items">
                <?php foreach ($items as $it): ?>
                <li class="amw-item<?= $it['key'] === 'atak' ? ' is-main' : '' ?>">
                    <div class="amw-item__head">
                        <h3 translate="no"><?= $e($it['name']) ?></h3>
                        <span class="amw-tag<?= $it['required'] ? ' is-req' : '' ?>"><?= $e($it['required'] ? 'Obligatoire' : $it['role']) ?></span>
                    </div>
                    <p><?= $e($it['description']) ?></p>
                    <div class="amw-item__actions">
                        <a class="amw-btn amw-btn--sm<?= $it['key'] === 'atak' ? ' amw-btn--primary' : '' ?>" href="<?= $e(SteamWorkshop::clientUrl($it['id'])) ?>"><?= $ico('steam') ?> S’abonner</a>
                        <a class="amw-btn amw-btn--sm amw-btn--ghost" href="<?= $e(SteamWorkshop::webUrl($it['id'])) ?>" target="_blank" rel="noopener noreferrer">Fiche Steam</a>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
        </section>

        <section class="amw-section amw-two" aria-label="Votre communauté">
            <div class="amw-card<?= !empty($set['has_collection']) ? ' is-main' : '' ?>">
                <p class="amw-card__k"><?= $ico('collection') ?> Mods de votre communauté</p>
                <?php if (!empty($set['has_collection'])): ?>
                    <h3 translate="no"><?= $e($set['title']) ?></h3>
                    <p>La collection Steam rassemble tout le modset de vos sessions (cartes, véhicules, équipements). Un clic « S’abonner à tout » suffit.</p>
                    <div class="amw-item__actions">
                        <a class="amw-btn amw-btn--primary amw-btn--sm" href="<?= $e($set['collection_client']) ?>"><?= $ico('steam') ?> S’abonner à la collection</a>
                        <a class="amw-btn amw-btn--sm amw-btn--ghost" href="<?= $e($set['collection_web']) ?>" target="_blank" rel="noopener noreferrer">Voir la collection</a>
                    </div>
                <?php else: ?>
                    <h3>Pas de collection publiée</h3>
                    <p>Votre communauté n’a pas encore indiqué sa collection Steam : demandez la liste des mods à votre état-major.</p>
                    <?php if ($canManage): ?><a class="amw-link" href="<?= $e(url('back-office/ressources/atak-mod')) ?>">Renseigner la collection de la communauté →</a><?php endif; ?>
                <?php endif; ?>
                <?php if (trim((string) ($set['notes'] ?? '')) !== ''): ?>
                    <div class="amw-notes"><p class="amw-card__k">Consignes de l’état-major</p><p><?= nl2br($e($set['notes'])) ?></p></div>
                <?php endif; ?>
            </div>
            <div class="amw-card">
                <p class="amw-card__k"><?= $ico('refresh') ?> Nouveautés<?= $version !== '' ? ' de la ' . $e($version) : '' ?></p>
                <?php if (($notes['title'] ?? '') !== '' || ($notes['bullets'] ?? []) !== []): ?>
                    <?php if (($notes['title'] ?? '') !== ''): ?><h3><?= $e($clean((string) $notes['title'])) ?></h3><?php endif; ?>
                    <ul class="amw-bullets">
                        <?php foreach (array_slice((array) $notes['bullets'], 0, 6) as $b): ?><li><?= $e($clean((string) $b)) ?></li><?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p>Le détail des changements est publié sur la fiche Steam, onglet « Notes de mise à jour ».</p>
                <?php endif; ?>
                <p class="amw-small">Le mod compare sa version au démarrage et vous prévient s’il est périmé.</p>
            </div>
        </section>

        <section class="amw-section" aria-labelledby="amw-faq-h">
            <h2 class="amw-h2" id="amw-faq-h">Dépannage</h2>
            <div class="amw-faq">
                <?php foreach ($faq as [$q, $a]): ?>
                <details class="amw-faq__item"><summary><?= $e($q) ?></summary><p><?= $e($a) ?></p></details>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="amw-section" aria-labelledby="amw-more-h">
            <h2 class="amw-h2" id="amw-more-h">Aller plus loin</h2>
            <div class="amw-more">
                <a class="amw-more__a" href="<?= $e(url('atak/premiere-liaison')) ?>"><?= $ico('link') ?><b>Première liaison</b><small>Relier le téléphone à votre compte</small></a>
                <a class="amw-more__a" href="<?= $e(url('atak/tuto')) ?>"><?= $ico('phone') ?><b>Guide de connexion</b><small>Pas à pas, captures à l’appui</small></a>
                <a class="amw-more__a" href="<?= $e(url('atak/mod/guide')) ?>"><?= $ico('book') ?><b>Guide complet du mod</b><small>Toutes les applications du téléphone</small></a>
                <a class="amw-more__a" href="<?= $e(url('atak/mod/formation')) ?>"><?= $ico('mods') ?><b>Formation opérateur</b><small>7 modules pour prendre le mod en main</small></a>
            </div>
        </section>
    </div>
</div>
