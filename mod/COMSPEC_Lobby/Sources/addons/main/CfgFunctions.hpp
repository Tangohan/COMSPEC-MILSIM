// Fonctions comspec_lobby_fnc_<nom> (fichiers functions\fn_<nom>.sqf). Le script de l'écran (fn_lobbyOpen.sqf) est lancé par le
// onLoad de l'écran, sans passer par CfgFunctions : il tourne avant toute mission.
class CfgFunctions {
    class comspec_lobby {
        class poste {
            file = "\z\comspec_lobby\addons\main\functions";
            class tagParse {};
            class eventSender {};
            class pinLoad {};
            class pinSave {};
            class serverCheck {};
            class serverSet {};
            class serverTick {};
            class clientCheck {};
            class clientResult {};
            class zenInit {};
        };
    };
};
