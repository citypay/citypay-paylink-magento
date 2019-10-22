<?php
/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace CityPay\PaylinkPaymentGateway\Gateway\Request;

use Magento\Payment\Gateway\ConfigInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;

class PLTokenRequest implements BuilderInterface
{
    /**
     * @var ConfigInterface
     */
    private $config;

    /**
     * @param ConfigInterface $config
     */
    public function __construct(
        ConfigInterface $config
    ) {
        $this->config = $config;
    }

    /**
     * Builds ENV request
     *
     * @param array $buildSubject
     * @return array
     */
    public function build(array $buildSubject)
    {
        if (!isset($buildSubject['payment'])
            || !$buildSubject['payment'] instanceof PaymentDataObjectInterface
        ) {
            throw new \InvalidArgumentException('Payment data object should be provided');
        }

        /** @var PaymentDataObjectInterface $payment */
        $payment = $buildSubject['payment'];
        $order = $payment->getOrder();
       // $address = $order->getShippingAddress();

        return [
            'merchantId'=>64215680,
            'licenceKey'=>'HKRW6442A025GEF0',
            'identifier'=> $order->getId(),
            'test'=>TRUE,
            'amount'=>$order->getGrandTotalAmount(),
            'config'=>[
                'customParams'=>[
                    ['name'=>'customerId',
                    'value'=>$order->getCustomerId(),
                    'locked'=>TRUE],
                    ['name'=>'storeId',
                        'value'=>$order->getStoreId(),
                        'locked'=>TRUE]

                ]
            ]
        ];

       /*
            [
            'TXN_TYPE' => 'A',
            'INVOICE' => $order->getOrderIncrementId(),
            'AMOUNT' => $order->getGrandTotalAmount(),
            'CURRENCY' => $order->getCurrencyCode(),
            'EMAIL' => $address->getEmail(),
            'MERCHANT_KEY' => $this->config->getValue(
                'merchant_gateway_key',
                $order->getStoreId()
            )
        ];*/
    }
}
