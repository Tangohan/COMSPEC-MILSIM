private _fog = missionNamespace getVariable ["comspec_sse_lastResult", createHashMap];
private _lines = _fog getOrDefault ["lines", []];
hint ((["CONSULTATION SSE"] + _lines) joinString endl);
true
