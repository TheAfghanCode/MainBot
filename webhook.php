<?php
if (!file_exists('.env')) exit('Env file not found');

$env = parse_ini_file('.env');
$BOT_TOKEN = $env['BOT_TOKEN'];

$update = json_decode(file_get_contents('php://input'), true);

file_put_contents("log2.txt", date("H:i:s") . " - " . print_r($update, true) . "\n", FILE_APPEND);

if (isset($update['message'])) {
    $chat_id = $update['message']['chat']['id'];
    $text = $update['message']['text'] ?? '';

    if ($text === '/start') {
        $keyboard = [
            'inline_keyboard' => [[
                [
                    'text' => 'اجرای مینی‌اپ',
'url' => 'https://t.me/afghancodebot?startapp'
      # 'url' => ['url' => 'https://t.me/afghancodebot?startapp']
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
        sendMessage("ربات آنلاین است ✅");
    }
}

function sendMessage($messageText = "This a Test From sendmessage() at webhook.php")
{
    global $update, $BOT_TOKEN;
    $chat_id = $update['message']['chat']['id'];

    $reply = [
        'chat_id' => $chat_id,
        'text' => $messageText
    ];

    file_get_contents("https://api.telegram.org/bot$BOT_TOKEN/sendMessage?" . http_build_query($reply));
}