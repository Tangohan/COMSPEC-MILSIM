// App ATAK « BII-10 / SEEK II » — ouvre Identifi dans le tiroir applications.
#ifndef QUOTE
    #define QUOTE(var1) #var1
#endif

#ifndef COMSPEC_BII_PHONE_W
    #define COMSPEC_BII_PHONE_W (safezoneW * 0.8)
#endif
#ifndef COMSPEC_BII_PHONE_H
    #define COMSPEC_BII_PHONE_H (COMSPEC_BII_PHONE_W * 4/3)
#endif
#ifndef COMSPEC_BII_SIZE_H
    #define COMSPEC_BII_SIZE_H ((((626) - (60) - (0))) / 2048 * COMSPEC_BII_PHONE_H)
#endif
#ifndef COMSPEC_BII_POS_H
    #define COMSPEC_BII_POS_H (((60)) / 2048 * COMSPEC_BII_PHONE_H)
#endif
#ifndef COMSPEC_BII_POS_W
    #define COMSPEC_BII_POS_W (((COMSPEC_BII_SIZE_H * 0.56)/3))
#endif
#ifndef COMSPEC_BII_W
    #define COMSPEC_BII_W(n) ((n) * COMSPEC_BII_POS_W)
#endif
#ifndef COMSPEC_BII_H
    #define COMSPEC_BII_H(n) ((n) * COMSPEC_BII_POS_H)
#endif

#define BII_BG_TITLE {0.02, 0.05, 0.07, 0.92}
#define BII_BG_BODY {0.02, 0.05, 0.06, 0.9}
#define BII_BTN {0.06, 0.22, 0.28, 0.95}
#define BII_BTN_F {0.1, 0.36, 0.42, 1}
#define BII_ACCENT {0.25, 0.78, 0.88, 0.95}

class COMSPEC_ATAK_BII: ATAK_Message
{
    class controls
    {
        class Title: BCE_RscButtonMenu
        {
            idc = 9800;
            x = 0;
            y = 0;
            w = QUOTE(COMSPEC_BII_W(3));
            h = QUOTE(COMSPEC_BII_H(0.62));
            size = QUOTE(COMSPEC_BII_H(0.44));
            text = "BII-10 / SEEK II";
            colorBackground[] = BII_BG_TITLE;
            colorBackground2[] = BII_BG_TITLE;
            colorBackgroundFocused[] = {0.04, 0.1, 0.12, 0.95};
            onButtonClick = "call BCE_fnc_ATAK_toggleSubListMenu";
            class Attributes { align = "center"; valign = "middle"; };
        };

        class AccentBar: RscText
        {
            idc = -1;
            x = 0;
            y = QUOTE(COMSPEC_BII_H(0.62));
            w = QUOTE(COMSPEC_BII_W(3));
            h = QUOTE(COMSPEC_BII_H(0.06));
            colorBackground[] = BII_ACCENT;
        };

        class Body: RscStructuredText
        {
            idc = 9801;
            x = 0;
            y = QUOTE(COMSPEC_BII_H(0.78));
            w = QUOTE(COMSPEC_BII_W(3));
            h = QUOTE(COMSPEC_BII_H(2.4));
            colorBackground[] = BII_BG_BODY;
            text = "";
        };

        class OpenIdentify: BCE_RscButtonMenu
        {
            idc = 9810;
            x = QUOTE(COMSPEC_BII_W(0.08));
            y = QUOTE(COMSPEC_BII_H(3.4));
            w = QUOTE(COMSPEC_BII_W(2.84));
            h = QUOTE(COMSPEC_BII_H(0.72));
            size = QUOTE(COMSPEC_BII_H(0.4));
            text = "Ouvrir Identify (SEEK)";
            colorBackground[] = BII_BTN;
            colorBackground2[] = BII_BTN;
            colorBackgroundFocused[] = BII_BTN_F;
            onButtonClick = "['scan'] call comspec_overwatch_atak_athena_fnc_athena_openBiiTab";
            class Attributes { align = "center"; valign = "middle"; };
        };

        class OpenSse: BCE_RscButtonMenu
        {
            idc = 9811;
            x = QUOTE(COMSPEC_BII_W(0.08));
            y = QUOTE(COMSPEC_BII_H(4.25));
            w = QUOTE(COMSPEC_BII_W(2.84));
            h = QUOTE(COMSPEC_BII_H(0.72));
            size = QUOTE(COMSPEC_BII_H(0.4));
            text = "Ouvrir SSE";
            colorBackground[] = BII_BTN;
            colorBackground2[] = BII_BTN;
            colorBackgroundFocused[] = BII_BTN_F;
            onButtonClick = "['sse'] call comspec_overwatch_atak_athena_fnc_athena_openBiiTab";
            class Attributes { align = "center"; valign = "middle"; };
        };

        class OpenBuilder: BCE_RscButtonMenu
        {
            idc = 9812;
            x = QUOTE(COMSPEC_BII_W(0.08));
            y = QUOTE(COMSPEC_BII_H(5.1));
            w = QUOTE(COMSPEC_BII_W(2.84));
            h = QUOTE(COMSPEC_BII_H(0.72));
            size = QUOTE(COMSPEC_BII_H(0.4));
            text = "Ouvrir Builder";
            colorBackground[] = BII_BTN;
            colorBackground2[] = BII_BTN;
            colorBackgroundFocused[] = BII_BTN_F;
            onButtonClick = "['builder'] call comspec_overwatch_atak_athena_fnc_athena_openBiiTab";
            class Attributes { align = "center"; valign = "middle"; };
        };
    };
};
