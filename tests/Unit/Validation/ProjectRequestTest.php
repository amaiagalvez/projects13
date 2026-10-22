<?php

namespace Projects13\Tests\Unit\Validation;

use Illuminate\Support\Arr;
use Illuminate\Routing\Route;
use Projects13\Tests\TestCase;
use Illuminate\Validation\Rules\Unique;
use Basics13\Support\Validation\MaxLength;
use Projects13\Http\Requests\ProjectRequest;
use PHPUnit\Framework\Attributes\DataProvider;

class ProjectRequestTest extends TestCase
{
    public function test_name_rule_contains_unique_validation(): void
    {
        $request = new ProjectRequest;
        $rules = $request->rules();

        $nameRules = $rules['name'];
        $hasUniqueRule = array_filter($nameRules, static fn (mixed $rule): bool => $rule instanceof Unique);
        $this->assertNotEmpty($hasUniqueRule, 'name rule must contain unique validation');
    }

    public function test_authorize_returns_false_when_user_cannot_create(): void
    {
        $request = ProjectRequest::create('/', 'POST');
        $request->setUserResolver(fn () => null);
        $this->assertFalse($request->authorize());
    }

    public function test_name_and_start_date_are_required(): void
    {
        $request = ProjectRequest::create('/', 'POST');
        $validator = $this->validatorForRequest($request, static fn (): array => $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('name', $validator->errors()->toArray());
        $this->assertArrayHasKey('start_date', $validator->errors()->toArray());
        $this->assertArrayNotHasKey('end_date', $validator->errors()->toArray());
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function invalidNames(): array
    {
        return [
            'three characters' => ['Abc'],
            'not a string' => [['Invalid name']],
            'over the configured maximum' => [null],
        ];
    }

    #[DataProvider('invalidNames')]
    public function test_invalid_names_are_rejected(mixed $name): void
    {
        $name ??= str_repeat('a', MaxLength::string() + 1);
        $request = ProjectRequest::create('/', 'POST', ['name' => $name, 'start_date' => '2026-10-01']);

        $this->assertRequestValidationFails($request, static fn (): array => $request->rules(), 'name');
    }

    public function test_dates_must_use_the_iso_format(): void
    {
        $request = ProjectRequest::create('/', 'POST', ['start_date' => '01/10/2026', 'end_date' => 'not-a-date']);
        $validator = $this->validatorForRequest($request, static fn (): array => $request->rules());

        $this->assertArrayHasKey('start_date', $validator->errors()->toArray());
        $this->assertArrayHasKey('end_date', $validator->errors()->toArray());
    }

    public function test_end_date_cannot_precede_start_date(): void
    {
        $request = ProjectRequest::create('/', 'POST', ['start_date' => '2026-12-01', 'end_date' => '2026-11-30']);

        $this->assertRequestValidationFails($request, static fn (): array => $request->rules(), 'end_date');
    }

    /**
     * @return array<string, array{array<string, string|null>}>
     */
    public static function validDateRanges(): array
    {
        return [
            'no end date' => [['start_date' => '2026-10-01']],
            'null end date' => [['start_date' => '2026-10-01', 'end_date' => null]],
            'same day' => [['start_date' => '2026-10-01', 'end_date' => '2026-10-01']],
        ];
    }

    /**
     * Only the date rules: the name's unique rule needs the database and is covered in Feature.
     *
     * @param  array<string, string|null>  $dates
     */
    #[DataProvider('validDateRanges')]
    public function test_valid_date_ranges_are_accepted(array $dates): void
    {
        $request = ProjectRequest::create('/', 'POST', $dates);

        $this->assertRequestValidationPasses(
            $request,
            static fn (): array => Arr::only($request->rules(), ['start_date', 'end_date']),
        );
    }

    public function test_notes_cannot_exceed_long_text_maximum(): void
    {
        $request = ProjectRequest::create('/', 'POST', ['notes' => str_repeat('a', MaxLength::longText() + 1)]);

        $this->assertRequestValidationFails($request, static fn (): array => $request->rules(), 'notes');
    }

    public function test_prepare_for_validation_trims_name(): void
    {
        $request = ProjectRequest::create('/', 'POST', ['name' => '  Trimmed name  ']);
        $route = new Route($request->getMethod(), '/', static fn (): null => null);
        $route->bind($request);
        $request->setRouteResolver(static fn (): Route => $route);

        (new \ReflectionMethod($request, 'prepareForValidation'))->invoke($request);

        $this->assertSame('Trimmed name', $request->input('name'));
    }
}
