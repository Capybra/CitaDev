<?php

// Настройки
$PASSWORD = 'admin1235'; // Пароль для входа
$DATA_FILE = 'data.json';
$TYPES_FILE = 'tile_types.json';

header('Content-Type: application/json');

// ------------------------------------------------------------------
// --- УТИЛИТЫ ДЛЯ РАБОТЫ С ФАЙЛАМИ ---
// ------------------------------------------------------------------

/**
 * Читает данные из JSON-файла.
 * @param string $filename Имя файла.
 * @return array Декодированные данные или пустой массив.
 */
function readJsonFile($filename) {
    if (!file_exists($filename)) {
        // Создаем файл, если его нет
        writeJsonFile($filename, []);
        return [];
    }
    $content = file_get_contents($filename);
    // Декодируем, возвращаем массив, или пустой массив, если декодирование не удалось
    return json_decode($content, true) ?: [];
}

/**
 * Записывает данные в JSON-файл.
 * @param string $filename Имя файла.
 * @param array $data Данные для записи.
 * @return bool|int Количество записанных байтов или false при ошибке.
 */
function writeJsonFile($filename, $data) {
    // Используем JSON_PRETTY_PRINT и JSON_UNESCAPED_UNICODE для читабельности и кириллицы
    $content = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    // Используем LOCK_EX для предотвращения конфликтов записи
    return file_put_contents($filename, $content, LOCK_EX);
}

/**
 * Ищет раздел по ID и возвращает ссылку на него.
 * @param array &$data Ссылка на массив данных.
 * @param int $id ID раздела.
 * @return array|null Ссылка на найденный раздел или null.
 */
function &findSectionById(array &$data, $id) {
    $found = null;
    if (isset($data['sections'])) {
        foreach ($data['sections'] as &$section) {
            if (isset($section['id']) && (int)$section['id'] === (int)$id) {
                return $section;
            }
        }
    }
    return $found;
}

/**
 * Ищет подраздел по ID и возвращает ссылку на него внутри родительского раздела.
 * ВНИМАНИЕ: Эта функция ищет первое совпадение по ID, что может привести к коллизии
 * если ID подраздела не уникален глобально (как в текущей структуре).
 * @param array &$data Ссылка на массив данных.
 * @param int $id ID подраздела.
 * @return array|null Ссылка на найденный подраздел или null.
 */
function &findSubsectionById(array &$data, $id) {
    $found = null;
    if (isset($data['sections'])) {
        foreach ($data['sections'] as &$section) {
            if (isset($section['subsections'])) {
                foreach ($section['subsections'] as &$subsection) {
                    if (isset($subsection['id']) && (int)$subsection['id'] === (int)$id) {
                        return $subsection;
                    }
                }
            }
        }
    }
    return $found;
}

// ------------------------------------------------------------------
// --- АУТЕНТИФИКАЦИЯ И ПРОВЕРКА ЗАПРОСА ---
// ------------------------------------------------------------------

$input = file_get_contents('php://input');
$requestData = json_decode($input, true) ?: [];

/**
 * Проверяет аутентификацию пользователя.
 * @param array $data Данные запроса.
 * @return bool
 */
function isAuthenticated($data, $expectedPassword) {
    // В реальном приложении здесь должна быть сессия или токен
    return isset($data['auth']) && $data['auth'] === $expectedPassword;
}

// Проверка аутентификации для всех действий, кроме входа
if (isset($_GET['action']) && $_GET['action'] !== 'login' && $_GET['action'] !== 'get_data' && $_GET['action'] !== 'get_tile_types') {
    if (!isAuthenticated($requestData, $PASSWORD)) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Несанкционированный доступ.']);
        exit;
    }
}

// ------------------------------------------------------------------
// --- ОСНОВНАЯ ЛОГИКА ОБРАБОТКИ ДЕЙСТВИЙ ---
// ------------------------------------------------------------------

if (isset($_GET['action'])) {
    $action = $_GET['action'];

    switch ($action) {
        
        case 'login':
            $passwordAttempt = $requestData['password'] ?? '';
            if ($passwordAttempt === $PASSWORD) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Неверный пароль.']);
            }
            break;

        case 'get_data':
            $data = readJsonFile($DATA_FILE);
            // Если секции пусты, инициализируем их
            if (!isset($data['sections'])) {
                $data['sections'] = [];
            }
            echo json_encode($data, JSON_UNESCAPED_UNICODE);
            break;

        case 'get_tile_types':
            $tileTypes = readJsonFile($TYPES_FILE);
            echo json_encode($tileTypes, JSON_UNESCAPED_UNICODE);
            break;
            
        // --------------------------------------------------
        // --- CRUD ТИПОВ ПЛИТОК ---
        // --------------------------------------------------

        case 'save_tile_type':
            $newTypeData = $requestData['tile_type'] ?? null;
            if (!$newTypeData || !isset($newTypeData['type'])) {
                echo json_encode(['success' => false, 'error' => 'Отсутствуют данные типа плитки.']);
                break;
            }

            $tileTypes = readJsonFile($TYPES_FILE);
            $typeKey = $newTypeData['type'];
            $found = false;

            foreach ($tileTypes as $i => $type) {
                if ($type['type'] === $typeKey) {
                    $tileTypes[$i] = $newTypeData; // Обновление существующего
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                $tileTypes[] = $newTypeData; // Добавление нового
            }

            if (writeJsonFile($TYPES_FILE, $tileTypes) !== false) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Не удалось сохранить тип плитки.']);
            }
            break;

        case 'delete_tile_type':
            $typeToDelete = $requestData['type'] ?? null;
            
            if (!$typeToDelete) {
                echo json_encode(['success' => false, 'error' => 'Не указан ключ типа для удаления.']);
                break;
            }

            $tileTypes = readJsonFile($TYPES_FILE);
            
            // Фильтрация: оставляем только те элементы, ключ которых НЕ совпадает с удаляемым.
            $newTileTypes = array_filter($tileTypes, function($type) use ($typeToDelete) {
                return $type['type'] !== $typeToDelete;
            });
            
            // Re-index array (важно, чтобы в JSON не было "дырок")
            $newTileTypes = array_values($newTileTypes);

            if (writeJsonFile($TYPES_FILE, $newTileTypes) !== false) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Не удалось записать в файл tile_types.json. Проверьте права доступа.']);
            }
            break;
            
        // --------------------------------------------------
        // --- CRUD СТРУКТУРЫ (Секции, Подсекции, Плитки) ---
        // --------------------------------------------------
        
        case 'save_section':
            $sectionData = $requestData['data'] ?? null;
            if (!$sectionData || !isset($sectionData['name']) || !isset($sectionData['icon'])) {
                echo json_encode(['success' => false, 'error' => 'Отсутствуют данные раздела (имя или иконка).']);
                break;
            }

            $data = readJsonFile($DATA_FILE);
            if (!isset($data['sections'])) {
                $data['sections'] = [];
            }
            
            $isNew = (int)$sectionData['id'] === 0;
            
            if ($isNew) {
                // Генерация нового ID: максимальный ID + 1
                $newId = 1;
                if (!empty($data['sections'])) {
                    $ids = array_column($data['sections'], 'id');
                    if (!empty($ids)) {
                        $newId = max($ids) + 1;
                    }
                }
                
                $newSection = [
                    'id' => $newId,
                    'name' => $sectionData['name'],
                    'icon' => $sectionData['icon'],
                    'subsections' => [] // Инициализация пустым массивом
                ];
                $data['sections'][] = $newSection;
                
            } else {
                // Обновление существующего
                $section = &findSectionById($data, $sectionData['id']);
                
                if ($section) {
                    $section['name'] = $sectionData['name'];
                    $section['icon'] = $sectionData['icon'];
                } else {
                    echo json_encode(['success' => false, 'error' => 'Раздел с указанным ID не найден.']);
                    break;
                }
            }

            if (writeJsonFile($DATA_FILE, $data) !== false) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Не удалось сохранить раздел в data.json.']);
            }
            break;
            
        case 'save_subsection':
            $sectionId = $requestData['section_id'] ?? null;
            $subsectionData = $requestData['data'] ?? null;

            if (!$sectionId || !$subsectionData || !isset($subsectionData['name'])) {
                echo json_encode(['success' => false, 'error' => 'Отсутствуют данные подраздела или ID родительского раздела.']);
                break;
            }

            $data = readJsonFile($DATA_FILE);
            
            // 1. Найти родительский раздел по ссылке
            $section = &findSectionById($data, $sectionId);

            if (!$section) {
                echo json_encode(['success' => false, 'error' => 'Родительский раздел не найден.']);
                break;
            }
            
            if (!isset($section['subsections'])) {
                $section['subsections'] = [];
            }

            $isNew = (int)($subsectionData['id'] ?? 0) === 0;

            if ($isNew) {
                // Генерация нового ID: максимальный ID + 1 (среди подразделов этого раздела)
                $newId = 1;
                if (!empty($section['subsections'])) {
                    $ids = array_column($section['subsections'], 'id');
                    if (!empty($ids)) {
                        $newId = max($ids) + 1;
                    }
                }
                
                $newSubsection = [
                    'id' => $newId,
                    'name' => $subsectionData['name'],
                    'tiles' => [] // Инициализация пустым массивом
                ];
                $section['subsections'][] = $newSubsection;
                
            } else {
                // Обновление существующего
                $subsectionFound = false;
                foreach ($section['subsections'] as &$subsection) {
                    if ((int)$subsection['id'] === (int)$subsectionData['id']) {
                        $subsection['name'] = $subsectionData['name'];
                        $subsectionFound = true;
                        break;
                    }
                }
                if (!$subsectionFound) {
                    echo json_encode(['success' => false, 'error' => 'Подраздел с указанным ID не найден.']);
                    break;
                }
            }
            
            // 2. Сохранить обновленные данные
            if (writeJsonFile($DATA_FILE, $data) !== false) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Не удалось сохранить подраздел в data.json.']);
            }
            break;
            
        case 'save_tile':
            $subsectionId = $requestData['subsection_id'] ?? null;
            $tileData = $requestData['tile'] ?? null;

            if (!$subsectionId || !$tileData || !isset($tileData['title']) || !isset($tileData['type'])) {
                echo json_encode(['success' => false, 'error' => 'Отсутствуют данные плитки (заголовок, тип) или ID родительского подраздела.']);
                break;
            }
            
            $data = readJsonFile($DATA_FILE);
            
            // 1. Найти родительский подраздел по ссылке
            $subsection = &findSubsectionById($data, $subsectionId);

            if (!$subsection) {
                echo json_encode(['success' => false, 'error' => 'Родительский подраздел не найден.']);
                break;
            }
            
            if (!isset($subsection['tiles'])) {
                $subsection['tiles'] = [];
            }

            $isNew = (int)($tileData['id'] ?? 0) === 0;
            
            // Очистка и нормализация данных плитки
            // stdClass используется, чтобы data была представлена как JSON-объект {}
            $newTile = [
                'title' => $tileData['title'],
                'type' => $tileData['type'],
                'width' => (int)($tileData['width'] ?? 1), // По умолчанию 1
                'status' => $tileData['status'] ?? null,
                'data' => $tileData['data'] ?? new stdClass(),
            ];


            if ($isNew) {
                // Генерация нового ID: максимальный ID + 1 (среди плиток этого подраздела)
                $newId = 1;
                if (!empty($subsection['tiles'])) {
                    $ids = array_column($subsection['tiles'], 'id');
                    if (!empty($ids)) {
                        $newId = max($ids) + 1;
                    }
                }
                
                $newTile['id'] = $newId;
                $subsection['tiles'][] = $newTile;
                
            } else {
                // Обновление существующей плитки
                $tileFound = false;
                foreach ($subsection['tiles'] as &$tile) {
                    if ((int)$tile['id'] === (int)$tileData['id']) {
                        // Обновляем только разрешенные поля
                        $tile['title'] = $newTile['title'];
                        $tile['type'] = $newTile['type'];
                        $tile['width'] = $newTile['width'];
                        $tile['status'] = $newTile['status'];
                        $tile['data'] = $newTile['data'];
                        $tileFound = true;
                        break;
                    }
                }
                if (!$tileFound) {
                    echo json_encode(['success' => false, 'error' => 'Плитка с указанным ID не найдена.']);
                    break;
                }
            }
            
            // 2. Сохранить обновленные данные
            if (writeJsonFile($DATA_FILE, $data) !== false) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Не удалось сохранить плитку в data.json.']);
            }
            break;

        case 'delete_section':
            $sectionId = $requestData['id'] ?? null;
            
            if (!$sectionId) {
                echo json_encode(['success' => false, 'error' => 'Не указан ID раздела для удаления.']);
                break;
            }

            $data = readJsonFile($DATA_FILE);
            
            // Фильтруем массив: оставляем только те разделы, ID которых НЕ совпадает с удаляемым
            $initialCount = count($data['sections'] ?? []);
            $data['sections'] = array_values(array_filter($data['sections'] ?? [], function($section) use ($sectionId) {
                return (int)($section['id'] ?? 0) !== (int)$sectionId;
            }));
            $deleted = count($data['sections']) < $initialCount;

            if ($deleted && writeJsonFile($DATA_FILE, $data) !== false) {
                echo json_encode(['success' => true]);
            } elseif (!$deleted) {
                echo json_encode(['success' => false, 'error' => 'Раздел с указанным ID не найден.']);
            } else {
                echo json_encode(['success' => false, 'error' => 'Не удалось удалить раздел.']);
            }
            break;
            
        case 'delete_subsection': // ИСПРАВЛЕНО: Теперь требуется ID родительской секции (section_id)
            $subsectionId = $requestData['id'] ?? null;
            $sectionId = $requestData['section_id'] ?? null; // <-- Добавлено получение section_id
            
            if (!$subsectionId || !$sectionId) { // <-- Проверка section_id
                echo json_encode(['success' => false, 'error' => 'Не указан ID подраздела или ID родительской секции для удаления.']);
                break;
            }

            $data = readJsonFile($DATA_FILE);
            $deleted = false;

            // Находим родительскую секцию по уникальному sectionId
            $section = &findSectionById($data, $sectionId);

            if ($section && isset($section['subsections'])) {
                $initialCount = count($section['subsections']);
                // Фильтруем подразделы: оставляем только те, ID которых НЕ совпадает с удаляемым
                $section['subsections'] = array_values(array_filter($section['subsections'], function($subsection) use ($subsectionId) {
                    return (int)($subsection['id'] ?? 0) !== (int)$subsectionId;
                }));
                
                if (count($section['subsections']) < $initialCount) {
                    $deleted = true;
                }
            }
            // Здесь нет необходимости в unset($section), так как findSectionById
            // возвращает ссылку только на один раздел.
            
            if ($deleted && writeJsonFile($DATA_FILE, $data) !== false) {
                echo json_encode(['success' => true]);
            } elseif (!$deleted) {
                 echo json_encode(['success' => false, 'error' => 'Подраздел с указанным ID не найден в указанной секции.']);
            } else {
                 echo json_encode(['success' => false, 'error' => 'Не удалось удалить подраздел.']);
            }
            break;
            
        case 'delete_tile': // ИСПРАВЛЕНО: Теперь требуется ID родительского подраздела (subsection_id)
            $tileId = $requestData['id'] ?? null;
            $subsectionId = $requestData['subsection_id'] ?? null; // <-- Добавлено получение subsection_id
            
            if (!$tileId || !$subsectionId) { // <-- Проверка subsection_id
                echo json_encode(['success' => false, 'error' => 'Не указан ID плитки или ID родительского подраздела для удаления.']);
                break;
            }

            $data = readJsonFile($DATA_FILE);
            $deleted = false;

            // Находим родительский подраздел по уникальному subsectionId
            $subsection = &findSubsectionById($data, $subsectionId);

            if ($subsection && isset($subsection['tiles'])) {
                $initialCount = count($subsection['tiles']);
                // Фильтруем плитки: оставляем только те, ID которых НЕ совпадает с удаляемым
                $subsection['tiles'] = array_values(array_filter($subsection['tiles'], function($tile) use ($tileId) {
                    return (int)($tile['id'] ?? 0) !== (int)$tileId;
                }));
                
                if (count($subsection['tiles']) < $initialCount) {
                    $deleted = true;
                }
            }
            
            if ($deleted && writeJsonFile($DATA_FILE, $data) !== false) {
                echo json_encode(['success' => true]);
            } elseif (!$deleted) {
                 echo json_encode(['success' => false, 'error' => 'Плитка с указанным ID не найдена в указанном подразделе.']);
            } else {
                 echo json_encode(['success' => false, 'error' => 'Не удалось удалить плитку.']);
            }
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Неизвестное действие.']);
            break;
    }
} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Действие не указано.']);
}