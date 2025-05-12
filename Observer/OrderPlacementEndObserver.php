<?php
/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace CityPay\Paylink\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Payment\Gateway\Http\TransferBuilder;
use mysql_xdevapi\Exception;

//use Magento\Payment\Observer\AbstractDataAssignObserver;

class OrderPlacementEndObserver implements  ObserverInterface  //extends AbstractDataAssignObserver
{
    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;

    /**
     * @param TransferBuilder $transferBuilder
     * @var TransferBuilder
     */
    private $transferBuilder;

    /**
     * OrderPlacementObserver constructor.
     * @param \Psr\Log\LoggerInterface $logger
     */
    public function __construct(\Psr\Log\LoggerInterface $logger,TransferBuilder $transferBuilder)
    {
        // Observer initialization code...
        // You can use dependency injection to get any class this observer may need.
        $this->transferBuilder = $transferBuilder;
        $this->logger=$logger;
        $this->logger->debug('OrderPlacementEndObserver constructor');
    }
    /**
     * @param Observer $observer
     * @return void
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $this->logger->debug('OrderPlacementEndObserver execute');
        /** @var \Magento\Sales\Model\Order $order */
        $payment = $observer->getEvent()->getPayment();
        $order   = $payment->getOrder();

        // Prevent Magento from sending the "New Order" email at order placement
        $order->setCanSendNewEmailFlag(false);
    }

}
