<?php

namespace CityPay\Paylink\Block\Product;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\ScopeInterface;

class ExpressCheckout extends Template
{
    private const CONFIG_ACTIVE =
        'payment/citypay_gateway/active';

    private const CONFIG_PAYMENT_MODE =
        'payment/citypay_gateway/payment_mode';

    private const CONFIG_CHECKOUT_CONTEXT =
        'payment/citypay_gateway/checkout_context';

    private const CONFIG_PAYMENT_OPTIONS =
        'payment/citypay_gateway/checkout_context_payment_option';

}