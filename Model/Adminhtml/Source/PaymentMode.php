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
class PaymentMode implements \Magento\Framework\Option\ArrayInterface
{
    public const PAYLINK = 'paylink';
    public const ELEMENTS = 'elements';

    public function toOptionArray()
    {
        return [
            [
                'value' => self::PAYLINK,
                'label' => __('Paylink')
            ],
            [
                'value' => self::ELEMENTS,
                'label' => __('Elements')
            ]
        ];
    }
}
