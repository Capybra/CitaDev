import webbrowser
import os
import sys

def open_dashboard():
    # Путь к файлу index.html в текущей папке скрипта
    current_dir = os.path.dirname(os.path.abspath(__file__))
    file_path = os.path.join(current_dir, "index.html")
    
    if not os.path.exists(file_path):
        print(f"Ошибка: Файл {file_path} не найден!")
        return

    # Формируем URL для открытия локального файла
    url = f"file:///{file_path.replace(os.sep, '/')}"
    
    print(f"Запуск клиента CitaDev...")
    print(f"Адрес: {url}")
    
    webbrowser.open(url)

if __name__ == "__main__":
    open_dashboard()