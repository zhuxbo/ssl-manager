<?php

namespace Tests\Unit\Services\Order;

use App\Services\Order\Action;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ActionValidationReadyTest extends TestCase
{
    #[DataProvider('validationReadyProvider')]
    public function test_is_validation_ready(?array $validation, ?string $method, bool $expected): void
    {
        $action = new Action(0);

        $this->assertEquals($expected, $action->isValidationReady($validation, $method));
    }

    public static function validationReadyProvider(): array
    {
        return [
            'validation null' => [null, 'txt', false],
            'validation empty array' => [[], 'txt', false],
            'txt ok' => [[['value' => 'token1'], ['value' => 'token2']], 'txt', true],
            'txt missing value' => [[['value' => 'token1'], ['value' => '']], 'txt', false],
            'cname ok' => [[['value' => 'token1']], 'cname', true],
            'cname missing value' => [[['value' => null]], 'cname', false],
            'http ok' => [[['content' => 'file-content']], 'http', true],
            'http missing content' => [[['content' => '']], 'http', false],
            'https ok' => [[['content' => 'file-content']], 'https', true],
            'file missing content' => [[['content' => null]], 'file', false],
            'other method with validation' => [[['email' => 'admin@example.com']], 'admin', true],
        ];
    }
}
