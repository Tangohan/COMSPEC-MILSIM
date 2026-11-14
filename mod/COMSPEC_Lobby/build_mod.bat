@echo off
setlocal
:: COMSPEC Lobby : mod independant (aucune dependance). Build HEMTT (config rapifiee, p3d binarises),
:: copie dans @COMSPEC_Lobby, signature avec la cle propre au mod (hors depot : %APPDATA%\COMSPEC\signing\lobby, creee une fois puis reutilisee : a garder).
:: Lancer depuis PowerShell : cmd /c "<chemin>\mod\COMSPEC_Lobby\build_mod.bat"
set "ROOT=%~dp0"
set "SRC=%ROOT%Sources"
set "OUT=%ROOT%@COMSPEC_Lobby"
where hemtt >nul 2>nul || (echo [ERREUR] hemtt introuvable : winget install BrettMayson.HEMTT & exit /b 1)
pushd "%SRC%"
hemtt build
if errorlevel 1 (popd & echo [ERREUR] hemtt build a echoue & exit /b 1)
popd
if not exist "%OUT%\addons" mkdir "%OUT%\addons"
del /q "%OUT%\addons\*.pbo" "%OUT%\addons\*.bisign" 2>nul
copy /Y "%SRC%\.hemttout\build\addons\comspec_lobby_main.pbo" "%OUT%\addons\comspec_lobby_main.pbo" >nul || (echo [ERREUR] PBO non copie : fermer Arma & exit /b 1)
copy /Y "%ROOT%mod.cpp" "%OUT%\mod.cpp" >nul
copy /Y "%ROOT%logo.paa" "%OUT%\logo.paa" >nul
copy /Y "%ROOT%README.md" "%OUT%\README.md" >nul
:: %ROOT% se termine par \ : on retire le slash final pour PowerShell.
set "MODROOT=%ROOT:~0,-1%"
powershell -NoProfile -ExecutionPolicy Bypass -File "%ROOT%tools\sign_pbos.ps1" -ModRoot "%MODROOT%" || (echo [ERREUR] Signature PBO echouee & exit /b 1)
echo [OK] Mod : %OUT%
endlocal
