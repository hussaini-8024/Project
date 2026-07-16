# Build and package EPIMS for Windows (run on Windows with .NET 8 SDK + Inno Setup)
$ErrorActionPreference = "Stop"
$root = Split-Path -Parent $PSScriptRoot
Set-Location $root

Write-Host "Restoring..." -ForegroundColor Cyan
dotnet restore EnterpriseProcurement.sln

Write-Host "Testing..." -ForegroundColor Cyan
dotnet test tests/EnterpriseProcurement.Tests/EnterpriseProcurement.Tests.csproj -c Release --nologo

Write-Host "Publishing win-x64..." -ForegroundColor Cyan
dotnet publish src/EnterpriseProcurement.Desktop/EnterpriseProcurement.Desktop.csproj `
  -c Release -r win-x64 --self-contained true `
  -p:PublishSingleFile=false `
  -o publish/win-x64

Write-Host "Publishing win-x86..." -ForegroundColor Cyan
dotnet publish src/EnterpriseProcurement.Desktop/EnterpriseProcurement.Desktop.csproj `
  -c Release -r win-x86 --self-contained true `
  -o publish/win-x86

$iscc = @(
  "${env:ProgramFiles(x86)}\Inno Setup 6\ISCC.exe",
  "${env:ProgramFiles}\Inno Setup 6\ISCC.exe"
) | Where-Object { Test-Path $_ } | Select-Object -First 1

if ($iscc) {
  Write-Host "Building Setup.exe..." -ForegroundColor Cyan
  New-Item -ItemType Directory -Force -Path dist | Out-Null
  & $iscc "installer\EPIMS-Setup.iss"
  Write-Host "Installer created in dist\EPIMS-Setup.exe" -ForegroundColor Green
} else {
  Write-Host "Inno Setup not found. Publish output is in publish\win-x64. Install Inno Setup 6 to build Setup.exe." -ForegroundColor Yellow
}
