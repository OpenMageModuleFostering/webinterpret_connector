<?php

/**
 * Diagnostics controller
 *
 * @category   Webinterpret
 * @package    Webinterpret_Connector
 * @author     Webinterpret Team <info@webinterpret.com>
 * @license    http://opensource.org/licenses/osl-3.0.php
 */
class Webinterpret_Connector_DiagnosticsController extends Mage_Core_Controller_Front_Action
{
    public function indexAction()
    {
        try {
            Webinterpret_Connector_Helper_Verifier::verifyRequestWebInterpretSignature();
            $this->handleDiagnosticsRequest();
        } catch (\Exception $e) {
            echo $e->getMessage();
            die();
        }
        die();
    }

    /**
     * Provides diagnostics information for Status API
     */
    private function handleDiagnosticsRequest()
    {
        $statusApiConfigurator = new \WebInterpret\Toolkit\StatusApi\StatusApiConfigurator(Mage::app(), Mage::getConfig());

        $statusApi = $statusApiConfigurator->getExtendedStatusApi();

        $json = $statusApi->getJsonTestResults();
        header('Content-Type: application/json');
        header('Content-Length: '.strlen($json));
        echo $json;
    }
}

