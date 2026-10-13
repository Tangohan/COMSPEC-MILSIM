// Gestes du joueur (ATAK natif 1.34.0) : doigt d'honneur (bras gauche, compatible fusil en main) et selfie
// (bras droit tendu, téléphone face au visage). Animations maison : anim/*.rtm, générées par
// tools/gen_gestures.py (non binarisées, le jeu les lit telles quelles).
#include "gestures_aim.hpp"
class CfgMovesBasic {
    class ManActions {
        GestureComspecFinger = "GestureComspecFinger";
        GestureComspecSelfieUp = "GestureComspecSelfieUp";
        GestureComspecSelfie = "GestureComspecSelfie";
        GestureComspecSelfieDown = "GestureComspecSelfieDown";
        COMSPEC_AIM_ACTIONS
    };
    class Actions {
        class NoActions: ManActions {
            GestureComspecFinger[] = {"GestureComspecFinger", "Gesture"};
            GestureComspecSelfieUp[] = {"GestureComspecSelfieUp", "Gesture"};
            GestureComspecSelfie[] = {"GestureComspecSelfie", "Gesture"};
            GestureComspecSelfieDown[] = {"GestureComspecSelfieDown", "Gesture"};
            COMSPEC_AIM_NOACTIONS
        };
    };
};


class CfgGesturesMale {
    class Default;
    class BlendAnims {
        comspec_leftArm[] = {
            "LeftShoulder", 1, "LeftArm", 1, "LeftArmRoll", 1, "LeftForeArm", 1, "LeftForeArmRoll", 1, "LeftHand", 1,
            "LeftHandThumb1", 1, "LeftHandThumb2", 1, "LeftHandThumb3", 1,
            "LeftHandIndex1", 1, "LeftHandIndex2", 1, "LeftHandIndex3", 1,
            "LeftHandMiddle1", 1, "LeftHandMiddle2", 1, "LeftHandMiddle3", 1,
            "LeftHandRing", 1, "LeftHandRing1", 1, "LeftHandRing2", 1, "LeftHandRing3", 1,
            "LeftHandPinky1", 1, "LeftHandPinky2", 1, "LeftHandPinky3", 1
        };
        comspec_rightArm[] = {
            "RightShoulder", 1, "RightArm", 1, "RightArmRoll", 1, "RightForeArm", 1, "RightForeArmRoll", 1, "RightHand", 1,
            "RightHandThumb1", 1, "RightHandThumb2", 1, "RightHandThumb3", 1,
            "RightHandIndex1", 1, "RightHandIndex2", 1, "RightHandIndex3", 1,
            "RightHandMiddle1", 1, "RightHandMiddle2", 1, "RightHandMiddle3", 1,
            "RightHandRing", 1, "RightHandRing1", 1, "RightHandRing2", 1, "RightHandRing3", 1,
            "RightHandPinky1", 1, "RightHandPinky2", 1, "RightHandPinky3", 1
        };
    };
    class States {
        class GestureComspecBase: Default {
            actions = "NoActions";
            file = "\z\comspec_atak_native\addons\main\anim\comspec_finger.rtm";
            mask = "comspec_leftArm";
            looped = 0;
            speed = 0.42;
            canPullTrigger = 0;
            disableWeapons = 0;
            enableOptics = 0;
            enableBinocular = 1;
            enableMissile = 1;
            showWeaponAim = 1;
            showHandGun = 0;
            showItemInHand = 0;
            showItemInRightHand = 0;
            headBobMode = 0;
            headBobStrength = 0;
            interpolationRestart = 0;
            interpolationSpeed = 6;
            minPlayTime = 0.5;
            preload = 0;
            ragdoll = 0;
            relSpeedMin = 1;
            relSpeedMax = 1;
            soundEnabled = 0;
            static = 0;
            terminal = 0;
            Walkcycles = 1;
            weaponIK = 1;
            // Fusil en main : la main gauche quitte le garde-main au début et y revient à la fin.
            leftHandIKBeg = 1;
            leftHandIKCurve[] = {0, 1, 0.1, 0, 0.88, 0, 1, 1};
            leftHandIKEnd = 1;
            rightHandIKBeg = 1;
            rightHandIKCurve[] = {1};
            rightHandIKEnd = 1;
        };
        // Doigt d'honneur : bras gauche levé, dos de la main vers la cible, deux coups secs, ~2,4 s.
        class GestureComspecFinger: GestureComspecBase {};
        // Doigt d'honneur libre (fn_gestureFingerAim, touche maintenue) : bras gauche tendu vers la cible, une pose
        // en boucle par case de la grille cap x site (gestures_aim.hpp), montée et descente par la pose centrale.
        class GestureComspecFingerAimBase: GestureComspecBase {
            file = "\z\comspec_atak_native\addons\main\anim\comspec_finger_aim_yp0_pp0.rtm";
            looped = 1;
            speed = 0.6;
            minPlayTime = 0;
            leftHandIKCurve[] = {0};
        };
        class GestureComspecFingerAimUp: GestureComspecFingerAimBase {
            file = "\z\comspec_atak_native\addons\main\anim\comspec_finger_aim_up.rtm";
            looped = 0;
            speed = 2.8;
            leftHandIKCurve[] = {0, 1, 0.5, 0};
        };
        class GestureComspecFingerAimDown: GestureComspecFingerAimBase {
            file = "\z\comspec_atak_native\addons\main\anim\comspec_finger_aim_down.rtm";
            looped = 0;
            speed = 2.5;
            leftHandIKCurve[] = {0, 0, 0.6, 0, 1, 1};
        };
        COMSPEC_AIM_STATES
        // Selfie (mode selfie de l'appareil photo, arme rangée) : bras droit tendu devant le visage.
        class GestureComspecSelfieBase: GestureComspecBase {
            mask = "comspec_rightArm";
            leftHandIKCurve[] = {1};
            rightHandIKBeg = 0;
            rightHandIKCurve[] = {0};
            rightHandIKEnd = 0;
            minPlayTime = 0;
        };
        class GestureComspecSelfieUp: GestureComspecSelfieBase {
            file = "\z\comspec_atak_native\addons\main\anim\comspec_selfie_up.rtm";
            speed = 1.7;
        };
        class GestureComspecSelfie: GestureComspecSelfieBase {
            file = "\z\comspec_atak_native\addons\main\anim\comspec_selfie_hold.rtm";
            looped = 1;
            speed = 0.25;
        };
        class GestureComspecSelfieDown: GestureComspecSelfieBase {
            file = "\z\comspec_atak_native\addons\main\anim\comspec_selfie_down.rtm";
            speed = 2;
        };
    };
};
