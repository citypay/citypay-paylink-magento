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

class ElementsPaymentManagementPaymentUpdateTest extends TestCase
{
    public function testVerifiedPaymentIsRegisteredOnlyOnce(): void
    {
        $information = [];

        /** @var Payment&MockObject $payment */
        $payment = $this->getMockBuilder(Payment::class)
            ->disableOriginalConstructor()
            ->onlyMethods([
                'getAdditionalInformation',
                'setAdditionalInformation',
                'setTransactionId',
                'setIsTransactionClosed',
                'registerAuthorizationNotification',
                'registerCaptureNotification',
            ])
            ->getMock();
        $payment->method('getAdditionalInformation')->willReturnCallback(
            static function ($key = null) use (&$information) {
                return $key === null ? $information : ($information[$key] ?? null);
            }
        );
        $payment->method('setAdditionalInformation')->willReturnCallback(
            static function ($key, $value) use (&$information, $payment) {
                $information[$key] = $value;
                return $payment;
            }
        );
        $payment->expects($this->once())->method('setTransactionId')->with('74875');
        $payment->expects($this->once())->method('setIsTransactionClosed')->with(false);
        $payment->expects($this->once())
            ->method('registerAuthorizationNotification')
            ->with(27.0);
        $payment->expects($this->once())
            ->method('registerCaptureNotification')
            ->with(27.0);

        /** @var Order&MockObject $order */
        $order = $this->getMockBuilder(Order::class)
            ->disableOriginalConstructor()
            ->onlyMethods([
                'getPayment',
                'getBaseGrandTotal',
                'addCommentToStatusHistory',
                'getEmailSent',
                'setCanSendNewEmailFlag',
            ])
            ->getMock();
        $order->method('getPayment')->willReturn($payment);
        $order->method('getBaseGrandTotal')->willReturn('27.00');
        $order->expects($this->exactly(2))->method('addCommentToStatusHistory');
        $order->method('getEmailSent')->willReturn(false);
        $order->expects($this->once())->method('setCanSendNewEmailFlag')->with(true);

        $orderRepository = $this->createMock(OrderRepositoryInterface::class);
        $orderRepository->expects($this->exactly(2))->method('save')->with($order);

        $orderSender = $this->createMock(OrderSender::class);
        $orderSender->expects($this->once())->method('send')->with($order, true);

        $management = new ElementsPaymentManagement(
            $this->createMock(CheckoutSession::class),
            $this->createMock(ScopeConfigInterface::class),
            $this->createMock(LoggerInterface::class),
            new MinorUnitConverter(),
            $this->createMock(CartRepositoryInterface::class),
            $orderRepository,
            $orderSender
        );

        $result = [
            'result' => 'Accepted',
            'result_id' => 1,
            'trans_status' => 'O',
            'transno' => 74875,
        ];

        $this->invokeRegisterVerifiedPayment($management, $order, $result);
        $this->invokeRegisterVerifiedPayment($management, $order, $result);

        $this->assertSame('74875', $information['citypay_elements_authorised_transaction']);
        $this->assertSame('74875', $information['citypay_elements_verified_transaction']);
    }

    private function invokeRegisterVerifiedPayment(
        ElementsPaymentManagement $management,
        Order $order,
        array $result
    ): void {
        $method = new \ReflectionMethod(
            ElementsPaymentManagement::class,
            'registerVerifiedPayment'
        );
        $method->setAccessible(true);
        $method->invoke($management, $order, $result);
    }
}
