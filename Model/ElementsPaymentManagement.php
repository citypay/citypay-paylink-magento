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
use CityPay\Paylink\Model\Elements\MinorUnitConverter;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;

class ElementsPaymentManagement implements \CityPay\Paylink\Api\ElementsPaymentManagementInterface {
    private const AUTH_TRANSACTION_KEY = 'citypay_elements_authorised_transaction';
    private const VERIFIED_TRANSACTION_KEY = 'citypay_elements_verified_transaction';

    private $checkoutSession;
    private $scopeConfig;
    private $logger;
    private $minorUnitConverter;
    private $cartRepository;
    private $orderRepository;
    private $orderSender;

    public function __construct(
        CheckoutSession $checkoutSession,
        ScopeConfigInterface $scopeConfig,
        LoggerInterface $logger,
        MinorUnitConverter $minorUnitConverter,
        CartRepositoryInterface $cartRepository,
        OrderRepositoryInterface $orderRepository,
        OrderSender $orderSender
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->scopeConfig = $scopeConfig;
        $this->logger = $logger;
        $this->minorUnitConverter = $minorUnitConverter;
        $this->cartRepository = $cartRepository;
        $this->orderRepository = $orderRepository;
        $this->orderSender = $orderSender;
    }

    public function createSession() {
        $this->logger->debug("CityPay:Elements: In createSession(), calling createPaymentSession");
        return json_encode($this->createPaymentSession());
    }

    public function authorise($paymentIntentId, $orderId) {
        $this->logger->debug("CityPay:Elements: In authorise(), calling authoriseRequest");

        // Verify this is the correct order
        $order = $this->validateOrderIntent($paymentIntentId, $orderId);
        $this->logger->debug("CityPay:Elements: order validated");

        // Authorise payment
        $result = $this->normalisePaymentIntentResponse(
            $this->authoriseRequest($paymentIntentId)
        );

        if ($this->isAuthorised($result)) {
            $this->logger->debug("CityPay:Elements: payment authorised, updating order");
            $this->registerAuthorisation($order, $result);
        } else {
            $this->handleDeclineCancel($order, $result);
        }

        return json_encode($result);
    }

    public function verify($paymentIntentId, $orderId) {
        $this->logger->debug("CityPay:Elements: In verify(), calling verifyAuth");

        // Verify this is the correct order
        $order = $this->validateOrderIntent($paymentIntentId, $orderId);
        $this->logger->debug("CityPay:Elements: validated order intent");

        // Verify payment
        $result = $this->verifyAuth($paymentIntentId);

        if (!is_array($result)) {
            throw new LocalizedException(__('CityPay returned an invalid verification response.'));
        }

        $approved =
            ($result['result'] ?? null) === 'Accepted' && (int) ($result['result_id'] ?? 0) === 1
            && in_array(strtoupper((string) ($result['trans_status'] ?? '')),
                ['OPEN', 'O'],
                true
            );

        if (!$approved) {
            $this->handleDeclineCancel($order, $result);

            throw new LocalizedException(__('The CityPay payment could not be verified.'));
        }

        $response = [
            'approved' => true,
            'result' => (string) $result['result'],
            'resultId' => (int) $result['result_id'],
            'transactionStatus' => (string) $result['trans_status'],
            'transactionNumber' => (int) ($result['transno'] ?? 0),
        ];

        $this->registerVerifiedPayment($order, $result);
        $this->logger->debug('CityPay payment verified', $response);

        return json_encode($response);
    }

    private function createPaymentSession() {
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

        $currency = (string) $clientSession->getQuoteCurrencyCode();
        $amount = $this->minorUnitConverter->toMinorUnits(
            $clientSession->getGrandTotal(),
            $currency
        );

        $apiInstance = $this->createPaymentIntentApi();
        $paymentIntent = new PaymentIntentRequestModel([
            'merchantid' => (int) $merchantId,
            'identifier' => 'quote-' . $clientSession->getId(),
            'amount' => $amount,
            'currency' => $currency,
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
        $this->logger->debug("Payment Session Created: ");

        $responseAmount = (int) $responseData['context']['amount'];
        $responseCurrency = strtoupper((string) $responseData['context']['currency']);
        $googlepayMID = (string) $responseData['services']['google_pay']['merchantId'];

        // Ensure CityPay created the intent for the amount we requested.
        if ($responseAmount !== $amount) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __('CityPay returned an unexpected payment amount.')
            );
        }

        if ($responseCurrency !== $currency) {
            throw new LocalizedException(
                __('CityPay returned an unexpected currency.')
            );
        }

        // After CityPay SDK returns the intent, store the payment intent id, amount, currency and quote id against the quote payment:
        $quotePayment = $clientSession->getPayment();

        $quotePayment->setAdditionalInformation(
            'citypay_elements_intent_id',
            $responseData['payment_intent_id']
        );
        $quotePayment->setAdditionalInformation(
            'citypay_elements_intent_amount',
            $amount
        );
        $quotePayment->setAdditionalInformation(
            'citypay_elements_intent_currency',
            $currency
        );
        $quotePayment->setAdditionalInformation(
            'citypay_elements_quote_id',
            (int) $clientSession->getId()
        );

        // Persist the updated quote and its payment information.
        $this->cartRepository->save($clientSession);

        $result = [
            'paymentIntentId' => $responseData['payment_intent_id'],
            'opaqueKey' => $responseData['opaque_key'],
            'sessionToken' => $responseData['session_token'],
            'amount' => $responseAmount,
            'googlepayMID' => $googlepayMID
        ];

        return $result;

    }

    /**
     * @param string $paymentIntentId
     * @return string
     */
    private function authoriseRequest($paymentIntentId) {
        $apiInstance = $this->createPaymentIntentApi();
        $authoriseRequest = new AuthorisePaymentIntentRequestModel([
            'payment_intent_id' => $paymentIntentId,
        ]);

        return $apiInstance->authorisePaymentIntent($authoriseRequest);

    }

    /**
     * @param string $paymentIntentId
     * @return string
     */
    private function verifyAuth($paymentIntentId) {
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
    private function normalisePaymentIntentResponse($response) {
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

    private function createPaymentIntentApi(): PaymentIntentApi {
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

    private function isAuthorised(array $result): bool {
        return filter_var(
            $result['authorised'] ?? false,
            FILTER_VALIDATE_BOOLEAN
        );
    }

    private function registerAuthorisation($order, array $result): void {
        $transactionNumber = $this->getTransactionNumber($result);
        $payment = $order->getPayment();
        $registeredTransaction = (string) $payment->getAdditionalInformation(
            self::AUTH_TRANSACTION_KEY
        );

        if ($registeredTransaction === $transactionNumber) {
            return;
        }

        if ($registeredTransaction !== '') {
            throw new LocalizedException(
                __('A different CityPay transaction is already registered for this order.')
            );
        }

        $payment->setTransactionId($transactionNumber);
        $payment->setIsTransactionClosed(false);
        $payment->registerAuthorizationNotification(
            (float) $order->getBaseGrandTotal()
        );
        $payment->setAdditionalInformation(
            self::AUTH_TRANSACTION_KEY,
            $transactionNumber
        );
        $payment->setAdditionalInformation(
            'citypay_elements_transaction_status',
            (string) ($result['trans_status'] ?? '')
        );

        $order->addCommentToStatusHistory(
            __('CityPay transaction %1 was authorised.', $transactionNumber)
        );
        $this->orderRepository->save($order);
    }

    private function registerVerifiedPayment($order, array $result): void {
        $transactionNumber = $this->getTransactionNumber($result);
        $payment = $order->getPayment();
        $verifiedTransaction = (string) $payment->getAdditionalInformation(
            self::VERIFIED_TRANSACTION_KEY
        );

        if ($verifiedTransaction === $transactionNumber) {
            return;
        }

        if ($verifiedTransaction !== '') {
            throw new LocalizedException(
                __('A different CityPay payment is already verified for this order.')
            );
        }

        $this->registerAuthorisation($order, $result);

        $payment->registerCaptureNotification(
            (float) $order->getBaseGrandTotal()
        );
        $payment->setAdditionalInformation(
            self::VERIFIED_TRANSACTION_KEY,
            $transactionNumber
        );
        $payment->setAdditionalInformation(
            'citypay_elements_result',
            (string) ($result['result'] ?? '')
        );

        $order->addCommentToStatusHistory(
            __('CityPay transaction %1 was verified and captured.', $transactionNumber)
        );
        $this->orderRepository->save($order);

        if (!$order->getEmailSent()) {
            $order->setCanSendNewEmailFlag(true);

            try {
                if (!$this->orderSender->send($order, true)) {
                    $this->logger->warning(
                        'Magento did not send the verified CityPay order email.',
                        ['orderId' => $order->getEntityId()]
                    );
                }
            } catch (\Throwable $exception) {
                // Payment has already been safely recorded. An email problem
                // must not turn a successful verification into a checkout
                // failure or encourage the customer to retry payment.
                $this->logger->error(
                    'Unable to send the verified CityPay order email: '
                    . $exception->getMessage(),
                    ['orderId' => $order->getEntityId()]
                );
            }
        }
    }

    private function getTransactionNumber(array $result): string {
        $transactionNumber = (string) ($result['transno'] ?? '');

        if (!preg_match('/^[0-9]+$/', $transactionNumber)
            || (int) $transactionNumber <= 0
        ) {
            throw new LocalizedException(
                __('CityPay did not return a valid transaction number.')
            );
        }

        return $transactionNumber;
    }

    private function validateOrderIntent($paymentIntentId, $orderId) {
        $order = $this->orderRepository->get((int) $orderId);
        $payment = $order->getPayment();

        if ((int) $this->checkoutSession->getLastOrderId() !== (int) $order->getEntityId()) {
            throw new LocalizedException(
                __('The order does not belong to this checkout session.')
            );
        }

        if ($payment->getMethod() !== 'citypay_gateway') {
            throw new LocalizedException(
                __('The order does not use CityPay.')
            );
        }

        $storedIntentId = (string) $payment->getAdditionalInformation(
            'citypay_elements_intent_id'
        );

        if (!$storedIntentId || !hash_equals($storedIntentId, (string) $paymentIntentId)) {
            throw new LocalizedException(
                __('The payment intent does not belong to this order.')
            );
        }

        $storedQuoteId = (int) $payment->getAdditionalInformation(
            'citypay_elements_quote_id'
        );

        if ($storedQuoteId !== (int) $order->getQuoteId()) {
            throw new LocalizedException(
                __('The payment intent belongs to a different quote.')
            );
        }

        $currency = strtoupper((string) $order->getOrderCurrencyCode());

        $amount = $this->minorUnitConverter->toMinorUnits(
            $order->getGrandTotal(),
            $currency
        );

        $storedAmount = (int) $payment->getAdditionalInformation(
            'citypay_elements_intent_amount'
        );

        $storedCurrency = strtoupper(
            (string) $payment->getAdditionalInformation(
                'citypay_elements_intent_currency'
            )
        );

        if ($storedAmount !== $amount || $storedCurrency !== $currency) {
            throw new LocalizedException(
                __('The CityPay payment amount no longer matches the order.')
            );
        }

        return $order;
    }

    private function handleDeclineCancel($order, array $result = []): void {
        $payment = $order->getPayment();
        $registeredAuth = (string) $payment->getAdditionalInformation(self::AUTH_TRANSACTION_KEY);
        $registeredVerified = (string) $payment->getAdditionalInformation(self::VERIFIED_TRANSACTION_KEY);

        if ($registeredAuth !== '' || $registeredVerified !== '') {
            $this->logger->info('Ignoring decline: order already has a registered CityPay transaction.',
                [
                    'orderId' => $order->getEntityId(),
                    'transno' => $result['transno'] ?? null
                ]);
            return;
        }

        if ($order->canCancel()) {
            try {
                $order->cancel();
            } catch (\Throwable $e) {
                $this->logger->error('Unable to cancel order after CityPay decline: ' . $e->getMessage(), ['orderId' => $order->getEntityId()]);
            }

            $order->addCommentToStatusHistory(__('CityPay payment was declined.'));
            $this->orderRepository->save($order);
            $this->logger->info('Order cancelled due to CityPay decline.', ['orderId' => $order->getEntityId()]);
            return;
        }

        $this->logger->info('CityPay decline received but order is not cancelable; leaving order unchanged.', ['orderId' => $order->getEntityId()]);
    }
}
