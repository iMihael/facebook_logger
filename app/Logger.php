<?php

namespace app;

use MongoDB\Database;
use Symfony\Component\HttpFoundation\Request;

class Logger
{
    private $request;
    private $db;
    private $token;
    private $tag;

    public function __construct(Request $request, Database $db, $token, $tag)
    {
        $this->request = $request;
        $this->db = $db;
        $this->token = $token;
        $this->tag = $tag;

        if($user = $this->auth()) {
            $this->parse((string)$user['_id'], $user['user_id']);
        }
    }

    /**
     * @return \MongoDB\BSON\ObjectID
     */
    private function auth()
    {
        $collection = $this->db->selectCollection('user');
        if($user = $collection->findOne(['token' => $this->token])) {
            return $user;
        }

        return null;
    }

    private function parse($userId, $facebookUserId)
    {
        $collection = $this->db->selectCollection('request');

        $collection->insertOne([
            'user_id' => $userId,
            'tag' => $this->tag,
            'get' => $this->request->query->all(),
            'post' => $this->request->request->all(),
            'headers' => $this->request->headers->all(),
            'method' => $this->request->getMethod(),
            'ip' => $this->request->getClientIp(),
            'created_at' => new \MongoDB\BSON\UTCDateTime(time()),
        ]);

        (new Facebook())->sendMessage($facebookUserId, sprintf(
            "New Request.\nTag: %s\nMethod: %s\nGET: %s\nPOST: %s\nBody: %s",
            $this->tag,
            $this->request->getMethod(),
            json_encode($this->request->query->all(), JSON_PRETTY_PRINT),
            json_encode($this->request->request->all(), JSON_PRETTY_PRINT),
            $this->request->getContent()
        ));
    }
}