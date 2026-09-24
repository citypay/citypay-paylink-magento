<?php

namespace CityPay\Paylink\Test\Unit\Model\Elements;

use CityPay\Paylink\Model\Elements\MinorUnitConverter;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;

class MinorUnitConverterTest extends TestCase
{
    /**
     * @dataProvider validAmountsProvider
     */
    public function testConvertsGbpToMinorUnits(string $amount, int $expected): void
    {
        $this->assertSame(
            $expected,
            (new MinorUnitConverter())->toMinorUnits($amount, 'GBP')
        );
    }

    public static function validAmountsProvider(): array
    {
        return [
            'whole pounds' => ['39', 3900],
            'one decimal place' => ['50.4', 5040],
            'two decimal places' => ['74.95', 7495],
            'round to GBP precision' => ['27.005', 2701],
            'zero' => ['0', 0],
        ];
    }

    public function testAcceptsNormalisedLowercaseCurrency(): void
    {
        $this->assertSame(
            2700,
            (new MinorUnitConverter())->toMinorUnits('27.00', ' gbp ')
        );
    }

    public function testRejectsUnsupportedCurrency(): void
    {
        $this->expectException(LocalizedException::class);
        (new MinorUnitConverter())->toMinorUnits('27.00', 'EUR');
    }

    public function testRejectsNonNumericAmount(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new MinorUnitConverter())->toMinorUnits('twenty-seven', 'GBP');
    }

    public function testRejectsNegativeAmount(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new MinorUnitConverter())->toMinorUnits('-0.01', 'GBP');
    }
}
