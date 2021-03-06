<?php

declare(strict_types=1);

namespace Dockr\Questions;

use Symfony\Component\Console\Question\Question as SymfonyQuestion;
use Symfony\Component\Console\Question\ChoiceQuestion as SymfonyChoiceQuestion;

class ChoiceQuestion extends Question implements QuestionInterface
{
    /**
     * @var array
     */
    protected $choices;

    /**
     * @var bool
     */
    protected $multiChoice;

    /**
     * @var bool
     */
    protected $allowEmpty;

    /**
     * ChoiceQuestion constructor.
     *
     * @param string $question
     * @param array  $choices
     * @param int    $default
     * @param bool   $multiChoice
     * @param bool   $allowEmpty
     */
    public function __construct(
        string $question, 
        array $choices, 
        $default = null, 
        bool $multiChoice = false, 
        bool $allowEmpty = false
    )
    {
        if ($allowEmpty) {
            array_unshift($choices, 'None');
        }

        $this->choices = $choices;
        $this->multiChoice = $multiChoice;
        $this->allowEmpty = $allowEmpty;

        parent::__construct($question, $default);
    }

    /**
     * Display the question to the user.
     *
     * @return \Dockr\Questions\Question
     */
    public function render()
    {
        $question = new SymfonyChoiceQuestion($this->question, $this->choices, $this->default);

        $this->includeValidators($question);

        if ($this->multiChoice) {
            $question->setMultiselect(true);
        }

        $this->answer = $this->storeAnswer($question);

        return $this;
    }

    /**
     * Prompts user for input and saves the answer.
     *
     * @param \Symfony\Component\Console\Question\Question $question
     *
     * @return mixed
     */
    protected function storeAnswer(SymfonyQuestion $question)
    {
        $answer = parent::storeAnswer($question);

        return ctype_digit($answer) ? $this->choices[$answer] : $answer;
    }

    /**
     * Appends the default to the question in brackets.
     *
     * @return void
     */
    protected function includeDefault(): void
    {
        if ($this->default !== null) {
            $this->default = $this->choices[$this->default];
            parent::includeDefault();
        }
    }
}
