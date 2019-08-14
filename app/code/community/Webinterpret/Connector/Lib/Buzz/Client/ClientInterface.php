<?php

namespace WebinterpretConnector\Buzz\Client;

use WebinterpretConnector\Buzz\Exception\ClientException;
use WebinterpretConnector\Buzz\Message\MessageInterface;
use WebinterpretConnector\Buzz\Message\RequestInterface;

interface ClientInterface
{
    /**
     * Populates the supplied response with the response for the supplied request.
     *
     * @param RequestInterface $request  A request object
     * @param MessageInterface $response A response object
     *
     * @throws ClientException If something goes wrong
     */
    public function send(RequestInterface $request, MessageInterface $response);
}
