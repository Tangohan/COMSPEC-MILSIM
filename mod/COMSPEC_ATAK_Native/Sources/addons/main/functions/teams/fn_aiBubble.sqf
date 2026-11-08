/*
    Bulles de texte au-dessus de la tête de l'IA qui répond à un ordre (fn_aiOrder "recv"). Client uniquement.
      "say"   [unité, texte déjà traduit, nom affiché, type INFO | SUCCESS | WARNING | ERROR] -> true si la bulle est vue
              tout de suite (à portée et en ligne de vue) ; false sinon : l'appelant passe alors par le toast d'Arma.
      "chat"  paroles radio d'une IA du jeu (HandleChatMessage, posé par XEH_postInitClient) : même bulle
      "clear" retire toutes les bulles et le gestionnaire
      "draw"  à chaque image (Draw3D), tant qu'il reste une bulle

    Coût : aucun événement Draw3D sans bulle (posé par "say", retiré par "draw" quand la liste est vide). Avec des bulles :
    6 au plus, 5 textes par bulle ; ligne de vue et distance recalculées 4 fois par seconde, jamais à chaque image.
    Réglages (par joueur) : comspec_atak_native_ai_bubble, comspec_atak_native_ai_bubble_range.
    Texte : caractères de contrôle et < > & retirés, 160 caractères, 4 lignes de 34 caractères, coupées aux espaces.
    Découpe sur les points de code (toArray) : une lettre accentuée n'est jamais coupée en deux.
    Bulle : [0 unité, 1 lignes, 2 nom, 3 couleur, 4 début, 5 fin, 6 demi-largeur, 7 hauteur, 8 vue, 9 tête (modèle), 10 opacité]
*/
params [["_act", ""], ["_arg", []]];
#define COMSPEC_BUBBLE_MAX 6
#define COMSPEC_BUBBLE_TEXT 0.032
#define COMSPEC_BUBBLE_TAG 0.024
#define COMSPEC_BUBBLE_LINE_H 0.037

private _sees = {
    params ["_u", "_cam"];
    private _eye = eyePos _u;
    ((_cam vectorDistance _eye) <= (missionNamespace getVariable ["comspec_atak_native_ai_bubble_range", 50]))
        && { (lineIntersectsSurfaces [_cam, _eye, vehicle player, vehicle _u, true, 1, "VIEW", "FIRE"]) isEqualTo [] }
};

switch (_act) do {
    case "draw": {
        private _now = time;
        private _list = (missionNamespace getVariable ["COMSPEC_ATAK_AiBubbles", []]) select { (_x select 5) > _now && { alive (_x select 0) } };
        if (_list isEqualTo []) exitWith { ["clear"] call comspec_atak_native_fnc_aiBubble; };
        // Quatre fois par seconde : ligne de vue, position de la tête, et la bulle la plus proche placée d'abord.
        if (_now >= (missionNamespace getVariable ["COMSPEC_ATAK_AiBubbleNext", 0])) then {
            missionNamespace setVariable ["COMSPEC_ATAK_AiBubbleNext", _now + 0.25];
            private _cam = AGLToASL (positionCameraToWorld [0, 0, 0]);
            private _order = [];
            {
                private _u = _x select 0;
                _x set [8, [_u, _cam] call _sees];
                _x set [9, _u selectionPosition "pilot"];
                _order pushBack [_cam vectorDistance (eyePos _u), _forEachIndex];
            } forEach _list;
            _order sort true;
            _list = _order apply { _list select (_x select 1) };
        };
        missionNamespace setVariable ["COMSPEC_ATAK_AiBubbles", _list];
        private _dt = (_now - (missionNamespace getVariable ["COMSPEC_ATAK_AiBubbleLast", _now])) max 0;
        missionNamespace setVariable ["COMSPEC_ATAK_AiBubbleLast", _now];
        if (visibleMap) exitWith {};
        private _placed = [];
        {
            _x params ["_u", "_lines", "_tag", "_col", "_t0", "_t1", "_hw", "_h", "_vis", "_off", "_va"];
            _va = [(_va - _dt * 4) max 0, (_va + _dt * 4) min 1] select _vis;
            _x set [10, _va];
            if (_va <= 0.01) then { continue };
            private _head = (_u modelToWorldVisual _off) vectorAdd [0, 0, 0.3];
            private _s0 = worldToScreen _head;
            if (_s0 isEqualTo []) then { continue };
            private _s1 = worldToScreen (_head vectorAdd [0, 0, 1]);
            if (_s1 isEqualTo []) then { continue };
            private _perM = ((_s0 select 1) - (_s1 select 1)) max 0.002;
            private _left = (_s0 select 0) - _hw;
            private _right = (_s0 select 0) + _hw;
            // Sans chevauchement : la bulle monte au-dessus de celles, plus proches, qu'elle croise à l'écran.
            private _bottom = _s0 select 1;
            private _moved = true;
            private _guard = 0;
            while { _moved && { _guard <= COMSPEC_BUBBLE_MAX } } do {
                _moved = false;
                _guard = _guard + 1;
                {
                    _x params ["_l", "_r", "_top", "_bot"];
                    if (_left < _r && { _right > _l } && { (_bottom - _h) < _bot } && { _bottom > _top }) then {
                        _bottom = _top - 0.006;
                        _moved = true;
                    };
                } forEach _placed;
            };
            _placed pushBack [_left, _right, _bottom - _h, _bottom];
            private _a = _va * (((_now - _t0) / 0.2) min (((_t1 - _now) / 0.8) min 1));
            private _base = ((_s0 select 1) - _bottom) / _perM;
            private _n = count _lines;
            private _at = { params ["_up"]; _head vectorAdd [0, 0, _base + _up / _perM] };
            // Trait fin de la tête à la bulle, puis le nom et le texte, du haut vers le bas.
            drawLine3D [_head vectorAdd [0, 0, -0.12], [0] call _at, [_col select 0, _col select 1, _col select 2, _a * 0.8]];
            drawIcon3D ["", [_col select 0, _col select 1, _col select 2, _a], [(_n + 0.45) * COMSPEC_BUBBLE_LINE_H] call _at, 0, 0, 0, _tag, 2, COMSPEC_BUBBLE_TAG, "RobotoCondensedBold", "center"];
            {
                drawIcon3D ["", [0.97, 0.98, 0.95, _a], [(_n - _forEachIndex - 0.5) * COMSPEC_BUBBLE_LINE_H] call _at, 0, 0, 0, _x, 2, COMSPEC_BUBBLE_TEXT, "RobotoCondensed", "center"];
            } forEach _lines;
        } forEach _list;
    };
    case "say": {
        _arg params [["_u", objNull, [objNull]], ["_text", "", [""]], ["_tag", "", [""]], ["_type", "INFO", [""]]];
        if (!hasInterface || { !alive _u } || { !(missionNamespace getVariable ["comspec_atak_native_ai_bubble", true]) }) exitWith { false };
        private _cam = AGLToASL (positionCameraToWorld [0, 0, 0]);
        if ((_cam vectorDistance (eyePos _u)) > (missionNamespace getVariable ["comspec_atak_native_ai_bubble_range", 50])) exitWith { false };
        // Nettoyage et limite, sur les points de code.
        private _all = (toArray _text) apply { [_x, 32] select (_x < 32 || { _x in [38, 60, 62, 127] }) };
        private _cut = (count _all) > 160;
        private _words = (toString (_all select [0, 160])) splitString " ";
        if (_words isEqualTo []) exitWith { false };
        private _lines = [];
        private _cur = "";
        {
            private _w = toArray _x;
            // Mot plus long qu'une ligne : coupé net.
            while { (count _w) > 34 } do {
                if (_cur isNotEqualTo "") then { _lines pushBack _cur; _cur = ""; };
                _lines pushBack (toString (_w select [0, 34]));
                _w = _w select [34];
            };
            private _ws = toString _w;
            if (_cur isEqualTo "") then { _cur = _ws; } else {
                if (((count toArray _cur) + 1 + (count _w)) <= 34) then { _cur = _cur + " " + _ws; } else { _lines pushBack _cur; _cur = _ws; };
            };
        } forEach _words;
        if (_cur isNotEqualTo "") then { _lines pushBack _cur; };
        if ((count _lines) > 4) then { _lines resize 4; _cut = true; };
        if (_cut) then { _lines set [3 min ((count _lines) - 1), (_lines select (3 min ((count _lines) - 1))) + "..."]; };
        private _tagCp = (toArray _tag) apply { [_x, 32] select (_x < 32 || { _x in [38, 60, 62, 127] }) };
        private _name = toUpper (toString (_tagCp select [0, 32]));
        private _width = count (toArray _name);
        { _width = _width max (count (toArray _x)); } forEach _lines;
        // Lecture : environ 15 caractères par seconde, entre 3,5 et 12 s.
        private _dur = ((2.5 + (((count _all) min 160) / 15)) max 3.5) min 12;
        private _col = switch (_type) do {
            case "SUCCESS": { [0.52, 0.93, 0.62, 1] };
            case "WARNING";
            case "ERROR": { [1, 0.74, 0.25, 1] };
            default { [0.5, 0.82, 1, 1] };
        };
        private _list = missionNamespace getVariable ["COMSPEC_ATAK_AiBubbles", []];
        // Une seule bulle par personne qui parle ; au plafond, la plus ancienne laisse la place.
        private _same = _list findIf { (_x select 0) isEqualTo _u };
        if (_same >= 0) then { _list deleteAt _same; };
        if ((count _list) >= COMSPEC_BUBBLE_MAX) then {
            private _old = 0;
            { if ((_x select 4) < ((_list select _old) select 4)) then { _old = _forEachIndex; }; } forEach _list;
            _list deleteAt _old;
        };
        private _seen = [_u, _cam] call _sees;
        _list pushBack [_u, _lines, _name, _col, time, time + _dur, _width * COMSPEC_BUBBLE_TEXT * 0.19, ((count _lines) + 1) * COMSPEC_BUBBLE_LINE_H, _seen, _u selectionPosition "pilot", [0, 1] select _seen];
        missionNamespace setVariable ["COMSPEC_ATAK_AiBubbles", _list];
        // Vérification en jeu : journal à chaque bulle en niveau de log debug (comspec_overwatch_log_level >= 4).
        if ((missionNamespace getVariable ["comspec_overwatch_log_level", 3]) >= 4) then {
            ["DEBUG", "AIBUBBLE", format ["%1 : « %2 » (vue %3, %4 m)", _name, _text, _seen, round (_cam vectorDistance (eyePos _u))]] call comspec_atak_native_fnc_log;
        };
        if ((missionNamespace getVariable ["COMSPEC_ATAK_AiBubbleEh", -1]) < 0) then {
            missionNamespace setVariable ["COMSPEC_ATAK_AiBubbleLast", time];
            missionNamespace setVariable ["COMSPEC_ATAK_AiBubbleEh", addMissionEventHandler ["Draw3D", { ["draw"] call comspec_atak_native_fnc_aiBubble; }]];
        };
        _seen
    };
    case "chat": {
        // Arma n'a pas d'autre accès à ce que dit une IA (« 2, en position »). Le message n'est jamais modifié.
        _arg params [["_person", objNull], ["_text", ""], ["_isPlayerMsg", true]];
        if (_isPlayerMsg || {!(_person isEqualType objNull)} || {isNull _person} || {isPlayer _person} || {_person isEqualTo player} || {!(_person isKindOf "CAManBase")}) exitWith {};
        ["say", [_person, _text, name _person, "INFO"]] call comspec_atak_native_fnc_aiBubble;
    };
    case "clear": {
        private _eh = missionNamespace getVariable ["COMSPEC_ATAK_AiBubbleEh", -1];
        if (_eh >= 0) then { removeMissionEventHandler ["Draw3D", _eh]; };
        missionNamespace setVariable ["COMSPEC_ATAK_AiBubbleEh", -1];
        missionNamespace setVariable ["COMSPEC_ATAK_AiBubbles", []];
    };
};
