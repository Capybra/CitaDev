Set WshShell = CreateObject("WScript.Shell")
Set fso = CreateObject("Scripting.FileSystemObject")

' Получаем путь к папке проекта
strPath = fso.GetParentFolderName(WScript.ScriptFullName)

' Путь к твоему портативному PHP
phpExe = """" & strPath & "\php\php.exe"""

' Запуск API сервера на порту 2712
' Мы используем относительный путь для папки php
WshShell.Run "cmd /c cd /d """ & strPath & """ && " & phpExe & " -S 0.0.0.0:2712 index.php", 0, False

' Запуск Worker Core
WshShell.Run "cmd /c cd /d """ & strPath & """ && " & phpExe & " worker.php", 0, False