// Page « IA de la zone » de l'éditeur SSE (contrôleur : fn_editorAi). Inclus dans la classe controls de editor.hpp.
// Trois colonnes sur la grille 40 x 25 de la charte (ui_macros.hpp, cases carrées) :
//   gauche  x 0.3 .. 15.3   la liste des IA (trouver, chercher, filtrer, cocher)
//   milieu  x 15.6 .. 29.2  les réglages des IA cochées, en quatre onglets (Identité, Papiers, Comportement, Camp)
//   droite  x 29.5 .. 39.7  « Ce que verra le joueur » : le vrai papier rendu (recto / verso) et ce qu'il dira au contrôle
//   bas     y 23.2 .. 25    une ligne d'état, « Mémoire de la carte… », « Appliquer à tout le groupe », « Appliquer »
// Deux volets s'ouvrent par-dessus (Filtres, Mémoire de la carte) ; la visite guidée est dans coach.hpp.
// Textes : stringtable.xml de l'addon (STR_COMSPEC_SSE_AZ_*, anciennes clés STR_COMSPEC_SSE_ED_* gardées).
// Taille de texte : jamais sous AZ_TXT_MIN (aizone_styles.hpp). Largeurs comptées caractère par caractère (RobotoCondensed)
// avec 6 % de marge, en français et en anglais : tests/Unit/SseAiZoneWindowAssetTest.php. En jeu, fn_editorAi réduit au
// besoin sans descendre sous AZ_TXT_MIN, puis raccourcit avec « … » et met le libellé entier en info-bulle.

// ------------------------------------------------------------ fond de la page
class AzFill: COMSPEC_SSE_EdFill { idc = 95000; ED_POS(0,1.8,40,21.4); };

// ------------------------------------------------------------ gauche : la liste des IA
class AzLTitle: COMSPEC_SSE_AzHeading { idc = 95001; text = "$STR_COMSPEC_SSE_AZ_Title"; tooltip = "$STR_COMSPEC_SSE_AZ_Title_D"; ED_POS(0.3,2.05,9.0,1.0); };
class AzLCount: COMSPEC_SSE_AzMuted { idc = 95002; style = 1; tooltip = "$STR_COMSPEC_SSE_AZ_Count_D"; ED_POS(9.4,2.1,4.3,0.9); };
class AzHelp: COMSPEC_SSE_AzButton { idc = 95003; text = "$STR_COMSPEC_SSE_ED_Help"; tooltip = "$STR_COMSPEC_SSE_AZ_Help_D"; font = "RobotoCondensedBold"; ED_POS(13.9,2.0,1.4,1.1); action = "['coach'] call comspec_sse_fnc_editorAi"; };
class AzScan: COMSPEC_SSE_AzButton { idc = 95004; text = "$STR_COMSPEC_SSE_ED_Around"; tooltip = "$STR_COMSPEC_SSE_ED_Around_D"; ED_POS(0.3,3.25,5.8,1.1); action = "['scan'] call comspec_sse_fnc_editorAi"; };
class AzRadius: COMSPEC_SSE_AzCombo { idc = 95005; tooltip = "$STR_COMSPEC_SSE_AZ_Radius_D"; ED_POS(6.2,3.25,3.4,1.1); };
class AzZeus: COMSPEC_SSE_AzButton { idc = 95006; text = "$STR_COMSPEC_SSE_ED_ZeusSel"; tooltip = "$STR_COMSPEC_SSE_ED_ZeusSel_D"; ED_POS(9.7,3.25,5.6,1.1); action = "['scanZeus'] call comspec_sse_fnc_editorAi"; };
class AzSearchBg: COMSPEC_SSE_EdText { idc = 95007; colorBackground[] = AZ_FIELD; ED_POS(0.3,4.5,9.9,1.1); };
class AzSearchHint: COMSPEC_SSE_AzMuted { idc = 95008; text = "$STR_COMSPEC_SSE_AZ_SearchHint"; ED_POS(0.45,4.5,9.6,1.1); };
class AzSearch: COMSPEC_SSE_AzEditClear { idc = 95009; tooltip = "$STR_COMSPEC_SSE_ED_Search_D"; ED_POS(0.3,4.5,9.9,1.1); onKeyUp = "['filter'] call comspec_sse_fnc_editorAi"; };
class AzFilters: COMSPEC_SSE_AzButton { idc = 95010; text = "$STR_COMSPEC_SSE_AZ_Filters"; tooltip = "$STR_COMSPEC_SSE_AZ_Filters_D"; ED_POS(10.3,4.5,5.0,1.1); action = "['filters'] call comspec_sse_fnc_editorAi"; };
// En-têtes : mêmes positions que les colonnes de la liste (cases depuis x 0.3 : case 1, nom 6.6, rôle 3.6, papiers 2, profil 1.8).
class AzHName: COMSPEC_SSE_AzColHead { idc = 95011; text = "$STR_COMSPEC_SSE_AZ_HName"; tooltip = "$STR_COMSPEC_SSE_AZ_HSort_D"; ED_POS(1.3,5.75,6.6,0.85); action = "['sort', 'name'] call comspec_sse_fnc_editorAi"; };
class AzHRole: COMSPEC_SSE_AzColHead { idc = 95012; text = "$STR_COMSPEC_SSE_AZ_HRole"; tooltip = "$STR_COMSPEC_SSE_AZ_HSort_D"; ED_POS(7.9,5.75,3.6,0.85); action = "['sort', 'role'] call comspec_sse_fnc_editorAi"; };
class AzHDocsPic: COMSPEC_SSE_EdPicture { idc = 95013; text = "\z\comspec_sse\addons\editor\data\type_paper_ca.paa"; colorText[] = ED_MUTED; ED_POS(12.1,5.78,0.8,0.8); };
class AzHDocs: COMSPEC_SSE_AzColHead { idc = 95014; tooltip = "$STR_COMSPEC_SSE_AZ_HDocs_D"; ED_POS(11.5,5.75,2.0,0.85); action = "['sort', 'docs'] call comspec_sse_fnc_editorAi"; };
class AzHProfPic: COMSPEC_SSE_EdPicture { idc = 95015; text = "\z\comspec_sse\addons\editor\data\type_identity_ca.paa"; colorText[] = ED_MUTED; ED_POS(14.0,5.78,0.8,0.8); };
class AzHProf: COMSPEC_SSE_AzColHead { idc = 95016; tooltip = "$STR_COMSPEC_SSE_AZ_HProf_D"; ED_POS(13.5,5.75,1.8,0.85); action = "['sort', 'prof'] call comspec_sse_fnc_editorAi"; };
class AzList: COMSPEC_SSE_AzList {
    idc = 95017;
    tooltip = "$STR_COMSPEC_SSE_AZ_List_D";
    ED_POS(0.3,6.65,15.0,13.0);
    columns[] = {0, 0.0667, 0.5067, 0.7467, 0.88};
    onLBSelChanged = "['click'] call comspec_sse_fnc_editorAi";
};
// État vide : un vrai panneau d'aide avec les deux façons de remplir la liste.
class AzEmptyBg: COMSPEC_SSE_EdText { idc = 95018; colorBackground[] = AZ_FIELD; ED_POS(0.3,6.65,15.0,13.0); };
class AzEmptyTitle: COMSPEC_SSE_AzHeading { idc = 95019; style = 2; ED_POS(1.0,9.2,13.6,1.1); };
class AzEmptyText: COMSPEC_SSE_AzHelp { idc = 95020; size = SSE_Q(SSE_GH * AZ_TXT_BODY); ED_POS(1.0,10.5,13.6,3.7); class Attributes { font = "RobotoCondensed"; color = "#9fb0a8"; align = "center"; shadow = 0; }; };
class AzEmptyScan: COMSPEC_SSE_AzButton { idc = 95021; text = "$STR_COMSPEC_SSE_ED_Around"; tooltip = "$STR_COMSPEC_SSE_ED_Around_D"; ED_POS(1.6,14.5,5.9,1.1); action = "['scan'] call comspec_sse_fnc_editorAi"; };
class AzEmptyZeus: COMSPEC_SSE_AzButton { idc = 95022; text = "$STR_COMSPEC_SSE_ED_ZeusSel"; tooltip = "$STR_COMSPEC_SSE_ED_ZeusSel_D"; ED_POS(8.1,14.5,5.9,1.1); action = "['scanZeus'] call comspec_sse_fnc_editorAi"; };
class AzCheckAll: COMSPEC_SSE_AzButton { idc = 95023; text = "$STR_COMSPEC_SSE_ED_CheckAll"; tooltip = "$STR_COMSPEC_SSE_ED_CheckAll_D"; ED_POS(0.3,19.85,4.6,1.1); action = "['checkAll'] call comspec_sse_fnc_editorAi"; };
class AzCheckNone: COMSPEC_SSE_AzButton { idc = 95024; text = "$STR_COMSPEC_SSE_ED_CheckNone"; tooltip = "$STR_COMSPEC_SSE_AZ_CheckNone_D"; ED_POS(5.05,19.85,3.9,1.1); action = "['checkNone'] call comspec_sse_fnc_editorAi"; };
class AzChecked: COMSPEC_SSE_AzText { idc = 95025; style = 1; colorText[] = ED_GREEN; tooltip = "$STR_COMSPEC_SSE_AZ_Checked_D"; ED_POS(9.1,19.9,6.2,1.0); };
class AzLegend: COMSPEC_SSE_AzHelp { idc = 95026; tooltip = "$STR_COMSPEC_SSE_AZ_HDocs_D"; ED_POS(0.3,21.1,15.0,1.8); };

// ------------------------------------------------------------ milieu : l'IA choisie et ses onglets
class AzMName: COMSPEC_SSE_AzHeading { idc = 95030; ED_POS(15.6,2.05,13.6,1.0); };
class AzMSub: COMSPEC_SSE_AzColText { idc = 95031; tooltip = "$STR_COMSPEC_SSE_AZ_MSub_D"; ED_POS(15.6,3.05,13.6,0.85); };
class AzTabId: COMSPEC_SSE_AzTab { idc = 95032; text = "$STR_COMSPEC_SSE_AZ_TabId"; tooltip = "$STR_COMSPEC_SSE_AZ_TabId_D"; ED_POS(15.6,4.05,3.1,1.15); action = "['tab', 'id'] call comspec_sse_fnc_editorAi"; };
class AzTabDocs: COMSPEC_SSE_AzTab { idc = 95033; text = "$STR_COMSPEC_SSE_AZ_TabDocs"; tooltip = "$STR_COMSPEC_SSE_AZ_TabDocs_D"; ED_POS(18.7,4.05,2.85,1.15); action = "['tab', 'docs'] call comspec_sse_fnc_editorAi"; };
class AzTabBeh: COMSPEC_SSE_AzTab { idc = 95034; text = "$STR_COMSPEC_SSE_AZ_TabBeh"; tooltip = "$STR_COMSPEC_SSE_AZ_TabBeh_D"; ED_POS(21.55,4.05,5.4,1.15); action = "['tab', 'beh'] call comspec_sse_fnc_editorAi"; };
class AzTabSide: COMSPEC_SSE_AzTab { idc = 95035; text = "$STR_COMSPEC_SSE_AZ_TabSide"; tooltip = "$STR_COMSPEC_SSE_AZ_TabSide_D"; ED_POS(26.95,4.05,2.25,1.15); action = "['tab', 'side'] call comspec_sse_fnc_editorAi"; };
class AzTabRule: COMSPEC_SSE_EdRule { idc = 95036; ED_POS(15.6,5.2,13.6,0.05); };
class AzTabMark: COMSPEC_SSE_AzAccent { idc = 95037; ED_POS(15.6,5.1,3.1,0.15); };

// --- onglet Identité (y 5.55 .. 22.4)
class AzSrcLabel: COMSPEC_SSE_AzText { idc = 95100; text = "$STR_COMSPEC_SSE_AZ_SrcLabel"; ED_POS(15.6,5.55,13.6,0.85); };
class AzSrc: COMSPEC_SSE_AzCombo { idc = 95101; tooltip = "$STR_COMSPEC_SSE_ED_ProfHelp_D"; ED_POS(15.6,6.4,13.6,1.1); onLBSelChanged = "['changed'] call comspec_sse_fnc_editorAi"; };
class AzSrcHelp: COMSPEC_SSE_AzHelp { idc = 95102; tooltip = "$STR_COMSPEC_SSE_ED_ProfHelp_D"; ED_POS(15.6,7.55,13.6,1.75); };
class AzKindLabel: COMSPEC_SSE_AzText { idc = 95103; text = "$STR_COMSPEC_SSE_AZ_KindLabel"; tooltip = "$STR_COMSPEC_SSE_AZ_Kind_D"; ED_POS(15.6,9.45,13.6,0.85); };
class AzKind: COMSPEC_SSE_AzCombo { idc = 95104; tooltip = "$STR_COMSPEC_SSE_AZ_Kind_D"; ED_POS(15.6,10.3,13.6,1.1); onLBSelChanged = "['changed'] call comspec_sse_fnc_editorAi"; };
class AzKindHelp: COMSPEC_SSE_AzHelp { idc = 95105; tooltip = "$STR_COMSPEC_SSE_AZ_Kind_D"; ED_POS(15.6,11.45,13.6,1.75); };
class AzCtryLabel: COMSPEC_SSE_AzText { idc = 95106; text = "$STR_COMSPEC_SSE_AZ_CtryLabel"; tooltip = "$STR_COMSPEC_SSE_AZ_Ctry_D"; ED_POS(15.6,13.35,13.6,0.85); };
class AzCtry: COMSPEC_SSE_AzCombo { idc = 95107; tooltip = "$STR_COMSPEC_SSE_AZ_Ctry_D"; ED_POS(15.6,14.2,13.6,1.1); onLBSelChanged = "['changed'] call comspec_sse_fnc_editorAi"; };
class AzCtryHelp: COMSPEC_SSE_AzHelp { idc = 95108; text = "$STR_COMSPEC_SSE_AZ_CtryHelp"; tooltip = "$STR_COMSPEC_SSE_AZ_Ctry_D"; ED_POS(15.6,15.35,13.6,1.75); };
// Volet repliable « Importer depuis Athena » : état des profils, recherche, Actualiser.
class AzFold: COMSPEC_SSE_AzButton { idc = 95109; style = 0; tooltip = "$STR_COMSPEC_SSE_ED_ProfStatus_D"; ED_POS(15.6,17.3,13.6,1.1); action = "['fold'] call comspec_sse_fnc_editorAi"; };
class AzAthStatus: COMSPEC_SSE_AzHelp { idc = 95110; tooltip = "$STR_COMSPEC_SSE_ED_ProfStatus_D"; ED_POS(15.6,18.55,13.6,2.55); };
class AzAthSearchBg: COMSPEC_SSE_EdText { idc = 95113; colorBackground[] = AZ_FIELD; ED_POS(15.6,21.3,9.1,1.1); };
class AzAthSearchHint: COMSPEC_SSE_AzMuted { idc = 95114; text = "$STR_COMSPEC_SSE_AZ_ProfHint"; ED_POS(15.75,21.3,8.8,1.1); };
class AzAthSearch: COMSPEC_SSE_AzEditClear { idc = 95111; tooltip = "$STR_COMSPEC_SSE_ED_ProfSearch_D"; ED_POS(15.6,21.3,9.1,1.1); onKeyUp = "['profFilter'] call comspec_sse_fnc_editorAi"; };
class AzAthRefresh: COMSPEC_SSE_AzButton { idc = 95112; text = "$STR_COMSPEC_SSE_ED_Refresh"; tooltip = "$STR_COMSPEC_SSE_ED_Refresh_D"; ED_POS(24.85,21.3,4.35,1.1); action = "['refresh'] call comspec_sse_fnc_editorAi"; };

// --- onglet Papiers (y 5.55 .. 22.4)
class AzCarryLabel: COMSPEC_SSE_AzText { idc = 95200; text = "$STR_COMSPEC_SSE_AZ_CarryLabel"; tooltip = "$STR_COMSPEC_SSE_ED_IdentityHelp_D"; ED_POS(15.6,5.55,13.6,0.85); };
class AzCarry: COMSPEC_SSE_AzCombo { idc = 95201; tooltip = "$STR_COMSPEC_SSE_ED_IdentityHelp_D"; ED_POS(15.6,6.4,13.6,1.1); onLBSelChanged = "['changed'] call comspec_sse_fnc_editorAi"; };
class AzCarryHelp: COMSPEC_SSE_AzHelp { idc = 95202; text = "$STR_COMSPEC_SSE_AZ_CarryHelp"; tooltip = "$STR_COMSPEC_SSE_ED_IdentityHelp_D"; ED_POS(15.6,7.55,13.6,1.75); };
class AzDocsLabel: COMSPEC_SSE_AzText { idc = 95203; text = "$STR_COMSPEC_SSE_AZ_DocsLabel"; tooltip = "$STR_COMSPEC_SSE_ED_Docs_D"; ED_POS(15.6,9.45,13.6,0.85); };
// En-têtes : colonnes de la liste (cases depuis x 15.6 : papier et pays 5.9, numéro 4, vrai / faux 3.7).
class AzDH1: COMSPEC_SSE_AzColText { idc = 95204; text = "$STR_COMSPEC_SSE_AZ_DPaper"; ED_POS(15.67,10.3,5.7,0.8); };
class AzDH3: COMSPEC_SSE_AzColText { idc = 95206; text = "$STR_COMSPEC_SSE_ED_Col_Number"; ED_POS(21.5,10.3,4.0,0.8); };
class AzDH4: COMSPEC_SSE_AzColText { idc = 95207; text = "$STR_COMSPEC_SSE_AZ_DAuth"; tooltip = "$STR_COMSPEC_SSE_AZ_DAuth_D"; ED_POS(25.5,10.3,3.7,0.8); };
class AzDocs: COMSPEC_SSE_AzList {
    idc = 95208;
    tooltip = "$STR_COMSPEC_SSE_ED_Docs_D";
    ED_POS(15.6,11.1,13.6,3.9);
    columns[] = {0.005, 0.4338, 0.7279};
    ED_TXT(AZ_TXT_MIN);
    onLBSelChanged = "['docSel'] call comspec_sse_fnc_editorAi";
    onLBDblClick = "['docOpen'] call comspec_sse_fnc_editorAi";
};
class AzDocsEmpty: COMSPEC_SSE_AzHelp { idc = 95209; ED_POS(15.9,11.3,13.0,3.5); };
class AzDocView: COMSPEC_SSE_AzButton { idc = 95210; text = "$STR_COMSPEC_SSE_AZ_View"; tooltip = "$STR_COMSPEC_SSE_AZ_View_D"; ED_POS(15.6,15.2,6.725,1.1); action = "['docOpen'] call comspec_sse_fnc_editorAi"; };
class AzDocMark: COMSPEC_SSE_AzButton { idc = 95211; text = "$STR_COMSPEC_SSE_AZ_MakeFalse"; tooltip = "$STR_COMSPEC_SSE_ED_DocFalse_D"; ED_POS(22.475,15.2,6.725,1.1); action = "['docMark'] call comspec_sse_fnc_editorAi"; };
class AzDocType: COMSPEC_SSE_AzCombo { idc = 95212; tooltip = "$STR_COMSPEC_SSE_ED_DocType_D"; ED_POS(15.6,16.5,13.6,1.1); };
class AzDocGive: COMSPEC_SSE_AzButton { idc = 95213; text = "$STR_COMSPEC_SSE_AZ_Give"; tooltip = "$STR_COMSPEC_SSE_ED_DocGive_D"; ED_POS(15.6,17.8,7.6,1.1); action = "['docGive'] call comspec_sse_fnc_editorAi"; };
class AzDocsHelp: COMSPEC_SSE_AzHelp { idc = 95214; text = "$STR_COMSPEC_SSE_AZ_DocsHelp"; tooltip = "$STR_COMSPEC_SSE_ED_DocsHelp_D"; ED_POS(15.6,19.1,13.6,2.55); };

// --- onglet Comportement
class AzBehLabel: COMSPEC_SSE_AzText { idc = 95300; text = "$STR_COMSPEC_SSE_AZ_BehLabel"; tooltip = "$STR_COMSPEC_SSE_ED_BehHelp_D"; ED_POS(15.6,5.55,13.6,0.85); };
class AzBeh: COMSPEC_SSE_AzCombo { idc = 95301; tooltip = "$STR_COMSPEC_SSE_ED_BehHelp_D"; ED_POS(15.6,6.4,13.6,1.1); onLBSelChanged = "['changed'] call comspec_sse_fnc_editorAi"; };
class AzBehHelp: COMSPEC_SSE_AzHelp { idc = 95302; tooltip = "$STR_COMSPEC_SSE_ED_BehHelp_D"; ED_POS(15.6,7.55,13.6,1.75); };
class AzAnswers: COMSPEC_SSE_AzButton { idc = 95303; text = "$STR_COMSPEC_SSE_AZ_Answers"; tooltip = "$STR_COMSPEC_SSE_ED_Answers_D"; ED_POS(15.6,9.6,9.8,1.1); action = "['answers'] call comspec_sse_fnc_editorAi"; };
class AzAnswersHelp: COMSPEC_SSE_AzHelp { idc = 95304; text = "$STR_COMSPEC_SSE_AZ_AnswersHelp"; tooltip = "$STR_COMSPEC_SSE_ED_Answers_D"; ED_POS(15.6,10.9,13.6,2.55); };

// --- onglet Camp
class AzSideLabel: COMSPEC_SSE_AzText { idc = 95400; text = "$STR_COMSPEC_SSE_AZ_SideLabel"; tooltip = "$STR_COMSPEC_SSE_ED_SideHelp_D"; ED_POS(15.6,5.55,13.6,0.85); };
class AzSide: COMSPEC_SSE_AzCombo { idc = 95401; tooltip = "$STR_COMSPEC_SSE_ED_SideHelp_D"; ED_POS(15.6,6.4,13.6,1.1); onLBSelChanged = "['changed'] call comspec_sse_fnc_editorAi"; };
class AzSideHelp: COMSPEC_SSE_AzHelp { idc = 95402; tooltip = "$STR_COMSPEC_SSE_ED_SideHelp_D"; ED_POS(15.6,7.55,13.6,2.55); };

// ------------------------------------------------------------ droite : ce que verra le joueur
class AzPvTitle: COMSPEC_SSE_AzHeading { idc = 95500; text = "$STR_COMSPEC_SSE_AZ_PvTitle"; tooltip = "$STR_COMSPEC_SSE_AZ_Preview_D"; ED_POS(29.5,2.05,10.2,1.0); };
class AzPvCaption: COMSPEC_SSE_AzColText { idc = 95501; ED_POS(29.5,3.05,10.2,0.85); };
class AzPvArea: COMSPEC_SSE_EdText { idc = 95502; colorBackground[] = AZ_FIELD; ED_POS(29.5,4.0,10.2,7.4); };
// Le papier est dessiné dans ce groupe (fn_editorAiCard) : gabarit, photo, champs, MRZ, comme la visionneuse.
class AzPvCard: COMSPEC_SSE_RscControlsGroup { idc = 95503; ED_POS(29.7,4.2,9.8,7.0); };
class AzPvEmpty: COMSPEC_SSE_AzHelp { idc = 95504; size = SSE_Q(SSE_GH * AZ_TXT_BODY); ED_POS(29.8,4.5,9.6,6.6); class Attributes { font = "RobotoCondensed"; color = "#9fb0a8"; align = "center"; shadow = 0; }; };
class AzPvFlip: COMSPEC_SSE_AzButton { idc = 95505; text = "$STR_COMSPEC_SSE_AZ_Flip"; tooltip = "$STR_COMSPEC_SSE_AZ_Flip_D"; ED_POS(29.5,11.6,5.2,1.1); action = "['flip'] call comspec_sse_fnc_editorAi"; };
class AzPvSumTitle: COMSPEC_SSE_AzText { idc = 95506; font = "RobotoCondensedBold"; text = "$STR_COMSPEC_SSE_AZ_SumTitle"; tooltip = "$STR_COMSPEC_SSE_AZ_SumTitle_D"; ED_POS(29.5,12.95,10.2,0.85); };
class AzPvSumGrp: COMSPEC_SSE_RscControlsGroup {
    idc = 95507;
    ED_POS(29.5,13.8,10.2,9.1);
    class controls {
        class AzPvSum: RscStructuredText { idc = 95508; colorBackground[] = {0, 0, 0, 0}; ED_REL(0,0,9.7,9.1); size = SSE_Q(SSE_GH * AZ_TXT_BODY); };
    };
};

// ------------------------------------------------------------ bas : état, mémoire, appliquer
class AzBar: COMSPEC_SSE_EdText { idc = 95600; ED_POS(0,23.2,40,1.8); colorBackground[] = ED_BAR; };
class AzStatus: COMSPEC_SSE_AzHelp { idc = 95601; size = SSE_Q(SSE_GH * 0.8); tooltip = "$STR_COMSPEC_SSE_AZ_Status_D"; ED_POS(0.3,23.28,13.6,1.7); };
class AzMemory: COMSPEC_SSE_AzButton { idc = 95602; text = "$STR_COMSPEC_SSE_AZ_Memory"; tooltip = "$STR_COMSPEC_SSE_AZ_Memory_D"; ED_POS(14.2,23.45,8.2,1.3); action = "['memory'] call comspec_sse_fnc_editorAi"; };
class AzGroup: COMSPEC_SSE_AzButton { idc = 95603; text = "$STR_COMSPEC_SSE_AZ_ApplyGroup"; tooltip = "$STR_COMSPEC_SSE_ED_ApplyGroup_D"; ED_POS(22.6,23.45,9.6,1.3); action = "['applyGroup'] call comspec_sse_fnc_editorAi"; };
class AzApply: COMSPEC_SSE_AzPrimary { idc = 95604; text = "$STR_COMSPEC_SSE_ED_Apply"; tooltip = "$STR_COMSPEC_SSE_ED_Apply_D"; ED_POS(32.4,23.45,7.3,1.3); action = "['applyChecked'] call comspec_sse_fnc_editorAi"; };

// ------------------------------------------------------------ volet « Filtres » (sous le bouton, aligné à droite)
class AzFiBg: COMSPEC_SSE_AzPopup { idc = 95700; ED_POS(4.7,5.75,10.6,5.65); };
class AzFiAccent: COMSPEC_SSE_AzAccent { idc = 95701; ED_POS(4.7,5.75,10.6,0.1); };
class AzFiSideLabel: COMSPEC_SSE_AzText { idc = 95702; text = "$STR_COMSPEC_SSE_AZ_FiSide"; ED_POS(5.0,6.0,2.6,1.1); };
class AzFiSide: COMSPEC_SSE_AzCombo { idc = 95703; tooltip = "$STR_COMSPEC_SSE_ED_Filters_D"; ED_POS(7.6,6.0,7.45,1.1); onLBSelChanged = "['filter'] call comspec_sse_fnc_editorAi"; };
class AzFiRoleLabel: COMSPEC_SSE_AzText { idc = 95704; text = "$STR_COMSPEC_SSE_AZ_FiRole"; ED_POS(5.0,7.3,2.6,1.1); };
class AzFiRole: COMSPEC_SSE_AzCombo { idc = 95705; tooltip = "$STR_COMSPEC_SSE_ED_Filters_D"; ED_POS(7.6,7.3,7.45,1.1); onLBSelChanged = "['filter'] call comspec_sse_fnc_editorAi"; };
class AzFiStateLabel: COMSPEC_SSE_AzText { idc = 95706; text = "$STR_COMSPEC_SSE_AZ_FiState"; ED_POS(5.0,8.6,2.6,1.1); };
class AzFiState: COMSPEC_SSE_AzCombo { idc = 95707; tooltip = "$STR_COMSPEC_SSE_ED_Filters_D"; ED_POS(7.6,8.6,7.45,1.1); onLBSelChanged = "['filter'] call comspec_sse_fnc_editorAi"; };
class AzFiClear: COMSPEC_SSE_AzButton { idc = 95708; text = "$STR_COMSPEC_SSE_AZ_FiClear"; tooltip = "$STR_COMSPEC_SSE_AZ_FiClear_D"; ED_POS(5.0,10.0,4.9,1.1); action = "['filtersClear'] call comspec_sse_fnc_editorAi"; };
class AzFiClose: COMSPEC_SSE_AzButton { idc = 95709; text = "$STR_COMSPEC_SSE_AZ_Close"; ED_POS(10.15,10.0,4.9,1.1); action = "['filters'] call comspec_sse_fnc_editorAi"; };

// ------------------------------------------------------------ volet « Mémoire de la carte » (au-dessus du bouton)
class AzMeBg: COMSPEC_SSE_AzPopup { idc = 95800; ED_POS(14.2,12.4,14.4,10.4); };
class AzMeAccent: COMSPEC_SSE_AzAccent { idc = 95801; ED_POS(14.2,12.4,14.4,0.1); };
class AzMeTitle: COMSPEC_SSE_AzHeading { idc = 95802; text = "$STR_COMSPEC_SSE_AZ_Memory_T"; tooltip = "$STR_COMSPEC_SSE_ED_KeptNote_D"; ED_POS(14.5,12.65,13.8,1.0); };
class AzMeText: COMSPEC_SSE_AzHelp { idc = 95803; text = "$STR_COMSPEC_SSE_AZ_MemText"; tooltip = "$STR_COMSPEC_SSE_ED_KeptNote_D"; size = SSE_Q(SSE_GH * AZ_TXT_BODY); ED_POS(14.5,13.7,13.8,3.7); };
class AzMeKeep: COMSPEC_SSE_AzPrimary { idc = 95804; text = "$STR_COMSPEC_SSE_AZ_MemKeep"; tooltip = "$STR_COMSPEC_SSE_ED_Keep_D"; ED_POS(14.5,17.55,13.8,1.1); action = "['keepChecked'] call comspec_sse_fnc_editorAi"; };
class AzMeListLabel: COMSPEC_SSE_AzText { idc = 95805; text = "$STR_COMSPEC_SSE_AZ_MemList"; ED_POS(14.5,18.85,13.8,0.85); };
class AzMeList: COMSPEC_SSE_AzCombo { idc = 95806; tooltip = "$STR_COMSPEC_SSE_ED_KeptNote_D"; ED_POS(14.5,19.7,13.8,1.1); onLBSelChanged = "['keptSel'] call comspec_sse_fnc_editorAi"; };
class AzMeReload: COMSPEC_SSE_AzButton { idc = 95807; text = "$STR_COMSPEC_SSE_AZ_MemReload"; tooltip = "$STR_COMSPEC_SSE_ED_KeptReload_D"; ED_POS(14.5,20.95,4.5,1.1); action = "['keptReload'] call comspec_sse_fnc_editorAi"; };
class AzMeForget: COMSPEC_SSE_AzButton { idc = 95808; text = "$STR_COMSPEC_SSE_AZ_MemForget"; tooltip = "$STR_COMSPEC_SSE_ED_KeptForget_D"; ED_POS(19.15,20.95,4.5,1.1); action = "['keptForget'] call comspec_sse_fnc_editorAi"; };
class AzMeClose: COMSPEC_SSE_AzButton { idc = 95809; text = "$STR_COMSPEC_SSE_AZ_Close"; ED_POS(23.8,20.95,4.5,1.1); action = "['memory'] call comspec_sse_fnc_editorAi"; };
