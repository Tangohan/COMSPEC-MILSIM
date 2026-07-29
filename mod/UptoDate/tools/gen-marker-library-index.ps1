# Generate public/assets/js/arma-marker-library-index.js
# Indexe les PNG convertis de TOUTES les addons (A3, MarkersPlus, Metis, cTab).
$ErrorActionPreference = 'Stop'
$root = 'E:\Developpement\compsec.ttrd.fr\COMSPEC-MILSIM\public\assets\markers\arma'
$out = 'E:\Developpement\compsec.ttrd.fr\COMSPEC-MILSIM\public\assets\js\arma-marker-library-index.js'

function Rel([string]$full) {
  return ($full.Substring($root.Length + 1) -replace '\\', '/').ToLowerInvariant()
}

function Esc([string]$s) {
  return ($s -replace '\\', '\\' -replace "'", "\'" -replace "`r|`n", ' ')
}

$frRole = @{
  'infantry' = 'Infanterie'; 'armor' = 'Blindé'; 'motorized' = 'Motorisé'; 'reconnaissance' = 'Reconnaissance'
  'signal_unit' = 'Transmissions'; 'anti_armor' = 'Anti-char'; 'rotary_wing' = 'Hélicoptère'; 'uav' = 'Drone'
  'artillery' = 'Artillerie'; 'artillery_sp' = 'Artillerie automotrice'; 'mortar' = 'Mortier'; 'mortar_armored' = 'Mortier blindé'
  'air_defence' = 'Défense aérienne'; 'missile' = 'Missile'; 'surface_surface' = 'Surface-surface'
  'engineer' = 'Génie'; 'engineer_armored' = 'Génie blindé'; 'maintenance' = 'Maintenance'; 'supply' = 'Ravitaillement'
  'transportation' = 'Transport'; 'cbrn' = 'NRBC'; 'combat_service_support' = 'Soutien'; 'fixed_wing' = 'Aviation'
  'medical' = 'Santé'; 'military_police' = 'Police militaire'; 'military_intelligence' = 'Renseignement'
  'amphibious' = 'Amphibie'; 'joint_fire_support' = 'Appui feu conjoint'; 'naval' = 'Naval'
  'special_forces' = 'Forces spéciales'; 'special_operation_forces' = 'Forces opérations spéciales'
  'combined_arms' = 'Armes combinées'; 'radar' = 'Radar'; 'field_artillery_observer' = 'Observateur artillerie'
  'eod' = 'EOD'; 'ranger' = 'Ranger'; 'aviation_composite' = 'Aviation composite'; 'electromagnetic_warfare' = 'Guerre électronique'
  'internal_security_force' = 'Forces de sécurité'; 'isaf' = 'ISAF'; 'liaison' = 'Liaison'; 'main_gun_system' = 'Système d’arme'
  'police' = 'Police'; 'search_and_rescue' = 'SAR'; 'attack' = 'Attaque'; 'air_assault' = 'Assaut aérien'
  'maintenance_top' = 'Maintenance'; 'multiple_rocket_launcher' = 'Lance-roquettes multiple'
  'single_rocket_launcher' = 'Lance-roquettes'; 'sniper' = 'Tireur d’élite'; 'headquarters' = 'PC'
  'naval_top' = 'Naval'; 'radar_top' = 'Radar'; 'bridging' = 'Pontage'; 'medevac' = 'Évacuation médicale'; 'eod_top' = 'EOD'
  'airborne' = 'Aéroporté'; 'mountain' = 'Montagne'; 'light' = 'Léger'; 'medium' = 'Moyen'; 'heavy' = 'Lourd'
  'vstol' = 'VSTOL'; 'wheeled' = 'À roues'; 'towed' = 'Tracté'
}

$affFr = @{ blu = 'Ami'; bludash = 'Ami'; red = 'Adverse'; reddash = 'Adverse'; neu = 'Neutre'; unk = 'Inconnu'; com = 'Coalition' }
$affKind = @{ blu = 'friend'; bludash = 'friend'; red = 'hostile'; reddash = 'hostile'; neu = 'neutral'; unk = 'unknown'; com = 'friend' }

$mplusFr = @{
  aapoint = 'Point munitions'; ambush = 'Embuscade'; ammopoint = 'Point munitions'; attackbyfire = 'Attaque par le feu'
  block = 'Bloquer'; breach = 'Brèche'; bypass = 'Contourner'; canalize = 'Canaliser'; ccppoint = 'Point de ramassage blessés'
  checkpoint = 'Point de contrôle'; civpoint = 'Point regroupement civils'; clear = 'Nettoyer'; contain = 'Contenir'
  cordonknock = 'Cordon et frappe'; cordonsearch = 'Cordon et fouille'; cover = 'Couverture'; departurepoint = 'Point de départ opérationnel'
  destroy = 'Détruire'; detaineepoint = 'Point de détention'; disengage = 'Désengager'; disrupt = 'Perturber'
  exfiltrate = 'Exfiltrer'; feintattack = 'Flèche feinte'; fix = 'Fixer'; followassume = 'Suivre et prendre le relais'
  followsupport = 'Suivre et appuyer'; guard = 'Garde'; interdict = 'Interdire'; iprp = 'Point récupération personnel isolé'
  isolate = 'Isoler'; linkuppoint = 'Point de jonction'; mainattack = 'Flèche d’attaque principale'; medevac = 'Point évacuation médicale'
  neutralize = 'Neutraliser'; occupy = 'Occuper'; passagepoint = 'Point de passage'; phaseline = 'Ligne de phase'
  r3p = 'Point R3P'; rallypoint = 'Point de ralliement'; releasepoint = 'Point de libération'; retain = 'Conserver'
  sarpoint = 'Point SAR'; screen = 'Écran'; secure = 'Sécuriser'; seize = 'Saisir'; startpoint = 'Point de départ'
  supportbyfire = 'Appui par le feu'; supress = 'Supprimer'; turn = 'Faire pivoter'; waypoint = 'Waypoint'
}

$items = New-Object System.Collections.Generic.List[object]

# --- MarkersPlus ---
Get-ChildItem (Join-Path $root 'markersplus\data\img\*.png') -ErrorAction SilentlyContinue | ForEach-Object {
  $base = $_.BaseName.ToLowerInvariant()
  $label = if ($mplusFr.ContainsKey($base)) { $mplusFr[$base] } else { $base }
  $items.Add([ordered]@{
    key = "mplus_$base"
    label = $label
    source = 'markersplus'
    category = 'markersplus'
    affiliation = ''
    png = Rel $_.FullName
  })
}

# --- Metis mods (blu/red/neu/unk/com) ---
foreach ($side in @('blu','red','neu','unk','com')) {
  $modDir = Join-Path $root "z\mts\addons\markers\data\$side\mod"
  if (-not (Test-Path $modDir)) { continue }
  Get-ChildItem $modDir -Filter '*.png' | ForEach-Object {
    $name = $_.BaseName.ToLowerInvariant()
    # mts_markers_blu_mod_infantry → role infantry
    if ($name -notmatch '^mts_markers_(blu|red|neu|unk|com|bludash|reddash)_mod_(.+)$') { return }
    $aff = $Matches[1]
    $role = $Matches[2]
    $roleFr = if ($frRole.ContainsKey($role)) { $frRole[$role] } else { $role -replace '_', ' ' }
    $sideFr = $affFr[$aff]
    $items.Add([ordered]@{
      key = "mts_$aff`_mod_$role"
      label = ($sideFr + ' - ' + $roleFr)
      source = 'metis'
      category = "metis-$aff"
      affiliation = $affKind[$aff]
      png = Rel $_.FullName
    })
  }
}

# --- Metis special (complete markers, skip tiny overlays) ---
$specialDir = Join-Path $root 'z\mts\addons\markers\data\special'
if (Test-Path $specialDir) {
  Get-ChildItem $specialDir -Recurse -Filter '*.png' | Where-Object {
    $_.Name -notmatch 'alphanum|frameshape|preview' -and $_.Length -gt 800
  } | ForEach-Object {
    $base = $_.BaseName.ToLowerInvariant()
    $label = ($base -replace '^mts_markers_special_', '' -replace '_', ' ')
    $items.Add([ordered]@{
      key = $base
      label = ('Metis - ' + $label)
      source = 'metis'
      category = 'metis-special'
      affiliation = ''
      png = Rel $_.FullName
    })
  }
}

# --- cTab map markers (not UI chrome) ---
$ctabImg = Join-Path $root 'ctab\img'
Get-ChildItem $ctabImg -Filter '*.png' -ErrorAction SilentlyContinue | Where-Object {
  $_.Name -match '^(b_|o_|n_|u_|c_|m_|tic|icon_air_contact)'
} | ForEach-Object {
  $base = $_.BaseName.ToLowerInvariant() -replace '_ca$', ''
  $items.Add([ordered]@{
    key = "ctab_$base"
    label = ('cTab - ' + ($base -replace '_', ' '))
    source = 'ctab'
    category = 'ctab'
    affiliation = ''
    png = Rel $_.FullName
  })
}
$ctabMenu = Join-Path $root 'ctab\img\menu'
if (Test-Path $ctabMenu) {
  Get-ChildItem $ctabMenu -Filter '*.png' | ForEach-Object {
    $sidc = $_.BaseName
    $items.Add([ordered]@{
      key = "ctab_sidc_$sidc"
      label = ('cTab - symbole ' + $sidc)
      source = 'ctab'
      category = 'ctab-menu'
      affiliation = ''
      png = Rel $_.FullName
    })
  }
}

# --- Vanilla A3 map markers (military / nato / handdrawn / flags) ---
$a3Markers = Join-Path $root 'a3\ui_f\data\map\markers'
if (Test-Path $a3Markers) {
  Get-ChildItem $a3Markers -Recurse -Filter '*.png' | ForEach-Object {
    $rel = Rel $_.FullName
    $base = $_.BaseName.ToLowerInvariant() -replace '_ca$', '' -replace '_co$', ''
    $folder = Split-Path (Split-Path $rel -Parent) -Leaf
    $cat = switch -Regex ($rel) {
      '/nato/' { 'a3-nato' }
      '/military/' { 'a3-military' }
      '/handdrawn/' { 'a3-handdrawn' }
      '/flags/' { 'a3-flags' }
      '/system/' { 'a3-system' }
      default { 'a3-other' }
    }
    # Skip rotate variants clutter
    if ($base -match 'rotate_\d') { return }
    $key = if ($folder -eq 'nato') { $base } elseif ($folder -eq 'military') { "mil_$base" } elseif ($folder -eq 'handdrawn') { "hd_$base" } else { "a3_$base" }
    $items.Add([ordered]@{
      key = $key
      label = ($base -replace '_', ' ')
      source = 'vanilla'
      category = $cat
      affiliation = ''
      png = $rel
    })
  }
}

# Dedupe by key (first wins)
$seen = @{}
$unique = New-Object System.Collections.Generic.List[object]
foreach ($it in $items) {
  $k = [string]$it.key
  if ($seen.ContainsKey($k)) { continue }
  $seen[$k] = $true
  $unique.Add($it)
}

$sb = New-Object System.Text.StringBuilder
[void]$sb.AppendLine('/**')
[void]$sb.AppendLine(' * Index bibliothèque marqueurs — toutes addons (A3, MarkersPlus, Metis, cTab).')
[void]$sb.AppendLine(' * Généré par mod/UptoDate/tools/gen-marker-library-index.ps1 — ne pas éditer à la main.')
[void]$sb.AppendLine(" * Généré: $(Get-Date -Format 'yyyy-MM-dd HH:mm') · $($unique.Count) entrées")
[void]$sb.AppendLine(' */')
[void]$sb.AppendLine('window.ArmaMarkerLibraryIndex = (function () {')
[void]$sb.AppendLine("  'use strict';")
[void]$sb.AppendLine('  var ITEMS = [')
foreach ($it in $unique) {
  $line = "    { key: '$(Esc $it.key)', label: '$(Esc $it.label)', source: '$(Esc $it.source)', category: '$(Esc $it.category)', affiliation: '$(Esc $it.affiliation)', png: '$(Esc $it.png)' },"
  [void]$sb.AppendLine($line)
}
[void]$sb.AppendLine('  ];')
[void]$sb.AppendLine('  return { ITEMS: ITEMS, generatedAt: ''' + (Get-Date -Format 'o') + ''', count: ITEMS.length };')
[void]$sb.AppendLine('})();')

[System.IO.File]::WriteAllText($out, $sb.ToString(), [System.Text.UTF8Encoding]::new($false))
$bySource = $unique | Group-Object source | ForEach-Object { "$($_.Name)=$($_.Count)" }
Write-Host "OK $out total=$($unique.Count) $($bySource -join ' ')"
