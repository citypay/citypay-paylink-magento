<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace CityPay\Paylink\Model;

use Magento\Framework\Exception\CouldNotSaveException;
use mysql_xdevapi\Exception;
use Magento\Payment\Gateway\Http\TransferInterface;
use Magento\Framework\Webapi\Rest\Request as RestRequest;

/**
 * Payment information management
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class PaylinkTokenInformationManagement implements \CityPay\Paylink\Api\PaylinkTokenInformationManagementInterface2
{

    protected $_logger;
    /**
     * @var \Magento\Quote\Api\BillingAddressManagementInterface
     * @deprecated 100.1.0 This call was substituted to eliminate extra quote::save call
     */
    protected $billingAddressManagement;

    /**
     * @var \Magento\Quote\Api\PaymentMethodManagementInterface
     */
    protected $paymentMethodManagement;

    /**
     * @var \Magento\Quote\Api\CartManagementInterface
     */
    protected $cartManagement;

    /**
     * @var PaymentDetailsFactory
     */
    protected $paymentDetailsFactory;

    /**
     * @var \Magento\Quote\Api\CartTotalRepositoryInterface
     */
    protected $cartTotalsRepository;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;

    /**
     * @var \Magento\Quote\Api\CartRepositoryInterface
     */
    private $cartRepository;

    /**
     * @var \Magento\Sales\Api\OrderRepositoryInterface $orderRepository
     */
    private $orderRepository;

    /**
     * @var \Magento\Payment\Gateway\Http\TransferBuilder $transferBuilder
     */
    private $transferBuilder;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     */
    private $scopeConfig;

    /**
     * @var \Magento\Framework\Webapi\Rest\Request
     */
    protected $_request;


    /**
     * @param \Magento\Quote\Api\BillingAddressManagementInterface $billingAddressManagement
     * @param \Magento\Quote\Api\PaymentMethodManagementInterface $paymentMethodManagement
     * @param \Magento\Quote\Api\CartManagementInterface $cartManagement
     * @param PaymentDetailsFactory $paymentDetailsFactory
     * @param \Magento\Quote\Api\CartTotalRepositoryInterface $cartTotalsRepository
     * @param \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
     * @param \Magento\Payment\Gateway\Http\TransferBuilder $transferBuilder,
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Psr\Log\LoggerInterface $logger
     * @param RestRequest $request
     * @codeCoverageIgnore
     */
    public function __construct(
        \Magento\Quote\Api\BillingAddressManagementInterface $billingAddressManagement,
        \Magento\Quote\Api\PaymentMethodManagementInterface $paymentMethodManagement,
        \Magento\Quote\Api\CartManagementInterface $cartManagement,
        \Magento\Checkout\Model\PaymentDetailsFactory $paymentDetailsFactory,
        \Magento\Quote\Api\CartTotalRepositoryInterface $cartTotalsRepository,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \Magento\Payment\Gateway\Http\TransferBuilder $transferBuilder,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Psr\Log\LoggerInterface $logger,
        RestRequest $request
    ) {
        $this->billingAddressManagement = $billingAddressManagement;
        $this->paymentMethodManagement = $paymentMethodManagement;
        $this->cartManagement = $cartManagement;
        $this->paymentDetailsFactory = $paymentDetailsFactory;
        $this->cartTotalsRepository = $cartTotalsRepository;
        $this->orderRepository=$orderRepository;
        $this->transferBuilder=$transferBuilder;
        $this->scopeConfig=$scopeConfig;
        $this->logger=$logger;
        $this->_request=$request;
        $this->logger->debug('PaylinkTokenInformationManagement constructor');
    }
/*
    private function getArgs($quote)
    {
        [
            'TXN_TYPE' => 'A',
            'INVOICE' => $quote->getOrderIncrementId(),
            'AMOUNT' => $order->getGrandTotalAmount(),
            'CURRENCY' => $order->getCurrencyCode(),
            'EMAIL' => $address->getEmail(),
            'MERCHANT_KEY' => $this->config->getValue(
                'merchant_gateway_key',
                $order->getStoreId()
            )



        ];
    }
*/
    /**
     * @inheritdoc
     */
    public function getPaylinkToken(
        \Magento\Quote\Api\Data\PaymentInterface $paymentMethod

    ) {
        $this->logger->debug('PaylinkTokenInformationManagement getPaylinkToken');

        try {

            $payment = $paymentMethod;
            $request = $this->buildRequestData($payment);
            $transfer = $this->create($request);
            $response = $this->placeRequest($transfer);
            $this->logger->debug('PaylinkTokenInformationManagement getPaylinkToken '.$response);

            return $response;
            //return response
            /*
            $responseJson=json_decode($response);
            if ($responseJson->result==1){

            }
            */
        }
        catch (Exception $ex){
            $this->logger->error($ex->getMessage());
        }

        /*
        //subscribe to the event sales_order_payment_place_start
        $this->savePaymentInformation($cartId, $paymentMethod, $billingAddress);
        try {
            $orderId = $this->cartManagement->placeOrder($cartId,$paymentMethod);
            / *
            $quote = $this->quoteRepository->getActive($cartId);
            $quote->reserveOrderId();
            $args=getArgs($quote);
            * /
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            throw new CouldNotSaveException(
                __($e->getMessage()),
                $e
            );
        } catch (\Exception $e) {
            $this->getLogger()->critical($e);
            throw new CouldNotSaveException(
                __('A server error stopped your order from being placed. Please try to place your order again.'),
                $e
            );
        }

        //unsubscribe to the event sales_order_payment_place_start
        // need to return the redirect here

        return $orderId;
        */
    }
    /**
     * @inheritdoc
     */
    public function processPaylinkPostback(
  #      \CityPay\Paylink\Api\Data\PaylinkPostbackInterface $postbackInfo

    ) {
        $this->logger->debug('PaylinkTokenInformationManagement processPaylinkPostback ');
        $this->logger->debug($this->_request->getContent());
     #   $content=json_decode($this->_request->getContent());
     #   $body=json_decode($this->_request->getBody());
     #   $this->logger->debug(json(encode(body)) );
    }
    public function buildRequestData($payment)
    {
        /*
        if (!isset($buildSubject['payment'])
            || !$buildSubject['payment'] instanceof PaymentDataObjectInterface
        ) {
            throw new \InvalidArgumentException('Payment data object should be provided');
        }
        /  ** @var PaymentDataObjectInterface $payment * /
        $payment = $buildSubject['payment'];
        */
        $ad=$payment->getAdditionalData();
        $this->logger->debug('PaylinkTokenInformationManagement getPaylinkToken' . json_encode($ad));
        $orderId=$ad['orderId'];
        $order=$this->orderRepository->get($orderId);
        //$order = $payment->getOrder();
        $this->logger->debug('PaylinkTokenInformationManagement getPaylinkToken' . json_encode($order));
        $path = 'payment/sample_gateway/title';
        $value = $this->scopeConfig->getValue($path, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        $this->logger->debug('getenv='.json_encode(getenv()));
        $this->logger->debug('_ENV='.json_encode($_ENV));
        #$merchantId=$this->scopeConfig
        //$address = $order->getShippingAddress();
        return [
            'test'=>TRUE,
            'identifier' => $order->getData('increment_id'),
            'amount' => (int)(floatval($order->getData('grand_total'))*100), //'total_due'
            'merchantId'=>64215680,
            'licenceKey'=>'HKRW6442A025GEF0',
            'postback'=>getenv('NGROK_URL').'/magento/rest/V1/paylink/processAuthResponse'

            /*        'MERCHANT_KEY' => $this->config->getValue(
                        'merchant_gateway_key',
                        $order->getStoreId()
                    )
            */
        ];
    }
    /**
     * Builds gateway transfer object
     *
     * @param array $request
     * @return TransferInterface
     */
    public function create(array $request)
    {
        $this->logger->debug(json_encode($request));
        return $this->transferBuilder
            ->setBody($request)
            ->setMethod('POST')
            ->setHeaders(array('content-type:application/json') )
            ->setUri('https://secure.citypay.com/paylink3/create')
            ->build();
    }

    /**
     * Places request to gateway. Returns result as ENV array
     *
     * @param TransferInterface $transferObject
     * @return array
     */
    public function placeRequest(TransferInterface $transferObject)
    {
        $this->logger->debug(json_encode($transferObject));
        $ch=curl_init($transferObject->getUri());
        curl_setopt($ch,CURLOPT_POST,$transferObject->getMethod()=='POST');
        curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($transferObject->getBody()));
        curl_setopt($ch,CURLOPT_HTTPHEADER,$transferObject->getHeaders());
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response=curl_exec($ch);
        curl_close($ch);
        $this->logger->debug(json_encode($response));
        return $response;
    }


    /**
     * @inheritdoc
     */
    public function savePaymentInformation(
        $cartId,
        \Magento\Quote\Api\Data\PaymentInterface $paymentMethod,
        \Magento\Quote\Api\Data\AddressInterface $billingAddress = null
    ) {
        if ($billingAddress) {
            /** @var \Magento\Quote\Api\CartRepositoryInterface $quoteRepository */
            $quoteRepository = $this->getCartRepository();
            /** @var \Magento\Quote\Model\Quote $quote */
            $quote = $quoteRepository->getActive($cartId);
            $customerId = $quote->getBillingAddress()
                ->getCustomerId();
            if (!$billingAddress->getCustomerId() && $customerId) {
                //It's necessary to verify the price rules with the customer data
                $billingAddress->setCustomerId($customerId);
            }
            $quote->removeAddress($quote->getBillingAddress()->getId());
            $quote->setBillingAddress($billingAddress);
            $quote->setDataChanges(true);
            $shippingAddress = $quote->getShippingAddress();
            if ($shippingAddress && $shippingAddress->getShippingMethod()) {
                $shippingRate = $shippingAddress->getShippingRateByCode($shippingAddress->getShippingMethod());
                $shippingAddress->setLimitCarrier(
                    $shippingRate ? $shippingRate->getCarrier() : $shippingAddress->getShippingMethod()
                );
            }
        }
        $this->paymentMethodManagement->set($cartId, $paymentMethod);
        return true;
    }

    /**
     * @inheritdoc
     */
    public function getPaymentInformation($cartId)
    {
        /** @var \Magento\Checkout\Api\Data\PaymentDetailsInterface $paymentDetails */
        $paymentDetails = $this->paymentDetailsFactory->create();
        $paymentDetails->setPaymentMethods($this->paymentMethodManagement->getList($cartId));
        $paymentDetails->setTotals($this->cartTotalsRepository->get($cartId));
        return $paymentDetails;
    }

    /**
     * Get logger instance
     *
     * @return \Psr\Log\LoggerInterface
     * @deprecated 100.1.8
     */
    private function getLogger()
    {
        if (!$this->logger) {
            $this->logger = \Magento\Framework\App\ObjectManager::getInstance()->get(\Psr\Log\LoggerInterface::class);
        }
        return $this->logger;
    }

    /**
     * Get Cart repository
     *
     * @return \Magento\Quote\Api\CartRepositoryInterface
     * @deprecated 100.2.0
     */
    private function getCartRepository()
    {
        if (!$this->cartRepository) {
            $this->cartRepository = \Magento\Framework\App\ObjectManager::getInstance()
                ->get(\Magento\Quote\Api\CartRepositoryInterface::class);
        }
        return $this->cartRepository;
    }
}
