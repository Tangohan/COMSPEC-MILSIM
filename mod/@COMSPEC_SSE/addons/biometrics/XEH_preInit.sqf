/*
    Enregistre les raccourcis CBA (Options > Contrôles > Configurer les addons > COMSPEC SSE) :
      Ctrl+Shift+S  app SSE du téléphone ATAK, sinon BII-10 ou SEEK II ;
      Ctrl+Shift+R  fiche de renseignement (RENS).
    Noms et aides écrits en français : l'ATAK natif les traduit dans la langue du téléphone (fn_keyRelang, dictionnaire
    i18n/en.txt) ; les traduire ici fausserait le texte de référence qu'il garde.
*/
// Réglages du moteur biométrique : partout (serveur compris), avant les raccourcis.
[] call comspec_sse_fnc_bioInitSettings;

if (!hasInterface) exitWith {};
if (!isNil "cba_fnc_addKeybind") then {
    [
        "COMSPEC SSE",
        "comspec_sse_open_seek_ii",
        ["Ouvrir l'outil SSE (ATAK / SEEK II)", "Ouvre l'app SSE du téléphone ATAK. Sans téléphone : BII-10 Identifi si le mod BII est chargé, sinon le terminal SEEK II sur la personne visée."],
        {
            if (!hasInterface) exitWith { false };
            if (!isNil "comspec_sse_fnc_openSeekKeybind") then {
                [] call comspec_sse_fnc_openSeekKeybind;
            } else {
                ["Terminal SSE indisponible : le mod COMSPEC SSE n'a pas chargé le raccourci.", "WARNING"] call comspec_sse_fnc_notify;
            };
            true
        },
        { false },
        [0x1F, [true, true, false]], // Ctrl+Shift+S
        false, 0, false
    ] call cba_fnc_addKeybind;

    [
        "COMSPEC SSE",
        "comspec_sse_open_intel_note",
        ["Rédiger une fiche RENS", "Ouvre le rédacteur de fiche de renseignement (Athena / Overwatch), sinon l'app FRS / FRM du téléphone ATAK."],
        {
            if (!hasInterface) exitWith { false };
            if (!isNil "comspec_sse_fnc_openIntelNoteKeybind") then { [] call comspec_sse_fnc_openIntelNoteKeybind; };
            true
        },
        { false },
        [0x13, [true, true, false]], // Ctrl+Shift+R
        false, 0, false
    ] call cba_fnc_addKeybind;
};
