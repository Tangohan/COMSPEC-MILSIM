/*

    Identifiant stable du terminal ATAK pour cette installation (persisté en profil).

    Retourne une chaîne du type OW-XXXXXXXX (jamais nil / "<null>").

*/

if (!hasInterface) exitWith { "" };



private _isBadUid = {

    if (isNil "_this") exitWith { true };

    private _v = _this;

    if (!(_v isEqualType "")) exitWith { true };

    private _t = toLower (trim _v);

    if (_t isEqualTo "") exitWith { true };

    if (_t in ["null", "<null>", "<nul>", "nil", "any", "undefined"]) exitWith { true };

    if ((count _t) >= 6 && {(_t select [0, 6]) isEqualTo "<null"}) exitWith { true };

    false

};



private _uid = profileNamespace getVariable ["comspec_overwatch_terminal_uid", ""];

if (_uid call _isBadUid) then { _uid = ""; } else { _uid = trim _uid; };



if (_uid isEqualTo "") then {

    private _steam = getPlayerUID player;
    if (!(_steam isEqualType "")) then { _steam = ""; };
    private _profile = profileName;
    if (!(_profile isEqualType "")) then { _profile = ""; };

    // Stable : sans tick ni hasard, sinon chaque profil vidé recrée une fiche Athena.
    // Génération : augmentée quand l'appareil est remplacé (compromission validée, fn_compromise « newDevice ») ou quand
    // Athena signale l'identifiant comme déjà pris. Deux empreintes de 16 bits : calcul exact en flottants SQF. L'ancienne
    // formule additionnait les caractères et donnait le même identifiant à deux joueurs (les profils existants le gardent).
    private _gen = profileNamespace getVariable ["comspec_overwatch_terminal_gen", 0];
    if !(_gen isEqualType 0) then { _gen = 0; };
    private _seed = format ["%1|%2|%3", _steam, _profile, _gen];
    private _h1 = 7;
    private _h2 = 11;
    {
        _h1 = (_h1 * 31 + _x) mod 65521;
        _h2 = (_h2 * 37 + _x) mod 65519;
    } forEach toArray _seed;
    _uid = format ["OW-%1-%2", _h1, _h2];

    profileNamespace setVariable ["comspec_overwatch_terminal_uid", _uid];
    [] call comspec_overwatch_connect_fnc_saveProfile;

};



missionNamespace setVariable ["COMSPEC_TerminalUid", _uid, false];

_uid

