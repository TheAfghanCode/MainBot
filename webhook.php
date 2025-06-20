<?php
header('Content-Type: text/html; charset=utf-8');

$BOT_TOKEN = getenv('BOT_TOKEN');
// $BOT_TOKEN = $env['BOT_TOKEN'];

$update = json_decode(file_get_contents('php://input'), true);
if (!$update) exit('No update received');

$CHANNEL_ID = getenv("LOG_CHANNEL");

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
    } elseif ($text === '/status') {
        sendMessage("ربات آنلاین است ✅", $chat_id);
    }
    elseif ($text === '/contact') {
        sendMessage($text, $chat_id);
    }
    elseif ($text === '/about') {
        sendMessage($text, $chat_id);
    }
}

function sendMessage($messageText, $chatID)
{
    global $BOT_TOKEN;

    $reply = [
        'chat_id' => $chatID,
        'text' => $messageText
    ];

    file_get_contents("https://api.telegram.org/bot$BOT_TOKEN/sendMessage?" . http_build_query($reply));
}
