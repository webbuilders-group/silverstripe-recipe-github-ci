<?php
namespace WebbuildersGroup\GitHubActionsCIRecipe\Behaviour;

use Behat\Behat\Hook\Scope\AfterStepScope;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use SilverStripe\BehatExtension\Context\SilverStripeContext;
use SilverStripe\Versioned\Versioned;

/**
 * Features context
 *
 * Context automatically loaded by Behat.
 * Uses subcontexts to extend functionality.
 */
class FeatureContext extends SilverStripeContext
{
    /**
     * Initializes context.
     * Every scenario gets it's own context object.
     *
     * @param array $parameters Context parameters (set them up through behat.yml)
     */
    public function __construct(array $parameters = null)
    {
        parent::__construct($parameters);
        
        //Workaround for if the reading mode is not set
        if (Versioned::get_reading_mode() == null) {
            Versioned::set_stage(Versioned::DRAFT);
        }
    }
    
    /**
     * @BeforeScenario
     * @param BeforeScenarioScope $event
     */
    public function before(BeforeScenarioScope $event)
    {
        $webDriverSession = $this->getSession();
        if (!$webDriverSession->isStarted()) {
            $webDriverSession->start();
        }
        
        parent::before($event);
    }
    
    /**
     * @AfterStep
     *
     * Wait for all requests to be handled after each step
     * @param AfterStepScope $event
     */
    public function waitResponsesAfterStep(AfterStepScope $event)
    {
        $success = $this->testSessionEnvironment->waitForPendingRequests();
        if (!$success) {
            echo (
                'Warning! The timeout for waiting a response from the server has expired...' . PHP_EOL .
                'I keep going on, but this test behaviour may be inconsistent.' . PHP_EOL . PHP_EOL .
                'Your action required!' . PHP_EOL . PHP_EOL .
                'You may want to investigate why the server is responding that slowly.' . PHP_EOL .
                'Otherwise, you may need to increase the timeout.'
                );
        }
    }
}
