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

#define BII_BG_TITLE ATAK_BG_TITLE
#define BII_BG_BODY ATAK_BG_DETAIL
#define BII_BTN ATAK_BG_TILE
#define BII_BTN_F ATAK_BG_TILE_F
#define BII_ACCENT ATAK_ACCENT

class COMSPEC_ATAK_BII: ATAK_Message
{
    class controls
    {
        class Title: COMSPEC_ATAK_Title
        {
            idc = 9800;
            x = 0;
            y = 0;
            w = QUOTE(COMSPEC_BII_W(3));
            h = QUOTE(COMSPEC_BII_H(0.62));
            size = QUOTE(COMSPEC_BII_H(0.40));
            text = "  BII-10 / SEEK II";
            onButtonClick = "call BCE_fnc_ATAK_toggleSubListMenu";
            tooltip = "Revenir au tiroir des applications.";
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

        class PanelFill: RscText
        {
            idc = -1;
            x = 0;
            y = QUOTE(COMSPEC_BII_H(0.68));
            w = QUOTE(COMSPEC_BII_W(3));
            h = QUOTE(COMSPEC_BII_H(7.40));
            colorBackground[] = ATAK_BG_PANEL;
        };

        class Body: RscStructuredText
        {
            idc = 9801;
            x = QUOTE(COMSPEC_BII_W(0.08));
            y = QUOTE(COMSPEC_BII_H(0.82));
            w = QUOTE(COMSPEC_BII_W(2.84));
            h = QUOTE(COMSPEC_BII_H(2.20));
            colorBackground[] = {0, 0, 0, 0};
            text = "";
        };

        class OpenIdentify: COMSPEC_ATAK_Btn
        {
            idc = 9810;
            x = QUOTE(COMSPEC_BII_W(0.08));
            y = QUOTE(COMSPEC_BII_H(3.18));
            w = QUOTE(COMSPEC_BII_W(1.38));
            h = QUOTE(COMSPEC_BII_H(1.28));
            size = QUOTE(COMSPEC_BII_H(0.36));
            text = "Identifier";
            onButtonClick = "['scan'] call comspec_overwatch_atak_athena_fnc_athena_openBiiTab";
        };

        class OpenSse: COMSPEC_ATAK_Btn
        {
            idc = 9811;
            x = QUOTE(COMSPEC_BII_W(1.54));
            y = QUOTE(COMSPEC_BII_H(3.18));
            w = QUOTE(COMSPEC_BII_W(1.38));
            h = QUOTE(COMSPEC_BII_H(1.28));
            size = QUOTE(COMSPEC_BII_H(0.36));
            text = "Saisies";
            onButtonClick = "['sse'] call comspec_overwatch_atak_athena_fnc_athena_openBiiTab";
        };

        class OpenBuilder: COMSPEC_ATAK_Btn
        {
            idc = 9812;
            x = QUOTE(COMSPEC_BII_W(0.08));
            y = QUOTE(COMSPEC_BII_H(4.58));
            w = QUOTE(COMSPEC_BII_W(2.84));
            h = QUOTE(COMSPEC_BII_H(1.10));
            size = QUOTE(COMSPEC_BII_H(0.38));
            text = "Dossiers";
            onButtonClick = "['builder'] call comspec_overwatch_atak_athena_fnc_athena_openBiiTab";
        };
    };
};
