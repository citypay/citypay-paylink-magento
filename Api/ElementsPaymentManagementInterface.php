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
     * @return string
     */
    public function createCheckoutContext();

    /**
     * @param string $paymentIntentId
     * @return string
     */
    public function authorise($paymentIntentId);


    /**
     * @param string $paymentIntentId
     * @return string
     */
    public function verify($paymentIntentId);
}
