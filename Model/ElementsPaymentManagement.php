<?php

namespace CityPay\Paylink\Model;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Psr\Log\LoggerInterface;

use CityPay\Api\PaymentIntentApi;
use CityPay\Configuration;
use CityPay\Model\ApiKey;
use CityPay\Model\PaymentIntentRequestModel;
use CityPay\Model\AuthorisePaymentIntentRequestModel;
use CityPay\Model\VerificationRequest;

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

    public function createSession() {
        $this->logger->debug("CityPay:Elements: In createSession(), calling createPaymentSession");
        return json_encode($this->createPaymentSession());
    }

    public function authorise($paymentIntentId) {
        $this->logger->debug("CityPay:Elements: In authorise(), calling authoriseRequest");
        return json_encode($this->authoriseRequest($paymentIntentId));
    }

    public function verify($paymentIntentId) {
        $this->logger->debug("CityPay:Elements: In verify(), calling verifyAuth");
        return json_encode($this->verifyAuth($paymentIntentId));
    }

    private function createPaymentSession()
    {
        $this->logger->debug("Creating payment session");
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

        $amount = (int) number_format((float) $clientSession->getGrandTotal(), 2, '', '');

        $apiInstance = $this->createPaymentIntentApi();
        $paymentIntent = new PaymentIntentRequestModel([
            'merchantid' => (int) $merchantId,
            'identifier' => 'quote-' . $clientSession->getId(),
            'amount' => $amount,
            'currency' => $clientSession->getQuoteCurrencyCode(),
            'bill_to' => [
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
        ]);

        $response = $apiInstance->createPaymentIntent($paymentIntent);
        $responseData = $this->normalisePaymentIntentResponse($response);
        $this->logger->debug("Payment Session Created: ", $responseData);
        $amount = (int) $responseData['context']['amount'];

        return [
            'paymentIntentId' => $responseData['payment_intent_id'],
            'opaqueKey' => $responseData['opaque_key'],
            'sessionToken' => $responseData['session_token'],
            'amount' => $amount,
        ];

    }

    public function authoriseRequest($paymentIntentId) {
        $apiInstance = $this->createPaymentIntentApi();
        $authoriseRequest = new AuthorisePaymentIntentRequestModel([
            'payment_intent_id' => $paymentIntentId,
        ]);

        return $apiInstance->authorisePaymentIntent($authoriseRequest);

    }

    public function verifyAuth($paymentIntentId) {
        $clientId = $this->scopeConfig->getValue(
            'payment/citypay_gateway/client_id',
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

        $host = $testMode ? 'https://sandbox.citypay.com' : 'https://api.citypay.com';
        $apiKey = ApiKey::newKey($clientId, $licenceKey);

        $response = (new \GuzzleHttp\Client())->request(
            'GET',
            $host . '/v6/intent/verify-auth/' . rawurlencode($paymentIntentId),
            [
                'headers' => [
                    'cp-api-key' => $apiKey,
                    'Accept' => 'application/json',
                ],
            ]
        );

        return json_decode((string) $response->getBody(), true);
    }

    // HELPER FUNCTIONS
    private function normalisePaymentIntentResponse($response)
    {
        if (is_array($response)) {
            return $response;
        }

        if ($response instanceof \JsonSerializable) {
            $encoded = json_encode($response->jsonSerialize());

            if ($encoded !== false) {
                $decoded = json_decode($encoded, true);

                if (is_array($decoded)) {
                    return $decoded;
                }
            }
        }

        if (is_object($response) && method_exists($response, '__toString')) {
            $decoded = json_decode((string) $response, true);

            if (is_array($decoded)) {
                return $decoded;
            }
        }

        if (is_string($response)) {
            $decoded = json_decode($response, true);

            if (is_array($decoded)) {
                return $decoded;
            }

            return ['response' => $response];
        }

        return [];
    }

    private function createPaymentIntentApi(): PaymentIntentApi
    {
        $clientId = $this->scopeConfig->getValue(
            'payment/citypay_gateway/client_id',
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

        $apiKey = ApiKey::newKey($clientId, $licenceKey);

        $config = Configuration::getDefaultConfiguration()
            ->setApiKey('cp-api-key', $apiKey)
            ->setHost($testMode ? 'https://sandbox.citypay.com' : 'https://api.citypay.com');

        return new PaymentIntentApi(new \GuzzleHttp\Client(), $config);
    }
}
