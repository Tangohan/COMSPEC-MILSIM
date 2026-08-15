# Iceman ATAK Route

Companion addon for BCE/cTab ATAK route planning.

First slice:
- Patches BCE's existing empty Route ATAK app and opens the existing ATAK side page while custom controls are stabilized.
- Lets the user set start/end by tapping the ATAK map, or set start from current player position.
- Builds a route over Arma road objects with `roadsConnectedTo`.
- Draws the route, start/end points, and turn points on the ATAK map.
- Tracks ETA from current speed and gives cTab notifications within 50m of the next turn.

Pack this folder as `ATAK_Route` under the prefix `\ATAK_Route`.


