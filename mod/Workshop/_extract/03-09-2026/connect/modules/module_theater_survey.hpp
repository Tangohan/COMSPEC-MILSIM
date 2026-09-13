/*
    Module Eden : relevé complet du théâtre (bâtiments, forêts, relief).

    Zeus passe par Zeus Enhanced (scopeCurator = 0), comme les modules roleplay / SSE.
    Inclus dans un seul class CfgVehicles (config.cpp).
*/

    class COMSPEC_Module_TheaterSurvey: Module_F
    {
        author = "COMSPEC";
        scope = 2;
        scopeCurator = 0;
        category = "COMSPEC_Outils";
        displayName = "Relever la carte du théâtre";
        function = "comspec_overwatch_connect_fnc_moduleTheaterSurvey";
        functionPriority = 1;
        isGlobal = 1;
        isTriggerActivated = 0;
        isDisposable = 1;
        is3DEN = 1;
        curatorCanAttach = 0;
        canSetArea = 0;
        canSetAreaHeight = 0;
        canSetAreaShape = 0;
        icon = "\A3\ui_f\data\igui\cfg\simpletasks\types\map_ca.paa";
        portrait = "\A3\ui_f\data\igui\cfg\simpletasks\types\map_ca.paa";

        class Attributes
        {
            class RunAtStart
            {
                displayName = "Au début de la mission";
                tooltip = "Choisir si le relevé complet part tout seul au lancement, ou seulement depuis Zeus et cette fenêtre.";
                property = "COMSPEC_TheaterSurvey_RunAtStart";
                control = "Combo";
                expression = "_this setVariable ['RunAtStart',_value,true];";
                defaultValue = "'manual'";
                typeName = "STRING";

                class Values
                {
                    class Manual { name = "Uniquement depuis Zeus ou la fenêtre de relevé"; value = "manual"; default = 1; };
                    class Start { name = "Lancer au début de la mission (Zeus / chef de mission)"; value = "start"; };
                };
            };
        };

        class Arguments
        {
            class RunAtStart
            {
                displayName = "Au début de la mission";
                description = "Lancer le relevé au début de partie, ou seulement à la demande.";
                typeName = "STRING";
                defaultValue = "manual";
                class values
                {
                    class Manual { name = "Uniquement depuis Zeus ou la fenêtre de relevé"; value = "manual"; default = 1; };
                    class Start { name = "Lancer au début de la mission (Zeus / chef de mission)"; value = "start"; };
                };
            };
        };

        class ModuleDescription
        {
            description = "Parcourt tout le théâtre : bâtiments, forêts et relief. Une fenêtre indique la durée, le nombre d’éléments collectés et le secteur en cours. Athena doit être liée. Le parcours est découpé pour ne pas figer Zeus.";
            position = 0;
            direction = 0;
            optional = 1;
            duplicate = 0;
        };
    };
