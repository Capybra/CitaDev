Set WshShell = CreateObject("WScript.Shell")
Set fso = CreateObject("Scripting.FileSystemObject")

' Получаем путь к папке, где лежит сам VBS файл
strPath = fso.GetParentFolderName(WScript.ScriptFullName)

' Запуск API сервера на порту 2712 (скрыто)
' Параметр 0 — скрыть окно, False — не ждать завершения
WshShell.Run "cmd /c cd /d """ & strPath & """ && php -S 0.0.0.0:2712 index.php", 1, False

' Запуск Worker Core (скрыто)
WshShell.Run "cmd /c cd /d """ & strPath & """ && php worker.php", 1, False