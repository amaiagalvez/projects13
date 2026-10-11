<?php

namespace Projects13\Tests\Unit\Validation;

use Illuminate\Routing\Route;
use Projects13\Tests\TestCase;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\Unique;
use Illuminate\Support\Facades\Validator;
use Projects13\Http\Requests\EpicRequest;
use Basics13\Support\Validation\MaxLength;

class EpicRequestTest extends TestCase
{
    public function test_project_attribute_is_translated_by_the_resource_request(): void
    {
        app()->setLocale('eu');
        $request = new EpicRequest;
        $validator = Validator::make([], ['project_id' => ['required']], [], $request->attributes());

        $this->assertSame(
            __('validation.required', ['attribute' => __('Project')]),
            $validator->errors()->first('project_id'),
        );
    }

    public function test_name_is_required(): void
    {
        $request = EpicRequest::create('/', 'POST', ['name' => '']);

        $this->assertRequestValidationFails($request, static fn (): array => $request->rules(), 'name');
    }

    public function test_name_must_be_a_string(): void
    {
        $request = EpicRequest::create('/', 'POST', ['name' => ['Ane Bezeroa']]);

        $this->assertRequestValidationFails($request, static fn (): array => $request->rules(), 'name');
    }

    public function test_name_cannot_exceed_the_configured_maximum(): void
    {
        $name = str_repeat('a', MaxLength::string() + 1);
        $request = EpicRequest::create('/', 'POST', ['name' => $name]);

        $this->assertRequestValidationFails($request, static fn (): array => $request->rules(), 'name');
    }

    public function test_name_must_be_at_least_four_characters(): void
    {
        $request = EpicRequest::create('/', 'POST', ['name' => 'Abc']);

        $this->assertRequestValidationFails($request, static fn (): array => $request->rules(), 'name');
    }

    public function test_start_date_is_nullable_but_required_with_end_date(): void
    {
        $request = EpicRequest::create('/', 'POST', ['end_date' => '2026-10-01', 'start_date' => '']);

        $this->assertRequestValidationFails($request, static fn (): array => $request->rules(), 'start_date');
    }

    public function test_start_date_must_be_valid_format_when_provided(): void
    {
        $request = EpicRequest::create('/', 'POST', ['start_date' => 'not-a-date']);

        $this->assertRequestValidationFails($request, static fn (): array => $request->rules(), 'start_date');
    }

    public function test_end_date_is_nullable(): void
    {
        $request = EpicRequest::create('/', 'POST', ['end_date' => null]);

        $rules = $request->rules();
        $this->assertContains('nullable', $rules['end_date']);
    }

    public function test_end_date_must_be_valid_format_when_provided(): void
    {
        $request = EpicRequest::create('/', 'POST', ['end_date' => 'not-a-date']);

        $this->assertRequestValidationFails($request, static fn (): array => $request->rules(), 'end_date');
    }

    public function test_end_date_must_be_after_start_date(): void
    {
        $request = EpicRequest::create('/', 'POST', [
            'start_date' => '2026-10-01',
            'end_date' => '2026-09-30',
        ]);

        $this->assertRequestValidationFails($request, static fn (): array => $request->rules(), 'end_date');
    }

    public function test_project_id_is_required(): void
    {
        $request = EpicRequest::create('/', 'POST', ['project_id' => '']);

        $this->assertRequestValidationFails($request, static fn (): array => $request->rules(), 'project_id');
    }

    public function test_project_id_must_be_integer(): void
    {
        $request = EpicRequest::create('/', 'POST', ['project_id' => 'not-an-integer']);

        $this->assertRequestValidationFails($request, static fn (): array => $request->rules(), 'project_id');
    }

    public function test_notes_is_nullable_string_with_max_length(): void
    {
        $request = EpicRequest::create('/', 'POST', ['notes' => 123]);

        $this->assertRequestValidationFails($request, static fn (): array => $request->rules(), 'notes');
    }

    public function test_notes_cannot_exceed_long_text_maximum(): void
    {
        $notes = str_repeat('a', MaxLength::longText() + 1);
        $request = EpicRequest::create('/', 'POST', ['notes' => $notes]);

        $this->assertRequestValidationFails($request, static fn (): array => $request->rules(), 'notes');
    }

    public function test_reuse_deleted_name_is_sometimes_boolean(): void
    {
        $request = EpicRequest::create('/', 'POST', ['reuse_deleted_name' => 'not-a-boolean']);

        $this->assertRequestValidationFails($request, static fn (): array => $request->rules(), 'reuse_deleted_name');
    }

    public function test_prepare_for_validation_trims_name(): void
    {
        $request = EpicRequest::create('/', 'POST', ['name' => '  Trimmed Name  ']);
        $route = new Route($request->getMethod(), '/', static fn (): null => null);
        $route->bind($request);
        $request->setRouteResolver(static fn (): Route => $route);

        $reflection = new \ReflectionClass($request);
        $method = $reflection->getMethod('prepareForValidation');
        $method->setAccessible(true);
        $method->invoke($request);

        $this->assertSame('Trimmed Name', $request->input('name'));
    }

    public function test_prepare_for_validation_does_not_trim_non_string_name(): void
    {
        $request = EpicRequest::create('/', 'POST', ['name' => ['array']]);
        $route = new Route($request->getMethod(), '/', static fn (): null => null);
        $route->bind($request);
        $request->setRouteResolver(static fn (): Route => $route);

        $reflection = new \ReflectionClass($request);
        $method = $reflection->getMethod('prepareForValidation');
        $method->setAccessible(true);
        $method->invoke($request);

        $this->assertSame(['array'], $request->input('name'));
    }

    public function test_rules_return_expected_structure(): void
    {
        $request = new EpicRequest;
        $rules = $request->rules();

        $this->assertArrayHasKey('name', $rules);
        $this->assertArrayHasKey('start_date', $rules);
        $this->assertArrayHasKey('end_date', $rules);
        $this->assertArrayHasKey('project_id', $rules);
        $this->assertArrayHasKey('notes', $rules);
        $this->assertArrayHasKey('reuse_deleted_name', $rules);
        $this->assertCount(6, $rules);
    }

    public function test_name_rule_contains_required_string_min_max_unique(): void
    {
        $request = new EpicRequest;
        $rules = $request->rules();

        $nameRules = $rules['name'];
        $this->assertContains('required', $nameRules);
        $this->assertContains('string', $nameRules);
        $this->assertContains('min:4', $nameRules);
        $this->assertContains('max:'.MaxLength::string(), $nameRules);
    }

    public function test_start_date_rule_contains_nullable_required_with_date_format(): void
    {
        $request = new EpicRequest;
        $rules = $request->rules();

        $this->assertContains('nullable', $rules['start_date']);
        $this->assertContains('required_with:end_date', $rules['start_date']);
        $this->assertContains('date_format:Y-m-d', $rules['start_date']);
    }

    public function test_end_date_rule_contains_nullable_date_format_after(): void
    {
        $request = new EpicRequest;
        $rules = $request->rules();

        $this->assertContains('nullable', $rules['end_date']);
        $this->assertContains('date_format:Y-m-d', $rules['end_date']);
        $this->assertContains('after:start_date', $rules['end_date']);
    }

    public function test_project_id_rule_contains_required_integer_exists(): void
    {
        $request = new EpicRequest;
        $rules = $request->rules();

        $this->assertContains('required', $rules['project_id']);
        $this->assertContains('integer', $rules['project_id']);
    }

    public function test_name_rule_contains_unique_scoped_to_project(): void
    {
        $request = new EpicRequest;
        $rules = $request->rules();

        $nameRules = $rules['name'];
        $uniqueRules = array_filter($nameRules, static fn (mixed $rule): bool => $rule instanceof Unique);
        $this->assertNotEmpty($uniqueRules, 'name rule must contain unique rule');
    }

    public function test_project_id_rule_contains_exists_with_active_condition(): void
    {
        $request = new EpicRequest;
        $rules = $request->rules();

        $projectIdRules = $rules['project_id'];
        $existsRules = array_filter($projectIdRules, static fn (mixed $rule): bool => $rule instanceof Exists);
        $this->assertNotEmpty($existsRules, 'project_id rule must contain exists rule');

        // The rule should reference the projects table
        $existsRule = reset($existsRules);
        $this->assertInstanceOf(Exists::class, $existsRule);
        $this->assertStringContainsString('PRO_projects', (string) $existsRule);
    }

    public function test_authorize_returns_false_when_user_cannot_create(): void
    {
        $request = EpicRequest::create('/', 'POST');
        $request->setUserResolver(fn () => null);

        $this->assertFalse($request->authorize());
    }
}
