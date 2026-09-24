<?php

namespace CityPay\Paylink\Test\Unit\Model\Elements;

use CityPay\Paylink\Model\Elements\MinorUnitConverter;
use CityPay\Paylink\Model\ElementsPaymentManagement;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order\Payment;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ElementsPaymentManagementOrderIntentTest extends TestCase
{
    public function testAcceptsMatchingCheckoutOrderAndIntent(): void
    {
        $management = $this->createManagement($this->validPaymentInformation());

        $order = $this->invokeValidateOrderIntent($management, 'pi_valid', 61);

        $this->assertSame(61, (int) $order->getEntityId());
    }

    /**
     * @dataProvider invalidIntentProvider
     */
    public function testRejectsInvalidOrderOrIntent(
        int $checkoutOrderId,
        int $requestedOrderId,
        string $intentId,
        string $paymentMethod,
        array $paymentInformation
    ): void {
        $this->expectException(LocalizedException::class);

        $management = $this->createManagement(
            $paymentInformation,
            $paymentMethod,
            $checkoutOrderId
        );

        $this->invokeValidateOrderIntent(
            $management,
            $intentId,
            $requestedOrderId
        );
    }

    public static function invalidIntentProvider(): array
    {
        $valid = self::validPaymentInformation();

        return [
            'different checkout order' => [62, 61, 'pi_valid', 'citypay_gateway', $valid],
            'different payment method' => [61, 61, 'pi_valid', 'checkmo', $valid],
            'invented intent id' => [61, 61, 'pi_invented', 'citypay_gateway', $valid],
            'different quote id' => [
                61,
                61,
                'pi_valid',
                'citypay_gateway',
                array_replace($valid, ['citypay_elements_quote_id' => 105]),
            ],
            'stale amount' => [
                61,
                61,
                'pi_valid',
                'citypay_gateway',
                array_replace($valid, ['citypay_elements_intent_amount' => 2600]),
            ],
            'different currency' => [
                61,
                61,
                'pi_valid',
                'citypay_gateway',
                array_replace($valid, ['citypay_elements_intent_currency' => 'EUR']),
            ],
        ];
    }

    private function createManagement(
        array $paymentInformation,
        string $paymentMethod = 'citypay_gateway',
        int $checkoutOrderId = 61
    ): ElementsPaymentManagement {
        $checkoutSession = $this->getMockBuilder(CheckoutSession::class)
            ->disableOriginalConstructor()
            ->addMethods(['getLastOrderId'])
            ->getMock();
        $checkoutSession->method('getLastOrderId')->willReturn($checkoutOrderId);

        /** @var Payment&MockObject $payment */
        $payment = $this->getMockBuilder(Payment::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getMethod', 'getAdditionalInformation'])
            ->getMock();
        $payment->method('getMethod')->willReturn($paymentMethod);
        $payment->method('getAdditionalInformation')->willReturnCallback(
            static function ($key = null) use ($paymentInformation) {
                return $key === null
                    ? $paymentInformation
                    : ($paymentInformation[$key] ?? null);
            }
        );

        $order = $this->createMock(OrderInterface::class);
        $order->method('getEntityId')->willReturn(61);
        $order->method('getQuoteId')->willReturn(104);
        $order->method('getOrderCurrencyCode')->willReturn('GBP');
        $order->method('getGrandTotal')->willReturn('27.00');
        $order->method('getPayment')->willReturn($payment);

        $orderRepository = $this->createMock(OrderRepositoryInterface::class);
        $orderRepository->method('get')->with(61)->willReturn($order);

        return new ElementsPaymentManagement(
            $checkoutSession,
            $this->createMock(ScopeConfigInterface::class),
            $this->createMock(LoggerInterface::class),
            new MinorUnitConverter(),
            $this->createMock(CartRepositoryInterface::class),
            $orderRepository,
            $this->createMock(OrderSender::class)
        );
    }

    private function invokeValidateOrderIntent(
        ElementsPaymentManagement $management,
        string $paymentIntentId,
        int $orderId
    ): OrderInterface {
        $method = new \ReflectionMethod(
            ElementsPaymentManagement::class,
            'validateOrderIntent'
        );
        $method->setAccessible(true);

        return $method->invoke($management, $paymentIntentId, $orderId);
    }

    private static function validPaymentInformation(): array
    {
        return [
            'citypay_elements_intent_id' => 'pi_valid',
            'citypay_elements_quote_id' => 104,
            'citypay_elements_intent_amount' => 2700,
            'citypay_elements_intent_currency' => 'GBP',
        ];
    }
}
