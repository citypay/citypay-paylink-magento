<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace CityPay\Paylink\Model;

use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Sales\Model\Order;
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
     * @var \Magento\Framework\Stdlib\CookieManagerInterface
     */
    protected $_cookieManager;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * @var \Magento\Framework\Api\SearchCriteriaBuilder
     */
    protected  $searchCriteriaBuilder;

    private $licence_key;

    /**
     * @param \Magento\Quote\Api\BillingAddressManagementInterface $billingAddressManagement
     * @param \Magento\Quote\Api\PaymentMethodManagementInterface $paymentMethodManagement
     * @param \Magento\Quote\Api\CartManagementInterface $cartManagement
     * @param PaymentDetailsFactory $paymentDetailsFactory
     * @param \Magento\Quote\Api\CartTotalRepositoryInterface $cartTotalsRepository
     * @param \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
     * @param \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder
     * @param \Magento\Payment\Gateway\Http\TransferBuilder $transferBuilder,
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
     * @param \Psr\Log\LoggerInterface $logger,
     * @param RestRequest $request,
     * @param \Magento\Framework\Stdlib\CookieManagerInterface $cookieManager,
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager,
     * @codeCoverageIgnore
     */
    public function __construct(
        \Magento\Quote\Api\BillingAddressManagementInterface $billingAddressManagement,
        \Magento\Quote\Api\PaymentMethodManagementInterface $paymentMethodManagement,
        \Magento\Quote\Api\CartManagementInterface $cartManagement,
        \Magento\Checkout\Model\PaymentDetailsFactory $paymentDetailsFactory,
        \Magento\Quote\Api\CartTotalRepositoryInterface $cartTotalsRepository,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder,
        \Magento\Payment\Gateway\Http\TransferBuilder $transferBuilder,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Psr\Log\LoggerInterface $logger,
        RestRequest $request,
        \Magento\Framework\Stdlib\CookieManagerInterface $cookieManager,
        \Magento\Store\Model\StoreManagerInterface $storeManager
    ) {
        $this->billingAddressManagement = $billingAddressManagement;
        $this->paymentMethodManagement = $paymentMethodManagement;
        $this->cartManagement = $cartManagement;
        $this->paymentDetailsFactory = $paymentDetailsFactory;
        $this->cartTotalsRepository = $cartTotalsRepository;
        $this->orderRepository=$orderRepository;
        $this->searchCriteriaBuilder=$searchCriteriaBuilder;
        $this->transferBuilder=$transferBuilder;
        $this->scopeConfig=$scopeConfig;
        $this->logger=$logger;
        $this->_request=$request;
        $this->_cookieManager=$cookieManager;
        $this->_storeManager=$storeManager;
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

     * @param $postback_data

     * @return bool

     * @throws Exception

     */

    public function validatePostbackDigest($postback_data)

    {

        $this->logger->debug('validatePostbackData()');

        $this->logger->debug(json_encode($postback_data));

        $this->logger->debug('Pre concatx');

        $hash_src = $postback_data->authcode .
            $postback_data->amount .
            $postback_data->errorcode .
            $postback_data->merchantid .
            $postback_data->transno .
            $postback_data->identifier .
            $this->licence_key;

        $this->logger->debug('Done concat');
        // Check both the sha256 hash values to ensure that results have not
        // been tampered with

        $check = base64_encode(hash('sha256', $hash_src, true));

        if (strcmp($postback_data->sha256, $check) != 0) {

            $this->logger->debug('Digest mismatch');

            throw new Exception('Digest mismatch');

        }

        $this->logger->info('Postback data is valid, digest matched "' . $check . '"');

        return true;    // Hash values match expected value

    }

    private function getOrderFromIncId($incrementId)
    {
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('increment_id', $incrementId, 'eq')->create();
        $this->logger->debug('made criteria');
        $searchResult = $this->orderRepository->getList($searchCriteria);
        $this->logger->debug('searched');
        if ($searchResult != null) {
            $searchItems = $searchResult->getItems();
            $this->logger->debug('gotitems');
            $this->logger->debug(sizeof($searchItems));
            $this->logger->debug(json_encode($searchItems));
            /*
            $this->logger->debug('search result count=' . str(count($searchItems)));
            if (count($searchItems) == 1) {
                $order = $searchItems[0];
            }
            */
            foreach ($searchItems as $item) {
                $this->logger->debug('item');
                $arrItems['items'][] = $item->getData();
            }
            $this->logger->debug(json_encode($arrItems));

            if (sizeof($searchItems)==1)
            {
                $order=$searchItems[key($searchItems)];
                $this->logger->debug(gettype($order));
            }
        }
        else
            $this->logger->debug('search result null');
        //$searchResult->

    }
    /**
     * @inheritdoc
     */
    public function processPaylinkPostback(
  #      \CityPay\Paylink\Api\Data\PaylinkPostbackInterface $postbackInfo

    ) {
        $this->logger->debug('PaylinkTokenInformationManagement processPaylinkPostback');
        $postbackString=$this->_request->getContent();
        $postbackData=json_decode($postbackString);
        try {
            $path = 'payment/sample_gateway/licencekey';
            $this->licence_key = $this->scopeConfig->getValue($path, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
            $this->logger->debug($this->licence_key);
            if ($this->validatePostbackDigest($postbackData)) {
                $this->logger->debug('good PostbackDigest');
                $incrementId = $postbackData->identifier;
                $order=$this->getOrderFromIncId($incrementId);
            }
            else
                $this->logger->debug('invalid postback');
        }
        catch (\Exception $ex)
        {
            $this->logger->debug('\\ exception error' );
            $this->logger->debug($ex==null?'true':'false');
            $msg=$ex->getMessage();
            $this->logger->debug(gettype($msg) );
            $this->logger->debug($msg );
            $this->logger->debug($ex->getTraceAsString());
        }
     #   $content=json_decode($this->_request->getContent());
     #   $body=json_decode($this->_request->getBody());
     #   $this->logger->debug(json(encode(body)) );
    }

    private function trimString(&$item,$key)
    {
        $item=trim($item);
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
        $this->getOrderFromIncId($order->getIncrementId()); // test function here

        //$order = $payment->getOrder();
        $this->logger->debug('PaylinkTokenInformationManagement getPaylinkToken' . json_encode($order));
        $path = 'payment/sample_gateway/postbackhost';
        $postbackHost = $this->scopeConfig->getValue($path);
        $this->logger->debug('postbackhost='.$postbackHost);

        $path = 'payment/sample_gateway/merchantid';
        $merchantid = $this->scopeConfig->getValue($path, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        $this->logger->debug('merchantid='.$merchantid);

        $path = 'payment/sample_gateway/licencekey';
        $licencekey = $this->scopeConfig->getValue($path, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        $this->logger->debug('licencekey='.$licencekey);

        $path = 'payment/sample_gateway/orderconfirmationemail';
        $orderconfirmationemail = $this->scopeConfig->getValue($path, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        $this->logger->debug('orderconfirmationemail='.$orderconfirmationemail);

        $path = 'payment/sample_gateway/options';
        $options = $this->scopeConfig->getValue($path, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        $this->logger->debug('options='.$options);
        if ($options!=null) {
            $optionsarray = explode(",", $options);
            array_walk($optionsarray, trimString);
        }
        else
            $optionsarray=null;

        $path = 'payment/sample_gateway/postback_policy';
        $postback_policy = $this->scopeConfig->getValue($path, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        $this->logger->debug('postback_policy='.$postback_policy);

        $xdebugsession=$this->_cookieManager->getCookie('XDEBUG_SESSION');
        if ($xdebugsession!=null)
        {
            $passThroughHeaders=[];
            $passThroughHeaders['Cookie']='XDEBUG_SESSION='.$xdebugsession;
        }
        else
            $passThroughHeaders=null;

        #get storecode for postback url
        $storeId=$order->getStoreId();
        $storeCode=$this->_storeManager->getStore($storeId)->getCode();

        $configData= [
        'postback'=>$postbackHost.'/magento/rest/'.$storeCode.'/V1/paylink/processAuthResponse'
        #    ,'redirect_success'=>''
        #    ,'redirect_failure'=>''
        ];
        if ($postback_policy!=null){
            $configData['postback_policy']=$postback_policy;
        }
        if ($passThroughHeaders!=null)
            $configData['passThroughHeaders']=$passThroughHeaders;

        $requestData= [
            'test'=>TRUE,
            'identifier' => $order->getData('increment_id'),
            'amount' => (int)(floatval($order->getData('grand_total'))*100), //'total_due'
            'merchantId'=>(int)$merchantid,
            'licenceKey'=>$licencekey,
            'clientVersion'=>'magento2 payment module v 2.0.1'
        ];
        if ($orderconfirmationemail) {
            $requestData['email']=$orderconfirmationemail;
         }
        if ($optionsarray!=null) {
            $requestData['options']=$optionsarray;
        }
        $requestData['config']=$configData;

        $order->setStatus(Order::STATE_PENDING_PAYMENT);
        #save the order.....
        #$order->
        return $requestData;

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
