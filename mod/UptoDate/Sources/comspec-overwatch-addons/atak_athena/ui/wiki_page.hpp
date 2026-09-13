// App « Tutoriel / WIKI » — liaison, connecté, sync et dépannage identité.
#ifndef QUOTE
    #define QUOTE(var1) #var1
#endif

#ifndef COMSPEC_WIKI_PHONE_W
    #define COMSPEC_WIKI_PHONE_W (safezoneW * 0.8)
#endif
#ifndef COMSPEC_WIKI_PHONE_H
    #define COMSPEC_WIKI_PHONE_H (COMSPEC_WIKI_PHONE_W * 4/3)
#endif
#ifndef COMSPEC_WIKI_SIZE_H
    #define COMSPEC_WIKI_SIZE_H ((((626) - (60) - (0))) / 2048 * COMSPEC_WIKI_PHONE_H)
#endif
#ifndef COMSPEC_WIKI_POS_H
    #define COMSPEC_WIKI_POS_H (((60)) / 2048 * COMSPEC_WIKI_PHONE_H)
#endif
#ifndef COMSPEC_WIKI_POS_W
    #define COMSPEC_WIKI_POS_W (((COMSPEC_WIKI_SIZE_H * 0.56)/3))
#endif
#ifndef COMSPEC_WIKI_W
    #define COMSPEC_WIKI_W(n) ((n) * COMSPEC_WIKI_POS_W)
#endif
#ifndef COMSPEC_WIKI_H
    #define COMSPEC_WIKI_H(n) ((n) * COMSPEC_WIKI_POS_H)
#endif

#define WIKI_BG_TITLE ATAK_BG_TITLE
#define WIKI_BG_BODY ATAK_BG_DETAIL
#define WIKI_BTN ATAK_BTN
#define WIKI_BTN_F ATAK_BTN_F
#define WIKI_ACCENT ATAK_ACCENT

class COMSPEC_ATAK_Wiki: ATAK_Message
{
    class controls
    {
        class Title: COMSPEC_ATAK_Title
        {
            idc = 9880;
            x = 0;
            y = 0;
            w = QUOTE(COMSPEC_WIKI_W(3));
            h = QUOTE(COMSPEC_WIKI_H(0.62));
            size = QUOTE(COMSPEC_WIKI_H(0.36));
            text = "  Tutoriel / WIKI";
            onButtonClick = "call BCE_fnc_ATAK_toggleSubListMenu";
            tooltip = "Revenir au tiroir des applications.";
        };

        class AccentBar: RscText
        {
            idc = -1;
            x = 0;
            y = QUOTE(COMSPEC_WIKI_H(0.62));
            w = QUOTE(COMSPEC_WIKI_W(3));
            h = QUOTE(COMSPEC_WIKI_H(0.06));
            colorBackground[] = WIKI_ACCENT;
        };

        class Body: RscStructuredText
        {
            idc = 9881;
            x = QUOTE(COMSPEC_WIKI_W(0.06));
            y = QUOTE(COMSPEC_WIKI_H(0.78));
            w = QUOTE(COMSPEC_WIKI_W(2.88));
            h = QUOTE(COMSPEC_WIKI_H(6.40));
            text = "";
            colorBackground[] = WIKI_BG_BODY;
            class Attributes
            {
                font = "RobotoCondensed";
                color = "#E8F2FA";
                align = "left";
                valign = "top";
                shadow = 1;
                size = "0.72";
            };
        };

        class BtnConnexion: COMSPEC_ATAK_Btn
        {
            idc = 9882;
            x = QUOTE(COMSPEC_WIKI_W(0.06));
            y = QUOTE(COMSPEC_WIKI_H(7.30));
            w = QUOTE(COMSPEC_WIKI_W(1.40));
            h = QUOTE(COMSPEC_WIKI_H(0.48));
            size = QUOTE(COMSPEC_WIKI_H(0.26));
            text = "Connexion Athena";
            colorBackground[] = WIKI_BTN;
            colorBackground2[] = WIKI_BTN;
            colorBackgroundFocused[] = WIKI_BTN_F;
            onButtonClick = "[] call comspec_overwatch_connect_fnc_openLogin";
            class Attributes { font = "RobotoCondensed"; color = "#E8F2FA"; align = "center"; valign = "middle"; shadow = "false"; };
        };

        class BtnRetour: COMSPEC_ATAK_Btn
        {
            idc = 9883;
            x = QUOTE(COMSPEC_WIKI_W(1.54));
            y = QUOTE(COMSPEC_WIKI_H(7.30));
            w = QUOTE(COMSPEC_WIKI_W(1.40));
            h = QUOTE(COMSPEC_WIKI_H(0.48));
            size = QUOTE(COMSPEC_WIKI_H(0.26));
            text = "Retour bureau";
            colorBackground[] = WIKI_BTN;
            colorBackground2[] = WIKI_BTN;
            colorBackgroundFocused[] = WIKI_BTN_F;
            onButtonClick = "call BCE_fnc_ATAK_toggleSubListMenu";
            class Attributes { font = "RobotoCondensed"; color = "#E8F2FA"; align = "center"; valign = "middle"; shadow = "false"; };
        };
    };
};
