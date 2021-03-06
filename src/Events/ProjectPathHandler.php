<?php

declare(strict_types=1);

namespace Dockr\Events;

use Dockr\Config;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;

class ProjectPathHandler implements EventHandlerInterface
{
    /**
     * @var Config
     */
    protected $config;

    /**
     * SetProjectPathEvent constructor.
     */
    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * The event name.
     *
     * @return string
     */
    public function onEvent(): string
    {
        return ConsoleEvents::COMMAND;
    }

    /**
     * Handle this event.
     *
     * @return void
     */
    public function handle(): \Closure
    {
        return function (ConsoleCommandEvent $event) {
            if ($event->getInput()->hasParameterOption(['--project-path']) === true) {
                $this->config->setConfigFile($event->getInput()->getOption('project-path'));
            }
        };
    }
}