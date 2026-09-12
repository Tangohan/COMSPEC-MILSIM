/*
    True si la simulation de liaison dégradée doit s’appliquer
    (réglage dédié, ou roleplay + simulations réseau).
*/
if (missionNamespace getVariable ["comspec_overwatch_link_degrade_sim", false]) exitWith { true };
if (!(missionNamespace getVariable ["comspec_overwatch_roleplay_enabled", false])) exitWith { false };
missionNamespace getVariable ["comspec_overwatch_roleplay_network_failures", false]
