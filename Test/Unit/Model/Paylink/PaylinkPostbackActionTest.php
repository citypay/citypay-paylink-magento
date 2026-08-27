<?php

namespace CityPay\Paylink\Test\Unit\Model\Paylink;

use CityPay\Paylink\Model\PaylinkTokenInformationManagement;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\TestCase;

class PaylinkPostbackActionTest extends TestCase
{
    /**
     * @dataProvider postbackActionProvider
     */
    public function testDeterminesSafePostbackAction(
        bool $authorised,
        string $orderState,
        string $orderStatus,
        string $registeredTransaction,
        string $transactionNumber,
        string $expectedAction
    ): void {
        $reflection = new \ReflectionClass(PaylinkTokenInformationManagement::class);
        $management = $reflection->newInstanceWithoutConstructor();
        $method = $reflection->getMethod('determinePostbackAction');
        $method->setAccessible(true);

        $this->assertSame(
            $expectedAction,
            $method->invoke(
                $management,
                $authorised,
                $orderState,
                $orderStatus,
                $registeredTransaction,
                $transactionNumber
            )
        );
    }

    public static function postbackActionProvider(): array
    {
        return [
            'first successful postback' => [
                true,
                Order::STATE_PENDING_PAYMENT,
                Order::STATE_PENDING_PAYMENT,
                '',
                '74875',
                'authorise',
            ],
            'same successful transaction is a duplicate' => [
                true,
                Order::STATE_PROCESSING,
                Order::STATE_PROCESSING,
                '74875',
                '74875',
                'duplicate',
            ],
            'legacy processing order is a duplicate' => [
                true,
                Order::STATE_PROCESSING,
                Order::STATE_PROCESSING,
                '',
                '74875',
                'duplicate',
            ],
            'different successful transaction conflicts' => [
                true,
                Order::STATE_PROCESSING,
                Order::STATE_PROCESSING,
                '74875',
                '74876',
                'conflict',
            ],
            'decline cancels pending order' => [
                false,
                Order::STATE_PENDING_PAYMENT,
                Order::STATE_PENDING_PAYMENT,
                '',
                '74875',
                'cancel',
            ],
            'decline cannot cancel processing order' => [
                false,
                Order::STATE_PROCESSING,
                Order::STATE_PROCESSING,
                '',
                '74875',
                'ignore_decline',
            ],
            'decline cannot cancel registered payment' => [
                false,
                Order::STATE_PENDING_PAYMENT,
                Order::STATE_PENDING_PAYMENT,
                '74875',
                '74875',
                'ignore_decline',
            ],
            'repeated decline cannot recancel order' => [
                false,
                Order::STATE_CANCELED,
                Order::STATE_CANCELED,
                '',
                '74875',
                'ignore_decline',
            ],
        ];
    }
}
