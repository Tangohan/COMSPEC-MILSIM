// App « Fieldwatch » — scan RF passif (Wi‑Fi / BLE simulés).
#ifndef QUOTE
    #define QUOTE(var1) #var1
#endif

#ifndef COMSPEC_FW_PHONE_W
    #define COMSPEC_FW_PHONE_W (safezoneW * 0.8)
#endif
#ifndef COMSPEC_FW_PHONE_H
    #define COMSPEC_FW_PHONE_H (COMSPEC_FW_PHONE_W * 4/3)
#endif
#ifndef COMSPEC_FW_SIZE_H
    #define COMSPEC_FW_SIZE_H ((((626) - (60) - (0))) / 2048 * COMSPEC_FW_PHONE_H)
#endif
#ifndef COMSPEC_FW_POS_H
    #define COMSPEC_FW_POS_H (((60)) / 2048 * COMSPEC_FW_PHONE_H)
#endif
#ifndef COMSPEC_FW_POS_W
    #define COMSPEC_FW_POS_W (((COMSPEC_FW_SIZE_H * 0.56)/3))
#endif
#ifndef COMSPEC_FW_W
    #define COMSPEC_FW_W(n) ((n) * COMSPEC_FW_POS_W)
#endif
#ifndef COMSPEC_FW_H
    #define COMSPEC_FW_H(n) ((n) * COMSPEC_FW_POS_H)
#endif

class COMSPEC_ATAK_Fieldwatch: ATAK_Message
{
    class controls
    {
        class Title: COMSPEC_ATAK_Title
        {
            idc = 9920;
            x = 0;
            y = 0;
            w = QUOTE(COMSPEC_FW_W(3));
            h = QUOTE(COMSPEC_FW_H(0.62));
            size = QUOTE(COMSPEC_FW_H(0.36));
            text = "  Fieldwatch";
            onButtonClick = "call BCE_fnc_ATAK_toggleSubListMenu";
            tooltip = "Revenir au tiroir des applications.";
        };

        class AccentBar: RscText
        {
            idc = -1;
            x = 0;
            y = QUOTE(COMSPEC_FW_H(0.62));
            w = QUOTE(COMSPEC_FW_W(3));
            h = QUOTE(COMSPEC_FW_H(0.06));
            colorBackground[] = ATAK_ACCENT;
        };

        class Body: RscStructuredText
        {
            idc = 9921;
            x = QUOTE(COMSPEC_FW_W(0.06));
            y = QUOTE(COMSPEC_FW_H(0.74));
            w = QUOTE(COMSPEC_FW_W(2.88));
            h = QUOTE(COMSPEC_FW_H(7.20));
            text = "";
            colorBackground[] = ATAK_BG_DETAIL;
            size = QUOTE(COMSPEC_FW_H(0.28));
            class Attributes
            {
                font = "RobotoCondensed";
                color = "#E8F2FA";
                align = "left";
                size = 0.92;
            };
        };
    };
};
