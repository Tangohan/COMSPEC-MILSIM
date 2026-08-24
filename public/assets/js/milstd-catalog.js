/**
 * Catalogue milstd (2525C) pour sélection de symboles COMSPEC.
 * Libellés FR métier ; SIDC lettre en interne.
 * Dépend de window.milstd2525 (vendor) — dégrade gracieusement sinon.
 */
window.MilstdCatalog = (function () {
  var AFF_LETTER = {
    friend: 'F',
    friendly: 'F',
    hostile: 'H',
    enemy: 'H',
    neutral: 'N',
    unknown: 'U',
    suspect: 'S',
  };

  var AFF_LABEL_FR = {
    friend: 'Ami',
    hostile: 'Hostile',
    neutral: 'Neutre',
    unknown: 'Inconnu',
    suspect: 'Suspect',
  };

  var FAMILY_META = [
    { id: 'GRDTRK_UNT', label: 'Unités terrestres', path: ['WAR', 'GRDTRK_UNT'] },
    { id: 'GRDTRK_EQT', label: 'Équipements terrestres', path: ['WAR', 'GRDTRK_EQT'] },
    { id: 'GRDTRK_INS', label: 'Installations', path: ['WAR', 'GRDTRK_INS'] },
    { id: 'AIRTRK', label: 'Aérien', path: ['WAR', 'AIRTRK'] },
    { id: 'SSUF', label: 'Surface maritime', path: ['WAR', 'SSUF'] },
    { id: 'SBSUF', label: 'Sous-marin', path: ['WAR', 'SBSUF'] },
    { id: 'SOFUNT', label: 'Forces spéciales', path: ['WAR', 'SOFUNT'] },
    { id: 'SPC', label: 'Espace', path: ['WAR', 'SPC'] },
    { id: 'TACGRP', label: 'Points de contrôle', path: ['TACGRP'] },
    { id: 'SIGINT', label: 'Renseignement signaux', path: ['SIGINT'] },
    { id: 'STBOPS', label: 'Opérations de stabilité', path: ['STBOPS'] },
    { id: 'EMS', label: 'Urgences / protection civile', path: ['EMS'] },
  ];

  var FR_PHRASES = [
    [/GROUND TRACK EQUIPMENT/gi, 'Équipement terrestre'],
    [/GROUND TRACK UNIT/gi, 'Unité terrestre'],
    [/GROUND TRACK INSTALLATION/gi, 'Installation terrestre'],
    [/AIR DEFENSE/gi, 'défense aérienne'],
    [/ANTIARMOR/gi, 'antichar'],
    [/ANTITANK/gi, 'antichar'],
    [/ARMORED PERSONNEL CARRIER/gi, 'VTT'],
    [/INFANTRY MOTORIZED/gi, 'infanterie motorisée'],
    [/INFANTRY MECHANIZED/gi, 'infanterie mécanisée'],
    [/INFANTRY AIRBORNE/gi, 'infanterie aéroportée'],
    [/INFANTRY MOUNTAIN/gi, 'infanterie de montagne'],
    [/INFANTRY LIGHT/gi, 'infanterie légère'],
    [/INFANTRY/gi, 'infanterie'],
    [/ARMOR TRACK/gi, 'char chenillé'],
    [/ARMOR, WHEELED/gi, 'blindé à roues'],
    [/ARMOR/gi, 'blindé'],
    [/ARTILLERY/gi, 'artillerie'],
    [/FIELD ARTILLERY/gi, 'artillerie de campagne'],
    [/RECONNAISSANCE/gi, 'reconnaissance'],
    [/HEADQUARTERS/gi, 'état-major'],
    [/COMBAT SERVICE SUPPORT/gi, 'soutien logistique'],
    [/MEDICAL/gi, 'santé'],
    [/ENGINEER/gi, 'génie'],
    [/SIGNAL/gi, 'transmissions'],
    [/MILITARY POLICE/gi, 'police militaire'],
    [/SPECIAL FORCES/gi, 'forces spéciales'],
    [/FIXED WING/gi, 'à voilure fixe'],
    [/ROTARY WING/gi, 'à voilure tournante'],
    [/HELICOPTER/gi, 'hélicoptère'],
    [/UNMANNED AERIAL VEHICLE/gi, 'drone'],
    [/ATTACK\/STRIKE/gi, 'attaque / frappe'],
    [/FIGHTER/gi, 'chasse'],
    [/BOMBER/gi, 'bombardier'],
    [/TANKER/gi, 'ravitailleur'],
    [/CARGO/gi, 'cargo'],
    [/SUBMARINE/gi, 'sous-marin'],
    [/SURFACE COMBATANT/gi, 'bâtiment de combat'],
    [/CHECKPOINT/gi, 'point de contrôle'],
    [/CONTACT POINT/gi, 'point de contact'],
    [/DECISION POINT/gi, 'point de décision'],
    [/ASSEMBLY AREA/gi, 'zone de rassemblement'],
    [/SUPPLY POINT/gi, 'point de ravitaillement'],
    [/AMMUNITION/gi, 'munitions'],
    [/MORTAR/gi, 'mortier'],
    [/MISSILE/gi, 'missile'],
    [/RADAR/gi, 'radar'],
    [/UAV/gi, 'drone'],
    [/LIGHT/gi, 'léger'],
    [/MEDIUM/gi, 'moyen'],
    [/HEAVY/gi, 'lourd'],
    [/WHEELED/gi, 'à roues'],
    [/TRACKED/gi, 'chenillé'],
    [/MOTORIZED/gi, 'motorisé'],
    [/MECHANIZED/gi, 'mécanisé'],
    [/AIRBORNE/gi, 'aéroporté'],
    [/AIR ASSAULT/gi, 'assaut aérien'],
    [/MOUNTAIN/gi, 'montagne'],
    [/ARCTIC/gi, 'arctique'],
    [/AMPHIBIOUS/gi, 'amphibie'],
    [/RECOVERY/gi, 'dépannage'],
    [/UNIT/gi, 'unité'],
    [/EQUIPMENT/gi, 'équipement'],
    [/INSTALLATION/gi, 'installation'],
    [/VEHICLE/gi, 'véhicule'],
    [/GUN/gi, 'canon'],
    [/ROCKET/gi, 'roquette'],
    [/HOWITZER/gi, 'obusier'],
  ];

  var ROLE_FUNCTION = {
    infantry: { scheme: 'S', dim: 'G', functionid: 'UCI---' },
    mechanized: { scheme: 'S', dim: 'G', functionid: 'UCIM--' },
    motorized: { scheme: 'S', dim: 'G', functionid: 'UCIMO-' },
    armor: { scheme: 'S', dim: 'G', functionid: 'UCA---' },
    artillery: { scheme: 'S', dim: 'G', functionid: 'UCF---' },
    mortar: { scheme: 'S', dim: 'G', functionid: 'UCFM--' },
    recon: { scheme: 'S', dim: 'G', functionid: 'UCR---' },
    hq: { scheme: 'S', dim: 'G', functionid: 'UH----' },
    medical: { scheme: 'S', dim: 'G', functionid: 'USM---' },
    logistics: { scheme: 'S', dim: 'G', functionid: 'US----' },
    naval: { scheme: 'S', dim: 'S', functionid: 'CL----' },
    aviation_fixed: { scheme: 'S', dim: 'A', functionid: 'MF----' },
    aviation_rotary: { scheme: 'S', dim: 'A', functionid: 'MH----' },
    uav: { scheme: 'S', dim: 'A', functionid: 'MFQ---' },
  };

  var _cache = null;

  function affiliationLetter(aff) {
    var a = String(aff || 'friend').toLowerCase().trim();
    return AFF_LETTER[a] || 'F';
  }

  function affiliationLabelFr(aff) {
    var a = String(aff || 'friend').toLowerCase().trim();
    if (a === 'enemy' || a === 'east') a = 'hostile';
    if (a === 'friendly') a = 'friend';
    return AFF_LABEL_FR[a] || AFF_LABEL_FR.friend;
  }

  function titleFr(en) {
    var s = String(en || '').trim();
    if (!s) return 'Symbole';
    for (var i = 0; i < FR_PHRASES.length; i++) {
      s = s.replace(FR_PHRASES[i][0], FR_PHRASES[i][1]);
    }
    s = s.replace(/\s+/g, ' ').trim().toLowerCase();
    return s.charAt(0).toUpperCase() + s.slice(1);
  }

  function buildSidc(affOrLetter, parts) {
    parts = parts || {};
    var letter = String(affOrLetter || 'F').length === 1
      ? String(affOrLetter).toUpperCase()
      : affiliationLetter(affOrLetter);
    var scheme = String(parts.scheme || parts.codingscheme || 'S').charAt(0) || 'S';
    var dim = String(parts.dim || parts.battledimension || 'G').charAt(0) || 'G';
    var status = String(parts.status || 'P').charAt(0) || 'P';
    var fid = String(parts.functionid || '------');
    while (fid.length < 6) fid += '-';
    fid = fid.slice(0, 6);
    return (scheme + letter + dim + status + fid + '-----').slice(0, 15);
  }

  function isPointIcon(it) {
    if (!it || !it.functionid) return false;
    var geom = String(it.geometry || 'Point').toLowerCase();
    if (geom && geom !== 'point') return false;
    if (!String(it.functionid).replace(/-/g, '')) return false;
    return true;
  }

  function pushIcons(list, familyId, familyLabel, node) {
    if (!node || !Array.isArray(node.mainIcon)) return;
    for (var i = 0; i < node.mainIcon.length; i++) {
      var it = node.mainIcon[i];
      if (!isPointIcon(it)) continue;
      var nameEn = String(it.name || '').trim();
      if (!nameEn) continue;
      list.push({
        id: familyId + ':' + it.functionid + ':' + nameEn,
        familyId: familyId,
        familyLabel: familyLabel,
        nameEn: nameEn,
        nameFr: titleFr(nameEn),
        scheme: it.codingscheme || 'S',
        battledimension: it.battledimension || 'G',
        functionid: it.functionid,
        hierarchy: it.hierarchy || '',
      });
    }
  }

  function resolvePath(root, path) {
    var cur = root;
    for (var i = 0; i < path.length; i++) {
      if (!cur) return null;
      cur = cur[path[i]];
    }
    return cur;
  }

  function buildFromMilstd() {
    var root = window.milstd2525 && window.milstd2525.ms2525c;
    if (!root) return [];
    var list = [];
    for (var f = 0; f < FAMILY_META.length; f++) {
      var meta = FAMILY_META[f];
      var node = resolvePath(root, meta.path);
      pushIcons(list, meta.id, meta.label, node);
    }
    return list;
  }

  function fallbackCatalog() {
    return [
      { id: 'fb:inf', familyId: 'GRDTRK_UNT', familyLabel: 'Unités terrestres', nameEn: 'INFANTRY', nameFr: 'Infanterie', scheme: 'S', battledimension: 'G', functionid: 'UCI---' },
      { id: 'fb:armor', familyId: 'GRDTRK_UNT', familyLabel: 'Unités terrestres', nameEn: 'ARMOR', nameFr: 'Blindé', scheme: 'S', battledimension: 'G', functionid: 'UCA---' },
      { id: 'fb:arty', familyId: 'GRDTRK_UNT', familyLabel: 'Unités terrestres', nameEn: 'FIELD ARTILLERY', nameFr: 'Artillerie', scheme: 'S', battledimension: 'G', functionid: 'UCF---' },
      { id: 'fb:recon', familyId: 'GRDTRK_UNT', familyLabel: 'Unités terrestres', nameEn: 'RECONNAISSANCE', nameFr: 'Reconnaissance', scheme: 'S', battledimension: 'G', functionid: 'UCR---' },
      { id: 'fb:hq', familyId: 'GRDTRK_UNT', familyLabel: 'Unités terrestres', nameEn: 'HEADQUARTERS', nameFr: 'État-major', scheme: 'S', battledimension: 'G', functionid: 'UH----' },
      { id: 'fb:med', familyId: 'GRDTRK_UNT', familyLabel: 'Unités terrestres', nameEn: 'MEDICAL', nameFr: 'Santé', scheme: 'S', battledimension: 'G', functionid: 'USM---' },
      { id: 'fb:log', familyId: 'GRDTRK_UNT', familyLabel: 'Unités terrestres', nameEn: 'COMBAT SERVICE SUPPORT', nameFr: 'Soutien logistique', scheme: 'S', battledimension: 'G', functionid: 'US----' },
      { id: 'fb:fw', familyId: 'AIRTRK', familyLabel: 'Aérien', nameEn: 'FIXED WING', nameFr: 'À voilure fixe', scheme: 'S', battledimension: 'A', functionid: 'MF----' },
      { id: 'fb:rw', familyId: 'AIRTRK', familyLabel: 'Aérien', nameEn: 'ROTARY WING', nameFr: 'À voilure tournante', scheme: 'S', battledimension: 'A', functionid: 'MH----' },
      { id: 'fb:uav', familyId: 'AIRTRK', familyLabel: 'Aérien', nameEn: 'UAV', nameFr: 'Drone', scheme: 'S', battledimension: 'A', functionid: 'MFQ---' },
    ];
  }

  function getAll() {
    if (_cache) return _cache;
    var list = buildFromMilstd();
    if (!list.length) list = fallbackCatalog();
    _cache = list;
    return list;
  }

  function families() {
    var seen = {};
    var out = [];
    getAll().forEach(function (it) {
      if (seen[it.familyId]) return;
      seen[it.familyId] = true;
      out.push({ id: it.familyId, label: it.familyLabel });
    });
    return out;
  }

  function search(opts) {
    opts = opts || {};
    var q = String(opts.query || '').toLowerCase().trim();
    var familyId = opts.familyId || '';
    var limit = opts.limit != null ? opts.limit : 200;
    var list = getAll();
    var out = [];
    for (var i = 0; i < list.length; i++) {
      var it = list[i];
      if (familyId && it.familyId !== familyId) continue;
      if (q) {
        var hay = (it.nameFr + ' ' + it.nameEn + ' ' + it.familyLabel + ' ' + it.functionid).toLowerCase();
        if (hay.indexOf(q) < 0) continue;
      }
      out.push(it);
      if (out.length >= limit) break;
    }
    return out;
  }

  function findById(id) {
    if (!id) return null;
    var list = getAll();
    for (var i = 0; i < list.length; i++) {
      if (list[i].id === id) return list[i];
    }
    return null;
  }

  function findByFunctionId(functionid) {
    var fid = String(functionid || '');
    var list = getAll();
    for (var i = 0; i < list.length; i++) {
      if (list[i].functionid === fid) return list[i];
    }
    return null;
  }

  function sidcForEntry(entry, affiliation) {
    if (!entry) return buildSidc(affiliation, ROLE_FUNCTION.infantry);
    return buildSidc(affiliation, {
      scheme: entry.scheme,
      dim: entry.battledimension,
      functionid: entry.functionid,
    });
  }

  function sidcForRole(roleKey, affiliation) {
    var parts = ROLE_FUNCTION[roleKey] || ROLE_FUNCTION.infantry;
    return buildSidc(affiliation, parts);
  }

  return {
    AFF_LABEL_FR: AFF_LABEL_FR,
    affiliationLetter: affiliationLetter,
    affiliationLabelFr: affiliationLabelFr,
    buildSidc: buildSidc,
    getAll: getAll,
    families: families,
    search: search,
    findById: findById,
    findByFunctionId: findByFunctionId,
    sidcForEntry: sidcForEntry,
    sidcForRole: sidcForRole,
    titleFr: titleFr,
    count: function () { return getAll().length; },
  };
})();
