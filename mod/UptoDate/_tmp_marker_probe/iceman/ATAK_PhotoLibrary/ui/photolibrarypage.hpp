#ifndef ICEMAN_PHOTO_PHONE_MOD
    #define ICEMAN_PHOTO_PHONE_MOD 1134
#endif
#ifndef QUOTE
    #define QUOTE(var1) #var1
#endif
#ifndef ICEMAN_PHOTO_PHONE_W
    #define ICEMAN_PHOTO_PHONE_W (safezoneW * 0.8)
#endif
#ifndef ICEMAN_PHOTO_PHONE_H
    #define ICEMAN_PHOTO_PHONE_H (ICEMAN_PHOTO_PHONE_W * 4/3)
#endif
#ifndef ICEMAN_PHOTO_SIZE_H
    #define ICEMAN_PHOTO_SIZE_H ((((626) - (60) - (0))) / 2048 * ICEMAN_PHOTO_PHONE_H)
#endif
#ifndef ICEMAN_PHOTO_POS_H
    #define ICEMAN_PHOTO_POS_H (((60)) / 2048 * ICEMAN_PHOTO_PHONE_H)
#endif
#ifndef ICEMAN_PHOTO_POS_W
    #define ICEMAN_PHOTO_POS_W (((ICEMAN_PHOTO_SIZE_H * 0.56)/3))
#endif
#ifndef ICEMAN_PHOTO_CONTAINER_W
    #define ICEMAN_PHOTO_CONTAINER_W(AxisX) AxisX * ICEMAN_PHOTO_POS_W
#endif

class ATAK_Message;
class BCE_RscButtonMenu;
class RscStructuredText;
class RscPicture;
class RscListBox;
class RscCombo;

class Iceman_ATAK_PhotoLibrary: ATAK_Message
{
    class controls
    {
        class Title: BCE_RscButtonMenu
        {
            idc = 9400;
            x = 0;
            y = 0;
            w = QUOTE(ICEMAN_PHOTO_CONTAINER_W(3));
            h = QUOTE(0.70 * ICEMAN_PHOTO_POS_H);
            size = QUOTE(0.60 * ICEMAN_PHOTO_POS_H);
            text = "Photo Library";
            colorBackground[] = {0,0,0,0.55};
            colorBackground2[] = {0,0,0,0.55};
            colorBackgroundFocused[] = {0,0,0,0.8};
            onButtonClick = "call BCE_fnc_ATAK_toggleSubListMenu";
            class Attributes
            {
                align = "center";
                valign = "Bottom";
            };
        };
        class Status: RscStructuredText
        {
            idc = 9401;
            x = QUOTE(ICEMAN_PHOTO_CONTAINER_W(0.08));
            y = QUOTE(0.78 * ICEMAN_PHOTO_POS_H);
            w = QUOTE(ICEMAN_PHOTO_CONTAINER_W(2.84));
            h = QUOTE(0.52 * ICEMAN_PHOTO_POS_H);
            text = "";
            colorBackground[] = {0,0,0,0.24};
            class Attributes
            {
                font = "RobotoCondensed_BCE";
                color = "#E6EEF0";
                align = "center";
                valign = "middle";
                shadow = 1;
            };
        };
        class PhotoList: RscListBox
        {
            idc = 9410;
            x = QUOTE(ICEMAN_PHOTO_CONTAINER_W(0.08));
            y = QUOTE(1.40 * ICEMAN_PHOTO_POS_H);
            w = QUOTE(ICEMAN_PHOTO_CONTAINER_W(2.84));
            h = QUOTE(1.92 * ICEMAN_PHOTO_POS_H);
            sizeEx = QUOTE(0.29 * ICEMAN_PHOTO_POS_H);
            rowHeight = QUOTE(0.44 * ICEMAN_PHOTO_POS_H);
            colorBackground[] = {0.03,0.06,0.07,0.84};
            colorSelect[] = {0,0,0,1};
            colorSelect2[] = {0,0,0,1};
            colorSelectBackground[] = {0.62,0.80,0.86,0.95};
            colorSelectBackground2[] = {0.62,0.80,0.86,0.95};
            pictureColor[] = {1,1,1,1};
            pictureColorSelect[] = {1,1,1,1};
            onLBSelChanged = "_this call Iceman_fnc_photo_onListSelect";
            onLBDblClick = "call Iceman_fnc_photo_toggleExpanded";
        };
        class Preview: RscPicture
        {
            idc = 9420;
            style = 2096;
            x = QUOTE(ICEMAN_PHOTO_CONTAINER_W(0.08));
            y = QUOTE(3.42 * ICEMAN_PHOTO_POS_H);
            w = QUOTE(ICEMAN_PHOTO_CONTAINER_W(2.84));
            h = QUOTE(2.18 * ICEMAN_PHOTO_POS_H);
            text = "\ATAK_PhotoLibrary\data\photo_library_icon_ca.paa";
            colorText[] = {1,1,1,1};
            colorBackground[] = {0.01,0.02,0.02,0.92};
        };
        class Metadata: RscStructuredText
        {
            idc = 9421;
            x = QUOTE(ICEMAN_PHOTO_CONTAINER_W(0.08));
            y = QUOTE(5.70 * ICEMAN_PHOTO_POS_H);
            w = QUOTE(ICEMAN_PHOTO_CONTAINER_W(2.84));
            h = QUOTE(1.16 * ICEMAN_PHOTO_POS_H);
            text = "";
            colorBackground[] = {0,0,0,0.24};
            class Attributes
            {
                font = "RobotoCondensed_BCE";
                color = "#DDE7EA";
                align = "left";
                valign = "top";
                shadow = 1;
            };
        };
        class RecipientLabel: Metadata
        {
            idc = 9430;
            x = QUOTE(ICEMAN_PHOTO_CONTAINER_W(0.08));
            y = QUOTE(6.98 * ICEMAN_PHOTO_POS_H);
            w = QUOTE(ICEMAN_PHOTO_CONTAINER_W(0.58));
            h = QUOTE(0.48 * ICEMAN_PHOTO_POS_H);
            text = "<t align='center'>Send</t>";
            colorBackground[] = {0,0,0,0.30};
        };
        class Recipient: RscCombo
        {
            idc = 9431;
            x = QUOTE(ICEMAN_PHOTO_CONTAINER_W(0.70));
            y = QUOTE(6.98 * ICEMAN_PHOTO_POS_H);
            w = QUOTE(ICEMAN_PHOTO_CONTAINER_W(2.22));
            h = QUOTE(0.48 * ICEMAN_PHOTO_POS_H);
            sizeEx = QUOTE(0.29 * ICEMAN_PHOTO_POS_H);
            colorBackground[] = {0.03,0.06,0.07,0.95};
        };
        class ActionView: BCE_RscButtonMenu
        {
            idc = 9440;
            x = QUOTE(ICEMAN_PHOTO_CONTAINER_W(0.08));
            y = QUOTE(7.60 * ICEMAN_PHOTO_POS_H);
            w = QUOTE(ICEMAN_PHOTO_CONTAINER_W(1.38));
            h = QUOTE(0.58 * ICEMAN_PHOTO_POS_H);
            size = QUOTE(0.31 * ICEMAN_PHOTO_POS_H);
            text = "View";
            tooltip = "Open the selected photo";
            onButtonClick = "call Iceman_fnc_photo_toggleExpanded";
            colorBackground[] = {0.08,0.12,0.14,0.88};
            colorBackground2[] = {0.08,0.12,0.14,0.88};
            colorBackgroundFocused[] = {0.10,0.42,0.50,0.95};
            class Attributes
            {
                align = "center";
                valign = "middle";
            };
        };
        class ActionCamera: ActionView
        {
            idc = 9441;
            x = QUOTE(ICEMAN_PHOTO_CONTAINER_W(1.54));
            text = "Camera";
            tooltip = "Open Quick Pictures";
            onButtonClick = "call Iceman_fnc_photo_openCamera";
        };
        class ActionSend: ActionView
        {
            idc = 9442;
            y = QUOTE(8.30 * ICEMAN_PHOTO_POS_H);
            text = "Send";
            tooltip = "Send the selected photo to the chosen ATAK user";
            onButtonClick = "call Iceman_fnc_photo_sendSelected";
        };
        class ActionDelete: ActionView
        {
            idc = 9443;
            x = QUOTE(ICEMAN_PHOTO_CONTAINER_W(1.54));
            y = QUOTE(8.30 * ICEMAN_PHOTO_POS_H);
            text = "Delete";
            tooltip = "Remove the selected photo from this library";
            onButtonClick = "call Iceman_fnc_photo_deleteSelected";
            colorBackground[] = {0.42,0.12,0.13,0.88};
            colorBackground2[] = {0.42,0.12,0.13,0.88};
            colorBackgroundFocused[] = {0.62,0.16,0.18,0.95};
        };
        class ActionMode: ActionCamera
        {
            idc = 9444;
            text = "Live";
            tooltip = "Switch between the saved JPEG and reconstructed field view";
            onButtonClick = "call Iceman_fnc_photo_togglePreview";
            show = 0;
        };
    };
};
