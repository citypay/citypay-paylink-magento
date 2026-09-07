<?php

namespace CityPay\Paylink\Test\Unit\Gateway\Command;

use CityPay\Paylink\Gateway\Command\InitializeCommand;
use Magento\Framework\DataObject;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\TestCase;

class InitializeCommandTest extends TestCase
{
    public function testInitializesOrderAsPendingPaymentWithoutNotification(): void
    {
        $stateObject = new DataObject();

        (new InitializeCommand())->execute(['stateObject' => $stateObject]);

        $this->assertSame(Order::STATE_PENDING_PAYMENT, $stateObject->getState());
        $this->assertSame(Order::STATE_PENDING_PAYMENT, $stateObject->getStatus());
        $this->assertFalse($stateObject->getIsNotified());
    }

    public function testRequiresStateObject(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new InitializeCommand())->execute([]);
    }
}
