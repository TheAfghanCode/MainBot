<?php
// Load env variables
$env = parse_ini_file('.env');
$BOT_TOKEN = $env['BOT_TOKEN'];

$update = json_decode(file_get_contents('php://input'), true);

if (isset($update['message'])) {
    $chat_id = $update['message']['chat']['id'];

    // If /start command
    if ($update['message']['text'] === '/start') {
        $keyboard = [
            'keyboard' => [],
            'inline_keyboard' => [[
                [
                    'text' => 'اجرای مینی‌اپ',
                    'web_app' => ['url' => $env['BOT_URL'] . '?startapp']
                ]
            ]]
        ];

        $reply = [
            'chat_id' => $chat_id,
            'text' => "به ربات رسمی Afghan Coders خوش آمدید!\nبا ضربه زدن روی دکمه زیر مینی‌اپ Afghan Coders را اجرا کنید!",
            'reply_markup' => json_encode($keyboard)
        ];

        file_get_contents("https://api.telegram.org/bot$BOT_TOKEN/sendMessage?" . http_build_query($reply));
    }
}
?>
