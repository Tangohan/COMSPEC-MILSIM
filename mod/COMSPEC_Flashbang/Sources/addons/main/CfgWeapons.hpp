// Un muzzle de lancer par modèle : la touche « changer de grenade » passe de l'une à l'autre comme en vanilla.
// Variantes à délai : muzzle COMSPEC_FB_Muzzle_<V>_D3 / _D8 (même rang que les autres dans le cycle Ctrl+G).
// muzzles[] += : ajout cumulatif à la liste de Throw (vanilla, ACE et les autres mods de grenades gardent la leur).
#define FB_MUZZLE_NAMES(V) QUOTE(COMSPEC_FB_Muzzle_##V##_D3), QUOTE(COMSPEC_FB_Muzzle_##V##_D8)
#define FB_MUZZLE_DELAY(V,M,D) class COMSPEC_FB_Muzzle_##V##_D##D: ThrowMuzzle { \
            displayName = "$STR_COMSPEC_FB_Muzzle"; \
            magazines[] = {QUOTE(COMSPEC_FB_##M##_D##D)}; \
        }
#define FB_MUZZLE_DELAYS(V,M) FB_MUZZLE_DELAY(V,M,3); FB_MUZZLE_DELAY(V,M,8)
class CfgWeapons {
    class GrenadeLauncher;
    class Throw: GrenadeLauncher {
        muzzles[] += {"COMSPEC_FB_Muzzle_1", "COMSPEC_FB_Muzzle_2", "COMSPEC_FB_Muzzle_3", "COMSPEC_FB_Muzzle_4", "COMSPEC_FB_Muzzle_5", "COMSPEC_FB_Muzzle_6", "COMSPEC_FB_Muzzle_7", "COMSPEC_FB_Muzzle_8", "COMSPEC_FB_Muzzle_9", "COMSPEC_FB_Muzzle_50", "COMSPEC_FB_Muzzle_Drill",
            FB_MUZZLE_NAMES(1), FB_MUZZLE_NAMES(2), FB_MUZZLE_NAMES(3), FB_MUZZLE_NAMES(4), FB_MUZZLE_NAMES(5),
            FB_MUZZLE_NAMES(6), FB_MUZZLE_NAMES(7), FB_MUZZLE_NAMES(8), FB_MUZZLE_NAMES(9), FB_MUZZLE_NAMES(50), FB_MUZZLE_NAMES(Drill),
            "COMSPEC_FB_Muzzle_2_R", "COMSPEC_FB_Muzzle_3_R", "COMSPEC_FB_Muzzle_4_R", "COMSPEC_FB_Muzzle_5_R", "COMSPEC_FB_Muzzle_6_R", "COMSPEC_FB_Muzzle_7_R", "COMSPEC_FB_Muzzle_8_R", "COMSPEC_FB_Muzzle_9_R", "COMSPEC_FB_Muzzle_50_R"};
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
        class COMSPEC_FB_Muzzle_50: ThrowMuzzle {
            displayName = "$STR_COMSPEC_FB_Muzzle";
            magazines[] = {"COMSPEC_FB_Flash_50"};
        };
        class COMSPEC_FB_Muzzle_Drill: ThrowMuzzle {
            displayName = "$STR_COMSPEC_FB_Muzzle";
            magazines[] = {"COMSPEC_FB_Drill"};
        };
        FB_MUZZLE_DELAYS(1,Flash_1);
        FB_MUZZLE_DELAYS(2,Flash_2);
        FB_MUZZLE_DELAYS(3,Flash_3);
        FB_MUZZLE_DELAYS(4,Flash_4);
        FB_MUZZLE_DELAYS(5,Flash_5);
        FB_MUZZLE_DELAYS(6,Flash_6);
        FB_MUZZLE_DELAYS(7,Flash_7);
        FB_MUZZLE_DELAYS(8,Flash_8);
        FB_MUZZLE_DELAYS(9,Flash_9);
        FB_MUZZLE_DELAYS(50,Flash_50);
        FB_MUZZLE_DELAYS(Drill,Drill);
        class COMSPEC_FB_Muzzle_2_R: ThrowMuzzle { displayName = "$STR_COMSPEC_FB_Muzzle"; magazines[] = {"COMSPEC_FB_Flash_2_R"}; };
        class COMSPEC_FB_Muzzle_3_R: ThrowMuzzle { displayName = "$STR_COMSPEC_FB_Muzzle"; magazines[] = {"COMSPEC_FB_Flash_3_R"}; };
        class COMSPEC_FB_Muzzle_4_R: ThrowMuzzle { displayName = "$STR_COMSPEC_FB_Muzzle"; magazines[] = {"COMSPEC_FB_Flash_4_R"}; };
        class COMSPEC_FB_Muzzle_5_R: ThrowMuzzle { displayName = "$STR_COMSPEC_FB_Muzzle"; magazines[] = {"COMSPEC_FB_Flash_5_R"}; };
        class COMSPEC_FB_Muzzle_6_R: ThrowMuzzle { displayName = "$STR_COMSPEC_FB_Muzzle"; magazines[] = {"COMSPEC_FB_Flash_6_R"}; };
        class COMSPEC_FB_Muzzle_7_R: ThrowMuzzle { displayName = "$STR_COMSPEC_FB_Muzzle"; magazines[] = {"COMSPEC_FB_Flash_7_R"}; };
        class COMSPEC_FB_Muzzle_8_R: ThrowMuzzle { displayName = "$STR_COMSPEC_FB_Muzzle"; magazines[] = {"COMSPEC_FB_Flash_8_R"}; };
        class COMSPEC_FB_Muzzle_9_R: ThrowMuzzle { displayName = "$STR_COMSPEC_FB_Muzzle"; magazines[] = {"COMSPEC_FB_Flash_9_R"}; };
        class COMSPEC_FB_Muzzle_50_R: ThrowMuzzle { displayName = "$STR_COMSPEC_FB_Muzzle"; magazines[] = {"COMSPEC_FB_Flash_50_R"}; };

    };
};
