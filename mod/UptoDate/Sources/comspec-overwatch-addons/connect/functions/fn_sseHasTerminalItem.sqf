/*
    Le joueur porte-t-il le terminal SEEK ?
    Le réglage « comspec_sse_require_item » permet aux communautés qui n’équipent pas
    l’objet de conserver l’accès historique (menu ATAK sans objet).

    Returns: Bool
*/
if (!hasInterface) exitWith { false };
if (!(missionNamespace getVariable ["comspec_sse_require_item", true])) exitWith { true };

private _all = (items player) + (assignedItems player);
if ("COMSPEC_Item_SeekTerminal" in _all) exitWith { true };

// Sacs / gilets : « items » couvre uniforme, gilet et sac, mais on reste tolérant
// si un script tiers a rangé l’objet autrement.
if ("COMSPEC_Item_SeekTerminal" in ((uniformItems player) + (vestItems player) + (backpackItems player))) exitWith { true };

false
