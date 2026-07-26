/**
 * Catalogue marqueurs Arma 3 + MarkersPlus + Metis Marker.
 * Inspire CfgMarkers / Map Symbols (Bohemia). Libelles FR TOC Athena.
 */
window.ArmaMarkerCatalog = (function () {
  'use strict';
  var ENTRIES = {
    'mil_dot': { kind: 'handdrawn', label: 'RepÃ¨re', glyph: 'dot', source: 'vanilla' },
    'mil_box': { kind: 'handdrawn', label: 'CarrÃ©', glyph: 'box', source: 'vanilla' },
    'mil_triangle': { kind: 'handdrawn', label: 'Triangle', glyph: 'triangle', source: 'vanilla' },
    'mil_circle': { kind: 'handdrawn', label: 'Cercle', glyph: 'circle', source: 'vanilla' },
    'mil_marker': { kind: 'handdrawn', label: 'RepÃ¨re', glyph: 'marker', source: 'vanilla' },
    'mil_flag': { kind: 'handdrawn', label: 'Drapeau', glyph: 'flag', source: 'vanilla' },
    'mil_arrow': { kind: 'handdrawn', label: 'FlÃ¨che', glyph: 'arrow', source: 'vanilla' },
    'mil_arrow2': { kind: 'handdrawn', label: 'FlÃ¨che double', glyph: 'arrow2', source: 'vanilla' },
    'mil_ambush': { kind: 'handdrawn', label: 'Embuscade', glyph: 'ambush', source: 'vanilla' },
    'mil_destroy': { kind: 'handdrawn', label: 'Destruction', glyph: 'destroy', source: 'vanilla' },
    'mil_objective': { kind: 'handdrawn', label: 'Objectif', glyph: 'objective', source: 'vanilla' },
    'mil_unknown': { kind: 'handdrawn', label: 'Inconnu', glyph: 'unknown', source: 'vanilla' },
    'mil_warning': { kind: 'handdrawn', label: 'Alerte', glyph: 'warning', source: 'vanilla' },
    'mil_join': { kind: 'handdrawn', label: 'Ralliement', glyph: 'join', source: 'vanilla' },
    'mil_pickup': { kind: 'handdrawn', label: 'RÃ©cupÃ©ration', glyph: 'pickup', source: 'vanilla' },
    'mil_start': { kind: 'handdrawn', label: 'DÃ©part', glyph: 'start', source: 'vanilla' },
    'mil_end': { kind: 'handdrawn', label: 'ArrivÃ©e', glyph: 'end', source: 'vanilla' },
    'hd_dot': { kind: 'handdrawn', label: 'RepÃ¨re', glyph: 'dot', source: 'vanilla' },
    'hd_box': { kind: 'handdrawn', label: 'CarrÃ©', glyph: 'box', source: 'vanilla' },
    'hd_triangle': { kind: 'handdrawn', label: 'Triangle', glyph: 'triangle', source: 'vanilla' },
    'hd_circle': { kind: 'handdrawn', label: 'Cercle', glyph: 'circle', source: 'vanilla' },
    'hd_flag': { kind: 'handdrawn', label: 'Drapeau', glyph: 'flag', source: 'vanilla' },
    'hd_arrow': { kind: 'handdrawn', label: 'FlÃ¨che', glyph: 'arrow', source: 'vanilla' },
    'hd_arrow2': { kind: 'handdrawn', label: 'FlÃ¨che double', glyph: 'arrow2', source: 'vanilla' },
    'hd_ambush': { kind: 'handdrawn', label: 'Embuscade', glyph: 'ambush', source: 'vanilla' },
    'hd_destroy': { kind: 'handdrawn', label: 'Destruction', glyph: 'destroy', source: 'vanilla' },
    'hd_objective': { kind: 'handdrawn', label: 'Objectif', glyph: 'objective', source: 'vanilla' },
    'hd_unknown': { kind: 'handdrawn', label: 'Inconnu', glyph: 'unknown', source: 'vanilla' },
    'hd_warning': { kind: 'handdrawn', label: 'Alerte', glyph: 'warning', source: 'vanilla' },
    'hd_join': { kind: 'handdrawn', label: 'Ralliement', glyph: 'join', source: 'vanilla' },
    'hd_pickup': { kind: 'handdrawn', label: 'RÃ©cupÃ©ration', glyph: 'pickup', source: 'vanilla' },
    'hd_start': { kind: 'handdrawn', label: 'DÃ©part', glyph: 'start', source: 'vanilla' },
    'hd_end': { kind: 'handdrawn', label: 'ArrivÃ©e', glyph: 'end', source: 'vanilla' },
    'loc_hospital': { kind: 'handdrawn', label: 'Poste mÃ©dical', glyph: 'cross', source: 'vanilla' },
    'loc_fuelstation': { kind: 'handdrawn', label: 'Station-service', glyph: 'fuel', source: 'vanilla' },
    'loc_church': { kind: 'handdrawn', label: 'Ã‰glise', glyph: 'church', source: 'vanilla' },
    'loc_transmitter': { kind: 'handdrawn', label: 'Antenne', glyph: 'tower', source: 'vanilla' },
    'loc_lighthouse': { kind: 'handdrawn', label: 'Phare', glyph: 'tower', source: 'vanilla' },
    'loc_power': { kind: 'handdrawn', label: 'Ã‰lectricitÃ©', glyph: 'power', source: 'vanilla' },
    'loc_stack': { kind: 'handdrawn', label: 'CheminÃ©e', glyph: 'tower', source: 'vanilla' },
    'loc_bunker': { kind: 'handdrawn', label: 'Bunker', glyph: 'bunker', source: 'vanilla' },
    'loc_quay': { kind: 'handdrawn', label: 'Quai', glyph: 'port', source: 'vanilla' },
    'loc_busstop': { kind: 'handdrawn', label: 'ArrÃªt', glyph: 'dot', source: 'vanilla' },
    'loc_tourism': { kind: 'handdrawn', label: 'Tourisme', glyph: 'flag', source: 'vanilla' },
    'loc_viewpoint': { kind: 'handdrawn', label: 'Point de vue', glyph: 'eye', source: 'vanilla' },
    'loc_rockarea': { kind: 'handdrawn', label: 'Rocher', glyph: 'mountain', source: 'vanilla' },
    'loc_fortification': { kind: 'handdrawn', label: 'Fortification', glyph: 'bunker', source: 'vanilla' },
    'loc_crossroad': { kind: 'handdrawn', label: 'CarrÃ©four', glyph: 'dot', source: 'vanilla' },
    'empty': { kind: 'handdrawn', label: 'Vide', glyph: 'dot', source: 'vanilla' },
    'flag': { kind: 'handdrawn', label: 'Drapeau', glyph: 'flag', source: 'vanilla' },
    'contact_arrow1': { kind: 'handdrawn', label: 'Contact flÃ¨che', glyph: 'arrow', source: 'vanilla' },
    'contact_arrow2': { kind: 'handdrawn', label: 'Contact flÃ¨che', glyph: 'arrow', source: 'vanilla' },
    'contact_arrow3': { kind: 'handdrawn', label: 'Contact flÃ¨che', glyph: 'arrow', source: 'vanilla' },
    'contact_dots1': { kind: 'handdrawn', label: 'Contact', glyph: 'dot', source: 'vanilla' },
    'contact_circle1': { kind: 'handdrawn', label: 'Contact cercle', glyph: 'circle', source: 'vanilla' },
    'contact_pencilcircle1': { kind: 'handdrawn', label: 'Contact', glyph: 'circle', source: 'vanilla' },
    'b_inf': { kind: 'nato', label: '', affiliation: 'friend', role: 'inf', source: 'vanilla' },
    'o_inf': { kind: 'nato', label: '', affiliation: 'hostile', role: 'inf', source: 'vanilla' },
    'n_inf': { kind: 'nato', label: '', affiliation: 'neutral', role: 'inf', source: 'vanilla' },
    'c_inf': { kind: 'nato', label: '', affiliation: 'neutral', role: 'inf', source: 'vanilla' },
    'u_inf': { kind: 'nato', label: '', affiliation: 'unknown', role: 'inf', source: 'vanilla' },
    'b_mech_inf': { kind: 'nato', label: '', affiliation: 'friend', role: 'mech_inf', source: 'vanilla' },
    'o_mech_inf': { kind: 'nato', label: '', affiliation: 'hostile', role: 'mech_inf', source: 'vanilla' },
    'n_mech_inf': { kind: 'nato', label: '', affiliation: 'neutral', role: 'mech_inf', source: 'vanilla' },
    'c_mech_inf': { kind: 'nato', label: '', affiliation: 'neutral', role: 'mech_inf', source: 'vanilla' },
    'u_mech_inf': { kind: 'nato', label: '', affiliation: 'unknown', role: 'mech_inf', source: 'vanilla' },
    'b_motor_inf': { kind: 'nato', label: '', affiliation: 'friend', role: 'motor_inf', source: 'vanilla' },
    'o_motor_inf': { kind: 'nato', label: '', affiliation: 'hostile', role: 'motor_inf', source: 'vanilla' },
    'n_motor_inf': { kind: 'nato', label: '', affiliation: 'neutral', role: 'motor_inf', source: 'vanilla' },
    'c_motor_inf': { kind: 'nato', label: '', affiliation: 'neutral', role: 'motor_inf', source: 'vanilla' },
    'u_motor_inf': { kind: 'nato', label: '', affiliation: 'unknown', role: 'motor_inf', source: 'vanilla' },
    'b_armor': { kind: 'nato', label: '', affiliation: 'friend', role: 'armor', source: 'vanilla' },
    'o_armor': { kind: 'nato', label: '', affiliation: 'hostile', role: 'armor', source: 'vanilla' },
    'n_armor': { kind: 'nato', label: '', affiliation: 'neutral', role: 'armor', source: 'vanilla' },
    'c_armor': { kind: 'nato', label: '', affiliation: 'neutral', role: 'armor', source: 'vanilla' },
    'u_armor': { kind: 'nato', label: '', affiliation: 'unknown', role: 'armor', source: 'vanilla' },
    'b_recon': { kind: 'nato', label: '', affiliation: 'friend', role: 'recon', source: 'vanilla' },
    'o_recon': { kind: 'nato', label: '', affiliation: 'hostile', role: 'recon', source: 'vanilla' },
    'n_recon': { kind: 'nato', label: '', affiliation: 'neutral', role: 'recon', source: 'vanilla' },
    'c_recon': { kind: 'nato', label: '', affiliation: 'neutral', role: 'recon', source: 'vanilla' },
    'u_recon': { kind: 'nato', label: '', affiliation: 'unknown', role: 'recon', source: 'vanilla' },
    'b_air': { kind: 'nato', label: '', affiliation: 'friend', role: 'air', source: 'vanilla' },
    'o_air': { kind: 'nato', label: '', affiliation: 'hostile', role: 'air', source: 'vanilla' },
    'n_air': { kind: 'nato', label: '', affiliation: 'neutral', role: 'air', source: 'vanilla' },
    'c_air': { kind: 'nato', label: '', affiliation: 'neutral', role: 'air', source: 'vanilla' },
    'u_air': { kind: 'nato', label: '', affiliation: 'unknown', role: 'air', source: 'vanilla' },
    'b_plane': { kind: 'nato', label: '', affiliation: 'friend', role: 'plane', source: 'vanilla' },
    'o_plane': { kind: 'nato', label: '', affiliation: 'hostile', role: 'plane', source: 'vanilla' },
    'n_plane': { kind: 'nato', label: '', affiliation: 'neutral', role: 'plane', source: 'vanilla' },
    'c_plane': { kind: 'nato', label: '', affiliation: 'neutral', role: 'plane', source: 'vanilla' },
    'u_plane': { kind: 'nato', label: '', affiliation: 'unknown', role: 'plane', source: 'vanilla' },
    'b_uav': { kind: 'nato', label: '', affiliation: 'friend', role: 'uav', source: 'vanilla' },
    'o_uav': { kind: 'nato', label: '', affiliation: 'hostile', role: 'uav', source: 'vanilla' },
    'n_uav': { kind: 'nato', label: '', affiliation: 'neutral', role: 'uav', source: 'vanilla' },
    'c_uav': { kind: 'nato', label: '', affiliation: 'neutral', role: 'uav', source: 'vanilla' },
    'u_uav': { kind: 'nato', label: '', affiliation: 'unknown', role: 'uav', source: 'vanilla' },
    'b_naval': { kind: 'nato', label: '', affiliation: 'friend', role: 'naval', source: 'vanilla' },
    'o_naval': { kind: 'nato', label: '', affiliation: 'hostile', role: 'naval', source: 'vanilla' },
    'n_naval': { kind: 'nato', label: '', affiliation: 'neutral', role: 'naval', source: 'vanilla' },
    'c_naval': { kind: 'nato', label: '', affiliation: 'neutral', role: 'naval', source: 'vanilla' },
    'u_naval': { kind: 'nato', label: '', affiliation: 'unknown', role: 'naval', source: 'vanilla' },
    'b_art': { kind: 'nato', label: '', affiliation: 'friend', role: 'art', source: 'vanilla' },
    'o_art': { kind: 'nato', label: '', affiliation: 'hostile', role: 'art', source: 'vanilla' },
    'n_art': { kind: 'nato', label: '', affiliation: 'neutral', role: 'art', source: 'vanilla' },
    'c_art': { kind: 'nato', label: '', affiliation: 'neutral', role: 'art', source: 'vanilla' },
    'u_art': { kind: 'nato', label: '', affiliation: 'unknown', role: 'art', source: 'vanilla' },
    'b_mortar': { kind: 'nato', label: '', affiliation: 'friend', role: 'mortar', source: 'vanilla' },
    'o_mortar': { kind: 'nato', label: '', affiliation: 'hostile', role: 'mortar', source: 'vanilla' },
    'n_mortar': { kind: 'nato', label: '', affiliation: 'neutral', role: 'mortar', source: 'vanilla' },
    'c_mortar': { kind: 'nato', label: '', affiliation: 'neutral', role: 'mortar', source: 'vanilla' },
    'u_mortar': { kind: 'nato', label: '', affiliation: 'unknown', role: 'mortar', source: 'vanilla' },
    'b_antiair': { kind: 'nato', label: '', affiliation: 'friend', role: 'antiair', source: 'vanilla' },
    'o_antiair': { kind: 'nato', label: '', affiliation: 'hostile', role: 'antiair', source: 'vanilla' },
    'n_antiair': { kind: 'nato', label: '', affiliation: 'neutral', role: 'antiair', source: 'vanilla' },
    'c_antiair': { kind: 'nato', label: '', affiliation: 'neutral', role: 'antiair', source: 'vanilla' },
    'u_antiair': { kind: 'nato', label: '', affiliation: 'unknown', role: 'antiair', source: 'vanilla' },
    'b_support': { kind: 'nato', label: '', affiliation: 'friend', role: 'support', source: 'vanilla' },
    'o_support': { kind: 'nato', label: '', affiliation: 'hostile', role: 'support', source: 'vanilla' },
    'n_support': { kind: 'nato', label: '', affiliation: 'neutral', role: 'support', source: 'vanilla' },
    'c_support': { kind: 'nato', label: '', affiliation: 'neutral', role: 'support', source: 'vanilla' },
    'u_support': { kind: 'nato', label: '', affiliation: 'unknown', role: 'support', source: 'vanilla' },
    'b_maint': { kind: 'nato', label: '', affiliation: 'friend', role: 'maint', source: 'vanilla' },
    'o_maint': { kind: 'nato', label: '', affiliation: 'hostile', role: 'maint', source: 'vanilla' },
    'n_maint': { kind: 'nato', label: '', affiliation: 'neutral', role: 'maint', source: 'vanilla' },
    'c_maint': { kind: 'nato', label: '', affiliation: 'neutral', role: 'maint', source: 'vanilla' },
    'u_maint': { kind: 'nato', label: '', affiliation: 'unknown', role: 'maint', source: 'vanilla' },
    'b_med': { kind: 'nato', label: '', affiliation: 'friend', role: 'med', source: 'vanilla' },
    'o_med': { kind: 'nato', label: '', affiliation: 'hostile', role: 'med', source: 'vanilla' },
    'n_med': { kind: 'nato', label: '', affiliation: 'neutral', role: 'med', source: 'vanilla' },
    'c_med': { kind: 'nato', label: '', affiliation: 'neutral', role: 'med', source: 'vanilla' },
    'u_med': { kind: 'nato', label: '', affiliation: 'unknown', role: 'med', source: 'vanilla' },
    'b_hq': { kind: 'nato', label: '', affiliation: 'friend', role: 'hq', source: 'vanilla' },
    'o_hq': { kind: 'nato', label: '', affiliation: 'hostile', role: 'hq', source: 'vanilla' },
    'n_hq': { kind: 'nato', label: '', affiliation: 'neutral', role: 'hq', source: 'vanilla' },
    'c_hq': { kind: 'nato', label: '', affiliation: 'neutral', role: 'hq', source: 'vanilla' },
    'u_hq': { kind: 'nato', label: '', affiliation: 'unknown', role: 'hq', source: 'vanilla' },
    'b_ordnance': { kind: 'nato', label: '', affiliation: 'friend', role: 'ordnance', source: 'vanilla' },
    'o_ordnance': { kind: 'nato', label: '', affiliation: 'hostile', role: 'ordnance', source: 'vanilla' },
    'n_ordnance': { kind: 'nato', label: '', affiliation: 'neutral', role: 'ordnance', source: 'vanilla' },
    'c_ordnance': { kind: 'nato', label: '', affiliation: 'neutral', role: 'ordnance', source: 'vanilla' },
    'u_ordnance': { kind: 'nato', label: '', affiliation: 'unknown', role: 'ordnance', source: 'vanilla' },
    'b_installation': { kind: 'nato', label: '', affiliation: 'friend', role: 'installation', source: 'vanilla' },
    'o_installation': { kind: 'nato', label: '', affiliation: 'hostile', role: 'installation', source: 'vanilla' },
    'n_installation': { kind: 'nato', label: '', affiliation: 'neutral', role: 'installation', source: 'vanilla' },
    'c_installation': { kind: 'nato', label: '', affiliation: 'neutral', role: 'installation', source: 'vanilla' },
    'u_installation': { kind: 'nato', label: '', affiliation: 'unknown', role: 'installation', source: 'vanilla' },
    'b_unknown': { kind: 'nato', label: '', affiliation: 'friend', role: 'unknown', source: 'vanilla' },
    'o_unknown': { kind: 'nato', label: '', affiliation: 'hostile', role: 'unknown', source: 'vanilla' },
    'n_unknown': { kind: 'nato', label: '', affiliation: 'neutral', role: 'unknown', source: 'vanilla' },
    'c_unknown': { kind: 'nato', label: '', affiliation: 'neutral', role: 'unknown', source: 'vanilla' },
    'u_unknown': { kind: 'nato', label: '', affiliation: 'unknown', role: 'unknown', source: 'vanilla' },
    'b_service': { kind: 'nato', label: '', affiliation: 'friend', role: 'service', source: 'vanilla' },
    'o_service': { kind: 'nato', label: '', affiliation: 'hostile', role: 'service', source: 'vanilla' },
    'n_service': { kind: 'nato', label: '', affiliation: 'neutral', role: 'service', source: 'vanilla' },
    'c_service': { kind: 'nato', label: '', affiliation: 'neutral', role: 'service', source: 'vanilla' },
    'u_service': { kind: 'nato', label: '', affiliation: 'unknown', role: 'service', source: 'vanilla' },
    'b_car': { kind: 'nato', label: '', affiliation: 'friend', role: 'car', source: 'vanilla' },
    'o_car': { kind: 'nato', label: '', affiliation: 'hostile', role: 'car', source: 'vanilla' },
    'n_car': { kind: 'nato', label: '', affiliation: 'neutral', role: 'car', source: 'vanilla' },
    'c_car': { kind: 'nato', label: '', affiliation: 'neutral', role: 'car', source: 'vanilla' },
    'u_car': { kind: 'nato', label: '', affiliation: 'unknown', role: 'car', source: 'vanilla' },
    'b_ship': { kind: 'nato', label: '', affiliation: 'friend', role: 'ship', source: 'vanilla' },
    'o_ship': { kind: 'nato', label: '', affiliation: 'hostile', role: 'ship', source: 'vanilla' },
    'n_ship': { kind: 'nato', label: '', affiliation: 'neutral', role: 'ship', source: 'vanilla' },
    'c_ship': { kind: 'nato', label: '', affiliation: 'neutral', role: 'ship', source: 'vanilla' },
    'u_ship': { kind: 'nato', label: '', affiliation: 'unknown', role: 'ship', source: 'vanilla' },
    'mplus_markers': { kind: 'mplus', label: 'MarkersPlus', glyph: 'marker', source: 'markersplus' },
    'mplus_aapoint': { kind: 'mplus', label: 'Point gÃ©nÃ©rique', glyph: 'circle', source: 'markersplus' },
    'mplus_ambush': { kind: 'mplus', label: 'Embuscade', glyph: 'ambush', source: 'markersplus' },
    'mplus_attackbyfire': { kind: 'mplus', label: 'Attaque par le feu', glyph: 'arrow', source: 'markersplus' },
    'mplus_breach': { kind: 'mplus', label: 'BrÃ¨che', glyph: 'marker', source: 'markersplus' },
    'mplus_bypass': { kind: 'mplus', label: 'Contourner', glyph: 'marker', source: 'markersplus' },
    'mplus_clear': { kind: 'mplus', label: 'Nettoyer', glyph: 'objective', source: 'markersplus' },
    'mplus_disengage': { kind: 'mplus', label: 'DÃ©sengager', glyph: 'marker', source: 'markersplus' },
    'mplus_exfiltrate': { kind: 'mplus', label: 'Exfiltrer', glyph: 'marker', source: 'markersplus' },
    'mplus_followassume': { kind: 'mplus', label: 'Suivre et prendre le relais', glyph: 'marker', source: 'markersplus' },
    'mplus_followsupport': { kind: 'mplus', label: 'Suivre et appuyer', glyph: 'marker', source: 'markersplus' },
    'mplus_occupy': { kind: 'mplus', label: 'Occuper', glyph: 'objective', source: 'markersplus' },
    'mplus_retain': { kind: 'mplus', label: 'Conserver', glyph: 'objective', source: 'markersplus' },
    'mplus_secure': { kind: 'mplus', label: 'SÃ©curiser', glyph: 'objective', source: 'markersplus' },
    'mplus_seize': { kind: 'mplus', label: 'Saisir', glyph: 'objective', source: 'markersplus' },
    'mplus_supportbyfire': { kind: 'mplus', label: 'Appui par le feu', glyph: 'marker', source: 'markersplus' },
    'mplus_block': { kind: 'mplus', label: 'Bloquer', glyph: 'objective', source: 'markersplus' },
    'mplus_canalize': { kind: 'mplus', label: 'Canaliser', glyph: 'marker', source: 'markersplus' },
    'mplus_contain': { kind: 'mplus', label: 'Contenir', glyph: 'marker', source: 'markersplus' },
    'mplus_destroy': { kind: 'mplus', label: 'DÃ©truire', glyph: 'destroy', source: 'markersplus' },
    'mplus_disrupt': { kind: 'mplus', label: 'Perturber', glyph: 'marker', source: 'markersplus' },
    'mplus_fix': { kind: 'mplus', label: 'Fixer', glyph: 'marker', source: 'markersplus' },
    'mplus_isolate': { kind: 'mplus', label: 'Isoler', glyph: 'marker', source: 'markersplus' },
    'mplus_interdict': { kind: 'mplus', label: 'Interdire', glyph: 'marker', source: 'markersplus' },
    'mplus_neutralize': { kind: 'mplus', label: 'Neutraliser', glyph: 'destroy', source: 'markersplus' },
    'mplus_supress': { kind: 'mplus', label: 'Supprimer', glyph: 'destroy', source: 'markersplus' },
    'mplus_turn': { kind: 'mplus', label: 'Faire pivoter', glyph: 'marker', source: 'markersplus' },
    'mplus_cordonknock': { kind: 'mplus', label: 'Cordon et frappe', glyph: 'marker', source: 'markersplus' },
    'mplus_cordonsearch': { kind: 'mplus', label: 'Cordon et fouille', glyph: 'marker', source: 'markersplus' },
    'mplus_guard': { kind: 'mplus', label: 'Garde', glyph: 'objective', source: 'markersplus' },
    'mplus_screen': { kind: 'mplus', label: 'Ã‰cran', glyph: 'objective', source: 'markersplus' },
    'mplus_cover': { kind: 'mplus', label: 'Couverture', glyph: 'objective', source: 'markersplus' },
    'mplus_feintattack': { kind: 'mplus', label: 'FlÃ¨che feinte', glyph: 'arrow', source: 'markersplus' },
    'mplus_mainattack': { kind: 'mplus', label: 'FlÃ¨che d\'attaque principale', glyph: 'arrow', source: 'markersplus' },
    'mplus_phaseline': { kind: 'mplus', label: 'Ligne de phase', glyph: 'arrow', source: 'markersplus' },
    'mplus_checkpoint': { kind: 'mplus', label: 'Point de contrÃ´le', glyph: 'circle', source: 'markersplus' },
    'mplus_linkuppoint': { kind: 'mplus', label: 'Point de jonction', glyph: 'circle', source: 'markersplus' },
    'mplus_passagepoint': { kind: 'mplus', label: 'Point de passage', glyph: 'circle', source: 'markersplus' },
    'mplus_rallypoint': { kind: 'mplus', label: 'Point de ralliement', glyph: 'circle', source: 'markersplus' },
    'mplus_releasepoint': { kind: 'mplus', label: 'Point de libÃ©ration', glyph: 'circle', source: 'markersplus' },
    'mplus_startpoint': { kind: 'mplus', label: 'Point de depart', glyph: 'circle', source: 'markersplus' },
    'mplus_departurepoint': { kind: 'mplus', label: 'Point de depart opÃ©rationnel', glyph: 'circle', source: 'markersplus' },
    'mplus_civpoint': { kind: 'mplus', label: 'Point de regroupement civils', glyph: 'circle', source: 'markersplus' },
    'mplus_iprp': { kind: 'mplus', label: 'Point recuperation personnel isolÃ©', glyph: 'cross', source: 'markersplus' },
    'mplus_sarpoint': { kind: 'mplus', label: 'Point SAR', glyph: 'circle', source: 'markersplus' },
    'mplus_ammopoint': { kind: 'mplus', label: 'Point munitions', glyph: 'box', source: 'markersplus' },
    'mplus_ccppoint': { kind: 'mplus', label: 'Point de ramassage blessÃ©s', glyph: 'cross', source: 'markersplus' },
    'mplus_medevac': { kind: 'mplus', label: 'Point evacuation mÃ©dicale', glyph: 'cross', source: 'markersplus' },
    'mplus_r3p': { kind: 'mplus', label: 'Point R3P', glyph: 'box', source: 'markersplus' },
    'mplus_waypoint': { kind: 'mplus', label: 'Waypoint', glyph: 'circle', source: 'markersplus' },
  };
  var METIS_ROLES = {
    armor: 'BlindÃ©', infantry: 'Infanterie', motorized: 'MotorisÃ©', reconnaissance: 'Reconnaissance',
    signal_unit: 'Transmissions', anti_armor: 'Anti-char', rotary_wing: 'HÃ©licoptÃ¨re', uav: 'Drone',
    artillery: 'Artillerie', artillery_sp: 'Artillerie automotrice', mortar: 'Mortier', mortar_armored: 'Mortier blinde',
    air_defence: 'DÃ©fense aÃ©rienne', missile: 'Missile', surface_surface: 'Surface-surface',
    engineer: 'GÃ©nie', engineer_armored: 'GÃ©nie blinde', maintenance: 'Maintenance', supply: 'Ravitaillement',
    transportation: 'Transport', cbrn: 'NRBC', combat_service_support: 'Soutien', fixed_wing: 'Aviation',
    medical: 'SantÃ©', military_police: 'Police militaire', military_intelligence: 'Renseignement',
    amphibious: 'Amphibie', joint_fire_support: 'Appui feu conjoint', naval: 'Naval',
    special_forces: 'Forces spÃ©ciales', special_operation_forces: 'Forces opÃ©rations spÃ©ciales',
    combined_arms: 'Armes combinÃ©es', radar: 'Radar', field_artillery_observer: 'Observateur artillerie',
    eod: 'EOD', ranger: 'Ranger', aviation_composite: 'Aviation composite', electromagnetic_warfare: 'Guerre Ã©lectronique',
    internal_security_force: 'Forces de sÃ©curitÃ©', isaf: 'ISAF', liaison: 'Liaison', main_gun_system: 'Systeme arme',
    police: 'Police', search_and_rescue: 'SAR', attack: 'Attaque', air_assault: 'Assaut aerien',
    maintenance_top: 'Maintenance', multiple_rocket_launcher: 'Lance-roquettes multiple',
    single_rocket_launcher: 'Lance-roquettes', sniper: 'Tireur elite', headquarters: 'PC',
    naval_top: 'Naval', radar_top: 'Radar', bridging: 'Pontage', medevac: 'Ã‰vacuation mÃ©dicale', eod_top: 'EOD',
    airborne: 'AÃ©roportÃ©', mountain: 'Montagne', light: 'LÃ©ger', medium: 'Moyen', heavy: 'Lourd',
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
      if (!roleFr && /^size_/.test(rest)) roleFr = 'Ã‰chelon ' + rest.replace(/^size_/, '');
      if (!roleFr && rest.indexOf('frameshape') >= 0) roleFr = 'Cadre';
      if (!roleFr && rest === 'hq') roleFr = 'PC';
      var label = roleFr ? ('Metis â€” ' + roleFr) : 'Symbole Metis';
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
      return { kind: 'mplus', typeKey: key, label: 'RepÃ¨re MarkersPlus', glyph: 'marker', source: 'markersplus' };
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
    return fallback || 'RepÃ¨re';
  }

  return { ENTRIES: ENTRIES, METIS_ROLES: METIS_ROLES, get: get, labelFr: labelFr, normalize: normalize };
})();
