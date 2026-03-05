; ============================================================
; MoodleKelas - Inno Setup Script
; ============================================================

#define AppName      "E-UJIAN SMKN 9 Garut"
#define AppVersion   "1.0.0"
#define AppPublisher "Suhendar Aryadi"
#define AppURL       "https://smkn9garut.sch.id"

[Setup]
AppId={{A1B2C3D4-E5F6-7890-ABCD-EF1234567890}
AppName={#AppName}
AppVersion={#AppVersion}
AppPublisher={#AppPublisher}
AppPublisherURL={#AppURL}
DefaultDirName=C:\MoodleKelas
DefaultGroupName={#AppName}
AllowNoIcons=yes
OutputDir=..\dist
OutputBaseFilename=EUJIAN-Setup
Compression=lzma2
SolidCompression=yes
WizardStyle=modern
PrivilegesRequired=admin
UninstallDisplayIcon={app}\MoodleSyncApp\MoodleSyncApp.exe
MinVersion=10.0

[Languages]
Name: "english"; MessagesFile: "compiler:Default.isl"

[Tasks]
Name: "desktopicon"; Description: "Buat shortcut di Desktop"; GroupDescription: "Shortcut:"

[Files]
Source: "..\publish\windows\*"; DestDir: "{app}\MoodleSyncApp"; Flags: ignoreversion recursesubdirs createallsubdirs
Source: "components\laragon\*"; DestDir: "{app}\laragon"; Flags: ignoreversion recursesubdirs createallsubdirs
Source: "components\moodle\*"; DestDir: "{app}\laragon\www\moodle"; Flags: ignoreversion recursesubdirs createallsubdirs
Source: "scripts\*"; DestDir: "{app}\installer\scripts"; Flags: ignoreversion recursesubdirs

[Icons]
Name: "{group}\E-UJIAN (Browser)"; Filename: "{app}\laragon\laragon.exe"
Name: "{group}\E-UJIAN Sync App"; Filename: "{app}\MoodleSyncApp\MoodleSyncApp.exe"
Name: "{group}\Uninstall {#AppName}"; Filename: "{uninstallexe}"
Name: "{commondesktop}\E-UJIAN SMKN 9 Garut"; Filename: "{app}\laragon\laragon.exe"; Tasks: desktopicon
Name: "{commondesktop}\E-UJIAN Sync App"; Filename: "{app}\MoodleSyncApp\MoodleSyncApp.exe"; Tasks: desktopicon

[Registry]
Root: HKLM; Subkey: "SOFTWARE\Microsoft\Windows\CurrentVersion\Run"; ValueType: string; ValueName: "EUJIAN-Laragon"; ValueData: """{app}\laragon\laragon.exe"" --autostart"; Flags: uninsdeletevalue

[Run]
Filename: "{app}\MoodleSyncApp\MoodleSyncApp.exe"; Description: "Jalankan Moodle Sync App"; Flags: postinstall nowait skipifsilent

[UninstallRun]
Filename: "powershell.exe"; Parameters: "-Command ""Get-Process laragon -ErrorAction SilentlyContinue | Stop-Process -Force"""; RunOnceId: "StopLaragon"

[Code]
var
  RoomPage: TInputQueryWizardPage;
  ServerPage: TInputQueryWizardPage;

procedure InitializeWizard;
begin
  RoomPage := CreateInputQueryPage(wpSelectDir,
    'Identitas Ruangan',
    'Masukkan informasi ruang kelas ini.',
    'Informasi ini digunakan untuk identifikasi saat sinkronisasi data ujian.');
  RoomPage.Add('Room ID (contoh: ROOM_01, LAB_TKJ_1):', False);
  RoomPage.Add('Nama Ruangan (contoh: Lab Komputer 1):', False);
  RoomPage.Values[0] := 'ROOM_01';
  RoomPage.Values[1] := 'Ruang Lab Komputer 1';

  ServerPage := CreateInputQueryPage(RoomPage.ID,
    'Konfigurasi Server Utama',
    'Masukkan URL server Moodle utama (server pusat sekolah).',
    'Pengaturan ini bisa diubah nanti di aplikasi Moodle Sync App.');
  ServerPage.Add('URL Server Utama (contoh: http://192.168.1.1/moodle):', False);
  ServerPage.Add('Port Moodle Lokal (default: 8080):', False);
  ServerPage.Values[0] := 'http://192.168.1.1/moodle';
  ServerPage.Values[1] := '8080';
end;

function NextButtonClick(CurPageID: Integer): Boolean;
begin
  Result := True;
  if CurPageID = RoomPage.ID then
  begin
    if Trim(RoomPage.Values[0]) = '' then
    begin
      MsgBox('Room ID tidak boleh kosong!', mbError, MB_OK);
      Result := False;
    end;
  end;
  if CurPageID = ServerPage.ID then
  begin
    if Trim(ServerPage.Values[0]) = '' then
    begin
      MsgBox('URL Server Utama tidak boleh kosong!', mbError, MB_OK);
      Result := False;
    end;
  end;
end;

procedure CurStepChanged(CurStep: TSetupStep);
var
  RoomId, RoomName, MasterUrl, LocalPort: String;
  ResultCode: Integer;
  PsArgs: String;
begin
  if CurStep = ssPostInstall then
  begin
    RoomId    := RoomPage.Values[0];
    RoomName  := RoomPage.Values[1];
    MasterUrl := ServerPage.Values[0];
    LocalPort := ServerPage.Values[1];
    if LocalPort = '' then
      LocalPort := '8080';

    PsArgs := '-ExecutionPolicy Bypass -NonInteractive -File "' + ExpandConstant('{app}') + '\installer\scripts\setup-main.ps1" -InstallDir "' + ExpandConstant('{app}') + '" -RoomId "' + RoomId + '" -RoomName "' + RoomName + '" -MasterUrl "' + MasterUrl + '" -LocalPort "' + LocalPort + '"';

    Exec('powershell.exe', PsArgs, '', SW_SHOW, ewWaitUntilTerminated, ResultCode);

    if ResultCode <> 0 then
      MsgBox('Setup mengalami error (code: ' + IntToStr(ResultCode) + '). Cek file: ' + ExpandConstant('{app}') + '\setup.log', mbInformation, MB_OK);
  end;
end;
