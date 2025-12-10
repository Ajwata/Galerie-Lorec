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
    // Ищем цены для Gold 999 и Gold 585
    
    $price999 = null;
    $price585 = null;
    
    // Паттерны для поиска цен (могут потребовать корректировки)
    // Ögussa отображает цены в формате: "Gold 999" и цену в EUR
    
    // Ищем строки с ценами на золото
    // Примерный формат: "EUR/g" и числа рядом
    
    // Попытка 1: Поиск через регулярные выражения
    // Паттерн для "999" или "Feingold" и цены
    if (preg_match('/999[^0-9]*([0-9]+[,\.][0-9]{2})/i', $html, $matches)) {
        $price999 = floatval(str_replace(',', '.', $matches[1]));
    }
    
    if (preg_match('/585[^0-9]*([0-9]+[,\.][0-9]{2})/i', $html, $matches)) {
        $price585 = floatval(str_replace(',', '.', $matches[1]));
    }
    
    // Если не нашли 585, можем вычислить: 999 * 0.585
    if ($price999 && !$price585) {
        $price585 = $price999 * 0.585;
    }
    
    // Альтернативный метод: поиск JSON данных на странице
    if (!$price999 || !$price585) {
        // Ищем JavaScript переменные или JSON данные
        if (preg_match('/goldPrice["\']?\s*:\s*["\']?([0-9]+[,\.][0-9]+)/i', $html, $matches)) {
            $price999 = floatval(str_replace(',', '.', $matches[1]));
            $price585 = $price999 * 0.585;
        }
        
        // Еще один паттерн: поиск в data-атрибутах
        if (preg_match('/data-gold-999["\']?\s*=\s*["\']([0-9]+[,\.][0-9]+)/i', $html, $matches)) {
            $price999 = floatval(str_replace(',', '.', $matches[1]));
        }
        
        if (preg_match('/data-gold-585["\']?\s*=\s*["\']([0-9]+[,\.][0-9]+)/i', $html, $matches)) {
            $price585 = floatval(str_replace(',', '.', $matches[1]));
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
