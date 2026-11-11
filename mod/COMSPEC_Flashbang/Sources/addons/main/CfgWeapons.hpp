// Un muzzle de lancer par modèle : la touche « changer de grenade » passe de l'une à l'autre comme en vanilla.
class CfgWeapons {
    class GrenadeLauncher;
    class Throw: GrenadeLauncher {
        muzzles[] += {"COMSPEC_FB_Muzzle_1", "COMSPEC_FB_Muzzle_2", "COMSPEC_FB_Muzzle_3", "COMSPEC_FB_Muzzle_4", "COMSPEC_FB_Muzzle_5", "COMSPEC_FB_Muzzle_6", "COMSPEC_FB_Muzzle_7", "COMSPEC_FB_Muzzle_8", "COMSPEC_FB_Muzzle_9", "COMSPEC_FB_Muzzle_Drill"};
        class ThrowMuzzle;
        class COMSPEC_FB_Muzzle_1: ThrowMuzzle {
            displayName = "$STR_COMSPEC_FB_Muzzle";
            magazines[] = {"COMSPEC_FB_Flash_1"};
        };
        class COMSPEC_FB_Muzzle_2: ThrowMuzzle {
            displayName = "$STR_COMSPEC_FB_Muzzle";
            magazines[] = {"COMSPEC_FB_Flash_2"};
        };
        class COMSPEC_FB_Muzzle_3: ThrowMuzzle {
            displayName = "$STR_COMSPEC_FB_Muzzle";
            magazines[] = {"COMSPEC_FB_Flash_3"};
        };
        class COMSPEC_FB_Muzzle_4: ThrowMuzzle {
            displayName = "$STR_COMSPEC_FB_Muzzle";
            magazines[] = {"COMSPEC_FB_Flash_4"};
        };
        class COMSPEC_FB_Muzzle_5: ThrowMuzzle {
            displayName = "$STR_COMSPEC_FB_Muzzle";
            magazines[] = {"COMSPEC_FB_Flash_5"};
        };
        class COMSPEC_FB_Muzzle_6: ThrowMuzzle {
            displayName = "$STR_COMSPEC_FB_Muzzle";
            magazines[] = {"COMSPEC_FB_Flash_6"};
        };
        class COMSPEC_FB_Muzzle_7: ThrowMuzzle {
            displayName = "$STR_COMSPEC_FB_Muzzle";
            magazines[] = {"COMSPEC_FB_Flash_7"};
        };
        class COMSPEC_FB_Muzzle_8: ThrowMuzzle {
            displayName = "$STR_COMSPEC_FB_Muzzle";
            magazines[] = {"COMSPEC_FB_Flash_8"};
        };
        class COMSPEC_FB_Muzzle_9: ThrowMuzzle {
            displayName = "$STR_COMSPEC_FB_Muzzle";
            magazines[] = {"COMSPEC_FB_Flash_9"};
        };
        class COMSPEC_FB_Muzzle_Drill: ThrowMuzzle {
            displayName = "$STR_COMSPEC_FB_Muzzle";
            magazines[] = {"COMSPEC_FB_Drill"};
        };
    };
};
