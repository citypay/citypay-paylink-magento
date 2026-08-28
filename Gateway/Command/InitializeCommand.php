<?php

namespace CityPay\Paylink\Gateway\Command;

use Magento\Payment\Gateway\CommandInterface;
use Magento\Sales\Model\Order;

/**
 * Creates the Magento order
 */
class InitializeCommand implements CommandInterface
{
    /**
     * @param array $commandSubject
     * @return null
     */
    public function execute(array $commandSubject)
    {
        if (!isset($commandSubject['stateObject'])) {
            throw new \InvalidArgumentException('State object should be provided.');
        }

        $commandSubject['stateObject']
            ->setState(Order::STATE_PENDING_PAYMENT)
            ->setStatus(Order::STATE_PENDING_PAYMENT)
            ->setIsNotified(false);

        return null;
    }
}
