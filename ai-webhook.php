<?php
// PHP 8.0+ is recommended

header('Content-Type: text/html; charset=utf-8');

// --- Constants ---
define('CHAT_HISTORY_FILE', 'chat_history.log');
define('MAX_HISTORY_LINES', 20); // Keep last 10 user messages + 10 bot responses

// --- Load Environment Variables ---
$env = parse_ini_file('.env2');
if (!$env) {
    error_log("Failed to read .env2 file.");
    exit;
}
$BOT_TOKEN = $env['BOT_TOKEN'];
$GEMINI_API_KEY = $env['GEMINI_API_KEY'];

// Get update from Telegram
$update = json_decode(file_get_contents('php://input'), true);

if (isset($update['message']['text'])) {
    $chat_id = $update['message']['chat']['id'];
    $user_message = $update['message']['text'];
    $message_id = $update['message']['message_id'];

    // --- ارتقاء شماره ۱: بارگذاری تاریخچه مکالمات ---
    $history_contents = load_chat_history();

    // --- ارتقاء شماره ۲: فراخوانی هوش مصنوعی با حافظه! ---
    $final_ai_response = getGeminiResponse($user_message, $GEMINI_API_KEY, $history_contents);

    // --- ارتقاء شماره ۳: ذخیره مکالمه جدید در تاریخچه ---
    save_chat_history($user_message, $final_ai_response);

    // --- منطق ریپلای (بدون تغییر) ---
    if (trim($final_ai_response) === '/warn') {
        sendMessage($final_ai_response, $chat_id, $BOT_TOKEN, $message_id);
    } else {
        sendMessage($final_ai_response, $chat_id, $BOT_TOKEN, $message_id);
    }
}

/**
 * Loads the initial prompt and combines it with chat history for the API call.
 */
function getGeminiResponse(string $prompt, string $apiKey, array $history_contents): string
{
    // *** ارتقاء کلیدی: بارگذاری مغز خارجی از فایل JSON! ***
    $template_json = file_get_contents('prompt_template.json');
    $data = json_decode($template_json, true);

    // *** ارتقاء کلیدی: ادغام تاریخچه با درخواست جدید! ***
    // Prepend history to the start of the contents, right after the initial system prompt.
    array_splice($data['contents'], 2, 0, $history_contents);

    // Add the latest user message to the very end
    $data['contents'][] = ['role' => 'user', 'parts' => [['text' => $prompt]]];

    $apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=' . $apiKey;
    $jsonData = json_encode($data);

    // cURL request logic... (same as before)
    $ch = curl_init();
    // ... (all curl_setopt lines) ...
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    $response = curl_exec($ch);
    curl_close($ch);

    $result = json_decode($response, true);
    if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
        return $result['candidates'][0]['content']['parts'][0]['text'];
    }

    // Log error if response is not valid
    error_log("Invalid Gemini Response: " . $response);
    return "مشکلی در پردازش درخواست شما پیش آمد.";
}

/**
 * Loads the last N messages from the history file.
 */
function load_chat_history(): array
{
    if (!file_exists(CHAT_HISTORY_FILE)) {
        return [];
    }
    $lines = file(CHAT_HISTORY_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $history_slice = array_slice($lines, -MAX_HISTORY_LINES);

    $contents = [];
    foreach ($history_slice as $line) {
        $decoded_line = json_decode($line, true);
        if (is_array($decoded_line)) {
            $contents[] = $decoded_line;
        }
    }
    return $contents;
}

/**
 * Saves the new user message and AI response to the history file.
 */
function save_chat_history(string $user_message, string $ai_response): void
{
    $user_entry = json_encode(['role' => 'user', 'parts' => [['text' => $user_message]]]);
    $model_entry = json_encode(['role' => 'model', 'parts' => [['text' => $ai_response]]]);

    file_put_contents(CHAT_HISTORY_FILE, $user_entry . PHP_EOL, FILE_APPEND);
    file_put_contents(CHAT_HISTORY_FILE, $model_entry . PHP_EOL, FILE_APPEND);

    // Trim the log file to prevent it from growing indefinitely
    $lines = file(CHAT_HISTORY_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (count($lines) > MAX_HISTORY_LINES) {
        $lines_to_keep = array_slice($lines, -MAX_HISTORY_LINES);
        file_put_contents(CHAT_HISTORY_FILE, implode(PHP_EOL, $lines_to_keep) . PHP_EOL);
    }
}

/**
 * Sends a message to Telegram.
 * (This function remains unchanged)
 */
function sendMessage(string $messageText, $chatID, string $botToken, ?int $replyToMessageId = null): void
{
    // ... (same sendMessage logic as before) ...
    $api_url = "https://api.telegram.org/bot$botToken/sendMessage";
    $query_params = ['chat_id' => $chatID, 'text' => $messageText];
    if ($replyToMessageId !== null) {
        $query_params['reply_to_message_id'] = $replyToMessageId;
    }
    @file_get_contents($api_url . "?" . http_build_query($query_params));
}
?>