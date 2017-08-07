<?php

namespace Amet\SimpleORM\Commands;

use File;
use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class GeneratorModelInteractive extends Command
{

    protected $soft_delete =  0;
    protected $methods =  "";
    protected $model_name =  "";
    protected $table_name =  "";
    protected $file_name =  "";
    protected $framework =  "laravel";
    protected $version =  "";
    protected $table_column =  [];
    protected $default_key =  null;
    protected $migration_enable =  false;
    protected $ask_migration_enable_value =  false;
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'simple_orm:interactive';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Interactive Publishes Models';

    /**
     * Execute the command.
     *
     * @return void
     */
    public function handle()
    {
        $v = app()->version();
        $version = explode(" ", $v);
        $this->version = $version[0];
        if ($version[0] == "Lumen") {
            $this->framework = "lumen";
            $this->version =  str_replace(")", "", str_replace("(", "", $version[1]));
        }

        $this->start_command();
        
    } 

    private function start_command()
    {
        $this->methods = "";
        $this->model_name = "";
        $this->table_name = "";
        $this->file_name = "";
        $this->table_column = [];
        $this->default_key = null;
        $this->migration_enable = false;
        $this->ask_model_name();
        $this->ask_soft_delete();
        $this->ask_default_key();

        $this->ask_migration_enable();
        
        $this->execute_call();
        $this->finish();
    }  

    
    private function ask_model_name()
    {
        $model_name = $this->ask('Model Name ?', false);
        if (!$model_name) {
            $this->ask_model_name();
        } else  {
            $this->model_name = $model_name;
        }

    }

    private function ask_soft_delete()
    {
        $soft_delete = $this->ask('use soft delete (blank if not supplied) ?', false);
        if ($soft_delete) {
            $this->soft_delete = true;
        }
    }

    private function ask_default_key()
    {
        $default_key = $this->ask('Default key (blank if not supplied) ?', false);
        if ($default_key) {
            $this->default_key = $default_key;
        }   
    }

    private function ask_migration_enable()
    {
        if ($this->confirm('Enable Migration ?'))
        {
            $this->migration_enable = true;
            $this->ask_migration_enable_value = true;
        } 
    }

    private function ask_generate_controller()
    {
        if ($this->confirm('Enable Generate Controller ?'))
        {
            $this->controller_file();
            // if ($this->confirm('Enable Admin Generate Controller ?'))
            // {
            //     $this->admin_controller_file();
            // }
            // if ($this->confirm('Enable Api Generate Controller ?'))
            // {
            //     $this->api_controller_file();
            // }
        } 
       
    }

  
    private function getStubPath()
    {
        return __DIR__.'/../stubs';
    }

    private function ask_add_column()
    {
       

        if ($this->confirm('Add Another Column ?'))
        {
            $this->add_migration_column();
            $this->ask_add_column();
        } else {
            $this->migration_file();
        }

        
    }
  
    private function add_migration_column()
    {
        $column = [];
        $column["length"] = null;
        $column["default"] = null;
        $column["unsigned"] = null;
        $column["nullable_column"] = null;
        $column["default_value"] = null;
        $column["name"] = $this->ask("Add Column Name ?","");
        $column["type"] = $this->choice("Choose Column Type ?",
                        ["bigIncrements","bigInteger","binary","boolean","char","date","dateTime","dateTimeTz","decimal","double","enum","float","increments","integer","ipAddress","json","jsonb","longText","macAddress","mediumIncrements","mediumInteger","mediumText","morphs","nullableMorphs","nullableTimestamps","rememberToken","smallIncrements","smallInteger","softDeletes","string","text","time","timeTz","tinyInteger","timestamp","timestampTz","timestamps","timestampsTz","unsignedBigInteger","unsignedInteger","unsignedMediumInteger","unsignedSmallInteger","unsignedTinyInteger","uuid"]);
        $valuable_type = ["char","decimal","double","enum","float","string"];

        if (in_array($column['type'], $valuable_type)) {
            $column["length"] = $this->ask("Add Column Length ?",null);
        }

        if ($column['type'] == "integer") {
            $column["unsigned"] = $this->choice("is Column unsigned ?",["Yes","No"]);
        }

        $column["default"] = $this->choice("is Column has default value ?", ["Yes","No"]);
        if ($column["default"] == "Yes" || $column["default"] != "No") {
            $column["default_value"] = $this->ask("Add Column Default value ?",null);
        }
        $column["nullable_column"] = $this->choice("is Nullable Column ?", ["Yes","No"]);
        $this->table_column[] = $column;
    }


    private function migration_file()
    {
        $template = $this->getStubPath().'/Migration.stub';
        $file_name = date('Y_m_d_His')."_Create".ucfirst(camel_case($this->model_name)).'Table'.'.php';
        $path = database_path().'/migrations/';
        try {
            $fh = fopen($template,'r+');
            $content = "";
            $line_number = 1;
            while(!feof($fh)) {
                $line = fgets($fh);
                $line = str_replace("ClassName", "Create".ucfirst(camel_case($this->model_name)).'Table', $line);
                $line = str_replace("table_name",str_plural(strtolower(snake_case($this->model_name))), $line);
                if ($line_number == 17) {
                    foreach ($this->table_column as $key => $table_column) {
                        $line .= "\t".'        $table->'.$table_column['type'].'("'.$table_column['name'].'"';
                        if ($table_column['length']) {
                            $line .= ", ".$table_column['length'];
                        }
                        $line .= ')';
                        if ($table_column['unsigned'] == "Yes") {
                            $line .= '->unsigned()';
                        }
                        if ($table_column['default'] == "Yes") {
                            if ($table_column['type'] == "string") {
                                $table_column['default_value'] = '"'.$table_column['default_value'].'"';
                            }
                            $line .= '->default('.$table_column['default_value'].')';
                        }
                        if ($table_column['nullable_column'] == "Yes") {
                            $line .= '->nullable()';
                        }
                        $line .= ';'.PHP_EOL;
                    }
                    if ($this->soft_delete) {
                        $line .= "\t".'        $table->softDeletes();'.PHP_EOL;
                    }
                }
                $content .= $line;
                $line_number++;
            }
            fclose($fh);
            file_put_contents($path.$file_name, $content);
            $this->info('Created Migration: '.$file_name);
        } catch (\Exception $e) {
            $this->error($e->getMessage());
        }
    }

    private function controller_file()
    {
        $route_template = "Route::resource('".str_plural(strtolower(snake_case($this->model_name)))."', '".ucfirst(camel_case($this->model_name)).'Controller'."');";
        if ($this->framework == "lumen") {
            $route_template = '$app->resource("'.str_plural(strtolower(snake_case($this->model_name))).'", "\App\Http\Controllers\\'.ucfirst(camel_case($this->model_name))."Controller".'");';
        }
        $route_path = base_path().'/routes/web.php';
        // if (preg_match(pattern, subject))
        if (!version_compare($this->version, '5.2')) {
            $route_path = app_path().'/Http/routes.php';
        }
        $template = $this->getStubPath().'/Controller.stub';
        $file_name = ucfirst(camel_case($this->model_name)).'Controller'.'.php';
        $path_controller = app()->path().'/Http/Controllers/';
        $path_view = resource_path('views/'.str_plural(strtolower(snake_case($this->model_name))));
        try {
            $fh = fopen($template,'r+');
            $content = "";
            $line_number = 1;
            while(!feof($fh)) {
                $line = fgets($fh);
                $line = str_replace("sampleController", ucfirst(camel_case($this->model_name)).'Controller', $line);
                $line = str_replace("samples",str_plural(strtolower(snake_case($this->model_name))), $line);
                $line = str_replace("Model",ucfirst(camel_case($this->model_name)), $line);
                $content .= $line;
                $line_number++;
            }
            
            fclose($fh);
            if (!file_exists($path_view)) {
                mkdir($path_view,0777,true);
            }
            file_put_contents($path_controller.$file_name, $content);
            $this->info('Created Controller: '.$file_name);

            file_put_contents($path_view.'/'.'index.blade.php', "open file : app/Http/Controllers/".ucfirst(camel_case($this->model_name)).'Controller'.'.php');
            file_put_contents($path_view.'/'.'create.blade.php', "open file : app/Http/Controllers/".ucfirst(camel_case($this->model_name)).'Controller'.'.php');
            file_put_contents($path_view.'/'.'show.blade.php', "open file : app/Http/Controllers/".ucfirst(camel_case($this->model_name)).'Controller'.'.php');
            file_put_contents($path_view.'/'.'edit.blade.php', "open file : app/Http/Controllers/".ucfirst(camel_case($this->model_name)).'Controller'.'.php');
            $this->info('Created View: '.$path_view.'/'.'index.blade.php');
            $this->info('Created View: '.$path_view.'/'.'create.blade.php');
            $this->info('Created View: '.$path_view.'/'.'show.blade.php');
            $this->info('Created View: '.$path_view.'/'.'edit.blade.php');
            file_put_contents($route_path, PHP_EOL.$route_template.PHP_EOL , FILE_APPEND | LOCK_EX);
            $this->info('Add Route: '.$route_path);
        } catch (\Exception $e) {
            $this->error($e->getMessage());
        }
    }

    private function execute_call()
    {
        $attributes = ['name' => $this->model_name];
        if ($this->soft_delete) {
            $attributes['--soft_delete'] = 1;            
        }

        if ($this->default_key) {
            $attributes['--default_key'] = $this->default_key;            
        }

        if ($this->migration_enable) {
            // $attributes['--migration'] = 1;   
            $this->add_migration_column(); 
            $this->ask_add_column();  
            $this->ask_generate_controller();

        }
            $this->call('simple_orm:model', $attributes);

        
    }

    private function ask_migrate()
    {
        

        if ($this->confirm('Do You Want To Migrate DB?'))
        {
            $this->call('migrate');
        } else {
            exit;
        }
    }

    private function exit()
    {
        if ($this->ask_migration_enable_value) {
            $this->ask_migrate();
        } else {
            exit;
        }
    }

    private function finish()
    {
        

        if ($this->confirm('Create Another Model?'))
        {
            $this->start_command();
        } else {
            $this->exit();
        }

       
    }

}