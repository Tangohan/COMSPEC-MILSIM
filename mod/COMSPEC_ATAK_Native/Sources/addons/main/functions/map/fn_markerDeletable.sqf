/*
    Un marqueur peut-il être effacé depuis le téléphone ? Params : [nom] -> 0 non (marqueur de la mission),
    1 marqueur de joueur (effacé pour tout le canal), 2 repère local du téléphone : alerte reçue (CONTACT, WIA, SALUTE…),
    PANIQUE d'un allié ou point marqué depuis l'app Données (effacé sur ce téléphone seulement).
*/
params [["_name", ""]];
if (_name isEqualTo "" || {(markerShape _name) isEqualTo ""}) exitWith { 0 };
if ((_name find "_USER_DEFINED") isEqualTo 0) exitWith { 1 };
if ((["COMSPEC_ALERT_", "COMSPEC_PANIC_", "COMSPEC_DATA_"] findIf { (_name find _x) isEqualTo 0 }) >= 0) exitWith { 2 };
0
