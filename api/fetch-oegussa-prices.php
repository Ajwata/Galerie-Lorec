<?php
// Прокси для получения цен на золото с сайта Ögussa
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// URL страницы с ценами Ögussa
$url = 'https://www.oegussa.at/de/charts/tageskurse';

try {
    // Инициализируем cURL
    $ch = curl_init();
    
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
    ]);
    
    $html = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    curl_close($ch);
    
    if ($httpCode !== 200 || !$html) {
        throw new Exception('Failed to fetch page');
    }
    
    // Парсим HTML для извлечения цен на золото
    $price999 = null;
    $price585 = null;
    
    // Ögussa использует таблицу с ценами, ищем паттерны:
    // 1. Ищем "Gold 999" или "999er" и следующее за ним число
    // 2. Ищем EUR/g в той же строке
    
    // Паттерн 1: Поиск строки с "999" и ценой после неё
    if (preg_match('/999.*?EUR\/g.*?([0-9]{2,3}[,\.][0-9]{2})/is', $html, $matches)) {
        $price999 = floatval(str_replace(',', '.', $matches[1]));
    }
    
    // Паттерн 2: Обратный поиск - цена перед "999"
    if (!$price999 && preg_match('/([0-9]{2,3}[,\.][0-9]{2}).*?EUR\/g.*?999/is', $html, $matches)) {
        $price999 = floatval(str_replace(',', '.', $matches[1]));
    }
    
    // Паттерн 3: Feingold (чистое золото)
    if (!$price999 && preg_match('/Feingold.*?([0-9]{2,3}[,\.][0-9]{2})/is', $html, $matches)) {
        $price999 = floatval(str_replace(',', '.', $matches[1]));
    }
    
    // Аналогично для 585
    if (preg_match('/585.*?EUR\/g.*?([0-9]{2,3}[,\.][0-9]{2})/is', $html, $matches)) {
        $price585 = floatval(str_replace(',', '.', $matches[1]));
    }
    
    if (!$price585 && preg_match('/([0-9]{2,3}[,\.][0-9]{2}).*?EUR\/g.*?585/is', $html, $matches)) {
        $price585 = floatval(str_replace(',', '.', $matches[1]));
    }
    
    // Если не нашли 585, вычисляем
    if ($price999 && !$price585) {
        $price585 = $price999 * 0.585;
    }
    
    // Если ничего не нашли, пробуем найти любые цены около EUR/g
    if (!$price999) {
        if (preg_match_all('/([0-9]{2,3}[,\.][0-9]{2}).*?EUR\/g/is', $html, $matches)) {
            // Берём первую найденную цену как 999
            if (isset($matches[1][0])) {
                $price999 = floatval(str_replace(',', '.', $matches[1][0]));
                $price585 = $price999 * 0.585;
            }
        }
    }
    
    // Проверяем, нашли ли цены
    if ($price999 && $price585) {
        echo json_encode([
            'success' => true,
            'prices' => [
                '999' => round($price999, 2),
                '585' => round($price585, 2)
            ],
            'source' => 'oegussa',
            'timestamp' => date('Y-m-d H:i:s'),
            'debug' => 'Parsed from HTML'
        ]);
    } else {
        // Не удалось распарсить, используем fallback API
        echo json_encode([
            'success' => false,
            'debug' => [
                'found_999' => $price999,
                'found_585' => $price585,
                'html_length' => strlen($html),
                'message' => 'Failed to parse Ögussa prices, check patterns'
            ]
        ]);
        useFallbackAPI();
    }
    
} catch (Exception $e) {
    // В случае ошибки используем резервный API
    useFallbackAPI();
}

function useFallbackAPI() {
    // Используем публичный API goldprice.org как резерв
    try {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => 'https://data-asg.goldprice.org/dbXRates/EUR',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        $data = json_decode($response, true);
        
        if ($data && isset($data['items'][0]['xauPrice'])) {
            $eurPerOunce = floatval($data['items'][0]['xauPrice']);
            $price999 = $eurPerOunce / 31.1035; // конвертация в граммы
            $price585 = $price999 * 0.585;
            
            echo json_encode([
                'success' => true,
                'prices' => [
                    '999' => round($price999, 2),
                    '585' => round($price585, 2)
                ],
                'source' => 'goldprice.org (fallback)',
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        } else {
            throw new Exception('Fallback API failed');
        }
    } catch (Exception $e) {
        // Последний резерв - статические цены
        echo json_encode([
            'success' => true,
            'prices' => [
                '999' => 85.50,
                '585' => 50.00
            ],
            'source' => 'static (fallback)',
            'timestamp' => date('Y-m-d H:i:s'),
            'warning' => 'Using static prices, please update manually'
        ]);
    }
}
?>
