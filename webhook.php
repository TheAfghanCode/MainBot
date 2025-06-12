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
                    'text' => '🧩 Open Mini App',
                    'web_app' => ['url' => 'https://mainbot-g94g.onrender.com/mini-app/index.html']
                ]
            ]]
        ];

        $reply = [
            'chat_id' => $chat_id,
            'text' => "👋 Welcome to Afghan Code!\nClick the button below to open the mini app.",
            'reply_markup' => json_encode($keyboard)
        ];

        file_get_contents("https://api.telegram.org/bot$BOT_TOKEN/sendMessage?" . http_build_query($reply));
    }
}
?>
