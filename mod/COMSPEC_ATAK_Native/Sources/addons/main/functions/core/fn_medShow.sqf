/*
    Données médicales visibles dans l'ATAK (Réglages > Réalisme > Données médicales).
    Chaque donnée et chaque endroit peut être masqué :
      - par le serveur (réglages CBA « COMSPEC · ATAK · Données médicales », comspec_atak_native_med_<clé>, imposés à tous) ;
      - par la communauté sur Athena (clé med_<clé> : "on" / "off", imposée) ;
      - sinon par le joueur (profil COMSPEC_ATAK_MedHide = liste des clés masquées).
    Une donnée masquée n'apparaît nulle part dans l'ATAK (app Médical et moniteur, liaison NFC / câble et rapports
    envoyés, carte et BFT, alertes).
    Params :
      [champ, endroit]   -> visible ? (endroit "" : seulement le champ)
      ["fields"]         -> [[clé, libellé]...] ; ["contexts"] -> [[clé, libellé]...] (clé d'endroit : "ctx_<endroit>")
      ["rule", clé]      -> [visible, imposé, par qui ("serveur", "communauté" ou "")]
      ["ctx", endroit]   -> l'endroit est-il affiché ?
*/
params [["_field", ""], ["_ctx", ""]];
private _fields = [
    ["state", "État (inconscient, arrêt cardiaque, gravité)"],
    ["hr", "Pouls et tracé cardiaque"],
    ["bp", "Tension artérielle"],
    ["spo2", "Saturation (SpO2)"],
    ["blood", "Volume sanguin"],
    ["bleed", "Hémorragie"],
    ["pain", "Douleur"],
    ["wounds", "Blessures par partie du corps"],
    ["tq", "Garrots"],
    ["fractures", "Fractures"],
    ["meds", "Médicaments administrés"],
    ["triage", "Catégorie de triage"],
    ["bloodtype", "Groupe sanguin"],
    ["weight", "Poids"]
];
private _ctxs = [
    ["allies", "Carte, BFT, groupe et C2 (état des alliés)"],
    ["medical", "App Médical (suivi des troupes, moniteur)"],
    ["alerts", "Alertes médicales et alertes BFT"],
    ["nfc", "Liaison NFC / câble avec un blessé, rapports"]
];
if (_field isEqualTo "fields") exitWith { _fields };
if (_field isEqualTo "contexts") exitWith { _ctxs apply { ["ctx_" + (_x select 0), _x select 1] } };
private _rule = {
    params ["_k"];
    if !(missionNamespace getVariable [format ["comspec_atak_native_med_%1", _k], true]) exitWith { [false, true, "serveur"] };
    private _t = [format ["med_%1", _k]] call comspec_atak_native_fnc_tenantRule;
    if (_t in ["on", "off"]) exitWith { [_t isEqualTo "on", true, "communauté"] };
    private _hide = profileNamespace getVariable ["COMSPEC_ATAK_MedHide", []];
    if !(_hide isEqualType []) then { _hide = []; };
    [!(_k in _hide), false, ""]
};
if (_field isEqualTo "rule") exitWith { [_ctx] call _rule };
if (_field isEqualTo "ctx") exitWith { ([format ["ctx_%1", _ctx]] call _rule) select 0 };
(([_field] call _rule) select 0) && {_ctx isEqualTo "" || {(([format ["ctx_%1", _ctx]] call _rule) select 0)}}
