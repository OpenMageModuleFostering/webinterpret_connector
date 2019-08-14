<?php
/**
 * Notification
 *
 * @category   Webinterpret
 * @package    Webinterpret_Connector
 * @author     Webinterpret Team <info@webinterpret.com>
 * @license    http://opensource.org/licenses/osl-3.0.php
 */
class Webinterpret_Connector_Model_Notification extends Varien_object
{
    protected $messages = array();

    public function getMessages()
    {
        return $this->messages;
    }

    public function setMessages($messages)
    {
        $this->messages = $messages;
        return $this;
    }

    public function addMessage($message)
    {
        $this->messages[] = $message;
        return $this;
    }
}
