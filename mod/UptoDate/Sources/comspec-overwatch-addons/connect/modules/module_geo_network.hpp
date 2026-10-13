/*
    Module Eden : relevé des routes et des villes seulement (fn_sampleGeoNetwork), sans bâtiments ni relief.
    Zeus : module « Relever routes et villes » par Zeus Enhanced / ACE Zeus (fn_registerZenTheaterSurvey).
    Inclus dans le seul class CfgVehicles (config.cpp).
*/

    class COMSPEC_Module_GeoNetwork: Module_F
    {
        author = "COMSPEC";
        scope = 2;
        scopeCurator = 0;
        category = "COMSPEC_Outils";
        displayName = "Relever routes et villes";
        function = "comspec_overwatch_connect_fnc_moduleGeoNetwork";
        functionPriority = 1;
        isGlobal = 1;
        isTriggerActivated = 0;
        isDisposable = 1;
        is3DEN = 0;
        curatorCanAttach = 0;
        canSetArea = 0;
        canSetAreaHeight = 0;
        canSetAreaShape = 0;
        icon = "\A3\ui_f\data\igui\cfg\simpletasks\types\move_ca.paa";
        portrait = "\A3\ui_f\data\igui\cfg\simpletasks\types\move_ca.paa";

        class Attributes
        {
            class RunAtStart
            {
                displayName = "Au début de la mission";
                tooltip = "Envoyer routes et villes à Athena au lancement (par le Zeus ou le chef de mission), ou seulement depuis Zeus.";
                property = "COMSPEC_GeoNetwork_RunAtStart";
                control = "Combo";
                expression = "_this setVariable ['RunAtStart',_value,true];";
                defaultValue = "'start'";
                typeName = "STRING";

                class Values
                {
                    class Start { name = "Envoyer au début de la mission"; value = "start"; default = 1; };
                    class Manual { name = "Uniquement depuis Zeus"; value = "manual"; };
                };
            };
            class Resend
            {
                displayName = "Renvoyer";
                tooltip = "Renvoyer même si routes et villes de cette carte ont déjà été transmises.";
                property = "COMSPEC_GeoNetwork_Resend";
                control = "Checkbox";
                expression = "_this setVariable ['Resend',_value,true];";
                defaultValue = "false";
                typeName = "BOOL";
            };
        };

        class ModuleDescription
        {
            description = "Envoie à Athena les routes (autoroutes, routes, pistes) et les localités (villes, bourgs, villages, lieux-dits) de la carte, sans le relevé complet des bâtiments et du relief. Athena doit être liée ; le relevé attend la liaison jusqu'à 3 minutes. Une seule fois par carte, sauf « Renvoyer ».";
            position = 0;
            direction = 0;
            optional = 1;
            duplicate = 0;
        };
    };
