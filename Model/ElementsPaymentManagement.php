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
        $this->logger->debug("CityPay:Elements: In createSession(), calling  createPaymentSession");
        return json_encode($this->createPaymentSession());
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

        $licenceKey = $this->scopeConfig->getValue(
            'payment/citypay_gateway/licencekey',
            ScopeInterface::SCOPE_STORE
        );

        $testMode = (bool) $this->scopeConfig->getValue(
            'payment/citypay_gateway/testmode',
            ScopeInterface::SCOPE_STORE
        );

        $pub_key = $this->scopeConfig->getValue(
            'payment/citypay_gateway/pub_key',
            ScopeInterface::SCOPE_STORE
        );

        $amount = (int) number_format((float) $clientSession->getGrandTotal(), 2, '', '');

        $apiKey = ApiKey::newKey("PC222210", $licenceKey);

        $config = Configuration::getDefaultConfiguration()
            ->setApiKey('cp-api-key', $apiKey)
            ->setHost($testMode ? 'https://sandbox.citypay.com' : 'https://api.citypay.com');

        $apiInstance = new PaymentIntentApi(new \GuzzleHttp\Client(), $config);

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
        $this->logger->debug("Payment intent", $paymentIntent);

        $statusCode = $response['statusCode'] ?? $response['status_code'] ?? null;

        if ($statusCode !== null && ((int) $statusCode < 200 || (int) $statusCode >= 300)) {
            $this->logger->debug(
                'CityPay:Elements:createPaymentSession failed',
                [
                    'status_code' => $statusCode,
                    'response' => $response,
                ]
            );

            throw new \Magento\Framework\Exception\LocalizedException(
                __('CityPay returned an invalid payment session response.')
            );
        }

        $this->logger->debug(
            'CityPay:Elements:createPaymentSession response',
            ['response' => $response]
        );

        return $response;
    }

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

    private function extractPaymentIntentId($response, array $responseData)
    {
        if (is_object($response) && method_exists($response, 'getPaymentIntentId')) {
            return $response->getPaymentIntentId();
        }

        foreach (['paymentIntentId', 'payment_intent_id'] as $key) {
            if (!empty($responseData[$key])) {
                return $responseData[$key];
            }
        }

        if ($response instanceof \ArrayAccess) {
            foreach (['paymentIntentId', 'payment_intent_id'] as $key) {
                if ($response->offsetExists($key) && !empty($response[$key])) {
                    return $response[$key];
                }
            }
        }

        return null;
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
