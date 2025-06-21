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

    // --- Load chat history ---
    $history_contents = load_chat_history();

    // --- Call the AI with memory ---
    $final_ai_response = getGeminiResponse($user_message, $GEMINI_API_KEY, $history_contents);

    // --- Save the new interaction to history ---
    save_chat_history($user_message, $final_ai_response);

    // --- Reply logic ---
    if (trim($final_ai_response) === '/warn') {
        sendMessage($final_ai_response, $chat_id, $BOT_TOKEN, $message_id);
    } else {
        // *** ارتقاء کلیدی: ارسال پاسخ نهایی با قابلیت رندر Markdown ***
        sendMessage($final_ai_response, $chat_id, $BOT_TOKEN, null, 'MarkdownV2');
    }
}

/**
 * Loads the initial prompt and combines it with chat history for the API call.
 */
function getGeminiResponse(string $prompt, string $apiKey, array $history_contents): string
{
    // Load the brain from the external JSON file
    $template_json = file_get_contents('prompt_template.json');
    if ($template_json === false) {
        error_log("Failed to read prompt_template.json file.");
        return "خطای داخلی: فایل پرامپت یافت نشد.";
    }
    $data = json_decode($template_json, true);

    // Merge history into the request
    // The new prompt structure has multiple user/model turns, so we find the last model response to insert before.
    $lastModelIndex = count($data['contents']) - 1;
    array_splice($data['contents'], $lastModelIndex, 0, $history_contents);

    // Add the latest user message to the very end
    $data['contents'][] = ['role' => 'user', 'parts' => [['text' => $prompt]]];

    $apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=' . $apiKey;
    $jsonData = json_encode($data);

    // cURL request logic
    $ch = curl_init();
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
    // Do not save the interaction if it was just a warning
    if (trim($ai_response) === '/warn') {
        return;
    }

    $user_entry = json_encode(['role' => 'user', 'parts' => [['text' => $user_message]]]);
    $model_entry = json_encode(['role' => 'model', 'parts' => [['text' => $ai_response]]]);

    file_put_contents(CHAT_HISTORY_FILE, $user_entry . PHP_EOL, FILE_APPEND);
    file_put_contents(CHAT_HISTORY_FILE, $model_entry . PHP_EOL, FILE_APPEND);

    // Trim the log file
    $lines = file(CHAT_HISTORY_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (count($lines) > MAX_HISTORY_LINES) {
        $lines_to_keep = array_slice($lines, -MAX_HISTORY_LINES);
        file_put_contents(CHAT_HISTORY_FILE, implode(PHP_EOL, $lines_to_keep) . PHP_EOL);
    }
}
/**
 * Sends a message to Telegram.
 */
function sendMessage(string $messageText, $chatID, string $botToken, ?int $replyToMessageId = null, ?string $parseMode = null): void
{
    // *** ارتقاء نهایی: افزودن پارامتر parse_mode برای پشتیبانی از Markdown! ***
    $api_url = "https://api.telegram.org/bot$botToken/sendMessage";
    $query_params = [
        'chat_id' => $chatID,
        'text' => $messageText,
    ];
    if ($replyToMessageId !== null) {
        $query_params['reply_to_message_id'] = $replyToMessageId;
    }
    if ($parseMode !== null) {
        $query_params['parse_mode'] = $parseMode;
    }

    @file_get_contents($api_url . "?" . http_build_query($query_params));
}
?>