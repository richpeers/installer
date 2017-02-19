<?php

namespace Laravel\Installer\Console;

use ZipArchive;
use RuntimeException;
use GuzzleHttp\Client;
use Symfony\Component\Process\Process;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class NewCommand extends Command
{
    /**
     * Configure the command options.
     *
     * @return void
     */
    protected function configure()
    {
        $this
            ->setName('new')
            ->setDescription('Create a new Laravel application.')
            ->addArgument('name', InputArgument::OPTIONAL)
            ->addOption('dev', null, InputOption::VALUE_NONE, 'Installs the latest "development" release')
            ->addOption('5.1', null, InputOption::VALUE_NONE, 'Installs the "5.1" release')
            ->addOption('5.2', null, InputOption::VALUE_NONE, 'Installs the "5.2" release')
            ->addOption('5.3', null, InputOption::VALUE_NONE, 'Installs the "5.3" release')
            ->addOption('phpstorm', null, InputOption::VALUE_NONE, 'Includes barryvdh/laravel-ide-helper and barryvdh/laravel-debugbar');
    }

    /**
     * Execute the command.
     *
     * @param  \Symfony\Component\Console\Input\InputInterface  $input
     * @param  \Symfony\Component\Console\Output\OutputInterface  $output
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (! class_exists('ZipArchive')) {
            throw new RuntimeException('The Zip PHP extension is not installed. Please install it and try again.');
        }

        $this->verifyApplicationDoesntExist(
            $directory = ($input->getArgument('name')) ? getcwd().'/'.$input->getArgument('name') : getcwd()
        );

        $output->writeln('<info>Crafting application...</info>');

        $version = $this->getVersion($input);

        $source = $this->getSource($version);

        $composer = $this->findComposer();

        if ($source === 'laravel') {

            $this->download($zipFile = $this->makeFilename(), $version)
                ->extract($zipFile, $directory)
                ->cleanUp($zipFile);

            $commands = [
                $composer.' install --no-scripts',
                $composer.' run-script post-root-package-install',
                $composer.' run-script post-install-cmd',
                $composer.' run-script post-create-project-cmd',
            ];

            if ($input->getOption('no-ansi')) {
                $commands = array_map(function ($value) {
                    return $value.' --no-ansi';
                }, $commands);
            }

            $process = new Process(implode(' && ', $commands), $directory, null, null, null);
        }

        if ($source === 'composer') {

            $name = $input->getArgument('name');

            $command = $composer . ' create-project laravel/laravel ' . $name . ' "' . $version . '.*"';

            $process = new Process($command, null, null, null, null);
        }

        if ('\\' !== DIRECTORY_SEPARATOR && file_exists('/dev/tty') && is_readable('/dev/tty')) {
            $process->setTty(true);
        }

        $process->run(function ($type, $line) use ($output) {
            $output->write($line);
        });

        if ($input->getOption('phpstorm')) {

            $commands = [
                $composer.' require --dev barryvdh/laravel-ide-helper',
                $composer.' require --dev barryvdh/laravel-debugbar',
            ];

            $process = new Process(implode(' && ', $commands), $directory, null, null, null);

            $output->writeln('<info>Installing phpstorm specific repositories...</info>');

            $process->run(function ($type, $line) use ($output) {
                $output->write($line);
            });

            $output->writeln('<comment>For BarryVDH php stuff, add the following code to your app/Providers/AppServiceProvider.php file, within the register() method:</comment>
            if ($this->app->environment() !== \'production\') {
                    $this->app->register(\Barryvdh\LaravelIdeHelper\IdeHelperServiceProvider::class);
                    $this->app->register(\Barryvdh\Debugbar\ServiceProvider::class,);
             }
             
             <comment>then run:</comment>
             php artisan clear-compiled | php artisan ide-helper:generate | php artisan optimize
             
             ');
        }

        $output->writeln('<comment>Application ready! Build something amazing.</comment>');
    }

    /**
     * Verify that the application does not already exist.
     *
     * @param  string  $directory
     * @return void
     */
    protected function verifyApplicationDoesntExist($directory)
    {
        if ((is_dir($directory) || is_file($directory)) && $directory != getcwd()) {
            throw new RuntimeException('Application already exists!');
        }
    }

    /**
     * Generate a random temporary filename.
     *
     * @return string
     */
    protected function makeFilename()
    {
        return getcwd().'/laravel_'.md5(time().uniqid()).'.zip';
    }

    /**
     * Download the temporary Zip to the given file.
     *
     * @param  string  $zipFile
     * @param  string  $version
     * @return $this
     */
    protected function download($zipFile, $version = 'master')
    {
        switch ($version) {
            case 'develop':
                $filename = 'latest-develop.zip';
                break;
            case 'master':
                $filename = 'latest.zip';
                break;
        }

        $response = (new Client)->get('http://cabinet.laravel.com/'.$filename);

        file_put_contents($zipFile, $response->getBody());

        return $this;
    }

    /**
     * Extract the Zip file into the given directory.
     *
     * @param  string  $zipFile
     * @param  string  $directory
     * @return $this
     */
    protected function extract($zipFile, $directory)
    {
        $archive = new ZipArchive;

        $archive->open($zipFile);

        $archive->extractTo($directory);

        $archive->close();

        return $this;
    }

    /**
     * Clean-up the Zip file.
     *
     * @param  string  $zipFile
     * @return $this
     */
    protected function cleanUp($zipFile)
    {
        @chmod($zipFile, 0777);

        @unlink($zipFile);

        return $this;
    }

    /**
     * Get the version that should be downloaded.
     *
     * @param  \Symfony\Component\Console\Input\InputInterface  $input
     * @return string
     */
    protected function getVersion(InputInterface $input)
    {
        if ($input->getOption('dev')) {
            return 'develop';
        }

        if ($input->getOption('5.1')) {
            return '5.1';
        }

        if ($input->getOption('5.2')) {
            return '5.2';
        }

        if ($input->getOption('5.3')) {
            return '5.3';
        }

        return 'master';
    }

    /**
     * Determine source of the download.
     *
     * @param $version
     * @return string
     */
    protected function getSource($version)
    {
        switch ($version) {
            case 'develop':
                return 'laravel';
            case 'master':
                return 'laravel';
            case '5.1':
                return 'composer';
            case '5.2':
                return 'composer';
            case '5.3':
                return 'composer';
        }
    }

    /**
     * Get the composer command for the environment.
     *
     * @return string
     */
    protected function findComposer()
    {
        if (file_exists(getcwd().'/composer.phar')) {
            return '"'.PHP_BINARY.'" composer.phar';
        }

        return 'composer';
    }
}
