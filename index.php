<?php
// GPT API
$api_key = '';

// FB API
$access_token = '';
$verify_token = '';

// Obsługuje weryfikację zapytania
if ($_REQUEST['hub_verify_token'] === $verify_token) {
    echo $_REQUEST['hub_challenge'];
    exit;
}

// Odbierz dane od Messenger API
$input = json_decode(file_get_contents('php://input'), true);

// Sprawdź, czy dane zawierają wiadomości
if (isset($input['entry'][0]['messaging'])) {
    foreach ($input['entry'][0]['messaging'] as $event) {
        $sender_id = $event['sender']['id'];
        $message_id = $event['message']['mid']; // Pobierz ID wiadomości

        $message = $event['message']['text'];

        // Wysyłanie "pisze..." informacji
        $data = [
            'recipient' => ['id' => $sender_id],
            'sender_action' => 'typing_on',
        ];

        $options = [
            'http' => [
                'header' => "Content-Type: application/json\r\n",
                'method' => 'POST',
                'content' => json_encode($data),
            ],
        ];

        $context = stream_context_create($options);
        $result = file_get_contents("https://graph.facebook.com/v13.0/me/messages?access_token=$access_token", false, $context);

        // Teraz wywołujemy API GPT-3.5 Turbo
        $url = 'https://api.openai.com/v1/chat/completions';

        $data = array(
            "model" => "gpt-3.5-turbo",
            "messages" => array(
                array("role" => "user", "content" => $message)
            ),
            "temperature" => 0.7
        );

        $headers = array(
            'Content-Type: application/json',
            'Authorization: Bearer ' . $api_key
        );

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);

        if ($response === false) {
            $responsem = 'Błąd: ' . curl_error($ch);
        } else {
            // Parsowanie odpowiedzi JSON
            $response_data = json_decode($response, true);

            // Wyodrębnienie treści i przypisanie do zmiennej
            $responsem = $response_data['choices'][0]['message']['content'];
        }

        curl_close($ch);

        // Po otrzymaniu odpowiedzi od GPT-3.5 Turbo
        $data = [
            'recipient' => ['id' => $sender_id],
            'message' => ['text' => $responsem],
        ];

        $options = [
            'http' => [
                'header' => "Content-Type: application/json\r\n",
                'method' => 'POST',
                'content' => json_encode($data),
            ],
        ];

        $context = stream_context_create($options);
        $result = file_get_contents("https://graph.facebook.com/v13.0/me/messages?access_token=$access_token", false, $context);
    }
}
?>
