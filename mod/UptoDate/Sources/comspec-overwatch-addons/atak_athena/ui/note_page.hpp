// App ATAK « RENS » — fiches de renseignement simplifiées.
//
// La page du tiroir n'est qu'un accueil : le rédacteur, lui, s'ouvre en plein
// cadre sur toute la surface de l'ATAK (COMSPEC_IntelNote_Dialog). Elle sert de
// point de retour quand on referme le rédacteur, et rappelle l'état du brouillon
// en cours et de la liaison.
#ifndef QUOTE
    #define QUOTE(var1) #var1
#endif

#ifndef COMSPEC_NOTE_PHONE_W
    #define COMSPEC_NOTE_PHONE_W (safezoneW * 0.8)
#endif
#ifndef COMSPEC_NOTE_PHONE_H
    #define COMSPEC_NOTE_PHONE_H (COMSPEC_NOTE_PHONE_W * 4/3)
#endif
#ifndef COMSPEC_NOTE_SIZE_H
    #define COMSPEC_NOTE_SIZE_H ((((626) - (60) - (0))) / 2048 * COMSPEC_NOTE_PHONE_H)
#endif
#ifndef COMSPEC_NOTE_POS_H
    #define COMSPEC_NOTE_POS_H (((60)) / 2048 * COMSPEC_NOTE_PHONE_H)
#endif
#ifndef COMSPEC_NOTE_POS_W
    #define COMSPEC_NOTE_POS_W (((COMSPEC_NOTE_SIZE_H * 0.56)/3))
#endif
#ifndef COMSPEC_NOTE_W
    #define COMSPEC_NOTE_W(n) ((n) * COMSPEC_NOTE_POS_W)
#endif
#ifndef COMSPEC_NOTE_H
    #define COMSPEC_NOTE_H(n) ((n) * COMSPEC_NOTE_POS_H)
#endif

#define NOTE_BG_TITLE {0.02, 0.03, 0.09, 0.92}
#define NOTE_BG_BODY {0.02, 0.03, 0.07, 0.9}
#define NOTE_BTN {0.12, 0.10, 0.42, 0.95}
#define NOTE_BTN_F {0.18, 0.15, 0.55, 1}
#define NOTE_ACCENT {0.30, 0.26, 0.85, 0.95}

class COMSPEC_ATAK_Note: ATAK_Message
{
    class controls
    {
        class Title: BCE_RscButtonMenu
        {
            idc = 9862;
            x = 0;
            y = 0;
            w = QUOTE(COMSPEC_NOTE_W(3));
            h = QUOTE(COMSPEC_NOTE_H(0.62));
            size = QUOTE(COMSPEC_NOTE_H(0.44));
            text = "Fiches de renseignement";
            colorBackground[] = NOTE_BG_TITLE;
            colorBackground2[] = NOTE_BG_TITLE;
            colorBackgroundFocused[] = {0.04, 0.05, 0.14, 0.95};
            onButtonClick = "call BCE_fnc_ATAK_toggleSubListMenu";
            class Attributes { align = "center"; valign = "middle"; };
        };

        class AccentBar: RscText
        {
            idc = -1;
            x = 0;
            y = QUOTE(COMSPEC_NOTE_H(0.62));
            w = QUOTE(COMSPEC_NOTE_W(3));
            h = QUOTE(COMSPEC_NOTE_H(0.06));
            colorBackground[] = NOTE_ACCENT;
        };

        class Body: RscStructuredText
        {
            idc = 9863;
            x = QUOTE(COMSPEC_NOTE_W(0.08));
            y = QUOTE(COMSPEC_NOTE_H(0.78));
            w = QUOTE(COMSPEC_NOTE_W(2.84));
            h = QUOTE(COMSPEC_NOTE_H(4.10));
            text = "";
            colorBackground[] = NOTE_BG_BODY;
            class Attributes
            {
                font = "RobotoCondensed";
                color = "#DCE0F0";
                align = "left";
                valign = "top";
                shadow = 1;
                size = "0.68";
            };
        };

        class BtnCompose: BCE_RscButtonMenu
        {
            idc = 9864;
            x = QUOTE(COMSPEC_NOTE_W(0.08));
            y = QUOTE(COMSPEC_NOTE_H(5.05));
            w = QUOTE(COMSPEC_NOTE_W(2.84));
            h = QUOTE(COMSPEC_NOTE_H(0.72));
            size = QUOTE(COMSPEC_NOTE_H(0.40));
            text = "Rédiger une fiche (plein écran)";
            colorBackground[] = NOTE_BTN;
            colorBackground2[] = NOTE_BTN;
            colorBackgroundFocused[] = NOTE_BTN_F;
            onButtonClick = "[''] call comspec_overwatch_atak_athena_fnc_athena_openNote";
            class Attributes { align = "center"; valign = "middle"; };
        };

        class BtnAmbiance: BtnCompose
        {
            idc = 9865;
            y = QUOTE(COMSPEC_NOTE_H(5.90));
            w = QUOTE(COMSPEC_NOTE_W(1.38));
            text = "Fiche d’ambiance";
            onButtonClick = "['FRA'] call comspec_overwatch_atak_athena_fnc_athena_openNote";
        };

        class BtnContact: BtnCompose
        {
            idc = 9866;
            x = QUOTE(COMSPEC_NOTE_W(1.54));
            y = QUOTE(COMSPEC_NOTE_H(5.90));
            w = QUOTE(COMSPEC_NOTE_W(1.38));
            text = "Fiche de contact";
            onButtonClick = "['FRC'] call comspec_overwatch_atak_athena_fnc_athena_openNote";
        };

        class BtnRefresh: BtnCompose
        {
            idc = 9867;
            y = QUOTE(COMSPEC_NOTE_H(6.75));
            w = QUOTE(COMSPEC_NOTE_W(1.38));
            text = "Actualiser";
            onButtonClick = "[] call comspec_overwatch_atak_athena_fnc_athena_updateNote";
        };

        class BtnAthena: BtnCompose
        {
            idc = 9868;
            x = QUOTE(COMSPEC_NOTE_W(1.54));
            y = QUOTE(COMSPEC_NOTE_H(6.75));
            w = QUOTE(COMSPEC_NOTE_W(1.38));
            text = "Athena";
            onButtonClick = "['all'] call comspec_overwatch_atak_athena_fnc_athena_openFeature";
        };
    };
};
