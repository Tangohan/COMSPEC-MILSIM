/*
    Réglage CBA côté joueur changé depuis le téléphone : gardé d'une partie à l'autre, sans écriture immédiate du profil.
    Le 5e paramètre de CBA_settings_fnc_set range le réglage dans le profil puis lance saveProfileNamespace à chaque appel
    (jusqu'à trois par toucher pour les apps épinglées) : sur certains PC cette écriture fige l'image (fn_saveProfile).
    Ici le réglage est posé par CBA (même validation, même rappel du réglage), rangé dans la table CBA du profil comme
    CBA le fait (retiré s'il revient à sa valeur par défaut), puis l'écriture passe par fn_saveProfile : regroupée, et
    forcée en fin de mission.
    [réglage, valeur] call comspec_atak_native_fnc_setSetting;  -> true si le réglage a changé
*/
params ["_k", "_v"];
if !([_k, _v, 0, "client"] call CBA_settings_fnc_set) exitWith { false };
// Réglage imposé par le fichier userconfig de CBA : jamais rangé dans le profil (même règle que CBA).
if (!isNil { (missionNamespace getVariable ["cba_settings_userconfig", locationNull]) getVariable _k }) exitWith { true };
private _hash = profileNamespace getVariable ["cba_settings_hash", [] call CBA_fnc_hashCreate];
if (_v isEqualTo ([_k, "default"] call CBA_settings_fnc_get)) then {
    [_hash, toLower _k] call CBA_fnc_hashRem;
} else {
    [_hash, toLower _k, [_v, 0]] call CBA_fnc_hashSet;
};
profileNamespace setVariable ["cba_settings_hash", _hash];
[] call comspec_atak_native_fnc_saveProfile;
true
