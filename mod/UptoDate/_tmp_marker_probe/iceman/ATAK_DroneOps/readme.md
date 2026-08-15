# ATAK Drone Ops

Adds an ATAK app and interactions for tasking owned Black Hornet and AR-2 style micro UAVs.

Scan and Protect contacts are drawn locally on the owner's ATAK map only. Contact labels use `BH-<drone>-<contact>`, for example `BH-1-0001`; the drone camera tracks the closest detected contact to the operator. Connecting to a supported AR-2/Black Hornet wakes the drone, enables UAV connectability for the owner, and starts the engine.

Scan searches around a selected point. Protect uses the same search/mark behavior but keeps the drone loiter centered on the operator as they move.
