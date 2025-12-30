' Используем двойные кавычки ("") внутри команды для корректной передачи
CreateObject("WScript.Shell").Run "C:\php\php.exe -S 0.0.0.0:8000 -t ""C:\php\server\""", 0, false