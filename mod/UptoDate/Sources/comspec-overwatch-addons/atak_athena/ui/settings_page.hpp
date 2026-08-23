// App « Paramètres » — identité, équipe de feu, groupe.
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

#define SET_BG_TITLE {0.02, 0.05, 0.07, 0.92}
#define SET_BG_STRIP {0.03, 0.07, 0.09, 0.88}
#define SET_BG_BODY {0.02, 0.05, 0.06, 0.9}
#define SET_BTN {0.08, 0.28, 0.22, 0.95}
#define SET_BTN_F {0.12, 0.42, 0.34, 1}
#define SET_ACCENT {0.85, 0.72, 0.28, 0.95}
#define SET_EDIT_BG {0.04, 0.08, 0.12, 1}

class COMSPEC_ATAK_Settings: ATAK_Message
{
    class controls
    {
        class Title: BCE_RscButtonMenu
        {
            idc = 9840;
            x = 0;
            y = 0;
            w = QUOTE(COMSPEC_SET_W(3));
            h = QUOTE(COMSPEC_SET_H(0.56));
            size = QUOTE(COMSPEC_SET_H(0.38));
            text = "Paramètres";
            colorBackground[] = SET_BG_TITLE;
            colorBackground2[] = SET_BG_TITLE;
            colorBackgroundFocused[] = {0.04, 0.1, 0.12, 0.95};
            onButtonClick = "call BCE_fnc_ATAK_toggleSubListMenu";
            class Attributes { align = "center"; valign = "middle"; };
        };

        class AccentBar: RscText
        {
            idc = -1;
            x = 0;
            y = QUOTE(COMSPEC_SET_H(0.56));
            w = QUOTE(COMSPEC_SET_W(3));
            h = QUOTE(COMSPEC_SET_H(0.05));
            colorBackground[] = SET_ACCENT;
        };

        class Summary: RscStructuredText
        {
            idc = 9841;
            x = QUOTE(COMSPEC_SET_W(0.08));
            y = QUOTE(COMSPEC_SET_H(0.70));
            w = QUOTE(COMSPEC_SET_W(2.84));
            h = QUOTE(COMSPEC_SET_H(1.05));
            text = "Chargement des paramètres…";
            colorBackground[] = SET_BG_STRIP;
            class Attributes
            {
                font = "RobotoCondensed";
                color = "#E6EEF0";
                align = "left";
                valign = "middle";
                shadow = 1;
                size = "0.64";
            };
        };

        class LblCallsign: RscStructuredText
        {
            idc = -1;
            x = QUOTE(COMSPEC_SET_W(0.08));
            y = QUOTE(COMSPEC_SET_H(1.82));
            w = QUOTE(COMSPEC_SET_W(2.84));
            h = QUOTE(COMSPEC_SET_H(0.28));
            text = "Indicatif";
            colorBackground[] = {0, 0, 0, 0};
            class Attributes
            {
                font = "RobotoCondensed";
                color = "#8FBEA8";
                align = "left";
                size = "0.58";
            };
        };
        class EditCallsign: RscEdit
        {
            idc = 9842;
            x = QUOTE(COMSPEC_SET_W(0.08));
            y = QUOTE(COMSPEC_SET_H(2.10));
            w = QUOTE(COMSPEC_SET_W(2.84));
            h = QUOTE(COMSPEC_SET_H(0.42));
            colorBackground[] = SET_EDIT_BG;
            colorText[] = {0.95, 0.98, 0.9, 1};
            sizeEx = QUOTE(COMSPEC_SET_H(0.28));
            autocomplete = "";
        };

        class LblRole: LblCallsign
        {
            y = QUOTE(COMSPEC_SET_H(2.56));
            text = "Rôle";
        };
        class ComboRole: RscCombo
        {
            idc = 9843;
            x = QUOTE(COMSPEC_SET_W(0.08));
            y = QUOTE(COMSPEC_SET_H(2.84));
            w = QUOTE(COMSPEC_SET_W(2.84));
            h = QUOTE(COMSPEC_SET_H(0.42));
            colorBackground[] = SET_EDIT_BG;
            colorSelectBackground[] = {0.08, 0.28, 0.22, 1};
            sizeEx = QUOTE(COMSPEC_SET_H(0.26));
        };

        class LblFire: LblCallsign
        {
            y = QUOTE(COMSPEC_SET_H(3.30));
            text = "Équipe de feu";
        };
        class ComboFire: ComboRole
        {
            idc = 9844;
            y = QUOTE(COMSPEC_SET_H(3.58));
        };

        class LblGroup: LblCallsign
        {
            y = QUOTE(COMSPEC_SET_H(4.04));
            text = "Groupe en jeu";
        };
        class ComboGroup: ComboRole
        {
            idc = 9845;
            y = QUOTE(COMSPEC_SET_H(4.32));
        };

        class Feedback: RscStructuredText
        {
            idc = 9847;
            x = QUOTE(COMSPEC_SET_W(0.08));
            y = QUOTE(COMSPEC_SET_H(4.82));
            w = QUOTE(COMSPEC_SET_W(2.84));
            h = QUOTE(COMSPEC_SET_H(0.70));
            text = "Indiquez votre indicatif et votre rôle. L’équipe de feu et le groupe choisis apparaissent ensuite sur ATAK.";
            colorBackground[] = SET_BG_BODY;
            class Attributes
            {
                font = "RobotoCondensed";
                color = "#C8D8E0";
                align = "left";
                valign = "top";
                size = "0.60";
            };
        };

        class BtnSave: BCE_RscButtonMenu
        {
            idc = 9846;
            x = QUOTE(COMSPEC_SET_W(0.08));
            y = QUOTE(COMSPEC_SET_H(5.62));
            w = QUOTE(COMSPEC_SET_W(1.80));
            h = QUOTE(COMSPEC_SET_H(0.50));
            size = QUOTE(COMSPEC_SET_H(0.28));
            text = "Enregistrer";
            colorBackground[] = SET_BTN;
            colorBackground2[] = SET_BTN;
            colorBackgroundFocused[] = SET_BTN_F;
            onButtonClick = "[] call comspec_overwatch_atak_athena_fnc_athena_settingsSave";
            class Attributes { align = "center"; valign = "middle"; };
        };

        class BtnRefresh: BtnSave
        {
            idc = 9848;
            x = QUOTE(COMSPEC_SET_W(1.96));
            w = QUOTE(COMSPEC_SET_W(0.96));
            text = "Actualiser";
            onButtonClick = "[] call comspec_overwatch_atak_athena_fnc_athena_updateSettings";
        };
    };
};
