<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Testing\TestResponse;

abstract class TestCase extends BaseTestCase
{
    protected function assertApiResponseDoesNotExposePassword(
        TestResponse $response,
        array $sensitiveValues = [],
    ): void {
        $this->assertPasswordKeysAreAbsent($response->json());

        foreach ($sensitiveValues as $sensitiveValue) {
            if ($sensitiveValue !== '') {
                $this->assertStringNotContainsString($sensitiveValue, $response->getContent());
            }
        }
    }

    private function assertPasswordKeysAreAbsent(mixed $value): void
    {
        if (! is_array($value)) {
            return;
        }

        foreach ($value as $key => $nestedValue) {
            $this->assertDoesNotMatchRegularExpression(
                '/password/i',
                (string) $key,
                'API responses must not expose password-related fields.',
            );

            $this->assertPasswordKeysAreAbsent($nestedValue);
        }
    }
}
