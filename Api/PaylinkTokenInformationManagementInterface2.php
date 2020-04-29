<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace CityPay\Paylink\Api;

/**
 * Interface for managing quote payment information
 * @api
 * @since 100.0.2
 */
interface PaylinkTokenInformationManagementInterface2
{
    /**
     * Get PayLinkToken for specified orderId.
     *
     * @param \Magento\Quote\Api\Data\PaymentInterface $paymentMethod
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     * @return string $json.
     */
    public function getPaylinkToken(
        \Magento\Quote\Api\Data\PaymentInterface $paymentMethod
    );

    /**
     * process a PaylinkPostback for previous request.
     *
     * @param \CityPay\Paylink\Api\Data\PaylinkPostbackInterface
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     * @return string $data
     */
    public function processPaylinkPostback();


}
