#!/bin/bash
# Script de build extension C# COMSPEC
# Nécessite .NET 6 SDK installé

echo "🔨 Build COMSPEC Extension..."

cd "$(dirname "$0")"

# Build Release x64
dotnet build COMSPECExtension.csproj -c Release -p:Platform=x64

if [ $? -eq 0 ]; then
    echo "✅ Build réussi"
    
    # Copier DLL vers racine mod
    cp bin/x64/Release/net6.0/COMSPECExtension_x64.dll ../
    
    echo "📦 DLL copiée vers @COMSPECOverwatch/"
    echo ""
    echo "⚠️  N'oubliez pas d'autoriser la DLL dans BattlEye !"
    echo "    Fichier: battleye/beserver_x64.cfg"
    echo "    Ligne: allowedLoadFileExtensions[] = {\"dll\"};"
else
    echo "❌ Build échoué"
    exit 1
fi
