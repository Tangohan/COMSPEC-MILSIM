/*
    Photo du document en direct (client), pendant que la visionneuse est ouverte (fn_idViewerOnLoad, onUnload d'idViewer).
    ["start", doc] -> texture de rendu à poser sur un RscPicture ; "" sans PiP (la silhouette reste visible).
    ["stop"]       -> détruit caméra, mannequin et lumière : plus aucun rendu.

    doc.faceNetId rempli : caméra attachée à la tête du vrai titulaire (joueur ou IA), elle le suit sans script par image.
    Sinon (faux papier, titulaire disparu) : mannequin local isolé, invisible des autres postes, avec un autre visage du
    jeu, tiré du nom imprimé : même identité, même visage ; jamais celui du porteur s'il est connu.
    Arma n'affiche pas d'image téléchargée sans -filePatching : la photo du site n'apparaît donc pas en jeu.
*/
params [["_op", "stop", [""]], ["_doc", createHashMap, [createHashMap]]];
if (!hasInterface) exitWith { "" };
private _rtt = "comspec_sse_idface";
private _state = uiNamespace getVariable ["comspec_sse_idFaceState", []];

if (_op isEqualTo "stop") exitWith {
    if (_state isNotEqualTo []) then {
        _state params ["_cam", "_doll", "_light"];
        if (!isNull _cam) then {
            _cam cameraEffect ["terminate", "back", _rtt];
            camDestroy _cam;
        };
        if (!isNull _doll) then { deleteVehicle _doll; };
        if (!isNull _light) then { deleteVehicle _light; };
        uiNamespace setVariable ["comspec_sse_idFaceState", []];
    };
    ""
};

private _texture = format ["#(argb,256,256,1)r2t(%1,1.0)", _rtt];
if !(isPiPEnabled) exitWith { "" };
// Recto, verso, zoom : la même caméra reste en place tant que la visionneuse est ouverte.
if (_state isNotEqualTo []) exitWith { _texture };

private _target = objectFromNetId (_doc getOrDefault ["faceNetId", ""]);
private _doll = objNull;
private _light = objNull;
if (isNull _target) then {
    // Visages du jeu : liste lue une fois par partie (uniquement des têtes humaines complètes).
    private _faces = uiNamespace getVariable ["comspec_sse_idFaces", []];
    if (_faces isEqualTo []) then {
        _faces = ("(getNumber (_x >> 'disabled')) isEqualTo 0 && {(getText (_x >> 'head')) isNotEqualTo ''}" configClasses (configFile >> "CfgFaces" >> "Man_A3")) apply { configName _x };
        uiNamespace setVariable ["comspec_sse_idFaces", _faces];
    };
    if (_faces isEqualTo []) exitWith {};
    private _identity = toUpper format ["%1|%2|%3", _doc getOrDefault ["last", ""], _doc getOrDefault ["first", ""], _doc getOrDefault ["dob", ""]];
    private _i = ([_identity] call comspec_sse_fnc_bioHash) mod (count _faces);
    if ((_faces select _i) isEqualTo (_doc getOrDefault ["faceAvoid", ""])) then { _i = (_i + 1) mod (count _faces); };
    private _pick = _faces select _i;
    // Loin de tout, au-dessus de l'eau : aucun décor, aucun autre joueur ne le voit (createVehicleLocal).
    _doll = "C_man_1" createVehicleLocal [0, 0, 0];
    _doll setPosASL [-4000, -4000, 2000];
    _doll enableSimulation false;
    _doll setFace _pick;
    removeHeadgear _doll;
    removeGoggles _doll;
    _light = "#lightpoint" createVehicleLocal [0, 0, 0];
    _light setLightBrightness 0.6;
    _light setLightColor [1, 0.95, 0.9];
    _light setLightAmbient [0.6, 0.6, 0.6];
    _light attachTo [_doll, [0, 1.2, 1.7]];
    _target = _doll;
};
if (isNull _target) exitWith { "" };

private _cam = "camera" camCreate (getPosATL _target);
_cam camSetFov 0.28;
_cam cameraEffect ["internal", "back", _rtt];
// Sur le point « head » sans suivre sa rotation : un plan fixe sur le visage, quels que soient les gestes.
_cam attachTo [_target, [0, 0.75, 0.02], "head"];
_cam setVectorDirAndUp [[0, -1, 0], [0, 0, 1]];
uiNamespace setVariable ["comspec_sse_idFaceState", [_cam, _doll, _light]];
_texture
