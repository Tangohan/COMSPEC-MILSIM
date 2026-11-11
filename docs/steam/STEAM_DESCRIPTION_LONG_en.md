# COMSPEC ATAK: long description (EN)

Detailed version of the Steam Workshop description (item 3684656708), as of 4.4.0 (operations room).
The published description is the compact bilingual `mod/COMSPEC_ATAK_Native/publisher/STEAM_DESCRIPTION.txt`
(Steam limit: 8,000 UTF-8 bytes, French and English together), sent by `mod/COMSPEC_ATAK_Native/tools/steam_set_description.py`.
Use this text for the website, Discord or a future Steam guide.

## The mod

COMSPEC ATAK puts a real tactical ATAK smartphone in Arma 3. `Ctrl+U` carries it in a corner of the screen
(you keep playing, the map follows you), `Ctrl+Shift+U` takes it in hand (mouse, keyboard, portrait or landscape,
mini or full screen from 60 to 100%). Held sideways, apps show two columns. 60+ apps, a realistic radio network,
3D items, a real operations room and a live link to Athena (https://athena-comspec.eu), the COMSPEC web portal.
French, English and Simplified Chinese built in; more languages through community translation mods.
Playable without Athena.

## What's new

- Operations room (TOC): real full-size 3D screens made for the mod. 4-screen (2×2) and 6-screen (3×2) video walls, a 98-inch screen on the wall or on a rolling stand, a 65-inch screen hung from the ceiling, an LED clock strip and element name plates. A wall shows one big picture, or different content on each screen.
- Live satellite view, north up, in day, green night vision or thermal. Every ally carrying an ATAK is framed with their symbol and callsign (never a unit without ATAK, never an enemy). Two satellites per side, steered from the post laptop or the phone.
- Rebuilt post laptop: map, satellite, timers, radio, duty log, execution checklist (EXCHECK), casualties, element requests and a FLASH button.
- The whole room is saved continuously by the server and comes back by itself next session.
- Armed drones: take control from the Drone app, put it in orbit, mark a target and it fires on its own; hunter-killer mode proposes the targets it spots.
- A map readable at every zoom, tactical style, real NATO symbols (2525D) and vehicle silhouettes.

More displays for the room are in the works (for example a mosaic of ATAK cameras, driving every screen of a room
from a single laptop, a ready-made room to place in Eden or Zeus): they will be announced when they ship.

## Requirements and compatibility

- Required: CBA_A3 (clients and server). It is the only dependency.
- Detected automatically when loaded:
  - ACE3: medical (monitor, triage, blood type on alerts), arsenal (Athena loadouts), interaction menus, explosives, NVGs, laser, weather, deployable antennas.
  - ACRE2 or TFAR: phone linked to the radio through the MPU5, voice relayed over the mesh (server setting, off by default), radios shown on the post laptop, TFAR jammers.
  - ZEN: COMSPEC modules in the Zeus modules.
  - Live Presentation (FENRIR): presentations shown in the Briefing app.
  - COMSPEC SSE: identity papers and checks, ID Check, Records and My cover apps, SEEK II terminal, SSE data.
  - Jammers from Crows Electronic Warfare and other electronic warfare mods.
- COMSPEC Overwatch connect is built in: do not load the old `@COMSPECOverwatch` as well.
- COMSPEC Link (two Windows extensions shipped with the mod): Athena link, music, Bluetooth transfer, local configuration, version check. Nothing to install.

## The apps (launcher, App Store)

- Navigation: Map, GPS, Routes, Route, Terrain, Weather, Distances, Garmin.
- Communication: Messages (channels), SMS, Squad, Alerts, BFT, Inter-team, Wave Relay, Numbers, Discord.
- Support and fires: CAS (typed fire missions), JTAC, Drone (armed drones included).
- Specialties: Medical, Sniper, Explosives, EW, Counter-drone, ATAK Link.
- Intelligence: FRS / FRM, Recon, SALUTE, Photo, Gallery, Live Feed, Mark tracking, Extraction, SSE, Target Deck.
- Mission: TOC post, Tasks, Reports, End-of-op report, Briefing, Academy, Plan, Logistics, Replay, Community.
- System: App Store, Athena, Admin, Network, Bluetooth, Sync, Status, Profile, Accessories, Settings, Screen time, Debug, Credits.
- Civilian: Music, Calculator, Notepad, UberEats, Year in review, YouTube video (when the admin enables it), Tinder (hides an HVT hunt with informant tips).

Leaders can require or forbid apps (App Store, server settings). Pilot mode and grouped apps are addon settings,
off by default.

## Map and navigation

- Friendly tracking (BFT) through the ATAK, GPS accuracy circles, realistic GPS (buildings, forests, satellite acquisition).
- Tactical style: ringed pawns, name plates, contacts recognisable by shape (diamond enemy, square neutral, circle friend), old positions faded with their age. Optional 2525D icons. Names readable at every zoom, never overlapping.
- A unit aboard a vehicle shows the silhouette of its plane, helicopter, vehicle or boat, and the crew is counted once.
- Named, editable markers (time placed, author, channel), optionally spread over the ATAK network only; holding left click points out a spot for the whole side.
- Road-following lines (fastest / shortest, main or discreet roads, avoid bridges), line of sight, viewshed, grid search, Topo, Satellite, Hybrid and SLATE base maps, HD photo capture of the map.
- Cursor bar: MGRS grid, altitude, distance, bearing and heading.
- Zones app (battleplan): grid, named axes, zone states.
- Routes app: foot, vehicle, air or water routes, typed steps (checkpoint, phase line, LZ…) with H+ time and instruction, step-by-step guidance (vibration, Garmin watch), sharing with team, squad or side, shown on the Arma map, Athena and in 3D.
- Web map through a QR code from the phone's map.

## Network and radio

- The network follows terrain, forests, buildings, weather and relay load; jammers (Zeus, modules, other mods). Realism on by default, adjustable in Escape › Realism.
- Off the mesh, the phone names the missing link and its cause (terrain, distance, buildings) with a tip, and the map shows it as a red dashed line.
- MPU5 mesh radio linked by EUD cable or Wi-Fi, radio battery, multi-hop per channel; destructible relays, 3D towers and monopoles, mini building antennas, vehicle gateways; satellite antenna and mast antenna deployed through ACE.
- ACRE2 / TFAR voice relayed over the mesh to teammates' linked radios (server option). Network app › MPU TROUBLESHOOTING: the assistant finds the broken link and suggests the fix.
- Without network: messages, SMS, reports and routes are sent when the signal returns. Bluetooth between phones (photos, music, routes, SEEK files), back-to-back NFC to share a camera.

## Fires, drones, intelligence

- Typed fire missions: 60, 81 and 120 mm mortars, howitzer, rocket launcher, naval or CAS, with ranges, ammunition, delays and danger-close distance; choose the unit to call; AI really fires, a player gunner receives the mission; + / – / L / R corrections, EFFECT, END OF MISSION.
- JTAC: terminal, CHECK-IN to BDA timeline, 9-LINE (manual or auto-filled), 5-LINE, briefs sent to pilots, tactical map, magnetic heading, laser.
- Drones: fleet, flight controller, map orders, no-fly zones, gimbal view day / night / thermal, marking from a friendly drone feed. Armed drones: orbit, marked target, fire while you watch, hunter-killer mode.
- Live video: IP cameras, camera of an ATAK carrier, adjustable focal length, sharing over relays, Bluetooth or NFC.
- Photo and Gallery: MGRS grid and Zulu time, automatic face blur (several styles).
- DL-7 extraction: plug the phone into a computer, phone or USB key, crack the password, download the files left by the mission maker.
- A lost ATAK can be picked up by enemy AI and used to hunt the team; compromise alert to confirm.

## TOC post and operations room

- Post laptop: large post map (COP), SAT, timers, radio (your ACRE2 or TFAR radios, RX / TX lights), casting to screens, duty log in ZULU time, execution checklist (EXCHECK), casualties, element requests (MEDEVAC, calls for fire, resupply: the answer goes to the requester's phone), side groups, report feed, order writer, messages to the side (ROUTINE, URGENT, FLASH) tabs. TAKE THE POST signs the operator in; FLASH shows a message in red on every post screen for 3 minutes.
- TOC post app on the phone, next to a post laptop: messages to the side, orders, groups, satellites, cameras on the screens.
- Screens: TOC screens, COMSPEC screens (tripod, dual, TV, monitor), projector, and the big operations room screens (4 and 6-screen walls, 98 inches on the wall or a rolling stand, 65 inches from the ceiling, clock strip, name plates). Each screen, or each panel of a wall, keeps its own display: map and feed, ORBAT, live camera, slides (Google Slides included), Academy lessons, laptop picture-in-picture, satellite, ZULU and local clock with timers, squad screen (one group, its members and their status, its last report), duty log, casualties, requests.
- Satellites: two per side, one operator at a time (others see « Read only »); view from 80 m to 5 km, day, night or thermal, following a group or unit as the ATAK network knows it.
- Timers: stopwatch, countdown, H-hour (H- then H+), shared by every laptop of the side and shown on clock screens.
- Saving: screens (place, name, display), timers, satellites, duty log, execution checklist, requests and casualties are saved continuously and restored next session on the same map and mission (server setting, on by default).
- Orders to friendly AI (right-click on the map, Tasks app, post laptop): move, patrol, attack, hold, fall back, follow, mount, stance, occupy a building, rules of engagement; « Then… » queues an order; the group reports its own contacts. AI carrying an ATAK shows on the BFT.

## Hardware

- 9 phones and tablets: Samsung S7 (coyote, olive, black; default), Samsung S23, iPhone 17 Pro, V2 Enhanced, Xiaomi X3D, Sonim XP8, rugged tablet; vest-mounted phone, ATAK on the JPC, vehicle tablet.
- Personal phone: every phone has an owner, 4 to 8-digit code with a hint, lock screen, wallpapers, three themes (DARK, DAY, NVG NIGHT), notification centre grouped by app.
- Wrist GPS: a standalone item with no phone (position, compass, points and routes on a monochrome screen with buttons).
- Battery (drains faster in the cold), power bank, CS-10 charging dock (10 phones), antennas (stub, whip, patch), NVG filter, repair kit, DL-7 cable, EUD cable.
- Garmin watches (4 colours + 3 Instinct): map, friendlies, music player, H-hour, Jumpmaster, guidance.
- Tactical info in NVGs, gestures, dirt, cracks and blood on the screen, stealth mode.

## Zeus and mission makers

- Eden / Zeus modules sorted by category: Zeus control (disable, lock, mute, jam, damage an ATAK), AI with ATAK, area jammer, relay ("active only when" condition), COMSPEC screen, TOC screen (display, satellite and squad shown, wall panels, prepared execution checklist), data to extract, files, Tinder HVT profile, map capture, roads and towns survey. Object configuration window.
- Zeus centre: every player's phone state (battery, Athena link, network, drones, alerts), SERVER tab (game state) and labels above players that flag what is wrong.
- Admin app: every player's ATAK, Zeus effects, the community's ATAK fleet, AI debug.
- After-action report forced in the phone (IMPOSE tab or Zeus menu).
- COMSPEC Modules: a separate addon can add its own apps and map layers (see `MODULES.md`).

## Server settings

200+ CBA settings in Options › Addon Options › COMSPEC, grouped under "ATAK · …": phone access, map,
geolocation, teams and tracking, battery and memory, data radio (MPU5), fire support, armed drones, routes, photo,
medical, enemy capture, data extraction, local mode and TOC post (full local mode without Athena, saved TOC screens),
removed, required or forbidden apps, Garmin, Tinder, debug. Realism settings are also in Escape › Realism, each
explained with an example.

## Athena (athena-comspec.eu)

- Link your account from the Athena app: Steam, e-mail and password, e-mail code or portal pairing code.
- Sent up: positions, markers, messages, orders, alerts, reports, photos, briefing plan, shared routes, distances, flight hours, play time, Garmin data, after-action reports. The community chooses on Athena what the game sends (everything, or reports only).
- Sent down: ACE arsenal loadouts, slides, Academy courses, unit medical records, site music, the community's instructions and required kit (Community app), identity and papers (with COMSPEC SSE), admin notices.
- ON MY PHONE: a QR code opens your reports and the map on your real phone.
- Without Athena: full local mode (server setting), or answer "No" to the Athena service; orders, TOC messages, the TOC room, 9-line and mission slides work in game.

## Installation and dedicated server

1. Subscribe to CBA_A3 and COMSPEC ATAK.
2. Load both mods on the clients and the server (`-mod=@CBA_A3;@COMSPEC_ATAK_Native`).
3. Server with signature checks: copy `keys\comspec_atak_native.bikey` into the server's `keys` folder.
4. After an update, quit Arma completely.

## Support, credits, license

- Bug: double-click `Envoyer_mes_logs.bat` in the mod folder (zip on the desktop, passwords hidden) and post it on the COMSPEC Discord (https://discord.gg/CCpJA6Nks). Escape menu › Link troubleshooting diagnoses the link.
- Credits: ideas inspired by Better CAS Environment (BCE, Aaren) and the map tools of Simple Map Tools (POLPOX), no code reused; Chinese translation RickLeo and MOYA; fonts Barlow Condensed, B612, Rajdhani, Share Tech (SIL OFL 1.1).
- License: © 2026 COMSPEC, COMSPEC license (all rights reserved): personal use from the official Workshop page; copying, modifying, repacking and redistributing are forbidden without written permission.
