// Écrans du jeu et d'autres mods utilisables comme écrans TOC (fn_tocScreen) : classe (ou classe parente) -> surface
// qui reçoit l'image. Ces écrans sont proposés à la pose (module Écran TOC) et reçoivent l'action ACE
// « Écran COMSPEC › Utiliser comme écran TOC ». Un autre mod peut ajouter les siens dans sa propre config :
//   class CfgComspecTocScreens { class Mon_Ecran_F { selection = "screen"; }; };
// Champs :
//   selection    nom de la sélection cachée (hiddenSelections de l'objet) qui reçoit l'image
//   mirror[]     autres sélections qui recopient la même image (double écran, poste à trois moniteurs)
//   ratio        0 : image 16:9 (défaut), 4 : bandeau 4:1
//   material     1 : matière d'écran du mod, lumineuse jour / nuit (défaut), 0 : matière d'origine de l'objet
// Classes relevées dans les configs du jeu (Jets, Apex, Contact, Heli) et d'Eden Objects / OFR (Tactical_Screen_F).
class CfgComspecTocScreens {
    // Écran blanc « OPSIS » de salle de briefing (porte-avions, DLC Jets, présent chez tous) : une seule dalle.
    class Land_BriefingRoomScreen_01_F { selection = "largeScreen"; };
    // Grand écran renforcé sur trépied : dix dalles superposées, Screen_0 couvre tout l'écran.
    class Land_TripodScreen_01_large_F { selection = "Screen_0"; };
    class Land_TripodScreen_01_large_black_F: Land_TripodScreen_01_large_F {};
    class Land_TripodScreen_01_large_sand_F: Land_TripodScreen_01_large_F {};
    // Écran renforcé double (horizontal v1, vertical v2) : même image sur les deux dalles.
    class Land_TripodScreen_01_dual_v1_F { selection = "Screen_1"; mirror[] = {"Screen_2"}; };
    class Land_TripodScreen_01_dual_v1_black_F: Land_TripodScreen_01_dual_v1_F {};
    class Land_TripodScreen_01_dual_v1_sand_F: Land_TripodScreen_01_dual_v1_F {};
    class Land_TripodScreen_01_dual_v2_F: Land_TripodScreen_01_dual_v1_F {};
    class Land_TripodScreen_01_dual_v2_black_F: Land_TripodScreen_01_dual_v1_F {};
    class Land_TripodScreen_01_dual_v2_sand_F: Land_TripodScreen_01_dual_v1_F {};
    // Ordinateur multi-écran renforcé (Contact) : sélection 0 = châssis, les trois moniteurs reçoivent l'image.
    class Land_MultiScreenComputer_01_olive_F { selection = "Screen_1"; mirror[] = {"Screen_2", "Screen_3"}; };
    class Land_MultiScreenComputer_01_black_F: Land_MultiScreenComputer_01_olive_F {};
    class Land_MultiScreenComputer_01_sand_F: Land_MultiScreenComputer_01_olive_F {};
    // TV à écran plat, poste informatique (Helicopters), ordinateur portable : dalle Camo_1 / camo.
    class Land_FlatTV_01_F { selection = "Camo_1"; };
    class Land_PCSet_01_screen_F { selection = "Camo_1"; };
    class Land_Laptop_unfolded_F { selection = "camo"; };
    // « Écran Tactique » d'Eden Objects (et du pack OFR) : sélection « camo », l'image de l'écran.
    class Tactical_Screen_F { selection = "camo"; };
    class Tactical_Screen_On_F: Tactical_Screen_F {};
};
