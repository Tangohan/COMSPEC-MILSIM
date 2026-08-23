/*
    Attributs Eden — exploitation SSE, directement sur l'unité.

    Le module « Profil d'identité SSE » sert à Zeus, en cours de partie. En
    préparation de mission, poser un module par PNJ est intenable : un chef de
    mission qui place trente civils veut cocher le sujet intéressant dans ses
    attributs, pas gérer trente logiques.

    Ces attributs écrivent exactement les mêmes variables que le module — un seul
    contrat, deux façons de le renseigner.

    `condition = "objectBrain"` : la catégorie n'apparaît que sur ce qui a un
    cerveau, donc pas sur un mur ou une caisse.
*/

class Cfg3DEN
{
    class Object
    {
        class AttributeCategories
        {
            class COMSPEC_Overwatch_Seek
            {
                displayName = "COMSPEC — Exploitation SSE";
                collapsed = 1;
                condition = "objectBrain";

                class Attributes
                {
                    class COMSPEC_SSE_Profile_Preset
                    {
                        displayName = "Ce que la base doit répondre";
                        tooltip = "Génération automatique : verdict stable dérivé de la graine du sujet. Les trois autres imposent le résultat de la requête d'identité du terminal SEEK.";
                        property = "COMSPEC_SSE_Profile_Preset";
                        control = "Combo";
                        expression = "if (_value != 'auto') then { [_this, ([_value] call comspec_overwatch_connect_fnc_sseProfilePreset)] call comspec_overwatch_connect_fnc_sseApplyProfile; };";
                        defaultValue = "'auto'";
                        typeName = "STRING";

                        class values
                        {
                            class Auto      { name = "Génération automatique (défaut)";      value = "auto"; default = 1; };
                            class Inconnu   { name = "Inconnu des bases";                    value = "inconnu"; };
                            class Signale   { name = "Signalé — correspondance partielle";   value = "signale"; };
                            class Recherche { name = "Recherché — correspondance confirmée"; value = "recherche"; };
                        };
                    };

                    class COMSPEC_SSE_LastName
                    {
                        displayName = "Nom";
                        tooltip = "Si renseigné, SEEK et les fiches utilisent ce nom. Vide = nom de l’identité Eden (panneau Identité), sinon génération automatique.";
                        property = "COMSPEC_SSE_LastName";
                        control = "Edit";
                        expression = "if (_value != '') then { _this setVariable ['COMSPEC_SSE_LastName', _value, true]; _this setVariable ['COMSPEC_SSE_NameAuthored', true, true]; };";
                        defaultValue = "''";
                        typeName = "STRING";
                    };

                    class COMSPEC_SSE_FirstName
                    {
                        displayName = "Prénom";
                        tooltip = "Si renseigné, SEEK et les fiches utilisent ce prénom. Vide = prénom de l’identité Eden, sinon génération automatique.";
                        property = "COMSPEC_SSE_FirstName";
                        control = "Edit";
                        expression = "if (_value != '') then { _this setVariable ['COMSPEC_SSE_FirstName', _value, true]; _this setVariable ['COMSPEC_SSE_NameAuthored', true, true]; };";
                        defaultValue = "''";
                        typeName = "STRING";
                    };

                    class COMSPEC_SSE_Alias
                    {
                        displayName = "Alias connu";
                        tooltip = "Surnom sous lequel le sujet est connu. C'est souvent le seul élément dont dispose le terrain.";
                        property = "COMSPEC_SSE_Alias";
                        control = "Edit";
                        expression = "if (_value != '') then { _this setVariable ['COMSPEC_SSE_Alias', _value, true]; };";
                        defaultValue = "''";
                        typeName = "STRING";
                    };

                    class COMSPEC_SSE_Nationality
                    {
                        displayName = "Nationalité déclarée";
                        tooltip = "Ce que le sujet déclare, pas ce qui est établi.";
                        property = "COMSPEC_SSE_Nationality";
                        control = "Edit";
                        expression = "if (_value != '') then { _this setVariable ['COMSPEC_SSE_Nationality', _value, true]; };";
                        defaultValue = "''";
                        typeName = "STRING";
                    };

                    class COMSPEC_SSE_Language
                    {
                        displayName = "Langue parlée";
                        tooltip = "Détermine si un interprète est nécessaire pour l'entretien.";
                        property = "COMSPEC_SSE_Language";
                        control = "Edit";
                        expression = "if (_value != '') then { _this setVariable ['COMSPEC_SSE_Language', _value, true]; };";
                        defaultValue = "''";
                        typeName = "STRING";
                    };

                    class COMSPEC_SSE_RecordRef
                    {
                        displayName = "Référence de dossier antérieur";
                        tooltip = "Affichée par le terminal en cas de correspondance. Laisser vide pour génération automatique.";
                        property = "COMSPEC_SSE_RecordRef";
                        control = "Edit";
                        expression = "if (_value != '') then { _this setVariable ['COMSPEC_SSE_RecordRef', _value, true]; };";
                        defaultValue = "''";
                        typeName = "STRING";
                    };

                    class COMSPEC_SSE_Confidence
                    {
                        displayName = "Indice de confiance imposé (-1 = automatique)";
                        tooltip = "Pourcentage affiché par le terminal après requête. -1 laisse le calcul dépendre de la qualité réelle des relevés, ce qui est le comportement souhaitable dans la plupart des scénarios.";
                        property = "COMSPEC_SSE_Confidence";
                        control = "Edit";
                        expression = "if (_value >= 0) then { _this setVariable ['COMSPEC_SSE_Confidence', ((round _value) max 0) min 100, true]; };";
                        defaultValue = "-1";
                        validate = "number";
                        typeName = "NUMBER";
                    };

                    class COMSPEC_SSE_Seed
                    {
                        displayName = "Graine (0 = automatique)";
                        tooltip = "Fixer la graine rend le sujet identique d'une session à l'autre — utile pour un scénario rejoué ou une séance de formation. 0 laisse dériver de l'identifiant réseau.";
                        property = "COMSPEC_SSE_Seed";
                        control = "Edit";
                        expression = "if (_value > 0) then { _this setVariable ['COMSPEC_SSE_Seed', round _value, true]; };";
                        defaultValue = "0";
                        validate = "number";
                        typeName = "NUMBER";
                    };
                };
            };
        };
    };
};
