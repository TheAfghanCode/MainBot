<?php

$BOT_TOKEN = getenv('BOT_TOKEN');

$data = json_decode(file_get_contents("php://input"), true);

if (isset($data['user_id']) && isset($data['message'])) {
    $chat_id = $data['user_id'];
    $text = $data['message'];

    // Send message back to user
    $url = "https://api.telegram.org/bot$BOT_TOKEN/sendMessage";
    $params = [
        'chat_id' => $chat_id,
        'text' => $text
    ];

    file_get_contents($url . '?' . http_build_query($params));

    // Optional: log the request
    file_put_contents("log.txt", date('c') . " - Sent to $chat_id: $text\n", FILE_APPEND);
}
?>
