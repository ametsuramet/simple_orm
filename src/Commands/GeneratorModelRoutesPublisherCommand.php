<?php

namespace Amet\SimpleORM\Commands;

use File;
use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputArgument;

class GeneratorModelRoutesPublisherCommand extends Command
{
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
                    $line = $line."\t".'protected $table = "'.strtolower($name).'s'.'";'.PHP_EOL;
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

}