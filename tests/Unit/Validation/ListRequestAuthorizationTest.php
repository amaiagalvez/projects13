<?php

namespace Projects13\Tests\Unit\Validation;

use App\Models\User;
use Projects13\Tests\TestCase;
use Illuminate\Support\Facades\Gate;
use Projects13\Http\Requests\EpicListRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use Projects13\Http\Requests\ProjectListRequest;
use Projects13\Http\Requests\TimelineListRequest;
use Projects13\Http\Requests\ProjectSelectOptionsRequest;

class ListRequestAuthorizationTest extends TestCase
{
    /**
     * @return array<string, array{0: class-string<EpicListRequest|ProjectListRequest|TimelineListRequest|ProjectSelectOptionsRequest>}>
     */
    public static function requests(): array
    {
        return [
            'epics' => [EpicListRequest::class],
            'projects' => [ProjectListRequest::class],
            'timeline' => [TimelineListRequest::class],
            'project options' => [ProjectSelectOptionsRequest::class],
        ];
    }

    /**
     * @param  class-string<EpicListRequest|ProjectListRequest|TimelineListRequest|ProjectSelectOptionsRequest>  $requestClass
     */
    #[DataProvider('requests')]
    public function test_guests_cannot_list_records(string $requestClass): void
    {
        $request = new $requestClass;

        $this->assertFalse($request->authorize());
    }

    /**
     * @param  class-string<EpicListRequest|ProjectListRequest|TimelineListRequest|ProjectSelectOptionsRequest>  $requestClass
     */
    #[DataProvider('requests')]
    public function test_list_permission_is_checked(string $requestClass): void
    {
        Gate::before(static fn (): bool => false);
        $request = new $requestClass;
        $request->setUserResolver(static fn (): User => new User);

        $this->assertFalse($request->authorize());
    }
}
