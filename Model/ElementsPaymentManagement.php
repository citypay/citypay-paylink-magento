<?php

namespace CityPay\Paylink\Model;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Psr\Log\LoggerInterface;

class ElementsPaymentManagement implements \CityPay\Paylink\Api\ElementsPaymentManagementInterface {
    private $checkoutSession;
    private $scopeConfig;
    private $logger;

    public function __construct(
        CheckoutSession $checkoutSession,
        ScopeConfigInterface $scopeConfig,
        LoggerInterface $logger
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->scopeConfig = $scopeConfig;
        $this->logger = $logger;
    }

    public function createSession()
    {
        return json_encode($this->createPaymentSession());
    }

    private function createPaymentSession()
    {
        $clientSession = $this->checkoutSession->getQuote();

        if (!$clientSession || !$clientSession->getId()) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __('Unable to create CityPay payment session: no active quote.')
            );
        }

        $billingAddress = $clientSession->getBillingAddress();

        $merchantId = $this->scopeConfig->getValue(
            'payment/citypay_gateway/merchantid',
            ScopeInterface::SCOPE_STORE
        );

        $licenceKey = $this->scopeConfig->getValue(
            'payment/citypay_gateway/licencekey',
            ScopeInterface::SCOPE_STORE
        );

        $testMode = (bool) $this->scopeConfig->getValue(
            'payment/citypay_gateway/testmode',
            ScopeInterface::SCOPE_STORE
        );

        $pub_key = $this->scopeConfig->getValue(
            'payment/citypay_gateway/pubkey',
            ScopeInterface::SCOPE_STORE
        );

        $amount = (int) number_format((float) $clientSession->getGrandTotal(), 2, '', '');

        $payload = [
            'test' => $testMode,
            'merchantId' => (int) $merchantId,
            'licenceKey' => $licenceKey,
            'pub_key' => $pub_key,
            'identifier' => 'quote-' . $clientSession->getId(),
            'amount' => $amount,
            'currency' => $clientSession->getQuoteCurrencyCode(),
            'cardholder' => [
                'email' => $clientSession->getCustomerEmail(),
                'firstName' => $billingAddress ? $billingAddress->getFirstname() : null,
                'lastName' => $billingAddress ? $billingAddress->getLastname() : null,
                'address' => [
                    'address1' => $billingAddress ? ($billingAddress->getStreet()[0] ?? null) : null,
                    'address2' => $billingAddress ? ($billingAddress->getStreet()[1] ?? null) : null,
                    'area' => $billingAddress ? $billingAddress->getCity() : null,
                    'postcode' => $billingAddress ? $billingAddress->getPostcode() : null,
                    'country' => $billingAddress ? $billingAddress->getCountryId() : null,
                ],
            ],
        ];

        $response = $this->postJson(
            "https://api.citypay.com/intent/create",
            $payload
        );

        if (empty($response['paymentIntentId'])) {
            $this->logger->error('CityPay Elements session response missing paymentIntentId', $response);

            throw new \Magento\Framework\Exception\LocalizedException(
                __('Unable to create CityPay payment session.')
            );
        }
        return $response;
    }

    private function postJson($url, array $payload)
    {
        $this->logger->debug('CityPay:Elements:createPaymentSession request ' . json_encode($payload));

        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $rawResponse = curl_exec($ch);
        $curlError = curl_error($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        if ($rawResponse === false) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __('CityPay request failed: %1', $curlError)
            );
        }

        $this->logger->debug('CityPay:Elements:createPaymentSession response ' . $rawResponse);

        $decoded = json_decode($rawResponse, true);

        if ($statusCode < 200 || $statusCode >= 300 || !is_array($decoded)) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __('CityPay returned an invalid payment session response.')
            );
        }

        return $decoded;
    }

    public function authorise()
    {
        return json_encode([
            'success' => false,
            'message' => 'CityPay Elements authorise is not implemented.',
        ]);
    }

    public function verifyAuth()
    {
        return json_encode([
            'success' => false,
            'message' => 'CityPay Elements verifyAuth is not implemented.',
        ]);
    }
}
