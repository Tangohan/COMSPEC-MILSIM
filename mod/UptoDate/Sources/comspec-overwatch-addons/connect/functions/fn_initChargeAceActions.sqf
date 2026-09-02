/*
    Menus ACE : choix « Uniquement depuis ATAK » dans le sélecteur de
    déclencheur, bascule sur la charge armée, liste et tout-déclencher.
*/
if (!hasInterface) exitWith { false };
if (!isClass (configFile >> "CfgPatches" >> "ace_explosives")) exitWith { false };
if (!isClass (configFile >> "CfgPatches" >> "ace_interact_menu")) exitWith { false };
if (isNil "ace_interact_menu_fnc_createAction") exitWith { false };
if (isNull player) exitWith { false };

private _noChildren = { [] };
private _icon = "\a3\ui_f\data\igui\cfg\simpletasks\types\destroy_ca.paa";

if (!(missionNamespace getVariable ["COMSPEC_ChargeAceClassReady", false])) then {
    missionNamespace setVariable ["COMSPEC_ChargeAceClassReady", true, false];

    private _armAtak = [
        "COMSPEC_ArmAtak",
        "Uniquement depuis ATAK",
        _icon,
        {
            params ["_target", "_player"];
            [_target, _player] call comspec_overwatch_connect_fnc_chargeArmAtak;
        },
        {
            params ["_target", "_player"];
            (missionNamespace getVariable ["comspec_overwatch_enabled", true])
            && { !isNull _target }
            && { (_target getVariable ["ace_explosives_class", ""]) isNotEqualTo "" }
            && { (_player distance _target) < 4 }
        },
        _noChildren
    ] call ace_interact_menu_fnc_createAction;
    _armAtak = [_armAtak] call comspec_overwatch_connect_fnc_acePadAction;

    if (_armAtak isNotEqualTo [] && {isClass (configFile >> "CfgVehicles" >> "ACE_Explosives_Place")}) then {
        ["ACE_Explosives_Place", 0, ["ACE_MainActions", "ACE_SetTrigger"], _armAtak, true] call ace_interact_menu_fnc_addActionToClass;
    };

    private _modeAtak = [
        "COMSPEC_ModeAtak",
        "Uniquement depuis ATAK (jeu et poste)",
        _icon,
        {
            params ["_target", "_player"];
            [_target, "atak", _player] call comspec_overwatch_connect_fnc_chargeSetTrigger;
        },
        {
            params ["_target", "_player"];
            (missionNamespace getVariable ["comspec_overwatch_enabled", true])
            && { !isNull _target }
            && { (_target getVariable ["COMSPEC_chargeId", ""]) isNotEqualTo "" }
            && { !(_target getVariable ["COMSPEC_detonateFired", false]) }
            && { (toLower (_target getVariable ["COMSPEC_triggerKind", ""])) isNotEqualTo "timer" }
            && { (toLower (_target getVariable ["COMSPEC_triggerKind", ""])) isNotEqualTo "atak" }
            && {
                private _uid = _target getVariable ["COMSPEC_chargeOwnerUid", ""];
                (_uid isEqualTo "") || {_uid isEqualTo (getPlayerUID _player)}
            }
        },
        _noChildren
    ] call ace_interact_menu_fnc_createAction;
    _modeAtak = [_modeAtak] call comspec_overwatch_connect_fnc_acePadAction;

    private _modeLocal = [
        "COMSPEC_ModeLocal",
        "Déclencheur local (porté)",
        _icon,
        {
            params ["_target", "_player"];
            [_target, "clacker", _player] call comspec_overwatch_connect_fnc_chargeSetTrigger;
        },
        {
            params ["_target", "_player"];
            (missionNamespace getVariable ["comspec_overwatch_enabled", true])
            && { !isNull _target }
            && { (toLower (_target getVariable ["COMSPEC_triggerKind", ""])) isEqualTo "atak" }
            && { !(_target getVariable ["COMSPEC_detonateFired", false]) }
            && {
                private _uid = _target getVariable ["COMSPEC_chargeOwnerUid", ""];
                (_uid isEqualTo "") || {_uid isEqualTo (getPlayerUID _player)}
            }
        },
        _noChildren
    ] call ace_interact_menu_fnc_createAction;
    _modeLocal = [_modeLocal] call comspec_overwatch_connect_fnc_acePadAction;

    private _fireOne = [
        "COMSPEC_FireAtakCharge",
        "Déclencher via ATAK",
        _icon,
        {
            params ["_target", "_player"];
            private _cid = _target getVariable ["COMSPEC_chargeId", ""];
            private _pending = missionNamespace getVariable ["COMSPEC_ChargeConfirmId", ""];
            private _until = missionNamespace getVariable ["COMSPEC_ChargeConfirmUntil", -1e9];
            private _ok = (_pending isEqualTo _cid) && {diag_tickTime <= _until};
            [_cid, _ok] call comspec_overwatch_connect_fnc_chargeConfirmDetonate;
        },
        {
            params ["_target", "_player"];
            (missionNamespace getVariable ["comspec_overwatch_enabled", true])
            && { (toLower (_target getVariable ["COMSPEC_triggerKind", ""])) isEqualTo "atak" }
            && { !(_target getVariable ["COMSPEC_detonateFired", false]) }
            && { [_player] call comspec_overwatch_connect_fnc_hasTerminal }
            && {
                private _uid = _target getVariable ["COMSPEC_chargeOwnerUid", ""];
                (_uid isEqualTo "") || {_uid isEqualTo (getPlayerUID _player)}
            }
        },
        {
            params ["_target"];
            private _cid = _target getVariable ["COMSPEC_chargeId", ""];
            private _pending = missionNamespace getVariable ["COMSPEC_ChargeConfirmId", ""];
            private _until = missionNamespace getVariable ["COMSPEC_ChargeConfirmUntil", -1e9];
            if (_pending isEqualTo _cid && {diag_tickTime <= _until}) then {
                private _a = [
                    "COMSPEC_FireAtakChargeConfirm",
                    "Confirmer le déclenchement",
                    "\a3\ui_f\data\igui\cfg\simpletasks\types\destroy_ca.paa",
                    {
                        params ["_t"];
                        [(_t getVariable ["COMSPEC_chargeId", ""]), true] call comspec_overwatch_connect_fnc_chargeConfirmDetonate;
                    },
                    { true },
                    { [] }
                ] call ace_interact_menu_fnc_createAction;
                [[_a, [], _target]]
            } else {
                []
            };
        }
    ] call ace_interact_menu_fnc_createAction;
    _fireOne = [_fireOne] call comspec_overwatch_connect_fnc_acePadAction;

    {
        private _act = _x;
        if (_act isEqualTo []) then { continue };
        {
            [_x, 0, ["ACE_MainActions"], _act, true] call ace_interact_menu_fnc_addActionToClass;
        } forEach ["PipeBombBase", "TimeBombCore", "MineBase"];
    } forEach [_modeAtak, _modeLocal, _fireOne];
};

if (missionNamespace getVariable ["COMSPEC_ChargeAceSelfReady", false]) exitWith { true };
if (!(missionNamespace getVariable ["COMSPEC_ACEMenuReady", false])) exitWith { true };

missionNamespace setVariable ["COMSPEC_ChargeAceSelfReady", true, false];

private _root = [
    "COMSPEC_ChargesAtak",
    "Charges ATAK",
    _icon,
    {},
    {
        (missionNamespace getVariable ["comspec_overwatch_enabled", true])
        && { isClass (configFile >> "CfgPatches" >> "ace_explosives") }
    },
    {
        private _actions = [];
        private _noChildren = { [] };
        private _icon = "\a3\ui_f\data\igui\cfg\simpletasks\types\destroy_ca.paa";
        private _list = [player] call comspec_overwatch_connect_fnc_chargeOwnedAtak;
        private _pendingAll = (diag_tickTime <= (missionNamespace getVariable ["COMSPEC_ChargeConfirmAllUntil", -1e9]));
        private _pendingId = missionNamespace getVariable ["COMSPEC_ChargeConfirmId", ""];
        private _pendingUntil = missionNamespace getVariable ["COMSPEC_ChargeConfirmUntil", -1e9];

        if (_list isEqualTo []) then {
            private _empty = [
                "COMSPEC_ChargesAtakEmpty",
                "Aucune charge ATAK armée",
                "",
                {},
                { true },
                _noChildren
            ] call ace_interact_menu_fnc_createAction;
            _actions pushBack [_empty, [], player];
        } else {
            {
                _x params ["_cid", "_exp", "_label", "_grid"];
                private _needConfirm = (_pendingId isEqualTo _cid) && {diag_tickTime <= _pendingUntil};
                private _title = if (_needConfirm) then {
                    format ["Confirmer — %1 (%2)", _label, _grid]
                } else {
                    format ["Déclencher — %1 (%2)", _label, _grid]
                };
                private _a = [
                    format ["COMSPEC_DetonateAtak_%1", _cid],
                    _title,
                    _icon,
                    {
                        params ["", "", "_params"];
                        _params params ["_id", "_confirm"];
                        [_id, _confirm] call comspec_overwatch_connect_fnc_chargeConfirmDetonate;
                    },
                    { [player] call comspec_overwatch_connect_fnc_hasTerminal },
                    _noChildren,
                    [_cid, _needConfirm]
                ] call ace_interact_menu_fnc_createAction;
                _actions pushBack [_a, [], player];
            } forEach _list;

            private _allTitle = if (_pendingAll) then {
                "Confirmer : tout déclencher"
            } else {
                format ["Tout déclencher (%1)", count _list]
            };
            private _all = [
                "COMSPEC_DetonateAtakAll",
                _allTitle,
                _icon,
                {
                    params ["", "_player", "_confirmed"];
                    [_player, _confirmed] call comspec_overwatch_connect_fnc_chargeDetonateAll;
                },
                { [player] call comspec_overwatch_connect_fnc_hasTerminal },
                _noChildren,
                _pendingAll
            ] call ace_interact_menu_fnc_createAction;
            _actions pushBack [_all, [], player];
        };

        _actions
    }
] call ace_interact_menu_fnc_createAction;

[_root, ["ACE_SelfActions", "COMSPEC_Main"]] call comspec_overwatch_connect_fnc_aceAddSelfAction;

true
