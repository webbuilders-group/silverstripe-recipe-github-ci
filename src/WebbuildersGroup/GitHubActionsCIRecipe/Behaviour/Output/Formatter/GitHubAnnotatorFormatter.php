<?php
namespace WebbuildersGroup\GitHubActionsCIRecipe\Behaviour\Output\Formatter;

use Behat\Behat\EventDispatcher\Event\AfterOutlineTested;
use Behat\Behat\EventDispatcher\Event\AfterScenarioTested;
use Behat\Behat\EventDispatcher\Event\AfterStepTested;
use Behat\Behat\EventDispatcher\Event\BeforeOutlineTested;
use Behat\Behat\EventDispatcher\Event\BeforeScenarioTested;
use Behat\Behat\EventDispatcher\Event\OutlineTested;
use Behat\Behat\EventDispatcher\Event\ScenarioTested;
use Behat\Behat\EventDispatcher\Event\StepTested;
use Behat\Gherkin\Node\FeatureNode;
use Behat\Gherkin\Node\ScenarioLikeInterface;
use Behat\Testwork\Output\Formatter;
use Behat\Testwork\Tester\Result\ExceptionResult;
use Behat\Testwork\Tester\Result\TestResult;
use WebbuildersWebbuildersGroup\GitHubActionsCIRecipe\Behaviour\Output\Printer\ConsoleOutput;

class GitHubAnnotatorFormatter implements Formatter
{
    /** @var CallResult|null */
    private $failedStep;
    
    private static $REPLACEMENTS = [
        "'"  => '\\\\\'',
        "\n" => '%0A',
        "\r" => '%0D',
    ];
    
    /**
     * Constructor
     * @param ConsoleOutput $printer
     */
    public function __construct(ConsoleOutput $printer)
    {
        $this->printer = $printer;
    }
    
    /**
     * @inheritdoc
     */
    public static function getSubscribedEvents()
    {
        return [
            ScenarioTested::BEFORE => 'onBeforeScenarioTested',
            ScenarioTested::AFTER => 'onAfterScenarioTested',
            OutlineTested::BEFORE => 'onBeforeOutlineTested',
            OutlineTested::AFTER => 'onAfterOutlineTested',
            StepTested::AFTER => 'saveFailedStep',
        ];
    }
    
    /**
     * Returns formatter name.
     * @return string
     */
    public function getName()
    {
        return 'github_annotator';
    }
    
    /**
     * Returns formatter description.
     * @return string
     */
    public function getDescription()
    {
        return 'Formatter that adds annotations for GitHub Actions on failure';
    }
    
    /**
     * Sets formatter parameter.
     * @param string $name
     * @param mixed $value
     */
    public function setParameter($name, $value)
    {
    }
    
    /**
     * Returns parameter name.
     * @param string $name
     * @return mixed
     */
    public function getParameter($name)
    {
    }
    
    /**
     * Returns formatter output printer.
     * @return OutputPrinter
     */
    public function getOutputPrinter()
    {
        return $this->printer;
    }
    
    public function saveFailedStep(AfterStepTested $event)
    {
        $result = $event->getTestResult();
        
        if (TestResult::FAILED === $result->getResultCode()) {
            $this->failedStep = $result;
        }
    }
    
    public function onBeforeScenarioTested(BeforeScenarioTested $event)
    {
        $this->failedStep = null;
    }
    
    public function onBeforeOutlineTested(BeforeOutlineTested $event)
    {
        $this->failedStep = null;
    }
    
    public function onAfterScenarioTested(AfterScenarioTested $event)
    {
        $this->afterTest($event->getTestResult(), $event->getScenario(), $event->getFeature());
    }
    
    public function onAfterOutlineTested(AfterOutlineTested $event)
    {
        $this->afterTest($event->getTestResult(), $event->getOutline());
    }
    
    protected function afterTest(TestResult $result, ScenarioLikeInterface $scenario, FeatureNode $feature = null)
    {
        switch ($result->getResultCode()) {
            case TestResult::FAILED:
                $failedParams = [
                    'message' => '',
                    'file' => false,
                    'line' => false,
                ];
                
                if ($this->failedStep && $this->failedStep->hasException()) {
                    switch (true) {
                        case ($this->failedStep instanceof ExceptionResult && $this->failedStep->hasException()):
                            $exception = $this->failedStep->getException();
                            $failedParams['message'] = 'Scenario: ' . $scenario->getTitle() . "\n\n" . $exception->getMessage();
                            if ($feature != null) {
                                $failedParams['message'] = 'Feature: ' . $feature->getTitle() . "\n" . $failedParams['message'];
                                $failedParams['file'] = $feature->getFile();
                                $failedParams['line'] = $scenario->getLine();
                            }
                            
                            break;
                        default:
                            $failedParams['message'] = sprintf("Unknown error in ", get_class($this->failedStep));
                            break;
                    }
                }
                
                $this->writeServiceMessage($failedParams);
                
                break;
        }
    }
    
    protected function writeServiceMessage(array $params)
    {
        $message = str_replace(array_keys(self::$REPLACEMENTS), array_values(self::$REPLACEMENTS), $params['message']);
        
        $this->printer->writeln('::error' . ($params['file'] !== false ? ' file=' . $params['file'] . ',line=' . $params['line'] : '') . '::' . trim($message));
    }
}
