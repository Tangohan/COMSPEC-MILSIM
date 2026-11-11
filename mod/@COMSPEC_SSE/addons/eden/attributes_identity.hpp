/*
    Attributs Eden — papiers d'identité (addon identity).

    Variables écrites sur le SERVEUR seulement, sans diffusion : la nature des papiers (couverture, faux) et leur
    qualité font partie de la vérité, que les joueurs ne doivent pas pouvoir lire sur l'unité.
      COMSPEC_ID_Carry    auto | none | card | passport | license | card_license | card_passport | military
                          | <clé de CfgComspecDocuments> (id_card, press_card… extensions comprises) | <clé1>,<clé2>
                          (liste dynamique COMSPEC_SSE_DocTypeCombo, comspec_sse_fnc_edenDocTypes)
      COMSPEC_ID_Country  auto | ALT | TNA | LVN | MLD          COMSPEC_ID_Kind  civil | cover | forged
      COMSPEC_ID_Quality  0-100 (faux)          COMSPEC_ID_Profile  auto | civil | insurge | hvt | agent | policier (IA réalistes)
      COMSPEC_ID_Undercover  vrai : infiltré dès le début (couverture, fn_idCover)
      COMSPEC_ID_AiUid    identifiant Athena du lore de session (fn_loreUid), vide : règle automatique
    Automatique : les joueurs n'ont rien, les IA ont des papiers selon leur camp (réglages COMSPEC SSE › Identité).
*/
class COMSPEC_SSE_Papiers {
    displayName = "$STR_COMSPEC_SSE_CFG_0B5D97E4D8";
    collapsed = 1;
    class Attributes {
        class COMSPEC_ID_Carry {
            displayName = "$STR_COMSPEC_SSE_CFG_8E5DEC6D7C";
            tooltip = "$STR_COMSPEC_SSE_CFG_F85EC29CD3";
            property = "COMSPEC_ID_Carry";
            // Liste dynamique : alias historiques, puis chaque type du registre (extensions comprises) ; voir fn_edenDocTypes.
            control = "COMSPEC_SSE_DocTypeCombo";
            expression = "if (isServer && {_value isNotEqualTo 'auto'}) then { _this setVariable ['COMSPEC_ID_Carry', _value]; };";
            defaultValue = "'auto'";
            typeName = "STRING";
            condition = "objectBrain";
        };
        // Profil Athena (éditeur : liste de la communauté si la connexion Athena du menu COMSPEC SSE est ouverte, sinon la
        // dernière liste vue sur ce poste ; sans aucune liste : papiers générés et réglages ci-dessous).
        class COMSPEC_ID_AiProfile {
            displayName = "Profil Athena";
            tooltip = "Identité et papiers d'un profil IA du Bureau SSE. La liste vient de la connexion Athena (menu COMSPEC SSE) ou de la dernière liste vue sur ce poste. Aucune liste : Générer. Profil absent en jeu : papiers générés.";
            property = "COMSPEC_ID_AiProfile";
            control = "COMSPEC_SSE_AiProfileCombo";
            expression = "if (isServer && {_value isNotEqualTo ''}) then { _this setVariable ['COMSPEC_ID_AiProfile', _value]; };";
            defaultValue = "''";
            typeName = "STRING";
            condition = "objectBrain";
        };
        // Lore de session Athena : identifiant stable de la personne (fn_loreUid ; vide : règle automatique), publié par le
        // serveur sous comspec_sse_aiUid.
        class COMSPEC_ID_AiUid {
            displayName = "$STR_COMSPEC_SSE_LORE_AiUid";
            tooltip = "$STR_COMSPEC_SSE_LORE_AiUidTip";
            property = "COMSPEC_ID_AiUid";
            control = "Edit";
            expression = "if (isServer && {(trim _value) isNotEqualTo ''}) then { _this setVariable ['COMSPEC_ID_AiUid', trim _value]; };";
            defaultValue = "''";
            typeName = "STRING";
            condition = "objectBrain";
        };
        class COMSPEC_ID_Behaviour {
            displayName = "Comportement au contrôle";
            tooltip = "Réponse de l'IA à « Papiers, s'il vous plaît ». Automatique : selon le contexte (IA réalistes).";
            property = "COMSPEC_ID_Behaviour";
            control = "Combo";
            expression = "if (isServer && {_value isNotEqualTo 'auto'}) then { _this setVariable ['COMSPEC_ID_Behaviour', _value]; };";
            defaultValue = "'auto'";
            typeName = "STRING";
            condition = "objectBrain";
            class values {
                class Auto { name = "Automatique"; value = "auto"; default = 1; };
                class Coopere { name = "Coopère"; value = "coopere"; };
                class Nerveux { name = "Nerveux"; value = "nerveux"; };
                class Ment { name = "Ment avec aplomb"; value = "ment"; };
                class Refuse { name = "Refuse"; value = "refuse"; };
                class Fuit { name = "S'enfuit"; value = "fuit"; };
                class Agressif { name = "Devient agressif"; value = "agressif"; };
                class Rendu { name = "Se rend"; value = "rendu"; };
            };
        };
        class COMSPEC_ID_Country {
            displayName = "$STR_COMSPEC_SSE_CFG_6A56979958";
            tooltip = "$STR_COMSPEC_SSE_CFG_3DDBED6623";
            property = "COMSPEC_ID_Country";
            control = "Combo";
            expression = "if (isServer && {_value isNotEqualTo 'auto'}) then { _this setVariable ['COMSPEC_ID_Country', _value]; };";
            defaultValue = "'auto'";
            typeName = "STRING";
            condition = "objectBrain";
            class values {
                class Auto { name = "$STR_COMSPEC_SSE_CFG_F8A3C37BEB"; value = "auto"; default = 1; };
                class ALT { name = "$STR_COMSPEC_SSE_CFG_4FFC3A9C10"; value = "ALT"; };
                class TNA { name = "$STR_COMSPEC_SSE_CFG_7336D48B54"; value = "TNA"; };
                class LVN { name = "$STR_COMSPEC_SSE_CFG_300D164467"; value = "LVN"; };
                class MLD { name = "$STR_COMSPEC_SSE_CFG_73B65B11ED"; value = "MLD"; };
            };
        };
        class COMSPEC_ID_Kind {
            displayName = "$STR_COMSPEC_SSE_CFG_BD419A13FB";
            tooltip = "$STR_COMSPEC_SSE_CFG_26DEC05427";
            property = "COMSPEC_ID_Kind";
            control = "Combo";
            expression = "if (isServer && {_value isNotEqualTo 'civil'}) then { _this setVariable ['COMSPEC_ID_Kind', _value]; };";
            defaultValue = "'civil'";
            typeName = "STRING";
            condition = "objectBrain";
            class values {
                class Civil { name = "$STR_COMSPEC_SSE_CFG_45FF8F0E18"; value = "civil"; default = 1; };
                class Cover { name = "$STR_COMSPEC_SSE_CFG_95F9CC59DC"; value = "cover"; };
                class Forged { name = "$STR_COMSPEC_SSE_CFG_9A36AFA781"; value = "forged"; };
            };
        };
        // IA réalistes (Lot 3) : profil au contrôle, vérité serveur (jamais diffusée).
        class COMSPEC_ID_Profile {
            displayName = "$STR_COMSPEC_SSE_CFG_EDA7AF9B0E";
            tooltip = "$STR_COMSPEC_SSE_CFG_03E9E5EBB4";
            property = "COMSPEC_ID_Profile";
            control = "Combo";
            expression = "if (isServer && {_value isNotEqualTo 'auto'}) then { _this setVariable ['COMSPEC_ID_Profile', _value]; };";
            defaultValue = "'auto'";
            typeName = "STRING";
            condition = "objectBrain";
            class values {
                class Auto { name = "$STR_COMSPEC_SSE_CFG_F8A3C37BEB"; value = "auto"; default = 1; };
                class Civil { name = "$STR_COMSPEC_SSE_CFG_496B2073F0"; value = "civil"; };
                class Insurge { name = "$STR_COMSPEC_SSE_CFG_DA14187553"; value = "insurge"; };
                class Hvt { name = "$STR_COMSPEC_SSE_CFG_1743A99AA7"; value = "hvt"; };
                class Agent { name = "$STR_COMSPEC_SSE_CFG_86DAFE2F48"; value = "agent"; };
                class Policier { name = "$STR_COMSPEC_SSE_CFG_6FE6600A2D"; value = "policier"; };
            };
        };
        class COMSPEC_ID_Quality {
            displayName = "$STR_COMSPEC_SSE_CFG_FA56F3752E";
            tooltip = "$STR_COMSPEC_SSE_CFG_3507EC6D79";
            property = "COMSPEC_ID_Quality";
            control = "Edit";
            expression = "if (isServer) then { _this setVariable ['COMSPEC_ID_Quality', (_value max 0) min 100]; };";
            defaultValue = "60";
            validate = "number";
            typeName = "NUMBER";
            condition = "objectBrain";
        };
        class COMSPEC_ID_Undercover {
            displayName = "$STR_COMSPEC_SSE_ID_Undercover";
            tooltip = "$STR_COMSPEC_SSE_ID_UndercoverTip";
            property = "COMSPEC_ID_Undercover";
            control = "Checkbox";
            expression = "if (isServer && {_value}) then { _this setVariable ['COMSPEC_ID_Undercover', true]; };";
            defaultValue = "false";
            typeName = "BOOL";
            condition = "objectBrain";
        };
    };
};
