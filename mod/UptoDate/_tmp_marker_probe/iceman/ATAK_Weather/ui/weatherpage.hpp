#ifndef ICEMAN_WEATHER_PHONE_MOD
    #define ICEMAN_WEATHER_PHONE_MOD 1134
#endif
#ifndef QUOTE
    #define QUOTE(var1) #var1
#endif
#ifndef ICEMAN_WEATHER_PHONE_W
    #define ICEMAN_WEATHER_PHONE_W (safezoneW * 0.8)
#endif
#ifndef ICEMAN_WEATHER_PHONE_H
    #define ICEMAN_WEATHER_PHONE_H (ICEMAN_WEATHER_PHONE_W * 4/3)
#endif
#ifndef ICEMAN_WEATHER_SIZE_H
    #define ICEMAN_WEATHER_SIZE_H ((((626) - (60) - (0))) / 2048 * ICEMAN_WEATHER_PHONE_H)
#endif
#ifndef ICEMAN_WEATHER_POS_H
    #define ICEMAN_WEATHER_POS_H (((60)) / 2048 * ICEMAN_WEATHER_PHONE_H)
#endif
#ifndef ICEMAN_WEATHER_POS_W
    #define ICEMAN_WEATHER_POS_W (((ICEMAN_WEATHER_SIZE_H * 0.56)/3))
#endif
#ifndef ICEMAN_WEATHER_CONTAINER_W
    #define ICEMAN_WEATHER_CONTAINER_W(AxisX) AxisX * ICEMAN_WEATHER_POS_W
#endif

class ATAK_Message;
class BCE_RscButtonMenu;
class RscStructuredText;
class RscPicture;
class RscListBox;

class Iceman_ATAK_Weather: ATAK_Message
{
    class controls
    {
        class Title: BCE_RscButtonMenu
        {
            idc = 9300;
            x = 0;
            y = 0;
            w = QUOTE(ICEMAN_WEATHER_CONTAINER_W(3));
            h = QUOTE(0.70 * ICEMAN_WEATHER_POS_H);
            size = QUOTE(0.62 * ICEMAN_WEATHER_POS_H);
            text = "Weather";
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
        class Condition: RscStructuredText
        {
            idc = 9301;
            x = QUOTE(ICEMAN_WEATHER_CONTAINER_W(0.08));
            y = QUOTE(0.80 * ICEMAN_WEATHER_POS_H);
            w = QUOTE(ICEMAN_WEATHER_CONTAINER_W(2.84));
            h = QUOTE(0.62 * ICEMAN_WEATHER_POS_H);
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
        class CurrentIcon: RscPicture
        {
            idc = 9310;
            style = 2096;
            x = QUOTE(ICEMAN_WEATHER_CONTAINER_W(0.12));
            y = QUOTE(1.58 * ICEMAN_WEATHER_POS_H);
            w = QUOTE(ICEMAN_WEATHER_CONTAINER_W(0.92));
            h = QUOTE(1.52 * ICEMAN_WEATHER_POS_H);
            text = "\BCE_Core\data\sunny.paa";
            colorText[] = {1,1,1,1};
        };
        class Current: RscStructuredText
        {
            idc = 9311;
            x = QUOTE(ICEMAN_WEATHER_CONTAINER_W(1.10));
            y = QUOTE(1.58 * ICEMAN_WEATHER_POS_H);
            w = QUOTE(ICEMAN_WEATHER_CONTAINER_W(1.82));
            h = QUOTE(0.82 * ICEMAN_WEATHER_POS_H);
            text = "";
            colorBackground[] = {0,0,0,0.18};
            class Attributes
            {
                font = "RobotoCondensed_BCE";
                color = "#FFFFFF";
                align = "left";
                valign = "middle";
                shadow = 1;
            };
        };
        class Metrics: Current
        {
            idc = 9312;
            y = QUOTE(2.46 * ICEMAN_WEATHER_POS_H);
            h = QUOTE(0.88 * ICEMAN_WEATHER_POS_H);
            colorBackground[] = {0,0,0,0.24};
        };
        class ForecastHeader: Condition
        {
            idc = 9320;
            y = QUOTE(3.52 * ICEMAN_WEATHER_POS_H);
            h = QUOTE(0.48 * ICEMAN_WEATHER_POS_H);
            text = "<t align='left' color='#b8e8ef'>3-hour forecast</t>";
        };
        class ForecastList: RscListBox
        {
            idc = 9321;
            x = QUOTE(ICEMAN_WEATHER_CONTAINER_W(0.08));
            y = QUOTE(4.08 * ICEMAN_WEATHER_POS_H);
            w = QUOTE(ICEMAN_WEATHER_CONTAINER_W(2.84));
            h = QUOTE(3.32 * ICEMAN_WEATHER_POS_H);
            sizeEx = QUOTE(0.30 * ICEMAN_WEATHER_POS_H);
            rowHeight = QUOTE(0.68 * ICEMAN_WEATHER_POS_H);
            colorBackground[] = {0.03,0.06,0.07,0.84};
            colorSelect[] = {0.88,0.95,0.97,1};
            colorSelect2[] = {0.88,0.95,0.97,1};
            colorSelectBackground[] = {0.10,0.24,0.28,0.90};
            colorSelectBackground2[] = {0.10,0.24,0.28,0.90};
            pictureColor[] = {1,1,1,1};
            pictureColorSelect[] = {1,1,1,1};
        };
        class Transition: Condition
        {
            idc = 9330;
            y = QUOTE(7.58 * ICEMAN_WEATHER_POS_H);
            h = QUOTE(0.64 * ICEMAN_WEATHER_POS_H);
            text = "";
        };
        class Refresh: BCE_RscButtonMenu
        {
            idc = 9331;
            x = QUOTE(ICEMAN_WEATHER_CONTAINER_W(0.08));
            y = QUOTE(8.38 * ICEMAN_WEATHER_POS_H);
            w = QUOTE(ICEMAN_WEATHER_CONTAINER_W(2.84));
            h = QUOTE(0.58 * ICEMAN_WEATHER_POS_H);
            size = QUOTE(0.31 * ICEMAN_WEATHER_POS_H);
            text = "Refresh";
            tooltip = "Refresh current mission weather";
            onButtonClick = "call Iceman_fnc_weather_updatePanel";
            colorBackground[] = {0.08,0.12,0.14,0.88};
            colorBackground2[] = {0.08,0.12,0.14,0.88};
            colorBackgroundFocused[] = {0.10,0.42,0.50,0.95};
            class Attributes
            {
                align = "center";
                valign = "middle";
            };
        };
    };
};
