<?php
// PHP 8.0+ is recommended

// Ensure we are sending UTF-8 headers
header('Content-Type: text/html; charset=utf-8');

// --- امن‌سازی: خواندن کلیدها از فایل .env2 ---
// All your secret keys should be here. Never write them directly in the code.
$env = parse_ini_file('.env2');
if ($env === false) {
    // A simple way to log errors. In a real project, use a proper logger.
    file_put_contents('bot_errors.log', "Error: Could not read .env2 file.\n", FILE_APPEND);
    exit; // Stop execution if we can't get the keys
}
$BOT_TOKEN = $env['BOT_TOKEN'];
$GEMINI_API_KEY = $env['GEMINI_API_KEY']; // *** کلید Gemini را هم از فایل امن می‌خوانیم ***

// Get the update from Telegram's webhook
$update = json_decode(file_get_contents('php://input'), true);

// --- پردازش پیام فقط در صورت وجود ---
if (isset($update['message']['text'])) {
    $chat_id = $update['message']['chat']['id'];
    $user_message = $update['message']['text'];

    // Call the Gemini API and get the response
    $final_ai_response = getGeminiResponse($user_message, $GEMINI_API_KEY);
    
    // Send the AI's response back to the Telegram chat
    sendMessage($final_ai_response, $chat_id, $BOT_TOKEN);
}


/**
 * Sends a request to the Gemini API and returns the response.
 *
 * @param string $prompt The user's message.
 * @param string $apiKey The Gemini API key.
 * @return string The AI's response or an error message.
 */
function getGeminiResponse(string $prompt, string $apiKey): string
{
    // *** منطق هوش مصنوعی در یک تابع تمیز و جداگانه قرار گرفت ***
    $apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=' . $apiKey;

    // The initial prompt that defines the bot's personality and rules
    $data = [
        "contents" => [
            [
                "role" => "user",
                "parts" => [["text" => "شما یک ربات دستیار هوشمند به نام 'CodeGuardian' در یک گروه تلگرامی برنامه‌نویسی هستید. شما دو قانون اصلی و بسیار مهم دارید:\n\nقانون شماره ۱: **پاسخ به سوالات فنی.**\nوقتی یک کاربر سوالی در مورد برنامه‌نویسی (مانند پایتون، جاوااسکریپت، PHP، CSS و غیره) می‌پرسد، شما باید یک پاسخ دقیق، مفید و واضح ارائه دهید. در صورت امکان از مثال‌های کد استفاده کنید.\n\nقانون شماره ۲: **اجرای ادب و احترام.**\nاگر پیامی حاوی توهین آشکار، کلمات بسیار زشت، یا نفرت‌پراکنی نسبت به دیگران بود، شما تحت هیچ شرایطی نباید به آن پاسخ دهید. تنها و تنها خروجی شما باید متن دقیق زیر باشد و نه هیچ چیز دیگر:\n/warn\n\nمثال ۱:\nپیام کاربر: 'چطوری می‌تونم یک div رو در CSS وسط‌چین کنم؟'\nپاسخ صحیح شما: 'برای وسط‌چین کردن یک div راه‌های مختلفی وجود داره. مدرن‌ترین راه استفاده از Flexbox هست: `display: flex; justify-content: center; align-items: center;` ...'\n\nمثال ۲:\nپیام کاربر: 'شماها هیچی بلد نیستین، این چه کدیه آخه!؟'\nپاسخ صحیح شما: '/warn'\n\nحالا، پیام بعدی کاربر را تحلیل کرده و طبق این قوانین پاسخ دهید:"]]
            ],
            [
                "role" => "model",
                "parts" => [["text" => "دستورالعمل‌ها دریافت شد. من به عنوان 'CodeGuardian' آماده‌ام تا به گروه برنامه‌نویسی طبق این قوانین کمک کنم. لطفاً پیام بعدی را ارائه دهید."]]
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

    // Add the new user message to the conversation history
    $data['contents'][] = ['role' => 'user', 'parts' => [['text' => $prompt]]];

    $jsonData = json_encode($data);

    // --- ارتباط با API با cURL ---
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        $error = 'cURL Error: ' . curl_error($ch);
        curl_close($ch);
        file_put_contents('bot_errors.log', $error . "\n", FILE_APPEND);
        return "خطای فنی در اتصال. لطفاً به ادمین اطلاع دهید.";
    }
    curl_close($ch);

    // --- رمزگشایی هوشمندانه پاسخ ---
    $result = json_decode($response, true);

    if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
        return $result['candidates'][0]['content']['parts'][0]['text'];
    } else {
        // *** نمایش خطای واقعی از طرف Gemini برای خطایابی بهتر ***
        $error_message = "پاسخ نامعتبر از هوش مصنوعی. ";
        if(isset($result['error']['message'])) {
            $error_message .= "پیام خطا: " . $result['error']['message'];
        }
        file_put_contents('bot_errors.log', $error_message . "\nResponse: " . $response . "\n", FILE_APPEND);
        return "متاسفانه مشکلی در پردازش درخواست شما پیش آمد. (کد خطا: G-01)";
    }
}


/**
 * Sends a message to a Telegram chat.
 *
 * @param string $messageText The text to send.
 * @param int|string $chatID The ID of the chat.
 * @param string $botToken The Telegram Bot Token.
 */
function sendMessage(string $messageText, $chatID, string $botToken): void
{
    // *** تابع ارسال پیام بهبود یافته و تمیزتر شده ***
    $api_url = "https://api.telegram.org/bot$botToken/sendMessage";

    // For simplicity, we send as plain text. 
    // If you need Markdown, you must escape it properly.
    $query_params = [
        'chat_id' => $chatID,
        'text' => $messageText,
        // 'parse_mode' => 'MarkdownV2' // Enable this only if you are sure your text is escaped correctly
    ];
    
    // Use file_get_contents for a simple request.
    // The @ symbol suppresses warnings on failure, which is okay for a fire-and-forget request.
    @file_get_contents($api_url . "?" . http_build_query($query_params));
}

?>


### چک‌لیست نهایی برای پرتاب موفقیت‌آمیز:
