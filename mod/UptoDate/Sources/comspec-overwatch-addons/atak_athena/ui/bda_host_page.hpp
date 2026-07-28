// Conteneur vide pour BDA Report (Iceman remplit dynamiquement le groupe).
#ifndef QUOTE
    #define QUOTE(var1) #var1
#endif

#ifndef COMSPEC_BDA_PHONE_W
    #define COMSPEC_BDA_PHONE_W (safezoneW * 0.8)
#endif
#ifndef COMSPEC_BDA_PHONE_H
    #define COMSPEC_BDA_PHONE_H (COMSPEC_BDA_PHONE_W * 4/3)
#endif
#ifndef COMSPEC_BDA_SIZE_H
    #define COMSPEC_BDA_SIZE_H ((((626) - (60) - (0))) / 2048 * COMSPEC_BDA_PHONE_H)
#endif
#ifndef COMSPEC_BDA_POS_H
    #define COMSPEC_BDA_POS_H (((60)) / 2048 * COMSPEC_BDA_PHONE_H)
#endif
#ifndef COMSPEC_BDA_POS_W
    #define COMSPEC_BDA_POS_W (((COMSPEC_BDA_SIZE_H * 0.56)/3))
#endif
#ifndef COMSPEC_BDA_W
    #define COMSPEC_BDA_W(n) ((n) * COMSPEC_BDA_POS_W)
#endif
#ifndef COMSPEC_BDA_H
    #define COMSPEC_BDA_H(n) ((n) * COMSPEC_BDA_POS_H)
#endif

class COMSPEC_ATAK_BdaHost: ATAK_Message
{
    class controls
    {
        class Placeholder: RscStructuredText
        {
            idc = 9860;
            x = QUOTE(COMSPEC_BDA_W(0.08));
            y = QUOTE(COMSPEC_BDA_H(0.20));
            w = QUOTE(COMSPEC_BDA_W(2.84));
            h = QUOTE(COMSPEC_BDA_H(1.20));
            text = "<t align='center'>Chargement du rapport BDA…</t>";
            colorBackground[] = {0.02, 0.05, 0.07, 0.85};
            class Attributes
            {
                font = "RobotoCondensed";
                color = "#D8E4EA";
                align = "center";
                valign = "middle";
                size = "0.72";
            };
        };
    };
};
