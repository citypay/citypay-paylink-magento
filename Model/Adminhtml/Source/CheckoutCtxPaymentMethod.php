<?php
/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace CityPay\Paylink\Model\Adminhtml\Source;

use Magento\Framework\Option\ArrayInterface;

/**
 * Class PaymentMode
 */
class CheckoutCtxPaymentMethod implements \Magento\Framework\Option\ArrayInterface
{
    public const APPLEPAY = 'apple-pay';
    public const ELEMENTS = 'elements';
    public const GOOGLEPAY = 'google-pay';

    public function toOptionArray()
    {
        return [
            [
                'value' => self::APPLEPAY,
                'label' => __('Apple Pay')
            ],
            [
                'value' => self::ELEMENTS,
                'label' => __('Elements (upcoming)')
            ],
            [
                'value' => self::GOOGLEPAY,
                'label' => __('Google Pay (upcoming)')
            ]
        ];
    }
}
