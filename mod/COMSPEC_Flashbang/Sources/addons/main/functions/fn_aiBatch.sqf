/*
    comspec_fb_fnc_aiBatch : traite une tranche de 6 IA locales d'une détonation (règle du camp, exposition, réaction),
    puis confie le reste à l'image suivante (CBA_fnc_execNextFrame) : jamais plus de 6 IA par image, aucune attente.
    Le plafond de 6 par image est COMMUN à toutes les détonations en cours sur ce poste (comspec_fb_aiFrame = [image,
    IA déjà traitées dans cette image]) : en rafale (un flash toutes les 65 ms), chaque détonation ouvrait sa propre
    suite de tranches et plusieurs tombaient dans la même image (12 à 18 IA, 2 à 3 rayons chacune). Une tranche qui
    n'a plus de place attend l'image suivante ; aucune IA n'est perdue, l'ordre de chaque détonation est gardé.
    Même calcul que pour le joueur (fn_exposure) avec le rayon et le plancher de pupille des IA (fn_aiParams) ;
    les lignes de journal « unité » gardent leur format (la mission d'autotest les compte).
    [_file, _ctx, _posASL, _env, _force, _centre, _réglages] call comspec_fb_fnc_aiBatch
*/
params ["_queue", "_ctx", "_pos", "_env", "_g", "_center", "_cfg"];
private _n = 6 min count _queue;
if (isNil "comspec_fb_aiFrame") then { comspec_fb_aiFrame = [-1, 0] };
if ((comspec_fb_aiFrame select 0) != diag_frameNo) then { comspec_fb_aiFrame = [diag_frameNo, 0] };
_n = _n min ((6 - (comspec_fb_aiFrame select 1)) max 0);
comspec_fb_aiFrame set [1, (comspec_fb_aiFrame select 1) + _n];
private _side = _ctx get "side";
private _thrower = _ctx getOrDefault ["thrower", objNull];
private _area = (_ctx get "kind") == "area";
private _extra = _ctx get "extra";
private _tPlan = _ctx getOrDefault ["tPlan", 0];
private _dbg = [true] call comspec_fb_fnc_logOn;
{
    private _u = _x;
    if (!isNull _u && {alive _u} && {local _u}) then {
        private _rule = [side group _u, _side, !isNull _thrower && {_u == _thrower}] call comspec_fb_fnc_sideRule;
        _rule params ["_ruleLevel", "_kVis", "_kDur", "_kAud", "_ruleName"];
        if (_ruleLevel == 3) then {
            if (_dbg) then {
                ["unité", format ["%1 %2 m : aucun effet, camp=%3 règle=immunisé", _u, round (_u distance _center), side group _u]] call comspec_fb_fnc_log;
            };
        } else {
            private _exp = if (_area) then {
                private _sa = (_extra param [1, 0.8]) * (parseNumber ((_u distance _center) <= (_extra param [0, 10])));
                [_sa, _sa, createHashMapFromArray [["dist", _u distance _center], ["durMult", 1], ["angle", 0]]]
            } else {
                [_u, _ctx, _pos, _env, false, [_cfg select 1, _cfg select 2]] call comspec_fb_fnc_exposure
            };
            _exp params ["_vis", "_aud", "_info"];
            // Hasard par victime et par détonation (moyenne inchangée), comme pour le joueur.
            private _rv = (1 + ([0.1] call comspec_fb_fnc_rand)) * _g;
            private _ra = (1 + ([0.1] call comspec_fb_fnc_rand)) * _g;
            _vis = _vis * _rv * _kVis;
            _aud = _aud * _ra * _kAud;
            _info set ["tirage", [_rv, _ra]];
            _info set ["startle", (_info getOrDefault ["startle", 0]) * _kAud * ([1, 0.5] select (!isNull _thrower && {_u == _thrower}))];
            _info set ["durMult", (_info getOrDefault ["durMult", 1]) * _kDur];
            _info set ["id", _ctx get "id"];
            _info set ["tPlan", _tPlan];
            if (_dbg) then {
                ["unité", format ["%1 IA : %2 m, angle %3°, vue %4, %5, vision %6, audition %7 (tirage x%9 / x%10) camp=%11 règle=%12 -> %8", _u,
                    (_info getOrDefault ["dist", 0]) toFixed 1, round (_info getOrDefault ["angle", -1]),
                    ["directe", "bloquée"] select (_info getOrDefault ["blocked", false]), _env getOrDefault ["class", "?"],
                    _vis toFixed 2, _aud toFixed 2,
                    ["aucun effet (trop faible : distance, mur ou dos tourné)", "effets appliqués"] select (_vis > 0.02 || {_aud > 0.02}),
                    _rv toFixed 2, _ra toFixed 2, side group _u, _ruleName]] call comspec_fb_fnc_log;
            };
            if (_vis > 0.02 || {_aud > 0.02} || {(_info getOrDefault ["startle", 0]) > 0.15}) then {
                [_u, _vis, _aud, _pos, _info, _cfg] call comspec_fb_fnc_aiHit;
                // Blessure légère à bout portant (réglage, désactivé par défaut ; jamais avec ACE Médical).
                if (!_area && {missionNamespace getVariable ["comspec_fb_damage", false]} && {(_info get "dist") < 1}
                    && {!isClass (configFile >> "CfgPatches" >> "ace_medical")} && {isDamageAllowed _u}) then {
                    _u setDamage ((damage _u) + 0.08);
                };
            };
        };
    };
} forEach (_queue select [0, _n]);
if (count _queue > _n) then {
    [comspec_fb_fnc_aiBatch, [_queue select [_n, count _queue], _ctx, _pos, _env, _g, _center, _cfg]] call CBA_fnc_execNextFrame;
};
