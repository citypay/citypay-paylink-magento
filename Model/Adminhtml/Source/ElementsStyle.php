<?php
/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace CityPay\Paylink\Model\Adminhtml\Source;

use Magento\Framework\Option\ArrayInterface;

/**
 * Class ElementsStyle
 */
class ElementsStyle implements \Magento\Framework\Option\ArrayInterface
{
    public const ROW = 'row';
    public const STACK = 'stack';
    public const ROWMINIMAL = 'row-minimal';
    public const ROWCOMPACT = 'row-compact';
    public const COLUMNCOMPACT = 'column-compact';
    public const COLUMN = 'column';

    public function toOptionArray()
    {
        return [
            [
                'value' => self::ROW,
                'label' => __('Row')
            ],
            [
                'value' => self::STACK,
                'label' => __('Stack')
            ],
            [
                'value' => self::ROWMINIMAL,
                'label' => __('Row Minimal')
            ],
            [
                'value' => self::ROWCOMPACT,
                'label' => __('Row Compact')
            ],
            [
                'value' => self::COLUMNCOMPACT,
                'label' => __('Column Compact')
            ],
            [
                'value' => self::COLUMN,
                'label' => __('Column')
            ]
        ];
    }
}
