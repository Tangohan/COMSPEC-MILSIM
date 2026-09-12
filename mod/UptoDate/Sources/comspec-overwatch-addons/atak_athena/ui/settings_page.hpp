// App « Paramètres » — identité, rôle, carte, équipe, groupe, alerte, liaison au poste.
// Libellés plus hauts, gaps aérés, scroll avec marge basse au-dessus de Retour.
#ifndef QUOTE
    #define QUOTE(var1) #var1
#endif

#ifndef COMSPEC_SET_PHONE_W
    #define COMSPEC_SET_PHONE_W (safezoneW * 0.8)
#endif
#ifndef COMSPEC_SET_PHONE_H
    #define COMSPEC_SET_PHONE_H (COMSPEC_SET_PHONE_W * 4/3)
#endif
#ifndef COMSPEC_SET_SIZE_H
    #define COMSPEC_SET_SIZE_H ((((626) - (60) - (0))) / 2048 * COMSPEC_SET_PHONE_H)
#endif
#ifndef COMSPEC_SET_POS_H
    #define COMSPEC_SET_POS_H (((60)) / 2048 * COMSPEC_SET_PHONE_H)
#endif
#ifndef COMSPEC_SET_POS_W
    #define COMSPEC_SET_POS_W (((COMSPEC_SET_SIZE_H * 0.56)/3))
#endif
#ifndef COMSPEC_SET_W
    #define COMSPEC_SET_W(n) ((n) * COMSPEC_SET_POS_W)
#endif
#ifndef COMSPEC_SET_H
    #define COMSPEC_SET_H(n) ((n) * COMSPEC_SET_POS_H)
#endif

#define SET_BG_TITLE ATAK_BG_TITLE
#define SET_BG_STRIP ATAK_BG_STRIP
#define SET_BG_BODY ATAK_BG_DETAIL
#define SET_BTN ATAK_GO
#define SET_BTN_F ATAK_GO_F
#define SET_ACCENT ATAK_ACCENT
#define SET_EDIT_BG ATAK_BG_EDIT

class COMSPEC_ATAK_Settings: ATAK_Message
{
    class controls
    {
        class Title: COMSPEC_ATAK_Title
        {
            idc = 9840;
            x = 0;
            y = 0;
            w = QUOTE(COMSPEC_SET_W(3));
            h = QUOTE(COMSPEC_SET_H(0.54));
            size = QUOTE(COMSPEC_SET_H(0.36));
            text = "  Paramètres";
            onButtonClick = "call BCE_fnc_ATAK_toggleSubListMenu";
            tooltip = "Revenir au tiroir des applications.";
        };

        class AccentBar: RscText
        {
            idc = -1;
            x = 0;
            y = QUOTE(COMSPEC_SET_H(0.54));
            w = QUOTE(COMSPEC_SET_W(3));
            h = QUOTE(COMSPEC_SET_H(0.05));
            colorBackground[] = SET_ACCENT;
        };

        // Hauteur réduite pour laisser la barre Retour / Enter visible ; le contenu scroll.
        class BodyScroll: RscControlsGroup
        {
            idc = 9839;
            x = 0;
            y = QUOTE(COMSPEC_SET_H(0.62));
            w = QUOTE(COMSPEC_SET_W(3));
            h = QUOTE(COMSPEC_SET_H(8.55));
            class VScrollbar
            {
                width = 0.014;
                autoScrollEnabled = 1;
                color[] = {0.45, 0.72, 0.62, 0.85};
            };
            class HScrollbar
            {
                height = 0;
            };
            class controls
            {
                class Summary: RscStructuredText
                {
                    idc = 9841;
                    x = QUOTE(COMSPEC_SET_W(0.08));
                    y = QUOTE(COMSPEC_SET_H(0.10));
                    w = QUOTE(COMSPEC_SET_W(2.84));
                    h = QUOTE(COMSPEC_SET_H(0.92));
                    text = "Chargement des paramètres…";
                    colorBackground[] = SET_BG_STRIP;
                    class Attributes
                    {
                        font = "RobotoCondensed";
                        color = "#E6EEF0";
                        align = "left";
                        valign = "middle";
                        shadow = 1;
                        size = "0.90";
                    };
                };

                class LblCallsign: RscStructuredText
                {
                    idc = -1;
                    x = QUOTE(COMSPEC_SET_W(0.08));
                    y = QUOTE(COMSPEC_SET_H(1.16));
                    w = QUOTE(COMSPEC_SET_W(2.84));
                    h = QUOTE(COMSPEC_SET_H(0.44));
                    text = "Indicatif";
                    colorBackground[] = {0, 0, 0, 0};
                    class Attributes
                    {
                        font = "RobotoCondensedBold";
                        color = "#9ADCF5";
                        align = "left";
                        valign = "middle";
                        size = "0.92";
                    };
                };
                class EditCallsign: RscEdit
                {
                    idc = 9842;
                    x = QUOTE(COMSPEC_SET_W(0.08));
                    y = QUOTE(COMSPEC_SET_H(1.58));
                    w = QUOTE(COMSPEC_SET_W(2.84));
                    h = QUOTE(COMSPEC_SET_H(0.50));
                    colorBackground[] = SET_EDIT_BG;
                    colorText[] = {0.95, 0.98, 0.9, 1};
                    sizeEx = QUOTE(COMSPEC_SET_H(0.30));
                    autocomplete = "";
                    tooltip = "Indicatif court de votre fiche Effectifs, par exemple YB1. Pas le nom de la communauté.";
                };

                class LblRole: LblCallsign
                {
                    y = QUOTE(COMSPEC_SET_H(2.22));
                    text = "Rôle";
                };
                class EditRole: EditCallsign
                {
                    idc = 9843;
                    y = QUOTE(COMSPEC_SET_H(2.64));
                    tooltip = "Saisissez le rôle que vous voulez (Breacher, médecin, chef d’équipe…). Il apparaît auprès de l’équipe et, si vous le choisissez, sur la carte.";
                };

                class LblMapLabel: LblCallsign
                {
                    y = QUOTE(COMSPEC_SET_H(3.28));
                    text = "Affichage sur la carte";
                };
                class ComboMapLabel: RscCombo
                {
                    idc = 9850;
                    x = QUOTE(COMSPEC_SET_W(0.08));
                    y = QUOTE(COMSPEC_SET_H(3.70));
                    w = QUOTE(COMSPEC_SET_W(2.84));
                    h = QUOTE(COMSPEC_SET_H(0.50));
                    colorBackground[] = SET_EDIT_BG;
                    colorSelectBackground[] = {0.06, 0.22, 0.12, 1};
                    sizeEx = QUOTE(COMSPEC_SET_H(0.28));
                    tooltip = "Ce que vous voyez sur les pions de la carte : l’indicatif seul, ou l’indicatif suivi du rôle.";
                };

                class LblFire: LblCallsign
                {
                    y = QUOTE(COMSPEC_SET_H(4.34));
                    text = "Équipe de feu";
                };
                class ComboFire: ComboMapLabel
                {
                    idc = 9844;
                    y = QUOTE(COMSPEC_SET_H(4.76));
                    tooltip = "";
                };

                class LblGroup: LblCallsign
                {
                    y = QUOTE(COMSPEC_SET_H(5.40));
                    text = "Groupe en jeu";
                };
                class ComboGroup: ComboMapLabel
                {
                    idc = 9845;
                    y = QUOTE(COMSPEC_SET_H(5.82));
                    tooltip = "";
                };

                class LblProximity: LblCallsign
                {
                    y = QUOTE(COMSPEC_SET_H(6.46));
                    text = "Alerte téléphones suivis";
                };
                class ComboProximity: ComboMapLabel
                {
                    idc = 9849;
                    y = QUOTE(COMSPEC_SET_H(6.88));
                    tooltip = "";
                    onLBSelChanged = "[] call comspec_overwatch_atak_athena_fnc_athena_phoneProximitySave";
                };

                class Feedback: RscStructuredText
                {
                    idc = 9847;
                    x = QUOTE(COMSPEC_SET_W(0.08));
                    y = QUOTE(COMSPEC_SET_H(7.52));
                    w = QUOTE(COMSPEC_SET_W(2.84));
                    h = QUOTE(COMSPEC_SET_H(0.46));
                    text = "Indicatif, rôle, carte, équipe et groupe. Enregistrez pour appliquer.";
                    colorBackground[] = SET_BG_BODY;
                    class Attributes
                    {
                        font = "RobotoCondensed";
                        color = "#D0E0E8";
                        align = "left";
                        valign = "middle";
                        size = "0.82";
                    };
                };

                class BtnSave: COMSPEC_ATAK_BtnGo
                {
                    idc = 9846;
                    x = QUOTE(COMSPEC_SET_W(0.08));
                    y = QUOTE(COMSPEC_SET_H(8.08));
                    w = QUOTE(COMSPEC_SET_W(1.80));
                    h = QUOTE(COMSPEC_SET_H(0.52));
                    size = QUOTE(COMSPEC_SET_H(0.28));
                    text = "Enregistrer";
                    colorBackground[] = SET_BTN;
                    colorBackground2[] = SET_BTN;
                    colorBackgroundFocused[] = SET_BTN_F;
                    onButtonClick = "[] call comspec_overwatch_atak_athena_fnc_athena_settingsSave";
                };

                class BtnRefresh: BtnSave
                {
                    idc = 9848;
                    x = QUOTE(COMSPEC_SET_W(1.96));
                    w = QUOTE(COMSPEC_SET_W(0.96));
                    text = "Actualiser";
                    colorBackground[] = ATAK_BTN;
                    colorBackground2[] = ATAK_BTN;
                    colorBackgroundFocused[] = ATAK_BTN_F;
                    onButtonClick = "[] call comspec_overwatch_atak_athena_fnc_athena_updateSettings";
                    class Attributes { font = "RobotoCondensed"; color = "#FFFFFF"; align = "center"; valign = "middle"; shadow = "false"; };
                };

                class LblLinkSection: RscStructuredText
                {
                    idc = 9856;
                    x = QUOTE(COMSPEC_SET_W(0.08));
                    y = QUOTE(COMSPEC_SET_H(8.78));
                    w = QUOTE(COMSPEC_SET_W(2.84));
                    h = QUOTE(COMSPEC_SET_H(0.92));
                    text = "<t color='#7CFF9A' size='1.05'>Liaison au poste</t><br/><t color='#B8C8D4' size='0.95'>Préférez Appairer sur le portail. Les réglages avancés sont rarement nécessaires.</t>";
                    colorBackground[] = {0.04, 0.10, 0.08, 0.92};
                    class Attributes
                    {
                        font = "RobotoCondensed";
                        color = "#D0D8E0";
                        align = "left";
                        size = "0.90";
                    };
                };

                class BtnToggleAdvanced: COMSPEC_ATAK_BtnGo
                {
                    idc = 9857;
                    x = QUOTE(COMSPEC_SET_W(0.08));
                    y = QUOTE(COMSPEC_SET_H(9.82));
                    w = QUOTE(COMSPEC_SET_W(2.84));
                    h = QUOTE(COMSPEC_SET_H(0.50));
                    size = QUOTE(COMSPEC_SET_H(0.28));
                    text = "Afficher les réglages avancés";
                    colorBackground[] = ATAK_BTN;
                    colorBackground2[] = ATAK_BTN;
                    colorBackgroundFocused[] = ATAK_BTN_F;
                    onButtonClick = "[] call comspec_overwatch_atak_athena_fnc_athena_connectionToggleAdvanced";
                    class Attributes { font = "RobotoCondensed"; color = "#FFFFFF"; align = "center"; valign = "middle"; shadow = "false"; };
                };

                class LblPortal: LblCallsign
                {
                    idc = 9858;
                    y = QUOTE(COMSPEC_SET_H(10.48));
                    text = "Adresse du portail";
                    show = 0;
                };
                class EditPortal: EditCallsign
                {
                    idc = 9851;
                    y = QUOTE(COMSPEC_SET_H(10.90));
                    show = 0;
                    tooltip = "Adresse du portail Athena, par ex. https://athena.ttrd.fr/public — sans slash final.";
                };

                class LblAccessKey: LblCallsign
                {
                    idc = 9859;
                    y = QUOTE(COMSPEC_SET_H(11.54));
                    text = "Clé d’accès communauté";
                    show = 0;
                };
                class EditAccessKey: EditCallsign
                {
                    idc = 9852;
                    y = QUOTE(COMSPEC_SET_H(11.96));
                    password = 1;
                    show = 0;
                    tooltip = "Clé fournie par l’administration. Laissez vide si Appairer a déjà configuré la liaison.";
                };

                class LblCommunity: LblCallsign
                {
                    idc = 9860;
                    y = QUOTE(COMSPEC_SET_H(12.60));
                    text = "Identifiant de communauté";
                    show = 0;
                };
                class EditCommunity: EditCallsign
                {
                    idc = 9853;
                    y = QUOTE(COMSPEC_SET_H(13.02));
                    show = 0;
                    tooltip = "Utile si plusieurs communautés partagent la même adresse. Souvent renseigné automatiquement.";
                };

                class LinkFeedback: RscStructuredText
                {
                    idc = 9855;
                    x = QUOTE(COMSPEC_SET_W(0.08));
                    y = QUOTE(COMSPEC_SET_H(13.66));
                    w = QUOTE(COMSPEC_SET_W(2.84));
                    h = QUOTE(COMSPEC_SET_H(0.56));
                    text = "Statut de liaison à jour après Appairer ou Enregistrer.";
                    colorBackground[] = SET_BG_BODY;
                    class Attributes
                    {
                        font = "RobotoCondensed";
                        color = "#D0E0E8";
                        align = "left";
                        valign = "top";
                        size = "0.82";
                    };
                };

                class BtnSaveLink: COMSPEC_ATAK_BtnGo
                {
                    idc = 9854;
                    x = QUOTE(COMSPEC_SET_W(0.08));
                    y = QUOTE(COMSPEC_SET_H(14.34));
                    w = QUOTE(COMSPEC_SET_W(2.84));
                    h = QUOTE(COMSPEC_SET_H(0.52));
                    size = QUOTE(COMSPEC_SET_H(0.28));
                    text = "Enregistrer la liaison";
                    show = 0;
                    colorBackground[] = SET_BTN;
                    colorBackground2[] = SET_BTN;
                    colorBackgroundFocused[] = SET_BTN_F;
                    onButtonClick = "[] spawn comspec_overwatch_atak_athena_fnc_athena_connectionSave";
                    class Attributes { font = "RobotoCondensed"; color = "#7CFF9A"; align = "center"; valign = "middle"; shadow = "false"; };
                };

                // Marge basse pour scroller « Liaison au poste » au-dessus de Retour.
                class Spacer: RscText
                {
                    idc = -1;
                    x = 0;
                    y = QUOTE(COMSPEC_SET_H(15.00));
                    w = QUOTE(COMSPEC_SET_W(0.1));
                    h = QUOTE(COMSPEC_SET_H(0.70));
                    colorBackground[] = {0, 0, 0, 0};
                };
            };
        };
    };
};
