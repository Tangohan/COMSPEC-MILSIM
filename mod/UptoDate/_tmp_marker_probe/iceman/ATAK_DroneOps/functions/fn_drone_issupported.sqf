params [["_drone", objNull]];

if (isNull _drone || {!alive _drone}) exitWith {false};

private _type = toLower typeOf _drone;
private _display = toLower getText (configOf _drone >> "displayName");

(_drone isKindOf "UAV_01_base_F") ||
{_type in ["b_uav_01_f", "o_uav_01_f", "i_uav_01_f", "b_uav_01_backpack_f", "o_uav_01_backpack_f", "i_uav_01_backpack_f"]} ||
{((_display find "black") > -1) && {(_display find "hornet") > -1}} ||
{((_type find "blackhornet") > -1) || {(_type find "black_hornet") > -1} || {(_type find "bhornet") > -1}} ||
{((_display find "ar-2") > -1) || {(_display find "ar 2") > -1} || {(_display find "darter") > -1}}
