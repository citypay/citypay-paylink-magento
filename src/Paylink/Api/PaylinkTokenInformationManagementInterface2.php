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
     * Set getPayLinkToken for specified orderId.
     *
     * @param \Magento\Quote\Api\Data\PaymentInterface $paymentMethod
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     * @return string $json.
     */
    public function getPaylinkToken(
        \Magento\Quote\Api\Data\PaymentInterface $paymentMethod
    );

    /**
     * Set payment information for a specified cart.
     *
     * @param int $cartId
     * @param \Magento\Quote\Api\Data\PaymentInterface $paymentMethod
     * @param \Magento\Quote\Api\Data\AddressInterface|null $billingAddress
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     * @return int Order ID.
     */
    public function savePaymentInformation(
        $cartId,
        \Magento\Quote\Api\Data\PaymentInterface $paymentMethod,
        \Magento\Quote\Api\Data\AddressInterface $billingAddress = null
    );

    /**
     * Get payment information
     *
     * @param int $cartId
     * @return \Magento\Checkout\Api\Data\PaymentDetailsInterface
     */
    public function getPaymentInformation($cartId);
}
