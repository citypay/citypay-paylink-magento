<?php

namespace CityPay\Paylink\Test\Unit\Model\Elements;

use CityPay\Paylink\Model\ElementsPaymentManagement;
use PHPUnit\Framework\TestCase;

class ElementsPaymentManagementOutcomeTest extends TestCase
{
    /**
     * @dataProvider authorisationOutcomeProvider
     */
    public function testClassifiesAuthorisationResponses(array $response, string $expected): void
    {
        $this->assertSame(
            $expected,
            $this->classifyResponse('isAuthorisationApproved', $response)
        );
    }

    public static function authorisationOutcomeProvider(): array
    {
        return [
            'approved native values' => [
                ['authorised' => true, 'result' => 1, 'transno' => 74875, 'trans_status' => 'O'],
                'approved',
            ],
            'approved string values' => [
                ['authorised' => 'true', 'result' => '1', 'transno' => '74875'],
                'approved',
            ],
            'authorised false is declined' => [
                ['authorised' => false, 'result' => 0, 'trans_status' => 'D'],
                'declined',
            ],
            'authentication failure is declined' => [
                ['authen_result' => 'n'],
                'declined',
            ],
            'missing fields are unknown' => [
                ['response' => 'temporarily unavailable'],
                'unknown',
            ],
            'null optional fields are unknown' => [
                ['authorised' => null, 'result' => null],
                'unknown',
            ],
            'success without transaction number is unknown' => [
                ['authorised' => true, 'result' => 1],
                'unknown',
            ],
            'contradictory fields are unknown' => [
                ['authorised' => true, 'result' => 0, 'transno' => 74875],
                'unknown',
            ],
        ];
    }

    /**
     * @dataProvider verificationOutcomeProvider
     */
    public function testClassifiesVerificationResponses(array $response, string $expected): void
    {
        $this->assertSame(
            $expected,
            $this->classifyResponse('isVerificationApproved', $response)
        );
    }

    public static function verificationOutcomeProvider(): array
    {
        return [
            'approved open transaction' => [
                ['result' => 'Accepted', 'result_id' => 1, 'trans_status' => 'O', 'transno' => 74875],
                'approved',
            ],
            'approved long status' => [
                ['result' => 'accepted', 'result_id' => '1', 'trans_status' => 'open', 'transno' => '74875'],
                'approved',
            ],
            'declined result' => [
                ['result' => 'Declined', 'result_id' => 0, 'trans_status' => 'D'],
                'declined',
            ],
            'cancelled status' => [
                ['result' => 'Cancelled', 'result_id' => 0, 'trans_status' => 'C'],
                'declined',
            ],
            'missing fields are unknown' => [
                [],
                'unknown',
            ],
            'null optional fields are unknown' => [
                ['authorised' => null, 'result_id' => null],
                'unknown',
            ],
            'accepted without transaction number is unknown' => [
                ['result' => 'Accepted', 'result_id' => 1, 'trans_status' => 'O'],
                'unknown',
            ],
            'contradictory status is unknown' => [
                ['result' => 'Accepted', 'result_id' => 1, 'trans_status' => 'D', 'transno' => 74875],
                'unknown',
            ],
        ];
    }

    private function classifyResponse(string $approvalMethod, array $response): string
    {
        $reflection = new \ReflectionClass(ElementsPaymentManagement::class);
        $management = $reflection->newInstanceWithoutConstructor();
        $approved = $reflection->getMethod($approvalMethod);
        $approved->setAccessible(true);

        if ($approved->invoke($management, $response)) {
            return 'approved';
        }

        $declined = $reflection->getMethod('isDefiniteDecline');
        $declined->setAccessible(true);

        return $declined->invoke($management, $response) ? 'declined' : 'unknown';
    }
}
