<?php

namespace Projects13\Tests\Concerns;

use Closure;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator as LaravelValidator;

trait ValidatesFormRequests
{
    /**
     * @param  Closure(): array<string, array<int, mixed>>  $rules
     */
    protected function assertRequestValidationFails(FormRequest $request, Closure $rules, string $field): void
    {
        $validator = $this->validatorForRequest($request, $rules);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey($field, $validator->errors()->toArray());
    }

    /**
     * @param  Closure(): array<string, array<int, mixed>>  $rules
     */
    protected function assertRequestValidationPasses(FormRequest $request, Closure $rules): void
    {
        $validator = $this->validatorForRequest($request, $rules);

        $this->assertFalse($validator->fails());
    }

    /**
     * @param  Closure(): array<string, array<int, mixed>>  $rules
     */
    protected function validatorForRequest(FormRequest $request, Closure $rules): LaravelValidator
    {
        $route = new Route($request->getMethod(), '/', static fn (): null => null);
        $route->bind($request);
        $request->setRouteResolver(static fn (): Route => $route);

        return Validator::make($request->all(), $rules());
    }
}
