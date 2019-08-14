<?php

namespace WebinterpretConnector\Buzz\Message\Factory;

use WebinterpretConnector\Buzz\Message\Form\FormRequest;
use WebinterpretConnector\Buzz\Message\Request;
use WebinterpretConnector\Buzz\Message\RequestInterface;
use WebinterpretConnector\Buzz\Message\Response;

class Factory implements FactoryInterface
{
    public function createRequest($method = RequestInterface::METHOD_GET, $resource = '/', $host = null)
    {
        return new Request($method, $resource, $host);
    }

    public function createFormRequest($method = RequestInterface::METHOD_POST, $resource = '/', $host = null)
    {
        return new FormRequest($method, $resource, $host);
    }

    public function createResponse()
    {
        return new Response();
    }
}
