#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"
export PATH="$HOME/.dotnet:$PATH"

echo "==> Restore"
dotnet restore EnterpriseProcurement.sln

echo "==> Build Core / Data / Business / Tests"
dotnet build src/EnterpriseProcurement.Business/EnterpriseProcurement.Business.csproj -c Release --nologo
dotnet build tests/EnterpriseProcurement.Tests/EnterpriseProcurement.Tests.csproj -c Release --nologo

echo "==> Test"
dotnet test tests/EnterpriseProcurement.Tests/EnterpriseProcurement.Tests.csproj -c Release --nologo --verbosity minimal

echo "==> Done (WPF Desktop project requires Windows to compile)"
