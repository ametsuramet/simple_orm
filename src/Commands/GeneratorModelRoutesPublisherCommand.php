<?php

namespace Amet\SimpleORM\Commands;

use File;
use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class GeneratorModelRoutesPublisherCommand extends Command
{

    protected $soft_delete =  0;
    protected $methods =  "";
    protected $default_key =  null;
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'simple_orm:model';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Publishes Models';

    /**
     * Execute the command.
     *
     * @return void
     */
    public function handle()
    {
        $arguments = $this->arguments();
        $name = $arguments['name'];
        $this->soft_delete = $this->option('soft_delete');
        $this->methods = $this->option('methods');
        $this->default_key = $this->option('default_key');
        
        $template = $this->getStubPath().'/Model.stub';
        try {
            $fh = fopen($template,'r+');

            $content = '';

            while(!feof($fh)) {
                $line = fgets($fh);
                if (preg_match('/DummyClass/', $line)) {
                    $line = str_replace("DummyClass", ucfirst($name), $line);
                }
                if (preg_match('/{/', $line)) {
                    $line = $line."\t".'protected $table = "'.str_plural(strtolower($name)).'";'.PHP_EOL;
                    if ($this->soft_delete) {
                        $line = $line."\t".'protected $soft_delete = true;'.PHP_EOL;
                    }
                    if ($this->default_key) {
                        $line = $line."\t".'protected $default_key = "'.$this->default_key.'";'.PHP_EOL;
                    }
                    $line = $line."\t".PHP_EOL;
                    if ($this->methods) {
                        foreach (explode(',',$this->methods) as $key => $method) {
                            $line = $line."\t".'protected function '.$method.'()'.PHP_EOL;
                            $line = $line."\t".'{'.PHP_EOL;
                            $line = $line."\t".''.PHP_EOL;
                            $line = $line."\t".'}'.PHP_EOL;
                        }
                    }
                }
                $content .= $line;
            }
            if (!file_exists(app_path('ORM'))) {
                mkdir(app_path('ORM'),0777,true);
            }
            file_put_contents(app_path('ORM').'/'.ucfirst($name).'.php', $content);
            $this->info(ucfirst($name).' Models Generated');
        } catch (\Exception $e) {
            $this->error($e->getMessage());
        }
        

    }   

    private function getStubPath()
    {
        return __DIR__.'/../stubs';
    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        return [
            ['name', InputArgument::REQUIRED, 'Model Name.'],
        ];
    }

    protected function getOptions()
    {
        return [
            ['soft_delete', null, InputOption::VALUE_OPTIONAL, 'Soft Delete option.', null],
            ['default_key', null, InputOption::VALUE_OPTIONAL, 'Default Key option.', null],
            ['methods', null, InputOption::VALUE_OPTIONAL, 'Generate Methods option.', null],
        ];
 
 
    }


}