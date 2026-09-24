<?php

namespace CityPay\Paylink\Test\Unit\Model\Elements;

use CityPay\Paylink\Model\Elements\MinorUnitConverter;
use CityPay\Paylink\Model\ElementsPaymentManagement;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Magento\Sales\Model\Order\Payment;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ElementsPaymentManagementOrderTransitionTest extends TestCase
{
    public function testDefiniteDeclineCancelsPendingOrder(): void
    {
        /** @var Payment&MockObject $payment */
        $payment = $this->getMockBuilder(Payment::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getAdditionalInformation'])
            ->getMock();
        $payment->method('getAdditionalInformation')->willReturn(null);

        /** @var Order&MockObject $order */
        $order = $this->getMockBuilder(Order::class)
            ->disableOriginalConstructor()
            ->onlyMethods([
                'getPayment',
                'getState',
                'getStatus',
                'canCancel',
                'cancel',
                'addCommentToStatusHistory',
            ])
            ->getMock();
        $order->method('getPayment')->willReturn($payment);
        $order->method('getState')->willReturn(Order::STATE_PENDING_PAYMENT);
        $order->method('getStatus')->willReturn(Order::STATE_PENDING_PAYMENT);
        $order->method('canCancel')->willReturn(true);
        $order->expects($this->once())->method('cancel')->willReturnSelf();
        $order->expects($this->once())->method('addCommentToStatusHistory');

        $orderRepository = $this->createMock(OrderRepositoryInterface::class);
        $orderRepository->expects($this->once())->method('save')->with($order);

        $management = $this->createManagement(
            $this->createMock(CheckoutSession::class),
            $orderRepository
        );
        $method = new \ReflectionMethod(ElementsPaymentManagement::class, 'cancelPendingOrder');
        $method->setAccessible(true);
        $method->invoke($management, $order, 'CityPay payment was declined.');
    }

    public function testInconclusiveOutcomeMovesPendingOrderToPaymentReview(): void
    {
        /** @var Order&MockObject $order */
        $order = $this->getMockBuilder(Order::class)
            ->disableOriginalConstructor()
            ->onlyMethods([
                'getState',
                'setState',
                'setStatus',
                'addCommentToStatusHistory',
            ])
            ->getMock();
        $order->method('getState')->willReturn(Order::STATE_PENDING_PAYMENT);
        $order->expects($this->once())
            ->method('setState')
            ->with(Order::STATE_PAYMENT_REVIEW)
            ->willReturnSelf();
        $order->expects($this->once())
            ->method('setStatus')
            ->with(Order::STATE_PAYMENT_REVIEW)
            ->willReturnSelf();
        $order->expects($this->once())->method('addCommentToStatusHistory');

        $orderRepository = $this->createMock(OrderRepositoryInterface::class);
        $orderRepository->expects($this->once())->method('save')->with($order);

        $management = $this->createManagement(
            $this->createMock(CheckoutSession::class),
            $orderRepository
        );
        $method = new \ReflectionMethod(ElementsPaymentManagement::class, 'moveOrderToPaymentReview');
        $method->setAccessible(true);
        $method->invoke($management, $order, 'CityPay response was inconclusive.');
    }

    private function createManagement(
        CheckoutSession $checkoutSession,
        OrderRepositoryInterface $orderRepository
    ): ElementsPaymentManagement {
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
}
