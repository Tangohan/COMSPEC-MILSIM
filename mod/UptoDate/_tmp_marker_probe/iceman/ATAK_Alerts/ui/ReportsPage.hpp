class ATAK_Message;
class BCE_RscButtonMenu;

// Page dédiée : évite le partage de ATAK_Message avec Messagerie / Tasks
// (sinon createSubPage ne reset pas et les UIs se superposent).
class Iceman_ATAK_Reports: ATAK_Message
{
    class controls
    {
        class Title: BCE_RscButtonMenu
        {
            idc = 5;
            x = 0;
            y = 0;
            w = 1;
            h = 0;
            text = "Reports";
            colorBackground[] = {0, 0, 0, 0};
            colorBackground2[] = {0, 0, 0, 0};
            colorBackgroundFocused[] = {0, 0, 0, 0};
            onButtonClick = "";
        };
    };
};
