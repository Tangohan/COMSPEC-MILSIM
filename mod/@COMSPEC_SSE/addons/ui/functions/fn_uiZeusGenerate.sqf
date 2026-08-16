if (!isNil "comspec_sse_fnc_applyDataset") then {
    ["falcon", player, 50, 1] call comspec_sse_fnc_applyDataset;
} else {
    if (!isNil "comspec_sse_fnc_generateFromBrief") then {
        ["cellule logistique de 5 personnes", player, 40] call comspec_sse_fnc_generateFromBrief;
    };
};
["zeus"] call comspec_sse_fnc_uiRefresh;
true
