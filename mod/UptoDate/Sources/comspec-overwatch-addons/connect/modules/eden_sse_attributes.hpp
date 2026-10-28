/*
    Attributs Eden d'Overwatch : suivi ATAK et balise GPS.

    Les attributs SSE (identité, verdict de requête, biométrie) sont dans le pack
    COMSPEC SSE, catégorie « COMSPEC SSE — Renseignement » : une seule catégorie,
    plus de doublon « COMSPEC — Exploitation SSE ».

    Historique : attributs Eden — exploitation SSE, directement sur l'unité.

    Le module « Profil d'identité SSE » sert à Zeus, en cours de partie. En
    préparation de mission, poser un module par PNJ est intenable : un chef de
    mission qui place trente civils veut cocher le sujet intéressant dans ses
    attributs, pas gérer trente logiques.

    Ces attributs passent par sseEdenWriteField, comme le module « Profil
    d’identité SSE » : un seul contrat (variables COMSPEC_SSE_*), deux façons
    de le renseigner.

    `condition = "objectBrain"` : la catégorie n'apparaît que sur ce qui a un
    cerveau, donc pas sur un mur ou une caisse.
*/

class Cfg3DEN
{
    class Object
    {
        class AttributeCategories
        {
            class COMSPEC_Overwatch_PhoneTrack
            {
                displayName = "$STR_COMSPEC_OW_CFG_C49FBE77F5";
                collapsed = 1;
                condition = "objectBrain";

                class Attributes
                {
                    class COMSPEC_PhoneTrack
                    {
                        displayName = "$STR_COMSPEC_OW_CFG_CCCD375186";
                        tooltip = "$STR_COMSPEC_OW_CFG_B84B3FD620";
                        property = "COMSPEC_PhoneTrack";
                        control = "Checkbox";
                        expression = "[_this, _value] call comspec_overwatch_connect_fnc_setPhoneTrack;";
                        defaultValue = "false";
                        typeName = "BOOL";
                    };

                    class COMSPEC_PhoneCallsign
                    {
                        displayName = "$STR_COMSPEC_OW_CFG_3EB5598FA5";
                        tooltip = "$STR_COMSPEC_OW_CFG_C6E6AC49A8";
                        property = "COMSPEC_PhoneCallsign";
                        control = "Edit";
                        expression = "if (_value != '') then { _this setVariable ['COMSPEC_PhoneCallsign', _value, true]; };";
                        defaultValue = "''";
                        typeName = "STRING";
                    };

                    class COMSPEC_AllyTrack
                    {
                        displayName = "$STR_COMSPEC_OW_CFG_05CF37BCCA";
                        tooltip = "$STR_COMSPEC_OW_CFG_D87EDC6B9C";
                        property = "COMSPEC_AllyTrack";
                        control = "Checkbox";
                        expression = "[_this, _value] call comspec_overwatch_connect_fnc_setAllyTrack;";
                        defaultValue = "false";
                        typeName = "BOOL";
                    };

                    class COMSPEC_AllyCallsign
                    {
                        displayName = "$STR_COMSPEC_OW_CFG_E1829FA177";
                        tooltip = "$STR_COMSPEC_OW_CFG_73B225B539";
                        property = "COMSPEC_AllyCallsign";
                        control = "Edit";
                        expression = "if (_value != '') then { _this setVariable ['COMSPEC_AllyCallsign', _value, true]; };";
                        defaultValue = "''";
                        typeName = "STRING";
                    };
                };
            };

            class COMSPEC_Overwatch_GpsBeacon
            {
                displayName = "$STR_COMSPEC_OW_CFG_C032C6FDD9";
                collapsed = 1;
                condition = "objectVehicle";

                class Attributes
                {
                    class COMSPEC_GpsBeacon
                    {
                        displayName = "$STR_COMSPEC_OW_CFG_6525C816AC";
                        tooltip = "$STR_COMSPEC_OW_CFG_91EE3FBA4C";
                        property = "COMSPEC_GpsBeacon";
                        control = "Checkbox";
                        expression = "[_this, _value] call comspec_overwatch_connect_fnc_setGpsBeacon;";
                        defaultValue = "false";
                        typeName = "BOOL";
                    };

                    class COMSPEC_GpsCallsign
                    {
                        displayName = "$STR_COMSPEC_OW_CFG_3EB5598FA5";
                        tooltip = "$STR_COMSPEC_OW_CFG_88F89B739E";
                        property = "COMSPEC_GpsCallsign";
                        control = "Edit";
                        expression = "if (_value != '') then { _this setVariable ['COMSPEC_GpsCallsign', _value, true]; };";
                        defaultValue = "''";
                        typeName = "STRING";
                    };
                };
            };
        };
    };
};
