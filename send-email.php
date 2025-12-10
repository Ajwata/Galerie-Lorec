<?php
// Настройки - ЗАМЕНИ НА ПОЧТУ КЛИЕНТА
$to_email = "shonraprince@gmail.com"; // ← Сюда вставь почту клиента

// Заголовки для предотвращения спама
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');

// Проверка метода запроса
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Получаем данные из формы
$name = isset($_POST['name']) ? strip_tags($_POST['name']) : '';
$phone = isset($_POST['phone']) ? strip_tags($_POST['phone']) : '';
$email = isset($_POST['email']) ? strip_tags($_POST['email']) : '';
$category = isset($_POST['category']) ? strip_tags($_POST['category']) : '';
$message = isset($_POST['message']) ? strip_tags($_POST['message']) : '';

// Валидация
if (empty($name) || empty($phone)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Name und Telefon sind erforderlich']);
    exit;
}

// Формируем тему письма
$subject = "Neue Bewertungsanfrage von " . $name;

// Формируем тело письма
$email_body = "=== NEUE BEWERTUNGSANFRAGE ===\n\n";
$email_body .= "Name: " . $name . "\n";
$email_body .= "Telefon: " . $phone . "\n";
$email_body .= "E-Mail: " . ($email ?: 'Nicht angegeben') . "\n";
$email_body .= "Kategorie: " . $category . "\n";
$email_body .= "Nachricht:\n" . ($message ?: 'Keine Beschreibung') . "\n\n";

// Обработка файлов
$attachments = [];
if (isset($_FILES['files']) && is_array($_FILES['files']['tmp_name'])) {
    $file_count = count($_FILES['files']['tmp_name']);
    $email_body .= "Dateien angehängt: " . $file_count . "\n";
    
    for ($i = 0; $i < $file_count; $i++) {
        if ($_FILES['files']['error'][$i] === UPLOAD_ERR_OK) {
            $attachments[] = [
                'path' => $_FILES['files']['tmp_name'][$i],
                'name' => $_FILES['files']['name'][$i],
                'type' => $_FILES['files']['type'][$i]
            ];
            $email_body .= "- " . $_FILES['files']['name'][$i] . "\n";
        }
    }
}

$email_body .= "\n---\nОтправлено с сайта Galerie Lorec";

// Заголовки письма
$headers = "From: " . ($email ?: "noreply@galerie-lorec.at") . "\r\n";
$headers .= "Reply-To: " . ($email ?: $to_email) . "\r\n";
$headers .= "X-Mailer: PHP/" . phpversion();

// Если есть вложения, используем MIME
if (!empty($attachments)) {
    $boundary = md5(time());
    $headers .= "\r\nMIME-Version: 1.0\r\n";
    $headers .= "Content-Type: multipart/mixed; boundary=\"{$boundary}\"";
    
    $email_message = "--{$boundary}\r\n";
    $email_message .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $email_message .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $email_message .= $email_body . "\r\n";
    
    // Добавляем вложения
    foreach ($attachments as $file) {
        $file_content = chunk_split(base64_encode(file_get_contents($file['path'])));
        $email_message .= "--{$boundary}\r\n";
        $email_message .= "Content-Type: {$file['type']}; name=\"{$file['name']}\"\r\n";
        $email_message .= "Content-Transfer-Encoding: base64\r\n";
        $email_message .= "Content-Disposition: attachment; filename=\"{$file['name']}\"\r\n\r\n";
        $email_message .= $file_content . "\r\n";
    }
    
    $email_message .= "--{$boundary}--";
} else {
    $email_message = $email_body;
}

// Отправляем письмо
$success = mail($to_email, $subject, $email_message, $headers);

// Возвращаем результат
if ($success) {
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Email erfolgreich gesendet'
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Fehler beim Senden der E-Mail'
    ]);
}
?>
