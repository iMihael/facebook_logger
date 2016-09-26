<?php

namespace app;

use MongoDB\Database;
use Symfony\Component\HttpFoundation\Request;

class WebHook
{
    private $request;
    private $db;

    public function __construct(Request $request, Database $db)
    {
        $this->request = $request;
        $this->db = $db;

        $this->parse();
    }

    private function commands()
    {
        return [
            '/^\/start$/' => 'start',
            '/^\/subscribe ([a-zA-Z0-9]+)$/' => 'subscribe',
            '/^\/unsubscribe ([a-zA-Z0-9]+)$/' => 'unsubscribe',
            '/^\/gettags$/' => 'getTags',
            '/^\/gettoken$/' => 'getToken',
            '/^\/help$/' => 'help',
        ];
    }

    private function parse()
    {
        if($body = json_decode($this->request->getContent(), true)) {

            $collection = $this->db->selectCollection('raw_webhook');
            $collection->insertOne($body);

            $message = $body['entry'][0]['messaging'][0]['message']['text'];
            foreach($this->commands() as $command => $method) {
                $matches = [];
                if(preg_match($command, $message, $matches)) {
                    if(method_exists($this, $method)) {
                        $this->$method($body, $matches);
                    }
                    return;
                }
            }

        }
    }

    private function help($body)
    {
        (new Facebook())
            ->sendMessage(
                $body,
                "/start - regenerate token\n/gettoken - get token\n/gettags - get subscribed tags\n/subscribe - subscribe to tag\n/unsubscribe - unsubscribe from tag\nuri for sending requests: http://f.request.gq/request/<token>/<tag>"
            );

    }

    private function getToken($body)
    {
        $collection = $this->db->selectCollection('user');
        if($user = $collection->findOne(['user_id' => $body['message']['from']['id']])) {

            (new Facebook())
                ->sendMessage(
                    $body,
                    $user['token']
                );
        }
    }

    private function getTags($body)
    {
        $collection = $this->db->selectCollection('user');
        if($user = $collection->findOne(['user_id' => $body['message']['from']['id']])) {
            $tags = isset($user['tags']) ? (array)$user['tags'] : [];

            (new Facebook())
                ->sendMessage(
                    $body,
                    json_encode($tags)
                );
        }
    }

    private function subscribe($body, $matches)
    {
        $collection = $this->db->selectCollection('user');
        if($user = $collection->findOne(['user_id' => $body['message']['from']['id']])) {
            $tags = isset($user['tags']) ? (array)$user['tags'] : [];
            $tag = trim($matches[1]);
            if(!in_array($tag, $tags)) {
                $tags[] = $tag;

                $collection->updateOne(['user_id' => $body['message']['from']['id']], [
                    '$set' => [
                        'tags' => $tags,
                    ]
                ]);
            }

            (new Facebook())
                ->sendMessage(
                    $body,
                    "You are subscribed to tag " . $tag
                );
        }
    }

    private function unsubscribe($body, $matches)
    {
        $collection = $this->db->selectCollection('user');
        if($user = $collection->findOne(['user_id' => $body['message']['from']['id']])) {
            $tags = isset($user['tags']) ? (array)$user['tags'] : [];
            $tag = trim($matches[1]);
            if(in_array($tag, $tags)) {
                unset($tags[array_search($tag, $tags)]);
                $tags = array_values($tags);

                $collection->updateOne(['user_id' => $body['message']['from']['id']], [
                    '$set' => [
                        'tags' => $tags,
                    ]
                ]);
            }

            (new Facebook())
                ->sendMessage(
                    $body,
                    "You are subscribed from tag " . $tag
                );
        }
    }

    private function start($body)
    {
        $token = sha1(mt_rand(0, 100) . time());
        $collection = $this->db->selectCollection('user');
        if($user = $collection->findOne(['user_id' => $body['message']['from']['id']])) {
            $collection->updateOne([
                'user_id' => $body['message']['from']['id'],
            ], [
                '$set' => [
                    'token' => $token,
                    'chat_id' => $body['message']['chat']['id'],
                    'updated_at' => new \MongoDB\BSON\UTCDateTime(time()),
                ],
            ]);
        } else {
            $collection->insertOne([
                'user_id' => $body['message']['from']['id'],
                'token' => $token,
                'chat_id' => $body['message']['chat']['id'],
                'updated_at' => new \MongoDB\BSON\UTCDateTime(time()),
                'first_name' => $body['message']['from']['first_name'],
                'last_name' => $body['message']['from']['first_name'],
            ]);
        }

        (new Facebook())
            ->sendMessage(
                $body,
                "Your new token is $token\nYou can send requests to http://f.request.gq/request/$token/{tag}\nTo receive requests here, you must subscribe to tag using command /subscribe"
            );
    }
}