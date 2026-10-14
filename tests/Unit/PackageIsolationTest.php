<?php

namespace Projects13\Tests\Unit;

use Generator;
use SplFileInfo;
use Projects13\Models\Project;
use RecursiveIteratorIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;

/**
 * The package has to be usable without `customers13` installed, and the only thing that proves it
 * is the absence of every reference to it. Composer's lockfile does not prove anything here: the
 * host resolves a single dependency graph for all of them, so a reference that only fails at
 * runtime still installs cleanly.
 */
class PackageIsolationTest extends TestCase
{
    /**
     * Directories whose contents are production code. Tests, fixtures and vendor are excluded on
     * purpose: a test is allowed to know what the integration adds.
     *
     * @var list<string>
     */
    private const PRODUCTION_DIRECTORIES = ['src', 'resources', 'routes', 'config'];

    /**
     * @var array<non-empty-string, string>
     */
    private const FORBIDDEN = [
        'App\\' => 'App\\',
        'CustomerProjects13' => 'CustomerProjects13',
        'Customers13' => 'Customers13',
        'CUM_customers' => 'CUM_customers',
        'customer_id' => 'customer_id',
        "route('customers." => "route('customers.",
        "to_route('customers." => "to_route('customers.",
        "'customers.index'" => "'customers.index'",
        "'customers.store'" => "'customers.store'",
        "'customers.update'" => "'customers.update'",
        "'customers.destroy'" => "'customers.destroy'",
        "'customers.archive'" => "'customers.archive'",
        "'customers.archived" => "'customers.archived",
        "'customers.trash" => "'customers.trash",
        "'customers.options'" => "'customers.options'",
    ];

    public function test_production_code_never_references_the_customers_package(): void
    {
        $offences = [];

        foreach ($this->productionFiles() as $file) {
            foreach ($this->offencesIn($file) as $offence) {
                $offences[] = $offence;
            }
        }

        $this->assertSame(
            [],
            $offences,
            'projects13 must work without customers13 installed, but its production code still '
            .'names it. Move whatever needs a customer into the integration package, and keep the '
            .'contracts in Projects13\\Contracts\\ProjectValidationIntegration, '
            .'ProjectQueryIntegration, ProjectDisplayIntegration as the only seams.'.PHP_EOL
            .PHP_EOL.implode(PHP_EOL, $offences),
        );
    }

    public function test_the_isolation_test_scans_something(): void
    {
        // A scan that silently stopped finding files would make the assertion above pass forever.
        $this->assertGreaterThan(20, iterator_count($this->productionFiles()));
    }

    public function test_the_package_tests_do_not_require_customers_or_the_bridge(): void
    {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(dirname(__DIR__).'/'),
        );

        foreach ($files as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php' || $file->getPathname() === __FILE__) {
                continue;
            }

            $source = file_get_contents($file->getPathname());
            $this->assertIsString($source);
            $this->assertStringNotContainsString('Customers13\\', $source, $file->getPathname());
            $this->assertStringNotContainsString('CustomerProjects13\\', $source, $file->getPathname());
            $this->assertStringNotContainsString('customer_id', $source, $file->getPathname());
        }
    }

    /**
     * The model is where a forbidden relation would first show up, and this reads the contract
     * rather than the model: a project has no customer until an integration gives it one.
     */
    public function test_the_project_model_declares_no_customer(): void
    {
        $this->assertFalse(method_exists(Project::class, 'customer'));
        $this->assertFalse(method_exists(Project::class, 'fullName'));
    }

    /**
     * @return Generator<int, SplFileInfo>
     */
    private function productionFiles(): iterable
    {
        $root = dirname(__DIR__, 2);

        foreach (self::PRODUCTION_DIRECTORIES as $directory) {
            $path = $root.'/'.$directory;

            if (! is_dir($path)) {
                continue;
            }

            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));

            foreach ($files as $file) {
                if ($file->isFile() && in_array($file->getExtension(), ['php', 'js', 'json'], true)) {
                    yield $file;
                }
            }
        }
    }

    /**
     * @return list<string>
     */
    private function offencesIn(SplFileInfo $file): array
    {
        $contents = (string) file_get_contents($file->getPathname());
        $relative = str_replace(dirname(__DIR__, 2).'/', '', $file->getPathname());
        $offences = [];

        foreach (self::FORBIDDEN as $needle => $_) {
            $line = $this->lineWith($contents, $needle);

            if ($line !== null) {
                $offences[] = sprintf('%s:%d references "%s"', $relative, $line, $needle);
            }
        }

        return $offences;
    }

    private function lineWith(string $contents, string $needle): ?int
    {
        $position = strpos($contents, $needle);

        return $position === false
            ? null
            : substr_count($contents, PHP_EOL, 0, $position) + 1;
    }
}
