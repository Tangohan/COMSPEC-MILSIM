/*
    Modules Zeus/Eden — exploitation SSE.

    Mêmes précautions que module_roleplay_zone.hpp :
    - ne PAS rouvrir Module_F:Logic (casse ACE Arsenal / ZEN),
    - Attributes / Arguments / ModuleDescription définis à plat.

    Trois modules, choisis pour ce qu'ils débloquent réellement en partie :
      · Dossier SSE actif      → classe automatiquement tout ce qui suit
      · Profil d'identité SSE  → décide de ce que la requête va rendre
      · Doter en terminal SEEK → rend le module utilisable sans redéploiement

    Inclus dans un seul class CfgVehicles (config.cpp) — ne pas rouvrir CfgVehicles ici.
*/

    class COMSPEC_Module_SSE_Base: Module_F
    {
        author = "COMSPEC";
        scope = 1;
        scopeCurator = 0;
        category = "COMSPEC_SSE";
        functionPriority = 1;
        isGlobal = 1;
        isTriggerActivated = 0;
        isDisposable = 1;
        is3DEN = 1;
        curatorCanAttach = 1;
        canSetArea = 0;
        canSetAreaHeight = 0;
        canSetAreaShape = 0;
        icon = "\A3\ui_f\data\igui\cfg\simpletasks\types\intel_ca.paa";
        portrait = "\A3\ui_f\data\igui\cfg\simpletasks\types\intel_ca.paa";
    };

    class COMSPEC_Module_SSE_Case: COMSPEC_Module_SSE_Base
    {
        scope = 2;
        scopeCurator = 0;
        displayName = "Dossier SSE actif";
        function = "comspec_overwatch_connect_fnc_moduleSseCase";

        class Attributes
        {
            class Reference
            {
                displayName = "Référence du dossier";
                tooltip = "Référence communiquée par le poste de commandement, par exemple SSE-2026-0007. Toutes les fiches transmises ensuite y seront classées.";
                property = "COMSPEC_SSE_Case_Reference";
                control = "Edit";
                expression = "_this setVariable ['Reference',_value,true];";
                defaultValue = "''";
                typeName = "STRING";
            };
        };

        class Arguments
        {
            class Reference
            {
                displayName = "Référence du dossier";
                description = "Dossier ouvert côté portail (ex. SSE-2026-0007)";
                typeName = "STRING";
                defaultValue = "";
            };
        };

        class ModuleDescription
        {
            description = "Impose le dossier SSE de rattachement pour tout l'élément. Le dossier doit exister côté portail ; ce module ne le crée pas.";
            position = 0;
            direction = 0;
            optional = 1;
            duplicate = 1;
        };
    };

    class COMSPEC_Module_SSE_Profile: COMSPEC_Module_SSE_Base
    {
        scope = 2;
        scopeCurator = 0;
        displayName = "Profil d'identité SSE";
        function = "comspec_overwatch_connect_fnc_moduleSseProfile";

        class Attributes
        {
            class Preset
            {
                displayName = "Ce que la base doit répondre";
                tooltip = "Génération automatique : le sujet reçoit un verdict stable dérivé de sa graine. Les trois autres imposent le résultat de la requête d'identité.";
                property = "COMSPEC_SSE_Profile_Preset";
                control = "Combo";
                expression = "_this setVariable ['Preset',_value,true];";
                defaultValue = "'auto'";
                typeName = "STRING";

                class values
                {
                    class Auto        { name = "Génération automatique (défaut)";        value = "auto"; default = 1; };
                    class Inconnu     { name = "Inconnu des bases";                      value = "inconnu"; };
                    class Signale     { name = "Signalé — correspondance partielle";     value = "signale"; };
                    class Recherche   { name = "Recherché — correspondance confirmée";   value = "recherche"; };
                };
            };

            class LastName
            {
                displayName = "Nom";
                tooltip = "Laisser vide pour laisser le terminal proposer un nom cohérent avec le sujet.";
                property = "COMSPEC_SSE_Profile_LastName";
                control = "Edit";
                expression = "_this setVariable ['LastName',_value,true];";
                defaultValue = "''";
                typeName = "STRING";
            };

            class FirstName
            {
                displayName = "Prénom";
                tooltip = "Laisser vide pour génération automatique.";
                property = "COMSPEC_SSE_Profile_FirstName";
                control = "Edit";
                expression = "_this setVariable ['FirstName',_value,true];";
                defaultValue = "''";
                typeName = "STRING";
            };

            class Alias
            {
                displayName = "Alias connu";
                tooltip = "Surnom sous lequel le sujet est connu. C'est souvent le seul élément dont dispose le terrain.";
                property = "COMSPEC_SSE_Profile_Alias";
                control = "Edit";
                expression = "_this setVariable ['Alias',_value,true];";
                defaultValue = "''";
                typeName = "STRING";
            };

            class Nationality
            {
                displayName = "Nationalité déclarée";
                tooltip = "Ce que le sujet déclare, pas ce qui est établi.";
                property = "COMSPEC_SSE_Profile_Nationality";
                control = "Edit";
                expression = "_this setVariable ['Nationality',_value,true];";
                defaultValue = "''";
                typeName = "STRING";
            };

            class Language
            {
                displayName = "Langue parlée";
                tooltip = "Détermine si un interprète est nécessaire pour l'entretien.";
                property = "COMSPEC_SSE_Profile_Language";
                control = "Edit";
                expression = "_this setVariable ['Language',_value,true];";
                defaultValue = "''";
                typeName = "STRING";
            };

            class RecordRef
            {
                displayName = "Référence de dossier antérieur";
                tooltip = "Affichée par le terminal en cas de correspondance. Laisser vide pour génération automatique.";
                property = "COMSPEC_SSE_Profile_RecordRef";
                control = "Edit";
                expression = "_this setVariable ['RecordRef',_value,true];";
                defaultValue = "''";
                typeName = "STRING";
            };

            class Seed
            {
                displayName = "Graine (0 = automatique)";
                tooltip = "Fixer la graine rend le sujet identique d'une session à l'autre — utile pour un scénario rejoué. 0 laisse dériver de l'identifiant réseau.";
                property = "COMSPEC_SSE_Profile_Seed";
                control = "Edit";
                expression = "_this setVariable ['Seed',_value,true];";
                defaultValue = "0";
                validate = "number";
                typeName = "NUMBER";
            };
        };

        class Arguments
        {
            class Preset
            {
                displayName = "Ce que la base doit répondre";
                description = "auto | inconnu | signale | recherche";
                typeName = "STRING";
                defaultValue = "auto";
            };
            class Alias
            {
                displayName = "Alias connu";
                description = "Surnom sous lequel le sujet est connu";
                typeName = "STRING";
                defaultValue = "";
            };
        };

        class ModuleDescription
        {
            description = "Décide de ce que le terminal SEEK trouvera sur ce sujet. Posez le module sur la personne, ou synchronisez-le avec plusieurs sujets.";
            position = 0;
            direction = 0;
            optional = 1;
            duplicate = 1;
            synced[] = {"AnyPerson"};
        };
    };

    class COMSPEC_Module_SSE_Equip: COMSPEC_Module_SSE_Base
    {
        scope = 2;
        scopeCurator = 0;
        displayName = "Doter en terminal SEEK";
        function = "comspec_overwatch_connect_fnc_moduleSseEquip";
        icon = "\A3\ui_f\data\igui\cfg\simpletasks\types\documents_ca.paa";
        portrait = "\A3\ui_f\data\igui\cfg\simpletasks\types\documents_ca.paa";

        class ModuleDescription
        {
            description = "Place le terminal biométrique SEEK dans l'équipement des joueurs désignés (sac, gilet puis uniforme). Sans terminal, aucune fiche n'est ouvrable.";
            position = 0;
            direction = 0;
            optional = 1;
            duplicate = 1;
            synced[] = {"AnyPerson"};
        };
    };

    class COMSPEC_Module_SSE_DocChrome: COMSPEC_Module_SSE_Base
    {
        scope = 2;
        scopeCurator = 0;
        displayName = "Présentation des documents SSE";
        function = "comspec_overwatch_connect_fnc_moduleSseDocChrome";
        icon = "\A3\ui_f\data\igui\cfg\simpletasks\types\documents_ca.paa";
        portrait = "\A3\ui_f\data\igui\cfg\simpletasks\types\documents_ca.paa";

        class Attributes
        {
            class Prefab
            {
                displayName = "Modèle préfait";
                tooltip = "Aspect et textes de base. Les champs ci-dessous remplacent le modèle s’ils sont remplis.";
                property = "COMSPEC_SSE_DocChrome_Prefab";
                control = "Combo";
                expression = "_this setVariable ['Prefab',_value,true];";
                defaultValue = "'standard_restreint'";
                typeName = "STRING";
                class values
                {
                    class Standard { name = "Standard — diffusion restreinte"; value = "standard_restreint"; default = 1; };
                    class Terrain { name = "Terrain — feuille tachée"; value = "terrain_tache"; };
                    class Brouillon { name = "Brouillon — papier froissé"; value = "brouillon_froisse"; };
                    class Archives { name = "Archives — papier jauni"; value = "bureau_jauni"; };
                    class Formel { name = "Bureau — papier impeccable"; value = "formel_propre"; };
                };
            };
            class PaperStyle
            {
                displayName = "Aspect du papier (optionnel)";
                tooltip = "Laisser « Selon le modèle » pour garder l’aspect du modèle choisi.";
                property = "COMSPEC_SSE_DocChrome_PaperStyle";
                control = "Combo";
                expression = "_this setVariable ['PaperStyle',_value,true];";
                defaultValue = "''";
                typeName = "STRING";
                class values
                {
                    class Keep { name = "Selon le modèle"; value = ""; default = 1; };
                    class Clean { name = "Papier propre (bureau)"; value = "clean"; };
                    class Stained { name = "Papier taché (terrain)"; value = "stained"; };
                    class Crumpled { name = "Papier froissé"; value = "crumpled"; };
                    class Aged { name = "Papier jauni / usé"; value = "aged"; };
                };
            };
            class Banner
            {
                displayName = "Bandeau (optionnel)";
                tooltip = "Texte du bandeau rouge en tête de feuille. Vide = texte du modèle.";
                property = "COMSPEC_SSE_DocChrome_Banner";
                control = "Edit";
                expression = "_this setVariable ['Banner',_value,true];";
                defaultValue = "''";
                typeName = "STRING";
            };
            class TitlePerson
            {
                displayName = "Titre fiche personne (optionnel)";
                property = "COMSPEC_SSE_DocChrome_TitlePerson";
                control = "Edit";
                expression = "_this setVariable ['TitlePerson',_value,true];";
                defaultValue = "''";
                typeName = "STRING";
            };
            class TitleDocs
            {
                displayName = "Titre dossier documentaire (optionnel)";
                property = "COMSPEC_SSE_DocChrome_TitleDocs";
                control = "Edit";
                expression = "_this setVariable ['TitleDocs',_value,true];";
                defaultValue = "''";
                typeName = "STRING";
            };
            class SubtitleDossier
            {
                displayName = "Sous-titre compte rendu (optionnel)";
                property = "COMSPEC_SSE_DocChrome_SubtitleDossier";
                control = "Edit";
                expression = "_this setVariable ['SubtitleDossier',_value,true];";
                defaultValue = "''";
                typeName = "STRING";
            };
            class Footer
            {
                displayName = "Pied de page (optionnel)";
                tooltip = "Mention du type « Ne constitue pas une preuve… ». Vide = texte du modèle.";
                property = "COMSPEC_SSE_DocChrome_Footer";
                control = "Edit";
                expression = "_this setVariable ['Footer',_value,true];";
                defaultValue = "''";
                typeName = "STRING";
            };
            class BtnConsult
            {
                displayName = "Libellé bouton consultation (optionnel)";
                property = "COMSPEC_SSE_DocChrome_BtnConsult";
                control = "Edit";
                expression = "_this setVariable ['BtnConsult',_value,true];";
                defaultValue = "''";
                typeName = "STRING";
            };
            class BtnTransmit
            {
                displayName = "Libellé bouton transmettre (optionnel)";
                property = "COMSPEC_SSE_DocChrome_BtnTransmit";
                control = "Edit";
                expression = "_this setVariable ['BtnTransmit',_value,true];";
                defaultValue = "''";
                typeName = "STRING";
            };
            class BtnClose
            {
                displayName = "Libellé bouton fermer (optionnel)";
                property = "COMSPEC_SSE_DocChrome_BtnClose";
                control = "Edit";
                expression = "_this setVariable ['BtnClose',_value,true];";
                defaultValue = "''";
                typeName = "STRING";
            };
        };

        class Arguments
        {
            class Prefab
            {
                displayName = "Modèle préfait";
                description = "Aspect et textes de base";
                typeName = "STRING";
                class values
                {
                    class Standard { name = "Standard — diffusion restreinte"; value = "standard_restreint"; default = 1; };
                    class Terrain { name = "Terrain — feuille tachée"; value = "terrain_tache"; };
                    class Brouillon { name = "Brouillon — papier froissé"; value = "brouillon_froisse"; };
                    class Archives { name = "Archives — papier jauni"; value = "bureau_jauni"; };
                    class Formel { name = "Bureau — papier impeccable"; value = "formel_propre"; };
                };
            };
            class Footer
            {
                displayName = "Pied de page (optionnel)";
                description = "Mention RP / preuve";
                typeName = "STRING";
                defaultValue = "";
            };
        };

        class ModuleDescription
        {
            description = "Personnalise l’apparence des fiches SSE en jeu : modèle de papier, bandeau, titres et mention de pied de page. Un seul module suffit pour toute la mission.";
            position = 0;
            direction = 0;
            optional = 1;
            duplicate = 0;
        };
    };
