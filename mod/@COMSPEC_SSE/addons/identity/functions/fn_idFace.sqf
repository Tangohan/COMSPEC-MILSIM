/*
    Photo d'identité en direct (client), pendant que la visionneuse est ouverte (fn_idViewerOnLoad, onUnload d'idViewer).
    ["start", doc] -> texture de rendu à poser sur un RscPicture ; "" sans PiP ou réglage coupé (le portrait de repli reste).
    ["stop"]       -> détruit caméra, mannequin, studio et lumières, rend l'exposition automatique : plus aucun rendu.

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

if (_op isEqualTo "stop") exitWith {
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
// Conteneur fermé (6 m dans l'axe nord-sud, 2,4 x 2,6 m) posé au niveau des pieds : ni soleil ni ciel sur le mannequin.
["Land_Cargo20_blue_F", _spot vectorAdd [0, 0, -0.05], 0] call _mk;
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
uiNamespace setVariable ["comspec_sse_idFaceState", [_cam, _objs]];
// Le visage et la tête sont en place après la première image : second réglage.
[{
    params ["_cam", "_doll", "_pick", "_spot"];
    if (isNull _cam || {isNull _doll}) exitWith {};
    _doll setFace _pick;
    _doll setMimic "neutral";
    private _aim = (_spot vectorAdd (_doll selectionPosition "pilot")) vectorAdd [0, 0, -0.04];
    _cam camSetPos (ASLToAGL (_aim vectorAdd [0, 1.6, 0]));
    _cam camSetTarget (ASLToAGL _aim);
    _cam camCommit 0;
}, [_cam, _doll, _pick, _spot], 0.25] call CBA_fnc_waitAndExecute;
_texture
