/*
    Nom unique du repère local de bâtiment désigné (session client).
*/
private _seq = (missionNamespace getVariable ["COMSPEC_EcotiBldgMkSeq", 0]) + 1;
missionNamespace setVariable ["COMSPEC_EcotiBldgMkSeq", _seq, false];
format ["COMSPEC_ECOTI_BLDG_%1_%2", _seq, round (diag_tickTime * 1000)]
