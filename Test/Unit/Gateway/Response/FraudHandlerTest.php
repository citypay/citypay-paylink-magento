<?php

namespace CityPay\Paylink\Test\Unit\Gateway\Response;

use CityPay\Paylink\Gateway\Response\FraudHandler;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Sales\Model\Order\Payment;
use PHPUnit\Framework\TestCase;

class FraudHandlerTest extends TestCase
{
    public function testDoesNothingWhenFraudMessagesAreAbsent(): void
    {
        $paymentDataObject = $this->createMock(PaymentDataObjectInterface::class);
        $paymentDataObject->expects($this->never())->method('getPayment');

        (new FraudHandler())->handle(
            ['payment' => $paymentDataObject],
            ['RESULT_CODE' => 1]
        );
    }

    public function testMarksPaymentAsFraudWhenFraudMessagesArePresent(): void
    {
        $messages = ['Stolen card'];
        $payment = $this->getMockBuilder(Payment::class)
            ->disableOriginalConstructor()
            ->onlyMethods([
                'setAdditionalInformation',
                'setIsTransactionPending',
                'setIsFraudDetected',
            ])
            ->getMock();
        $payment->expects($this->once())
            ->method('setAdditionalInformation')
            ->with(FraudHandler::FRAUD_MSG_LIST, $messages);
        $payment->expects($this->once())
            ->method('setIsTransactionPending')
            ->with(true);
        $payment->expects($this->once())
            ->method('setIsFraudDetected')
            ->with(true);

        $paymentDataObject = $this->createMock(PaymentDataObjectInterface::class);
        $paymentDataObject->method('getPayment')->willReturn($payment);

        (new FraudHandler())->handle(
            ['payment' => $paymentDataObject],
            [FraudHandler::FRAUD_MSG_LIST => $messages]
        );
    }
}
