<?php
/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace CityPay\Paylink\Observer;

use Magento\Framework\Event\Observer;
use Magento\Payment\Observer\AbstractDataAssignObserver;
use Magento\Framework\DataObject;
use Magento\Quote\Api\Data\PaymentInterface;

class DataAssignObserver extends AbstractDataAssignObserver
{
    /**
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        $method = $this->readMethodArgument($observer);
        $data = $this->readDataArgument($observer);

        $paymentInfo = $method->getInfoInstance();

        $additionalData = $data->getData(
            PaymentInterface::KEY_ADDITIONAL_DATA
        );

        if ($additionalData instanceof DataObject) {
            $additionalData = $additionalData->getData();
        }

        if (!is_array($additionalData)) {
            return;
        }

        foreach (
            ['transaction_result', 'payment_intent_id', 'payment_channel']
            as $key
        ) {
            if (
                array_key_exists($key, $additionalData) &&
                $additionalData[$key] !== null
            ) {
                $paymentInfo->setAdditionalInformation(
                    $key,
                    $additionalData[$key]
                );
            }
        }
    }
}
