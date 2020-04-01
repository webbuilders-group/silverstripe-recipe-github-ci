<?php
namespace WebbuildersGroup\GitHubActionsCIRecipe\Behaviour;

use Behat\Behat\Hook\Scope\AfterStepScope;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use SilverStripe\BehatExtension\Context\SilverStripeContext;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Versioned\Versioned;

/**
 * Features context
 *
 * Context automatically loaded by Behat.
 * Uses subcontexts to extend functionality.
 */
class FeatureContext extends SilverStripeContext
{
    use Configurable;
    
    /**
     * Desktop Window Width
     * @config WebbuildersGroup\GitHubActionsCIRecipe\Behaviour\FeatureContext.desktop_window_width
     */
    private static $desktop_window_width = 1280;
    
    /**
     * Desktop Window Height
     * @config WebbuildersGroup\GitHubActionsCIRecipe\Behaviour\FeatureContext.desktop_window_height
     */
    private static $desktop_window_height = 700;
    
    /**
     * Tablet Window Width
     * @config WebbuildersGroup\GitHubActionsCIRecipe\Behaviour\FeatureContext.tablet_window_width
     */
    private static $tablet_window_width = 768;
    
    /**
     * Tablet Window Height
     * @config WebbuildersGroup\GitHubActionsCIRecipe\Behaviour\FeatureContext.tablet_window_height
     */
    private static $tablet_window_height = 700;
    
    /**
     * Mobile Window Width
     * @config WebbuildersGroup\GitHubActionsCIRecipe\Behaviour\FeatureContext.mobile_window_width
     */
    private static $mobile_window_width = 320;
    
    /**
     * Mobile Window Height
     * @config WebbuildersGroup\GitHubActionsCIRecipe\Behaviour\FeatureContext.mobile_window_height
     */
    private static $mobile_window_height = 568;
    
    /**
     * Window width offset, used to offset for a difference in selenium plus browser chrome (selenium is off by 14px, browser chrome is 2px in Google Chrome)
     * @config WebbuildersGroup\GitHubActionsCIRecipe\Behaviour\FeatureContext.window_width_offset
     */
    private static $window_width_offset = 16;
    
    /**
     * Window height offset, used to offset for a difference in selenium plus browser chrome (selenium is off by 14px, browser chrome is 2px in Google Chrome)
     * @config WebbuildersGroup\GitHubActionsCIRecipe\Behaviour\FeatureContext.window_height_offset
     */
    private static $window_height_offset = 93;
    
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
        
        
        //Init the stub code writter
        $this->stubCodeWriter = Injector::inst()->get(TestSessionStubCodeWriter::class);
    }
    
    /**
     * @BeforeScenario
     */
    public function initTestSessionStubCode()
    {
        $this->stubCodeWriter->write('if(!defined("BEHAT_TEST_SESSION")) {define("BEHAT_TEST_SESSION", true);}');
    }
    
    /**
     * @AfterScenario
     */
    public function resetTestSessionStubCode()
    {
        $this->stubCodeWriter->reset();
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
        
        
        if ($event->getScenario()->hasTag('desktop')) {
            $this->getSession()->getDriver()->resizeWindow($this->config()->desktop_window_width + $this->config()->window_width_offset, $this->config()->desktop_window_height + $this->config()->window_height_offset, 'current');
        } else if ($event->getScenario()->hasTag('tablet')) {
            $this->getSession()->getDriver()->resizeWindow($this->config()->tablet_window_width + $this->config()->window_width_offset, $this->config()->tablet_window_height + $this->config()->window_height_offset, 'current');
        } else if ($event->getScenario()->hasTag('mobile')) {
            $this->getSession()->getDriver()->resizeWindow($this->config()->mobile_window_width + $this->config()->window_width_offset, $this->config()->mobile_window_height + $this->config()->window_height_offset, 'current');
        }
    }
    
    /**
     * @AfterScenario @desktop
     */
    public function afterScenarioDesktop(AfterScenarioScope $scope)
    {
        if ($screenSize = getenv('BEHAT_SCREEN_SIZE')) {
            list($screenWidth, $screenHeight) = explode('x', $screenSize);
            
            $this->getSession()->resizeWindow(((int) $screenWidth) + $this->config()->window_width_offset, ((int) $screenHeight) + $this->config()->window_height_offset, 'current');
        } else {
            $this->getSession()->resizeWindow(1024 + $this->config()->window_width_offset, 768 + $this->config()->window_height_offset, 'current');
        }
    }
    
    /**
     * @AfterScenario @desktop
     */
    public function afterScenarioTablet(AfterScenarioScope $scope)
    {
        $this->afterScenarioDesktop($scope);
    }
    
    /**
     * @AfterScenario @mobile
     */
    public function afterScenarioMobile(AfterScenarioScope $scope)
    {
        $this->afterScenarioDesktop($scope);
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
    
    public function getTestSessionState()
    {
        return array_merge(
            parent::getTestSessionState(),
            ['stubfile' => $this->stubCodeWriter->getFilePath()]
        );
    }
}
