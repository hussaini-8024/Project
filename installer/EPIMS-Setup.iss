; Inno Setup script for Enterprise Procurement & Inventory Management System
; Build the desktop app first:
;   dotnet publish src/EnterpriseProcurement.Desktop/EnterpriseProcurement.Desktop.csproj -c Release -r win-x64 --self-contained true -o publish/win-x64
; Then compile this script with Inno Setup to produce Setup.exe

#define MyAppName "EPIMS"
#define MyAppFullName "Enterprise Procurement & Inventory Management System"
#define MyAppVersion "1.0.0"
#define MyAppPublisher "EPIMS"
#define MyAppExeName "EPIMS.exe"
#define PublishDir "..\publish\win-x64"

[Setup]
AppId={{8F3C2A91-4E6B-4D7A-9C1E-2B8F0A7D5E33}
AppName={#MyAppFullName}
AppVersion={#MyAppVersion}
AppPublisher={#MyAppPublisher}
DefaultDirName={autopf}\{#MyAppName}
DefaultGroupName={#MyAppName}
DisableProgramGroupPage=no
OutputDir=..\dist
OutputBaseFilename=EPIMS-Setup
Compression=lzma2
SolidCompression=yes
WizardStyle=modern
PrivilegesRequired=admin
ArchitecturesInstallIn64BitMode=x64compatible
ArchitecturesAllowed=x64compatible
SetupIconFile=
UninstallDisplayIcon={app}\{#MyAppExeName}
LicenseFile=
InfoBeforeFile=
VersionInfoVersion={#MyAppVersion}
MinVersion=6.1sp1

[Languages]
Name: "english"; MessagesFile: "compiler:Default.isl"

[Tasks]
Name: "desktopicon"; Description: "Create a &desktop shortcut"; GroupDescription: "Additional icons:"; Flags: checkedonce
Name: "quicklaunchicon"; Description: "Create a &Quick Launch shortcut"; GroupDescription: "Additional icons:"; Flags: unchecked; OnlyBelowVersion: 6.1

[Files]
Source: "{#PublishDir}\*"; DestDir: "{app}"; Flags: ignoreversion recursesubdirs createallsubdirs

[Icons]
Name: "{group}\{#MyAppName}"; Filename: "{app}\{#MyAppExeName}"
Name: "{group}\Backup Utility"; Filename: "{app}\{#MyAppExeName}"; Parameters: "--backup"
Name: "{group}\{cm:UninstallProgram,{#MyAppName}}"; Filename: "{uninstallexe}"
Name: "{autodesktop}\{#MyAppName}"; Filename: "{app}\{#MyAppExeName}"; Tasks: desktopicon

[Run]
Filename: "{app}\{#MyAppExeName}"; Description: "Launch {#MyAppName}"; Flags: nowait postinstall skipifsilent

[Dirs]
Name: "{app}\Backups"; Permissions: users-modify
Name: "{app}\Exports"; Permissions: users-modify
Name: "{app}\Attachments"; Permissions: users-modify

[Code]
function InitializeSetup(): Boolean;
begin
  Result := True;
end;
