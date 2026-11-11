@echo off
setlocal
:: COMSPEC Flashbang : mod independant (seule dependance CBA_A3). Build HEMTT (config rapifiee, p3d binarises),
:: copie dans @COMSPEC_Flashbang, signature avec la cle propre au mod (signing\, hors Git, creee au premier build).
:: Lancer depuis PowerShell : cmd /c "<chemin>\mod\COMSPEC_Flashbang\build_mod.bat"
set "ROOT=%~dp0"
set "SRC=%ROOT%Sources"
set "OUT=%ROOT%@COMSPEC_Flashbang"
where hemtt >nul 2>nul || (echo [ERREUR] hemtt introuvable : winget install BrettMayson.HEMTT & exit /b 1)
pushd "%SRC%"
hemtt build
if errorlevel 1 (popd & echo [ERREUR] hemtt build a echoue & exit /b 1)
popd
if not exist "%OUT%\addons" mkdir "%OUT%\addons"
del /q "%OUT%\addons\*.pbo" "%OUT%\addons\*.bisign" 2>nul
copy /Y "%SRC%\.hemttout\build\addons\comspec_flashbang_main.pbo" "%OUT%\addons\comspec_flashbang_main.pbo" >nul || (echo [ERREUR] PBO non copie : fermer Arma & exit /b 1)
copy /Y "%ROOT%mod.cpp" "%OUT%\mod.cpp" >nul
copy /Y "%ROOT%logo.paa" "%OUT%\logo.paa" >nul
copy /Y "%ROOT%README.md" "%OUT%\README.md" >nul
:: %ROOT% se termine par \ : on retire le slash final pour PowerShell.
set "MODROOT=%ROOT:~0,-1%"
powershell -NoProfile -ExecutionPolicy Bypass -File "%ROOT%tools\sign_pbos.ps1" -ModRoot "%MODROOT%" || (echo [ERREUR] Signature PBO echouee & exit /b 1)
echo [OK] Mod : %OUT%
endlocal
