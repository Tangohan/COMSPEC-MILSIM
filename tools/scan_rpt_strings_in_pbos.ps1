#Requires -Version 5.1
<#
.SYNOPSIS
  Localise le PBO qui contient les chaînes RPT du crash STACK_OVERFLOW.
.DESCRIPTION
  Scan binaire stream (sans charger tout le fichier en mémoire).
  Usage:
    powershell -File tools\scan_rpt_strings_in_pbos.ps1 -Root "F:\SteamLibrary\steamapps\common\Arma 3\!Workshop"
#>
param(
    [Parameter(Mandatory = $false)]
    [string]$Root = "F:\SteamLibrary\steamapps\common\Arma 3\!Workshop",
    [string[]]$Needles = @(
        "Was unit a player?",
        "ACE Was detected, adding event handler for ace",
        "event handler was ran on",
        "XEH_postInit running"
    )
)

$ErrorActionPreference = "Continue"
if (-not (Test-Path -LiteralPath $Root)) {
    Write-Error "Root introuvable: $Root"
    exit 1
}

function Test-FileContainsAscii([string]$Path, [string]$Needle) {
    $fs = [IO.File]::Open($Path, [IO.FileMode]::Open, [IO.FileAccess]::Read, [IO.FileShare]::ReadWrite)
    try {
        $needleBytes = [Text.Encoding]::ASCII.GetBytes($Needle)
        $nLen = $needleBytes.Length
        if ($nLen -lt 1) { return $false }
        $bufSize = 1024 * 1024
        $buf = New-Object byte[] $bufSize
        $overlap = New-Object byte[] ($nLen - 1)
        $overlapLen = 0
        while ($true) {
            $read = $fs.Read($buf, 0, $bufSize)
            if ($read -le 0) { break }
            $window = New-Object byte[] ($overlapLen + $read)
            if ($overlapLen -gt 0) {
                [Array]::Copy($overlap, 0, $window, 0, $overlapLen)
            }
            [Array]::Copy($buf, 0, $window, $overlapLen, $read)
            for ($i = 0; $i -le ($window.Length - $nLen); $i++) {
                $ok = $true
                for ($j = 0; $j -lt $nLen; $j++) {
                    if ($window[$i + $j] -ne $needleBytes[$j]) { $ok = $false; break }
                }
                if ($ok) { return $true }
            }
            if ($window.Length -ge ($nLen - 1)) {
                $overlapLen = $nLen - 1
                [Array]::Copy($window, $window.Length - $overlapLen, $overlap, 0, $overlapLen)
            } else {
                $overlapLen = $window.Length
                [Array]::Copy($window, 0, $overlap, 0, $overlapLen)
            }
        }
        return $false
    } finally {
        $fs.Dispose()
    }
}

Write-Host "Scan: $Root"
$pbos = Get-ChildItem -LiteralPath $Root -Recurse -Filter "*.pbo" -ErrorAction SilentlyContinue
Write-Host ("PBO count: {0}" -f $pbos.Count)
$hits = @()
$i = 0
foreach ($p in $pbos) {
    $i++
    if (($i % 50) -eq 0) { Write-Host ("... {0}/{1}" -f $i, $pbos.Count) }
    foreach ($n in $Needles) {
        try {
            if (Test-FileContainsAscii -Path $p.FullName -Needle $n) {
                $line = "HIT`t$n`t$($p.FullName)"
                Write-Host $line
                $hits += [pscustomobject]@{ Needle = $n; Path = $p.FullName; Mod = $p.Directory.Parent.Name }
            }
        } catch {
            Write-Host ("ERR`t$($p.FullName)`t$($_.Exception.Message)")
        }
    }
}

Write-Host "==== SUMMARY ===="
$hits | Format-Table -AutoSize
$out = Join-Path (Split-Path $PSScriptRoot -Parent) "mod\_tmp_mrh_probe\scan_rpt_hits.csv"
New-Item -ItemType Directory -Force -Path (Split-Path $out) | Out-Null
$hits | Export-Csv -NoTypeInformation -Path $out -Encoding UTF8
Write-Host "CSV: $out"
