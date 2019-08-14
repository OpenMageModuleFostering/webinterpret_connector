<?php

namespace WebinterpretConnector\Buzz\Message\Form;

use WebinterpretConnector\Buzz\Message\MessageInterface;

interface FormUploadInterface extends MessageInterface
{
    public function setName($name);
    public function getFile();
    public function getFilename();
    public function getContentType();
}
