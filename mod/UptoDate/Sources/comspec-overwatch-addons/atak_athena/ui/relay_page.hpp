// App « Relais AT » — mât le plus proche, fiche, destruction.
#ifndef QUOTE
    #define QUOTE(var1) #var1
#endif

#ifndef COMSPEC_RELAY_PHONE_W
    #define COMSPEC_RELAY_PHONE_W (safezoneW * 0.8)
#endif
#ifndef COMSPEC_RELAY_PHONE_H
    #define COMSPEC_RELAY_PHONE_H (COMSPEC_RELAY_PHONE_W * 4/3)
#endif
#ifndef COMSPEC_RELAY_SIZE_H
    #define COMSPEC_RELAY_SIZE_H ((((626) - (60) - (0))) / 2048 * COMSPEC_RELAY_PHONE_H)
#endif
#ifndef COMSPEC_RELAY_POS_H
    #define COMSPEC_RELAY_POS_H (((60)) / 2048 * COMSPEC_RELAY_PHONE_H)
#endif
#ifndef COMSPEC_RELAY_POS_W
    #define COMSPEC_RELAY_POS_W (((COMSPEC_RELAY_SIZE_H * 0.56)/3))
#endif
#ifndef COMSPEC_RELAY_W
    #define COMSPEC_RELAY_W(n) ((n) * COMSPEC_RELAY_POS_W)
#endif
#ifndef COMSPEC_RELAY_H
    #define COMSPEC_RELAY_H(n) ((n) * COMSPEC_RELAY_POS_H)
#endif

class COMSPEC_ATAK_Relay: ATAK_Message
{
    class controls
    {
        class Title: COMSPEC_ATAK_Title
        {
            idc = 9910;
            x = 0;
            y = 0;
            w = QUOTE(COMSPEC_RELAY_W(3));
            h = QUOTE(COMSPEC_RELAY_H(0.62));
            size = QUOTE(COMSPEC_RELAY_H(0.36));
            text = "  Relais AT";
            onButtonClick = "call BCE_fnc_ATAK_toggleSubListMenu";
            tooltip = "Revenir au tiroir des applications.";
        };

        class AccentBar: RscText
        {
            idc = -1;
            x = 0;
            y = QUOTE(COMSPEC_RELAY_H(0.62));
            w = QUOTE(COMSPEC_RELAY_W(3));
            h = QUOTE(COMSPEC_RELAY_H(0.06));
            colorBackground[] = ATAK_ACCENT;
        };

        class Body: RscStructuredText
        {
            idc = 9911;
            x = QUOTE(COMSPEC_RELAY_W(0.06));
            y = QUOTE(COMSPEC_RELAY_H(0.74));
            w = QUOTE(COMSPEC_RELAY_W(2.88));
            h = QUOTE(COMSPEC_RELAY_H(7.20));
            text = "";
            colorBackground[] = ATAK_BG_DETAIL;
            size = QUOTE(COMSPEC_RELAY_H(0.28));
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
