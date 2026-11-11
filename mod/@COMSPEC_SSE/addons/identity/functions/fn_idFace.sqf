/*
    Photo d'identité en direct (client), pendant que la visionneuse est ouverte (fn_idViewerOnLoad, onUnload d'idViewer).
    ["start", doc] -> texture de rendu à poser sur un RscPicture ; "" sans PiP ou réglage coupé (le portrait de repli reste).
    ["stop"]       -> détruit caméra, mannequin, studio et lumières, rend l'exposition automatique : plus aucun rendu.
    ["ready"]      -> vrai quand le portrait est vérifié (tête en place, caméra DANS le studio) : la visionneuse ne montre le
                      rendu qu'à ce moment (contrôle uiNamespace comspec_sse_idFaceCtrl, caché d'ici là) ; avant, ou après
                      4 essais ratés : la silhouette neutre de repli (silhouette_co), jamais une image ratée.
    Photo bleue unie (« dégradé bleu » sur la carte, 11/2026) : le conteneur bleu était posé sans tenir compte de l'axe de
    son modèle. Son grand côté n'était pas dans l'axe de la photo : la caméra, à 1,6 m devant le visage, se retrouvait
    DEHORS, dans la paroi, et filmait la tôle bleue éclairée par les lampes du studio. Le conteneur est maintenant orienté
    d'après sa boîte englobante (grand côté dans l'axe nord-sud), centré entre le fond et la caméra (setPosWorld), gris
    neutre, et la caméra est vérifiée à l'intérieur avant de montrer le rendu (sinon nouvel essai, puis la silhouette).
    Rien n'est gardé d'une ouverture à l'autre : le visage et le haut viennent du document reçu à chaque ouverture
    (faceAvoid : visage actuel du titulaire), un changement de visage ou de tenue est donc pris à l'ouverture suivante ;
    un seul studio à la fois (la visionneuse est unique), détruit à la fermeture.

    Studio photo fermé, loin de tout : un conteneur maritime (createVehicleLocal, invisible des autres postes) posé en
    l'air au-dessus de la mer ; dedans, un mannequin local de face, sans casque ni lunettes, en haut sombre uni, devant un
    panneau clair uni (fond réglementaire gris-bleu pâle), éclairé par trois lumières locales (face, débouchage, fond).
    Le conteneur coupe le soleil et le ciel ; l'exposition est FIXÉE (setAperture) tant que la visionneuse est ouverte
    (le monde derrière est de toute façon assombri par la fenêtre) : le rendu est le même de jour comme de nuit.
    Avant : mannequin à l'air libre à 2000 m, visage brûlé par le soleil ou par les lampes selon l'ouverture automatique
    du jeu (la nuit, l'œil s'ouvre en grand et la scène éclairée devenait blanche), tête minuscule (focale trop large),
    polo blanc délavé, sphère de fond mal texturée (fond bleu nuit).
    Cadrage : tête et épaules, visage ≈ 70 % de la hauteur (format 35 x 45 : r2t au rapport du cadre de la carte).
    Visage : celui du titulaire (doc.faceAvoid, envoyé par le serveur quand faceNetId est rempli). Faux papier, titulaire
    disparu : un autre visage du jeu, tiré du nom imprimé (même identité, même visage ; jamais celui du vrai titulaire).
    Arma n'affiche pas d'image téléchargée sans -filePatching : la photo du site n'apparaît donc pas en jeu.
*/
params [["_op", "stop", [""]], ["_doc", createHashMap, [createHashMap]]];
if (!hasInterface) exitWith { "" };
private _rtt = "comspec_sse_idface";
private _state = uiNamespace getVariable ["comspec_sse_idFaceState", []];

if (_op isEqualTo "ready") exitWith { _state param [2, false] };
if (_op isEqualTo "stop") exitWith {
    uiNamespace setVariable ["comspec_sse_idFaceCtrl", controlNull];
    if (_state isNotEqualTo []) then {
        _state params ["_cam", "_objs"];
        if (!isNull _cam) then {
            _cam cameraEffect ["terminate", "back", _rtt];
            camDestroy _cam;
        };
        { if (!isNull _x) then { deleteVehicle _x; }; } forEach _objs;
        uiNamespace setVariable ["comspec_sse_idFaceState", []];
        // Exposition automatique rendue au jeu.
        setAperture -1;
    };
    ""
};

// Rapport largeur / hauteur du cadre photo (35 x 45 par défaut).
private _aspect = _doc getOrDefault ["photoAspect", 0.74];
private _texture = format ["#(argb,512,512,1)r2t(%1,%2)", _rtt, _aspect];
if (!isPiPEnabled || {!(missionNamespace getVariable ["comspec_sse_id_livePhoto", true])}) exitWith { "" };
// Recto, verso, zoom : la même scène reste en place tant que la visionneuse est ouverte.
if (_state isNotEqualTo []) exitWith { _texture };

// Visages du jeu : liste lue une fois par partie (uniquement des têtes humaines complètes).
private _faces = uiNamespace getVariable ["comspec_sse_idFaces", []];
if (_faces isEqualTo []) then {
    _faces = ("(getNumber (_x >> 'disabled')) isEqualTo 0 && {(getText (_x >> 'head')) isNotEqualTo ''}" configClasses (configFile >> "CfgFaces" >> "Man_A3")) apply { configName _x };
    uiNamespace setVariable ["comspec_sse_idFaces", _faces];
};
private _avoid = _doc getOrDefault ["faceAvoid", ""];
private _pick = "";
if ((_doc getOrDefault ["faceNetId", ""]) isNotEqualTo "") then { _pick = _avoid; };
if (_pick isEqualTo "" && {_faces isNotEqualTo []}) then {
    private _identity = toUpper format ["%1|%2|%3", _doc getOrDefault ["last", ""], _doc getOrDefault ["first", ""], _doc getOrDefault ["dob", ""]];
    private _i = ([_identity] call comspec_sse_fnc_bioHash) mod (count _faces);
    if ((_faces select _i) isEqualTo _avoid) then { _i = (_i + 1) mod (count _faces); };
    _pick = _faces select _i;
};
if (_pick isEqualTo "") exitWith { "" };

// Studio : au-dessus de la mer, à 300 m (sous les nuages, hors de vue), loin de tout joueur.
private _spot = [-3500, -3500, 300];
private _objs = [];
private _mk = {
    params ["_cls", "_pos", ["_dir", 0]];
    if !(isClass (configFile >> "CfgVehicles" >> _cls)) exitWith { objNull };
    private _o = _cls createVehicleLocal [0, 0, 0];
    _o enableSimulation false;
    _o setDir _dir;
    _o setPosASL _pos;
    _objs pushBack _o;
    _o
};
// Conteneur fermé (6 x 2,4 x 2,6 m), gris neutre, ni soleil ni ciel sur le mannequin. Grand côté dans l'axe de la photo
// (nord-sud) d'après SA boîte englobante, centré entre le fond (0,9 m derrière la tête) et la caméra (1,6 m devant),
// plancher sous les pieds : la caméra est dedans quel que soit l'axe du modèle.
private _box = ["Land_Cargo20_grey_F", _spot, 0] call _mk;
if (isNull _box) then { _box = ["Land_Cargo20_blue_F", _spot, 0] call _mk; };
if (!isNull _box) then {
    (boundingBoxReal _box) params ["_b1", "_b2"];
    if (((_b2 select 0) - (_b1 select 0)) > ((_b2 select 1) - (_b1 select 1))) then { _box setDir 90; };
    private _ctr = [((_b1 select 0) + (_b2 select 0)) / 2, ((_b1 select 1) + (_b2 select 1)) / 2, _b1 select 2];
    _box setPosWorld ((_spot vectorAdd [0, 0.35, -0.12]) vectorDiff (_box vectorModelToWorld _ctr));
};
// Mannequin de face (regard vers le nord, caméra au nord), haut sombre uni, tête nue, visage neutre.
private _doll = "C_man_1" createVehicleLocal [0, 0, 0];
_doll enableSimulation false;
_doll setPosASL _spot;
_doll setDir 0;
_doll switchMove "AmovPercMstpSnonWnonDnon";
_doll setFace _pick;
_doll setMimic "neutral";
removeHeadgear _doll;
removeGoggles _doll;
removeAllWeapons _doll;
removeVest _doll;
removeBackpack _doll;
{ if (isClass (configFile >> "CfgWeapons" >> _x)) exitWith { _doll forceAddUniform _x; }; } forEach ["U_C_Man_casual_6_F", "U_C_Poloshirt_blue", "U_C_Poor_1"];
_objs pushBack _doll;
private _head = _spot vectorAdd (_doll selectionPosition "pilot");
// Fond uni clair : panneau de 10 m un mètre derrière la tête, face à la caméra (sa partie visible est dans le conteneur).
private _bg = ["UserTexture10m_F", _head vectorAdd [0, -0.9, -2.5], 180] call _mk;
if (!isNull _bg) then { _bg setObjectTexture [0, "#(argb,8,8,3)color(0.86,0.89,0.92,1)"]; };
// Lumières : principale en face un peu à droite et au-dessus, débouchage à gauche, fond.
{
    _x params ["_off", "_bright", "_amb"];
    private _l = "#lightpoint" createVehicleLocal [0, 0, 0];
    _l setPosASL (_head vectorAdd _off);
    _l setLightDayLight true;
    _l setLightUseFlare false;
    _l setLightColor [1, 0.98, 0.95];
    _l setLightAmbient [_amb, _amb, _amb];
    _l setLightBrightness _bright;
    _l setLightAttenuation [0, 0, 0, 1.2];
    _objs pushBack _l;
} forEach [[[0.6, 1.1, 0.45], 1.6, 0.25], [[-0.8, 0.9, 0.1], 0.7, 0.15], [[0, -0.5, 0.9], 0.5, 0.1]];

// Caméra à 1,6 m, focale longue : la tête et les épaules remplissent le cadre (visage ≈ 70 % de la hauteur).
private _aim = _head vectorAdd [0, 0, -0.04];
private _cam = "camera" camCreate (ASLToAGL (_aim vectorAdd [0, 1.6, 0]));
_cam cameraEffect ["internal", "back", _rtt];
_cam camSetTarget (ASLToAGL _aim);
// Sans camCommit, ni le cadrage ni la focale ne s'appliquent.
_cam camSetFov 0.085;
_cam camCommit 0;
// Exposition fixée : même photo de jour et de nuit (rendue au jeu à la fermeture).
setAperture 12;
uiNamespace setVariable ["comspec_sse_idFaceState", [_cam, _objs, false]];
// Le visage et la tête sont en place après la première image : second réglage, puis vérification (tête à hauteur
// d'homme, caméra et tête dans le studio). Vérifié : le rendu est montré ; sinon nouvel essai (4 au plus, 0,25 s), puis
// la silhouette reste.
comspec_sse_idFaceCheck = {
    params ["_cam", "_doll", "_pick", "_spot", "_box", ["_try", 1]];
    if (isNull _cam || {isNull _doll}) exitWith {};
    if (((uiNamespace getVariable ["comspec_sse_idFaceState", []]) param [0, objNull]) isNotEqualTo _cam) exitWith {};
    _doll setFace _pick;
    _doll setMimic "neutral";
    private _rel = _doll selectionPosition "pilot";
    private _aim = (_spot vectorAdd _rel) vectorAdd [0, 0, -0.04];
    private _camPos = _aim vectorAdd [0, 1.6, 0];
    _cam camSetPos (ASLToAGL _camPos);
    _cam camSetTarget (ASLToAGL _aim);
    _cam camCommit 0;
    private _inside = {
        params ["_p"];
        if (isNull _box) exitWith { true };
        private _m = _box worldToModel (ASLToAGL _p);
        (boundingBoxReal _box) params ["_b1", "_b2"];
        private _ok = true;
        { if ((_m select _x) <= ((_b1 select _x) + 0.05) || {(_m select _x) >= ((_b2 select _x) - 0.05)}) then { _ok = false; }; } forEach [0, 1, 2];
        _ok
    };
    private _good = ((_rel select 2) > 1.2) && {(_rel select 2) < 2.1} && {[_camPos] call _inside} && {[_aim] call _inside};
    if (_good) exitWith {
        private _st = uiNamespace getVariable ["comspec_sse_idFaceState", []];
        if ((count _st) >= 2) then { _st set [2, true]; };
        private _c = uiNamespace getVariable ["comspec_sse_idFaceCtrl", controlNull];
        if (!isNull _c) then { _c ctrlShow true; };
    };
    if (_try >= 4) exitWith {
        diag_log text format ["[COMSPEC SSE] Identité : portrait non vérifié (tête %1, caméra hors du studio), silhouette gardée.", _rel];
    };
    [comspec_sse_idFaceCheck, [_cam, _doll, _pick, _spot, _box, _try + 1], 0.25] call CBA_fnc_waitAndExecute;
};
[comspec_sse_idFaceCheck, [_cam, _doll, _pick, _spot, _box], 0.25] call CBA_fnc_waitAndExecute;
_texture
