<?php

declare(strict_types=1);
/**
 * Pied commun des pages « Sur mon téléphone » : déconnexion de ce téléphone, scripts.
 *
 * @var string $mobileView
 * @var array<string, mixed>|null $mobileLink
 * @var array<string, mixed>|null $mapConfig
 */
$h = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$ver = rawurlencode(class_exists(\App\Support\BuildVersion::class) ? \App\Support\BuildVersion::id() : '1');
$needsMap = in_array($mobileView ?? '', ['carte', 'cr', 'briefs'], true);
?>
  </main>
<?php if (!empty($mobileLink)): ?>
  <footer class="mm-foot">
    <button type="button" class="mm-link" data-mm-logout>Déconnecter ce téléphone</button>
    <small>Ce que vous faites ici part dans votre ATAK en jeu, comme si vous l’aviez fait dans le téléphone du jeu.</small>
  </footer>
<?php endif; ?>
  <div class="mm-toast" id="mm-toast" role="status" aria-live="polite" hidden></div>
<?php if ($needsMap): ?>
  <script>
    window.MM_MAP = <?= json_encode($mapConfig ?? null, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
    window.MM_MAP_ID = <?= (int) ($mapId ?? ($mobileLink['map_id'] ?? 1)) ?>;
    window.MM_USER_ID = <?= (int) ($mobileLink['user_id'] ?? 0) ?>;
    window.ATAK_API_BASE = <?= json_encode(rtrim(url(''), '/'), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?>;
    window.ATAK_MARKER_ICONS_CDN = <?= json_encode(function_exists('atak_marker_icons_cdn_base') ? atak_marker_icons_cdn_base() : rtrim(url('assets/markers/arma'), '/'), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?>;
  </script>
  <?php /* Carte Overwatch : mêmes bibliothèques et même module de carte que l'Overwatch (views/atak-overwatch-beta.php). */ ?>
  <script src="<?= $h(url('assets/vendor/leaflet-1.9.4/leaflet.js')) ?>"></script>
  <script src="<?= $h(url('assets/js/atak-map-crs.js')) ?>?v=<?= $h($ver) ?>"></script>
  <script src="<?= $h(url('assets/vendor/milsymbol/milsymbol.js')) ?>"></script>
  <script src="<?= $h(url('assets/js/milstd-catalog.js')) ?>?v=<?= $h($ver) ?>"></script>
  <script src="<?= $h(url('assets/js/nato-sidc-icons.js')) ?>?v=<?= $h($ver) ?>"></script>
  <script src="<?= $h(url('assets/js/arma-marker-catalog.js')) ?>?v=<?= $h($ver) ?>"></script>
  <script src="<?= $h(url('assets/js/arma-marker-library-index.js')) ?>?v=<?= $h($ver) ?>"></script>
  <script src="<?= $h(url('assets/js/arma-map-markers.js')) ?>?v=<?= $h($ver) ?>"></script>
  <script src="<?= $h(url('assets/js/overwatch-gl/OverwatchTileCache.js')) ?>?v=<?= $h($ver) ?>"></script>
  <script src="<?= $h(url('assets/js/atak-overwatch-mapkit.js')) ?>?v=<?= $h($ver) ?>"></script>
<?php endif; ?>
  <script src="<?= $h(url('assets/js/atak-m.js')) ?>?v=<?= $h($ver) ?>"></script>
</body>
</html>
