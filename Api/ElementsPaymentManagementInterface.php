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
    public function authorise();

    /**
     * @return string
     */
    public function verifyAuth();
}
