/*
    Slot virtuel COMSPEC (EXPÉRIMENTAL) : dessine sur cette machine les lunettes portées dans le slot COMSPEC par les
    unités proches. Appelée toutes les 0,2 s (XEH_postInitClient), au changement de vue et quand le joueur met ou
    retire ses lunettes ; le suivi de la tête se fait à chaque image (fn_eyewearFollow).

    C'est la seule méthode possible : Arma 3 n'a pas de slot d'inventaire personnalisable et le personnage n'a pas de
    proxy libre (lunettes, JVN, couvre-chef : tous déjà pris). L'objet 3D COMSPEC_SSE_EyewearHead_<id> (modèle centré sur
    les yeux, sans collision utile) est créé LOCALEMENT (createVehicleLocal : aucun trafic réseau ; chaque client crée sa
    copie d'après la variable publique comspec_sse_eyewear_worn de l'unité) et accroché par attachTo à l'unité.
    attachTo sur le point « pilot » avec followBoneRotation a été essayé : position mesurée juste au recalage, puis
    décalée de 15 cm après un changement de posture (le repère du décalage n'est pas celui de l'os). D'où le suivi à
    chaque image : position = point « pilot » (eyePos est 15 cm trop bas), orientation = os de la tête, écart mesuré retranché.
    Caché : pour le joueur en vue subjective (il verrait l'intérieur du modèle), dans un véhicule (un objet accroché à
    une unité embarquée ne suit pas le modèle dessiné dans le véhicule). Supprimé : mort, retrait, au-delà de 300 m.
*/
private _list = missionNamespace getVariable ["comspec_sse_eyewear_drawn", []];
private _cam = positionCameraToWorld [0, 0, 0];
private _byId = ([] call comspec_sse_fnc_eyewearCatalog) select 0;

// 1) Objets à supprimer : porteur mort ou disparu, lunettes retirées ou changées, trop loin.
_list = _list select {
    _x params ["_u", "_o", "_id"];
    private _keep = !isNull _u && {!isNull _o} && {alive _u}
        && {(_u getVariable ["comspec_sse_eyewear_worn", ""]) isEqualTo _id}
        && {(_u distance _cam) < 320};
    if (!_keep && {!isNull _o}) then { deleteVehicle _o; };
    _keep
};

// 2) Nouveaux porteurs proches : [unité, objet, id, correction, réglage de la config, dernier décalage posé].
private _drawn = _list apply { _x select 0 };
{
    private _id = _x getVariable ["comspec_sse_eyewear_worn", ""];
    if (_id isNotEqualTo "" && {!(_x in _drawn)} && {(_x distance _cam) < 300}) then {
        private _cls = (_byId getOrDefault [_id, createHashMap]) getOrDefault ["head", ""];
        if (_cls isNotEqualTo "" && {isClass (configFile >> "CfgVehicles" >> _cls)}) then {
            private _o = _cls createVehicleLocal [0, 0, 0];
            _o attachTo [_x, _x selectionPosition ["pilot", "Memory"]];
            _list pushBack [_x, _o, _id, [0, 0, 0], getArray (configFile >> "CfgVehicles" >> _cls >> "comspec_sse_eyewearAttachOffset"), []];
        };
    };
} forEach allUnits;

// 3) Visibilité. Vue subjective = vue INTERNAL du joueur ET caméra réellement aux yeux (une caméra de script ou
// de spectateur garde cameraView à INTERNAL alors qu'elle filme ailleurs : l'objet doit alors rester visible).
private _firstPerson = (cameraView in ["INTERNAL", "GUNNER"]) && {isNull curatorCamera} && {cameraOn isEqualTo (vehicle player)}
    && {((AGLToASL (positionCameraToWorld [0, 0, 0])) vectorDistance (eyePos player)) < 0.3};
{
    _x params ["_u", "_o"];
    _o hideObject ((!isNull objectParent _u) || {_firstPerson && {_u isEqualTo player}});
} forEach _list;

missionNamespace setVariable ["comspec_sse_eyewear_drawn", _list];

// 4) Suivi à chaque image, seulement tant qu'il y a des lunettes à dessiner.
private _eh = missionNamespace getVariable ["comspec_sse_eyewear_followEH", -1];
if (_list isNotEqualTo [] && {_eh < 0}) then {
    missionNamespace setVariable ["comspec_sse_eyewear_followEH", addMissionEventHandler ["EachFrame", { [] call comspec_sse_fnc_eyewearFollow; }]];
};
if (_list isEqualTo [] && {_eh >= 0}) then {
    removeMissionEventHandler ["EachFrame", _eh];
    missionNamespace setVariable ["comspec_sse_eyewear_followEH", -1];
};
count _list
