<?php

namespace Dockr\Events;

use Dockr\Config;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleEvent;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;

class EventSubscriber
{
    /**
     * Accepted types
     */
    const TYPE_PRE  = 'pre';
    const TYPE_ERR  = 'error';
    const TYPE_POST = 'post';

    /**
     * Map dockr events to the symfony ones.
     */
    const EVENT_MAP = [
        self::TYPE_PRE  => ConsoleEvents::COMMAND,
        self::TYPE_ERR  => ConsoleEvents::ERROR,
        self::TYPE_POST => ConsoleEvents::TERMINATE,
    ];

    /**
     * @var string
     */
    protected $type;

    /**
     * @var array
     */
    protected $scripts;

    /**
     * @var array
     */
    protected $commandList;

    /**
     * @var EventDispatcher
     */
    protected $dispatcher;

    /**
     * EventSubscriber constructor.
     *
     * @param \Dockr\Config   $config
     * @param EventDispatcher $dispatcher
     *
     * @return void
     */
    public function __construct(Config $config, EventDispatcher $dispatcher)
    {
        $this->scripts = $config->get('scripts') ?? [];
        $this->dispatcher = $dispatcher;
    }

    /**
     * Return all the commands and methods.
     *
     * @param bool $continueOnError
     *
     * @return $this
     */
    public function listen($continueOnError = false)
    {
        foreach (self::EVENT_MAP as $event => $eventName) {
            $this->dispatcher->addListener(self::EVENT_MAP[$event], $this->eventHandler($event, $continueOnError));
        }

        return $this;
    }

    /**
     * Event handler.
     *
     * @param string $event
     * @param bool   $continueOnError
     *
     * @return \Closure
     */
    protected function eventHandler($event, $continueOnError)
    {
        return function (ConsoleEvent $e) use ($event, $continueOnError) {
            if (
                !$continueOnError
                && $e instanceof ConsoleTerminateEvent
                && $e->getExitcode() !== 0
            ) {
                return;
            }

            $commandName = str_replace(':', '-', $e->getCommand()->getName());

            if (!$this->hookExists($event, $commandName)) {
                return;
            }

            foreach ((array)$this->scripts[$event . '-' . $commandName] as $command) {
                if (strpos($command, '::') !== false) {
                    list($class, $method) = explode('::', $command);
                    if (class_exists($class) && method_exists($class, $method)) {
                        $e->getOutput()->writeln($class::{$method}($e->getCommand()));
                    }
                } else {
                    $e->getOutput()->writeln(shell_exec($command));
                }
            }
        };
    }

    /**
     * Checks if hook has been registered.
     *
     * @param string $event
     * @param string $command
     *
     * @return bool
     */
    protected function hookExists($event, $command)
    {
        return array_key_exists($event . '-' . $command, $this->scripts);
    }
}