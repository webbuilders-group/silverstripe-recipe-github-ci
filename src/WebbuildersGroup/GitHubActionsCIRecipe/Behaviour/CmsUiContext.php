<?php
namespace WebbuildersGroup\GitHubActionsCIRecipe\Behaviour;

use Behat\Behat\Context\Context;
use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Behat\Hook\Scope\BeforeStepScope;
use Behat\Mink\Element\NodeElement;
use Behat\Mink\Exception\ElementNotFoundException;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Facebook\WebDriver\WebDriverKeys;
use Facebook\WebDriver\WebDriverSelect;
use Facebook\WebDriver\Remote\RemoteWebElement;
use SilverStripe\Assets\Filesystem;
use SilverStripe\BehatExtension\Context\MainContextAwareTrait;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Convert;
use SilverStripe\MinkFacebookWebDriver\FacebookWebDriver;
use SilverStripe\ORM\DataObject;
use Exception;

class CmsUiContext implements Context
{
    use MainContextAwareTrait;
    
    /**
     * @var \Behat\Gherkin\Node\ScenarioInterface
     */
    private $_scenario;
    
    /**
     * @var \Behat\Gherkin\Node\FeatureNode
     */
    private $_feature;
    
    /**
     * @var \Behat\Gherkin\Node\StepNode
     */
    private $_step;
    
    /**
     * @BeforeScenario
     */
    public function registerScenario(BeforeScenarioScope $scope)
    {
        $this->_scenario = $scope->getScenario();
    }
    
    /**
     * @BeforeStep
     */
    public function registerStep(BeforeStepScope $event)
    {
        $this->_feature = $event->getFeature();
        $this->_step = $event->getStep();
    }
    
    /**
     * Hook to reset the preview display
     * @AfterScenario
     */
    public function resetPreviewDisplay(AfterScenarioScope $event)
    {
        try {
            //Reset the preview
            @$this->getSession()->executeScript('if(window.top && typeof window.top.jQuery!="undefined" && typeof window.top.jQuery.entwine!="undefined" && window.top.jQuery(".cms-preview").length>0 && window.top.jQuery(".cms-preview").entwine("ss.preview").changeMode!="undefined") {' .
                                                    'window.top.jQuery(".cms-preview").entwine("ss.preview").changeMode("split");' .
                                                '}');
            
            //Purge the current tab state
            @$this->getSession()->executeScript('if(window.top && typeof window.top.jQuery!="undefined" && typeof window.top.jQuery.entwine!="undefined" && window.top.jQuery(".cms-container").length>0 && window.top.jQuery(".cms-container").entwine("ss").clearCurrentTabState!="undefined") {' .
                                                    'window.top.jQuery(".cms-container").entwine("ss").clearCurrentTabState();' .
                                                '}');
        } catch (\WebDriver\Exception\UnknownError $e) {
        }
        
        
        //Switch back to the current window
        $this->getSession()->getDriver()->getWebDriver()->switchTo()->defaultContent();
    }
    
    /**
     * @When /^I click the "([^"]*)" element$/
     */
    public function iClickTheElement($selector)
    {
        $page = $this->getSession()->getPage();
        /** @var $element NodeElement **/
        $element = $page->find('css', $selector);
        
        assertNotNull($element, sprintf('element with the selector "%s" not found', $selector));
        
        $element->click();
    }
    
    /**
     * Needs to be in single command to avoid "unexpected alert open" errors in Selenium.
     *
     * @Given /^I click the "([^"]*)" element, confirming the dialog$/
     */
    public function iClickTheElementConfirm($selector)
    {
        $this->iClickTheElement($selector);
        
        $this->spin(function () {
            try {
                $this->getExpectedAlert()->accept();
                
                return true;
            } catch (\WebDriver\Exception $e) {
                // no-op, alert might not be present
            }
            
            return false;
        });
        
        $this->handleAjaxTimeout();
    }
    
    /**
     * Needs to be in single command to avoid "unexpected alert open" errors in Selenium.
     *
     * @Given /^I press "([^"]*)", confirming the dialog$/
     */
    public function iPressButtonConfirm($button)
    {
        $this->getSession()->getPage()->pressButton($button);
        
        $this->spin(function () {
            try {
                $this->getExpectedAlert()->accept();
                
                return true;
            } catch (\WebDriver\Exception $e) {
                // no-op, alert might not be present
            }
            
            return false;
        });
        
        $this->handleAjaxTimeout();
    }
    
    /**
    * @When /^I click the on the "([^"]*)" link$/
    */
    public function iClickOnTheLink($text)
    {
        $page = $this->getSession()->getPage();
        
        //Find the link
        $escaper = new \Behat\Mink\Selector\Xpath\Escaper();
        $element = $page->find('xpath', '//a[normalize-space(.)=' . $escaper->escapeLiteral($text) . ']');
        
        if ($element === null) {
            throw new \InvalidArgumentException(sprintf('Cannot find a link containing "%s"', $text));
        }
        
        $element->click();
    }
    
    /**
     * @Then /^I should( not |\s*)see a field with the label "([^"]*)"$/
     */
    public function iShouldSeeAFieldWithLabel($negative, $label, $selector = null)
    {
        $page = $this->getSession()->getPage();
        
        //Find the field
        $escaper = new \Behat\Mink\Selector\Xpath\Escaper();
        $element = $page->find('xpath', 'descendant-or-self::*[@class and (contains(concat(\' \', normalize-space(@class), \' \'), \' field \') or contains(concat(\' \', normalize-space(@class), \' \'), \' fieldgroup-field \') or contains(concat(\' \', normalize-space(@class), \' \'), \' field-group \'))]/label[normalize-space(.)=' . $escaper->escapeLiteral($label) . ']');
        
        if (!$element) {
            $element = $page->find('xpath', 'descendant-or-self::*[@class and (contains(concat(\' \', normalize-space(@class), \' \'), \' field \') or contains(concat(\' \', normalize-space(@class), \' \'), \' fieldgroup-field \') or contains(concat(\' \', normalize-space(@class), \' \'), \' field-group \'))]/div[@class and contains(concat(\' \', normalize-space(@class), \' \'), \' form__field-holder \')]/label[normalize-space(.)=' . $escaper->escapeLiteral($label) . ']');
        }
        
        if (trim($negative)) {
            if ($element != null && $element->isVisible()) {
                throw new \Exception(sprintf('field with the label "%s" was present or was visible', $label));
            }
        } else {
            assertNotNull($element, sprintf('field with the label "%s" not found', $label));
            
            assertTrue($element->isVisible(), sprintf('field with the label "%s" was not visible', $label));
        }
    }
    
    /**
     * @Then /^I should( not |\s*)see a field with the label "([^"]*)" in the "([^"]*)" element$/
     */
    public function iShouldSeeAFieldWithLabelInElement($negative, $label, $selector = null)
    {
        $page = $this->getSession()->getPage();
        
        //Find the Selector
        $container = $page->find('css', $selector);
        assertNotNull($container, sprintf('element with the selector "%s" not found', $selector));
        
        //Find the field
        $escaper = new \Behat\Mink\Selector\Xpath\Escaper();
        $element = $container->find('xpath', 'descendant-or-self::*[@class and (contains(concat(\' \', normalize-space(@class), \' \'), \' field \') or contains(concat(\' \', normalize-space(@class), \' \'), \' fieldgroup-field \') or contains(concat(\' \', normalize-space(@class), \' \'), \' field-group \'))]/label[normalize-space(.)=' . $escaper->escapeLiteral($label) . ']');
        
        if (!$element) {
            $element = $container->find('xpath', 'descendant-or-self::*[@class and (contains(concat(\' \', normalize-space(@class), \' \'), \' field \') or contains(concat(\' \', normalize-space(@class), \' \'), \' fieldgroup-field \') or contains(concat(\' \', normalize-space(@class), \' \'), \' field-group \'))]/div[@class and contains(concat(\' \', normalize-space(@class), \' \'), \' form__field-holder \')]/label[normalize-space(.)=' . $escaper->escapeLiteral($label) . ']');
            
            //GraphQL Checkbox Fields
            if (!$element) {
                $element = $container->find('xpath', 'descendant-or-self::*[@class and (contains(concat(\' \', normalize-space(@class), \' \'), \' field \') or contains(concat(\' \', normalize-space(@class), \' \'), \' fieldgroup-field \') or contains(concat(\' \', normalize-space(@class), \' \'), \' field-group \'))]/div[@class and contains(concat(\' \', normalize-space(@class), \' \'), \' form__field-holder \')]/div/label/span[normalize-space(.)=' . $escaper->escapeLiteral($label) . ']');
            }
        }
        
        if (trim($negative)) {
            if ($element != null && $element->isVisible()) {
                throw new \Exception(sprintf('field with the label "%s" was present or was visible', $label));
            }
        } else {
            assertNotNull($element, sprintf('field with the label "%s" not found', $label));
            
            assertTrue($element->isVisible(), sprintf('field with the label "%s" was not visible', $label));
        }
    }
    
    /**
     * @Then /^I should( not |\s*)see a grid field with the label "([^"]*)"$/
     */
    public function iShouldSeeAGridWithLabel($negative, $label)
    {
        $page = $this->getSession()->getPage();
        
        //Find the field
        $escaper = new \Behat\Mink\Selector\Xpath\Escaper();
        $element = $page->find('xpath', 'descendant-or-self::*[@class and (contains(concat(\' \', normalize-space(@class), \' \'), \' grid-field__table \'))]/*/tr[@class and (contains(concat(\' \', normalize-space(@class), \' \'), \' grid-field__title-row \'))]/th/*[normalize-space(.)=' . $escaper->escapeLiteral($label) . ']');
        
        if (trim($negative)) {
            if ($element != null && $element->isVisible()) {
                throw new \Exception(sprintf('Grid field with the label "%s" was present or was visible', $label));
            }
        } else {
            assertNotNull($element, sprintf('Grid field with the label "%s" not found', $label));
            
            assertTrue($element->isVisible(), sprintf('Grid field with the label "%s" was not visible', $label));
        }
    }
    
    /**
     * @Then /^I fill in the "([^"]*)" auto complete field with "([^"]*)", and select the suggestion option "([^"]*)"$/
     */
    public function selectAutosuggestionOption($field, $value, $option)
    {
        $session = $this->getSession();
        /** @var $driver \SilverStripe\MinkFacebookWebDriver\FacebookWebDriver **/
        $driver = $session->getDriver();
        $element = $session->getPage()->findField($field);
        
        assertNotNull($element, 'Could not find a field with the label, placeholder, id or name of "' . $field . '"');
        
        //Get the XPath Selector for the Element
        $xpath = $element->getXpath();
        
        //Set the value, we can't use the normal setValue because of the TAB at the end see minkphp/MinkSelenium2Driver#244 present in selenium2 driver 1.2.*
        /** @var $driverElement \Facebook\WebDriver\WebDriverElement **/
        $driverElement = $driver->getWebDriver()->findElement(WebDriverBy::xpath($xpath));
        $existingValueLength = strlen($driverElement->getAttribute('value'));
        $driverValue = str_repeat(WebDriverKeys::BACKSPACE . WebDriverKeys::DELETE, $existingValueLength) . $value;
        $driverElement->sendKeys($driverValue);
        
        
        //Trigger change event
        $session->executeScript('(function() {' .
                                    'var element=document.getElementById(\'' . Convert::raw2js($element->getAttribute('id')) . '\');' .
                                    'element.dispatchEvent(new Event(\'change\', {\'bubbles\': true}));' .
                                '})()');
        
        
        //Trigger Key Down
        /*$driver->keyDown($xpath, substr($value, -1));
        $driver->keyUp($xpath, substr($value, -1));*/
        
        //Wait for the delay on jQuery UI's Autocomplete
        $session->wait(500);
        
        //Wait for ajax
        $this->handleAjaxTimeout();
        
        //Find Result
        $escaper = new \Behat\Mink\Selector\Xpath\Escaper();
        $element = $session->getPage()->find('xpath', 'descendant-or-self::*[@class and contains(concat(\' \', normalize-space(@class), \' \'), \' ui-autocomplete \')]/li/a[normalize-space(.)=' . $escaper->escapeLiteral($option) . ']');
        
        if ($element == null) {
            throw new \InvalidArgumentException(sprintf('Cannot find text: "%s"', $option));
        }
        
        $element->click();
        
        
        //Blur the field
        $driver->blur($xpath);
    }
    
    /**
     * @Then /^I wait for the loading to finish$/
     */
    public function handleAjaxTimeout()
    {
        $timeoutMs = $this->getMainContext()->getAjaxTimeout();

        // Wait for an ajax request to complete, but only for a maximum of 5 seconds to avoid deadlocks
        $this->getSession()->wait($timeoutMs, '(typeof(jQuery)=="undefined" || (0 === jQuery.active && 0 === jQuery(\':animated\').length))');

        // wait additional 100ms to allow DOM to update
        $this->getSession()->wait(100);
    }
    
    /**
     * @Then /^I switch to the cms popup$/
     */
    public function iSwitchToThePopup()
    {
        //Switch to the iframe Window
        $frame = $this->getSession()->getPage()->find('css', '.ss-ui-dialog:last-child iframe');
        
        $frameID = $frame->getAttribute('id');
        if (empty($frameID)) {
            $frameID = 'frame-' . sha1(uniqid(time()));
            $this->setElementId('.ss-ui-dialog:last-child iframe', $frameID);
        }
        
        //Switch to the frame based on it's index
        $this->getSession()->switchToIFrame($frameID);
    }
    
    /**
     * @Then /^I switch to the cms modal popup$/
     */
    public function iSwitchToTheModal()
    {
        //Switch to the iframe Window
        $frame = $this->getSession()->getPage()->find('css', '.modal-dialog:last-child iframe');
        
        $frameID = $frame->getAttribute('id');
        if (empty($frameID)) {
            $frameID = 'frame-' . sha1(uniqid(time()));
            $this->setElementId('.modal-dialog:last-child iframe', $frameID);
        }
        
        //Switch to the frame based on it's index
        $this->getSession()->switchToIFrame($frameID);
    }
    
    /**
     * @Then /^I switch back from the popup$/
     */
    public function iSwitchBackFromThePopup()
    {
        $this->getSession()->getDriver()->getWebDriver()->switchTo()->defaultContent();
    }
    
    /**
     * @Then /^I should( not |\s*)see an item edit form$/
     */
    public function iShouldSeeItemEditForm($negative)
    {
        $page = $this->getSession()->getPage();
        $form = $page->find('css', '#Form_ItemEditForm');
        
        if (trim($negative)) {
            assertNull($form, 'I should not see an item edit form');
        } else {
            assertNotNull($form, 'I should see an item edit form');
        }
    }
    
    /**
     * @Then /^I should( not |\s*)see a cms popup$/
     */
    public function iShouldSeeAPopup($negative)
    {
        $page = $this->getSession()->getPage();
        $dialog = $page->find('css', '.ss-ui-dialog:last-child');
        
        if (trim($negative)) {
            if (empty($dialog) || $dialog->isVisible() == true) {
                throw new \Exception('Found a visible CMS popup');
            }
        } else {
            assertNotNull($dialog, 'Could not find the cms popup');
            assertTrue($dialog->isVisible(), 'The cms popup was not visible');
        }
    }
    
    /**
     * @Then /^I navigate to the cms popup's page$/
     */
    public function iNavigateToPopupURL()
    {
        //Find the iframe's url
        $iframe = $this->getSession()->getPage()->findAll('css', '.ss-ui-dialog iframe');
        $iframe = array_pop($iframe);
        
        $this->getMainContext()->visit($iframe->getAttribute('src'));
    }
    
    /**
     * @Then /^I close the cms popup$/
     */
    public function iCloseThePopup()
    {
        //Find the dialog
        $dialog = $this->getSession()->getPage()->find('css', '.ss-ui-dialog:last-child');
        if (empty($dialog)) {
            throw new \Exception('Could not find the CMS popup');
        }
        
        $button = $dialog->getParent()->find('css', '.ui-dialog-titlebar .ui-dialog-titlebar-close');
        if (empty($button)) {
            throw new \Exception('Could not find the CMS popup close button');
        }
        
        $button->click();
    }
    
    /**
     * @Then /^I close the cms modal popup$/
     */
    public function iCloseTheModal()
    {
        //Find the dialog
        $dialog = $this->getSession()->getPage()->find('css', '.modal-dialog:last-child');
        if (empty($dialog)) {
            throw new \Exception('Could not find the CMS modal popup');
        }
        
        $button = $dialog->getParent()->find('css', '.modal-header .close');
        if (empty($button)) {
            throw new \Exception('Could not find the CMS modal popup close button');
        }
        
        $button->click();
    }
    
    /**
     * @Then /^I wait for the cms popup to load$/
     */
    public function iWaitForThePopupToLoad()
    {
        $timeoutMs = $this->getMainContext()->getAjaxTimeout();
        $this->getSession()->wait($timeoutMs, "document.getElementsByClassName('ui-dialog loading').length == 0");
    }
    
    /**
     * @Then /^the "(?P<selector>[^"]*)" table should(?P<negative>( not |\s*))contain "(?P<text>[^"]*)" in (?P<rowLocation>(any|(the (new|(((\d+)(st|nd|rd|th))|last|first) editable)))) row in a field in the "(?P<column>[^"]*)" column$/
     */
    public function theTableFieldShouldContain($selector, $negative, $text, $rowLocation, $column)
    {
        $escaper = new \Behat\Mink\Selector\Xpath\Escaper();
        $table = $this->getTable($selector);
        
        $columnElement = $table->find('xpath', '//thead/tr/th[contains(concat(\' \', normalize-space(@class), \' \'), ' . $escaper->escapeLiteral(' col-' . $column . ' ') . ') or contains(concat(\' \', normalize-space(@class), \' \'), ' . $escaper->escapeLiteral(' col-action_SetOrder' . $column . ' ') . ')]');
        if ($columnElement == null) {
            $columnElement = $table->find('xpath', '//thead/tr/th/*[contains(.,' . $escaper->escapeLiteral($column) . ')]/ancestor::th');
            if ($columnElement == null) {
                throw new \Exception('Could not find the "' . $column . '" column in the "' . $selector . '" grid field');
            }
            
            //Find the class attribute of the column
            $columnClass = [];
            if (preg_match('/((\s+)|^)col-((action_SetOrder)?)(.*)((\s+)|$)/', $columnElement->getAttribute('class'), $columnClass) == false) {
                throw new \Exception('Could not find the column name class');
            }
            
            $columnClass = $columnClass[5];
        } else {
            $columnClass = $column;
        }
        
        $element = null;
        if ($rowLocation == 'any') {
            $colElements = $table->findAll('css', 'tbody tr td.col-' . $columnClass);
            if (empty($colElements) || count($colElements) == 0) {
                throw new \Exception(sprintf('The column `%s` was not found in `%s` table', $column, $selector));
            }
            
            foreach ($colElements as $colElement) {
                $element = $colElement->find('xpath', '//input[contains(@value, ' . $this->getSession()->getSelectorsHandler()->xpathLiteral($text) . ')]');
                if ($element != null) {
                    break;
                }
            }
        } else {
            switch ($rowLocation) {
                case 'the first editable':
                    $rowSelector = ':first-child';
                    break;
                case 'the last editable':
                    $rowSelector = ':last-child';
                    break;
                case 'the new editable':
                    $rowSelector = '.ss-gridfield-inline-new:last-child';
                    break;
                default:
                    $pos = intval(str_replace(['st', 'nd', 'rd', 'the ', 'th', ' editable'], '', $rowLocation));
                    $rowSelector = ':nth-child(' . $pos . ')';
                    break;
            }
            
            $colElement = $table->find('css', 'tbody tr' . $rowSelector . ' td.col-' . $columnClass);
            if (empty($colElement)) {
                throw new \Exception(sprintf('The column `%s` was not found in `%s` table', $column, $selector));
            }
            
            $element = $colElement->find('xpath', '//input[contains(@value, ' . $this->getSession()->getSelectorsHandler()->xpathLiteral($text) . ')]');
        }
        
        if (trim($negative)) {
            assertNull($element, sprintf('Text `%s` found in column `%s` of `%s` table', $text, $column, $selector));
        } else {
            assertNotNull($element, sprintf('Text `%s` not found in column `%s` of `%s` table', $text, $column, $selector));
        }
    }
    
    /**
     * @Then /^the "(?P<selector>[^"]*)" table should(?P<negative>( not |\s*))have the field checked in (?P<rowLocation>(any|(the (new|(((\d+)(st|nd|rd|th))|last|first) editable)))) row in the "(?P<column>[^"]*)" column$/
     */
    public function theTableFieldShouldBeChecked($selector, $negative, $rowLocation, $column)
    {
        $escaper = new \Behat\Mink\Selector\Xpath\Escaper();
        $table = $this->getTable($selector);
        
        $columnElement = $table->find('xpath', '//thead/tr/th[contains(concat(\' \', normalize-space(@class), \' \'), ' . $escaper->escapeLiteral(' col-' . $column . ' ') . ') or contains(concat(\' \', normalize-space(@class), \' \'), ' . $escaper->escapeLiteral(' col-action_SetOrder' . $column . ' ') . ')]');
        if ($columnElement == null) {
            $columnElement = $table->find('xpath', '//thead/tr/th/*[contains(.,' . $escaper->escapeLiteral($column) . ')]/ancestor::th');
            if ($columnElement == null) {
                throw new \Exception('Could not find the "' . $column . '" column in the "' . $selector . '" grid field');
            }
            
            //Find the class attribute of the column
            $columnClass = [];
            if (preg_match('/((\s+)|^)col-((action_SetOrder)?)(.*)((\s+)|$)/', $columnElement->getAttribute('class'), $columnClass) == false) {
                throw new \Exception('Could not find the column name class');
            }
            
            $columnClass = $columnClass[5];
        } else {
            $columnClass = $column;
        }
        
        $element = null;
        if ($rowLocation == 'any') {
            $colElements = $table->findAll('css', 'tbody tr td.col-' . $columnClass);
            if (empty($colElements) || count($colElements) == 0) {
                throw new \Exception(sprintf('The column `%s` was not found in `%s` table', $column, $selector));
            }
            
            foreach ($colElements as $colElement) {
                $element = $colElement->find('css', 'input[type=checkbox]:checked');
                if ($element != null) {
                    break;
                }
            }
        } else {
            switch ($rowLocation) {
                case 'the first editable':
                    $rowSelector = ':first-child';
                    break;
                case 'the last editable':
                    $rowSelector = ':last-child';
                    break;
                case 'the new editable':
                    $rowSelector = '.ss-gridfield-inline-new:last-child';
                    break;
                default:
                    $pos = intval(str_replace(['st', 'nd', 'rd', 'the ', 'th', ' editable'], '', $rowLocation));
                    $rowSelector = ':nth-child(' . $pos . ')';
                    break;
            }
            
            $colElement = $table->find('css', 'tbody tr' . $rowSelector . ' td.col-' . $columnClass);
            if (empty($colElement)) {
                throw new \Exception(sprintf('The column `%s` was not found in `%s` table', $column, $selector));
            }
            
            $element = $colElement->find('css', 'input[type=checkbox]:checked');
        }
        
        if (trim($negative)) {
            assertNull($element, sprintf('The field was checked in the column `%s` of `%s` table', $column, $selector));
        } else {
            assertNotNull($element, sprintf('he field was not checked in the column `%s` of `%s` table', $column, $selector));
        }
    }
    
    /**
     * @Then /^the "(?P<selector>[^"]*)" table should(?P<negative>( not |\s*))have "(?P<optionValue>[^"]*)" selected in (?P<rowLocation>(any|(the (new|(((\d+)(st|nd|rd|th))|last|first) editable)))) row in a dropdown in the "(?P<column>[^"]*)" column$/
     */
    public function theTableSelectShouldBe($selector, $negative, $optionValue, $rowLocation, $column)
    {
        $escaper = new \Behat\Mink\Selector\Xpath\Escaper();
        $table = $this->getTable($selector);
        
        $columnElement = $table->find('xpath', '//thead/tr/th[contains(concat(\' \', normalize-space(@class), \' \'), ' . $escaper->escapeLiteral(' col-' . $column . ' ') . ') or contains(concat(\' \', normalize-space(@class), \' \'), ' . $escaper->escapeLiteral(' col-action_SetOrder' . $column . ' ') . ')]');
        if ($columnElement == null) {
            $columnElement = $table->find('xpath', '//thead/tr/th/*[contains(.,' . $escaper->escapeLiteral($column) . ')]/ancestor::th');
            if ($columnElement == null) {
                throw new \Exception('Could not find the "' . $column . '" column in the "' . $selector . '" grid field');
            }
            
            //Find the class attribute of the column
            $columnClass = [];
            if (preg_match('/((\s+)|^)col-((action_SetOrder)?)(.*)((\s+)|$)/', $columnElement->getAttribute('class'), $columnClass) == false) {
                throw new \Exception('Could not find the column name class');
            }
            
            $columnClass = $columnClass[5];
        } else {
            $columnClass = $column;
        }
        
        if ($rowLocation == 'any') {
            $colElements = $table->findAll('css', 'tbody tr td.col-' . $columnClass);
            if (empty($colElements) || count($colElements) == 0) {
                throw new \Exception(sprintf('The column `%s` was not found in `%s` table', $column, $selector));
            }
            
            $selectField = $columnElement->find('css', 'select');
            if ($selectField == null) {
                throw new \Exception(sprintf('Could not find a dropdown in the `%s` column in the `%s` table', $column, $selector));
            }
        } else {
            switch ($rowLocation) {
                case 'the first editable':
                    $rowSelector = ':first-child';
                    break;
                case 'the last editable':
                    $rowSelector = ':last-child';
                    break;
                case 'the new editable':
                    $rowSelector = '.ss-gridfield-inline-new:last-child';
                    break;
                default:
                    $pos = intval(str_replace(['st', 'nd', 'rd', 'the ', 'th', ' editable'], '', $rowLocation));
                    $rowSelector = ':nth-child(' . $pos . ')';
                    break;
            }
            
            $colElement = $table->find('css', 'tbody tr' . $rowSelector . ' td.col-' . $columnClass);
            if (empty($colElement)) {
                throw new \Exception(sprintf('The column `%s` was not found in `%s` table', $column, $selector));
            }
            
            $selectField = $colElement->find('css', 'select');
            if ($selectField == null) {
                throw new \Exception(sprintf('Could not find a dropdown in the `%s` column in the `%s` table', $column, $selector));
            }
        }
        
        
        $option = $selectField->find('named', ['option', $escaper->escapeLiteral($optionValue)]);
        if ($option == null) {
            throw new \Exception(sprintf('Could not find the option "%s" with value|text in the "%s" select', $optionValue, $column));
        }
        
        if (trim($negative)) {
            if ($option->isSelected()) {
                throw new \Exception(sprintf('The option "%s" was not selected in the "%s" select', $optionValue, $column));
            }
        } else {
            if (!$option->isSelected()) {
                throw new \Exception(sprintf('The option "%s" was not selected in the "%s" select', $optionValue, $column));
            }
        }
    }
    
    /**
     * @Given /^the row containing "([^"]*)" in the "([^"]*)" table should( not |\s*)have the class "([^"]*)" on (every|"([^"]*)") column$/
     */
    public function theRowContainingHaveClass($text, $selector, $negative, $class, $columnName)
    {
        $table = $this->getTable($selector);
        $negative = trim($negative);
        
        $element = $table->find('css', sprintf('tr.ss-gridfield-item:contains("%s")', $text));
        if ($element != null) {
            if ($element->getTagName() == 'td' || $element->getTagName() == 'th') {
                //Get the parent row
                $element = $element->getParent();
            }
            
            //If the column name is every then we look for all columns but the buttons column
            if ($columnName == 'every') {
                $columns = $element->findAll('css', 'td:not(.col-buttons):not(.action-menu)');
                if (count($columns) == 0) {
                    throw new \Exception(sprintf('Row containing `%s` in the `%s` table does not contain any valid columns', $text, $selector));
                }
                
                foreach ($columns as $colElement) {
                    if ($negative && $colElement->hasClass($class)) {
                        throw new \Exception(sprintf('A column in the row containing `%s` in the table `%s` has the class `%s`', $text, $selector, $class));
                    } else if (!$negative && !$colElement->hasClass($class)) {
                        throw new \Exception(sprintf('A column in the row containing `%s` in the table `%s` does not have the class `%s`', $text, $selector, $class));
                    }
                }
            } else {
                $colElement = $element->find('css', 'td.col-' . $columnName . '.' . $class);
                if ($negative) {
                    assertNull($colElement, sprintf('Column with the name `%s` has the class `%s` in `%s` table when looking for the row containing `%s`', $columnName, $class, $selector, $text));
                } else {
                    assertNotNull($colElement, sprintf('Column with the name `%s` does not have the class `%s` in `%s` table when looking for the row containing `%s`', $columnName, $class, $selector, $text));
                }
            }
        } else {
            throw new \Exception(sprintf('Column containing `%s` not found in `%s` table', $text, $selector));
        }
    }
    
    /**
     * @Then /^the hidden "([^"]*)" field should( not |\s*)contain "([^"]*)"$/
     */
    public function theHiddenFieldShouldContain($selector, $negative, $expected, $mode = 'contain')
    {
        $escaper = new \Behat\Mink\Selector\Xpath\Escaper();
        $escapedSelector = $escaper->escapeLiteral($selector);
        $field = $this->getSession()->getPage()->find('xpath', $this->getSession()->getSelectorsHandler()->selectorToXpath('css', 'input[name=' . $escapedSelector . '][type=hidden]'));
        
        if ($field == null) {
            $field = $this->getSession()->getPage()->find('xpath', $this->getSession()->getSelectorsHandler()->selectorToXpath('css', 'input[id=' . $escapedSelector . '][type=hidden]'));
        }
        
        assertNotNull($field, 'Could not find a field with the name|id ' . $selector);
        
        if (trim($negative)) {
            if ($mode == 'be empty') {
                $value = $field->getValue();
                if (empty($value)) {
                    throw new \Exception(sprintf('Value of the field "%s" was empty', $selector));
                }
            } else if (strpos($field->getValue(), $expected) !== false) {
                throw new \Exception(sprintf('Value of the field "%s" contains the expected "%s"', $selector, $expected));
            }
        } else {
            if ($mode == 'be empty') {
                $value = $field->getValue();
                if (!empty($value)) {
                    throw new \Exception(sprintf('Value of the field "%s" was not empty', $selector));
                }
            } else if (strpos($field->getValue(), $expected) === false) {
                throw new \Exception(sprintf('Value of the field "%s" does not contain the expected "%s"', $selector, $expected));
            }
        }
    }
    
    /**
     * @Then /^the hidden "([^"]*)" field should( not |\s*)be empty$/
     */
    public function theHiddenFieldShouldEmpty($selector, $negative)
    {
        $this->theHiddenFieldShouldContain($selector, $negative, '', 'be empty');
    }
    
    /**
     * @Then /^I fill in the hidden field "([^"]*)" with "([^"]*)"$/
     */
    public function fillInHiddenField($selector, $value)
    {
        $escaper = new \Behat\Mink\Selector\Xpath\Escaper();
        $escapedSelector = $escaper->escapeLiteral($selector);
        $field = $this->getSession()->getPage()->find('xpath', $this->getSession()->getSelectorsHandler()->selectorToXpath('css', 'input[name=' . $escapedSelector . '][type=hidden]'));
        
        if ($field == null) {
            $field = $this->getSession()->getPage()->find('xpath', $this->getSession()->getSelectorsHandler()->selectorToXpath('css', 'input[id=' . $escapedSelector . '][type=hidden]'));
        }
        
        assertNotNull($field, 'Could not find a field with the name|id ' . $selector);
        
        $this->getSession()->executeScript(
            'var element=document.getElementById(\'' . Convert::raw2js($field->getAttribute('id')) . '\');' .
            'element.value=\'' . Convert::raw2js($value) . '\';' .
            'if("createEvent" in document) {' .
                'var evt=document.createEvent("HTMLEvents");' .
                'evt.initEvent("change", false, true);' .
                'element.dispatchEvent(evt);' .
            '}else {' .
                'element.fireEvent("onchange");' .
            '}'
        );
    }
    
    /**
     * @Then /^I should( not |\s*)see "([^"]*)" attached to "([^"]*)"$/
     */
    public function iShouldNotSeeFileAttached($negative, $filename, $selector)
    {
        //Find the field
        $escaper = new \Behat\Mink\Selector\Xpath\Escaper();
        $element = $this->getSession()->getPage()->find('xpath', 'descendant-or-self::*[@class and (contains(concat(\' \', normalize-space(@class), \' \'), \' field \') or contains(concat(\' \', normalize-space(@class), \' \'), \' fieldgroup-field \') or contains(concat(\' \', normalize-space(@class), \' \'), \' form-group \'))]/label[normalize-space(.)=' . $escaper->escapeLiteral($selector) . ']');
        
        if (!$element) {
            $element = $this->getSession()->getPage()->find('xpath', 'descendant-or-self::*[@class and (contains(concat(\' \', normalize-space(@class), \' \'), \' field \') or contains(concat(\' \', normalize-space(@class), \' \'), \' fieldgroup-field \') or contains(concat(\' \', normalize-space(@class), \' \'), \' field-group \'))]/div[@class and contains(concat(\' \', normalize-space(@class), \' \'), \' form__field-holder \')]/label[normalize-space(.)=' . $escaper->escapeLiteral($selector) . ']');
        }
        
        //Verify we found the field
        assertNotNull($element, sprintf('Field with the label "%s" was not found', $selector));
        
        $element = $element->getParent();
        
        //Find the file name's label
        $fileItem = $element->find('xpath', '//div[@class and (contains(concat(\' \', normalize-space(@class), \' \'), \' uploadfield-item__details \'))]/div/span[@class and contains(concat(\' \', normalize-space(@class), \' \'), \' uploadfield-item__title \') and contains(., ' . $escaper->escapeLiteral($filename) . ')]');
        
        if (trim($negative)) {
            assertNull($fileItem, sprintf('The file "%s" was attached to the "%s" field', $filename, $selector));
        } else {
            assertNotNull($fileItem, sprintf('The file "%s" was not attached to the "%s" field', $filename, $selector));
        }
    }
    
    /**
     * @Then /^I select the (first|last|((\d+)(st|nd|rd|th))) option from "(?P<select>[^"]+)"$/
     */
    public function selectTheOrderedOption($position, $select)
    {
        $field = $this->getSession()->getPage()->findField($select);
        
        // If field is visible then select it as per normal
        if ($field) {
            //Find the appropriate option
            if ($position == 'first') {
                $option = $field->find('css', 'option:first-child');
            } else if ($position == 'last') {
                $option = $field->find('css', 'option:last-child');
            } else {
                $option = $field->find('css', 'option:nth-child(' . intval($position) . ')');
            }
            
            if (!$option) {
                throw new \InvalidArgumentException(sprintf('Could not find the %s option in the "%s" select', $position, $select));
            }
            
            if ($field->isVisible()) {
                if (!$option->selected()) {
                    $option->click();
                }
            } else {
                //Build and run the script
                $script = '(function($) {' .
                            '$("#' . $field->getAttribute('ID') . '")' .
                                '.val(' . json_encode($option->getAttribute('value')) . ')' .
                                '.change()' .
                                '.trigger(\'liszt:updated\')' .
                                '.trigger(\'chosen:updated\');' .
                        '})(jQuery);';
                
                $this->getSession()->getDriver()->executeScript($script);
            }
        } else {
            throw new \InvalidArgumentException(sprintf('Could not find the select "%s" with the specified id|name|label|value', $select));
        }
    }
    
    /**
     * @Given /^the event containing "([^"]*)" in the "([^"]*)" calendar should( not |\s*)have the class "([^"]*)"$/
     */
    public function theEventContainingHaveClass($text, $selector, $negative, $class)
    {
        $selector = $this->getSession()->getSelectorsHandler()->xpathLiteral($selector);
        $page = $this->getSession()->getPage();
        $candidates = $page->findAll('xpath', "//fieldset[@data-name=$selector]//div[contains(@class, 'fc-day-grid')]");
        
        assertTrue((bool) $candidates, 'Could not find any calendar elements');
        
        $calendar = null;
        foreach ($candidates as $candidate) {
            if (!$calendar && $candidate->isVisible()) {
                $calendar = $candidate;
            }
        }
        
        assertTrue((bool) $calendar, 'Found calendar elements, but none are visible');
        
        $negative = trim($negative);
        
        $element = $calendar->find('named', ['content', "'$text'"]);
        if ($element != null) {
            //Get the event's link which has the classes on it
            $element = $element->getParent()->getParent();
            
            if ($negative) {
                assertFalse($element->hasClass($class), sprintf('Event has the class `%s` in `%s` calendar when looking for an event containing `%s`', $class, $selector, $text));
            } else {
                assertTrue($element->hasClass($class), sprintf('Event does not have the class `%s` in `%s` calendar when looking for an event containing `%s`', $class, $selector, $text));
            }
        } else {
            throw new \Exception(sprintf('Event containing `%s` not found in `%s` calendar', $text, $selector));
        }
    }
    
    /**
     * @Then /^the "([^"]*)" option is selected in the "([^"]*)" dropdown$/
     */
    public function optionFromDropdownIsSelected($optionValue, $select)
    {
        $escaper = new \Behat\Mink\Selector\Xpath\Escaper();
        
        $selectField = $this->getSession()->getPage()->find('named', ['select', $this->getSession()->getSelectorsHandler()->xpathLiteral($select)]);
        if ($selectField == null) {
            throw new \Exception(sprintf('The select "%s" was not found', $select));
        }
        
        $option = $selectField->find('named', ['option', $escaper->escapeLiteral($optionValue)]);
        if ($option == null) {
            throw new \Exception(sprintf('Could not find the option "%s" with id|name|label|value in the "%s" select', $optionValue, $select));
        }
        
        if (!$option->isSelected()) {
            throw new \Exception(sprintf('The option "%s" was not selected in the "%s" select', $optionValue, $select));
        }
    }
    
    /**
     * @Given /^I select the "([^"]*)" option in the "([^"]*)" tree dropdown$/
     */
    public function selectTreeDropdownOption($optionValue, $field)
    {
        $formFields = $this->getSession()->getPage()->findAll('xpath', "//*[@name='$field']");
        
        // Find by label
        if (!$formFields) {
            $label = $this->getSession()->getPage()->find('xpath', "//label[.='$field']");
            if ($label && $for = $label->getAttribute('for')) {
                $formField = $this->getSession()->getPage()->find('xpath', "//*[@id='$for']");
                if ($formField) {
                    $formFields[] = $formField;
                }
            }
        }
        
        assertGreaterThan(0, count($formFields), sprintf('Tree dropdown named "%s" not found', $field));
        
        // Traverse up to field holder
        $container = null;
        foreach ($formFields as $formField) {
            $container = $this->findParentByClass($formField, 'field');
            if ($container && $container->hasClass('treedropdown')) {
                break; // Default to first visible container
            }
        }
        
        assertNotNull($container, sprintf('Tree dropdown named "%s" not found', $field));
        
        
        //If we have a loading spinner wait for it to go away
        if ($container->find('css', '.Select-loading')) {
            $this->spin(function () use ($container) {
                return ($container->find('css', '.Select-loading') != null);
            }, $this->getMainContext()->getAjaxTimeout());
        }
        
        
        //Fill in the text in the dropdown
        $searchField = $container->find('css', '.Select-input input');
        assertNotNull($searchField, 'Could not find the text field in the "' . $field . '" tree dropdown');
        $searchField->setValue($optionValue);
        
        
        //Wait for options
        $this->spin(function () use ($container) {
            return ($container->find('css', '.Select-menu-outer .Select-option') != null);
        }, $this->getMainContext()->getAjaxTimeout());
        
        
        //Find the dropdown option
        $option = $container->find('css', '.Select-menu-outer .Select-option:contains("' . $optionValue . '")');
        
        assertNotNull($option, 'Could not find the option "' . $optionValue . '" in the "' . $field . '" tree dropdown');
        
        //Select the option
        $option->click();
    }
    
    /**
     * @Then /^"([^"]*)" is selected in the "([^"]*)" tree dropdown$/
     */
    public function treeDropdownIsSetTo($optionValue, $field)
    {
        $formFields = $this->getSession()->getPage()->findAll('xpath', "//*[@name='$field']");
        
        // Find by label
        if (!$formFields) {
            $label = $this->getSession()->getPage()->find('xpath', "//label[.='$field']");
            if ($label && $for = $label->getAttribute('for')) {
                $formField = $this->getSession()->getPage()->find('xpath', "//*[@id='$for']");
                if ($formField) {
                    $formFields[] = $formField;
                }
            }
        }
        
        assertGreaterThan(0, count($formFields), sprintf('Tree dropdown named "%s" not found', $field));
        
        // Traverse up to field holder
        $container = null;
        foreach ($formFields as $formField) {
            $container = $this->findParentByClass($formField, 'field');
            if ($container && $container->hasClass('treedropdown')) {
                break; // Default to first visible container
            }
        }
        
        assertNotNull($container, sprintf('Tree dropdown named "%s" not found', $field));
        
        
        //Find the title element
        $selected = $container->find('css', '.Select-value-label');
        
        assertNotNull($container, 'Could not find the tree dropdown\'s title element');
        
        //Confirm the selected title
        assertEquals($selected->getText(), $optionValue, sprintf('Tree dropdown named "%s" does not have "%s" selected', $field, $optionValue));
    }
    
    /**
     * @Given /^I press the "([^"]*)" button in the "([^"]*)" grid field$/
     */
    public function pressTheGridFieldButton($button, $label)
    {
        //Find the GridField
        $gridField = $this->getTable($label);
        
        
        //Make sure we can find the grid field
        assertNotNull($gridField, sprintf('Grid field "%s" not found', $label));
        assertTrue($gridField->isVisible(), sprintf('Grid field "%s" was not visible', $label));
        
        
        //If we have the table get the fieldset
        if ($gridField->hasClass('grid-field__table')) {
            $gridField = $gridField->getParent();
        }
        
        
        $gridField->pressButton($button);
    }
    
    /**
     * @Given /^I (?P<mode>(fill in|check|uncheck)) "(?P<column>[^"]*)" in the (?P<rowLocation>(new row|(((\d+)(st|nd|rd|th))|last|first) editable row)) in the "(?P<gridFieldLabel>[^"]*)" grid field(( with "(?P<value>[^"]*)")?)$/
     */
    public function fillInGridFieldEditableField($mode, $column, $rowLocation, $gridFieldLabel, $value = '')
    {
        $escaper = new \Behat\Mink\Selector\Xpath\Escaper();
        
        //Find the GridField
        $gridField = $this->getTable($gridFieldLabel);
        
        
        //Make sure we can find the grid field
        assertNotNull($gridField, sprintf('Grid field "%s" not found', $gridFieldLabel));
        assertTrue($gridField->isVisible(), sprintf('Grid field "%s" was not visible', $gridFieldLabel));
        
        
        $columnElement = $gridField->find('xpath', '//thead/tr/th[contains(concat(\' \', normalize-space(@class), \' \'), ' . $escaper->escapeLiteral(' col-' . $column . ' ') . ') or contains(concat(\' \', normalize-space(@class), \' \'), ' . $escaper->escapeLiteral(' col-action_SetOrder' . $column . ' ') . ')]');
        if ($columnElement == null) {
            $columnElement = $gridField->find('xpath', '//thead/tr/th/*[contains(.,' . $escaper->escapeLiteral($column) . ')]/ancestor::th');
            if ($columnElement == null) {
                throw new \Exception('Could not find the "' . $column . '" column in the "' . $gridFieldLabel . '" grid field');
            }
            
            //Find the class attribute of the column
            $columnClass = [];
            if (preg_match('/((\s+)|^)col-((action_SetOrder)?)(.*)((\s+)|$)/', $columnElement->getAttribute('class'), $columnClass) == false) {
                throw new \Exception('Could not find the column name class');
            }
            
            $columnClass = $columnClass[5];
        } else {
            $columnClass = $column;
        }
        
        switch ($rowLocation) {
            case 'first editable row':
                $rowSelector = ':first-child';
                break;
            case 'last editable row':
                $rowSelector = ':last-child';
                break;
            case 'new row':
                $rowSelector = '.ss-gridfield-inline-new:last-child';
                break;
            default:
                $pos = intval(str_replace(['st', 'nd', 'rd', 'th', ' editable', ' row'], '', $rowLocation));
                $rowSelector = ':nth-child(' . $pos . ')';
                break;
        }
        
        /** @var $field NodeElement **/
        $field = $gridField->find('css', 'tbody tr' . $rowSelector . ' td.col-' . $columnClass . ' input:not([type=\'hidden\']):not([type=\'button\']):not([type=\'submit\']):not([type=\'reset\']):not([type=\'radio\'])');
        if ($field != null) {
            if ($mode == 'fill in') {
                if ($field->getAttribute('type') == 'date' || $field->getAttribute('type') == 'time' || $field->getAttribute('type') == 'datetime-local') {
                    switch ($field->getAttribute('type')) {
                        case 'date':
                            $value = date('Y-m-d', strtotime($value));
                            break;
                        case 'time':
                            $value = date('H:i:s', strtotime($value));
                            break;
                        case 'datetime-local':
                            $value = date('Y-m-d\TH:i:s', strtotime($value));
                            break;
                    }
                    
                    $this->getSession()->executeScript('(function() {' .
                                                            'var field=document.getElementById("' . $field->getAttribute('id') . '");' .
                                                            'field.value="' . Convert::raw2js($value) . '";' .
                                                            'field.dispatchEvent(new Event("change"));' .
                                                        '})();');
                } else {
                    $field->setValue($value);
                }
            } else if ($mode == 'check' || $mode == 'uncheck') {
                if ($field->getAttribute('type') == 'checkbox') {
                    if ($mode == 'check') {
                        $field->check();
                    } else {
                        $field->uncheck();
                    }
                } else {
                    throw new \Exception('Could not find an input in the "' . $column . '" column of the "' . $gridFieldLabel . '" grid field is not a checkbox');
                }
            }
        } else {
            $field = $gridField->find('css', 'tbody tr' . $rowSelector . ' td.col-' . $columnClass . ' textarea');
            if ($field != null) {
                $field->setValue($value);
            } else {
                throw new \Exception('Could not find an input or textarea in the "' . $column . '" column of the "' . $gridFieldLabel . '" grid field');
            }
        }
    }
    
    /**
     * @Given /^I select "(?P<value>[^"]*)" in "(?P<column>[^"]*)" in the (?P<rowLocation>(new row|(((\d+)(st|nd|rd|th))|last|first) editable row)) in the "(?P<gridFieldLabel>[^"]*)" grid field$/
     */
    public function selectOptionGridFieldEditableField($value, $column, $type, $rowLocation, $gridFieldLabel)
    {
        $escaper = new \Behat\Mink\Selector\Xpath\Escaper();
        
        //Find the GridField
        $gridField = $this->getTable($gridFieldLabel);
        
        
        //Make sure we can find the grid field
        assertNotNull($gridField, sprintf('Grid field "%s" not found', $gridFieldLabel));
        assertTrue($gridField->isVisible(), sprintf('Grid field "%s" was not visible', $gridFieldLabel));
        
        
        $columnElement = $gridField->find('xpath', '//thead/tr/th[contains(concat(\' \', normalize-space(@class), \' \'), ' . $escaper->escapeLiteral(' col-' . $column . ' ') . ') or contains(concat(\' \', normalize-space(@class), \' \'), ' . $escaper->escapeLiteral(' col-action_SetOrder' . $column . ' ') . ')]');
        if ($columnElement == null) {
            $columnElement = $gridField->find('xpath', '//thead/tr/th/*[contains(.,' . $escaper->escapeLiteral($column) . ')]/ancestor::th');
            if ($columnElement == null) {
                throw new \Exception('Could not find the "' . $column . '" column in the "' . $gridFieldLabel . '" grid field');
            }
            
            //Find the class attribute of the column
            $columnClass = [];
            if (preg_match('/((\s+)|^)col-((action_SetOrder)?)(.*)((\s+)|$)/', $columnElement->getAttribute('class'), $columnClass) == false) {
                throw new \Exception('Could not find the column name class');
            }
            
            $columnClass = $columnClass[5];
        } else {
            $columnClass = $column;
        }
        
        switch ($rowLocation) {
            case 'first editable row':
                $rowSelector = ':first-child';
                break;
            case 'last editable row':
                $rowSelector = ':last-child';
                break;
            case 'new row':
                $rowSelector = '.ss-gridfield-inline-new:last-child';
                break;
            default:
                $pos = intval(str_replace(['st', 'nd', 'rd', 'th', ' editable', ' row'], '', $rowLocation));
                $rowSelector = ':not(.ss-gridfield-no-items)';
                break;
        }
        
        if ($rowSelector == ':not(.ss-gridfield-no-items)') {
            $rows = $gridField->findAll('css', 'tbody tr' . $rowSelector);
            if (count($rows) > 0 && array_key_exists($pos - 1, $rows)) {
                $columnElement = $rows[$pos - 1]->find('css', 'td.col-' . $columnClass);
            } else {
                throw new \Exception('Could not find a row in the "' . $rowSelector . '" position in the "' . $gridFieldLabel . '" grid field');
            }
        } else {
            $columnElement = $gridField->find('css', 'tbody tr' . $rowSelector . ' td.col-' . $columnClass);
        }
        
        if ($columnElement != null) {
            $field = $columnElement->find('css', 'select');
            if ($field != null) {
                $option = $field->find('named', ['option', $value]);
                if ($option == null) {
                    throw new \Exception('Could not find the option by value or label "' . $value . '" in the "' . $rowLocation . '"\'s column "' . $column . '" in the "' . $gridFieldLabel . '" grid field');
                }
                
                //Work around for selecting options since it seems to fail in this case
                $driverField = new WebDriverSelect($this->getSession()->getDriver()->getWebDriver()->findElement(WebDriverBy::id($field->getAttribute('id'))));
                if ($option->getAttribute('value') == $value) {
                    $driverField->selectByValue($value);
                } else {
                    $driverField->selectByVisibleText($value);
                }
            } else {
                $field = $columnElement->find('named', ['radio', $escaper->escapeLiteral($value)]);
                if ($field != null) {
                    $field->click();
                } else {
                    throw new \Exception('Could not find a radio or select in the column "' . $column . '" in the "' . $gridFieldLabel . '" grid field');
                }
            }
        } else {
            throw new \Exception('Could not find a row with the column "' . $column . '" in the "' . $gridFieldLabel . '" grid field');
        }
    }
    
    /**
     * @Then /^the "([^"]*)" option is selected in the "([^"]*)" radio group$/
     */
    public function optionFromRadioGroupIsSelected($optionValue, $radioGroupName)
    {
        $page = $this->getSession()->getPage();
        $escaper = new \Behat\Mink\Selector\Xpath\Escaper();
        
        $element = $page->find('xpath', 'descendant-or-self::*[@class and (contains(concat(\' \', normalize-space(@class), \' \'), \' field \') or contains(concat(\' \', normalize-space(@class), \' \'), \' fieldgroup-field \'))]/label[normalize-space(.)=' . $escaper->escapeLiteral($radioGroupName) . ']');
        if ($element == null) {
            $element = $page->find('named', ['id_or_name', $escaper->escapeLiteral($radioGroupName)]);
            
            if ($element == null || $element->getTagName() != 'input' || $element->getAttribute('type') != 'radio') {
                throw new \Exception(sprintf('The radio group "%s" was not found', $radioGroupName));
            }
        }
        
        $element = $this->findParentByClass($element, 'field');
        if ($element == null || !$element->hasClass('optionset')) {
            throw new \Exception('Could not find the parent element of the radio group');
        }
        
        $selectedOption = $element->find('named', ['radio', $escaper->escapeLiteral($optionValue)]);
        if ($selectedOption == null) {
            throw new \Exception(sprintf('Could not find an option with the id|name|label "%s" in the "%s" radio group', $optionValue, $radioGroupName));
        }
        
        if (!$selectedOption->isChecked()) {
            throw new \Exception(sprintf('The option "%s" was not selected in the "%s" radio group', $optionValue, $radioGroupName));
        }
    }
    
    /**
     * @When /^I move the panel "([^"]*)" before the panel "([^"]*)" in the "([^"]*)" editor$/
     */
    public function iDragElementToElement($srcPanelName, $destPanelName, $context)
    {
        $escaper = new \Behat\Mink\Selector\Xpath\Escaper();
        
        
        //Try to find the parent element
        $parent = $this->getSession()->getPage()->findById($context);
        if ($parent == null) {
            throw new \Exception('Could not find the panel area editor with the id "' . $context . '"');
        }
        
        
        //Try to find the source panel
        $srcPanel = $parent->find('xpath', 'descendant-or-self::div[@class and contains(concat(\' \', normalize-space(@class), \' \'), \' panel-wrapper \')]/div[@class and contains(concat(\' \', normalize-space(@class), \' \'), \' panel-heading \')]/h3/span[normalize-space(.)=' . $escaper->escapeLiteral($srcPanelName) . ']');
        if ($srcPanel == null) {
            throw new \Exception('Panel to move "' . $srcPanelName . '" could not be found in the panel area editor "' . $context . '"');
        }
        
        
        //Try to find the target position panel
        $destPanel = $parent->find('xpath', 'descendant-or-self::div[@class and contains(concat(\' \', normalize-space(@class), \' \'), \' panel-wrapper \')]/div[@class and contains(concat(\' \', normalize-space(@class), \' \'), \' panel-heading \')]/h3/span[normalize-space(.)=' . $escaper->escapeLiteral($destPanelName) . ']');
        if ($destPanel == null) {
            throw new \Exception('Panel to move before "' . $destPanelName . '" could not be found in the panel area editor "' . $context . '"');
        }
        
        
        //$srcPanel->dragTo($destPanel); //@TODO Replace the below with this line when silverstripe/MinkFacebookWebDriver#1 is merged and released
        $this->drag($srcPanel, $destPanel);
    }
    
    /**
     * @When /^I move the (?P<srcRowNumber>(\d+(st|nd|rd|th))) row in the "(?P<gridFieldLabel>[^"]*)" table before the (?P<destRowNumber>(\d+(st|nd|rd|th))) row$/
     */
    public function iDragRowToRow($srcRowNumber, $gridFieldLabel, $destRowNumber)
    {
        //Find the GridField
        $gridField = $this->getTable($gridFieldLabel);
        
        
        //Make sure we can find the grid field
        assertNotNull($gridField, sprintf('Grid Field "%s" not found', $gridFieldLabel));
        assertTrue($gridField->isVisible(), sprintf('Grid Field "%s" was not visible', $gridFieldLabel));
        
        
        //If we're using SortableGridField find the first column if not fall over to assuming we're using GridFieldExtensions and find the handle
        if ($gridField->find('css', 'table.grid-field__table thead .gridfield-sortablerows')) {
            //Try to find the source row
            /** @var $srcRow \Behat\Mink\Element\NodeElement **/
            $srcRow = $gridField->find('css', 'table.grid-field__table tbody.ss-gridfield-items tr.ss-gridfield-item:nth-child(' . intval(str_replace(['st', 'nd', 'rd', 'th'], '', $srcRowNumber)) . ') td:first-child');
            if ($srcRow == null) {
                throw new \Exception('Row to move "' . $srcRowNumber . '" could not be found in the Grid Field "' . $gridFieldLabel . '"');
            }
            
            
            //Try to find the target position panel
            /** @var $destRow \Behat\Mink\Element\NodeElement **/
            $destRow = $gridField->find('css', 'table.grid-field__table tbody.ss-gridfield-items tr.ss-gridfield-item:nth-child(' . intval(str_replace(['st', 'nd', 'rd', 'th'], '', $destRowNumber)) . ') td:first-child');
            if ($destRow == null) {
                throw new \Exception('Row to move before "' . $destRowNumber . '" could not be found in the Grid Field "' . $gridFieldLabel . '"');
            }
        } else {
            //Try to find the source row
            /** @var $srcRow \Behat\Mink\Element\NodeElement **/
            $srcRow = $gridField->find('css', 'table.grid-field__table tbody.ss-gridfield-items tr.ss-gridfield-item:nth-child(' . intval(str_replace(['st', 'nd', 'rd', 'th'], '', $srcRowNumber)) . ') td:first-child .handle');
            if ($srcRow == null) {
                throw new \Exception('Row to move "' . $srcRowNumber . '" could not be found in the Grid Field "' . $gridFieldLabel . '"');
            }
            
            
            //Try to find the target position panel
            /** @var $destRow \Behat\Mink\Element\NodeElement **/
            $destRow = $gridField->find('css', 'table.grid-field__table tbody.ss-gridfield-items tr.ss-gridfield-item:nth-child(' . intval(str_replace(['st', 'nd', 'rd', 'th'], '', $destRowNumber)) . ') td:first-child .handle');
            if ($destRow == null) {
                throw new \Exception('Row to move before "' . $destRowNumber . '" could not be found in the Grid Field "' . $gridFieldLabel . '"');
            }
        }
        
        
        //$srcRow->dragTo($destRow); //@TODO Replace the below with this line when silverstripe/MinkFacebookWebDriver#1 is merged and released
        $this->drag($srcRow, $destRow);
    }
    
    /**
     * @When /^I add a(n?) "(?P<itemType>[^"]*)" to the "(?P<gridFieldLabel>[^"]*)" table$/
     */
    public function iAddAItemToTable($itemType, $gridFieldLabel)
    {
        $escaper = new \Behat\Mink\Selector\Xpath\Escaper();
        
        //Find the GridField
        $gridField = $this->getTable($gridFieldLabel);
        
        
        //Make sure we can find the grid field
        assertNotNull($gridField, sprintf('Grid Field "%s" not found', $gridFieldLabel));
        assertTrue($gridField->isVisible(), sprintf('Grid Field "%s" was not visible', $gridFieldLabel));
        
        //Get the fieldset around the GridField table
        $gridField = $gridField->getParent();
        
        
        //Find the dropdown
        $itemTypeDrop = $gridField->find('css', '.addNewItemTypeButton select');
        assertNotNull($gridField, sprintf('Could not find the item type dropdown in the "%s" Grid Field', $gridFieldLabel));
        assertTrue($gridField->isVisible(), sprintf('Item type dropdown in the "%s" Grid Field was not visible', $gridFieldLabel));
        
        
        //Find the add button
        $itemTypeButton = $gridField->find('css', '.addNewItemTypeButton a.new-item-type-add');
        assertNotNull($gridField, sprintf('Could not find the item type add button in the "%s" Grid Field', $gridFieldLabel));
        assertTrue($gridField->isVisible(), sprintf('Item type add button in the "%s" Grid Field was not visible', $gridFieldLabel));
        
        
        //Attempt to find the option in the dropdown
        $option = $itemTypeDrop->find('named', ['option', $escaper->escapeLiteral($itemType)]);
        assertNotNull($gridField, sprintf('Could not find the "%s" option in the item type dropdown for the "%s" Grid Field', $itemType, $gridFieldLabel));
        
        
        //Select the option
        if ($itemTypeDrop && $itemTypeDrop->isVisible()) {
            $itemTypeDrop->selectOption($option->getAttribute('value'));
            assertEquals($itemTypeDrop->getValue(), $option->getAttribute('value'), 'Could not select the option');
        } else {
            $container = $this->findParentByClass($itemTypeDrop, 'field');
            assertNotNull($container, 'Chosen.js field container not found');
            
            //Click on newly expanded list element, indirectly setting the dropdown value
            $linkEl = $container->find('css', 'a.chosen-single');
            assertNotNull($linkEl, 'Chosen.js link element not found');
            $linkEl->click();
            
            //Wait for dropdown overlay to appear (might be animated)
            $this->getSession()->wait(300);
            
            //Find the option in the chosen dropdown
            $listEl = $container->find('xpath', sprintf('.//li[contains(normalize-space(string(.)), %s)]', $escaper->escapeLiteral(trim(strip_tags($option->getHtml())))));
            assertNotNull($listEl, sprintf('Chosen.js list element with title "%s" not found', $itemType));
            
            //Click the element
            $listLinkEl = $listEl->find('xpath', './/a');
            if ($listLinkEl) {
                $listLinkEl->click();
            } else {
                $listEl->click();
            }
        }
        
        
        //Click the add button
        $itemTypeButton->click();
    }
    
    /**
     * @Then /^the "(?P<selector>[^"]*)" table should(?P<negative>( not |\s*))contain "(?P<text>[^"]*)" in the (?P<rowLocation>(((\d+)(st|nd|rd|th))|last|first)) row of the table$/
     */
    public function theTableShouldContainOnRow($selector, $negative, $text, $rowLocation)
    {
        $table = $this->getTable($selector);
        
        $element = null;
        switch ($rowLocation) {
            case 'first':
                $rowSelector = ':first-child';
                break;
            case 'last':
                $rowSelector = ':last-child';
                break;
            default:
                $pos = intval(str_replace(['st', 'nd', 'rd', 'th'], '', $rowLocation));
                $rowSelector = ':nth-child(' . $pos . ')';
                break;
        }
        
        $rowElement = $table->find('css', 'tbody tr' . $rowSelector);
        if (empty($rowElement)) {
            throw new \Exception(sprintf('The row `%s` was not found in `%s` table', $rowLocation, $selector));
        }
        
        $element = $rowElement->find('named', ['content', $this->getSession()->getSelectorsHandler()->xpathLiteral($text)]);
        
        if (trim($negative)) {
            assertNull($element, sprintf('Text `%s` found in the %s row of `%s` table', $text, $rowLocation, $selector));
        } else {
            assertNotNull($element, sprintf('Text `%s` not found in the %s row of `%s` table', $text, $rowLocation, $selector));
        }
    }
    
    /**
     * @Then /^the (?P<rowLocation>(((\d+)(st|nd|rd|th))|last|first)) row in the "(?P<selector>[^"]*)" table should(?P<negative>( not |\s*))have the class "(?P<className>[^"]*)"$/
     */
    public function theTableShouldHaveClassOnRow($rowLocation, $selector, $negative, $className)
    {
        $table = $this->getTable($selector);
        
        $element = null;
        switch ($rowLocation) {
            case 'first':
                $rowSelector = ':first-child';
                break;
            case 'last':
                $rowSelector = ':last-child';
                break;
            default:
                $pos = intval(str_replace(['st', 'nd', 'rd', 'th'], '', $rowLocation));
                $rowSelector = ':nth-child(' . $pos . ')';
                break;
        }
        
        $rowElement = $table->find('css', 'tbody tr' . $rowSelector);
        if (empty($rowElement)) {
            throw new \Exception(sprintf('The row `%s` was not found in `%s` table', $rowLocation, $selector));
        }
        
        
        if (trim($negative)) {
            assertFalse($rowElement->hasClass($className), sprintf('%s row in the `%s` table has the class `%s`', $rowLocation, $selector, $className));
        } else {
            assertTrue($rowElement->hasClass($className), sprintf('%s row in the `%s` table does not have the class `%s`', $rowLocation, $selector, $className));
        }
    }
    
    /**
     * @Given /^I click on "([^"]*)" in the "([^"]*)" Grid Field$/
     * @param string $text
     * @param string $selector
     */
    public function iClickOnInTheTable($text, $selector)
    {
        $table = $this->getTable($selector);
        $element = $table->find('css', sprintf('tr.ss-gridfield-item:contains("%s")', $text));
        assertNotNull($element, sprintf('Element containing `%s` not found', $text));
        $element->click();
    }
    
    /**
     * @Then /^I should( not |\s*)see a WYSIWYG popup$/
     */
    public function iShouldSeeWYSIWIGPopup($negative)
    {
        $page = $this->getSession()->getPage();
        $dialog = $page->find('css', '.htmleditorfield-dialog:last-child');
        
        //If a dialog with the class htmleditorfield-dialog was not found try the default tinymce class
        if ($dialog == null) {
            $dialog = $page->find('css', '.mce-window');
        }
        
        
        if (trim($negative)) {
            if (empty($dialog) || $dialog->isVisible() == true) {
                throw new \Exception('Found a visible WYSIWYG popup');
            }
        } else {
            assertNotNull($dialog, 'Could not find the WYSIWYG popup');
            assertTrue($dialog->isVisible(), 'The WYSIWYG popup was not visible');
        }
    }
    
    /**
     * @When /^I highlight "(?P<text>((?:[^"]|\\")*))" in the "(?P<field>(?:[^"]|\\")*)" HTML field$/
     */
    public function iHighlightTextInHtmlField($text, $field)
    {
        $inputField = $this->getHtmlField($field);
        $inputFieldId = $inputField->getAttribute('id');
        $text = addcslashes(str_replace('\\"', '"', $text), "'");

        $js = <<<JS
// TODO <IE9 support
// TODO Allow text matches across nodes
var editor = jQuery('#$inputFieldId').entwine('ss').getEditor(),
    doc = editor.getInstance().getDoc(),
    sel = editor.getInstance().selection,
    rng = document.createRange(),
    matched = false;

jQuery(doc).find('body *').each(function() {
    if(!matched) {
        for(var i=0;i<this.childNodes.length;i++) {
            if(!matched && this.childNodes[i].nodeValue && this.childNodes[i].nodeValue.match('$text')) {
                rng.setStart(this.childNodes[i], this.childNodes[i].nodeValue.indexOf('$text'));
                rng.setEnd(this.childNodes[i], this.childNodes[i].nodeValue.indexOf('$text') + '$text'.length);
                sel.setRng(rng);
                editor.getInstance().nodeChanged();
                matched = true;
                break;
            }else if(!matched && this.childNodes[i].outerHTML=='$text') {
                rng.selectNode(this.childNodes[i]);
                sel.setRng(rng);
                editor.getInstance().nodeChanged();
                matched = true;
                break;
            }
        }
    }
});

if(!matched) {
    throw new Error('Could not find the text "$text" in the editor');
}
JS;

        $this->getSession()->executeScript($js);
    }
    
    /**
     * @Given I take a screenshot
     */
    public function iTakeAScreenshot()
    {
        // Validate driver
        $driver = $this->getSession()->getDriver();
        if (!($driver instanceof FacebookWebDriver)) {
            file_put_contents('php://stdout', 'ScreenShots are only supported for FacebookWebDriver: skipping');
            return;
        }
        
        $feature = $this->_feature;
        $step = $this->_step;
        
        // Check paths are configured
        $path = $this->getMainContext()->getScreenshotPath();
        if (!$path) {
            file_put_contents('php://stdout', 'ScreenShots path not configured: skipping');
            return;
        }
        
        Filesystem::makeFolder($path);
        $path = realpath($path);
        if (!file_exists($path)) {
            file_put_contents('php://stderr', sprintf('"%s" is not valid directory and failed to create it' . PHP_EOL, $path));
            return;
        }
        
        if (file_exists($path) && !is_dir($path)) {
            file_put_contents('php://stderr', sprintf('"%s" is not valid directory' . PHP_EOL, $path));
            return;
        }
        
        if (file_exists($path) && !is_writable($path)) {
            file_put_contents('php://stderr', sprintf('"%s" directory is not writable' . PHP_EOL, $path));
            return;
        }
        
        $path = sprintf('%s/%s_%d.png', $path, basename($feature->getFile()), $step->getLine());
        $screenshot = $driver->getScreenshot();
        file_put_contents($path, $screenshot);
        file_put_contents('php://stderr', sprintf('Saving screenshot into %s' . PHP_EOL, $path));
    }
    
    /**
     * @Given /^I fill in the "(?P<selector>[^"]*)" (date time|date|time) field with "(?P<value>[^"]*)"$/
     */
    public function fillInDateTimeField($selector, $value)
    {
        /** @var $field NodeElement **/
        $field = $this->getSession()->getPage()->findField($selector);
        assertNotNull($field, 'Could not find a field with the id|name|placeholder|label "' . $selector . '"');
        
        //Make sure the input is the right type
        if ($field->getAttribute('type') != 'date' && $field->getAttribute('type') != 'time' && $field->getAttribute('type') != 'datetime-local') {
            throw new \InvalidArgumentException('Field with the id|name|placeholder|label "' . $selector . '" is not of the type "date", "time" or "datetime-local"');
        }
        
        //Make sure the element is visible
        assertTrue($field->isVisible(), 'Field is not visible');
        
        switch ($field->getAttribute('type')) {
            case 'date':
                $value = date('Y-m-d', strtotime($value));
                break;
            case 'time':
                $value = date('H:i:s', strtotime($value));
                break;
            case 'datetime-local':
                $value = date('Y-m-d\TH:i:s', strtotime($value));
                break;
        }
        
        
        $this->getSession()->executeScript('(function() {' .
                                                'var field=document.getElementById("' . $field->getAttribute('id') . '");' .
                                                'field.value="' . Convert::raw2js($value) . '";' .
                                                'field.dispatchEvent(new Event("change"));' .
                                            '})();');
    }
    
    /**
     * @Given /^the "(?P<selector>[^"]*)" (date time|date|time) field should(?P<negative>( not |\s*))contain "(?P<value>[^"]*)"$/
     */
    public function dateTimeHasValue($selector, $negative, $value)
    {
        /** @var $field NodeElement **/
        $field = $this->getSession()->getPage()->findField($selector);
        assertNotNull($field, 'Could not find a field with the id|name|placeholder|label "' . $selector . '"');
        
        //Make sure the input is the right type
        if ($field->getAttribute('type') != 'date' && $field->getAttribute('type') != 'time' && $field->getAttribute('type') != 'datetime-local') {
            throw new \InvalidArgumentException('Field with the id|name|placeholder|label "' . $selector . '" is not of the type "date", "time" or "datetime-local"');
        }
        
        switch ($field->getAttribute('type')) {
            case 'date':
                $expected = date('Y-m-d', strtotime($value));
                break;
            case 'time':
                $expected = date('H:i:s', strtotime($value));
                break;
            case 'datetime-local':
                $expected = date('Y-m-d\TH:i:s', strtotime($value));
                break;
        }
        
        
        if (trim($negative)) {
            assertNotEquals($expected, $field->getValue());
        } else {
            assertEquals($expected, $field->getValue());
        }
    }
    
    /**
     * @Given /^I click the "([^"]*)" HTML field menu item$/
     */
    public function iClickTheEditorMenuItem($label)
    {
        $escaper = new \Behat\Mink\Selector\Xpath\Escaper();
        
        //Find the menu item
        $menuItem = $this->getSession()->getPage()->find('xpath', 'descendant-or-self::*[@class and contains(concat(\' \', normalize-space(@class), \' \'), \' mce-menu \')]/div/div[@class and contains(concat(\' \', normalize-space(@class), \' \'), \' mce-menu-item \')]/span[@class and contains(concat(\' \', normalize-space(@class), \' \'), \' mce-text \') and (normalize-space(.)=' . $escaper->escapeLiteral($label) . ')]');
        if ($menuItem == null) {
            throw new \InvalidArgumentException('Could not find the menu item "' . $label . '"');
        }
        
        //Make sure the menu item is visible
        assertTrue($menuItem->isVisible(), 'Menu item "' . $label . '" was not visible');
        
        
        //Click the item
        $menuItem->click();
    }
    
    /**
     * @Given /^I hover over the "([^"]*)" HTML field menu item$/
     */
    public function iHoverTheEditorMenuItem($label)
    {
        $escaper = new \Behat\Mink\Selector\Xpath\Escaper();
        
        //Find the menu item
        /** @var $menuItem NodeElement **/
        $menuItem = $this->getSession()->getPage()->find('xpath', 'descendant-or-self::*[@class and contains(concat(\' \', normalize-space(@class), \' \'), \' mce-menu \')]/div/div[@class and contains(concat(\' \', normalize-space(@class), \' \'), \' mce-menu-item \')]/span[@class and contains(concat(\' \', normalize-space(@class), \' \'), \' mce-text \') and (normalize-space(.)=' . $escaper->escapeLiteral($label) . ')]');
        if ($menuItem == null) {
            throw new \InvalidArgumentException('Could not find the menu item "' . $label . '"');
        }
        
        //Make sure the menu item is visible
        assertTrue($menuItem->isVisible(), 'Menu item "' . $label . '" was not visible');
        
        
        //Hover over the item
        $menuItem->mouseOver();
    }
    
    /**
     * @Given /^I click the "([^"]*)" HTML field popup menu item$/
     */
    public function iClickTheEditorPopupMenuItem($label)
    {
        $escaper = new \Behat\Mink\Selector\Xpath\Escaper();
        
        //Find the menu item
        $menuItem = $this->getSession()->getPage()->find('xpath', 'descendant-or-self::*[@class and contains(concat(\' \', normalize-space(@class), \' \'), \' mce-inline-toolbar \')]/div/div/div/div[@class and contains(concat(\' \', normalize-space(@class), \' \'), \' mce-flow-layout-item \')]/button/span[@class and contains(concat(\' \', normalize-space(@class), \' \'), \' mce-txt \') and (normalize-space(.)=' . $escaper->escapeLiteral($label) . ')]');
        if ($menuItem == null) {
            throw new \InvalidArgumentException('Could not find the popup menu item "' . $label . '"');
        }
        
        //Make sure the menu item is visible
        assertTrue($menuItem->isVisible(), 'Popup menu item "' . $label . '" was not visible');
        
        
        //Click the item
        $menuItem->click();
    }
    
    /**
     * @Given /^I right click in the "([^"]*)" HTML field$/
     */
    public function iRightClickInTheHtmlField($field)
    {
        $htmlField = $this->getHtmlField($field);
        
        $iframe = $this->findParentByClass($htmlField, 'form__field-holder')->find('css', 'iframe');
        assertNotNull($iframe, 'Could not find the WYSIWYG\'s iframe');
        
        
        //Switch to the html editor's iframe
        $this->getSession()->switchToIFrame($iframe->getAttribute('id'));
        
        
        //Right click in the iframe
        $this->getSession()->getPage()->find('css', 'body')->rightClick();
        
        
        //Switch back to the old frame
        $this->getSession()->getDriver()->getWebDriver()->switchTo()->defaultContent();
    }
    
    /**
     * @Given /^I should( not |\s*)see a popup form$/
     */
    public function iShouldSeeAPopupForm($negative)
    {
        $page = $this->getSession()->getPage();
        $dialog = $page->find('css', '.form-builder-modal.show');
        
        if (trim($negative)) {
            if (empty($dialog) || $dialog->isVisible() == true) {
                throw new \Exception('Found a visible popup form');
            }
        } else {
            assertNotNull($dialog, 'Could not find the popup form');
            assertTrue($dialog->isVisible(), 'The popup form was not visible');
        }
    }
    
    /**
     * @Given /^I close the popup form$/
     */
    public function iCloseThePopupForm()
    {
        $page = $this->getSession()->getPage();
        $dialog = $page->find('css', '.form-builder-modal.show');
        
        //Make sure the dialog was found and is visible
        assertNotNull($dialog, 'Could not find the popup form');
        
        
        //Find the close button
        $closeButton = $dialog->find('css', '.modal-header button.close');
        assertNotNull($closeButton, 'Could not find the popup form\'s close button');
        
        
        //Click the close button
        $closeButton->click();
    }
    
    /**
     * @Given /^I view the campaign "([^"]*)"$/
     */
    public function iViewTheCampaign($name)
    {
        $item = $this->getCampaign($name);
        assertNotNull($item, sprintf('Campaign %s not found', $name));
        
        $item->find('css', 'td')->click();
    }
    
    /**
     * @Given /^I should( not? |\s*)see the "([^"]+)" campaign item$/
     */
    public function iSeeTheCampaignItem($negate, $name)
    {
        $item = $this->getCampaignItem($name);
        $shouldSee = !trim($negate);
        
        if ($shouldSee) {
            assertNotNull($item, sprintf('Item "%s" could not be found', $name));
            assertTrue($item->isVisible(), sprintf('Item "%s" is not visible', $name));
        } else if ($item) {
            assertFalse($item->isVisible(), sprintf('Item "%s" is visible', $name));
        } else {
            assertNull($item, sprintf('Item "%s" exists', $name));
        }
    }
    
    /**
     * @Given /^I select the "([^"]+)" campaign item$/
     * @param $name
     */
    public function iSelectTheCampaignItem($name)
    {
        $item = $this->getCampaignItem($name);
        assertNotNull($item, sprintf('Item "%s" could not be found', $name));
        
        $item->click();
    }
    
    /**
     * @Given /^I should see a modal titled "([^"]+)"$/
     * @param string $title
     */
    public function iShouldSeeAModalTitled($title)
    {
        $page = $this->getMainContext()->getSession()->getPage();
        
        $modalTitle = $page->find('css', '[role=dialog] .modal-header > .modal-title');
        
        assertNotNull($modalTitle, 'No modal on the page');
        assertTrue($modalTitle->getText() == $title);
    }
    
    /**
     * @Given /^I select the campaign "([^"]+)" in the add to campaign modal$/
     */
    public function iSelectTheCampaignOption($selector)
    {
        /** @var NodeElement $modal **/
        $modal = $this->getSession()->getPage()->find('css', '.modal-dialog.add-to-campaign-modal');
        assertNotNull($modal, 'Could not find the add to campaign modal');
        
        /** @var NodeElement $select **/
        $select = $modal->find('css', '.add-to-campaign__form select[name="Campaign"]');
        assertNotNull($select, 'Could not find the Available campaigns dropdown');
        
        /** @var NodeElement $option **/
        $option = $select->find('named', ['option', $this->getSession()->getSelectorsHandler()->xpathLiteral($selector)]);
        assertNotNull($selector, 'Could not find the option "' . $selector . '" in the available campaigns dropdown');
        
        //Click the option
        $driverElement = $this->findWebDriverElement($option->getXpath());
        assertNotNull($driverElement, 'Could not find the driver element for the campaign option');
        $driverElement->click();
    }
    
    /**
     * @When /^I fill in the proportion constrained field "(?P<field>(?:[^"]|\\")*)" with "(?P<value>(?:[^"]|\\")*)"$/
     */
    public function fillProportionField($field, $value)
    {
        $field = $this->fixStepArgument($field);
        $value = $this->fixStepArgument($value);
        $escaper = new \Behat\Mink\Selector\Xpath\Escaper();
        $nodes = $this->getSession()->getPage()->findAll('named', ['field', $escaper->escapeLiteral($field)]);
        if ($nodes) {
            /** @var NodeElement $node */
            foreach ($nodes as $node) {
                if ($node->isVisible()) {
                    $id = $node->getAttribute('id');
                    if ($id) {
                        $node->focus();
                        
                        $this->getSession()->getDriver()->executeScript('document.getElementById("' . $id . '").value = "' . Convert::raw2js($value) . '";');
                        
                        $xpath = $node->getXpath();
                        $wdElement = $this->findWebDriverElement($xpath);
                        $wdElement->sendKeys('1');
                        $wdElement->sendKeys(WebDriverKeys::BACKSPACE);
                        
                        $node->blur();
                    } else {
                        $node->setValue($value);
                    }
                    
                    return;
                }
            }
        }
        
        throw new ElementNotFoundException($this->getSession(), 'form field', 'id|name|label|value', $field);
    }
    
    /**
     * @param string $xpath XPath expression
     * @param RemoteWebElement|null $parent Optional parent element
     * @return RemoteWebElement
     */
    protected function findWebDriverElement($xpath, RemoteWebElement $parent = null)
    {
        $finder = WebDriverBy::xpath($xpath);
        return ($parent ? $parent->findElement($finder) : $this->getSession()->getDriver()->getWebDriver()->findElement($finder));
    }
    
    /**
     * Locate an HTML editor field
     * @param string $locator Raw html field identifier
     * @return NodeElement
     */
    protected function getHtmlField($locator)
    {
        $page = $this->getSession()->getPage();
        
        // Searching by name is usually good...
        $element = $page->find('css', 'textarea.htmleditor[name=\'' . $locator . '\']');
        
        if ($element === null) {
            $element = $this->findInputByLabelContent($locator);
        }
        
        assertNotNull($element, sprintf('HTML field "%s" not found', $locator));
        return $element;
    }
    
    /**
     * Finds an input by it's label
     * @param string $locator Label to look for, this should be an escaped xpath literal
     * @return NodeElement
     */
    protected function findInputByLabelContent($locator)
    {
        $page = $this->getSession()->getPage();
        $label = $page->findAll('xpath', sprintf('//label[contains(text(), \'%s\')]', $locator));
        
        if (empty($label)) {
            return null;
        }
        
        assertCount(1, $label, sprintf('Found more than one element containing the phrase "%s".', $locator));
        
        $label = array_shift($label);
        
        $fieldId = $label->getAttribute('for');
        return $page->find('css', '#' . $fieldId);
    }
    
    /**
     * Finds the first visible table by various factors:
     * - table[id]
     * - table[title]
     * - table *[class=title]
     * - fieldset[data-name] table
     * - table caption
     *
     * @return NodeElement
     */
    protected function getTable($selector)
    {
        $selector = $this->getSession()->getSelectorsHandler()->xpathLiteral($selector);
        $page = $this->getSession()->getPage();
        $candidates = $page->findAll('xpath', $this->getSession()->getSelectorsHandler()->selectorToXpath("xpath", ".//table[(./@id = $selector or  contains(./@title, $selector))]"));
        
        // Find tables by a <caption> field
        $candidates += $page->findAll('xpath', "//table//caption[contains(normalize-space(string(.)), $selector)]/ancestor-or-self::table[1]");
        
        // Find tables by a .grid-field__title node
        $candidates += $page->findAll('xpath', "//table//*[@class='grid-field__title-row' and contains(normalize-space(string(.)), $selector)]/ancestor-or-self::table[1]");
        
        // Some tables don't have a visible title, so look for a fieldset with data-name instead
        $candidates += $page->findAll('xpath', "//fieldset[@data-name=$selector]//table");
        
        assertTrue((bool) $candidates, 'Could not find any table elements');
        
        $table = null;
        foreach ($candidates as $candidate) {
            if (!$table && $candidate->isVisible()) {
                $table = $candidate;
            }
        }
        
        assertTrue((bool) $table, 'Found table elements, but none are visible');
        
        return $table;
    }
    
    /**
     * Converts a natural language class description to an actual class name.
     * Respects {@link DataObject::$singular_name} variations.
     * Example: "redirector page" -> "RedirectorPage"
     * @param string
     * @return string Class name
     */
    protected function convertTypeToClass($type)
    {
        $type = trim($type);
        
        // Try direct mapping
        $class = str_replace(' ', '', ucwords($type));
        if (class_exists($class) || !($class == DataObject::class || is_subclass_of($class, DataObject::class))) {
            return $class;
        }
        
        // Fall back to singular names
        foreach (array_values(ClassInfo::subclassesFor(DataObject::class)) as $candidate) {
            if (singleton($candidate)->singular_name() == $type) {
                return $candidate;
            }
        }
        
        throw new \InvalidArgumentException(sprintf(
            'Class "%s" does not exist, or is not a subclass of DataObjet',
            $class
        ));
    }
    
    /**
     * Helper for finding items in the visible campaign view
     * @param string $name Title of item
     * @return NodeElement
     */
    protected function getCampaign($name)
    {
        /** @var DocumentElement $page */
        $page = $this->getMainContext()->getSession()->getPage();
        
        // Find by row
        $row = $page->find('xpath', "//tr[contains(@class, 'grid-field__row')]//td[contains(text(), '{$name}')]/..");
        
        return $row ?: null;
    }
    
    /**
     * Gets a change set item in the detail view
     * @param $name
     * @return NodeElement
     */
    protected function getCampaignItem($name)
    {
        /** @var DocumentElement $page */
        $page = $this->getMainContext()->getSession()->getPage();
        return $page->find('xpath', "//h4[contains(@class, 'list-group-item__heading') and contains(text(), '{$name}')]");
    }
    
    /**
     * Spin function
     * @param function $callback Callback function
     * @param int $wait Timeout in seconds
     */
    protected function spin($callback, $wait = 5)
    {
        for ($i = 0; $i < $wait; $i++) {
            try {
                if ($callback($this)) {
                    return true;
                }
            } catch (Exception $e) {
            }
            
            sleep(1);
        }
        
        $backtrace = debug_backtrace();
        throw new \Exception("Timeout thrown by " . $backtrace[1]['class'] . "::" . $backtrace[1]['function'] . "()\n" . (array_key_exists('file', $backtrace[1]) ? $backtrace[1]['file'] . ", line " . $backtrace[1]['line'] : 'Unknown File'));
    }
    
    /**
     * Get Mink session from MinkContext
     * @return Session
     */
    protected function getSession($name = null)
    {
        return $this->getMainContext()->getSession($name);
    }
    
    /**
     * Sets the element with the given selector's id
     * @param string $selector CSS Selector
     * @param string $id ID to give the element
     */
    protected function setElementId($selector, $id)
    {
        $function = '(function() {' .
                        'var iframe = document.querySelector("' . Convert::raw2js($selector) . '");' .
                        'iframe.setAttribute("id", "' . Convert::raw2js($id) . '");' .
                    '})()';
        
        try {
            $this->getSession()->executeScript($function);
        } catch (\Exception $e) {
            throw new \Exception("Element $selector was NOT found." . PHP_EOL . $e->getMessage());
        }
    }

    /**
     * Returns the closest parent element having a specific class attribute.
     * @param NodeElement $el
     * @param String $class
     * @return Element|null
     */
    protected function findParentByClass(NodeElement $el, $class)
    {
        $container = $el->getParent();
        while ($container && $container->getTagName() != 'body') {
            if ($container->hasClass($class)) {
                return $container;
            }
            
            $container = $container->getParent();
        }

        return null;
    }
    
    /**
     * Wait for alert to appear, and return handle
     * @return Facebook\WebDriver\WebDriverAlert
     */
    protected function getExpectedAlert()
    {
        $session = $this->getSession()->getDriver()->getWebDriver();
        $session->wait()->until(
            WebDriverExpectedCondition::alertIsPresent(),
            "Alert is expected"
        );
        
        return $session->switchTo()->alert();
    }
    
    /**
     * Drags one element to another
     * @param NodeElement $src Source Element
     * @param NodeElement $dest Destination Element
     *
     * @TODO Remove when silverstripe/MinkFacebookWebDriver#1 is merged and released
     */
    protected function drag(NodeElement $src, NodeElement $dest)
    {
        /** @var $driver \SilverStripe\MinkFacebookWebDriver\FacebookWebDriver **/
        $driver = $this->getSession()->getDriver();
        $driver->getWebDriver()
                            ->action()
                            ->dragAndDrop($driver->getWebDriver()->findElement(WebDriverBy::xpath($src->getXpath())), $driver->getWebDriver()->findElement(WebDriverBy::xpath($dest->getXpath())))
                            ->perform();
    }
    
    
    /**
     * Returns fixed step argument (with \\" replaced back to ")
     * @param string $argument
     * @return string
     */
    protected function fixStepArgument($argument)
    {
        return str_replace('\\"', '"', $argument);
    }
}
