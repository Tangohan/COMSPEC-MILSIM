/*
    Slot virtuel COMSPEC (EXPÉRIMENTAL) : dessine sur cette machine les lunettes portées dans le slot COMSPEC par les
    unités proches. Appelée toutes les 0,2 s (XEH_postInitClient), au changement de vue et quand le joueur met ou
    retire ses lunettes.

    C'est la seule méthode possible : Arma 3 n'a pas de slot d'inventaire personnalisable et le personnage n'a pas de
    proxy libre (lunettes, JVN, couvre-chef : tous déjà pris). L'objet 3D COMSPEC_SSE_EyewearHead_<id> (modèle centré sur
    les yeux, sans collision utile) est créé LOCALEMENT (createVehicleLocal : aucun trafic réseau ; chaque client crée sa
    copie d'après la variable publique comspec_sse_eyewear_worn de l'unité) et accroché par attachTo au point de la tête
    réglé dans la config (COMSPEC_SSE_EyewearHead_Base : « pilot »), décalage recalé sur eyePos (étape 3), avec followBoneRotation :
    l'objet suit la rotation de l'os de la tête (paramètre d'attachTo d'Arma 3 2.x). Sans lui, il faudrait réorienter
    l'objet à chaque image d'après eyeDirection, en retard d'une image sur l'animation (tremblement visible).
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

// 2) Nouveaux porteurs proches. Décalage de départ : celui déjà trouvé par le recalage (étape 3), sinon la config.
private _drawn = _list apply { _x select 0 };
{
    private _id = _x getVariable ["comspec_sse_eyewear_worn", ""];
    if (_id isNotEqualTo "" && {!(_x in _drawn)} && {(_x distance _cam) < 300}) then {
        private _cls = (_byId getOrDefault [_id, createHashMap]) getOrDefault ["head", ""];
        if (_cls isNotEqualTo "" && {isClass (configFile >> "CfgVehicles" >> _cls)}) then {
            private _cfg = configFile >> "CfgVehicles" >> _cls;
            private _mem = getText (_cfg >> "comspec_sse_eyewearAttachPoint");
            private _off = missionNamespace getVariable ["comspec_sse_eyewear_attachOff", getArray (_cfg >> "comspec_sse_eyewearAttachOffset")];
            private _o = _cls createVehicleLocal [0, 0, 0];
            _o attachTo [_x, _off, _mem, true];
            _list pushBack [_x, _o, _id, 0, _off, _mem];
        };
    };
} forEach allUnits;

// 3) Recalage : le point d'accroche de la tête n'est pas exactement aux yeux (relevé en jeu : objet à 28 cm des yeux
// avec « pilot » et un décalage nul). Aux passages suivants, l'écart entre l'origine du modèle (milieu des yeux) et
// eyePos est ramené dans le repère de l'objet (qui suit l'os de la tête) et ajouté au décalage d'attachTo, jusqu'à
// moins de 5 mm (6 essais au plus, le premier au passage qui suit la création). Le décalage trouvé sert de départ aux objets suivants.
{
    _x params ["_u", "_o", "_id", ["_step", 99], ["_off", [0, 0, 0]], ["_mem", "pilot"]];
    // Passage de la création : l'objet n'est accroché qu'à l'image suivante, on mesure au passage d'après.
    if (_step isEqualTo 0) then { _x set [3, 1]; _step = 99; };
    if (_step < 7 && {isNull objectParent _u}) then {
        private _delta = (eyePos _u) vectorDiff (_o modelToWorldVisualWorld [0, 0, 0]);
        if ((vectorMagnitude _delta) < 0.005) then {
            _x set [3, 99];
            missionNamespace setVariable ["comspec_sse_eyewear_attachOff", _off];
        } else {
            private _dir = vectorDirVisual _o;
            private _up = vectorUpVisual _o;
            private _right = _dir vectorCrossProduct _up;
            _off = _off vectorAdd [_delta vectorDotProduct _right, _delta vectorDotProduct _dir, _delta vectorDotProduct _up];
            _o attachTo [_u, _off, _mem, true];
            _x set [3, _step + 1];
            _x set [4, _off];
        };
    };
} forEach _list;

// 4) Visibilité. Vue subjective = vue INTERNAL du joueur ET caméra réellement aux yeux (une caméra de script ou
// de spectateur garde cameraView à INTERNAL alors qu'elle filme ailleurs : l'objet doit alors rester visible).
private _firstPerson = (cameraView in ["INTERNAL", "GUNNER"]) && {isNull curatorCamera} && {cameraOn isEqualTo (vehicle player)}
    && {((AGLToASL (positionCameraToWorld [0, 0, 0])) vectorDistance (eyePos player)) < 0.3};
{
    _x params ["_u", "_o"];
    _o hideObject ((!isNull objectParent _u) || {_firstPerson && {_u isEqualTo player}});
} forEach _list;

missionNamespace setVariable ["comspec_sse_eyewear_drawn", _list];
count _list
