// Choix Message : P2P réseau local (IceMan) ou Via Athena (compte à compte).
#ifndef QUOTE
    #define QUOTE(var1) #var1
#endif

#ifndef COMSPEC_MSGHUB_PHONE_W
    #define COMSPEC_MSGHUB_PHONE_W (safezoneW * 0.8)
#endif
#ifndef COMSPEC_MSGHUB_PHONE_H
    #define COMSPEC_MSGHUB_PHONE_H (COMSPEC_MSGHUB_PHONE_W * 4/3)
#endif
#ifndef COMSPEC_MSGHUB_SIZE_H
    #define COMSPEC_MSGHUB_SIZE_H ((((626) - (60) - (0))) / 2048 * COMSPEC_MSGHUB_PHONE_H)
#endif
#ifndef COMSPEC_MSGHUB_POS_H
    #define COMSPEC_MSGHUB_POS_H (((60)) / 2048 * COMSPEC_MSGHUB_PHONE_H)
#endif
#ifndef COMSPEC_MSGHUB_POS_W
    #define COMSPEC_MSGHUB_POS_W (((COMSPEC_MSGHUB_SIZE_H * 0.56)/3))
#endif
#ifndef COMSPEC_MSGHUB_W
    #define COMSPEC_MSGHUB_W(n) ((n) * COMSPEC_MSGHUB_POS_W)
#endif
#ifndef COMSPEC_MSGHUB_H
    #define COMSPEC_MSGHUB_H(n) ((n) * COMSPEC_MSGHUB_POS_H)
#endif

class COMSPEC_ATAK_MessageHub: ATAK_Message
{
    class controls
    {
        class Title: COMSPEC_ATAK_Title
        {
            idc = 9930;
            x = 0;
            y = 0;
            w = QUOTE(COMSPEC_MSGHUB_W(3));
            h = QUOTE(COMSPEC_MSGHUB_H(0.52));
            size = QUOTE(COMSPEC_MSGHUB_H(0.34));
            text = "  Message";
            onButtonClick = "call BCE_fnc_ATAK_toggleSubListMenu";
            tooltip = "Revenir au tiroir des applications.";
        };

        class AccentBar: RscText
        {
            idc = -1;
            x = 0;
            y = QUOTE(COMSPEC_MSGHUB_H(0.52));
            w = QUOTE(COMSPEC_MSGHUB_W(3));
            h = QUOTE(COMSPEC_MSGHUB_H(0.05));
            colorBackground[] = ATAK_ACCENT;
        };

        class Hint: RscStructuredText
        {
            idc = 9931;
            x = QUOTE(COMSPEC_MSGHUB_W(0.10));
            y = QUOTE(COMSPEC_MSGHUB_H(0.68));
            w = QUOTE(COMSPEC_MSGHUB_W(2.80));
            h = QUOTE(COMSPEC_MSGHUB_H(0.70));
            size = QUOTE(COMSPEC_MSGHUB_H(0.24));
            text = "<t align='center'>Choisissez comment envoyer.</t>";
            colorBackground[] = {0.05, 0.08, 0.10, 0.96};
            class Attributes
            {
                font = "RobotoCondensed";
                color = "#F0F6FA";
                align = "center";
                valign = "middle";
            };
        };

        class BtnP2P: COMSPEC_ATAK_Btn
        {
            idc = 9932;
            x = QUOTE(COMSPEC_MSGHUB_W(0.10));
            y = QUOTE(COMSPEC_MSGHUB_H(1.55));
            w = QUOTE(COMSPEC_MSGHUB_W(2.80));
            h = QUOTE(COMSPEC_MSGHUB_H(1.55));
            size = QUOTE(COMSPEC_MSGHUB_H(0.30));
            text = "P2P — Réseau local";
            tooltip = "Messages entre téléphones à proximité, comme d’habitude.";
            onButtonClick = "[] call comspec_overwatch_atak_athena_fnc_athena_messageHubOpenP2P";
        };

        class HintP2P: RscStructuredText
        {
            idc = 9933;
            x = QUOTE(COMSPEC_MSGHUB_W(0.16));
            y = QUOTE(COMSPEC_MSGHUB_H(3.16));
            w = QUOTE(COMSPEC_MSGHUB_W(2.68));
            h = QUOTE(COMSPEC_MSGHUB_H(0.70));
            size = QUOTE(COMSPEC_MSGHUB_H(0.22));
            text = "<t align='center'>Téléphone à téléphone, autour de vous.</t>";
            colorBackground[] = {0, 0, 0, 0};
            class Attributes
            {
                font = "RobotoCondensed";
                color = "#A8B8C4";
                align = "center";
            };
        };

        class BtnAthena: COMSPEC_ATAK_BtnGo
        {
            idc = 9934;
            x = QUOTE(COMSPEC_MSGHUB_W(0.10));
            y = QUOTE(COMSPEC_MSGHUB_H(4.00));
            w = QUOTE(COMSPEC_MSGHUB_W(2.80));
            h = QUOTE(COMSPEC_MSGHUB_H(1.55));
            size = QUOTE(COMSPEC_MSGHUB_H(0.30));
            text = "Via Athena";
            tooltip = "Messages de compte à compte, comme au poste.";
            onButtonClick = "[] call comspec_overwatch_atak_athena_fnc_athena_messageHubOpenAthena";
        };

        class HintAthena: RscStructuredText
        {
            idc = 9935;
            x = QUOTE(COMSPEC_MSGHUB_W(0.16));
            y = QUOTE(COMSPEC_MSGHUB_H(5.62));
            w = QUOTE(COMSPEC_MSGHUB_W(2.68));
            h = QUOTE(COMSPEC_MSGHUB_H(0.80));
            size = QUOTE(COMSPEC_MSGHUB_H(0.22));
            text = "<t align='center'>De compte à compte, même hors proximité.</t>";
            colorBackground[] = {0, 0, 0, 0};
            class Attributes
            {
                font = "RobotoCondensed";
                color = "#A8B8C4";
                align = "center";
            };
        };
    };
};
