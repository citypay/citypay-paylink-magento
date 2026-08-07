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
class CheckoutCtxEnabledOptions implements \Magento\Framework\Option\ArrayInterface
{
    public const ENABLED = 'enabled';
    public const DISABLED = 'disabled';

    public function toOptionArray()
    {
        return [
            [
                'value' => self::ENABLED,
                'label' => __('Enabled')
            ],
            [
                'value' => self::DISABLED,
                'label' => __('Disabled')
            ],
        ];
    }
}
