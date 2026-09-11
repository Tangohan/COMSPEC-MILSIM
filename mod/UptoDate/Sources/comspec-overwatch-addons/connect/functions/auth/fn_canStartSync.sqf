/*
    Session Athena utilisable pour les transmissions (READY + canal poste OK).
    Aligné sur fn_isC2Ok : un compte trouvé avec erreur C2_* ne démarre pas les Tx.
    Pendant HandshakeQuiet : pas de Tx (évite le gel / spam 401 au boot).
*/
(!(missionNamespace getVariable ["COMSPEC_HandshakeQuiet", false]))
&& {[] call comspec_overwatch_connect_fnc_isReady}
&& {[] call comspec_overwatch_connect_fnc_isC2Ok}
