# COMSPEC ATAK: long description (EN)

Detailed version of the Steam Workshop description (item 3684656708), as of 2.9.34.
The published description is the compact bilingual `mod/COMSPEC_ATAK_Native/publisher/STEAM_DESCRIPTION.txt`
(Steam limit: 8,000 characters), sent by `mod/COMSPEC_ATAK_Native/tools/steam_set_description.py`.
Use this text for the website, Discord or a future Steam guide.

## The mod

COMSPEC ATAK puts a real tactical ATAK smartphone in Arma 3. `Ctrl+U` carries it in a corner of the screen
(you keep playing, the map follows you), `Ctrl+Shift+U` takes it in hand (mouse, keyboard, portrait or landscape,
mini or full screen from 60 to 100%). 60+ apps, a realistic radio network, 3D items and a live link to
Athena (https://athena-comspec.eu), the COMSPEC web portal. French, English and Simplified Chinese built in;
more languages through community translation mods. Playable without Athena.

## Requirements and compatibility

- Required: CBA_A3 (clients and server). It is the only dependency.
- Detected automatically when loaded:
  - ACE3: medical (monitor, triage, blood type on alerts), arsenal (Athena loadouts), interaction menus, explosives, NVGs, laser, weather.
  - ACRE2 or TFAR: phone linked to the radio through the MPU5, voice relayed over the mesh (server setting, off by default), TFAR jammers.
  - ZEN: COMSPEC modules in the Zeus modules.
  - Live Presentation (FENRIR): presentations shown in the Briefing app.
  - COMSPEC SSE: camera, SEEK terminal, SSE data.
  - Jammers from Crows Electronic Warfare and other electronic warfare mods.
- COMSPEC Overwatch connect is built in: do not load the old `@COMSPECOverwatch` as well.
- COMSPEC Link (two Windows extensions shipped with the mod): Athena link, music, Bluetooth transfer, local configuration, version check. Nothing to install.

## Apps (launcher, App Store)

- Navigation: Map, GPS, Routes, Waypoints, Terrain, Weather, Distances, Garmin.
- Communication: Messages (channels, SMS), Group, Alerts, BFT, Inter-team, C2, Wave Relay, Numbers, Discord.
- Support and fires: CAS (typed fire missions), JTAC, Drone; NINE LINE app (CAS / MEDEVAC drafts).
- Specialties: Medical, Sniper, Breach, Explosives, EW, Counter-drone, Ally link.
- Intelligence: FRS / FRM, Recon, SALUTE, Photo, Gallery, Live video, Tracking, Extraction, SSE, OSINT (work in progress), Wanted.
- Mission: TOC post, Tasks, Reports, After-action report, Briefing, Academy, Plan, Logistics, Replay.
- System: App Store, Athena, Admin, Network, Bluetooth, Resync, Status, Profile, Accessories, Settings, Screen time, Debug, Credits.
- Civilian: Music, Calculator, Notes, UberEats, Tinder (hides an HVT hunt with informant tips).

Leaders can require or forbid apps (App Store, server settings).

## Map and navigation

- Friendly tracking (BFT) through the ATAK, GPS accuracy circles, realistic GPS (buildings, forests, satellite acquisition).
- Named, editable markers (time placed, author, channel), optionally spread over the ATAK network only.
- Road-following lines (fastest / shortest, main or discreet roads, avoid bridges), line of sight, viewshed, grid search, Topo, Satellite and Hybrid base maps, HD photo capture of the map.
- Cursor bar: MGRS grid, altitude, distance, bearing and heading.
- Routes app: foot, vehicle, air or water routes, typed steps (checkpoint, phase line, LZ…) with H+ time and instruction, step-by-step guidance (vibration, Garmin watch), sharing with team, squad or side, shown on the Arma map, Athena and in 3D.
- Web map through a QR code from the phone's map.

## Network and radio

- The network follows terrain, forests, buildings, weather and relay load; jammers (Zeus, modules, other mods).
- MPU5 mesh radio linked by EUD cable or Wi-Fi, radio battery, multi-hop per channel; relays, destructible 3D towers (30, 40, 50 m) and 12 / 20 m monopoles; vehicle gateways.
- ACRE2 / TFAR voice relayed over the mesh to teammates' linked radios (server option).
- Without network: messages, reports and routes are sent when the signal returns. Bluetooth between phones (photos, music, routes, SEEK files), back-to-back NFC to share a camera.

## Fires, drones, intelligence

- Typed fire missions: 60, 81 and 120 mm mortars, howitzer, rocket launcher, naval or CAS, with ranges, ammunition, delays and danger-close distance; AI really fires, a player gunner receives the mission; + / – / L / R corrections, EFFECT, END OF MISSION.
- JTAC: terminal, CHECK-IN to BDA timeline, 9-LINE (manual or auto-filled), tactical map, magnetic heading, laser.
- Drones: fleet, flight controller, map orders, no-fly zones, gimbal view day / night / thermal, marking from a friendly drone feed.
- Live video: IP cameras, camera of an ATAK carrier, adjustable focal length, sharing over relays, Bluetooth or NFC.
- Photo and Gallery: MGRS grid and Zulu time, automatic face blur (several styles).
- DL-7 extraction: plug the phone into a computer, phone or USB key, crack the password, download the files left by the mission maker.
- A lost ATAK can be picked up by enemy AI and used to hunt the team; compromise alert to confirm.

## TOC post, screens, AI

- TOC laptop: large map, side groups, report feed, order writer, messages to the side (ROUTINE, URGENT, FLASH).
- TOC screens, COMSPEC screens (tripod, dual, TV, monitor) and projector: TOC map, slides (Google Slides included), Academy lessons, live cameras and drones, picture-in-picture of the TOC laptop.
- Orders to friendly AI (right-click on the map, Orders app, TOC laptop): move, patrol, attack, hold, fall back, follow, mount, stance; AI carrying an ATAK shows on the BFT.

## Hardware

- 9 phones and tablets: Samsung S7 (coyote, olive, black; default), Samsung S23, iPhone 17 Pro, V2 Enhanced, Xiaomi X3D, Sonim XP8, rugged tablet; vest-mounted phone, ATAK on the JPC, vehicle tablet.
- Battery, power bank, CS-10 charging dock (10 phones), antennas (stub, whip, patch), NVG filter, repair kit, DL-7 cable, EUD cable.
- Garmin watches (4 colours + 3 Instinct): map, friendlies, music player, H-hour, Jumpmaster, guidance.
- Tactical info in NVGs, gestures, dirt and blood on the screen, stealth mode, lock screen.

## Zeus and mission makers

- Eden / Zeus modules: Zeus control (disable, lock, mute, jam, damage an ATAK), AI with ATAK, area jammer, relay ("active only when" condition), COMSPEC screen, TOC screen (and TOC post), data to extract, files, Tinder HVT profile, map capture, roads and towns survey.
- Admin app: every player's ATAK, Zeus effects, the community's ATAK fleet, AI debug.
- After-action report forced in the phone (IMPOSE tab or Zeus menu).
- COMSPEC Modules: a separate addon can add its own apps and map layers (see `MODULES.md`).

## Server settings

200+ CBA settings in Options › Addon Options › COMSPEC, grouped under "ATAK · …": phone access, map,
geolocation, teams and tracking, battery and memory, data radio (MPU5), fire support, routes, photo, medical,
enemy capture, data extraction, local mode and TOC post (full local mode without Athena), removed, required or
forbidden apps, Garmin, Tinder, debug.

## Athena (athena-comspec.eu)

- Link your account from the Athena app: Steam, e-mail and password, e-mail code or portal pairing code.
- Sent up: positions, markers, messages, orders, alerts, reports, photos, briefing plan, shared routes, distances, flight hours, play time, Garmin data, after-action reports. The community chooses on Athena what the game sends (everything, or reports only).
- Sent down: ACE arsenal loadouts, slides, Academy courses, unit medical records, site music, admin notices.
- Without Athena: full local mode (server setting), or answer "No" to the Athena service; orders, TOC messages, 9-line and mission slides work in game.

## Installation and dedicated server

1. Subscribe to CBA_A3 and COMSPEC ATAK.
2. Load both mods on the clients and the server (`-mod=@CBA_A3;@COMSPEC_ATAK_Native`).
3. Server with signature checks: copy `keys\comspec_atak_native.bikey` into the server's `keys` folder.
4. After an update, quit Arma completely.

## Support, credits, license

- Bug: double-click `Envoyer_mes_logs.bat` in the mod folder (zip on the desktop, passwords hidden) and post it on the COMSPEC Discord (https://discord.gg/CCpJA6Nks). Escape menu › Link troubleshooting diagnoses the link.
- Credits: ideas inspired by Better CAS Environment (BCE, Aaren) and the map tools of Simple Map Tools (POLPOX), no code reused; Chinese translation RickLeo and MOYA; fonts Barlow Condensed, B612, Rajdhani, Share Tech (SIL OFL 1.1).
- License: © 2026 COMSPEC, COMSPEC license (all rights reserved): personal use from the official Workshop page; copying, modifying, repacking and redistributing are forbidden without written permission.
