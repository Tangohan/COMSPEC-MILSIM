<?php
declare(strict_types=1);
/**
 * Réglages de la notice > onglet « Services » : catalogue de services demandeurs livré (fictif) et services créés par la communauté.
 * Création, modification, duplication d'un service du catalogue, activation, ordre, service par défaut, import et export JSON.
 * Lecture pour qui ouvre les réglages ; écriture réservée au niveau « Gérer » de la section Fichiers et sujets.
 *
 * @var array<string,mixed> $config
 * @var array<string,array<string,mixed>> $serviceList
 * @var array<string,array{fr:string,en:string}> $serviceFamilies
 * @var array<string,string> $servicePalette
 * @var list<string> $serviceEmblems
 * @var array<string,int> $serviceUse
 * @var bool $serviceStoreReady
 * @var array<string,mixed> $serviceState
 */
use App\Core\Csrf;
use App\Support\SseUsClassification;
use App\Support\SseWantedCatalog as W;
use App\Support\SseWantedServices;

$h = $h ?? static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$lang = (string) ($lang ?? 'fr') === 'en' ? 'en' : 'fr';
$serviceList = is_array($serviceList ?? null) ? $serviceList : [];
$serviceFamilies = is_array($serviceFamilies ?? null) ? $serviceFamilies : [];
$servicePalette = is_array($servicePalette ?? null) ? $servicePalette : [];
$serviceUse = is_array($serviceUse ?? null) ? $serviceUse : [];
$serviceState = is_array($serviceState ?? null) ? $serviceState : [];
$canAdmin = isset($rights) && is_object($rights) && $rights->canAdmin();
$defaultCode = SseWantedServices::defaultCode($config);
$typeChoices = W::types($config);
$svcAction = url('atak/sse/avis/reglages/services');
$csrf = Csrf::field();
$stateErrors = is_array($serviceState['errors'] ?? null) ? $serviceState['errors'] : [];
$report = is_array($serviceState['report'] ?? null) ? $serviceState['report'] : null;
$opened = $serviceState !== [] && ($stateErrors !== [] || $report !== null) ? (($serviceState['op'] ?? '') === 'import' ? 'import' : 'service') : '';
$active = count(array_filter($serviceList, static fn (array $s): bool => $s['enabled'] === true));
$roleNames = [];
foreach (SseWantedServices::ROLES as $rk => $rl) {
    $roleNames[$rk] = $rl[$lang === 'en' ? 1 : 0];
}
$oneService = static function (array $s) use ($lang, $roleNames, $servicePalette): array {
    return [
        'code' => $s['code'], 'source' => $s['source'], 'family' => $s['family'], 'fr' => $s['fr'], 'en' => $s['en'], 'ac' => $s['ac'], 'roles' => $s['roles'],
        'auth_fr' => $s['auth_fr'], 'auth_en' => $s['auth_en'], 'radio' => $s['radio'], 'tel' => $s['tel'], 'mail' => $s['mail'], 'max' => $s['max'],
        'class' => $s['class'], 'thr' => $s['thr'], 'exp' => $s['exp'], 'threats' => $s['threats'], 'types' => $s['types'], 'color' => $s['color'],
        'emblem' => $s['emblem'], 'lang' => $s['lang'], 'ins_fr' => $s['ins_fr'], 'ins_en' => $s['ins_en'], 'enabled' => $s['enabled'],
    ];
};
?>
<div class="wc-settings-pane ws-admin" id="wc-pane-services" data-wc-pane="services" role="tabpanel" aria-labelledby="wc-stab-services" data-ws-admin>
    <div>
        <h2 class="wc-set-title">Services demandeurs</h2>
        <p class="ws-admin__desc">Les services qui peuvent demander un avis : le catalogue livré, tout fictif, et ceux que votre communauté crée. Choisir un service dans le formulaire remplit ses coordonnées et ses valeurs par défaut.</p>
    </div>
    <?php if (!$canAdmin): ?>
        <div class="dos-note dos-note--info">Vous pouvez consulter les services. Les créer, les modifier ou les masquer demande le niveau « Gérer » de la section Fichiers et sujets.</div>
    <?php endif; ?>
    <?php if (!$serviceStoreReady): ?>
        <div class="dos-note dos-note--info">Les services créés par votre communauté fonctionnent déjà. L’étape de base de données « processus des avis » n’est pas encore passée : le code du service n’est pas gardé avec l’avis, et un service créé est désactivé plutôt que supprimé.</div>
    <?php endif; ?>
    <?php if (!empty($serviceState['saved'])): ?>
        <div class="dos-note dos-note--info"><?= $h($serviceState['saved']) ?></div>
    <?php endif; ?>

    <div class="ws-bar" role="search">
        <div class="ws-bar__grow"><label class="sr-only" for="ws-q">Rechercher un service</label><input id="ws-q" type="search" placeholder="Rechercher un service, un sigle ou un code" autocomplete="off" data-ws-q></div>
        <div><label class="sr-only" for="ws-fam">Famille</label>
            <select id="ws-fam" data-ws-fam><option value="">Toutes les familles</option>
                <?php foreach ($serviceFamilies as $k => $f): ?><option value="<?= $h($k) ?>"><?= $h($f[$lang]) ?></option><?php endforeach; ?>
            </select></div>
        <div><label class="sr-only" for="ws-src">Origine</label>
            <select id="ws-src" data-ws-src><option value="">Toutes les origines</option><option value="catalogue">Catalogue livré</option><option value="custom">Créés par la communauté</option><option value="off">Masqués</option></select></div>
    </div>
    <div class="ws-bar">
        <span class="ws-count" data-ws-count aria-live="polite" data-total="<?= count($serviceList) ?>"><?= count($serviceList) ?> service(s), <?= $active ?> proposé(s)</span>
        <span class="ws-bar__grow"></span>
        <?php if ($canAdmin): ?>
            <a class="sse-btn ws-btn" href="<?= $h(url('atak/sse/avis/reglages/services/export')) ?>">Exporter</a>
            <button class="sse-btn ws-btn" type="button" data-sse-open="#ws-drawer-import">Importer</button>
            <button class="sse-btn sse-btn--primary ws-btn" type="button" data-ws-new data-sse-open="#ws-drawer-service">Nouveau service</button>
        <?php endif; ?>
    </div>

    <div class="ws-table-wrap">
        <table class="ws-table" data-ws-table>
            <caption class="sr-only">Services demandeurs</caption>
            <thead><tr><th scope="col">Service</th><th scope="col" class="ws-hide-md">Famille</th><th scope="col" class="ws-hide-md ws-hide-lg">Rôle</th><th scope="col">Classification max.</th><th scope="col">Avis</th><th scope="col"><span class="sr-only">Actions</span></th></tr></thead>
            <tbody>
            <?php foreach ($serviceList as $code => $s):
                $isCustom = $s['source'] === 'custom';
                $search = mb_strtolower($s['fr'] . ' ' . $s['en'] . ' ' . $s['ac'] . ' ' . $code);
                $used = (int) ($serviceUse[$code] ?? 0);
                ?>
                <tr class="<?= $s['enabled'] ? '' : 'is-off' ?>" data-ws-row data-family="<?= $h($s['family']) ?>" data-source="<?= $h($s['source']) ?>" data-enabled="<?= $s['enabled'] ? '1' : '0' ?>" data-search="<?= $h($search) ?>" data-ws-json="<?= $h(json_encode($oneService($s), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)) ?>">
                    <td class="ws-cell-name">
                        <div class="ws-name">
                            <span class="ws-name__dot" style="background:<?= $h($servicePalette[$s['color']] ?? '#1F3A5F') ?>"></span>
                            <span class="ws-name__text"><strong translate="no"><?= $h($lang === 'en' ? $s['en'] : $s['fr']) ?></strong><span translate="no"><?= $h(($s['ac'] !== '' ? $s['ac'] . ' · ' : '') . $code) ?></span></span>
                        </div>
                        <div class="ws-chips">
                            <?php if ($isCustom): ?><span class="ws-badge ws-badge--custom">Communauté</span><?php endif; ?>
                            <?php if ($code === $defaultCode): ?><span class="ws-badge ws-badge--default">Par défaut</span><?php endif; ?>
                            <?php if (!$s['enabled']): ?><span class="ws-badge ws-badge--off"><?= $isCustom ? 'Désactivé' : 'Masqué' ?></span><?php endif; ?>
                        </div>
                    </td>
                    <td class="ws-hide-md" data-label="Famille"><?= $h($serviceFamilies[$s['family']][$lang] ?? $s['family']) ?></td>
                    <td class="ws-hide-md ws-hide-lg" data-label="Rôle"><?= $h(implode(', ', array_map(static fn (string $r): string => $roleNames[$r] ?? $r, $s['roles']))) ?></td>
                    <td data-label="Classification max."><span class="ws-badge" translate="no"><?= $h(SseUsClassification::abbr((string) $s['max'])) ?></span></td>
                    <td data-label="Avis"><?= $used > 0 ? (int) $used : '—' ?></td>
                    <td class="ws-cell-actions">
                        <?php if ($canAdmin): ?>
                            <div class="ws-actions">
                                <?php if ($isCustom): ?>
                                    <button class="sse-btn ws-btn" type="button" data-ws-edit data-sse-open="#ws-drawer-service">Modifier</button>
                                <?php else: ?>
                                    <form method="post" action="<?= $h($svcAction) ?>"><?= $csrf ?><input type="hidden" name="op" value="duplicate"><input type="hidden" name="code" value="<?= $h($code) ?>"><button class="sse-btn ws-btn" type="submit" aria-label="Dupliquer <?= $h($s['fr']) ?> pour le personnaliser">Dupliquer</button></form>
                                <?php endif; ?>
                                <form method="post" action="<?= $h($svcAction) ?>"><?= $csrf ?><input type="hidden" name="op" value="toggle"><input type="hidden" name="code" value="<?= $h($code) ?>"><input type="hidden" name="enabled" value="<?= $s['enabled'] ? '0' : '1' ?>"><button class="sse-btn ws-btn" type="submit"><?= $s['enabled'] ? ($isCustom ? 'Désactiver' : 'Masquer') : 'Proposer' ?></button></form>
                                <details class="ws-more">
                                    <summary class="sse-btn ws-btn" aria-label="Plus d’actions pour <?= $h($s['fr']) ?>">Plus</summary>
                                    <div class="ws-more__menu">
                                        <?php if ($isCustom): ?>
                                            <form method="post" action="<?= $h($svcAction) ?>"><?= $csrf ?><input type="hidden" name="op" value="duplicate"><input type="hidden" name="code" value="<?= $h($code) ?>"><button class="ws-menuitem" type="submit">Dupliquer</button></form>
                                        <?php endif; ?>
                                        <?php if ($s['enabled'] && $code !== $defaultCode): ?>
                                            <form method="post" action="<?= $h($svcAction) ?>"><?= $csrf ?><input type="hidden" name="op" value="default"><input type="hidden" name="code" value="<?= $h($code) ?>"><button class="ws-menuitem" type="submit">Définir par défaut</button></form>
                                        <?php endif; ?>
                                        <form method="post" action="<?= $h($svcAction) ?>"><?= $csrf ?><input type="hidden" name="op" value="move"><input type="hidden" name="code" value="<?= $h($code) ?>"><input type="hidden" name="direction" value="up"><button class="ws-menuitem" type="submit">Monter dans la liste</button></form>
                                        <form method="post" action="<?= $h($svcAction) ?>"><?= $csrf ?><input type="hidden" name="op" value="move"><input type="hidden" name="code" value="<?= $h($code) ?>"><input type="hidden" name="direction" value="down"><button class="ws-menuitem" type="submit">Descendre dans la liste</button></form>
                                    </div>
                                </details>
                            </div>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <p class="ws-empty" data-ws-none hidden>Aucun service ne correspond à ces filtres.</p>
    </div>
</div>

<?php if ($canAdmin): ?>
    <dialog class="sse-drawer sse-drawer--wide ws-drawer" id="ws-drawer-service" aria-labelledby="ws-drawer-title" data-title-create="Nouveau service" data-title-update="Modifier le service" data-ws-drawer data-ws-old="<?= $h(json_encode(['op' => (string) ($serviceState['op'] ?? ''), 'code' => (string) ($serviceState['code'] ?? ''), 'old' => $serviceState['old'] ?? null], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)) ?>" <?= $opened === 'service' ? 'data-sse-autoopen' : '' ?>>
        <form method="post" action="<?= $h($svcAction) ?>" data-ws-form>
            <?= $csrf ?>
            <input type="hidden" name="op" value="create" data-ws-op>
            <input type="hidden" name="code" value="" data-ws-code>
            <div class="sse-dialog__head">
                <div>
                    <h2 id="ws-drawer-title" data-ws-title>Nouveau service</h2>
                    <p>Un service fictif de votre communauté : aucun nom, sigle, adresse ni numéro réel.</p>
                </div>
                <button type="button" class="sse-iconbtn" data-sse-close aria-label="Fermer"><?= '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/></svg>' ?></button>
            </div>
            <div class="sse-dialog__body">
                <?php if ($opened === 'service' && $stateErrors !== []): ?>
                    <ul class="ws-errors" role="alert"><?php foreach ($stateErrors as $e): ?><li><?= $h($e) ?></li><?php endforeach; ?></ul>
                <?php endif; ?>
                <div class="ws-tabs" role="tablist" aria-label="Sections du service" data-ws-tabs>
                    <button class="ws-tab" type="button" role="tab" aria-selected="true" data-ws-tab="id">Identité</button>
                    <button class="ws-tab" type="button" role="tab" aria-selected="false" data-ws-tab="contact">Coordonnées</button>
                    <button class="ws-tab" type="button" role="tab" aria-selected="false" data-ws-tab="issue">Émission</button>
                    <button class="ws-tab" type="button" role="tab" aria-selected="false" data-ws-tab="look">Apparence</button>
                </div>
                <div class="ws-pane" data-ws-pane="id">
                    <div class="ws-field-grid ws-field-grid--2">
                        <div><label for="wsf-fr">Nom français</label><input id="wsf-fr" type="text" name="fr" maxlength="120" required minlength="3" data-f="fr"></div>
                        <div><label for="wsf-en">Nom anglais</label><input id="wsf-en" type="text" name="en" maxlength="120" data-f="en"></div>
                    </div>
                    <div class="ws-field-grid ws-field-grid--2">
                        <div><label for="wsf-ac">Sigle fictif</label><input id="wsf-ac" type="text" name="ac" maxlength="12" pattern="[A-Za-z0-9\-]{2,12}" data-f="ac"><p class="ws-hint">2 à 12 lettres ou chiffres. Un sigle réel est refusé.</p></div>
                        <div><label for="wsf-family">Famille</label>
                            <select id="wsf-family" name="family" data-f="family"><?php foreach ($serviceFamilies as $k => $f): ?><option value="<?= $h($k) ?>"><?= $h($f[$lang]) ?></option><?php endforeach; ?></select></div>
                    </div>
                    <fieldset><legend>Rôle : qui émet</legend>
                        <div class="ws-checks"><?php foreach ($roleNames as $rk => $rn): ?><label class="ws-check"><input type="checkbox" name="roles[]" value="<?= $h($rk) ?>" data-f="roles"> <?= $h($rn) ?></label><?php endforeach; ?></div></fieldset>
                    <fieldset><legend>Langues de rédaction</legend>
                        <div class="ws-checks"><label class="ws-check"><input type="checkbox" name="lang[]" value="fr" data-f="lang"> Français</label><label class="ws-check"><input type="checkbox" name="lang[]" value="en" data-f="lang"> English</label></div></fieldset>
                </div>
                <div class="ws-pane" data-ws-pane="contact" hidden>
                    <div class="ws-field-grid ws-field-grid--2">
                        <div><label for="wsf-afr">Autorité émettrice (français)</label><input id="wsf-afr" type="text" name="auth_fr" maxlength="160" data-f="auth_fr"></div>
                        <div><label for="wsf-aen">Autorité émettrice (anglais)</label><input id="wsf-aen" type="text" name="auth_en" maxlength="160" data-f="auth_en"></div>
                    </div>
                    <div><label for="wsf-radio">Canal radio (indicatif inventé)</label><input id="wsf-radio" type="text" name="radio" maxlength="80" placeholder="HAMMER ACTUAL · canal TAC 1" data-f="radio"></div>
                    <div class="ws-field-grid ws-field-grid--2">
                        <div><label for="wsf-tel">Téléphone fictif</label><input id="wsf-tel" type="text" name="tel" maxlength="24" placeholder="+1 555 0142" data-f="tel"><p class="ws-hint">Une ligne « 555 01xx », réservée à la fiction.</p></div>
                        <div><label for="wsf-mail">Courriel fictif</label><input id="wsf-mail" type="text" name="mail" maxlength="80" placeholder="veille@service.invalid" data-f="mail"><p class="ws-hint">Domaine en « .invalid », « .example » ou « .test ».</p></div>
                    </div>
                </div>
                <div class="ws-pane" data-ws-pane="issue" hidden>
                    <div class="ws-field-grid ws-field-grid--3">
                        <div><label for="wsf-max">Classification maximale émissible</label>
                            <select id="wsf-max" name="max" data-f="max"><?php foreach (SseUsClassification::codes() as $cc): ?><option value="<?= $h($cc) ?>"><?= $h(SseUsClassification::abbr($cc)) ?></option><?php endforeach; ?></select></div>
                        <div><label for="wsf-class">Classification par défaut</label>
                            <select id="wsf-class" name="class" data-f="class"><?php foreach (SseUsClassification::codes() as $cc): ?><option value="<?= $h($cc) ?>"><?= $h(SseUsClassification::abbr($cc)) ?></option><?php endforeach; ?></select></div>
                        <div><label for="wsf-thr">Menace par défaut</label>
                            <select id="wsf-thr" name="thr" data-f="thr"><?php foreach (W::THREATS as $tk => $tl): ?><option value="<?= $h($tk) ?>"><?= $h($tl[$lang === 'en' ? 1 : 0]) ?></option><?php endforeach; ?></select></div>
                    </div>
                    <div><label for="wsf-exp">Durée de validité par défaut (jours, 0 : celle de la communauté)</label><input id="wsf-exp" type="number" name="exp" min="0" max="3650" data-f="exp"></div>
                    <fieldset><legend>Niveaux de menace habituels</legend>
                        <div class="ws-checks"><?php foreach (W::THREATS as $tk => $tl): ?><label class="ws-check"><input type="checkbox" name="threats[]" value="<?= $h($tk) ?>" data-f="threats"> <?= $h($tl[$lang === 'en' ? 1 : 0]) ?></label><?php endforeach; ?></div></fieldset>
                    <fieldset><legend>Types d’avis habituels</legend>
                        <div class="ws-checks"><?php foreach ($typeChoices as $tk => $tl): ?><label class="ws-check"><input type="checkbox" name="types[]" value="<?= $h($tk) ?>" data-f="types"> <?= $h($tl[$lang === 'en' ? 1 : 0]) ?></label><?php endforeach; ?></div></fieldset>
                    <div class="ws-field-grid ws-field-grid--2">
                        <div><label for="wsf-ifr">Consigne par défaut (français)</label><textarea id="wsf-ifr" name="ins_fr" rows="4" maxlength="<?= (int) W::INSTRUCTIONS_MAX ?>" data-f="ins_fr"></textarea></div>
                        <div><label for="wsf-ien">Consigne par défaut (anglais)</label><textarea id="wsf-ien" name="ins_en" rows="4" maxlength="<?= (int) W::INSTRUCTIONS_MAX ?>" data-f="ins_en"></textarea></div>
                    </div>
                </div>
                <div class="ws-pane" data-ws-pane="look" hidden>
                    <fieldset><legend>Couleur d’en-tête de la notice</legend>
                        <div class="ws-swatches"><?php foreach ($servicePalette as $ck => $hex): ?><label class="ws-swatch"><input type="radio" name="color" value="<?= $h($ck) ?>" data-f="color"><i style="background:<?= $h($hex) ?>"></i> <?= $h(ucfirst($ck)) ?></label><?php endforeach; ?></div>
                        <p class="ws-hint">Palette sobre : jamais un insigne ni un logo réel.</p></fieldset>
                    <fieldset><legend>Forme générique de l’emblème</legend>
                        <div class="ws-checks"><?php foreach (SseWantedServices::emblems() as $ek): ?><label class="ws-check"><input type="radio" name="emblem" value="<?= $h($ek) ?>" data-f="emblem"> <?= $h(ucfirst($ek)) ?></label><?php endforeach; ?></div></fieldset>
                    <label class="ws-check"><input type="checkbox" name="enabled" value="1" checked data-f="enabled"> Proposé dans le formulaire d’avis</label>
                </div>
            </div>
            <div class="sse-dialog__foot">
                <button class="sse-btn sse-btn--danger ws-danger" type="submit" name="op" value="delete" formnovalidate data-ws-delete hidden>Supprimer</button>
                <button class="sse-btn" type="button" data-sse-close>Annuler</button>
                <button class="sse-btn sse-btn--primary" type="submit" data-ws-save data-label-create="Créer le service" data-label-update="Enregistrer">Créer le service</button>
            </div>
        </form>
    </dialog>

    <dialog class="sse-drawer ws-drawer" id="ws-drawer-import" aria-labelledby="ws-import-title" <?= $opened === 'import' ? 'data-sse-autoopen' : '' ?>>
        <form method="post" action="<?= $h($svcAction) ?>" enctype="multipart/form-data">
            <?= $csrf ?>
            <input type="hidden" name="op" value="import">
            <div class="sse-dialog__head">
                <div>
                    <h2 id="ws-import-title">Importer des services</h2>
                    <p>Un export JSON d’une autre communauté. Chaque service passe le même contrôle qu’une création : un nom réel est refusé.</p>
                </div>
                <button type="button" class="sse-iconbtn" data-sse-close aria-label="Fermer"><svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/></svg></button>
            </div>
            <div class="sse-dialog__body">
                <?php if ($stateErrors !== []): ?><ul class="ws-errors" role="alert"><?php foreach ($stateErrors as $e): ?><li><?= $h($e) ?></li><?php endforeach; ?></ul><?php endif; ?>
                <?php if ($report !== null): ?>
                    <ul class="ws-report" aria-label="Résultat de l’import">
                        <li><strong><?= (int) $report['added'] ?> ajouté(s)</strong>, <?= (int) $report['skipped'] ?> déjà présent(s), <?= count($report['rejected']) ?> refusé(s).</li>
                        <?php foreach ($report['rejected'] as $rej): ?><li><strong translate="no"><?= $h($rej['name']) ?></strong> : <?= $h(implode(' ', $rej['reasons'])) ?></li><?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <div><label for="ws-import-file">Fichier d’export (.json)</label><input id="ws-import-file" type="file" name="import_file" accept="application/json,.json"></div>
                <div><label for="ws-import-json">Ou collez son contenu</label><textarea id="ws-import-json" name="import_json" rows="8" spellcheck="false" translate="no"></textarea><p class="ws-hint">200 Ko au plus. Les services déjà présents (même nom) sont ignorés.</p></div>
            </div>
            <div class="sse-dialog__foot">
                <button class="sse-btn" type="button" data-sse-close>Annuler</button>
                <button class="sse-btn sse-btn--primary" type="submit">Importer</button>
            </div>
        </form>
    </dialog>
<?php endif; ?>
