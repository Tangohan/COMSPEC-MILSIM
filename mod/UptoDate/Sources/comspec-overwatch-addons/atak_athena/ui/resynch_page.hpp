// App « Resynch » — renvoi de toutes les données Athena depuis le terminal.
#ifndef QUOTE
    #define QUOTE(var1) #var1
#endif

#ifndef COMSPEC_RESYNCH_PHONE_W
    #define COMSPEC_RESYNCH_PHONE_W (safezoneW * 0.8)
#endif
#ifndef COMSPEC_RESYNCH_PHONE_H
    #define COMSPEC_RESYNCH_PHONE_H (COMSPEC_RESYNCH_PHONE_W * 4/3)
#endif
#ifndef COMSPEC_RESYNCH_SIZE_H
    #define COMSPEC_RESYNCH_SIZE_H ((((626) - (60) - (0))) / 2048 * COMSPEC_RESYNCH_PHONE_H)
#endif
#ifndef COMSPEC_RESYNCH_POS_H
    #define COMSPEC_RESYNCH_POS_H (((60)) / 2048 * COMSPEC_RESYNCH_PHONE_H)
#endif
#ifndef COMSPEC_RESYNCH_POS_W
    #define COMSPEC_RESYNCH_POS_W (((COMSPEC_RESYNCH_SIZE_H * 0.56)/3))
#endif
#ifndef COMSPEC_RESYNCH_W
    #define COMSPEC_RESYNCH_W(n) ((n) * COMSPEC_RESYNCH_POS_W)
#endif
#ifndef COMSPEC_RESYNCH_H
    #define COMSPEC_RESYNCH_H(n) ((n) * COMSPEC_RESYNCH_POS_H)
#endif

#define RESYNCH_BG_TITLE {0.02, 0.05, 0.07, 0.92}
#define RESYNCH_BG_BODY {0.02, 0.05, 0.06, 0.9}
#define RESYNCH_BTN {0.06, 0.22, 0.16, 0.95}
#define RESYNCH_BTN_F {0.10, 0.38, 0.28, 1}
#define RESYNCH_ACCENT {0.20, 0.72, 0.55, 0.95}

class COMSPEC_ATAK_Resynch: ATAK_Message
{
    class controls
    {
        class Title: BCE_RscButtonMenu
        {
            idc = 9870;
            x = 0;
            y = 0;
            w = QUOTE(COMSPEC_RESYNCH_W(3));
            h = QUOTE(COMSPEC_RESYNCH_H(0.62));
            size = QUOTE(COMSPEC_RESYNCH_H(0.44));
            text = "Resynch";
            colorBackground[] = RESYNCH_BG_TITLE;
            colorBackground2[] = RESYNCH_BG_TITLE;
            colorBackgroundFocused[] = {0.04, 0.1, 0.12, 0.95};
            onButtonClick = "call BCE_fnc_ATAK_toggleSubListMenu";
            class Attributes { align = "center"; valign = "middle"; };
        };

        class AccentBar: RscText
        {
            idc = -1;
            x = 0;
            y = QUOTE(COMSPEC_RESYNCH_H(0.62));
            w = QUOTE(COMSPEC_RESYNCH_W(3));
            h = QUOTE(COMSPEC_RESYNCH_H(0.06));
            colorBackground[] = RESYNCH_ACCENT;
        };

        class Body: RscStructuredText
        {
            idc = 9871;
            x = QUOTE(COMSPEC_RESYNCH_W(0.08));
            y = QUOTE(COMSPEC_RESYNCH_H(0.84));
            w = QUOTE(COMSPEC_RESYNCH_W(2.84));
            h = QUOTE(COMSPEC_RESYNCH_H(5.90));
            text = "<t size='0.78'>Renvoi des données en cours…</t>";
            colorBackground[] = RESYNCH_BG_BODY;
            class Attributes
            {
                font = "RobotoCondensed";
                color = "#D8E4EA";
                align = "left";
                valign = "top";
                shadow = 1;
                size = "0.70";
            };
        };

        class BtnRelancer: BCE_RscButtonMenu
        {
            idc = 9872;
            x = QUOTE(COMSPEC_RESYNCH_W(0.08));
            y = QUOTE(COMSPEC_RESYNCH_H(6.90));
            w = QUOTE(COMSPEC_RESYNCH_W(2.84));
            h = QUOTE(COMSPEC_RESYNCH_H(0.52));
            size = QUOTE(COMSPEC_RESYNCH_H(0.28));
            text = "Relancer le Resynch";
            colorBackground[] = RESYNCH_BTN;
            colorBackground2[] = RESYNCH_BTN;
            colorBackgroundFocused[] = RESYNCH_BTN_F;
            onButtonClick = "[] spawn { [] call comspec_overwatch_atak_athena_fnc_athena_resynchAll; };";
            class Attributes { align = "center"; valign = "middle"; };
        };
    };
};
