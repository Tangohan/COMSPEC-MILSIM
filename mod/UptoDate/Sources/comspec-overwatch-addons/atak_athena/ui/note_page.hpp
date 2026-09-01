// App ATAK « RENS » — fiches de renseignement simplifiées.
//
// La page du tiroir n'est qu'un accueil : le rédacteur s'ouvre dans le
// panneau d'application du téléphone (même surface que les autres apps).
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

#define NOTE_BG_TITLE ATAK_BG_TITLE
#define NOTE_BG_BODY ATAK_BG_DETAIL
#define NOTE_BTN ATAK_BG_TILE
#define NOTE_BTN_F ATAK_BG_TILE_F
#define NOTE_ACCENT ATAK_ACCENT

class COMSPEC_ATAK_Note: ATAK_Message
{
    class controls
    {
        class Title: COMSPEC_ATAK_Title
        {
            idc = 9862;
            x = 0;
            y = 0;
            w = QUOTE(COMSPEC_NOTE_W(3));
            h = QUOTE(COMSPEC_NOTE_H(0.62));
            size = QUOTE(COMSPEC_NOTE_H(0.40));
            text = "  Fiches de renseignement";
            onButtonClick = "call BCE_fnc_ATAK_toggleSubListMenu";
            tooltip = "Revenir au tiroir des applications.";
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

        class BtnCompose: COMSPEC_ATAK_BtnGo
        {
            idc = 9864;
            x = QUOTE(COMSPEC_NOTE_W(0.08));
            y = QUOTE(COMSPEC_NOTE_H(5.05));
            w = QUOTE(COMSPEC_NOTE_W(2.84));
            h = QUOTE(COMSPEC_NOTE_H(0.72));
            size = QUOTE(COMSPEC_NOTE_H(0.40));
            text = "Rédiger une fiche";
            colorBackground[] = NOTE_BTN;
            colorBackground2[] = NOTE_BTN;
            colorBackgroundFocused[] = NOTE_BTN_F;
            onButtonClick = "[''] call comspec_overwatch_atak_athena_fnc_athena_openNote";
        };

        class BtnAmbiance: BtnCompose
        {
            idc = 9865;
            y = QUOTE(COMSPEC_NOTE_H(5.90));
            w = QUOTE(COMSPEC_NOTE_W(1.38));
            text = "Fiche d’ambiance";
            colorBackground[] = ATAK_BG_TILE;
            colorBackground2[] = ATAK_BG_TILE;
            colorBackgroundFocused[] = ATAK_BG_TILE_F;
            onButtonClick = "['FRA'] call comspec_overwatch_atak_athena_fnc_athena_openNote";
            class Attributes { font = "RobotoCondensed"; color = "#FFFFFF"; align = "center"; valign = "middle"; shadow = "false"; };
        };

        class BtnContact: BtnCompose
        {
            idc = 9866;
            x = QUOTE(COMSPEC_NOTE_W(1.54));
            y = QUOTE(COMSPEC_NOTE_H(5.90));
            w = QUOTE(COMSPEC_NOTE_W(1.38));
            text = "Fiche de contact";
            colorBackground[] = ATAK_BG_TILE;
            colorBackground2[] = ATAK_BG_TILE;
            colorBackgroundFocused[] = ATAK_BG_TILE_F;
            onButtonClick = "['FRC'] call comspec_overwatch_atak_athena_fnc_athena_openNote";
            class Attributes { font = "RobotoCondensed"; color = "#FFFFFF"; align = "center"; valign = "middle"; shadow = "false"; };
        };

        class BtnRefresh: BtnCompose
        {
            idc = 9867;
            y = QUOTE(COMSPEC_NOTE_H(6.75));
            w = QUOTE(COMSPEC_NOTE_W(1.38));
            text = "Actualiser";
            colorBackground[] = ATAK_BG_TILE;
            colorBackground2[] = ATAK_BG_TILE;
            colorBackgroundFocused[] = ATAK_BG_TILE_F;
            onButtonClick = "[] call comspec_overwatch_atak_athena_fnc_athena_updateNote";
            class Attributes { font = "RobotoCondensed"; color = "#FFFFFF"; align = "center"; valign = "middle"; shadow = "false"; };
        };

        class BtnAthena: BtnCompose
        {
            idc = 9868;
            x = QUOTE(COMSPEC_NOTE_W(1.54));
            y = QUOTE(COMSPEC_NOTE_H(6.75));
            colorBackground[] = ATAK_BG_TILE;
            colorBackground2[] = ATAK_BG_TILE;
            colorBackgroundFocused[] = ATAK_BG_TILE_F;
            class Attributes { font = "RobotoCondensed"; color = "#FFFFFF"; align = "center"; valign = "middle"; shadow = "false"; };
            w = QUOTE(COMSPEC_NOTE_W(1.38));
            text = "Athena";
            onButtonClick = "['all'] call comspec_overwatch_atak_athena_fnc_athena_openFeature";
        };
    };
};
