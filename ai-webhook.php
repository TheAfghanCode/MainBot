<?php
// PHP 8.0+ is recommended

// --- ارتقاء شماره ۱: فعال‌سازی حالت دیباگ کامل ---
// This will force all errors to be logged, helping us find the silent killer.
ini_set('display_errors', 0); // Do not display errors to the user/Telegram
ini_set('log_errors', 1);
ini_set('error_log', 'bot_errors.log'); // Log all errors to our file
error_reporting(E_ALL);

header('Content-Type: text/html; charset=utf-8');

// --- Constants ---
define('CHAT_HISTORY_FILE', 'chat_history.log');
define('MAX_HISTORY_LINES', 20);

// --- ارتقاء شماره ۲: استفاده از جعبه سیاه پرواز (try...catch) ---
try {
    // --- Load Environment Variables ---
    $env = parse_ini_file('.env2');
    if (!$env) {
        throw new Exception("Failed to read .env2 file.");
    }
    $BOT_TOKEN = $env['BOT_TOKEN'];
    $GEMINI_API_KEY = $env['GEMINI_API_KEY'];

    // Get update from Telegram
    $update = json_decode(file_get_contents('php://input'), true);

    if (isset($update['message']['text'])) {
        $chat_id = $update['message']['chat']['id'];
        $user_message = $update['message']['text'];
        $message_id = $update['message']['message_id'];

        error_log("INFO: New message received. ChatID: $chat_id, MessageID: $message_id");

        // --- Load chat history ---
        $history_contents = load_chat_history();
        error_log("INFO: Chat history loaded. " . count($history_contents) . " items found.");


        // --- Call the AI with memory ---
        $final_ai_response = getGeminiResponse($user_message, $GEMINI_API_KEY, $history_contents);
        error_log("INFO: Received response from Gemini: " . $final_ai_response);


        // --- Save the new interaction to history ---
        save_chat_history($user_message, $final_ai_response);
        error_log("INFO: New interaction saved to history.");

        // --- Reply logic ---
        if (trim($final_ai_response) === '/warn') {
            sendMessage($final_ai_response, $chat_id, $BOT_TOKEN, $message_id);
        } else {
            sendMessage($final_ai_response, $chat_id, $BOT_TOKEN, null, 'MarkdownV2');
        }
        error_log("INFO: Response sent to Telegram. Script finished successfully.");

    }

} catch (Throwable $e) {
    // This block will catch ANY fatal error in the script and log it.
    error_log("--- FATAL ERROR CAUGHT ---");
    error_log("Error: " . $e->getMessage());
    error_log("File: " . $e->getFile());
    error_log("Line: " . $e->getLine());
    error_log("--------------------------");
}

function getGeminiResponse(string $prompt, string $apiKey, array $history_contents): string
{
    // ... (This function remains the same as the stable version) ...
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
    curl_close($ch);

    $result = json_decode($response, true);
    if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
        return $result['candidates'][0]['content']['parts'][0]['text'];
    }

    throw new Exception("Invalid Gemini Response: " . $response);
}

function load_chat_history(): array
{
    // ... (This function remains the same) ...
    if (!file_exists(CHAT_HISTORY_FILE))
        return [];
    $lines = file(CHAT_HISTORY_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $history_slice = array_slice($lines, -MAX_HISTORY_LINES);
    $contents = [];
    foreach ($history_slice as $line) {
        if (trim($line) !== '') {
            $decoded_line = json_decode($line, true);
            if (is_array($decoded_line)) {
                $contents[] = $decoded_line;
            }
        }
    }
    return $contents;
}

function save_chat_history(string $user_message, string $ai_response): void
{
    // ... (This function remains the same) ...
    if (trim($ai_response) === '/warn')
        return;
    $user_entry = json_encode(['role' => 'user', 'parts' => [['text' => $user_message]]]);
    $model_entry = json_encode(['role' => 'model', 'parts' => [['text' => $ai_response]]]);
    file_put_contents(CHAT_HISTORY_FILE, $user_entry . PHP_EOL, FILE_APPEND | LOCK_EX);
    file_put_contents(CHAT_HISTORY_FILE, $model_entry . PHP_EOL, FILE_APPEND | LOCK_EX);
    $lines = file(CHAT_HISTORY_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (count($lines) > MAX_HISTORY_LINES) {
        $lines_to_keep = array_slice($lines, -MAX_HISTORY_LINES);
        file_put_contents(CHAT_HISTORY_FILE, implode(PHP_EOL, $lines_to_keep) . PHP_EOL, LOCK_EX);
    }
}

function sendMessage(string $messageText, $chatID, string $botToken, ?int $replyToMessageId = null, ?string $parseMode = null): void
{
    // ... (This function remains the same) ...
    $api_url = "https://api.telegram.org/bot$botToken/sendMessage";
    $query_params = ['chat_id' => $chatID, 'text' => $messageText];
    if ($replyToMessageId !== null)
        $query_params['reply_to_message_id'] = $replyToMessageId;
    if ($parseMode !== null)
        $query_params['parse_mode'] = $parseMode;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url . "?" . http_build_query($query_params));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_exec($ch);
    curl_close($ch);
}
?>