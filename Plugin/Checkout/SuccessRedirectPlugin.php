<?php
declare(strict_types=1);

namespace CityPay\Paylink\Plugin\Checkout;

use CityPay\Paylink\Api\PaylinkTokenInformationManagementInterface2;
use CityPay\Paylink\Model\Ui\ConfigProvider;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Module\Manager as ModuleManager;
use Magento\Quote\Api\Data\PaymentInterfaceFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Store\Model\ScopeInterface;
use Psr\Log\LoggerInterface;

class SuccessRedirectPlugin
{
    private const REDIRECT_SESSION_KEY = 'citypay_paylink_redirect_order_id';
    private const HYVA_REACT_CHECKOUT_ENABLED_PATH = 'hyva_react_checkout/general/enable';

    public function __construct(
        private readonly CheckoutSession $checkoutSession,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly PaymentInterfaceFactory $paymentFactory,
        private readonly PaylinkTokenInformationManagementInterface2 $paylinkTokenInformationManagement,
        private readonly RedirectFactory $redirectFactory,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly ModuleManager $moduleManager,
        private readonly LoggerInterface $logger
    ) {
    }

    public function aroundExecute(
        \CityPay\Paylink\Controller\Onepage\Success $subject,
        callable $proceed
    ) {
        if (!$this->shouldUseHyvaReactCheckoutFlow()) {
            return $proceed();
        }

        $orderId = (int) $this->checkoutSession->getLastOrderId();
        if ($orderId <= 0) {
            return $proceed();
        }

        if ((int) $this->checkoutSession->getData(self::REDIRECT_SESSION_KEY) === $orderId) {
            return $proceed();
        }

        try {
            $order = $this->orderRepository->get($orderId);
        } catch (NoSuchEntityException $exception) {
            $this->logger->warning(
                'CityPay: unable to load order for Hyva success redirect.',
                ['order_id' => $orderId, 'exception' => $exception->getMessage()]
            );
            return $proceed();
        }

        if ($order->getPayment() === null || $order->getPayment()->getMethod() !== ConfigProvider::CODE) {
            return $proceed();
        }

        if (in_array((string) $order->getState(), ['processing', 'complete', 'closed', 'canceled'], true)) {
            return $proceed();
        }

        try {
            $payment = $this->paymentFactory->create();
            $payment->setMethod(ConfigProvider::CODE);
            $payment->setAdditionalData(['orderId' => $orderId]);

            $response = $this->paylinkTokenInformationManagement->getPaylinkToken($payment);
            $decoded = json_decode((string) $response, true);

            if (is_array($decoded) && (int) ($decoded['result'] ?? 0) === 1 && !empty($decoded['url'])) {
                $this->checkoutSession->setData(self::REDIRECT_SESSION_KEY, $orderId);
                /** @var Redirect $redirect */
                $redirect = $this->redirectFactory->create();
                return $redirect->setUrl((string) $decoded['url']);
            }
        } catch (\Throwable $exception) {
            $this->logger->error(
                'CityPay: failed to build Paylink redirect on success page.',
                ['order_id' => $orderId, 'exception' => $exception->getMessage()]
            );
        }

        return $proceed();
    }

    private function shouldUseHyvaReactCheckoutFlow(): bool
    {
        if (!$this->moduleManager->isEnabled('Hyva_ReactCheckout')) {
            return false;
        }

        return $this->scopeConfig->isSetFlag(
            self::HYVA_REACT_CHECKOUT_ENABLED_PATH,
            ScopeInterface::SCOPE_STORE
        );
    }
}

