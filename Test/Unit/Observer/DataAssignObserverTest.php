<?php

namespace CityPay\Paylink\Test\Unit\Observer;

use CityPay\Paylink\Observer\DataAssignObserver;
use Magento\Framework\DataObject;
use Magento\Framework\Event\Observer;
use Magento\Payment\Model\InfoInterface;
use Magento\Payment\Model\MethodInterface;
use PHPUnit\Framework\TestCase;

class DataAssignObserverTest extends TestCase
{
    public function testStoresOnlySupportedCityPayAdditionalData(): void
    {
        $stored = [];
        $paymentInfo = $this->createMock(InfoInterface::class);
        $paymentInfo->method('setAdditionalInformation')->willReturnCallback(
            static function ($key, $value) use (&$stored) {
                $stored[$key] = $value;
            }
        );

        $method = $this->createMock(MethodInterface::class);
        $method->method('getInfoInstance')->willReturn($paymentInfo);

        $data = new DataObject([
            'additional_data' => [
                'payment_intent_id' => 'pi_valid',
                'payment_channel' => 'google_pay',
                'transaction_result' => '',
                'unexpected' => 'must-not-be-stored',
            ],
        ]);

        (new DataAssignObserver())->execute($this->createObserver($method, $data));

        $this->assertSame([
            'payment_intent_id' => 'pi_valid',
            'payment_channel' => 'google_pay',
        ], $stored);
    }

    public function testIgnoresMissingAdditionalData(): void
    {
        $paymentInfo = $this->createMock(InfoInterface::class);
        $paymentInfo->expects($this->never())->method('setAdditionalInformation');

        $method = $this->createMock(MethodInterface::class);
        $method->method('getInfoInstance')->willReturn($paymentInfo);

        (new DataAssignObserver())->execute(
            $this->createObserver($method, new DataObject())
        );
    }

    private function createObserver(
        MethodInterface $method,
        DataObject $data
    ): Observer {
        return new Observer([
            'event' => new DataObject([
                DataAssignObserver::METHOD_CODE => $method,
                DataAssignObserver::DATA_CODE => $data,
            ]),
        ]);
    }
}
