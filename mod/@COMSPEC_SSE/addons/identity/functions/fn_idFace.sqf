/*
    Photo d'identité en direct (client), pendant que la visionneuse est ouverte (fn_idViewerOnLoad, onUnload d'idViewer).
    ["start", doc] -> texture de rendu à poser sur un RscPicture ; "" sans PiP ou réglage coupé (le portrait de repli reste).
    ["stop"]       -> détruit caméra, mannequin, fond et lumières : plus aucun rendu.

    Toujours une scène dédiée, loin de tout : un mannequin local (createVehicleLocal, invisible des autres postes), de face,
    sans casque ni lunettes, devant un fond clair auto-éclairé, avec deux lumières locales. Avant, la caméra filmait la vraie
    personne : la nuit, sans aucune lumière, le rendu était un rectangle noir ; et l'angle dépendait de sa posture.
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
    };
    ""
};

private _texture = format ["#(argb,512,512,1)r2t(%1,0.69)", _rtt];
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

// Loin de tout, au-dessus de l'eau : aucun décor, aucun autre joueur ne le voit.
private _spot = [-4000, -4000, 2000];
private _doll = "C_man_1" createVehicleLocal [0, 0, 0];
_doll setPosASL _spot;
_doll setDir 0;
_doll switchMove "AmovPercMstpSnonWnonDnon";
_doll setFace _pick;
removeHeadgear _doll;
removeGoggles _doll;
_doll enableSimulation false;
private _head = _spot vectorAdd (_doll selectionPosition "pilot");
// Fond clair : sphère d'aide auto-éclairée (signes du jeu), visible de jour comme de nuit.
private _objs = [_doll];
private _bgCls = ["Sign_Sphere200cm_F", "Sign_Sphere100cm_F"] select { isClass (configFile >> "CfgVehicles" >> _x) };
if (_bgCls isNotEqualTo []) then {
    private _bg = (_bgCls select 0) createVehicleLocal [0, 0, 0];
    _bg setPosASL (_head vectorAdd [0, -1.3, 0]);
    _bg setObjectTexture [0, "#(argb,8,8,3)color(0.93,0.95,0.96,1)"];
    _objs pushBack _bg;
};
{
    _x params ["_off", "_bright"];
    private _l = "#lightpoint" createVehicleLocal [0, 0, 0];
    _l setPosASL (_head vectorAdd _off);
    _l setLightDayLight true;
    _l setLightUseFlare false;
    _l setLightColor [1, 0.97, 0.94];
    _l setLightAmbient [0.75, 0.75, 0.75];
    _l setLightBrightness _bright;
    _objs pushBack _l;
} forEach [[[0.45, 0.9, 0.2], 0.9], [[-0.45, 0.9, 0.1], 0.6]];

private _cam = "camera" camCreate (ASLToAGL (_head vectorAdd [0, 1.6, -0.02]));
_cam cameraEffect ["internal", "back", _rtt];
_cam camSetTarget (ASLToAGL _head);
// Sans camCommit, ni le cadrage ni la focale ne s'appliquent.
_cam camSetFov 0.13;
_cam camCommit 0;
uiNamespace setVariable ["comspec_sse_idFaceState", [_cam, _objs]];
// Le visage et la tête sont en place après la première image : second réglage.
[{
    params ["_cam", "_doll", "_pick", "_spot"];
    if (isNull _cam || {isNull _doll}) exitWith {};
    _doll setFace _pick;
    private _h = _spot vectorAdd (_doll selectionPosition "pilot");
    _cam camSetPos (ASLToAGL (_h vectorAdd [0, 1.6, -0.02]));
    _cam camSetTarget (ASLToAGL _h);
    _cam camCommit 0;
}, [_cam, _doll, _pick, _spot], 0.2] call CBA_fnc_waitAndExecute;
_texture
