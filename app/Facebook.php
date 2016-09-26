<?php

namespace app;

class Facebook
{
    private $token;
    private $url = 'https://graph.facebook.com/v2.6/me/messages?access_token=%s';
    public function __construct()
    {
        $params = require __DIR__ . '/../config/params.php';
        $this->token = $params['page_token'];
    }

    public function sendMessage($body, $text)
    {
        $id = $body['entry'][0]['messaging'][0]['sender']['id'];

        $data = [
            'recipient' => [
                'id' => $id
            ],
            'message' => [
                'text' => $text,
            ],
        ];

        $data_string = json_encode($data);

        $ch = curl_init(sprintf($this->url, $this->token));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $data_string,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($data_string),
            ],
        ]);

        return curl_exec($ch);
    }
}