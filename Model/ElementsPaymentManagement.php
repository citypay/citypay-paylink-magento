<?php

namespace CityPay\Paylink\Model;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Store\Model\ScopeInterface;
use Psr\Log\LoggerInterface;

use CityPay\Api\PaymentIntentApi;
use CityPay\Configuration;
use CityPay\Model\ApiKey;
use CityPay\Model\PaymentIntentRequestModel;
use CityPay\Model\AuthorisePaymentIntentRequestModel;
use CityPay\Model\VerificationRequest;
use CityPay\Api\CheckoutContextApi;
use CityPay\Model\createCheckoutContextFn;

class ElementsPaymentManagement implements \CityPay\Paylink\Api\ElementsPaymentManagementInterface {
    private $checkoutSession;
    private $scopeConfig;
    private $logger;
    private $storeManager;

    public function __construct(
        CheckoutSession $checkoutSession,
        ScopeConfigInterface $scopeConfig,
        LoggerInterface $logger,
        StoreManagerInterface $storeManager
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->scopeConfig = $scopeConfig;
        $this->logger = $logger;
        $this->storeManager = $storeManager;
    }

    public function createSession() {
        $this->logger->debug("CityPay:Elements: In createSession(), calling createPaymentSession");
        return json_encode($this->createPaymentSession());
    }

    public function createCheckoutContext() {
        $this->logger->debug("CityPay:Checkout Context: In createCheckoutContext(), calling createCheckoutContext");
        return json_encode($this->createCheckoutContextSession());
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
//        $googlepayMID = (string) $responseData['services']['google_pay']['merchantId'];

        $result = [
            'paymentIntentId' => $responseData['payment_intent_id'],
            'opaqueKey' => $responseData['opaque_key'],
            'sessionToken' => $responseData['session_token'],
            'amount' => $amount,
//            'googlepayMID' => $googlepayMID
        ];

        return $result;

    }

    private function createCheckoutContextSession(): array
    {
        $this->logger->debug('CityPay: creating checkout context session');

        $store = $this->storeManager->getStore();
        $storeId = $order->getStoreId();
        $storeCode = $store->getStore($storeId)->getCode();

        $merchantId = $this->scopeConfig->getValue(
            'payment/citypay_gateway/merchantid',
            ScopeInterface::SCOPE_STORE
        );

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

        $selectedOptions = (string) $this->scopeConfig->getValue(
            'payment/citypay_gateway/checkout_context_payment_option',
            ScopeInterface::SCOPE_STORE
        );

        $selectedOptions = array_filter(
            array_map('trim', explode(',', $selectedOptions))
        );

        $requestedCapabilities = [];

        if (in_array('apple-pay', $selectedOptions, true)) {
            $requestedCapabilities[] = 'apple_pay';
        }

        if (in_array('google-pay', $selectedOptions, true)) {
            $requestedCapabilities[] = 'google_pay';
        }

        $currency = $store->getCurrentCurrencyCode();

        $country = (string) $this->scopeConfig->getValue(
            'general/country/default',
            ScopeInterface::SCOPE_STORE
        );

        $origin = (string) $this->scopeConfig->getValue(
            'payment/citypay_gateway/origin',
            ScopeInterface::SCOPE_STORE
        );

        $host = $testMode ? 'https://sandbox.citypay.com' : 'https://api.citypay.com';

        $apiKey = ApiKey::newKey($clientId, $licenceKey);

        $requestBody = [
            'merchantId' => (int) $merchantId,
            'origin' => $origin,
            'currency' => $currency,
            'country' => $country,
            'requestedCapabilities' => array_values(
                array_unique($requestedCapabilities)
            )
        ];

        $this->logger->debug('CityPay: checkout context request',
            [
                'origin' => $origin,
                'currency' => $currency,
                'country' => $country,
                'requestedCapabilities' => $requestBody['requestedCapabilities']
            ]
        );

        try {
            $response = (new \GuzzleHttp\Client())->request(
                'POST',
                $host . '/v6/checkout/context/create',
                [
                    'headers' => [
                        'cp-api-key' => $apiKey,
                        'Accept' => 'application/json',
                        'Content-Type' => 'application/json'
                    ],
                    'json' => $requestBody
                ]
            );

            $result = json_decode((string) $response->getBody(), true);

            if (!is_array($result)) {
                throw new \RuntimeException('CityPay returned an invalid checkout context response.');
            }

            if (empty($result['checkoutContextId'])) {
                throw new \RuntimeException('CityPay did not return a checkoutContextId.');
            }

            $this->logger->debug('CityPay: checkout context created',
                [
                    'checkoutContextId' => $result['checkoutContextId'],
                    'origin' => $result['origin'] ?? null,
                    'exp' => $result['exp'] ?? null
                ]
            );

            return $result;
        } catch (\Throwable $exception) {
            $this->logger->error('CityPay: checkout context request failed',
                [
                    'message' => $exception->getMessage(),
                    'exception' => $exception
                ]
            );

            throw new \Magento\Framework\Exception\LocalizedException(
                __('Unable to initialise CityPay express checkout.')
            );
        }
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

    private function createCheckoutContextApi(): CheckoutContextApi
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

        $configuration = Configuration::getDefaultConfiguration()
            ->setApiKey('cp-api-key', $apiKey)
            ->setHost(
                $testMode
                    ? 'https://sandbox.citypay.com'
                    : 'https://api.citypay.com'
            );

        return new CheckoutContextApi(
            new \GuzzleHttp\Client(),
            $configuration
        );
    }

}
