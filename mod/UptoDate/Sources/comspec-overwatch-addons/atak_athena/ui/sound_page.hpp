// App « Sons ATAK » — style d’alerte, volumes, mode discret.
#ifndef QUOTE
    #define QUOTE(var1) #var1
#endif

#ifndef COMSPEC_SOUND_PHONE_W
    #define COMSPEC_SOUND_PHONE_W (safezoneW * 0.8)
#endif
#ifndef COMSPEC_SOUND_PHONE_H
    #define COMSPEC_SOUND_PHONE_H (COMSPEC_SOUND_PHONE_W * 4/3)
#endif
#ifndef COMSPEC_SOUND_SIZE_H
    #define COMSPEC_SOUND_SIZE_H ((((626) - (60) - (0))) / 2048 * COMSPEC_SOUND_PHONE_H)
#endif
#ifndef COMSPEC_SOUND_POS_H
    #define COMSPEC_SOUND_POS_H (((60)) / 2048 * COMSPEC_SOUND_PHONE_H)
#endif
#ifndef COMSPEC_SOUND_POS_W
    #define COMSPEC_SOUND_POS_W (((COMSPEC_SOUND_SIZE_H * 0.56)/3))
#endif
#ifndef COMSPEC_SOUND_W
    #define COMSPEC_SOUND_W(n) ((n) * COMSPEC_SOUND_POS_W)
#endif
#ifndef COMSPEC_SOUND_H
    #define COMSPEC_SOUND_H(n) ((n) * COMSPEC_SOUND_POS_H)
#endif

#define SOUND_BG_TITLE ATAK_BG_TITLE
#define SOUND_BG_STRIP ATAK_BG_STRIP
#define SOUND_BG_BODY ATAK_BG_DETAIL
#define SOUND_BTN ATAK_BTN
#define SOUND_BTN_F ATAK_BTN_F
#define SOUND_BTN_DIM {0.20, 0.20, 0.20, 1}
#define SOUND_ACCENT ATAK_ACCENT

class COMSPEC_ATAK_Sound: ATAK_Message
{
    class controls
    {
        class Title: COMSPEC_ATAK_Title
        {
            idc = 9820;
            x = 0;
            y = 0;
            w = QUOTE(COMSPEC_SOUND_W(3));
            h = QUOTE(COMSPEC_SOUND_H(0.58));
            size = QUOTE(COMSPEC_SOUND_H(0.36));
            text = "  Sons ATAK";
            onButtonClick = "call BCE_fnc_ATAK_toggleSubListMenu";
            tooltip = "Revenir au tiroir des applications.";
        };

        class AccentBar: RscText
        {
            idc = -1;
            x = 0;
            y = QUOTE(COMSPEC_SOUND_H(0.58));
            w = QUOTE(COMSPEC_SOUND_W(3));
            h = QUOTE(COMSPEC_SOUND_H(0.05));
            colorBackground[] = SOUND_ACCENT;
        };

        class Summary: RscStructuredText
        {
            idc = 9821;
            x = QUOTE(COMSPEC_SOUND_W(0.08));
            y = QUOTE(COMSPEC_SOUND_H(0.72));
            w = QUOTE(COMSPEC_SOUND_W(2.84));
            h = QUOTE(COMSPEC_SOUND_H(0.85));
            text = "";
            colorBackground[] = SOUND_BG_STRIP;
            class Attributes
            {
                font = "RobotoCondensed";
                color = "#E6EEF0";
                align = "center";
                valign = "middle";
                shadow = 1;
                size = "0.70";
            };
        };

        class BtnStyle: COMSPEC_ATAK_Btn
        {
            idc = 9822;
            x = QUOTE(COMSPEC_SOUND_W(0.08));
            y = QUOTE(COMSPEC_SOUND_H(1.68));
            w = QUOTE(COMSPEC_SOUND_W(2.84));
            h = QUOTE(COMSPEC_SOUND_H(0.48));
            size = QUOTE(COMSPEC_SOUND_H(0.28));
            text = "Style d’alerte";
            colorBackground[] = SOUND_BTN;
            colorBackground2[] = SOUND_BTN;
            colorBackgroundFocused[] = SOUND_BTN_F;
            onButtonClick = "['cycle_style'] call comspec_overwatch_atak_athena_fnc_athena_soundAction";
        };

        class LblMaster: RscStructuredText
        {
            idc = 9823;
            x = QUOTE(COMSPEC_SOUND_W(0.08));
            y = QUOTE(COMSPEC_SOUND_H(2.26));
            w = QUOTE(COMSPEC_SOUND_W(1.70));
            h = QUOTE(COMSPEC_SOUND_H(0.42));
            text = "Volume général";
            colorBackground[] = SOUND_BG_BODY;
            class Attributes
            {
                font = "RobotoCondensed";
                color = "#D8E4EA";
                align = "left";
                valign = "middle";
                size = "0.68";
            };
        };
        class BtnMasterDown: COMSPEC_ATAK_Btn
        {
            idc = 9824;
            x = QUOTE(COMSPEC_SOUND_W(1.82));
            y = QUOTE(COMSPEC_SOUND_H(2.26));
            w = QUOTE(COMSPEC_SOUND_W(0.50));
            h = QUOTE(COMSPEC_SOUND_H(0.42));
            size = QUOTE(COMSPEC_SOUND_H(0.28));
            text = "−";
            colorBackground[] = SOUND_BTN_DIM;
            colorBackground2[] = SOUND_BTN_DIM;
            colorBackgroundFocused[] = SOUND_BTN_F;
            onButtonClick = "['vol_master', -0.1] call comspec_overwatch_atak_athena_fnc_athena_soundAction";
        };
        class BtnMasterUp: BtnMasterDown
        {
            idc = 9825;
            x = QUOTE(COMSPEC_SOUND_W(2.40));
            text = "+";
            onButtonClick = "['vol_master', 0.1] call comspec_overwatch_atak_athena_fnc_athena_soundAction";
        };

        class LblNotif: LblMaster
        {
            idc = 9826;
            y = QUOTE(COMSPEC_SOUND_H(2.76));
            text = "Volume alertes";
        };
        class BtnNotifDown: BtnMasterDown
        {
            idc = 9827;
            y = QUOTE(COMSPEC_SOUND_H(2.76));
            onButtonClick = "['vol_notif', -0.1] call comspec_overwatch_atak_athena_fnc_athena_soundAction";
        };
        class BtnNotifUp: BtnMasterUp
        {
            idc = 9828;
            y = QUOTE(COMSPEC_SOUND_H(2.76));
            onButtonClick = "['vol_notif', 0.1] call comspec_overwatch_atak_athena_fnc_athena_soundAction";
        };

        class LblVib: LblMaster
        {
            idc = 9829;
            y = QUOTE(COMSPEC_SOUND_H(3.26));
            text = "Volume vibration";
        };
        class BtnVibDown: BtnMasterDown
        {
            idc = 9830;
            y = QUOTE(COMSPEC_SOUND_H(3.26));
            onButtonClick = "['vol_vibrate', -0.1] call comspec_overwatch_atak_athena_fnc_athena_soundAction";
        };
        class BtnVibUp: BtnMasterUp
        {
            idc = 9831;
            y = QUOTE(COMSPEC_SOUND_H(3.26));
            onButtonClick = "['vol_vibrate', 0.1] call comspec_overwatch_atak_athena_fnc_athena_soundAction";
        };

        class LblFx: LblMaster
        {
            idc = 9832;
            y = QUOTE(COMSPEC_SOUND_H(3.76));
            text = "Volume effets";
        };
        class BtnFxDown: BtnMasterDown
        {
            idc = 9833;
            y = QUOTE(COMSPEC_SOUND_H(3.76));
            onButtonClick = "['vol_fx', -0.1] call comspec_overwatch_atak_athena_fnc_athena_soundAction";
        };
        class BtnFxUp: BtnMasterUp
        {
            idc = 9834;
            y = QUOTE(COMSPEC_SOUND_H(3.76));
            onButtonClick = "['vol_fx', 0.1] call comspec_overwatch_atak_athena_fnc_athena_soundAction";
        };

        class BtnQuiet: COMSPEC_ATAK_Btn
        {
            idc = 9835;
            x = QUOTE(COMSPEC_SOUND_W(0.08));
            y = QUOTE(COMSPEC_SOUND_H(4.30));
            w = QUOTE(COMSPEC_SOUND_W(1.38));
            h = QUOTE(COMSPEC_SOUND_H(0.46));
            size = QUOTE(COMSPEC_SOUND_H(0.26));
            text = "Mode discret";
            colorBackground[] = SOUND_BTN_DIM;
            colorBackground2[] = SOUND_BTN_DIM;
            colorBackgroundFocused[] = SOUND_BTN_F;
            onButtonClick = "['toggle_quiet'] call comspec_overwatch_atak_athena_fnc_athena_soundAction";
        };
        class BtnScreen: BtnQuiet
        {
            idc = 9836;
            x = QUOTE(COMSPEC_SOUND_W(1.54));
            text = "Notifs écran";
            onButtonClick = "['toggle_screen'] call comspec_overwatch_atak_athena_fnc_athena_soundAction";
        };

        class BtnRoleplay: COMSPEC_ATAK_Btn
        {
            idc = 9837;
            x = QUOTE(COMSPEC_SOUND_W(0.08));
            y = QUOTE(COMSPEC_SOUND_H(4.86));
            w = QUOTE(COMSPEC_SOUND_W(2.84));
            h = QUOTE(COMSPEC_SOUND_H(0.46));
            size = QUOTE(COMSPEC_SOUND_H(0.26));
            text = "Effets sonores de zone";
            colorBackground[] = SOUND_BTN_DIM;
            colorBackground2[] = SOUND_BTN_DIM;
            colorBackgroundFocused[] = SOUND_BTN_F;
            onButtonClick = "['toggle_roleplay'] call comspec_overwatch_atak_athena_fnc_athena_soundAction";
        };

        class BtnTest: COMSPEC_ATAK_Btn
        {
            idc = 9838;
            x = QUOTE(COMSPEC_SOUND_W(0.08));
            y = QUOTE(COMSPEC_SOUND_H(5.44));
            w = QUOTE(COMSPEC_SOUND_W(1.38));
            h = QUOTE(COMSPEC_SOUND_H(0.50));
            size = QUOTE(COMSPEC_SOUND_H(0.28));
            text = "Tester";
            colorBackground[] = SOUND_BTN;
            colorBackground2[] = SOUND_BTN;
            colorBackgroundFocused[] = SOUND_BTN_F;
            onButtonClick = "['test'] call comspec_overwatch_atak_athena_fnc_athena_soundAction";
        };
        class BtnStatus: BtnTest
        {
            idc = 9839;
            x = QUOTE(COMSPEC_SOUND_W(1.54));
            text = "État ATAK";
            onButtonClick = "[] call comspec_overwatch_atak_athena_fnc_athena_openStatus";
        };

        class Help: RscStructuredText
        {
            idc = 9840;
            x = QUOTE(COMSPEC_SOUND_W(0.08));
            y = QUOTE(COMSPEC_SOUND_H(6.06));
            w = QUOTE(COMSPEC_SOUND_W(2.84));
            h = QUOTE(COMSPEC_SOUND_H(1.50));
            text = "";
            colorBackground[] = SOUND_BG_BODY;
            class Attributes
            {
                font = "RobotoCondensed";
                color = "#A8B8C4";
                align = "left";
                valign = "top";
                shadow = 1;
                size = "0.62";
            };
        };
    };
};
