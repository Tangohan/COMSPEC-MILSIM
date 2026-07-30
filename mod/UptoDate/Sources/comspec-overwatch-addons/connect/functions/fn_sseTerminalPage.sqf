/*
    Navigation par pages du terminal SEEK.

    L'écran de l'appareil ne tient qu'une dizaine de lignes : tous les contrôles
    coexistent dans le dialog et seuls ceux de la page courante sont affichés.

    Params: [_page, _relative]
      _page      numéro de page, ou incrément si _relative
      _relative  true = _page est un déplacement (-1 / +1)

    Pages : 0 accueil · 1 sujet · 2 contexte · 3 biométrie · 4 constat
            5 photo · 6 dossier
*/
params [["_page", 0, [0]], ["_relative", false, [false]]];

if (!hasInterface) exitWith {};

private _disp = uiNamespace getVariable ["COMSPEC_SsePerson_Display", displayNull];
if (isNull _disp) then { _disp = findDisplay 9991; };
if (isNull _disp) exitWith {};

// Chaque libellé porte un IDC : sans cela il resterait affiché et se
// superposerait au libellé de la page suivante, posé sur la même ligne.
private _pages = [
    ["ACCUEIL",   [9530, 9531, 9532, 9533, 9534, 9535]],
    ["SUJET",     [9501, 9502, 9503, 9504, 9507, 9508, 9511,
                   9551, 9552, 9553, 9554, 9555, 9556]],
    ["CONTEXTE",  [9505, 9506, 9509, 9510, 9560, 9561, 9562, 9563]],
    ["BIOMETRIE", [9514, 9522, 9523, 9524, 9527, 9566]],
    ["CONSTAT",   [9512, 9521, 9564]],
    ["PHOTO",     [9515, 9525, 9567]],
    ["DOSSIER",   [9513, 9516, 9517, 9518, 9519, 9520, 9565]]
];
private _count = count _pages;

private _current = uiNamespace getVariable ["COMSPEC_SsePerson_Page", 0];
if (!(_current isEqualType 0)) then { _current = 0; };

private _target = if (_relative) then { _current + _page } else { _page };
// Bornage plutôt que bouclage : revenir à l'accueil doit rester explicite.
_target = (_target max 0) min (_count - 1);
uiNamespace setVariable ["COMSPEC_SsePerson_Page", _target];

{
    _x params ["", "_idcs"];
    private _visible = (_forEachIndex isEqualTo _target);
    {
        private _ctrl = _disp displayCtrl _x;
        if (!isNull _ctrl) then { _ctrl ctrlShow _visible; };
    } forEach _idcs;
} forEach _pages;

// Bandeau d'aide : seulement à l'accueil.
private _hint = _disp displayCtrl 9500;
if (!isNull _hint) then { _hint ctrlShow (_target isEqualTo 0); };

// Titre et flèches.
(_pages select _target) params ["_label", ""];
private _title = _disp displayCtrl 9540;
if (!isNull _title) then {
    _title ctrlSetStructuredText parseText format [
        "<t size='0.42' align='center' color='#ffffff'>%1</t>",
        if (_target isEqualTo 0) then { "SEEK" } else { _label }
    ];
};

private _prev = _disp displayCtrl 9541;
private _next = _disp displayCtrl 9542;
if (!isNull _prev) then { _prev ctrlShow (_target > 0); };
if (!isNull _next) then { _next ctrlShow (_target < (_count - 1)); };

// Le bandeau LCD suit la page photo ; le reste du temps il informe depuis l'accueil.
[] call comspec_overwatch_connect_fnc_ssePersonRefreshPanels;
