/*
    Safe mode : variable mission/profile ou présence d’un marqueur logique.
*/
private _safe =
    missionNamespace getVariable ["COMSPEC_SAFE_MODE", false]
    || {profileNamespace getVariable ["COMSPEC_SAFE_MODE", false]}
    || {uiNamespace getVariable ["COMSPEC_SAFE_MODE", false]};

_safe
