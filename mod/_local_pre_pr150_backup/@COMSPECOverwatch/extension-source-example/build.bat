@echo off
REM Script de build extension C# COMSPEC (Windows)
REM Nécessite .NET 6 SDK installé

echo Build COMSPEC Extension...
echo.

cd /d "%~dp0"

REM Build Release x64
dotnet build COMSPECExtension.csproj -c Release -p:Platform=x64

if %errorlevel% equ 0 (
    echo.
    echo [OK] Build reussi
    
    REM Copier DLL vers racine mod
    copy /Y bin\x64\Release\net6.0\COMSPECExtension_x64.dll ..\
    
    echo [OK] DLL copiee vers @COMSPECOverwatch\
    echo.
    echo [WARNING] N'oubliez pas d'autoriser la DLL dans BattlEye !
    echo           Fichier: battleye\beserver_x64.cfg
    echo           Ligne: allowedLoadFileExtensions[] = {"dll"};
) else (
    echo.
    echo [ERROR] Build echoue
    pause
    exit /b 1
)

pause
