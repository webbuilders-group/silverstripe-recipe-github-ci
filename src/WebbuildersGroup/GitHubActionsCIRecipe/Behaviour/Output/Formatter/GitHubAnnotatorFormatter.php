<?php
namespace src\WebbuildersGroup\GitHubActionsCIRecipe\Behaviour\Output\Formatter;

use Behat\Behat\EventDispatcher\Event\AfterOutlineTested;
use Behat\Behat\EventDispatcher\Event\AfterScenarioTested;
use Behat\Behat\EventDispatcher\Event\AfterStepTested;
use Behat\Behat\EventDispatcher\Event\BeforeOutlineTested;
use Behat\Behat\EventDispatcher\Event\BeforeScenarioTested;
use Behat\Gherkin\Node\FeatureNode;
use Behat\Gherkin\Node\ScenarioLikeInterface;
use Behat\TeamCityFormatter\TeamCityFormatter;
use Behat\Testwork\Tester\Result\ExceptionResult;
use Behat\Testwork\Tester\Result\TestResult;

class GitHubAnnotatorFormatter extends TeamCityFormatter
{
    /** @var CallResult|null */
    private $failedStep;
    
    private static $REPLACEMENTS = [
        "|"  => "||",
        "'"  => "|'",
        "\n" => "|n",
        "\r" => "|r",
        "["  => "|[",
        "]"  => "|]",
    ];
    
    /**
     * Returns formatter name.
     * @return string
     */
    public function getName()
    {
        return 'github_annotator';
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
        
        parent::onBeforeScenarioTested($event);
    }
    
    public function onBeforeOutlineTested(BeforeOutlineTested $event)
    {
        $this->failedStep = null;
        
        parent::onBeforeOutlineTested($event);
    }
    
    public function onAfterScenarioTested(AfterScenarioTested $event)
    {
        $this->afterTest($event->getTestResult(), $event->getScenario(), $event->getFeature());
    }
    
    public function onAfterOutlineTested(AfterOutlineTested $event)
    {
        $this->afterTest($event->getTestResult(), $event->getOutline());
    }
    
    private function afterTest(TestResult $result, ScenarioLikeInterface $scenario, FeatureNode $feature = null)
    {
        $params = ['name' => $scenario->getTitle()];
        
        switch ($result->getResultCode()) {
            case TestResult::SKIPPED:
                #$this->writeServiceMessage('testIgnored', $params);
                #return;
                break;
            case TestResult::PASSED:
                break;
            case TestResult::FAILED:
                $failedParams = $params;
                
                if ($this->failedStep && $this->failedStep->hasException()) {
                    switch (true) {
                        case ($this->failedStep instanceof ExceptionResult && $this->failedStep->hasException()):
                            $exception = $this->failedStep->getException();
                            $failedParams['message'] = $exception->getMessage();
                            if ($feature != null) {
                                $failedParams['details'] = ' ' . $feature->getFile() . ':' . $scenario->getLine() . "\n ";
                            }
                            
                            break;
                        default:
                            $failedParams['message'] = sprintf("Unknown error in ", get_class($this->failedStep));
                            break;
                    }
                    
                    #$failedParams['details'] = $this->sanitizeExceptionStack($exception);
                }
                
                $this->writeServiceMessage('testFailed', $failedParams);
                
                break;
        }
        
        $this->writeServiceMessage('testFinished', $params);
    }
    
    private function writeServiceMessage($messageKey, array $params = array())
    {
        $message = '';
        $search  = array_keys(self::$REPLACEMENTS);
        $replace = array_values(self::$REPLACEMENTS);
        
        foreach ($params as $key => $value) {
            $value    = str_replace($search, $replace, $value);
            $message .= sprintf(" %s='%s'", $key, $value);
        }
        
        $message = sprintf("##teamcity[%s %s]", $messageKey, trim($message));
        
        $this->printer->writeln($message);
    }
}
