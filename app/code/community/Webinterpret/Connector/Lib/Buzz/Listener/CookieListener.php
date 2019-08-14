<?php

namespace WebinterpretConnector\Buzz\Listener;

use WebinterpretConnector\Buzz\Message\RequestInterface;
use WebinterpretConnector\Buzz\Message\MessageInterface;

use WebinterpretConnector\Buzz\Util\Cookie;
use WebinterpretConnector\Buzz\Util\CookieJar;

class CookieListener implements ListenerInterface
{
    private $cookieJar;

    public function __construct()
    {
        $this->cookieJar = new CookieJar();
    }

    public function setCookies($cookies)
    {
        $this->cookieJar->setCookies($cookies);
    }

    public function getCookies()
    {
        return $this->cookieJar->getCookies();
    }

    /**
     * Adds a cookie to the current cookie jar.
     *
     * @param Cookie $cookie A cookie object
     */
    public function addCookie(Cookie $cookie)
    {
        $this->cookieJar->addCookie($cookie);
    }

    public function preSend(RequestInterface $request)
    {
        $this->cookieJar->clearExpiredCookies();
        $this->cookieJar->addCookieHeaders($request);
    }

    public function postSend(RequestInterface $request, MessageInterface $response)
    {
        $this->cookieJar->processSetCookieHeaders($request, $response);
    }
}