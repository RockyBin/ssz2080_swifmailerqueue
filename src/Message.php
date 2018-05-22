<?php
namespace swiftmailerssz\mailerqueue;

use yii\base\InvalidConfigException;

class Message extends \yii\swiftmailer\Message
{
    public function queue()
    {
        $redis = \Yii::$app->redis;
        if (empty($redis)){
            throw new InvalidConfigException("redis not found in config");
        }

        // 0 - 15 redis数据
        // db => 1
        $mailer = \Yii::$app->mailer;
        if (empty($mailer) || !$redis->select($mailer->db)) {
            throw new InvalidConfigException('db not defined.');
        }
        $message = [];
        $message['from'] = array_keys($this->from);
        $message['to'] = array_keys($this->getTo());
        $message['cc'] = array_keys($this->getCc());
        $message['bcc'] = array_keys($this->getBcc());
        $message['reply_to'] = array_keys($this->getReplyTo());
        $message['charset'] = array_keys($this->getCharset());
        $message['subject'] = array_keys($this->getSubject());
        $parts = $this->getSwiftMessage()->getChildren();
        if (!is_array($parts) || !sizeof($parts)) {
            $parts = [$this->getSwiftMessage()];
        }
        foreach ($parts as $part) {
            if (!$part instanceof \Swift_Mime_Attachment) {
                switch($part->getContentType()) {
                    case 'text/html':
                        $message['html_body'] = $part->getBody();
                        break;
                    case 'text/plain':
                        $message['text_body'] = $part->getBody();
                        break;
                }
                if (!$message['charset']) {
                    $message['charset'] = $part->getCharset();
                }
            }
        }

        //todo 用户点击邮箱注册，队列在内存中还未完成注册期间，多次重复提交
        return $redis->rpush($mailer->key, json_encode($message));
    }
}