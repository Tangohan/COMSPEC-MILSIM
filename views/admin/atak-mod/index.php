<?php
declare(strict_types=1);

/**
 * Back-office › Ressources › Mods (Steam Workshop) — /back-office/ressources/atak-mod.
 * En-tête : coque back-office (config/back_office_pages.php). Le site n'héberge plus d'archive.
 */
use App\Support\SteamWorkshop;

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$base = url('back-office/ressources/atak-mod');
$csrf = '<input type="hidden" name="_csrf_token" value="' . $h($csrfToken ?? '') . '">';
$items = is_array($modItems ?? null) ? $modItems : SteamWorkshop::items();
$t = is_array($modTenant ?? null) ? $modTenant : ['collection_id' => '', 'collection_name' => '', 'notes' => '', 'updated_at' => ''];
$version = (string) ($modVersion ?? '');
$versionName = (string) ($modVersionName ?? '');
$linkVersion = (string) ($modLinkVersion ?? '');
$legacy = is_array($modLegacyArchive ?? null) ? $modLegacyArchive : null;
$discord = !empty($discordRelayReady);
$hasCollection = $t['collection_id'] !== '';
$fmtSize = static fn (int $b): string => $b >= 1048576 ? number_format($b / 1048576, 1, ',', ' ') . ' Mo' : number_format($b / 1024, 1, ',', ' ') . ' Ko';
?>
<div class="bo-wsm">

  <div class="ath-kpi-grid bo-wsm-kpis">
    <article class="ath-card ath-kpi is-ok"><p class="ath-kpi__label">Mod Athena</p><p class="ath-kpi__value"><?= $h($version !== '' ? $version : '—') ?></p><p class="ath-kpi__note"><?= $h($versionName !== '' ? $versionName : 'COMSPEC ATAK Native') ?></p></article>
    <article class="ath-card ath-kpi"><p class="ath-kpi__label">COMSPEC Link</p><p class="ath-kpi__value"><?= $h($linkVersion !== '' ? $linkVersion : '—') ?></p><p class="ath-kpi__note">DLL embarquée dans le mod</p></article>
    <article class="ath-card ath-kpi<?= $hasCollection ? ' is-ok' : ' is-warn' ?>"><p class="ath-kpi__label">Collection de la communauté</p><p class="ath-kpi__value"><?= $hasCollection ? 'Définie' : 'Aucune' ?></p><p class="ath-kpi__note"><?= $hasCollection ? $h($t['collection_name'] !== '' ? $t['collection_name'] : 'Collection ' . $t['collection_id']) : 'les membres ne voient que le mod Athena' ?></p></article>
    <article class="ath-card ath-kpi<?= $legacy ? ' is-warn' : '' ?>"><p class="ath-kpi__label">Archive hébergée</p><p class="ath-kpi__value"><?= $legacy ? 'À supprimer' : 'Aucune' ?></p><p class="ath-kpi__note">le site ne sert plus de fichier</p></article>
  </div>

  <section class="ath-panel bo-wsm-card">
    <h2 class="bo-wsm-t">Comment les membres installent le mod</h2>
    <p class="bo-wsm-lead">Tout passe par le Steam Workshop : un abonnement, Steam télécharge et met à jour seul. La page membre explique les 5 étapes, la collection de votre communauté et le dépannage.</p>
    <div class="bo-wsm-row">
      <a class="ath-btn ath-btn--solid" href="<?= $h($memberPageUrl ?? url('atak/mod')) ?>">Voir la page membre</a>
      <a class="ath-btn" href="<?= $h(SteamWorkshop::webUrl($items[0]['id'] ?? '')) ?>" target="_blank" rel="noopener noreferrer">Fiche Steam du mod</a>
    </div>
  </section>

  <section class="ath-table-panel">
    <div class="ath-table-toolbar"><span class="ath-table-toolbar__title">Objets Steam Workshop</span><span class="ath-table-toolbar__count"><?= count($items) ?></span><span class="ath-table-toolbar__spacer"></span><span class="bo-wsm-muted">Publiés par l’équipe COMSPEC ou dépendances officielles.</span></div>
    <div class="ath-table-wrap"><table class="ath-table bo-wsm-table">
      <thead><tr><th>Objet</th><th>Rôle</th><th>Identifiant</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($items as $it): ?>
        <tr>
          <td><b><?= $h($it['name']) ?></b><span class="bo-wsm-sub"><?= $h($it['description']) ?></span></td>
          <td><span class="ath-tag ath-tag--<?= $it['required'] ? 'ok' : 'neut' ?>"><?= $h($it['required'] ? 'Obligatoire' : $it['role']) ?></span></td>
          <td class="bo-wsm-mono"><?= $h($it['id']) ?></td>
          <td class="bo-wsm-right"><a class="ath-btn" href="<?= $h(SteamWorkshop::webUrl($it['id'])) ?>" target="_blank" rel="noopener noreferrer">Ouvrir</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  </section>

  <form method="post" action="<?= $h($base . '/steam') ?>" class="ath-panel bo-wsm-card">
    <?= $csrf ?>
    <h2 class="bo-wsm-t">Mods de votre communauté</h2>
    <p class="bo-wsm-lead">Indiquez la collection Steam qui regroupe votre modset (cartes, véhicules, équipements). Les membres s’y abonnent en un clic depuis la page d’installation.</p>
    <div class="bo-wsm-grid">
      <label class="bo-wsm-field"><span>Collection Steam</span><input name="collection_id" value="<?= $h($hasCollection ? SteamWorkshop::webUrl($t['collection_id']) : '') ?>" placeholder="https://steamcommunity.com/sharedfiles/filedetails/?id=…"><small>Lien de la collection ou son numéro. Vide : aucune collection.</small></label>
      <label class="bo-wsm-field"><span>Nom affiché</span><input name="collection_name" maxlength="120" value="<?= $h($t['collection_name']) ?>" placeholder="Ex. Modset Opé 2026.10"></label>
    </div>
    <label class="bo-wsm-field"><span>Consignes pour les membres</span><textarea name="notes" rows="4" maxlength="2000" placeholder="Ex. Preset « Opé » dans le launcher · ACRE2 obligatoire · Mot de passe serveur donné en briefing"><?= $h($t['notes']) ?></textarea><small>Affiché sur la page d’installation, sous la collection.</small></label>
    <div class="bo-wsm-row bo-wsm-row--end"><?php if ($t['updated_at'] !== ''): ?><span class="bo-wsm-muted">Modifié le <?= $h(date('d/m/Y H:i', strtotime($t['updated_at']) ?: time())) ?></span><?php endif; ?><button type="submit" class="ath-btn ath-btn--solid">Enregistrer</button></div>
  </form>

  <form method="post" action="<?= $h($base . '/annoncer') ?>" class="ath-panel bo-wsm-card">
    <?= $csrf ?>
    <h2 class="bo-wsm-t">Annoncer une mise à jour</h2>
    <p class="bo-wsm-lead">Publie une annonce de 14 jours sur le portail, avec un lien vers la page d’installation<?= $discord ? ', et la relaie sur Discord si vous le cochez' : '' ?>.</p>
    <div class="bo-wsm-grid">
      <label class="bo-wsm-field"><span>Ce qui change</span>
        <select name="what"><option value="mod">Nouvelle version du mod Athena</option><option value="collection">La collection de la communauté</option></select></label>
      <label class="bo-wsm-field"><span>Version</span><input name="version" value="<?= $h($version) ?>" placeholder="2.1.0" inputmode="decimal"></label>
    </div>
    <label class="bo-wsm-field"><span>Notes (facultatif)</span><textarea name="changelog_notes" rows="3" maxlength="4000" placeholder="Ex. Nouvelle application Météo, correctifs de la liaison radio."></textarea></label>
    <div class="bo-wsm-row bo-wsm-row--end">
      <?php if ($discord): ?><label class="bo-wsm-check"><input type="checkbox" name="notify_discord" value="1" checked> Relayer sur Discord</label>
      <?php else: ?><span class="bo-wsm-muted">Discord non relié : <a href="<?= $h($organizationSettingsUrl ?? url('back-office/organisation/parametres')) ?>">paramètres de l’organisation</a></span><?php endif; ?>
      <button type="submit" class="ath-btn ath-btn--solid">Publier l’annonce</button>
    </div>
  </form>

  <?php if ($legacy): ?>
  <form method="post" action="<?= $h($base . '/delete') ?>" class="ath-panel bo-wsm-card bo-wsm-card--warn" onsubmit="return confirm('Supprimer l’ancienne archive du serveur ?');">
    <?= $csrf ?>
    <h2 class="bo-wsm-t">Ancienne archive du pack</h2>
    <p class="bo-wsm-lead">Une archive déposée avant le passage au Steam Workshop est encore sur le serveur (<?= $h($fmtSize((int) $legacy['size'])) ?>, <?= $h($legacy['at']) ?>). Elle n’est plus proposée aux membres : vous pouvez la supprimer.</p>
    <div class="bo-wsm-row bo-wsm-row--end"><button type="submit" class="ath-btn">Supprimer l’archive</button></div>
  </form>
  <?php endif; ?>
</div>
