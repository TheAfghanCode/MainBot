<?php
// PHP 8.0+ is recommended

// --- فعال‌سازی حالت دیباگ کامل ---
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'bot_errors.log');
error_reporting(E_ALL);

header('Content-Type: text/html; charset=utf-8');

define('CHAT_HISTORY_FILE', 'chat_history.log');
define('MAX_HISTORY_LINES', 20);

try {
    $env = parse_ini_file('.env2');
    if (!$env) {
        throw new Exception("Failed to read .env2 file.");
    }
    $BOT_TOKEN = $env['BOT_TOKEN'];
    $GEMINI_API_KEY = $env['GEMINI_API_KEY'];

    $update = json_decode(file_get_contents('php://input'), true);

    if (isset($update['message']['text'])) {
        $chat_id = $update['message']['chat']['id'];
        $user_message = $update['message']['text'];
        $message_id = $update['message']['message_id'];

        error_log("INFO: New message received from ChatID: $chat_id");

        $history_contents = load_chat_history();
        $final_ai_response = getGeminiResponse($user_message, $GEMINI_API_KEY, $history_contents);

        error_log("INFO: Response from Gemini: $final_ai_response");

        save_chat_history($user_message, $final_ai_response);

        if (trim($final_ai_response) === '/warn') {
            sendMessage($final_ai_response, $chat_id, $BOT_TOKEN, $message_id);
        } else {
            // *** بازگشت به تنظیمات قدرتمند MarkdownV2 ***
            sendMessage($final_ai_response, $chat_id, $BOT_TOKEN, null, 'HTML');
        }
    }

} catch (Throwable $e) {
    error_log("--- FATAL ERROR ---: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
}

function getGeminiResponse(string $prompt, string $apiKey, array $history_contents): string
{
    $template_json = file_get_contents('prompt_template.json');
    if ($template_json === false) {
        throw new Exception("Failed to read prompt_template.json file.");
    }
    $data = json_decode($template_json, true);
    if (!is_array($data) || !isset($data['contents'])) {
        throw new Exception("Invalid JSON in prompt_template.json.");
    }

    $final_contents = $data['contents'];
    if (!empty($history_contents)) {
        $final_contents = array_merge($final_contents, $history_contents);
    }
    $final_contents[] = ['role' => 'user', 'parts' => [['text' => $prompt]]];
    $data['contents'] = $final_contents;

    $apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=' . $apiKey;
    $jsonData = json_encode($data);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
    curl_setopt($ch, CURLOPT_TIMEOUT, 40);
    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        throw new Exception(curl_error($ch));
    }
    curl_close($ch);

    $result = json_decode($response, true);
    if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
        return $result['candidates'][0]['content']['parts'][0]['text'];
    }

    throw new Exception("Invalid Gemini Response: " . $response);
}

function load_chat_history(): array
{
    if (!file_exists(CHAT_HISTORY_FILE))
        return [];
    $lines = file(CHAT_HISTORY_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    return array_map(fn($line) => json_decode($line, true), array_slice($lines, -MAX_HISTORY_LINES));
}

function save_chat_history(string $user_message, string $ai_response): void
{
    if (trim($ai_response) === '/warn')
        return;
    $user_entry = json_encode(['role' => 'user', 'parts' => [['text' => $user_message]]]);
    $model_entry = json_encode(['role' => 'model', 'parts' => [['text' => $ai_response]]]);

    $all_lines = file_exists(CHAT_HISTORY_FILE) ? file(CHAT_HISTORY_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
    $all_lines[] = $user_entry;
    $all_lines[] = $model_entry;

    if (count($all_lines) > MAX_HISTORY_LINES) {
        $all_lines = array_slice($all_lines, -MAX_HISTORY_LINES);
    }
    file_put_contents(CHAT_HISTORY_FILE, implode(PHP_EOL, $all_lines) . PHP_EOL, LOCK_EX);
}

function sendMessage(string $messageText, $chatID, string $botToken, ?int $replyToMessageId = null, ?string $parseMode = null): void
{
    $api_url = "https://api.telegram.org/bot$botToken/sendMessage";
    $query_params = [
        'chat_id' => $chatID,
        'text' => $messageText,
    ];
    if ($replyToMessageId !== null)
        $query_params['reply_to_message_id'] = $replyToMessageId;
    if ($parseMode !== null)
        $query_params['parse_mode'] = $parseMode;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $query_params);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code !== 200) {
        error_log("--- TELEGRAM API ERROR ---: HTTP Code: $http_code | Response: $response");
    } else {
        error_log("INFO: Message sent to Telegram successfully with parse_mode=$parseMode.");
    }
}
?>