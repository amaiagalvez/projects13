<?php

namespace Projects13\Tests\Unit\Validation;

use Illuminate\Routing\Route;
use Projects13\Tests\TestCase;
use Basics13\Support\Validation\MaxLength;
use Projects13\Http\Requests\EpicCommentRequest;

class EpicCommentRequestTest extends TestCase
{
    public function test_body_is_required(): void
    {
        $request = EpicCommentRequest::create('/', 'POST', ['body' => '']);

        $this->assertRequestValidationFails($request, static fn (): array => $request->rules(), 'body');
    }

    public function test_body_must_be_a_string(): void
    {
        $request = EpicCommentRequest::create('/', 'POST', ['body' => ['not a string']]);

        $this->assertRequestValidationFails($request, static fn (): array => $request->rules(), 'body');
    }

    public function test_body_cannot_exceed_long_text_maximum(): void
    {
        $body = str_repeat('a', MaxLength::longText() + 1);
        $request = EpicCommentRequest::create('/', 'POST', ['body' => $body]);

        $this->assertRequestValidationFails($request, static fn (): array => $request->rules(), 'body');
    }

    public function test_notes_is_nullable_string_with_max_length(): void
    {
        $request = EpicCommentRequest::create('/', 'POST', ['notes' => 123]);

        $this->assertRequestValidationFails($request, static fn (): array => $request->rules(), 'notes');
    }

    public function test_notes_cannot_exceed_long_text_maximum(): void
    {
        $notes = str_repeat('a', MaxLength::longText() + 1);
        $request = EpicCommentRequest::create('/', 'POST', ['notes' => $notes]);

        $this->assertRequestValidationFails($request, static fn (): array => $request->rules(), 'notes');
    }

    public function test_prepare_for_validation_trims_body(): void
    {
        $request = EpicCommentRequest::create('/', 'POST', ['body' => '  Trimmed body  ']);
        $route = new Route($request->getMethod(), '/', static fn (): null => null);
        $route->bind($request);
        $request->setRouteResolver(static fn (): Route => $route);

        $reflection = new \ReflectionClass($request);
        $method = $reflection->getMethod('prepareForValidation');
        $method->setAccessible(true);
        $method->invoke($request);

        $this->assertSame('Trimmed body', $request->input('body'));
    }

    public function test_prepare_for_validation_does_not_trim_non_string_body(): void
    {
        $request = EpicCommentRequest::create('/', 'POST', ['body' => ['array']]);
        $route = new Route($request->getMethod(), '/', static fn (): null => null);
        $route->bind($request);
        $request->setRouteResolver(static fn (): Route => $route);

        $reflection = new \ReflectionClass($request);
        $method = $reflection->getMethod('prepareForValidation');
        $method->setAccessible(true);
        $method->invoke($request);

        $this->assertSame(['array'], $request->input('body'));
    }

    public function test_rules_return_expected_structure(): void
    {
        $request = new EpicCommentRequest;
        $rules = $request->rules();

        $this->assertArrayHasKey('body', $rules);
        $this->assertArrayHasKey('notes', $rules);
        $this->assertCount(2, $rules);
    }

    public function test_body_rule_contains_required_string_max(): void
    {
        $request = new EpicCommentRequest;
        $rules = $request->rules();

        $this->assertContains('required', $rules['body']);
        $this->assertContains('string', $rules['body']);
        $this->assertContains('max:'.MaxLength::longText(), $rules['body']);
    }

    public function test_notes_rule_contains_nullable_string_max(): void
    {
        $request = new EpicCommentRequest;
        $rules = $request->rules();

        $this->assertContains('nullable', $rules['notes']);
        $this->assertContains('string', $rules['notes']);
        $this->assertContains('max:'.MaxLength::longText(), $rules['notes']);
    }

    public function test_error_bag_is_comment(): void
    {
        $request = new EpicCommentRequest;
        $reflection = new \ReflectionClass($request);
        $property = $reflection->getProperty('errorBag');
        $property->setAccessible(true);
        $this->assertSame('comment', $property->getValue($request));
    }

    public function test_authorize_returns_false_when_user_cannot_comment(): void
    {
        $request = EpicCommentRequest::create('/', 'POST');
        $request->setUserResolver(fn () => null);

        $this->assertFalse($request->authorize());
    }
}
