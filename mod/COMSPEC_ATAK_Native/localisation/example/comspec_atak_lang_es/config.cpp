// COMSPEC ATAK translation mod: example for Spanish. Copy this folder, replace "es" / "Español" / "Spanish"
// with your language, put your translated file next to this config.cpp and pack the folder into a PBO.
class CfgPatches {
    class comspec_atak_lang_es {
        name = "COMSPEC ATAK - Español";
        author = "Your name";
        requiredVersion = 2.14;
        // Loaded after COMSPEC ATAK Native.
        requiredAddons[] = {"comspec_atak_native_main"};
        units[] = {};
        weapons[] = {};
    };
};
class CfgComspecLanguages {
    // Class name = language code (two lowercase letters, never "fr" or "en").
    class es {
        // Shown in the phone (Settings > Language) and in the COMSPEC addon options.
        name = "Español";
        // Path = the $PBOPREFIX$ of this folder + the file name.
        file = "\comspec_atak_lang_es\es.txt";
        // Arma game languages that pick this translation when the player leaves the setting on Auto.
        gameLanguage[] = {"Spanish"};
    };
};
