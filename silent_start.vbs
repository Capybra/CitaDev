Set WshShell = CreateObject("WScript.Shell")
Set fso = CreateObject("Scripting.FileSystemObject")

' Получаем путь к папке, где лежит этот VBS файл
strPath = fso.GetParentFolderName(WScript.ScriptFullName)

' Путь к портативному PHP
phpExe = """" & strPath & "\php\php.exe"""

' Сначала принудительно завершаем старые процессы, если они зависли
WshShell.Run "taskkill /F /IM php.exe", 0, True

' Запуск API сервера на 0.0.0.0 (чтобы принимал внешние подключения)
WshShell.Run "cmd /c cd /d """ & strPath & """ && " & phpExe & " -S 0.0.0.0:2712 index.php", 0, False

' Запуск Воркера
WshShell.Run "cmd /c cd /d """ & strPath & """ && " & phpExe & " worker.php", 0, False