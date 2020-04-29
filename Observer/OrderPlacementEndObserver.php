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

        try {
            $ev = $observer->getEvent();
            $payment = $ev->getPayment();
            $request = $this->buildRequestData($payment);
            $transfer = $this->create($request);
            $response = $this->placeRequest($transfer);
        }
        catch (Exception $ex){
            $this->logger->error($ex->getMessage());
        }
        /*
        $data=$payment->getData();

        $data['additional_information']['method_title']; //"CityPay Hosted Payment Page"
        $methodInstance=$payment->getMethodInstance();
        $paymentInfo = $methodInstance->getInfoInstance();
        $order->$payment->getOrder();
        $order->getStatus();
        */
        //$this->logger->debug('OrderPlacementObserver event'+$ev);
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
