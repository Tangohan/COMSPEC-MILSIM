/*
    Module Eden : afficher ou masquer les IA ennemies sur le poste ATAK.

    Zeus passe par Zeus Enhanced (scopeCurator = 0).
    Inclus dans un seul class CfgVehicles (config.cpp).
*/

    class COMSPEC_Module_AtakShowEnemyAi: Module_F
    {
        author = "COMSPEC";
        scope = 2;
        scopeCurator = 0;
        category = "COMSPEC_Outils";
        displayName = "Contacts ennemis sur l’ATAK";
        function = "comspec_overwatch_connect_fnc_moduleAtakShowEnemyAi";
        functionPriority = 1;
        isGlobal = 1;
        isTriggerActivated = 0;
        isDisposable = 1;
        is3DEN = 1;
        curatorCanAttach = 0;
        canSetArea = 0;
        canSetAreaHeight = 0;
        canSetAreaShape = 0;
        icon = "\A3\ui_f\data\igui\cfg\simpletasks\types\attack_ca.paa";
        portrait = "\A3\ui_f\data\igui\cfg\simpletasks\types\attack_ca.paa";

        class Attributes
        {
            class ShowAtStart
            {
                displayName = "Afficher les IA ennemies sur la carte";
                tooltip = "Par défaut, les contacts ennemis (losanges rouges) n’apparaissent pas sur le poste. Choisissez de les afficher dès le début, ou de les laisser masqués : Zeus pourra les afficher en cours de mission.";
                property = "COMSPEC_AtakShowEnemyAi_ShowAtStart";
                control = "Combo";
                expression = "_this setVariable ['ShowAtStart',_value,true];";
                defaultValue = "'hidden'";
                typeName = "STRING";

                class Values
                {
                    class Hidden { name = "Masqués (défaut)"; value = "hidden"; default = 1; };
                    class Show { name = "Afficher dès le début de mission"; value = "show"; };
                };
            };
        };

        class Arguments
        {
            class ShowAtStart
            {
                displayName = "Afficher les IA ennemies sur la carte";
                description = "Masqués par défaut, ou visibles dès le début. Zeus peut changer ce choix en cours de mission.";
                typeName = "STRING";
                defaultValue = "hidden";
                class values
                {
                    class Hidden { name = "Masqués (défaut)"; value = "hidden"; default = 1; };
                    class Show { name = "Afficher dès le début de mission"; value = "show"; };
                };
            };
        };

        class ModuleDescription
        {
            description = "Les contacts ennemis n’apparaissent pas sur le poste tant que ce n’est pas demandé. Posez ce module pour les afficher dès le début, ou laissez Zeus le faire en cours de mission.";
            position = 0;
            direction = 0;
            optional = 1;
            duplicate = 0;
        };
    };
