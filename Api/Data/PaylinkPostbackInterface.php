<?php


namespace CityPay\Paylink\Api\Data;

interface PaylinkPostbackInterface
{
    /**
     * @return string[]
     */
    public function getData();
}