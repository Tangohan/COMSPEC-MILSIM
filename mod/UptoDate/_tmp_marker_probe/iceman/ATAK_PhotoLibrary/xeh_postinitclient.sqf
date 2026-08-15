if (!hasInterface) exitWith {};

[] spawn {
    waitUntil {
        uiSleep 0.1;
        !(isNil "CBA_fnc_addEventHandler")
        && {!(isNil "Iceman_fnc_photo_capture")}
        && {!(isNil "Iceman_fnc_photo_receive")}
    };

    ["bce_took_screenshot", {
        _this call Iceman_fnc_photo_capture;
    }] call CBA_fnc_addEventHandler;

    ["Iceman_PhotoLibrary_receive", {
        _this call Iceman_fnc_photo_receive;
    }] call CBA_fnc_addEventHandler;

    [{
        private _cam = uiNamespace getVariable ["Iceman_PhotoLibrary_previewCamera", objNull];
        if (isNull _cam) exitWith {};

        private _group = uiNamespace getVariable ["Iceman_ATAK_PhotoLibrary_group", controlNull];
        if (isNull _group || {!ctrlShown _group}) then {
            call Iceman_fnc_photo_cleanupPreview;
        };
    }, 1] call CBA_fnc_addPerFrameHandler;
};
