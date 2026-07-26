# Generate public/assets/js/arma-marker-catalog.js from MarkersPlus + Metis + vanilla
$ErrorActionPreference = 'Stop'
$out = 'E:\Developpement\compsec.ttrd.fr\COMSPEC-MILSIM\public\assets\js\arma-marker-catalog.js'

$cfgPath = 'F:\SteamLibrary\steamapps\common\Arma 3\!Workshop\@Markersplus\addons\MarkersPlus\config.cpp'
$cfg = Get-Content $cfgPath -Raw -Encoding UTF8
$mplus = [ordered]@{}
[regex]::Matches($cfg, 'class (mplus_\w+)[\s\S]*?name="([^"]+)"') | ForEach-Object {
  $cls = $_.Groups[1].Value
  if ($cls -eq 'mplus_BaseMarker') { return }
  $mplus[$cls] = $_.Groups[2].Value
}

$frMap = @{
  'Generic Point' = 'Point generique'
  'Ambush' = 'Embuscade'
  'Attack by Fire' = 'Attaque par le feu'
  'Breach' = 'Breche'
  'Bypass' = 'Contourner'
  'Clear' = 'Nettoyer'
  'Disengage' = 'Desengager'
  'Exfiltrate' = 'Exfiltrer'
  'Follow and Assume' = 'Suivre et prendre le relais'
  'Follow and Support' = 'Suivre et appuyer'
  'Occupy' = 'Occuper'
  'Retain' = 'Conserver'
  'Secure' = 'Securiser'
  'Seize' = 'Saisir'
  'Support by Fire' = 'Appui par le feu'
  'Block' = 'Bloquer'
  'Canalize' = 'Canaliser'
  'Contain' = 'Contenir'
  'Destroy' = 'Detruire'
  'Disrupt' = 'Perturber'
  'Fix' = 'Fixer'
  'Isolate' = 'Isoler'
  'Interdict' = 'Interdire'
  'Neutralize' = 'Neutraliser'
  'Suppress' = 'Supprimer'
  'Turn' = 'Faire pivoter'
  'Cordon and Knock' = 'Cordon et frappe'
  'Cordon and Search' = 'Cordon et fouille'
  'Guard' = 'Garde'
  'Screen' = 'Ecran'
  'Cover' = 'Couverture'
  'Feint Attack Arrow' = 'Fleche feinte'
  'Main Attack Arrow' = 'Fleche attaque principale'
  'Phaseline' = 'Ligne de phase'
  'Checkpoint' = 'Point de controle'
  'Linkup Point' = 'Point de jonction'
  'Passage Point' = 'Point de passage'
  'Rally Point' = 'Point de ralliement'
  'Release Point' = 'Point de liberation'
  'Start Point' = 'Point de depart'
  'Point of Departure' = 'Point de depart operationnel'
  'Civilian Collection Point' = 'Point regroupement civils'
  'Isolated Personnel Recovery Point' = 'Point recuperation personnel isole'
  'Search and Rescue Point' = 'Point SAR'
  'Ammunition Supply Point' = 'Point munitions'
  'Casualty Collection Point' = 'Point ramassage blesses'
  'Medical Evacuation Point' = 'Point evacuation medicale'
  'Rearm, Refuel, and Resupply Point' = 'Point R3P'
  'Waypoint' = 'Point de passage'
}

# Fix accents properly in output via unicode escapes in JS later - use proper FR here with UTF8
$frMap = @{
  'Generic Point' = "Point generique"
  'Ambush' = "Embuscade"
  'Attack by Fire' = "Attaque par le feu"
  'Breach' = "Breche"
  'Bypass' = "Contourner"
  'Clear' = "Nettoyer"
  'Disengage' = "Desengager"
  'Exfiltrate' = "Exfiltrer"
  'Follow and Assume' = "Suivre et prendre le relais"
  'Follow and Support' = "Suivre et appuyer"
  'Occupy' = "Occuper"
  'Retain' = "Conserver"
  'Secure' = "Securiser"
  'Seize' = "Saisir"
  'Support by Fire' = "Appui par le feu"
  'Block' = "Bloquer"
  'Canalize' = "Canaliser"
  'Contain' = "Contenir"
  'Destroy' = "Detruire"
  'Disrupt' = "Perturber"
  'Fix' = "Fixer"
  'Isolate' = "Isoler"
  'Interdict' = "Interdire"
  'Neutralize' = "Neutraliser"
  'Suppress' = "Supprimer"
  'Turn' = "Faire pivoter"
  'Cordon and Knock' = "Cordon et frappe"
  'Cordon and Search' = "Cordon et fouille"
  'Guard' = "Garde"
  'Screen' = "Ecran"
  'Cover' = "Couverture"
  'Feint Attack Arrow' = "Fleche feinte"
  'Main Attack Arrow' = "Fleche d'attaque principale"
  'Phaseline' = "Ligne de phase"
  'Checkpoint' = "Point de controle"
  'Linkup Point' = "Point de jonction"
  'Passage Point' = "Point de passage"
  'Rally Point' = "Point de ralliement"
  'Release Point' = "Point de liberation"
  'Start Point' = "Point de depart"
  'Point of Departure' = "Point de depart operationnel"
  'Civilian Collection Point' = "Point de regroupement civils"
  'Isolated Personnel Recovery Point' = "Point recuperation personnel isole"
  'Search and Rescue Point' = "Point SAR"
  'Ammunition Supply Point' = "Point munitions"
  'Casualty Collection Point' = "Point de ramassage blesses"
  'Medical Evacuation Point' = "Point evacuation medicale"
  'Rearm, Refuel, and Resupply Point' = "Point R3P"
  'Waypoint' = "Waypoint"
}

$lines = New-Object System.Collections.Generic.List[string]
$lines.Add('/**')
$lines.Add(' * Catalogue marqueurs Arma 3 + MarkersPlus + Metis Marker.')
$lines.Add(' * Inspire CfgMarkers / Map Symbols (Bohemia). Libelles FR TOC Athena.')
$lines.Add(' */')
$lines.Add('window.ArmaMarkerCatalog = (function () {')
$lines.Add("  'use strict';")
$lines.Add('  var ENTRIES = {')

function Add-Entry([string]$key, [string]$obj) {
  $lines.Add("    '$($key.ToLower())': $obj,")
}

# Vanilla hand-drawn / loc
$vanillaPairs = @(
  @('mil_dot','Repere','dot'), @('mil_box','Carre','box'), @('mil_triangle','Triangle','triangle'),
  @('mil_circle','Cercle','circle'), @('mil_marker','Repere','marker'), @('mil_flag','Drapeau','flag'),
  @('mil_arrow','Fleche','arrow'), @('mil_arrow2','Fleche double','arrow2'),
  @('mil_ambush','Embuscade','ambush'), @('mil_destroy','Destruction','destroy'),
  @('mil_objective','Objectif','objective'), @('mil_unknown','Inconnu','unknown'),
  @('mil_warning','Alerte','warning'), @('mil_join','Ralliement','join'),
  @('mil_pickup','Recuperation','pickup'), @('mil_start','Depart','start'), @('mil_end','Arrivee','end'),
  @('hd_dot','Repere','dot'), @('hd_box','Carre','box'), @('hd_triangle','Triangle','triangle'),
  @('hd_circle','Cercle','circle'), @('hd_flag','Drapeau','flag'), @('hd_arrow','Fleche','arrow'),
  @('hd_arrow2','Fleche double','arrow2'), @('hd_ambush','Embuscade','ambush'),
  @('hd_destroy','Destruction','destroy'), @('hd_objective','Objectif','objective'),
  @('hd_unknown','Inconnu','unknown'), @('hd_warning','Alerte','warning'),
  @('hd_join','Ralliement','join'), @('hd_pickup','Recuperation','pickup'),
  @('hd_start','Depart','start'), @('hd_end','Arrivee','end'),
  @('loc_hospital','Poste medical','cross'), @('loc_fuelstation','Station-service','fuel'),
  @('loc_church','Eglise','church'), @('loc_transmitter','Antenne','tower'),
  @('loc_lighthouse','Phare','tower'), @('loc_power','Electricite','power'),
  @('loc_stack','Cheminee','tower'), @('loc_bunker','Bunker','bunker'),
  @('loc_quay','Quai','port'), @('loc_busstop','Arret','dot'), @('loc_tourism','Tourisme','flag'),
  @('loc_viewpoint','Point de vue','eye'), @('loc_rockarea','Rocher','mountain'),
  @('loc_fortification','Fortification','bunker'), @('loc_crossroad','Carrefour','dot'),
  @('empty','Vide','dot'), @('flag','Drapeau','flag'),
  @('contact_arrow1','Contact fleche','arrow'), @('contact_arrow2','Contact fleche','arrow'),
  @('contact_arrow3','Contact fleche','arrow'), @('contact_dots1','Contact','dot'),
  @('contact_circle1','Contact cercle','circle'), @('contact_pencilcircle1','Contact','circle')
)
foreach ($p in $vanillaPairs) {
  Add-Entry $p[0] "{ kind: 'handdrawn', label: '$($p[1])', glyph: '$($p[2])', source: 'vanilla' }"
}

$natoRoles = @('inf','mech_inf','motor_inf','armor','recon','air','plane','uav','naval','art','mortar','antiair','support','maint','med','hq','ordnance','installation','unknown','service','car','ship')
$affMap = @{ b = 'friend'; o = 'hostile'; n = 'neutral'; c = 'neutral'; u = 'unknown' }
foreach ($role in $natoRoles) {
  foreach ($a in @('b','o','n','c','u')) {
    Add-Entry "${a}_$role" "{ kind: 'nato', label: '', affiliation: '$($affMap[$a])', role: '$role', source: 'vanilla' }"
  }
}

foreach ($cls in $mplus.Keys) {
  $en = [string]$mplus[$cls]
  $fr = if ($frMap.ContainsKey($en)) { [string]$frMap[$en] } else { $en }
  $fr = $fr.Replace('\', '\\').Replace("'", "\'")
  $glyph = 'marker'
  if ($en -match 'Arrow|Attack|Phaseline') { $glyph = 'arrow' }
  elseif ($en -match 'Destroy|Neutralize|Suppress') { $glyph = 'destroy' }
  elseif ($en -match 'Ambush') { $glyph = 'ambush' }
  elseif ($en -match 'Medical|Casualty|Medevac|SAR|Isolated') { $glyph = 'cross' }
  elseif ($en -match 'Ammunition|Rearm|R3P') { $glyph = 'box' }
  elseif ($en -match 'Point|Checkpoint|Waypoint|Rally|Linkup|Passage|Release|Start|Departure|Civilian') { $glyph = 'circle' }
  elseif ($en -match 'Guard|Screen|Cover|Secure|Retain|Occupy|Seize|Clear|Block') { $glyph = 'objective' }
  Add-Entry $cls "{ kind: 'mplus', label: '$fr', glyph: '$glyph', source: 'markersplus' }"
}

$lines.Add('  };')

$metisBlock = @'
  var METIS_ROLES = {
    armor: 'Blinde', infantry: 'Infanterie', motorized: 'Motorise', reconnaissance: 'Reconnaissance',
    signal_unit: 'Transmissions', anti_armor: 'Anti-char', rotary_wing: 'Helicoptere', uav: 'Drone',
    artillery: 'Artillerie', artillery_sp: 'Artillerie automotrice', mortar: 'Mortier', mortar_armored: 'Mortier blinde',
    air_defence: 'Defense aerienne', missile: 'Missile', surface_surface: 'Surface-surface',
    engineer: 'Genie', engineer_armored: 'Genie blinde', maintenance: 'Maintenance', supply: 'Ravitaillement',
    transportation: 'Transport', cbrn: 'NRBC', combat_service_support: 'Soutien', fixed_wing: 'Aviation',
    medical: 'Sante', military_police: 'Police militaire', military_intelligence: 'Renseignement',
    amphibious: 'Amphibie', joint_fire_support: 'Appui feu conjoint', naval: 'Naval',
    special_forces: 'Forces speciales', special_operation_forces: 'Forces operations speciales',
    combined_arms: 'Armes combinees', radar: 'Radar', field_artillery_observer: 'Observateur artillerie',
    eod: 'EOD', ranger: 'Ranger', aviation_composite: 'Aviation composite', electromagnetic_warfare: 'Guerre electronique',
    internal_security_force: 'Forces de securite', isaf: 'ISAF', liaison: 'Liaison', main_gun_system: 'Systeme arme',
    police: 'Police', search_and_rescue: 'SAR', attack: 'Attaque', air_assault: 'Assaut aerien',
    maintenance_top: 'Maintenance', multiple_rocket_launcher: 'Lance-roquettes multiple',
    single_rocket_launcher: 'Lance-roquettes', sniper: 'Tireur elite', headquarters: 'PC',
    naval_top: 'Naval', radar_top: 'Radar', bridging: 'Pontage', medevac: 'Evacuation medicale', eod_top: 'EOD',
    airborne: 'Aeroporte', mountain: 'Montagne', light: 'Leger', medium: 'Moyen', heavy: 'Lourd',
    vstol: 'VSTOL', wheeled: 'A roues', towed: 'Tracte'
  };
  var METIS_AFF = { blu: 'friend', red: 'hostile', neu: 'neutral', unk: 'unknown', bludash: 'friend', reddash: 'hostile', com: 'friend' };

  function normalize(t) {
    return String(t || '').trim().toLowerCase().replace(/\s+/g, '_').replace(/-/g, '_');
  }

  function metisNatoRole(rest) {
    var r = String(rest || '');
    if (/armor|anti_armor|main_gun/.test(r)) return 'armor';
    if (/artillery|mortar|rocket|missile|fire_support/.test(r)) return 'artillery';
    if (/rotary|air_assault|aviation/.test(r)) return 'aviation_rotary';
    if (/fixed_wing|vstol/.test(r)) return 'aviation_fixed';
    if (/uav/.test(r)) return 'uav';
    if (/recon|intelligence|ranger|sniper|observer/.test(r)) return 'recon';
    if (/medical|medevac/.test(r)) return 'medical';
    if (/supply|maintenance|transport|engineer|support|eod|cbrn/.test(r)) return 'logistics';
    if (/headquarters|hq|liaison|command/.test(r)) return 'hq';
    return 'infantry';
  }

  function get(type) {
    var key = normalize(type);
    if (!key) return null;
    if (ENTRIES[key]) return Object.assign({ typeKey: key }, ENTRIES[key]);

    // Metis composite classnames / texture basenames
    var m = key.match(/^mts_(blu|red|neu|unk|bludash|reddash|com)_(.+)$/);
    if (m) {
      var aff = METIS_AFF[m[1]] || 'unknown';
      var rest = m[2];
      var roleToken = rest.replace(/^(mod_|size_|dir_|hq_|opcond_)/, '');
      roleToken = roleToken.replace(/^hq_/, '').replace(/_preview$/, '');
      var roleFr = METIS_ROLES[roleToken] || METIS_ROLES[rest] || '';
      if (!roleFr && /^dir_/.test(rest)) roleFr = 'Direction';
      if (!roleFr && /^size_/.test(rest)) roleFr = 'Echelon ' + rest.replace(/^size_/, '');
      if (!roleFr && rest.indexOf('frameshape') >= 0) roleFr = 'Cadre';
      if (!roleFr && rest === 'hq') roleFr = 'PC';
      var label = roleFr ? ('Metis — ' + roleFr) : 'Symbole Metis';
      return {
        kind: 'metis',
        typeKey: key,
        label: label,
        affiliation: aff,
        roleKey: metisNatoRole(rest),
        glyph: 'nato',
        source: 'metis'
      };
    }

    if (key.indexOf('mplus_') === 0) {
      return { kind: 'mplus', typeKey: key, label: 'Repere MarkersPlus', glyph: 'marker', source: 'markersplus' };
    }

    // Texture path fallbacks: .../mts_markers_blu_mod_infantry.paa
    var tex = key.match(/mts_markers_(blu|red|neu|unk|bludash|reddash|com)_(.+?)(?:\.paa)?$/);
    if (tex) return get('mts_' + tex[1] + '_' + tex[2]);

    var mp = key.match(/(mplus_\w+)/);
    if (mp) return get(mp[1]);

    return null;
  }

  function labelFr(type, fallback) {
    var e = get(type);
    if (e && e.label) return e.label;
    return fallback || 'Repere';
  }

  return { ENTRIES: ENTRIES, METIS_ROLES: METIS_ROLES, get: get, labelFr: labelFr, normalize: normalize };
})();
'@

$lines.Add($metisBlock)

# Fix French accents in final file with proper UTF-8 replacements
$text = ($lines -join "`n") + "`n"
$text = $text.Replace('Repere', 'Repère').Replace('Carre', 'Carré').Replace('Fleche', 'Flèche')
$text = $text.Replace('Recuperation', 'Récupération').Replace('Depart', 'Départ').Replace('Arrivee', 'Arrivée')
$text = $text.Replace('Poste medical', 'Poste médical').Replace('Eglise', 'Église').Replace('Electricite', 'Électricité')
$text = $text.Replace('Cheminee', 'Cheminée').Replace('Arret', 'Arrêt')
$text = $text.Replace('Blinde', 'Blindé').Replace('Motorise', 'Motorisé').Replace('Helicoptere', 'Hélicoptère')
$text = $text.Replace('Defense aerienne', 'Défense aérienne').Replace('Genie', 'Génie').Replace('Sante', 'Santé')
$text = $text.Replace('Renseignement', 'Renseignement').Replace('speciales', 'spéciales').Replace('operations', 'opérations')
$text = $text.Replace('combinees', 'combinées').Replace('electronique', 'électronique').Replace('securite', 'sécurité')
$text = $text.Replace('Aeroporte', 'Aéroporté').Replace('Leger', 'Léger').Replace('Evacuation', 'Évacuation')
$text = $text.Replace('Echelon', 'Échelon').Replace('Point de controle', 'Point de contrôle')
$text = $text.Replace('Breche', 'Brèche').Replace('Desengager', 'Désengager').Replace('Securiser', 'Sécuriser')
$text = $text.Replace('Detruire', 'Détruire').Replace('Ecran', 'Écran').Replace('generique', 'générique')
$text = $text.Replace('blesses', 'blessés').Replace('medicale', 'médicale').Replace('liberation', 'libération')
$text = $text.Replace('isole', 'isolé').Replace('operationnel', 'opérationnel')
$text = $text.Replace('Contact fleche', 'Contact flèche')

[System.IO.File]::WriteAllText($out, $text, [System.Text.UTF8Encoding]::new($false))
Write-Host "OK $out size=$((Get-Item $out).Length) mplus=$($mplus.Count)"
