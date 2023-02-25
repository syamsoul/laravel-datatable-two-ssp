<?php

namespace SoulDoit\DataTableTwo\Console;

use Illuminate\Console\Concerns\CreatesMatchingTest;
use Illuminate\Console\GeneratorCommand;

class MakeDatatableCommand extends GeneratorCommand
{
    use CreatesMatchingTest;

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'make:datatable {--E|example}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new datatable class';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'DataTable';

    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function getStub()
    {
        $is_provide_example = $this->argument('example');
        
        if($is_provide_example) return $this->resolveStubPath('/stubs/datatable-example.stub');
        
        return $this->resolveStubPath('/stubs/datatable.stub');
    }

    /**
     * Resolve the fully-qualified path to the stub.
     *
     * @param  string  $stub
     * @return string
     */
    protected function resolveStubPath($stub)
    {
        return file_exists($customPath = $this->laravel->basePath(trim($stub, '/')))
                        ? $customPath
                        : __DIR__.$stub;
    }

    /**
     * Get the default namespace for the class.
     *
     * @param  string  $rootNamespace
     * @return string
     */
    protected function getDefaultNamespace($rootNamespace)
    {
        return $rootNamespace.'\DataTables';
    }
}
