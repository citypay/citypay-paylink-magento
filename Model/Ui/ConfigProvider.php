<?php

/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace CityPay\Paylink\Model\Ui;

use Magento\Checkout\Model\ConfigProviderInterface;
use CityPay\Paylink\Gateway\Http\Client\ClientMock;
use Magento\Framework\App\Config\ScopeConfigInterface; //allow the class to read Magento configuration values
use Magento\Store\Model\ScopeInterface; //used to say the config should be read at the store scope

/**
 * Class ConfigProvider
 */
final class ConfigProvider implements ConfigProviderInterface
{
    const CODE = 'citypay_gateway';

    private $scopeConfig;

    public function __construct(ScopeConfigInterface $scopeConfig)
    {
        $this->scopeConfig = $scopeConfig;
    }
    /**
     * Retrieve assoc array of checkout configuration
     *
     * @return array
     */
    public function getConfig()
    {
        return [
            'payment' => [
                self::CODE => [
                    'transactionResults' => [
                        1 => __('Success'),
                        0 => __('Fraud')
                    ],
                    'paymentMode' => $this->scopeConfig->getValue(
                        'payment/citypay_gateway/payment_mode',
                        ScopeInterface::SCOPE_STORE //means Magento should read the setting for the current store view / store scope.
                    ),
                    'merchantId' => $this->scopeConfig->getValue(
                        'payment/citypay_gateway/merchantid',
                        ScopeInterface::SCOPE_STORE
                    ),
                    'elementsStyle' => $this->scopeConfig->getValue(
                        'payment/citypay_gateway/elements_style',
                        ScopeInterface::SCOPE_STORE
                    ),
                    'pubKey' => $this->scopeConfig->getValue(
                        'payment/citypay_gateway/pub_key',
                        ScopeInterface::SCOPE_STORE
                    ),
                    'clientId' => $this->scopeConfig->getValue(
                        'payment/citypay_gateway/client_id',
                        ScopeInterface::SCOPE_STORE
                    ),
                ]
            ]
        ];
    }
}
