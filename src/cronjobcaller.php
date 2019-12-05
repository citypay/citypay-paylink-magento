<?php
require __DIR__ . '/../app/bootstrap.php';

class cronjobcaller
    extends \Magento\Framework\App\Http
    implements \Magento\Framework\AppInterface
{
    public function launch()
    {
        $this->_state->setAreaCode(\Magento\Framework\App\Area::AREA_ADMINHTML);
        $myClass = $this->_objectManager->create('Magento\Sales\Cron\SendEmails');
        $myClass->execute(); // In cron jobs we usually use the execute() method as the entry point
        return $this->_response;
    }
}

$bootstrap = \Magento\Framework\App\Bootstrap::create(BP, $_SERVER);
/** @var \Magento\Framework\App\Http $app */
$app = $bootstrap->createApplication('cronjobcaller');
$bootstrap->run($app);