<?php

namespace Dockr\Commands;

use Dockr\Config;
use Dockr\Questions\Question;
use Dockr\Questions\ChoiceQuestion;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class InitCommand extends Command
{
    /**
     * @var string
     */
    protected static $defaultName = 'init';

    /**
     * @var InputInterface
     */
    protected $input;

    /**
     * @var OutputInterface
     */
    protected $output;

    /**
     * @var \Symfony\Component\Console\Helper\QuestionHelper
     */
    protected $questionHelper;

    /**
     * User's answers
     */
    protected $projectName;
    protected $projectDomain;
    protected $webServer;
    protected $cacheStore;
    protected $phpVersion;
    protected $phpExtensions = [];

    /**
     * Configure the command
     *
     * @return void
     */
    protected function configure()
    {
        $this->setDescription('Initialize docker-compose.')
            ->setHelp('Start an initialization wizard to setup docker-compose for your project.');
    }

    /**
     * @param \Symfony\Component\Console\Input\InputInterface   $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *
     * @return int|void|null
     * @throws \Pouch\Exceptions\NotFoundException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        $this->runWizard();
//        $this->performReplacements();
        $this->storeConfig();
    }

    /**
     * Store JSON config file with all the data.
     *
     * @return void
     * @throws \Pouch\Exceptions\NotFoundException
     */
    public function storeConfig()
    {
        $config = pouch()->resolve(Config::class);

        $config->set([
            'project-name' => $this->projectName,
            'project-domain' => $this->projectDomain,
            'webserver' => $this->webServer,
            'cache-store' => $this->cacheStore,
            'php-version' => $this->phpVersion,
            'php-extensions' => $this->phpExtensions
        ]);

        $this->output->writeln('Configuration file has been saved under dockr.json');
    }

    /**
     * Ask series of questions and store answers.
     *
     * @return void
     */
    protected function runWizard()
    {
        $this->askProjectName();
        $this->askProjectDomain();
        $this->askWebServer();
        $this->askCacheStore();
        $this->askPhpVersion();
        $this->askPhpExtensions();
    }

    /**
     * Question
     *
     * @return void
     */
    protected function askProjectName()
    {
        $this->projectName = (new Question('Please enter the name of the project: '))
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
    protected function askProjectDomain()
    {
        $defaultDomain = str_replace(' ', '-', strtolower($this->projectName)).'.';

        $this->projectDomain = (new Question('Please enter the domain for the project: ', $defaultDomain . 'local'))
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
    protected function askWebServer()
    {
        $this->webServer = (new ChoiceQuestion(
            'Please select the webserver you want your project to run on',
            SwitchWebserverCommand::WEBSERVERS, 0
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
    protected function askCacheStore()
    {
        $this->cacheStore = (new ChoiceQuestion(
            'Please select the cache store you want your project to run on',
            SwitchCacheCommand::CACHE_STORES, 0
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
    protected function askPhpVersion()
    {
        $this->phpVersion = (new ChoiceQuestion(
            'Please select the PHP version you want your project to run on',
            SwitchPhpCommand::PHP_VERSIONS, 2
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
    protected function askPhpExtensions()
    {
        $question = (new ChoiceQuestion(
            'Please choose which PHP extensions should be included in your project (comma separated list): ',
            array_keys(ExtensionEnableCommand::$availableExtensions), null, true
        ))->render();

        $question->adjustAnswer(function ($choices) {
            $resultArray = [];
            foreach ($choices as $extensionName) {
                $actualExtensionName = ExtensionEnableCommand::$availableExtensions[$extensionName];
                if (strpos($actualExtensionName, '{PHP_VERSION}') !== false) {
                    $actualExtensionName = str_replace('{PHP_VERSION}', $this->phpVersion, $actualExtensionName);
                }
                ExtensionEnableCommand::$availableExtensions[$extensionName] = $actualExtensionName;
                $resultArray[] = $extensionName;
            }
            return array_unique($resultArray);
        })->outputAnswer();

        $this->phpExtensions = array_map(function($item) {
            return ExtensionEnableCommand::$availableExtensions[$item];
        }, $question->getAnswer());
    }

    /**
     * Prepare the stubs
     */
    public function performReplacements()
    {
        $finder = pouch()->resolve('stubsFinder');

        foreach ($finder as $file) {
            $folderStructure = './'.$file->getRelativePath();

            if (! file_exists($folderStructure)) {
                mkdir($folderStructure, 0777, true);
            }

            $contents = $this->replacementQuery($file->getContents());
            $fileName = './'.str_replace('.stub', '', $file->getRelativePathname());
            file_put_contents($fileName, $contents);
        }
    }

    /**
     * Performs the actual string replacement for all files.
     *
     * @param $haystack
     *
     * @return string
     */
    public function replacementQuery($haystack)
    {
        $vhost = constant(SwitchWebserverCommand::class.'::'.strtoupper($this->webServer).'_CONF');
        $rawPhp = str_replace('.', '', $this->phpVersion);
        $phpExts = implode(' ', $this->phpExtensions);

        return str_replace(
            [
                '{PROJECT_NAME}', '{CACHE_STORE}', '{PROJECT_DOMAIN}', '{WEBSERVER}',
                '{WEBSERVER_VHOST}', '{PHP_VERSION}', '{PHP_VERSION_RAW}', '{PHP_EXTENSIONS}',
            ],
            [
                $this->projectName, $this->cacheStore, $this->projectDomain, $this->webServer,
                $vhost, $this->phpVersion, $rawPhp, $phpExts
            ],
            $haystack
        );
    }
}
