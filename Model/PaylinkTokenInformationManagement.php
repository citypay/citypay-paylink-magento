<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace CityPay\Paylink\Model;

use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Webapi\Rest\Request as RestRequest;
use Magento\Payment\Gateway\Http\TransferInterface;
use Magento\Sales\Model\Order;
use mysql_xdevapi\Exception;

/**
 * Payment information management
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class PaylinkTokenInformationManagement implements \CityPay\Paylink\Api\PaylinkTokenInformationManagementInterface2
{

    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $checkoutSession;

    protected $_logger;
    /**
     * @var \Magento\Quote\Api\BillingAddressManagementInterface
     * @deprecated 100.1.0 This call was substituted to eliminate extra quote::save call
     */
    protected $billingAddressManagement;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;

    /**
     * @var \Magento\Quote\Api\CartRepositoryInterface
     */
    //private $cartRepository;

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
    protected $searchCriteriaBuilder;

    /**
     * @var UrlInterface $urlBuilder
     */
    protected $urlBuilder;

    private $licence_key;

    private $productMetadata;

    private $version;

    /**
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Magento\Quote\Api\BillingAddressManagementInterface $billingAddressManagement
     * @param \Magento\Quote\Api\PaymentMethodManagementInterface $paymentMethodManagement
     * @param \Magento\Sales\Api\OrderRepositoryInterface $orderRepository ,
     * @param \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder
     * @param \Magento\Payment\Gateway\Http\TransferBuilder $transferBuilder ,
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig ,
     * @param \Psr\Log\LoggerInterface $logger ,
     * @param RestRequest $request ,
     * @param \Magento\Framework\Stdlib\CookieManagerInterface $cookieManager ,
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager ,
     * @param \Magento\Framework\UrlInterface $urlBuilder
     * @codeCoverageIgnore
     */
    public function __construct(
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Quote\Api\BillingAddressManagementInterface $billingAddressManagement,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder,
        \Magento\Payment\Gateway\Http\TransferBuilder $transferBuilder,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Psr\Log\LoggerInterface $logger,
        RestRequest $request,
        \Magento\Framework\Stdlib\CookieManagerInterface $cookieManager,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\UrlInterface $urlBuilder,
        \Magento\Framework\App\ProductMetadataInterface $productMetadata
    )
    {
        $this->checkoutSession = $checkoutSession;
        $this->billingAddressManagement = $billingAddressManagement;
        $this->orderRepository = $orderRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->transferBuilder = $transferBuilder;
        $this->scopeConfig = $scopeConfig;
        $this->logger = $logger;
        $this->_request = $request;
        $this->_cookieManager = $cookieManager;
        $this->_storeManager = $storeManager;
        $this->urlBuilder = $urlBuilder;
        $this->productMetadata = $productMetadata;

        try {
            $string = file_get_contents(__DIR__ . "/../composer.json");
            $j = json_decode($string, true);
            $this->version = $j["name"] . ':' . $j["version"];
        } catch (\Exception $e) {
            $this->version = "Unknown";
        }

    }

    /**
     * @inheritdoc
     */
    public function getPaylinkToken(
        \Magento\Quote\Api\Data\PaymentInterface $paymentMethod
    )
    {
        $this->logger->debug('CityPay:Paylink:getPaylinkToken Start');
        try {
            $payment = $paymentMethod;
            $request = $this->buildPaylinkRequest($payment);
            $transfer = $this->createHttpRequest($request);
            $response = $this->processHttp($transfer);
            return $response;
        } catch (Exception $ex) {
            $this->logger->error('CityPay:Paylink:getPaylinkToken:' . $ex->getMessage());
        } finally {
            $this->logger->debug('CityPay:Paylink:getPaylinkToken End');
        }
    }


    /**
     * @param $postback_data
     * @return bool
     * @throws Exception
     */
    public function validatePostbackDigest($postback_data)
    {
        $this->logger->debug('CityPay:Paylink:validatePostbackDigest');

        $hash_src = $postback_data->authcode .
            $postback_data->amount .
            $postback_data->errorcode .
            $postback_data->merchantid .
            $postback_data->transno .
            $postback_data->identifier .
            $this->licence_key;

        $check = base64_encode(hash('sha256', $hash_src, true));

        if (strcmp($postback_data->sha256, $check) != 0) {
            $this->logger->info('Digest mismatch');
            throw new Exception('Digest mismatch');
        }

        $this->logger->debug('CityPay:Paylink:validatePostbackDigest:Digest matched "' . $check . '"');
        return true;

    }

    private function findOrder($identifier)
    {
        $searchCriteria = $this->searchCriteriaBuilder->addFilter('increment_id', $identifier, 'eq')->create();
        $searchResult = $this->orderRepository->getList($searchCriteria);
        $order = null;
        if ($searchResult != null) {
            $searchItems = $searchResult->getItems();
            foreach ($searchItems as $item) {
                $arrItems['items'][] = $item->getData();
            }
            if (sizeof($searchItems) == 1) {
                $order = $searchItems[key($searchItems)];
            }
        } else {
            $this->logger->info('search result not found');
        }
        return $order;
    }

    /**
     * @inheritdoc
     */
    public function processPaylinkPostback()
    {

        $this->logger->debug('CityPay:Paylink:processPaylinkPostback Start');
        try {

            $postbackString = $this->_request->getContent();
            $postbackData = json_decode($postbackString);

            $this->logger->debug('CityPay:Paylink:processPaylinkPostback: ' . $postbackString);


            $path = 'payment/citypay_gateway/licencekey';
            $this->licence_key = $this->scopeConfig->getValue($path, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);

            if ($this->validatePostbackDigest($postbackData)) {

                $identifier = $postbackData->identifier;
                $transno = $postbackData->transno;
                $amountAuthd = $postbackData->amount / 100.0;
                $order = $this->findOrder($identifier);
                $payment = $order->getPayment(); #OrderPaymentInterface

                if ($postbackData->authorised == 'true') {
                    $payment->registerAuthorizationNotification($amountAuthd);
                    # open for settlement, assigned to a batch or settled
                    $this->logger->info('Transaction authorised');

                    if ($postbackData->status == 'O' || $postbackData->status == 'A' || $postbackData->status == 'S') {
                        $this->logger->info('Transaction captured');
                        $payment->registerCaptureNotification($amountAuthd);
                    }

                } else {
                    $this->logger->info('Order cancelled due to transaction not being authorised');
                    $order->cancel();
                }
                $order->addCommentToStatusHistory(sprintf('<span>CityPay Paylink *%s* transaction |<br/> Result: %s |<br/> Card: %s |<br/> TransNo: %d |<br/> AuthCode: %s |<br/> AuthResult: %s |<br/> AVS: %s |<br/> CSC: %s |<br/> Status: %s </span>',
                    $postbackData->mode,
                    (isset($postbackData->errorcode) ? $postbackData->errorcode  : "-") . ':' . (isset($postbackData->errormessage) ? $postbackData->errormessage  : ""),
                    isset($postbackData->maskedPan) ? $postbackData->maskedPan : "-",
                    $transno,
                    isset($postbackData->authcode) ? $postbackData->authcode : "-",
                    isset($postbackData->authenticationResult) ? $postbackData->authenticationResult : "-",
                    isset($postbackData->AVSResponse) ? $postbackData->AVSResponse : "-",
                    isset($postbackData->CSCResponse) ? $postbackData->CSCResponse : "-",
                    isset($postbackData->status) ? $postbackData->status : "-"
                ));

                $this->logger->info('Saved to order');
                $order->save();
            } else {
                $this->logger->info('invalid postback (digest mismatch)');
            }
        } catch (\Exception $ex) {
            $this->logger->error($ex->getMessage());
        } finally {
            $this->logger->debug('CityPay:Paylink:processPaylinkPostback End');
        }
    }

    private function trimString(&$item, $key)
    {
        $item = trim($item);
    }

    public function buildPaylinkRequest($payment)
    {
        // obtain the order id
        $ad = $payment->getAdditionalData();
        $this->logger->debug('CityPay:Paylink:buildRequestData:ad ' . json_encode($ad));
        $orderId = $ad['orderId'];

        // obtain the order
        $order = $this->orderRepository->get($orderId);

        $billingAddress = $order->getBillingAddress();

        $postbackHost = $this->scopeConfig->getValue('payment/citypay_gateway/postbackhost');
        $merchantid = $this->scopeConfig->getValue('payment/citypay_gateway/merchantid', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        $licencekey = $this->scopeConfig->getValue('payment/citypay_gateway/licencekey', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        $orderconfirmationemail = $this->scopeConfig->getValue('payment/citypay_gateway/orderconfirmationemail', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        $options = $this->scopeConfig->getValue('payment/citypay_gateway/options', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);

        if ($options != null) {
            $optionsarray = explode(",", $options);
            array_walk($optionsarray, trimString);
        } else
            $optionsarray = null;

        $postback_policy = $this->scopeConfig->getValue('payment/citypay_gateway/postback_policy', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        $testmode = $this->scopeConfig->getValue('payment/citypay_gateway/testmode', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);

        $passThroughHeaders = [];

        #get storecode for postback url
        $storeId = $order->getStoreId();
        $storeCode = $this->_storeManager->getStore($storeId)->getCode();

        $configData = [
            'postback' => $postbackHost . '/rest/' . $storeCode . '/V1/paylink/processAuthResponse',
            'redirect_success' => "GET:".$this->urlBuilder->getUrl('checkout/onepage/success'),
            'redirect_failure' => "GET:".$this->urlBuilder->getUrl('checkout/onepage/failure')
        ];
        $streets = $billingAddress->getStreet();#returns an array
        $cardholder = [
            'email' => $order->getCustomerEmail(),
            'firstname' => $billingAddress->getFirstname(),
            'lastname' => $billingAddress->getLastname(),
            'address' => [
                'address1' => count($streets) > 0 ? $streets[0] : null,
                'address2' => count($streets) > 1 ? $streets[1] : null,
                'area' => $billingAddress->getCity() . ($billingAddress->getRegion() != null ? ',' . $billingAddress->getRegion() : ''),
                'postcode' => $billingAddress->getPostcode(),
                'country' => $billingAddress->getCountryId()
            ]
        ];

        if ($postback_policy != null) {
            $configData['postback_policy'] = $postback_policy;
        }
        if ($passThroughHeaders != null)
            $configData['passThroughHeaders'] = $passThroughHeaders;

        $requestData = [
            'test' => $testmode,
            'identifier' => $order->getData('increment_id'),
            'amount' => (int)(str_replace('.', '', floatval($order->getData('grand_total')))), //'total_due'
            'merchantId' => (int)$merchantid,
            'licenceKey' => $licencekey,
            'clientVersion' => $this->version . ' Magento-' . $this->productMetadata->getEdition() . ':' . $this->productMetadata->getVersion()
        ];
        if ($orderconfirmationemail) {
            $requestData['email'] = $orderconfirmationemail;
        }
        if ($optionsarray != null) {
            $requestData['options'] = $optionsarray;
        }
        $requestData['config'] = $configData;
        $requestData['cardholder'] = $cardholder;
        $order->setStatus(Order::STATE_PENDING_PAYMENT);
        #save the order.....
        $order->save();

        return $requestData;

    }

    /**
     * Builds gateway transfer object
     *
     * @param array $request
     * @return TransferInterface
     */
    public function createHttpRequest(array $request)
    {
        return $this->transferBuilder
            ->setBody($request)
            ->setMethod('POST')
            ->setHeaders(array('content-type:application/json'))
            ->setUri('https://secure.citypay.com/paylink3/create')
            ->build();
    }

    /**
     * Places request to gateway. Returns result as ENV array
     *
     * @param TransferInterface $transferObject
     * @return string
     */
    public function processHttp(TransferInterface $transferObject)
    {
        $this->logger->debug('CityPay:Paylink:processHttp:Request' . json_encode($transferObject->getBody()));
        $ch = curl_init($transferObject->getUri());
        curl_setopt($ch, CURLOPT_POST, $transferObject->getMethod() == 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($transferObject->getBody()));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $transferObject->getHeaders());
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);
        $this->logger->debug('CityPay:Paylink:processHttp:Response' . $response);
        return $response;
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
