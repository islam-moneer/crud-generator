<?php
namespace Appzcoder\CrudGenerator\Commands;

use File;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Str;
use Illuminate\Console\GeneratorCommand;

class CrudTestCommand extends GeneratorCommand
{
    use WithFaker;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'crud:test
                            {name : The name of the controler.}
                            {--crud-name= : The name of the Crud.}
                            {--model-name= : The name of the Model.}
                            {--model-namespace= : The namespace of the Model.}
                            {--controller-namespace= : Namespace of the controller.}
                            {--view-path= : The name of the view path.}
                            {--fields= : Field names for the form & migration.}
                            {--validations= : Validation rules for the fields.}
                            {--route-group= : Prefix of the route group.}
                            {--pagination=25 : The amount of models per page for index pages.}
                            {--force : Overwrite already existing controller.}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new resource controller.';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Test';

    protected $modelName;
    protected $modelNameCap;
    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function getStub()
    {
        return config('crudgenerator.custom_template')
            ? config('crudgenerator.path') . '/test.stub'
            : __DIR__ . '/../stubs/test.stub';
    }


    /**
     * Build the model class with the given name.
     *
     * @param  string  $name
     *
     * @return string
     */
    public function handle()
    {
        $stub = $this->files->get($this->getStub());

        $viewPath = $this->option('view-path') ? $this->option('view-path') . '.' : '';
        $crudName = strtolower($this->option('crud-name'));
        $crudNameSingular = Str::singular($crudName);
        $modelName = $this->option('model-name');
        $modelNamespace = $this->option('model-namespace');
        $routeGroup = ($this->option('route-group')) ? $this->option('route-group') : '';
        $routePrefix = ($this->option('route-group')) ? $this->option('route-group') . '.' : '';
        $routePrefixCap = ucfirst($routePrefix);
        $viewName = Str::snake($this->argument('name'), '-');
        $viewPath = $this->option('view-path');
        $fields = $this->option('fields');

        $validations = rtrim($this->option('validations'), ';');


        $fieldsArray = explode(';', $fields);

        // Exclude inputs to make inputs
        $inputs = $this->replaceInputsSnippet($stub, $fieldsArray);

        $name = $this->argument('name');
        $file = config('crudgenerator.path') . '/test.stub';
        $newFile = 'tests/Feature/' . $name . 'Test.php';

        if (!File::copy($file, $newFile)) {
            echo "failed to copy $file...\n";
        }
        $vars = [
            'modelNamespace' => 'App',
            'modelName' => $modelName,
            'DummyClass' => $name . 'Test',
            'crudName' => $crudName,
            'crudNameSingular' => $crudNameSingular,
            'routePrefix' => $routePrefix,
            'routeGroup' => $routeGroup,
            'viewName' => $viewName
            ];
        // Create Factory
        $test = $this->call('make:factory', ['name' =>  $modelName . 'Factory', '--model' => $modelName]);
        $modelFactory = "database/factories/{$modelName}Factory.php";
        $this->replaceFactoryData($modelFactory, $inputs);

        $this->replaceInputs($newFile, $inputs);
        $this->templateVars($newFile, $vars);
        return $this->info('test created successfully!');
    }

    /**
     * Update specified values between delimiter with real values
     *
     * @param $file
     * @param $vars
     */
    protected function templateVars($file, $vars)
    {
        foreach ($vars as $key => $var) {

            File::put($file, str_replace('{{' . $key . '}}', $var, File::get($file)));
        }
    }

    protected function replaceInputsSnippet(&$stub, $fieldsArray)
    {
        $inputs = [];
        $types = [
            'string' => <<<EOT
                \$this->faker->name
            EOT,
            'text' => <<<EOT
                \$this->faker->text
            EOT,
            'textarea' => <<<EOT
                \$this->faker->text
            EOT,
            'password' => <<<EOT
                \$this->faker->password
            EOT,
            'email' =>  <<<EOT
                \$this->faker->unique()->safeEmail
            EOT,
            'number' => <<<EOT
                \$this->faker->randomNumber()
            EOT,
            'integer' => <<<EOT
                \$this->faker->randomNumber()
            EOT,
            'date' => <<<EOT
                \$this->faker->date()
            EOT,
            'datetime' => <<<EOT
                \$this->faker->dateTime()
            EOT,
            'time' => <<<EOT
                \$this->faker->time()
            EOT,
            'file' => <<<EOT
                \$this->faker->file()
            EOT,
            'radio' => <<<EOT
                \$this->faker->randomElement([true, false])
            EOT
        ];

        foreach ($fieldsArray as $input) {
            $name = explode('#', $input);
            if (array_key_exists($name[1], $types)) {
                $inputs[$name[0]] = $types[$name[1]];
            } elseif ($name[1] == 'select') {
                $selections = json_decode(str_replace('options=', '', $name[2]), true);
                $text = implode('","', array_keys($selections));
                $inputs[$name[0]] = <<<EOD
                \$this->faker->randomElement(["{$text}"])
                EOD;
            }
        }

        return $inputs;
    }

    /**
     * replace input snippet with array of mock data
     * @param $file
     * @param $inputs
     */
    protected function replaceInputs($file, $inputs)
    {
        $text = '';
        foreach ($inputs as $key => $input) {
            $text .= "'{$key}' => {$input},\n";
        }
        File::put($file, str_replace('{{inputs}}', $text, File::get($file)));
    }

    /**
     * replace comment with faker data
     * @param $file
     * @param $inputs
     */
    protected function replaceFactoryData($file, $inputs)
    {
        $text = '';
        foreach ($inputs as $key => $input) {
            $text .= "'{$key}' => {$input},\n";
        }
        File::put($file, str_replace('//', $text, File::get($file)));
    }
}
