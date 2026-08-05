<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

// Schema-core unit tests need the app container (resource_path, Str) but no DB.
pest()->extend(TestCase::class)->in('Unit');

/**
 * A minimal schema that passes validation, used as a baseline in tests.
 */
function baseSchema(array $overrides = []): array
{
    return array_replace_recursive([
        'title' => 'Test form',
        'description' => null,
        'settings' => ['multi_step' => false],
        'sections' => [
            [
                'id' => 'sec_abcd1234',
                'title' => 'Main',
                'description' => null,
                'fields' => [
                    [
                        'id' => 'fld_abcd1234',
                        'type' => 'text',
                        'key' => 'full_name',
                        'label' => 'Full name',
                        'placeholder' => null,
                        'help' => null,
                        'default' => null,
                        'required' => true,
                        'options' => [],
                        'validation' => ['minLength' => 2, 'maxLength' => 100],
                        'conditions' => null,
                    ],
                ],
            ],
        ],
    ], $overrides);
}

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}
