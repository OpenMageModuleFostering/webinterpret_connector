<?php

namespace WebinterpretConnector\Buzz\Listener;

use WebinterpretConnector\Buzz\Message\MessageInterface;
use WebinterpretConnector\Buzz\Message\RequestInterface;

interface ListenerInterface
{
    public function preSend(RequestInterface $request);
    public function postSend(RequestInterface $request, MessageInterface $response);
}
