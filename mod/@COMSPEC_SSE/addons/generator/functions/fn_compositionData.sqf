/*
    Compositions SSE décrites en données (classes vanilla Arma 3 et pièces COMSPEC_SSE_Obj_*).
    [_type] call comspec_sse_fnc_compositionData -> HashMap (vide si type inconnu)
    [""] call comspec_sse_fnc_compositionData -> liste des types connus

    Clés :
      label       nom affiché
      profile     profil SSE du site ; theme : thème narratif du générateur (getThemePack)
      complexity  richesse par défaut ; occupants : nombre par défaut ; profiles : profil de chaque occupant
                  (le premier occupant traité devient le chef du cluster)
      building    true : placé de préférence dans un bâtiment existant
      anchors     [id, classe, [x, y], dir] : mobilier porteur, décalage en m depuis le centre (sol) ;
                  dans un bâtiment, chaque ancre prend une position du bâtiment (buildingPos)
      props       [ancre, classe, [dx, dy], dir, "top"|"floor"] : décor posé sur l'ancre ou au sol à côté
      evidence    [ancre, classe, [dx, dy], dir, "top"|"floor", relation] : pièces SSE reliées au chef
      containers  [ancre, classe, [dx, dy], dir] : caisses / bagages marqués CONTAINER
      traps       [classe de mine, "entry"|ancre]
*/
params [["_type", "", [""]]];

private _all = ["SAFEHOUSE", "IED_LAB", "FORGERY", "WEAPONS_CACHE", "CELL_HQ", "DEAD_DROP", "HAWALA"];
if (_type isEqualTo "") exitWith { _all };

switch (toUpper _type) do {
    case "SAFEHOUSE": {
        createHashMapFromArray [
            ["label", "Planque"],
            ["profile", "INSURGENT"],
            ["theme", "safehouse"],
            ["complexity", "STANDARD"],
            ["occupants", 3],
            ["profiles", ["INSURGENT", "INSURGENT", "COURIER", "INSURGENT"]],
            ["building", true],
            ["anchors", [
                ["table", "Land_WoodenTable_large_F", [0, 0], 0],
                ["kitchen", "Land_WoodenTable_small_F", [2.5, 1.5], 90],
                ["bed1", "Land_Sleeping_bag_brown_F", [-2.5, 1.2], 90],
                ["bed2", "Land_Sleeping_bag_F", [-2.5, -1], 90],
                ["shelf", "Land_ShelvesWooden_F", [2.5, -1.8], 270]
            ]],
            ["props", [
                ["table", "Land_ChairWood_F", [0.9, -0.2], 270, "floor"],
                ["table", "Land_ChairWood_F", [-0.9, 0.2], 90, "floor"],
                ["kitchen", "Land_GasCooker_F", [0, 0], 0, "top"],
                ["kitchen", "Land_GasCanister_F", [0.6, 0.5], 0, "floor"],
                ["bed1", "Land_GarbageBags_F", [0, 1.4], 0, "floor"],
                ["shelf", "Land_FMradio_F", [0, 0.1], 180, "top"]
            ]],
            ["evidence", [
                ["table", "COMSPEC_SSE_Obj_Phone", [0.3, 0.1], 20, "top", "OWNER"],
                ["table", "COMSPEC_SSE_Obj_Smartphone", [-0.35, 0.15], 200, "top", "OWNER"],
                ["table", "COMSPEC_SSE_Obj_Documents", [0, -0.25], 5, "top", "REFERENCES"],
                ["bed1", "COMSPEC_SSE_Obj_Notebook", [0.3, 0.2], 40, "floor", "OWNER"],
                ["shelf", "COMSPEC_SSE_Obj_USB", [0.2, 0.1], 0, "top", "OWNER"]
            ]],
            ["containers", [
                ["bed2", "COMSPEC_SSE_Obj_Suitcase", [0.2, 1.1], 0]
            ]],
            ["traps", [["APERSTripMine", "entry"]]]
        ]
    };
    case "IED_LAB": {
        createHashMapFromArray [
            ["label", "Labo d'IED"],
            ["profile", "TECHNICIAN"],
            ["theme", "ied_cell"],
            ["complexity", "DETAILED"],
            ["occupants", 2],
            ["profiles", ["TECHNICIAN", "TECHNICIAN", "LOGISTICS", "INSURGENT"]],
            ["building", true],
            ["anchors", [
                ["bench", "Land_Workbench_01_F", [0, 0], 0],
                ["table", "Land_CampingTable_F", [2.6, 0], 90],
                ["rack", "Land_ShelvesMetal_F", [-2.6, 1], 90]
            ]],
            ["props", [
                ["bench", "Land_CarBattery_01_F", [-0.5, 0], 0, "top"],
                ["bench", "Land_MetalWire_F", [0.1, 0.1], 30, "top"],
                ["bench", "Land_DuctTape_F", [0.35, -0.1], 0, "top"],
                ["bench", "Land_Pliers_F", [0.55, 0.05], 80, "top"],
                ["bench", "Land_Screwdriver_V1_F", [0.7, -0.05], 10, "top"],
                ["table", "Land_ButaneTorch_F", [-0.3, 0.1], 0, "top"],
                ["table", "Land_DrillAku_F", [0.3, -0.1], 60, "top"],
                ["table", "Land_ExtensionCord_F", [0, 0.6], 0, "floor"],
                ["rack", "Land_CanisterFuel_F", [0.5, -0.8], 0, "floor"],
                ["rack", "Land_CanisterPlastic_F", [-0.4, -0.8], 30, "floor"],
                ["bench", "Land_Sacks_heap_F", [0, -1.4], 0, "floor"]
            ]],
            ["evidence", [
                ["bench", "COMSPEC_SSE_Obj_Phone", [-0.15, -0.15], 90, "top", "OWNER"],
                ["bench", "COMSPEC_SSE_Obj_Phone", [0.3, 0.2], 250, "top", "OWNER"],
                ["table", "COMSPEC_SSE_Obj_Notebook", [0.1, 0.25], 0, "top", "OWNER"],
                ["table", "COMSPEC_SSE_Obj_Photos", [-0.2, -0.2], 15, "top", "REFERENCES"],
                ["rack", "COMSPEC_SSE_Obj_Laptop", [0, 0.1], 180, "top", "OWNER"]
            ]],
            ["containers", []],
            ["traps", [["APERSTripMine", "entry"], ["IEDUrbanSmall_F", "bench"]]]
        ]
    };
    case "FORGERY": {
        createHashMapFromArray [
            ["label", "Atelier de faux papiers"],
            ["profile", "INTELLIGENCE"],
            ["theme", "smuggling"],
            ["complexity", "DETAILED"],
            ["occupants", 2],
            ["profiles", ["INTELLIGENCE", "COURIER", "CIVILIAN"]],
            ["building", true],
            ["anchors", [
                ["desk", "Land_TableDesk_F", [0, 0], 0],
                ["print", "Land_TableDesk_F", [2.2, 0], 0],
                ["cabinet", "Land_OfficeCabinet_01_F", [-2.2, 1], 90]
            ]],
            ["props", [
                ["desk", "Land_PCSet_01_screen_F", [0, 0.2], 180, "top"],
                ["desk", "Land_PCSet_01_keyboard_F", [0, -0.1], 180, "top"],
                ["desk", "Land_PCSet_01_mouse_F", [0.35, -0.1], 180, "top"],
                ["desk", "Land_OfficeChair_01_F", [0, -0.8], 180, "floor"],
                ["print", "Land_Printer_01_F", [-0.3, 0.1], 180, "top"],
                ["print", "Land_PaperBox_closed_F", [0.3, 0.9], 0, "floor"]
            ]],
            ["evidence", [
                ["desk", "COMSPEC_SSE_Obj_PCTower", [0.7, 0.1], 180, "floor", "OWNER"],
                ["desk", "COMSPEC_SSE_Obj_Ledger", [-0.45, -0.05], 10, "top", "REFERENCES"],
                ["print", "COMSPEC_SSE_Obj_Photos", [0.3, -0.05], 0, "top", "REFERENCES"],
                ["print", "COMSPEC_SSE_Obj_Documents", [0.1, -0.2], 350, "top", "REFERENCES"],
                ["print", "COMSPEC_SSE_Obj_SDCard", [0.55, 0.15], 0, "top", "OWNER"]
            ]],
            ["containers", []],
            ["traps", [["APERSTripMine", "entry"]]]
        ]
    };
    case "WEAPONS_CACHE": {
        createHashMapFromArray [
            ["label", "Cache d'armes"],
            ["profile", "LOGISTICS"],
            ["theme", "weapons_cache"],
            ["complexity", "STANDARD"],
            ["occupants", 0],
            ["profiles", ["LOGISTICS", "INSURGENT", "INSURGENT"]],
            ["building", false],
            ["anchors", [
                ["sheet", "Land_Ground_sheet_F", [0, 0], 0],
                ["sacks", "Land_Sacks_heap_F", [2.2, 1], 30]
            ]],
            ["props", [
                ["sheet", "Land_Shovel_F", [1.4, -0.6], 70, "floor"],
                ["sacks", "Land_CanisterFuel_F", [-0.6, -1], 0, "floor"]
            ]],
            ["evidence", [
                ["sheet", "COMSPEC_SSE_Obj_Ledger", [-0.6, -0.5], 20, "floor", "REFERENCES"],
                ["sheet", "COMSPEC_SSE_Obj_Radio", [0.6, -0.6], 200, "floor", "OWNER"]
            ]],
            ["containers", [
                ["sheet", "Box_FIA_Wps_F", [0, 0.3], 0],
                ["sheet", "Box_FIA_Ammo_F", [-0.9, 0.5], 90]
            ]],
            ["traps", [["APERSTripMine", "entry"]]]
        ]
    };
    case "CELL_HQ": {
        createHashMapFromArray [
            ["label", "PC de cellule"],
            ["profile", "COMMANDER"],
            ["theme", "meeting_alpha"],
            ["complexity", "HIGH_VALUE"],
            ["occupants", 4],
            ["profiles", ["COMMANDER", "INTELLIGENCE", "INSURGENT", "INSURGENT", "COURIER"]],
            ["building", true],
            ["anchors", [
                ["table", "Land_WoodenTable_large_F", [0, 0], 0],
                ["board", "Land_MapBoard_F", [0, 2.4], 180],
                ["desk", "Land_TableDesk_F", [3, 0], 270],
                ["radio", "Land_CampingTable_small_F", [-3, 0], 90]
            ]],
            ["props", [
                ["table", "Land_ChairWood_F", [0.9, 0], 270, "floor"],
                ["table", "Land_ChairWood_F", [-0.9, 0], 90, "floor"],
                ["table", "Land_ChairWood_F", [0, -0.9], 0, "floor"],
                ["radio", "Land_FMradio_F", [0.15, 0.1], 0, "top"],
                ["radio", "Land_Portable_generator_F", [0, 1.2], 0, "floor"]
            ]],
            ["evidence", [
                ["table", "COMSPEC_SSE_Obj_Map", [0, 0.1], 0, "top", "REFERENCES"],
                ["table", "COMSPEC_SSE_Obj_SatPhone", [0.5, -0.2], 300, "top", "OWNER"],
                ["table", "COMSPEC_SSE_Obj_Notebook", [-0.5, -0.2], 15, "top", "OWNER"],
                ["desk", "COMSPEC_SSE_Obj_Laptop", [0, 0.05], 180, "top", "OWNER"],
                ["desk", "COMSPEC_SSE_Obj_HardDrive", [0.45, 0.1], 0, "top", "OWNER"],
                ["radio", "COMSPEC_SSE_Obj_Radio", [-0.2, -0.1], 90, "top", "OWNER"],
                ["board", "COMSPEC_SSE_Obj_Photos", [0.4, -0.6], 0, "floor", "REFERENCES"]
            ]],
            ["containers", []],
            ["traps", [["APERSTripMine", "entry"]]]
        ]
    };
    case "DEAD_DROP": {
        createHashMapFromArray [
            ["label", "Boîte aux lettres morte"],
            ["profile", "COURIER"],
            ["theme", "courier_run"],
            ["complexity", "STANDARD"],
            ["occupants", 0],
            ["profiles", ["COURIER"]],
            ["building", false],
            ["anchors", [
                ["barrel", "Land_BarrelEmpty_F", [0, 0], 0]
            ]],
            ["props", [
                ["barrel", "Land_GarbageBags_F", [1.4, 0.4], 30, "floor"],
                ["barrel", "Land_Tyre_F", [-1.1, 0.8], 0, "floor"]
            ]],
            ["evidence", [
                ["barrel", "COMSPEC_SSE_Obj_USB", [0.45, -0.25], 0, "floor", "OWNER"],
                ["barrel", "COMSPEC_SSE_Obj_Documents", [0.35, 0.4], 60, "floor", "REFERENCES"]
            ]],
            ["containers", []],
            ["traps", []]
        ]
    };
    case "HAWALA": {
        createHashMapFromArray [
            ["label", "Nœud hawala / changeur"],
            ["profile", "FINANCIER"],
            ["theme", "finance_drop"],
            ["complexity", "DETAILED"],
            ["occupants", 2],
            ["profiles", ["FINANCIER", "COURIER", "CIVILIAN"]],
            ["building", true],
            ["anchors", [
                ["counter", "Land_CashDesk_F", [0, 0], 0],
                ["desk", "Land_TableDesk_F", [2.5, 0.5], 270],
                ["cabinet", "Land_OfficeCabinet_01_F", [-2.5, 1.5], 90]
            ]],
            ["props", [
                ["desk", "Land_OfficeChair_01_F", [0, -0.8], 180, "floor"],
                ["counter", "Land_FMradio_F", [0.5, 0.15], 200, "top"]
            ]],
            ["evidence", [
                ["counter", "COMSPEC_SSE_Obj_Ledger", [0, 0], 0, "top", "REFERENCES"],
                ["counter", "COMSPEC_SSE_Obj_Cash", [-0.35, 0.1], 30, "top", "OWNER"],
                ["counter", "COMSPEC_SSE_Obj_Phone", [0.3, -0.1], 120, "top", "OWNER"],
                ["desk", "COMSPEC_SSE_Obj_Laptop", [0, 0.05], 180, "top", "OWNER"],
                ["desk", "COMSPEC_SSE_Obj_Notebook", [0.45, 0], 10, "top", "REFERENCES"]
            ]],
            ["containers", [
                ["desk", "Land_MetalCase_01_small_F", [-0.5, 0.6], 0]
            ]],
            ["traps", []]
        ]
    };
    default { createHashMap };
}
