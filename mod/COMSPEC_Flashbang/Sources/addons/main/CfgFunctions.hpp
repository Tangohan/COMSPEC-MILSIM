// Fonctions comspec_fb_fnc_<nom> (fichiers functions\fn_<nom>.sqf).
class CfgFunctions {
    class comspec_fb {
        class lancer {
            file = "\z\comspec_flashbang\addons\main\functions";
            class fired {};
            class fuseEnd {};
            class serverDetonate {};
            class serverArea {};
            class targets {};
        };
        class detonations {
            file = "\z\comspec_flashbang\addons\main\functions";
            class timeline {};
            class cancel {};
            class burst {};
            class envProbe {};
            class roofAbove {};
            class fx {};
            class playBang {};
        };
        class effets {
            file = "\z\comspec_flashbang\addons\main\functions";
            class exposure {};
            class chanValue {};
            class playerHit {};
            class playerLoop {};
            class playerReset {};
            class tinnitus {};
            class hearing {};
            class glare {};
            class aiHit {};
            class aiRestore {};
        };
        class editeur {
            file = "\z\comspec_flashbang\addons\main\functions";
            class moduleEden {};
            class zenRegister {};
            class log {};
        };
    };
};
