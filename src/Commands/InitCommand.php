<?php

declare(strict_types=1);

namespace Dockr\Commands;

use Dockr\Questions\Question;
use Dockr\Questions\ChoiceQuestion;
use Symfony\Component\Finder\Finder;
use Dockr\Questions\ConfirmationQuestion;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function Dockr\Helpers\{
    color,
    slash,
    ends_with,
    comma_list,
    snake_case,
    starts_with,
    current_path
};

class InitCommand extends Command
{
    /**
     * @var Finder
     */
    private $stubsFinder;

    /**
     * InitCommand constructor.
     *
     * @param Finder $stubsFinder
     */
    public function __construct(Finder $stubsFinder)
    {
        $this->stubsFinder = $stubsFinder;

        parent::__construct(null);
    }

    /**
     * Configure the command
     *
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->setName('init')
            ->setDescription('Initialize docker-compose')
            ->setHelp('Start an initialization wizard to setup docker-compose for your project.')
            ->addOption('from-config', 'c', InputOption::VALUE_NONE, 'Initialize dockr using an existing dockr.json configuration file.')
            ->addOption('name', null, InputOption::VALUE_REQUIRED, "Your project's name")
            ->addOption('domain', null, InputOption::VALUE_REQUIRED, "Your project's local domain")
            ->addOption(
                'webserver',
                null,
                InputOption::VALUE_REQUIRED,
                'The webserver powering your project. Must be: ' . comma_list(SwitchWebServerCommand::getChoices()),
                SwitchWebServerCommand::getChoices()[0]
            )
            ->addOption(
                'cache',
                null,
                InputOption::VALUE_REQUIRED,
                'The desired cache store. Must be: ' . comma_list(SwitchCacheStoreCommand::getChoices()),
                SwitchCacheStoreCommand::getChoices()[0]
            )
            ->addOption(
                'php',
                null,
                InputOption::VALUE_REQUIRED,
                'The PHP version powering your project. Must be: ' . comma_list(SwitchPhpVersionCommand::getChoices()),
                SwitchPhpVersionCommand::getChoices()[2]
            );
    }

    /**
     * Command execution logic.
     *
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return int|void|null
     * @throws \Pouch\Exceptions\NotFoundException
     * @throws \Pouch\Exceptions\PouchException
     */
    protected function execute(InputInterface $input, OutputInterface $output): void
    {
        parent::execute($input, $output);

        $this->fetchAnswers();
        $this->copyStubs();
        $configStored = $this->storeConfig();

        if ($configStored) {
            $this->output->writeln(color('green', 'Successfully initialized dockr for this directory!'));
        } else {
            $this->output->writeln(color('red', 'Something went wrong while initializing Dockr. Please try again.', true));
        }
    }

    /**
     * Determine in what fashion the answers will be loaded:
     * Via wizard, from dockr.json or from cli options.
     *
     * @return void
     * @throws \Pouch\Exceptions\NotFoundException
     * @throws \Pouch\Exceptions\PouchException
     */
    public function fetchAnswers(): void
    {
        $name = $this->input->getOption('name');
        $domain = $this->input->getOption('domain');

        if ($name && $domain) {
            $this->fromOptions($name, $domain);
        } elseif (!$this->input->getOption('from-config')) {
            $this->runWizard();
        } else {
            // Otherwise will use $this->answers which was populated from parent class.
        }
    }

    /**
     * Set the answers from CLI options.
     *
     * @param string $name
     * @param string $domain
     *
     * @return void
     */
    protected function fromOptions(string $name, string $domain): void
    {
        $webServer  = $this->input->getOption('webserver');
        $cacheStore = $this->input->getOption('cache');
        $phpVersion = $this->input->getOption('php');

        $webServerChoices = SwitchWebServerCommand::getChoices();
        $cacheStoreChoices = SwitchCacheStoreCommand::getChoices();
        $phpVersionChoices = SwitchPhpVersionCommand::getChoices();

        $this->answers = [
            'projectName' => $name,
            'projectDomain' => $domain,
            'webServer' => in_array($webServer, $webServerChoices) ? $webServer : $webServerChoices[0],
            'cacheStore' => in_array($cacheStore, $cacheStoreChoices) ? $cacheStore : $cacheStoreChoices[0],
            'phpVersion' => in_array($phpVersion, $phpVersionChoices) ? $phpVersion : $phpVersionChoices[0],
            'phpExtensions' => [],
        ];
    }

    /**
     * Ask series of questions and store answers.
     *
     * @return void
     */
    protected function runWizard(): void
    {
        $this->greeting();

        $this->askProjectName();
        $this->askProjectDomain();
        $this->askWebServer();
        $this->askCacheStore();
        $this->askPhpVersion();
        $this->askPhpExtensions();
        $this->askOptionalAddons();
        $this->askUseDotEnv();
    }

    /**
     * Greet the user.
     *
     * @return void
     */
    protected function greeting(): void
    {
        $this->output->writeln('Welcome to');
        $this->output->writeln('
      _            _         
   __| | ___   ___| | ___ __ 
  / _` |/ _ \ / __| |/ / \__|
 | (_| | (_) | (__|   <| |   
  \__,_|\___/ \___|_|\_\_|   
        ');

        $this->output->writeln("Let's initialize your docker-compose setup!");
        $this->output->writeln('');
    }

    /**
     * Question
     *
     * @return void
     */
    protected function askProjectName(): void
    {
        $this->answers['projectName'] = (new Question('Please enter the name of the project: '))
            ->setValidators(['not_empty'])
            ->render()
            ->outputAnswer()
            ->getAnswer();
    }

    /**
     * Question
     *
     * @return void
     */
    protected function askProjectDomain(): void
    {
        $defaultDomain = str_replace(' ', '-', strtolower($this->answers['projectName'])) . '.';

        $this->answers['projectDomain'] = (new Question('Please enter the domain for the project: ', $defaultDomain . 'test'))
            ->setAutocomplete([$defaultDomain])
            ->render()
            ->outputAnswer()
            ->getAnswer();
    }

    /**
     * Question
     *
     * @return void
     */
    protected function askWebServer(): void
    {
        $this->answers['webServer'] = (new ChoiceQuestion(
            'Please select the webserver you want your project to run on: ',
            SwitchWebServerCommand::getChoices(), 0
        ))
            ->render()
            ->outputAnswer()
            ->getAnswer();
    }

    /**
     * Question
     *
     * @return void
     */
    protected function askCacheStore(): void
    {
        $this->answers['cacheStore'] = (new ChoiceQuestion(
            'Please select the cache store you want your project to run on: ',
            SwitchCacheStoreCommand::getChoices(), 0
        ))
            ->render()
            ->outputAnswer()
            ->getAnswer();
    }

    /**
     * Question
     *
     * @return void
     */
    protected function askPhpVersion(): void
    {
        $this->answers['phpVersion'] = (new ChoiceQuestion(
            'Please select the PHP version you want your project to run on',
            SwitchPhpVersionCommand::getChoices(), 2
        ))
            ->render()
            ->outputAnswer()
            ->getAnswer();
    }

    /**
     * Question
     *
     * @return void
     */
    protected function askPhpExtensions(): void
    {
        $phpExts = [
            'memcached' => 'php-memcached',
            'pgsql' => 'php{PHP_VERSION}-pgsql',
            'redis' => 'php-redis',
            'xdebug' => 'php-xdebug',
            'sqlite' => 'php{PHP_VERSION}-sqlite3',
            'bcmath' => 'php{PHP_VERSION}-bcmath',
            'bz' => 'php{PHP_VERSION}-bz2',
            'dba' => 'php{PHP_VERSION}-dba',
            'enchant' => 'php{PHP_VERSION}-enchant',
            'gd' => 'php{PHP_VERSION}-gd',
            'gearman' => 'php-gearman',
            'gmp' => 'php{PHP_VERSION}-gmp',
            'igbinary' => 'php-igbinary',
            'imagick' => 'php-imagick',
            'imap' => 'php{PHP_VERSION}-imap',
            'interbase' => 'php{PHP_VERSION}-interbase',
            'intl' => 'php{PHP_VERSION}-intl',
            'ldap' => 'php{PHP_VERSION}-ldap',
            'mongodb' => 'php-mongodb',
            'msgpack' => 'php-msgpack',
            'odbc' => 'php{PHP_VERSION}-odbc',
            'phpdbg' => 'php{PHP_VERSION}-phpdbg',
            'pspell' => 'php{PHP_VERSION}-pspell',
            'raphf' => 'php-raphf',
            'recode' => 'php{PHP_VERSION}-recode',
            'snmp' => 'php{PHP_VERSION}-snmp',
            'soap' => 'php{PHP_VERSION}-soap',
            'ssh' => 'php-ssh2',
            'sybase' => 'php{PHP_VERSION}-sybase',
            'tideways' => 'php-tideways',
            'tidy' => 'php{PHP_VERSION}-tidy',
            'xmlrpc' => 'php{PHP_VERSION}-xmlrpc',
            'xsl' => 'php{PHP_VERSION}-xsl',
            'yaml' => 'php-yaml',
            'zmq' => 'php-zmq',
        ];

        $question = (new ChoiceQuestion(
            'Please choose which PHP extensions should be included in your project (comma separated list): ',
            array_keys($phpExts), 0, true, true
        ))->render();

        $question->adjustAnswer(function ($choices) use (&$phpExts) {
            $resultArray = [];
            foreach ($choices as $extensionName) {
                if (!array_key_exists($extensionName, $phpExts)) {
                    continue;
                }

                $actualExtensionName = $phpExts[$extensionName];
                if (strpos($actualExtensionName, '{PHP_VERSION}') !== false) {
                    $actualExtensionName = str_replace('{PHP_VERSION}', $this->answers['phpVersion'], $actualExtensionName);
                }

                $phpExts[$extensionName] = $actualExtensionName;
                $resultArray[] = $extensionName;
            }

            return array_unique($resultArray);
        })->outputAnswer();

        $this->answers['phpExtensions'] = array_map(function($item) use ($phpExts) {
            return $phpExts[$item];
        }, $question->getAnswer());
    }

    /**
     * Question.
     *
     * @return void
     */
    protected function askOptionalAddons(): void
    {
        $addons = [];

        foreach ($this->stubsFinder as $file) {
            $path = $file->getRelativePathname();
            if (starts_with($path, slash('.docker/')) && ends_with($path, '.yml.stub')) {
                $addon = str_replace([slash('.docker/docker-compose.'), '.yml.stub'], '', $path);

                if ($addon == 'yml.stub') {
                    continue;
                }

                $addons[] = $addon;
            }
        }

        $this->answers['addons'] = (new ChoiceQuestion(
            'Include optional addons to your setup (comma separated list): ',
            $addons, 0, true, true
        ))
            ->render()
            ->outputAnswer()
            ->getAnswer();
    }

    public function askUseDotenv(): void
    {
        if (!file_exists('.env')) {
            return;
        }

        $question = (new ConfirmationQuestion(
            'We have detected the existence of a .env file in your project root. 
            Use this file for your containers\' environment variables?: ',
            true
        ))->render();

        $question->adjustAnswer(function ($answer) {
            return $answer === true ? '.env' : $answer;
        });

        $this->answers['environmentFile'] = $question->getAnswer();
    }

    /**
     * Prepare the stubs
     *
     * @return void
     */
    protected function copyStubs(): void
    {
        foreach ($this->stubsFinder as $file) {
            $folderStructure = current_path($file->getRelativePath());

            if (!file_exists($folderStructure)) {
                @mkdir($folderStructure, 0777, true);
            }

            $fileName = current_path(str_replace('.stub', '', $file->getRelativePathname()));
            file_put_contents($fileName, $file->getContents());
        }
    }

    /**
     * Fetches an answer from the answers prop.
     *
     * @param string $key
     *
     * @return array|string
     */
    protected function getAnswer(string $key)
    {
        if (!array_key_exists($key, $this->answers)) {
            throw new \RuntimeException('Cannot find this answer in the answers list.');
        }

        if (is_array($this->answers[$key])) {
            $this->answers[$key] = array_filter($this->answers[$key], function ($item) {
                return $item !== 'None';
            });
        }

        return $this->answers[$key];
    }

    /**
     * Store JSON config file with all the data.
     *
     * @return bool
     */
    protected function storeConfig(): bool
    {
        $dockrJson = [];

        foreach (array_keys($this->answers) as $key) {
            $dockrJson[snake_case($key, '-')] = $this->getAnswer($key);
        }

        $dockrJson['aliases'] = [
            'up' => [
                'help' => 'Starts docker-compose with your custom environment',
                'commands' => [$this->upDownCommands('up')]
            ],
            'down' => [
                'help' => 'Shuts off docker-compose',
                'commands' => [$this->upDownCommands('down')]
            ],
            'ssh' => [
                'help' => 'SSH into a container',
                'commands' => [slash('docker-compose -f {DOCKR_PATH}.docker/docker-compose.yml exec {container} bash')]
            ]
        ];

        return $this->config->set($dockrJson);
    }

    /**
     * Prepares the docker-compose up command based on the wizard results.
     *
     * @param string $mode
     *
     * @return string
     */
    protected function upDownCommands(string $mode): string
    {
        $mode = $mode == 'up' ? 'up -d' : 'down';

        $upCommand = "docker-compose ~ {$mode}";
        $composeFiles = '-f {DOCKR_PATH}.docker/docker-compose.yml';

        foreach ((array)$this->getAnswer('addons') as $addon) {
            $file = "{DOCKR_PATH}.docker/docker-compose.{$addon}.yml";

            if (!file_exists($file)) {
                continue;
            }

            $composeFiles .= " -f {$file}";
        }

        return slash(str_replace('~', $composeFiles, $upCommand));
    }
}
