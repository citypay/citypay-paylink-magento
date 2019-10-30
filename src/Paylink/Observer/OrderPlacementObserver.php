<?php
/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace CityPay\Paylink\Observer;

use Magento\Framework\Event\ObserverInterface;
//use Magento\Payment\Observer\AbstractDataAssignObserver;

class OrderPlacementObserver implements  ObserverInterface  //extends AbstractDataAssignObserver
{
    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;

    /**
     * OrderPlacementObserver constructor.
     * @param \Psr\Log\LoggerInterface $logger
     */
    public function __construct(\Psr\Log\LoggerInterface $logger)
    {
        // Observer initialization code...
        // You can use dependency injection to get any class this observer may need.
        $this->logger=$logger;
        $this->logger->debug('OrderPlacementObserver constructor');
    }
    /**
     * @param Observer $observer
     * @return void
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $this->logger->debug('OrderPlacementObserver execute');
        /*
        $method = $this->readMethodArgument($observer);
        $data = $this->readDataArgument($observer);

        $paymentInfo = $method->getInfoInstance();

        if ($data->getDataByKey('transaction_result') !== null) {
            $paymentInfo->setAdditionalInformation(
                'transaction_result',
                $data->getDataByKey('transaction_result')
            );
        }
        */
    }
}
