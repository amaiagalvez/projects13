<?php

namespace Projects13\Tests\Unit\Validation;

use App\Models\User;
use Projects13\Tests\TestCase;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Projects13\Http\Requests\PlanningListRequest;

class PlanningListRequestTest extends TestCase
{
    /**
     * @return array<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function layouts(): array
    {
        return [
            'default' => [[], 'roadmap'],
            'roadmap' => [['view' => 'roadmap'], 'roadmap'],
            'timeline' => [['view' => 'timeline'], 'timeline'],
            'unknown' => [['view' => 'unknown'], 'roadmap'],
            'array' => [['view' => ['timeline']], 'roadmap'],
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    #[DataProvider('layouts')]
    public function test_layout_is_normalized_before_validation(array $input, string $expected): void
    {
        Gate::before(static fn (): bool => true);
        $request = PlanningListRequest::create('/', 'GET', $input);
        $request->setContainer(app());
        $request->setRedirector(app(Redirector::class));
        $request->setUserResolver(static fn (): User => new User);

        $request->validateResolved();

        $this->assertSame($expected, $request->view());
        $this->assertSame($expected, $request->validated('view'));
    }

    public function test_search_is_trimmed(): void
    {
        $request = PlanningListRequest::create('/', 'GET', ['search' => '  Website renewal  ']);

        $this->assertSame('Website renewal', $request->search());
    }

    public function test_invalid_search_is_rejected(): void
    {
        Gate::before(static fn (): bool => true);
        $request = PlanningListRequest::create('/', 'GET', ['search' => ['invalid']]);
        $request->setContainer(app());
        $request->setRedirector(app(Redirector::class));
        $request->setUserResolver(static fn (): User => new User);

        $this->expectException(ValidationException::class);

        $request->validateResolved();
    }

    public function test_guest_is_not_authorized(): void
    {
        $request = new PlanningListRequest;

        $this->assertFalse($request->authorize());
    }
}
