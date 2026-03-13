<?php

use App\Services\Acme\Api\AcmeSourceApiInterface;

uses(Tests\TestCase::class);

test('interface defines 4 methods', function () {
    $reflection = new ReflectionClass(AcmeSourceApiInterface::class);
    $methods = array_map(fn ($m) => $m->getName(), $reflection->getMethods());

    expect($methods)->toHaveCount(4);
    expect($methods)->toContain('new');
    expect($methods)->toContain('get');
    expect($methods)->toContain('cancel');
    expect($methods)->toContain('getProducts');
});

test('certum api implements interface', function () {
    $reflection = new ReflectionClass(\App\Services\Acme\Api\certum\Api::class);

    expect($reflection->implementsInterface(AcmeSourceApiInterface::class))->toBeTrue();
});

test('all source apis implement interface', function () {
    $sources = ['certum', 'certumcnssl', 'certumtest'];

    foreach ($sources as $source) {
        $class = "App\\Services\\Acme\\Api\\$source\\Api";
        $reflection = new ReflectionClass($class);

        expect($reflection->implementsInterface(AcmeSourceApiInterface::class))
            ->toBeTrue("$class should implement AcmeSourceApiInterface");
    }
});

test('certumcnssl api extends certum api', function () {
    $reflection = new ReflectionClass(\App\Services\Acme\Api\certumcnssl\Api::class);
    expect($reflection->getParentClass()->getName())
        ->toBe(\App\Services\Acme\Api\certum\Api::class);
});

test('certumtest api extends certum api', function () {
    $reflection = new ReflectionClass(\App\Services\Acme\Api\certumtest\Api::class);
    expect($reflection->getParentClass()->getName())
        ->toBe(\App\Services\Acme\Api\certum\Api::class);
});

test('certumcnssl sdk extends certum sdk', function () {
    $reflection = new ReflectionClass(\App\Services\Acme\Api\certumcnssl\Sdk::class);
    expect($reflection->getParentClass()->getName())
        ->toBe(\App\Services\Acme\Api\certum\Sdk::class);
});

test('certumtest sdk extends certum sdk', function () {
    $reflection = new ReflectionClass(\App\Services\Acme\Api\certumtest\Sdk::class);
    expect($reflection->getParentClass()->getName())
        ->toBe(\App\Services\Acme\Api\certum\Sdk::class);
});

test('no default directory exists', function () {
    $class = 'App\\Services\\Acme\\Api\\default\\Api';
    expect(class_exists($class))->toBeFalse();
});

test('router has new get cancel getProducts methods', function () {
    $reflection = new ReflectionClass(\App\Services\Acme\Api\Api::class);
    $methods = array_map(fn ($m) => $m->getName(), $reflection->getMethods(ReflectionMethod::IS_PUBLIC));

    expect($methods)->toContain('new');
    expect($methods)->toContain('get');
    expect($methods)->toContain('cancel');
    expect($methods)->toContain('getProducts');
});
