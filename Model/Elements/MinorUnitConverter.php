<?php

namespace CityPay\Paylink\Model\Elements;

use Magento\Framework\Exception\LocalizedException;

final class                                                                                  MinorUnitConverter {
    /**
     * @param float|int|string $amount Magento amount in major units
     */
    public function toMinorUnits(string $amount, string $currency): int {
        $currency = strtoupper(trim($currency));

        if ($currency !== 'GBP') {
            throw new LocalizedException(__('CityPay Elements supports GBP only.'));
        }

        if (!is_numeric($amount)) {
            throw new \InvalidArgumentException(
                'Invalid Magento monetary amount.'
            );
        }

        /*
           * Apply Magento/GBP currency precision once at the CityPay boundary.
           *
           * 50.4    -> "50.40" -> 5040
           * 22      -> "22.00" -> 2200
           * 74.95   -> "74.95" -> 7495
        */
        $normalised = number_format(
            (float) $amount,
            2,
            '.',
            ''
        );

        if ((float) $normalised < 0) {
            throw new \InvalidArgumentException(
                'CityPay amount cannot be negative.'
            );
        }

        return (int) str_replace('.', '', $normalised);
    }
}

