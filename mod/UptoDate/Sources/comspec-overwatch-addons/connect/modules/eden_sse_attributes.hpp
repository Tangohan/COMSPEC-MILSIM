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
                displayName = "COMSPEC — Suivi ATAK";
                collapsed = 1;
                condition = "objectBrain";

                class Attributes
                {
                    class COMSPEC_PhoneTrack
                    {
                        displayName = "Téléphone visible sur l’ATAK";
                        tooltip = "La position de cette personne apparaît sur la carte de commandement, comme si elle portait un téléphone. Par défaut, aucun détail (nom, grille, cap…) n’est publié : le Zeus choisit ensuite ce qui apparaît, en jeu.";
                        property = "COMSPEC_PhoneTrack";
                        control = "Checkbox";
                        expression = "[_this, _value] call comspec_overwatch_connect_fnc_setPhoneTrack;";
                        defaultValue = "false";
                        typeName = "BOOL";
                    };

                    class COMSPEC_PhoneCallsign
                    {
                        displayName = "Nom affiché sur la carte";
                        tooltip = "Laisser vide : le nom de la personne est utilisé. Renseignez un indicatif si plusieurs contacts ont le même nom.";
                        property = "COMSPEC_PhoneCallsign";
                        control = "Edit";
                        expression = "if (_value != '') then { _this setVariable ['COMSPEC_PhoneCallsign', _value, true]; };";
                        defaultValue = "''";
                        typeName = "STRING";
                    };

                    class COMSPEC_AllyTrack
                    {
                        displayName = "IA alliée visible sur l’ATAK";
                        tooltip = "Cette IA apparaît sur la carte de commandement comme une unité de terrain (pas comme un téléphone). Utile pour une section d’accompagnement, un QRF ou un élément ami. Sans effet sur un joueur : il est déjà suivi s’il a une tablette.";
                        property = "COMSPEC_AllyTrack";
                        control = "Checkbox";
                        expression = "[_this, _value] call comspec_overwatch_connect_fnc_setAllyTrack;";
                        defaultValue = "false";
                        typeName = "BOOL";
                    };

                    class COMSPEC_AllyCallsign
                    {
                        displayName = "Indicatif";
                        tooltip = "Nom court affiché sur la carte et dans les effectifs (ex. RAVEN). Laisser vide : le nom du groupe, sinon le nom de l’IA.";
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
                displayName = "COMSPEC — Balise GPS";
                collapsed = 1;
                condition = "objectVehicle";

                class Attributes
                {
                    class COMSPEC_GpsBeacon
                    {
                        displayName = "Balise GPS (suivi ATAK)";
                        tooltip = "Le véhicule apparaît sur la carte de commandement même sans joueur à bord (convoi IA, véhicule volé, balise posée par le chef de mission).";
                        property = "COMSPEC_GpsBeacon";
                        control = "Checkbox";
                        expression = "[_this, _value] call comspec_overwatch_connect_fnc_setGpsBeacon;";
                        defaultValue = "false";
                        typeName = "BOOL";
                    };

                    class COMSPEC_GpsCallsign
                    {
                        displayName = "Nom affiché sur la carte";
                        tooltip = "Laisser vide : le modèle du véhicule est utilisé. Utile pour distinguer deux véhicules du même type (ex. Convoi 1).";
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
