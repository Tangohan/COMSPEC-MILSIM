<?php
declare(strict_types=1);

/**
 * Page membre /atak/mod : installer le mod Athena. Le pack se télécharge sur la page publique /modpacks
 * (views/site/public_modpack.php) ; seules les dépendances (CBA_A3, ACE3, ACRE2) restent sur le Steam Workshop.
 * Aucune version n'est écrite ici : tout vient d'AtakReleaseInfo, du pack publié et du manifeste du modpack.
 *
 * @var list<array{key:string,id:string,name:string,role:string,required:bool,description:string}> $modItems
 * @var array<string, mixed> $modSet
 * @var array<string, mixed> $modPack Pack publié sur /modpacks, [] s'il est indisponible.
 * @var list<array<string, mixed>> $modPackItems Mods du manifeste config/modpack_items.json.
 */
use App\Services\Modpack\ModpackVersion;
use App\Support\SteamWorkshop;

$e = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$items = is_array($modItems ?? null) ? $modItems : SteamWorkshop::items();
$deps = array_values(array_filter($items, static fn (array $it): bool => ($it['key'] ?? '') !== 'atak'));
$set = is_array($modSet ?? null) ? $modSet : [];
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

// Pack publié (même source que /modpacks) : version, taille, date, empreinte.
$pack = is_array($modPack ?? null) ? $modPack : [];
$packItems = is_array($modPackItems ?? null) ? $modPackItems : [];
$english = function_exists('locale') && locale() === 'en';
$packPublished = !empty($pack['archive_key']) && !empty($pack['published']) && trim((string) ($pack['version'] ?? '')) !== '';
$packVersion = $packPublished ? (string) $pack['version'] : '';
$packBytes = $packPublished ? (int) ($pack['bytes'] ?? 0) : 0;
$num = static fn (float $v, int $d): string => number_format($v, $d, $english ? '.' : ',', $english ? ',' : ' ');
$packSize = $packBytes >= 1073741824 ? $num($packBytes / 1073741824, 2) . ' ' . __('modpack.gib')
    : ($packBytes >= 1048576 ? $num($packBytes / 1048576, 1) . ' ' . __('modpack.mib') : ($packBytes > 0 ? $num($packBytes, 0) . ' ' . __('modpack.bytes') : ''));
$packDate = '';
if ($packPublished && !empty($pack['published_at'])) {
    try {
        $packDate = (new DateTimeImmutable((string) $pack['published_at'], new DateTimeZone('UTC')))
            ->setTimezone(new DateTimeZone('Europe/Paris'))->format($english ? 'j M Y' : 'd/m/Y');
    } catch (Throwable) {
    }
}
$packSha = $packPublished && preg_match('/\A[a-f0-9]{64}\z/', (string) ($pack['sha256'] ?? '')) === 1 ? (string) $pack['sha256'] : '';
$packShaShort = $packSha !== '' ? substr($packSha, 0, 8) . '…' . substr($packSha, -8) : '';
$packBehind = $packPublished && $version !== '' && ModpackVersion::isOlder($packVersion, $version);
$urlPack = url('modpacks');
$urlInstall = $urlPack . '#installation';
$urlTrust = $urlPack . '#securite';

$ico = static function (string $name): string {
    $p = [
        'steam' => '<circle cx="12" cy="12" r="9"/><circle cx="15.5" cy="9" r="2.6"/><circle cx="9" cy="15" r="2"/><path d="m10.7 14 3.3-3.4"/>',
        'launcher' => '<rect x="3" y="4" width="18" height="14" rx="1"/><path d="M3 8h18"/><path d="m10 11 4 2.5-4 2.5z"/>',
        'mods' => '<rect x="3.5" y="3.5" width="7" height="7"/><rect x="13.5" y="3.5" width="7" height="7"/><rect x="3.5" y="13.5" width="7" height="7"/><path d="m14.5 17 2 2 3.5-4"/>',
        'phone' => '<rect x="7" y="2.5" width="10" height="19" rx="1.5"/><path d="M10.5 18.5h3"/><path d="M9.5 7h5M9.5 10h5M9.5 13h3"/>',
        'link' => '<path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1"/><path d="M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1"/>',
        'download' => '<path d="M12 4v11M7 10l5 5 5-5"/><path d="M4 20h16"/>',
        'folder' => '<path d="M3 6.5h6l2 2.5h10v10H3z"/>',
        'external' => '<path d="M14 4h6v6M20 4l-9 9"/><path d="M18 14v6H4V6h6"/>',
        'check' => '<path d="m5 12.5 4.5 4.5L19 7.5"/>',
        'warn' => '<path d="M12 3 2 20h20L12 3Z"/><path d="M12 10v4.5M12 17v.5"/>',
        'collection' => '<rect x="3" y="7" width="14" height="13"/><path d="M7 3h14v13"/>',
        'book' => '<path d="M4 4h7a3 3 0 0 1 3 3v13a2.5 2.5 0 0 0-2.5-2.5H4z"/><path d="M20 4h-4a2 2 0 0 0-2 2"/><path d="M20 4v13.5h-5"/>',
        'refresh' => '<path d="M20 11a8 8 0 0 0-14.5-4.5L4 8"/><path d="M4 4v4h4"/><path d="M4 13a8 8 0 0 0 14.5 4.5L20 16"/><path d="M20 20v-4h-4"/>',
    ];

    return '<svg class="amw-ico" viewBox="0 0 24 24" aria-hidden="true">' . ($p[$name] ?? $p['check']) . '</svg>';
};
// [icône, titre, texte, lien : null | 'pack' | 'install' | 'steam' | 'link']
$steps = [
    ['download', 'Télécharger le pack', 'Récupérez l’archive du pack COMSPEC sur la page des modpacks. Vérifiez l’empreinte SHA-256 si vous le souhaitez.', 'pack'],
    ['folder', 'Extraire l’archive', 'Décompressez le .RAR avec 7-Zip ou WinRAR dans un dossier dédié.', null],
    ['launcher', 'Ajouter les mods locaux', 'Dans le launcher d’Arma 3, ouvrez Mods, puis Mod local, et sélectionnez chaque dossier de mod extrait.', 'install'],
    ['mods', 'Activer le pack et les dépendances', 'Cochez les mods ajoutés ainsi que CBA_A3, à abonner séparément sur le Steam Workshop. Enregistrez le preset pour la prochaine fois.', 'steam'],
    ['link', 'Lier votre compte', 'Rejoignez le serveur de votre communauté, sortez le téléphone ATAK, puis suivez « Première liaison » : un code à saisir dans le téléphone, aucune clé ni adresse à recopier.', 'link'],
];
// [question, réponse, lien optionnel [libellé, adresse]]
$faq = [
    ['Le mod n’apparaît pas dans le launcher', 'Le pack s’ajoute à la main : dans Mods, utilisez « Mod local » et sélectionnez chaque dossier extrait. Un abonnement Steam ne suffit pas pour le pack COMSPEC. Pensez aussi à cocher CBA_A3.', null],
    ['Message « Mod périmé » (MOD_OUTDATED) à la connexion', 'Votre communauté exige une version minimale du mod, indiquée en haut de cette page. Steam ne met pas à jour un pack ajouté à la main : retéléchargez la dernière version sur la page des modpacks, remplacez l’ancien dossier, puis relancez Arma.', [$urlPack, 'Ouvrir la page des modpacks']],
    ['Mon antivirus signale le pack', 'Le pack contient des extensions (DLL) non signées, chargées par Arma 3 pour la liaison avec Athena : un antivirus peut les signaler à tort. Comparez l’empreinte SHA-256 du fichier avec celle publiée, et lisez l’explication complète sur la page des modpacks.', [$urlTrust, 'Voir « Sécurité et vérification »']],
    ['« Clé invalide » ou liaison refusée', 'La clé de la communauté a pu être régénérée. Refaites « Première liaison » depuis la Tacmap : le code transmet la nouvelle clé automatiquement.', null],
    ['« Maintenance » à l’ouverture', 'Votre état-major a mis la carte et la liaison en maintenance. Réessayez plus tard ; le message affiché précise en général la durée.', null],
    ['La mise à jour ne s’applique pas', 'Steam ne met pas à jour les mods ajoutés à la main : revenez sur la page des modpacks à chaque nouvelle version, remplacez le dossier du mod, et relancez Arma (launcher fermé pendant le remplacement).', null],
    ['Le mod est-il toujours sur le Steam Workshop ?', 'Non : le pack COMSPEC a été retiré du Steam Workshop et se télécharge directement depuis le site. Seules les dépendances (CBA_A3, ACE3, ACRE2) restent à abonner sur le Workshop.', null],
];
$kindLabel = static fn (array $it): string => !empty($it['in_pack']) ? 'Dans l’archive principale' : 'Mod à l’unité';
?>
<link href="<?= $e(asset_url('assets/css/public-modpack.css')) ?>" rel="stylesheet">
<link href="<?= $e(asset_url('assets/css/atak-mod-download.css')) ?>" rel="stylesheet">

<div class="amw">
    <header class="amw-hero">
        <div class="amw-hero__grid" aria-hidden="true"></div>
        <div class="amw-wrap amw-hero__inner">
            <div class="amw-hero__copy">
                <p class="amw-context">ATAK · Arma 3 · Téléchargement direct</p>
                <h1 class="amw-title">Installer le mod Athena</h1>
                <p class="amw-lead">Un seul pack à télécharger : téléphone ATAK en jeu, tablette Athena et liaison avec la Tacmap de votre communauté. Le pack se récupère sur la page des modpacks ; Steam ne le met plus à jour, revenez y à chaque nouvelle version.</p>
                <div class="amw-cta">
                    <a class="amw-btn amw-btn--primary" href="#amw-pack-h"><?= $ico('download') ?> Télécharger le pack</a>
                    <a class="amw-btn" href="#amw-steps-h"><?= $ico('book') ?> Voir l’installation</a>
                </div>
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

        <section class="mp amw-pack" id="telecharger-pack" aria-labelledby="amw-pack-h">
            <div class="mp-box">
                <div class="amw-pack__head">
                    <div>
                        <h2 class="amw-h2" id="amw-pack-h">Télécharger le pack</h2>
                        <?php if ($packPublished): ?>
                        <ul class="mp-meta" aria-label="<?= $e(__('modpack.file_details')) ?>">
                            <li class="mp-badge-status<?= $packBehind ? ' mp-warning' : '' ?>"><span aria-hidden="true"></span><?= $e(__('modpack.version')) ?> <strong class="mp-mono" translate="no">v<?= $e($packVersion) ?></strong></li>
                            <?php if ($packSize !== ''): ?><li><?= $e($packSize) ?> · RAR</li><?php endif; ?>
                            <?php if ($packDate !== ''): ?><li><?= $e($packDate) ?></li><?php endif; ?>
                        </ul>
                        <?php else: ?>
                        <p class="amw-pack__soon"><?= $e(__('modpack.coming')) ?></p>
                        <?php endif; ?>
                    </div>
                    <a class="mp-primary" href="<?= $e($urlPack) ?>"><?= $e(__('modpack.download')) ?> <span class="mp-arrow mp-arrow-down" aria-hidden="true"></span></a>
                </div>
                <?php if ($packBehind): ?>
                <p class="mp-note mp-warning" role="status">Le pack publié est plus ancien que la dernière version de l’ATAK natif (<span translate="no"><?= $e($version) ?></span>). La page des modpacks indique toujours la version réellement proposée.</p>
                <?php endif; ?>
                <?php if ($packShaShort !== ''): ?>
                <p class="mp-hash" title="SHA-256"><span>SHA-256</span> <code translate="no"><?= $e($packShaShort) ?></code></p>
                <?php endif; ?>
                <div class="mp-box-links">
                    <a class="mp-ghost" href="<?= $e($urlInstall) ?>"><?= $e(__('modpack.guide')) ?></a>
                    <a class="mp-ghost" href="<?= $e($urlTrust) ?>"><?= $e(__('modpack.flag_q')) ?></a>
                </div>
            </div>
        </section>

        <section class="amw-section" aria-labelledby="amw-steps-h">
            <h2 class="amw-h2" id="amw-steps-h">Installation en 5 étapes</h2>
            <ol class="amw-steps">
                <?php foreach ($steps as $i => [$icon, $title, $text, $kind]): ?>
                <li class="amw-step">
                    <span class="amw-step__n"><?= $i + 1 ?></span>
                    <span class="amw-step__ico"><?= $ico($icon) ?></span>
                    <h3><?= $e($title) ?></h3>
                    <p><?= $e($text) ?></p>
                    <?php if ($kind === 'pack'): ?><a class="amw-link" href="<?= $e($urlPack) ?>">Page des modpacks →</a><?php endif; ?>
                    <?php if ($kind === 'install'): ?><a class="amw-link" href="<?= $e($urlInstall) ?>">Guide détaillé →</a><?php endif; ?>
                    <?php if ($kind === 'steam' && $deps !== []): ?><a class="amw-link" href="<?= $e(SteamWorkshop::webUrl($deps[0]['id'])) ?>" target="_blank" rel="noopener noreferrer">Ouvrir CBA_A3 sur Steam →</a><?php endif; ?>
                    <?php if ($kind === 'link'): ?><a class="amw-link" href="<?= $e(url('atak/premiere-liaison')) ?>">Première liaison →</a><?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ol>
        </section>

        <?php if ($packItems !== []): ?>
        <section class="amw-section" aria-labelledby="amw-pack-items-h">
            <h2 class="amw-h2" id="amw-pack-items-h">Contenu du pack</h2>
            <ul class="amw-items">
                <?php foreach ($packItems as $it): ?>
                <?php $id = (string) ($it['id'] ?? ''); ?>
                <li class="amw-item<?= !empty($it['in_pack']) ? ' is-main' : '' ?>">
                    <div class="amw-item__head">
                        <h3 translate="no"><?= $e($it['name'] ?? $id) ?></h3>
                        <span class="amw-tag<?= !empty($it['in_pack']) ? ' is-req' : '' ?>"><?= $e($kindLabel($it)) ?></span>
                    </div>
                    <p><?= $e(__('modpack.item_' . $id . '_short')) ?></p>
                    <?php if (!empty($it['version'])): ?><p class="amw-small" translate="no">v<?= $e($it['version']) ?></p><?php endif; ?>
                    <div class="amw-item__actions">
                        <a class="amw-btn amw-btn--sm<?= !empty($it['in_pack']) ? ' amw-btn--primary' : ' amw-btn--ghost' ?>" href="<?= $e($urlPack . '#mod-' . rawurlencode($id)) ?>"><?= $ico('download') ?> Voir sur les modpacks</a>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
            <p class="amw-small amw-small--spaced">Overwatch (connect et main) est embarqué dans le mod ATAK natif : il n’y a rien d’autre à installer pour la liaison avec la Tacmap.</p>
        </section>
        <?php endif; ?>

        <?php if ($deps !== []): ?>
        <section class="amw-section" aria-labelledby="amw-items-h">
            <h2 class="amw-h2" id="amw-items-h">Dépendances à abonner sur Steam</h2>
            <ul class="amw-items">
                <?php foreach ($deps as $it): ?>
                <li class="amw-item">
                    <div class="amw-item__head">
                        <h3 translate="no"><?= $e($it['name']) ?></h3>
                        <span class="amw-tag<?= $it['required'] ? ' is-req' : '' ?>"><?= $e($it['required'] ? 'Obligatoire' : $it['role']) ?></span>
                    </div>
                    <p><?= $e($it['description']) ?></p>
                    <div class="amw-item__actions">
                        <a class="amw-btn amw-btn--sm" href="<?= $e(SteamWorkshop::clientUrl($it['id'])) ?>"><?= $ico('steam') ?> S’abonner</a>
                        <a class="amw-btn amw-btn--sm amw-btn--ghost" href="<?= $e(SteamWorkshop::webUrl($it['id'])) ?>" target="_blank" rel="noopener noreferrer">Fiche Steam</a>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
        </section>
        <?php endif; ?>

        <section class="amw-section amw-two" aria-label="Votre communauté">
            <div class="amw-card<?= !empty($set['has_collection']) ? ' is-main' : '' ?>">
                <p class="amw-card__k"><?= $ico('collection') ?> Mods de votre communauté</p>
                <?php if (!empty($set['has_collection'])): ?>
                    <h3 translate="no"><?= $e($set['title']) ?></h3>
                    <p>La collection Steam rassemble les autres mods du modset de vos sessions (cartes, véhicules, équipements). Un clic « S’abonner à tout » suffit ; le pack COMSPEC, lui, se télécharge ci-dessus.</p>
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
                    <p>Le détail des changements est publié avec chaque version du pack, dans les notes de version de la page des modpacks.</p>
                <?php endif; ?>
                <p class="amw-small">Le mod compare sa version au démarrage et vous prévient s’il est périmé.</p>
            </div>
        </section>

        <section class="amw-section" aria-labelledby="amw-faq-h">
            <h2 class="amw-h2" id="amw-faq-h">Dépannage</h2>
            <div class="amw-faq">
                <?php foreach ($faq as [$q, $a, $lnk]): ?>
                <details class="amw-faq__item"><summary><?= $e($q) ?></summary><p><?= $e($a) ?></p><?php if ($lnk !== null): ?><p><a class="amw-link" href="<?= $e($lnk[0]) ?>"><?= $e($lnk[1]) ?> →</a></p><?php endif; ?></details>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="amw-section" aria-labelledby="amw-more-h">
            <h2 class="amw-h2" id="amw-more-h">Aller plus loin</h2>
            <div class="amw-more">
                <a class="amw-more__a" href="<?= $e($urlPack) ?>"><?= $ico('mods') ?><b>Page des modpacks</b><small>Pack, mods à l’unité, empreintes et vérification</small></a>
                <a class="amw-more__a" href="<?= $e(url('atak/premiere-liaison')) ?>"><?= $ico('link') ?><b>Première liaison</b><small>Relier le téléphone à votre compte</small></a>
                <a class="amw-more__a" href="<?= $e(url('atak/tuto')) ?>"><?= $ico('phone') ?><b>Guide de connexion</b><small>Pas à pas, captures à l’appui</small></a>
                <a class="amw-more__a" href="<?= $e(url('wiki/atak')) ?>"><?= $ico('book') ?><b>Wiki ATAK</b><small>Une page par app, réglages serveur et nouveautés</small></a>
                <a class="amw-more__a" href="<?= $e(url('atak/mod/guide')) ?>"><?= $ico('book') ?><b>Guide complet du mod</b><small>Toutes les applications du téléphone</small></a>
                <a class="amw-more__a" href="<?= $e(url('atak/mod/formation')) ?>"><?= $ico('mods') ?><b>Formation opérateur</b><small>7 modules pour prendre le mod en main</small></a>
            </div>
        </section>
    </div>
</div>
