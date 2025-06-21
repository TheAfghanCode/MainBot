<?php
header('Content-Type: text/html; charset=utf-8');


$env = parse_ini_file('.env');

$BOT_TOKEN = getenv('BOT_TOKEN');
// $BOT_TOKEN = $env['BOT_TOKEN'];

$update = json_decode(file_get_contents('php://input'), true);
if (!$update) exit('No update received');

$CHANNEL_ID = getenv("LOG_CHANNEL");
// $CHANNEL_ID = $env["LOG_CHANNEL"];

$logMessage = "🧾 New log at " . date("Y-m-d H:i:s") . "\n\n";
$logMessage .= json_encode($update, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
$urlLog = "https://api.telegram.org/bot$BOT_TOKEN/sendMessage";
$dataLog = [
    'chat_id' => $CHANNEL_ID,
    'text' => $logMessage
];
file_get_contents($urlLog . "?" . http_build_query($dataLog));


if (isset($update['message'])) {
    $chat_id = $update['message']['chat']['id'];
    $text = $update['message']['text'] ?? '';
    $chatSession = "/home/alisina/session/$chat_id.session";
    if (file_exists($chatSession)) {
        $sessionState = file_get_contents($chatSession);

        if ($sessionState === "wr") {
            unlink($chatSession);
            sendMessage("گزارش جدید از " . $update['message']['from']['username'] . "\n\n" . $text, $env["REPORT_CHAT_ID"]);
        }
    }

    if ($text === '/start') {
        $keyboard = [
            'inline_keyboard' => [[
                [
                    'text' => 'اجرای مینی‌اپ',
                    'url' => 'https://t.me/afghancodebot?startapp'
                ]
            ]]
        ];

        $reply = [
            'chat_id' => $chat_id,
            'text' => "به ربات رسمی Afghan Coders خوش آمدید!\nبا ضربه زدن روی دکمه زیر مینی‌اپ Afghan Coders را اجرا کنید!",
            'reply_markup' => json_encode($keyboard)
        ];

        file_get_contents("https://api.telegram.org/bot$BOT_TOKEN/sendMessage?" . http_build_query($reply));
    } elseif ($text === '/document') {
        $keyboard = [
            'inline_keyboard' => [[
                [
                    'text' => 'اجرای مستندات پروژه',
                    'url' => 'https://193b-149-54-56-236.ngrok-free.app/document.html'
                ]
            ]]
        ];

        $reply = [
            'chat_id' => $chat_id,
            'text' => "به ربات رسمی Afghan Coders خوش آمدید!\nبا ضربه زدن روی دکمه زیر مینی‌اپ Afghan Coders را اجرا کنید!",
            'reply_markup' => json_encode($keyboard)
        ];

        file_get_contents("https://api.telegram.org/bot$BOT_TOKEN/sendMessage?" . http_build_query($reply));
    } elseif ($text === '/status') {
        sendMessage("ربات آنلاین است ✅", $chat_id);
    } elseif ($text === '/contact') {
        sendMessage($text, $chat_id);
    } elseif ($text === '/about') {
        sendMessage($text, $chat_id);
    } elseif ($text === "/report") {
        sendMessage("گزارش خود را ارسال کنید !", $chat_id);
        file_put_contents("/home/alisina/session/$chat_id.session", "wr");
    }
}

function sendMessage($messageText, $chatID)
{
    global $BOT_TOKEN;

    $reply = [
        'chat_id' => $chatID,
        'text' => escapeV2($messageText),
        'parse_mode' => 'MarkdownV2'
    ];

    file_get_contents("https://api.telegram.org/bot$BOT_TOKEN/sendMessage?" . http_build_query($reply));
}

function escapeV2($text)
{
    $markdownSpecialChars = array('_', '*', '[', ']', '(', ')', '~', '`', '>', '#', '+', '-', '=', '|', '{', '}', '.', '!');
    $countEscapeChar = count($markdownSpecialChars);
    $result = null;

    $length = strlen($text);
    if ($length === 0) return "";

    for ($i = 0; $length > $i; $i++) {
        $charStr = $text[$i];

        $isSpecialChar = false;
        $specialCharIndex = null;
        for ($y = 0; $y < $countEscapeChar; $y++) {
            if ($charStr == $markdownSpecialChars[$y]) {
                // $result = $result . "\\" . $markdownSpecialChars[$y];
                $isSpecialChar = true;
                $specialCharIndex = $y;
                break;
            }
        }
        if ($isSpecialChar) {
            $result = $result . "\\" . $markdownSpecialChars[$specialCharIndex];
        } else {
            $result = $result . $charStr;
        }
    }
    return $result;
}
