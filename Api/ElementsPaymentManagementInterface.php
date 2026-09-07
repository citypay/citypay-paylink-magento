<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace CityPay\Paylink\Api;

/**
 * Interface for managing CityPay ElementsPaymentManagement Methods
 */

interface ElementsPaymentManagementInterface
{
    /**
     * @return string
     */
    public function createSession();


    /**
     * @param string $paymentIntentId
     * @param int $orderId
     * @return string
     */
    public function authorise($paymentIntentId, $orderId);


    /**
     * @param string $paymentIntentId
     * @param int $orderId
     * @return string
     */
    public function verify($paymentIntentId, $orderId);
}
