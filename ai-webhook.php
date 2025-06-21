<?php
// PHP 8.0+ is recommended

header('Content-Type: text/html; charset=utf-8');

// --- امن‌سازی: خواندن کلیدها از فایل .env2 ---
$env = parse_ini_file('.env2');
if ($env === false) {
    file_put_contents('bot_errors.log', "Error: Could not read .env2 file.\n", FILE_APPEND);
    exit;
}
$BOT_TOKEN = $env['BOT_TOKEN'];
$GEMINI_API_KEY = $env['GEMINI_API_KEY'];

// Get the update from Telegram's webhook
$update = json_decode(file_get_contents('php://input'), true);

// --- پردازش پیام فقط در صورت وجود ---
if (isset($update['message']['text'])) {
    $chat_id = $update['message']['chat']['id'];
    $user_message = $update['message']['text'];

    // *** ارتقاء شماره ۱: شکار کردن شناسه پیام! ***
    // ما به این شناسه برای ریپلای زدن نیاز داریم.
    $message_id = $update['message']['message_id'];

    // Call the Gemini API and get the response
    $final_ai_response = getGeminiResponse($user_message, $GEMINI_API_KEY);

    // *** ارتقاء شماره ۳: منطق شلیک دقیق! ***
    // اینجا تصمیم می‌گیریم که یک پاسخ عادی بدهیم یا یک اخطار نقطه‌زن!
    if (trim($final_ai_response) === '/warn') {
        // اگر پاسخ /warn بود، روی پیام اصلی ریپلای می‌زنیم.
        sendMessage($final_ai_response, $chat_id, $BOT_TOKEN, $message_id);
    } else {
        // در غیر این صورت، یک پاسخ عادی و بدون ریپلای ارسال می‌کنیم.
        sendMessage($final_ai_response, $chat_id, $BOT_TOKEN);
    }
}


/**
 * Sends a request to the Gemini API and returns the response.
 * (This function remains unchanged)
 * @param string $prompt The user's message.
 * @param string $apiKey The Gemini API key.
 * @return string The AI's response or an error message.
 */
function getGeminiResponse(string $prompt, string $apiKey): string
{
    // The logic inside this function is perfect and does not need to change.
    // It correctly gets the '/warn' command or a normal answer.
    // ... (Your existing Gemini API call logic) ...
    $apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=' . $apiKey;
    $data = [
        "contents" => [
            [
                "role" => "user",
                "parts" => [["text" => "شما یک ربات دستیار هوشمند به نام 'CodeGuardian' ... (بقیه قوانین) ..."]]
            ],
            [
                "role" => "model",
                "parts" => [["text" => "دستورالعمل‌ها دریافت شد..."]]
            ]
        ],
        "generationConfig" => [
            "temperature" => 0.3,
            "maxOutputTokens" => 1500,
        ],
        "safetySettings" => [
            ["category" => "HARM_CATEGORY_HARASSMENT", "threshold" => "BLOCK_NONE"],
            ["category" => "HARM_CATEGORY_HATE_SPEECH", "threshold" => "BLOCK_NONE"]
        ]
    ];
    $data['contents'][] = ['role' => 'user', 'parts' => [['text' => $prompt]]];
    $jsonData = json_encode($data);
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $response = curl_exec($ch);
    if (curl_errno($ch)) { /* ... error handling ... */
        return "خطای فنی در اتصال.";
    }
    curl_close($ch);
    $result = json_decode($response, true);
    if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
        return $result['candidates'][0]['content']['parts'][0]['text'];
    } else { /* ... error handling ... */
        return "مشکلی در پردازش درخواست شما پیش آمد.";
    }
}


/**
 * Sends a message to a Telegram chat, with an option to reply.
 *
 * @param string $messageText The text to send.
 * @param int|string $chatID The ID of the chat.
 * @param string $botToken The Telegram Bot Token.
 * @param int|null $replyToMessageId The ID of the message to reply to (optional).
 */
function sendMessage(string $messageText, $chatID, string $botToken, ?int $replyToMessageId = null): void
{
    // *** ارتقاء شماره ۲: مسلح کردن تابع ارسال پیام! ***
    $api_url = "https://api.telegram.org/bot$botToken/sendMessage";

    $query_params = [
        'chat_id' => $chatID,
        'text' => $messageText,
    ];

    // اگر شناسه‌ی ریپلای ارسال شده باشد، آن را به درخواست اضافه می‌کنیم
    if ($replyToMessageId !== null) {
        $query_params['reply_to_message_id'] = $replyToMessageId;
    }

    @file_get_contents($api_url . "?" . http_build_query($query_params));
}

?>