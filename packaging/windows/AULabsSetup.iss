; Inno Setup script — produces Setup-AULabs.exe (optional wrapper around AULabsSetup.exe)
; Build with:  iscc packaging\windows\AULabsSetup.iss
; Requires: Inno Setup 6+ and release\windows\*.exe from build_windows.bat

#define MyAppName "AU Labs IT Management"
#define MyAppVersion "1.0.0"
#define MyAppPublisher "AU Labs"
#define MyAppURL "http://127.0.0.1:8787"
#define MyAppExeName "AULabsSetup.exe"

[Setup]
AppId={{8F3C2A91-AULABS-4E11-9C2B-ITMGMT000001}
AppName={#MyAppName}
AppVersion={#MyAppVersion}
AppPublisher={#MyAppPublisher}
AppPublisherURL={#MyAppURL}
DefaultDirName={autopf}\AU Labs IT Management
DefaultGroupName={#MyAppName}
DisableProgramGroupPage=yes
OutputDir=..\..\release\windows
OutputBaseFilename=Setup-AULabs
Compression=lzma
SolidCompression=yes
WizardStyle=modern
PrivilegesRequired=admin
ArchitecturesInstallIn64BitMode=x64compatible
SetupIconFile=
UninstallDisplayName={#MyAppName}

[Languages]
Name: "english"; MessagesFile: "compiler:Default.isl"

[Tasks]
Name: "desktopicon"; Description: "Create a desktop shortcut"; GroupDescription: "Additional icons:"
Name: "installserver"; Description: "Install AU Labs Server (web panel)"; GroupDescription: "Components:"; Flags: checkedonce
Name: "installagent"; Description: "Install AU Labs Agent"; GroupDescription: "Components:"; Flags: checkedonce

[Files]
Source: "..\..\release\windows\AULabsServer.exe"; DestDir: "{app}\bin"; Flags: ignoreversion
Source: "..\..\release\windows\AULabsAgent.exe"; DestDir: "{app}\bin"; Flags: ignoreversion
Source: "..\..\release\windows\AULabsSetup.exe"; DestDir: "{app}"; Flags: ignoreversion
Source: "..\..\release\windows\README.txt"; DestDir: "{app}"; Flags: ignoreversion isreadme

[Icons]
Name: "{group}\AU Labs Server"; Filename: "{app}\bin\AULabsServer.exe"
Name: "{group}\AU Labs Agent"; Filename: "{app}\bin\AULabsAgent.exe"
Name: "{group}\Uninstall {#MyAppName}"; Filename: "{uninstallexe}"
Name: "{autodesktop}\AU Labs Server"; Filename: "{app}\bin\AULabsServer.exe"; Tasks: desktopicon
Name: "{autodesktop}\AU Labs Agent"; Filename: "{app}\bin\AULabsAgent.exe"; Tasks: desktopicon

[Run]
Filename: "{app}\bin\AULabsServer.exe"; Description: "Launch AU Labs Server"; Flags: nowait postinstall skipifsilent; Tasks: installserver
Filename: "{app}\bin\AULabsAgent.exe"; Description: "Launch AU Labs Agent"; Flags: nowait postinstall skipifsilent; Tasks: installagent
