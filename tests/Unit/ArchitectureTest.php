<?php

namespace Projects13\Tests\Unit;

use ReflectionClass;
use ReflectionMethod;
use Projects13\Models\Epic;
use Projects13\Models\Project;
use Projects13\Tests\TestCase;
use Projects13\Models\EpicComment;
use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;

class ArchitectureTest extends TestCase
{
    /**
     * Resource name (singular) => model class.
     *
     * @var array<string, class-string>
     */
    private const RESOURCES = [
        'Project' => Project::class,
        'Epic' => Epic::class,
    ];

    private const FORM_REQUEST_ENDPOINTS = ['index', 'store', 'update', 'restore', 'comment'];

    public function test_every_resource_ships_the_complete_set(): void
    {
        foreach (self::RESOURCES as $resource => $modelClass) {
            $plural = strtolower($resource).'s';

            $classes = [
                "Projects13\\Http\\Controllers\\{$resource}Controller",
                "Projects13\\Http\\Controllers\\{$resource}TrashController",
                "Projects13\\Http\\Requests\\{$resource}Request",
                "Projects13\\Http\\Requests\\{$resource}ListRequest",
                "Projects13\\Http\\Requests\\{$resource}RestoreRequest",
                "Projects13\\Policies\\{$resource}Policy",
                "Projects13\\Queries\\{$resource}s\\{$resource}ListQuery",
                "Projects13\\Transformers\\{$resource}ListTransformer",
                "Projects13\\Database\\Factories\\{$resource}Factory",
            ];

            foreach ($classes as $class) {
                $this->assertTrue(class_exists($class), "Missing class {$class}");
            }

            $model = new ReflectionClass($modelClass);
            $this->assertNotEmpty(
                $model->getAttributes(UsePolicy::class),
                "{$resource} must declare #[UsePolicy]",
            );

            foreach (['list', 'form'] as $view) {
                $this->assertFileExists(dirname(__DIR__, 2)."/resources/views/{$plural}/{$view}.blade.php");
            }
        }
    }

    public function test_resource_view_abilities_match_show_routes(): void
    {
        foreach (self::RESOURCES as $resource => $modelClass) {
            $policy = new ReflectionClass("Projects13\\Policies\\{$resource}Policy");
            $hasShowRoute = Route::has(strtolower($resource).'s.show');

            $this->assertSame(
                $hasShowRoute,
                $policy->hasMethod('view'),
                "{$resource}Policy::view must exist exactly when its show route exists",
            );
        }
    }

    public function test_soft_deletable_models_declare_a_policy(): void
    {
        foreach ([Project::class, Epic::class] as $class) {
            if (! in_array(SoftDeletes::class, class_uses_recursive($class), true)) {
                continue;
            }

            $model = new ReflectionClass($class);

            $this->assertNotEmpty(
                $model->getAttributes(UsePolicy::class),
                "{$class} must declare #[UsePolicy] because it uses SoftDeletes",
            );
        }
    }

    /**
     * epic_comments keeps deleted_at and active columns that no model uses: EpicComment declares
     * neither SoftDeletes nor the flag. They are reserved, so this test records the fact and will
     * fail if a model starts relying on them without this being revisited.
     *
     * deleted_by is reserved the same way: TracksAuditColumns only registers its deletion half on a
     * soft-deleting model, so a comment is deleted outright and never records who deleted it.
     */
    public function test_epic_comments_columns_are_reserved_and_unused(): void
    {
        $comment = new EpicComment;

        $this->assertNotContains(SoftDeletes::class, class_uses_recursive($comment));
        $this->assertArrayNotHasKey('active', $comment->getCasts());
    }

    public function test_controllers_stay_thin_and_free_of_raw_input(): void
    {
        foreach ($this->controllerFiles() as $file) {
            $source = (string) file_get_contents($file);

            $this->assertDoesNotMatchRegularExpression(
                '/DB::(?!transaction\s*\()/',
                $source,
                "{$file} may use DB::transaction() but no other DB facade calls",
            );
            $this->assertStringNotContainsString('Schema::', $source, "{$file} must not touch Schema::");
            $this->assertDoesNotMatchRegularExpression('/\b(?:dd|dump)\s*\(/', $source, "{$file} contains dd/dump");
            $this->assertStringNotContainsString(
                '->paginate(',
                $source,
                "{$file} must paginate in app/Queries, not in the controller",
            );
            $this->assertDoesNotMatchRegularExpression(
                '/\$request->(?:input|all|only|except)\s*\(/',
                $source,
                "{$file} reads raw input; use a FormRequest and validated data",
            );
            $this->assertDoesNotMatchRegularExpression(
                '/(?<!\w)Request\s+\$request\b/',
                $source,
                "{$file} type-hints the base Request; every input endpoint needs a FormRequest",
            );
        }
    }

    public function test_input_endpoints_declare_a_form_request(): void
    {
        foreach ($this->controllerFiles() as $file) {
            $class = 'Projects13\\Http\\Controllers\\'.basename($file, '.php');

            if (! class_exists($class)) {
                $this->fail("Missing class {$class}");
            }

            $reflection = new ReflectionClass($class);

            /** @var ReflectionMethod $method */
            foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                if ($method->getDeclaringClass()->getName() !== $reflection->getName()) {
                    continue;
                }

                if (! in_array($method->getName(), self::FORM_REQUEST_ENDPOINTS, true)) {
                    continue;
                }

                $declaresFormRequest = array_filter(
                    $method->getParameters(),
                    fn ($parameter): bool => $parameter->hasType()
                        && is_a((string) $parameter->getType(), FormRequest::class, true),
                );

                $this->assertNotEmpty(
                    $declaresFormRequest,
                    "{$class}::{$method->getName()} must declare a FormRequest (user input must be validated)",
                );
            }
        }
    }

    public function test_views_follow_the_canonical_patterns(): void
    {
        foreach (['projects', 'epics'] as $plural) {
            $list = (string) file_get_contents(dirname(__DIR__, 2)."/resources/views/{$plural}/list.blade.php");
            $form = (string) file_get_contents(dirname(__DIR__, 2)."/resources/views/{$plural}/form.blade.php");

            $this->assertStringContainsString(
                '<x-basics13::list.table',
                $list,
                "{$plural}/list.blade.php must use <x-basics13::list.table> instead of its own table markup",
            );
            $this->assertStringContainsString(
                '<x-basics13::forms.tracked-resource',
                $form,
                "{$plural}/form.blade.php must use <x-basics13::forms.tracked-resource>",
            );
        }
    }

    public function test_form_requests_expose_rules_and_authorize(): void
    {
        $requestFiles = [
            ...(glob(dirname(__DIR__, 2).'/src/Http/Requests/*.php') ?: []),
        ];

        foreach ($requestFiles as $file) {
            if (! str_ends_with($file, 'Request.php')) {
                continue;
            }

            if (preg_match('/^namespace\s+([^;]+);/m', (string) file_get_contents($file), $namespace) !== 1) {
                $this->fail("Missing namespace declaration in {$file}");
            }

            $class = $namespace[1].'\\'.basename($file, '.php');

            if (! class_exists($class)) {
                $this->fail("Missing class {$class}");
            }

            $reflection = new ReflectionClass($class);

            $this->assertTrue(
                $reflection->isSubclassOf(FormRequest::class),
                "{$reflection->getName()} must extend FormRequest",
            );

            if ($reflection->isAbstract()) {
                continue;
            }

            foreach (['rules', 'authorize'] as $method) {
                $this->assertTrue(
                    $reflection->hasMethod($method) && $reflection->getMethod($method)->isPublic(),
                    "{$reflection->getName()} must define public {$method}()",
                );
            }
        }
    }

    /**
     * @return array<int, string>
     */
    private function controllerFiles(): array
    {
        $files = [
            ...(glob(dirname(__DIR__, 2).'/src/Http/Controllers/*.php') ?: []),
        ];

        return array_values(array_filter(
            $files,
            fn (string $file): bool => basename($file) !== 'Controller.php',
        ));
    }
}
