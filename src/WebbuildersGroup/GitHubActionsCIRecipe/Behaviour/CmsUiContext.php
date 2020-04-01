<?php
namespace WebbuildersGroup\GitHubActionsCIRecipe\Behaviour;

use Behat\Behat\Context\BehatContext,
    Behat\Behat\Context\Step,
    Behat\Behat\Event\StepEvent,
    Behat\Behat\Event\ScenarioEvent,
    Behat\Mink\Element\NodeElement,
    Behat\Mink\Session,
    WebDriver\Exception\UnexpectedAlertOpen,
    WebDriver\Key;

// PHPUnit
require_once 'PHPUnit/Autoload.php';
require_once 'PHPUnit/Framework/Assert/Functions.php';

class CmsUiContext extends BehatContext {
    protected $context;
    
    /**
     * Initializes context.
     * Every scenario gets it's own context object.
     * @param array parameters context parameters (set them up through behat.yml)
     */
    public function __construct(array $parameters) {
        // Initialize your context here
        $this->context = $parameters;
    }
    
    /**
     * Wait until CMS loading overlay isn't present.
     * This is an addition to the "ajax steps" logic in
     * SilverStripe\BehatExtension\Context\BasicContext
     * which also waits for any ajax requests to finish before continuing.
     *
     * The check also applies in when not in the CMS, which is a structural issue:
     * Every step could cause the CMS to be loaded, and we don't know if we're in the
     * CMS UI until we run a check.
     *
     * Excluding scenarios with @modal tag is required,
     * because modal dialogs stop any JS interaction
     *
     * @AfterStep ~@modal
     */
    public function handleCmsLoadingAfterStep(StepEvent $event) {
        try {
            $timeoutMs=$this->getMainContext()->getAjaxTimeout();
            $this->getSession()->wait($timeoutMs,
                "document.getElementsByClassName('cms-content-loading-overlay').length == 0"
            );
            
            //Disable multiuser-editing
            $this->getSession()->executeScript('if(typeof jQuery != "undefined" && typeof jQuery.entwine != "undefined") {jQuery.entwine("multiUserEditing", function($) {$(".cms-edit-form").entwine({onmatch: function() {}});});}');
        }catch(\WebDriver\Exception\NoSuchWindow $e) {
            //Supress window already closed exceptions
        }
    }
    
    /**
     * Hook to reset the preview display
     * @AfterScenario
     */
    public function resetPreviewDisplay(ScenarioEvent $event) {
        try {
            @$this->getSession()->executeScript('if(window.top && typeof window.top.jQuery!="undefined" && typeof window.top.jQuery.entwine!="undefined" && window.top.jQuery(".cms-preview").length>0 && window.top.jQuery(".cms-preview").entwine("ss.preview").changeMode!="undefined") {'.
                                                    'window.top.jQuery(".cms-preview").entwine("ss.preview").changeMode("'.\Config::inst()->get('UserPreviewPreference', 'DefaultMode').'");'.
                                                '}');
        }catch(\WebDriver\Exception\UnknownError $e) {}
    }
    
    /**
     * @When /^I click the "([^"]*)" element$/
     */
    public function iClickTheElement($selector) {
        $page=$this->getSession()->getPage();
        $element=$page->find('css', $selector);
        
        assertNotNull($element, sprintf('element with the selector "%s" not found', $selector));
        
        $element->click();
    }
    
    /**
     * Needs to be in single command to avoid "unexpected alert open" errors in Selenium.
     *
     * @Given /^I click the "([^"]*)" element, confirming the dialog$/
     */
    public function iClickTheElementConfirm($selector) {
        $this->iClickTheElement($selector);
        
        $this->spin(function() {
            try {
				$this->getSession()->getDriver()->getWebDriverSession()->accept_alert();
                
                return true;
			}catch(\WebDriver\Exception $e) {
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
    public function iPressButtonConfirm($button) {
        $this->getSession()->getPage()->pressButton($button);
        
        $this->spin(function() {
            try {
                $this->getSession()->getDriver()->getWebDriverSession()->accept_alert();
                
                return true;
            }catch(\WebDriver\Exception $e) {
                // no-op, alert might not be present
            }
            
            return false;
        });
        
        $this->handleAjaxTimeout();
    }
    
    /**
     * @When /^I click the on the "([^"]*)" link$/
     */
    public function iClickOnTheLink($text) {
        $page=$this->getSession()->getPage();
        
        //Find the link
        $escaper=new \Behat\Mink\Selector\Xpath\Escaper();
        $element=$page->find('xpath', '//a[contains(., '. $escaper->escapeLiteral($text) .')]');
        
        if($element===null) {
            throw new \InvalidArgumentException(sprintf('Cannot find a link containing "%s"', $text));
        }
        
        $element->click();
    }
    
    /**
     * @Then /^I should( not |\s*)see a field with the label "([^"]*)"$/
     */
    public function iShouldSeeAFieldWithLabel($negative, $label, $selector=null) {
        $page=$this->getSession()->getPage();
        
        //Find the field
        $escaper=new \Behat\Mink\Selector\Xpath\Escaper();
        $element=$page->find('xpath', 'descendant-or-self::*[@class and (contains(concat(\' \', normalize-space(@class), \' \'), \' field \') or contains(concat(\' \', normalize-space(@class), \' \'), \' fieldgroup-field \'))]/label[contains(., '.$escaper->escapeLiteral($label).')]');
        
        if(trim($negative)) {
            if($element!=null && $element->isVisible()) {
                throw new \Exception(sprintf('field with the label "%s" was present or was visible', $label));
            }
        }else {
            assertNotNull($element, sprintf('field with the label "%s" not found', $label));
            
            assertTrue($element->isVisible(), sprintf('field with the label "%s" was not visible', $label));
        }
    }
    
    /**
     * @Then /^I should( not |\s*)see a field with the label "([^"]*)" in the "([^"]*)" element$/
     */
    public function iShouldSeeAFieldWithLabelInElement($negative, $label, $selector=null) {
        $page=$this->getSession()->getPage();
        
        //Find the Selector
        $element=$page->find('css', $selector);
        assertNotNull($element, sprintf('element with the selector "%s" not found', $selector));
        
        //Find the field
        $escaper=new \Behat\Mink\Selector\Xpath\Escaper();
        $element=$element->find('xpath', 'descendant-or-self::*[@class and (contains(concat(\' \', normalize-space(@class), \' \'), \' field \') or contains(concat(\' \', normalize-space(@class), \' \'), \' fieldgroup-field \'))]/label[contains(., '.$escaper->escapeLiteral($label).')]');
        
        if(trim($negative)) {
            if($element!=null && $element->isVisible()) {
                throw new \Exception(sprintf('field with the label "%s" was present or was visible', $label));
            }
        }else {
            assertNotNull($element, sprintf('field with the label "%s" not found', $label));
            
            assertTrue($element->isVisible(), sprintf('field with the label "%s" was not visible', $label));
        }
    }
    
    /**
     * @Then /^I should( not |\s*)see a grid field with the label "([^"]*)"$/
     */
    public function iShouldSeeAGridWithLabel($negative, $label) {
        $page=$this->getSession()->getPage();
        
        //Find the field
        $escaper=new \Behat\Mink\Selector\Xpath\Escaper();
        $element=$page->find('xpath', 'descendant-or-self::*[@class and (contains(concat(\' \', normalize-space(@class), \' \'), \' ss-gridfield-table \'))]/*/tr[@class and (contains(concat(\' \', normalize-space(@class), \' \'), \' title \'))]/th/*[contains(., '.$escaper->escapeLiteral($label).')]');
        
        if(trim($negative)) {
            if($element!=null && $element->isVisible()) {
                throw new \Exception(sprintf('Grid field with the label "%s" was present or was visible', $label));
            }
        }else {
            assertNotNull($element, sprintf('Grid field with the label "%s" not found', $label));
            
            assertTrue($element->isVisible(), sprintf('Grid field with the label "%s" was not visible', $label));
        }
    }
    
    /**
     * @Then /^I fill in the "([^"]*)" auto complete field with "([^"]*)", and select the suggestion option "([^"]*)"$/
     */
    public function selectAutosuggestionOption($field, $value, $option) {
        $session=$this->getSession();
        $driver=$session->getDriver();
        $element=$session->getPage()->findField($field);
        
        assertNotNull($element, 'Could not find a field with the label, placeholder, id or name of "%s"');
        
        //Get the XPath Selector for the Element
        $xpath=$element->getXpath();
        
        //Set the value, we can't use the normal setValue because of the TAB at the end see minkphp/MinkSelenium2Driver#244 present in selenium2 driver 1.2.*
        $driverElement=$driver->getWebDriverSession()->element('xpath', $xpath);
        $existingValueLength=strlen($driverElement->attribute('value'));
        $driverValue=str_repeat(Key::BACKSPACE . Key::DELETE, $existingValueLength) . $value;
        $driverElement->postValue(array('value'=>array($driverValue)));
        
        
        //Trigger change event
        $session->executeScript('(function() {'.
                                    'var element=document.getElementById(\''.\Convert::raw2js($element->getAttribute('id')).'\');'.
                                    'element.dispatchEvent(new Event(\'change\', {\'bubbles\': true}));'.
                                '})()');
        
        
        //Trigger Key Down
        /*$driver->keyDown($xpath, substr($value, -1));
        $driver->keyUp($xpath, substr($value, -1));*/
        
        //Wait for the delay on jQuery UI's Autocomplete
        $session->wait(500);
        
        //Wait for ajax
        $this->handleAjaxTimeout();
        
        //Find Result
        $escaper=new \Behat\Mink\Selector\Xpath\Escaper();
        $element=$session->getPage()->find('xpath', 'descendant-or-self::*[@class and contains(concat(\' \', normalize-space(@class), \' \'), \' ui-autocomplete \')]/li/a[contains(., '.$escaper->escapeLiteral($option).')]');
        
        if($element==null) {
            throw new \InvalidArgumentException(sprintf('Cannot find text: "%s"', $option));
        }
        
        $element->click();
        
        
        //Blur the field
        $driver->blur($xpath);
    }
    
    /**
     * @Then /^I wait for the loading to finish$/
     */
    public function handleAjaxTimeout() {
        $timeoutMs=$this->getMainContext()->getAjaxTimeout();

        // Wait for an ajax request to complete, but only for a maximum of 5 seconds to avoid deadlocks
        $this->getSession()->wait($timeoutMs, '(typeof(jQuery)=="undefined" || (0 === jQuery.active && 0 === jQuery(\':animated\').length))');

        // wait additional 100ms to allow DOM to update
        $this->getSession()->wait(100);
    }
    
    /**
     * @Then /^I switch to the cms popup$/
     */
    public function iSwitchToThePopup() {
        //Switch to the iframe Window
        $frame=$this->getSession()->getPage()->find('css', '.ss-ui-dialog:last-child iframe');
        
        $frameID=$frame->getAttribute('id');
        if(empty($frameID)) {
            $frameID='frame-'.sha1(uniqid(time()));
            $this->setElementId('.ss-ui-dialog:last-child iframe', $frameID);
        }
        
        //Switch to the frame based on it's index
        $this->getSession()->switchToIFrame($frameID);
    }
    
    /**
     * @Then /^I switch back from the popup$/
     */
    public function iSwitchBackFromThePopup() {
        $this->getSession()->switchToIFrame(null);
    }
    
    /**
     * @Then /^I should( not |\s*)see an item edit form$/
     */
    public function iShouldSeeItemEditForm($negative) {
        $page=$this->getSession()->getPage();
        $form=$page->find('css', '#Form_ItemEditForm');
        
        if(trim($negative)) {
            assertNull($form, 'I should not see an item edit form');
        }else {
            assertNotNull($form, 'I should see an item edit form');
        }
    }
    
    /**
     * @Then /^I should( not |\s*)see a cms popup$/
     */
    public function iShouldSeeAPopup($negative) {
        $page=$this->getSession()->getPage();
        $dialog=$page->find('css', '.ss-ui-dialog:last-child');
        
        if(trim($negative)) {
            if(empty($dialog) || $dialog->isVisible()==true) {
                throw new \Exception('Found a visible CMS popup');
            }
        }else {
            assertNotNull($dialog, 'Could not find the cms popup');
            assertTrue($dialog->isVisible(), 'The cms popup was not visible');
        }
    }
    
    /**
     * @Then /^I navigate to the cms popup's page$/
     */
    public function iNavigateToPopupURL() {
        //Find the iframe's url
        $iframe=$this->getSession()->getPage()->findAll('css', '.ss-ui-dialog iframe');
        $iframe=array_pop($iframe);
        
        return new Step\Then('I go to "/'.$iframe->getAttribute('src').'"');
    }
    
    /**
     * @Then /^I close the cms popup$/
     */
    public function iCloseThePopup() {
        //Find the dialog
        $dialog=$this->getSession()->getPage()->find('css', '.ss-ui-dialog:last-child');
        if(empty($dialog)) {
            throw new \Exception('Could not find the CMS popup');
        }
        
        $button=$dialog->getParent()->find('css', '.ui-dialog-titlebar .ui-dialog-titlebar-close');
        if(empty($button)) {
            throw new \Exception('Could not find the CMS popup close button');
        }
        
        $button->click();
    }
    
    /**
     * @Then /^I wait for the cms popup to load$/
     */
    public function iWaitForThePopupToLoad() {
        $timeoutMs=$this->getMainContext()->getAjaxTimeout();
        $this->getSession()->wait($timeoutMs, "document.getElementsByClassName('ui-dialog loading').length == 0");
    }
    
    /**
     * @Then /^the "(?P<selector>[^"]*)" table should(?P<negative>( not |\s*))contain "(?P<text>[^"]*)" in (?P<rowLocation>(any|(the (new|(((\d+)(st|nd|rd|th))|last|first) editable)))) row in a field in the "(?P<column>[^"]*)" column$/
     */
    public function theTableFieldShouldContain($selector, $negative, $text, $rowLocation, $column) {
        $escaper=new \Behat\Mink\Selector\Xpath\Escaper();
        $table=$this->getTable($selector);
        
        $columnElement=$table->find('xpath', '//thead/tr/th[contains(concat(\' \', normalize-space(@class), \' \'), '.$escaper->escapeLiteral(' col-'.$column.' ').') or contains(concat(\' \', normalize-space(@class), \' \'), '.$escaper->escapeLiteral(' col-action_SetOrder'.$column.' ').')]');
        if($columnElement==null) {
            $columnElement=$table->find('xpath', '//thead/tr/th/*[contains(.,'.$escaper->escapeLiteral($column).')]/ancestor::th');
            if($columnElement==null) {
                throw new \Exception('Could not find the "'.$column.'" column in the "'.$selector.'" grid field');
            }
            
            //Find the class attribute of the column
            $columnClass=array();
            if(preg_match('/((\s+)|^)col-((action_SetOrder)?)(.*)((\s+)|$)/', $columnElement->getAttribute('class'), $columnClass)==false) {
                throw new \Exception('Could not find the column name class');
            }
            
            $columnClass=$columnClass[5];
        }else {
            $columnClass=$column;
        }
        
        $element=null;
        if($rowLocation=='any') {
            $colElements=$table->findAll('css', 'tbody tr td.col-'.$columnClass);
            if(empty($colElements) || count($colElements)==0) {
                throw new \Exception(sprintf('The column `%s` was not found in `%s` table', $column, $selector));
            }
            
            foreach($colElements as $colElement) {
                $element=$colElement->find('xpath', '//input[contains(@value, '.$this->getSession()->getSelectorsHandler()->xpathLiteral($text).')]');
                if($element!=null) {
                    break;
                }
            }
        }else {
            switch($rowLocation) {
                case 'the first editable':$rowSelector=':first-child';break;
                case 'the last editable':$rowSelector=':last-child';break;
                case 'the new editable':$rowSelector='.ss-gridfield-inline-new:last-child';break;
                default: {
                    $pos=intval(str_replace(array('st', 'nd', 'rd', 'the ', 'th', ' editable'), '', $rowLocation));
                    $rowSelector=':nth-child('.$pos.')';
                    break;
                }
            }
            
            $colElement=$table->find('css', 'tbody tr'.$rowSelector.' td.col-'.$columnClass);
            if(empty($colElement)) {
                throw new \Exception(sprintf('The column `%s` was not found in `%s` table', $column, $selector));
            }
            
            $element=$colElement->find('xpath', '//input[contains(@value, '.$this->getSession()->getSelectorsHandler()->xpathLiteral($text).')]');
        }
        
        if(trim($negative)) {
            assertNull($element, sprintf('Text `%s` found in column `%s` of `%s` table', $text, $column, $selector));
        }else {
            assertNotNull($element, sprintf('Text `%s` not found in column `%s` of `%s` table', $text, $column, $selector));
        }
    }
    
    /**
     * @Then /^the "(?P<selector>[^"]*)" table should(?P<negative>( not |\s*))have the field checked in (?P<rowLocation>(any|(the (new|(((\d+)(st|nd|rd|th))|last|first) editable)))) row in the "(?P<column>[^"]*)" column$/
     */
    public function theTableFieldShouldBeChecked($selector, $negative, $rowLocation, $column) {
        $escaper=new \Behat\Mink\Selector\Xpath\Escaper();
        $table=$this->getTable($selector);
        
        $columnElement=$table->find('xpath', '//thead/tr/th[contains(concat(\' \', normalize-space(@class), \' \'), '.$escaper->escapeLiteral(' col-'.$column.' ').') or contains(concat(\' \', normalize-space(@class), \' \'), '.$escaper->escapeLiteral(' col-action_SetOrder'.$column.' ').')]');
        if($columnElement==null) {
            $columnElement=$table->find('xpath', '//thead/tr/th/*[contains(.,'.$escaper->escapeLiteral($column).')]/ancestor::th');
            if($columnElement==null) {
                throw new \Exception('Could not find the "'.$column.'" column in the "'.$selector.'" grid field');
            }
            
            //Find the class attribute of the column
            $columnClass=array();
            if(preg_match('/((\s+)|^)col-((action_SetOrder)?)(.*)((\s+)|$)/', $columnElement->getAttribute('class'), $columnClass)==false) {
                throw new \Exception('Could not find the column name class');
            }
            
            $columnClass=$columnClass[5];
        }else {
            $columnClass=$column;
        }
        
        $element=null;
        if($rowLocation=='any') {
            $colElements=$table->findAll('css', 'tbody tr td.col-'.$columnClass);
            if(empty($colElements) || count($colElements)==0) {
                throw new \Exception(sprintf('The column `%s` was not found in `%s` table', $column, $selector));
            }
            
            foreach($colElements as $colElement) {
                $element=$colElement->find('css', 'input[type=checkbox]:checked');
                if($element!=null) {
                    break;
                }
            }
        }else {
            switch($rowLocation) {
                case 'the first editable':$rowSelector=':first-child';break;
                case 'the last editable':$rowSelector=':last-child';break;
                case 'the new editable':$rowSelector='.ss-gridfield-inline-new:last-child';break;
                default: {
                    $pos=intval(str_replace(array('st', 'nd', 'rd', 'the ', 'th', ' editable'), '', $rowLocation));
                    $rowSelector=':nth-child('.$pos.')';
                    break;
                }
            }
            
            $colElement=$table->find('css', 'tbody tr'.$rowSelector.' td.col-'.$columnClass);
            if(empty($colElement)) {
                throw new \Exception(sprintf('The column `%s` was not found in `%s` table', $column, $selector));
            }
            
            $element=$colElement->find('css', 'input[type=checkbox]:checked');
        }
        
        if(trim($negative)) {
            assertNull($element, sprintf('The field was checked in the column `%s` of `%s` table', $column, $selector));
        }else {
            assertNotNull($element, sprintf('he field was not checked in the column `%s` of `%s` table', $column, $selector));
        }
    }
    
    /**
     * @Then /^the "(?P<selector>[^"]*)" table should(?P<negative>( not |\s*))have "(?P<optionValue>[^"]*)" selected in (?P<rowLocation>(any|(the (new|(((\d+)(st|nd|rd|th))|last|first) editable)))) row in a dropdown in the "(?P<column>[^"]*)" column$/
     */
    public function theTableSelectShouldBe($selector, $negative, $optionValue, $rowLocation, $column) {
        $escaper=new \Behat\Mink\Selector\Xpath\Escaper();
        $table=$this->getTable($selector);
        
        $columnElement=$table->find('xpath', '//thead/tr/th[contains(concat(\' \', normalize-space(@class), \' \'), '.$escaper->escapeLiteral(' col-'.$column.' ').') or contains(concat(\' \', normalize-space(@class), \' \'), '.$escaper->escapeLiteral(' col-action_SetOrder'.$column.' ').')]');
        if($columnElement==null) {
            $columnElement=$table->find('xpath', '//thead/tr/th/*[contains(.,'.$escaper->escapeLiteral($column).')]/ancestor::th');
            if($columnElement==null) {
                throw new \Exception('Could not find the "'.$column.'" column in the "'.$selector.'" grid field');
            }
            
            //Find the class attribute of the column
            $columnClass=array();
            if(preg_match('/((\s+)|^)col-((action_SetOrder)?)(.*)((\s+)|$)/', $columnElement->getAttribute('class'), $columnClass)==false) {
                throw new \Exception('Could not find the column name class');
            }
            
            $columnClass=$columnClass[5];
        }else {
            $columnClass=$column;
        }
        
        $element=null;
        if($rowLocation=='any') {
            $colElements=$table->findAll('css', 'tbody tr td.col-'.$columnClass);
            if(empty($colElements) || count($colElements)==0) {
                throw new \Exception(sprintf('The column `%s` was not found in `%s` table', $column, $selector));
            }
            
            $selectField=$colElement->find('css', 'select');
            if($selectField==null) {
                throw new \Exception(sprintf('Could not find a dropdown in the `%s` column in the `%s` table', $column, $selector));
            }
        }else {
            switch($rowLocation) {
                case 'the first editable':$rowSelector=':first-child';break;
                case 'the last editable':$rowSelector=':last-child';break;
                case 'the new editable':$rowSelector='.ss-gridfield-inline-new:last-child';break;
                default: {
                    $pos=intval(str_replace(array('st', 'nd', 'rd', 'the ', 'th', ' editable'), '', $rowLocation));
                    $rowSelector=':nth-child('.$pos.')';
                    break;
                }
            }
            
            $colElement=$table->find('css', 'tbody tr'.$rowSelector.' td.col-'.$columnClass);
            if(empty($colElement)) {
                throw new \Exception(sprintf('The column `%s` was not found in `%s` table', $column, $selector));
            }
            
            $selectField=$colElement->find('css', 'select');
            if($selectField==null) {
                throw new \Exception(sprintf('Could not find a dropdown in the `%s` column in the `%s` table', $column, $selector));
            }
        }
        
        
        $option=$selectField->find('named', array('option', $escaper->escapeLiteral($optionValue)));
        if($option==null) {
            throw new \Exception(sprintf('Could not find the option "%s" with value|text in the "%s" select', $optionValue, $select));
        }
        
        if(trim($negative)) {
            if($option->isSelected()) {
                throw new \Exception(sprintf('The option "%s" was not selected in the "%s" select', $optionValue, $select));
            }
        }else {
            if(!$option->isSelected()) {
                throw new \Exception(sprintf('The option "%s" was not selected in the "%s" select', $optionValue, $select));
            }
        }
    }
    
    /**
     * @Given /^the row containing "([^"]*)" in the "([^"]*)" table should( not |\s*)have the class "([^"]*)" on (every|"([^"]*)") column$/
     */
    public function theRowContainingHaveClass($text, $selector, $negative, $class, $columnName) {
        $table=$this->getTable($selector);
        $negative=trim($negative);
        
        $element=$table->find('named', array('content', "'$text'"));
        if($element!=null) {
            //Get the parent row
            $element=$element->getParent();
            
            //If the column name is every then we look for all columns but the buttons column
            if($columnName=='every') {
                $columns=$element->findAll('css', 'td:not(.col-buttons)');
                if(count($columns)==0) {
                    throw new \Exception(sprintf('Row containing `%s` in the `%s` table does not contain any valid columns', $text, $selector));
                }
                
                foreach($columns as $colElement) {
                    if($negative && $colElement->hasClass($class)) {
                        throw new \Exception(sprintf('A column in the row containing `%s` in the table `%s` has the class `%s`', $text, $selector, $class));
                    }else if(!$negative && !$colElement->hasClass($class)) {
                        throw new \Exception(sprintf('A column in the row containing `%s` in the table `%s` does not have the class `%s`', $text, $selector, $class));
                    }
                }
            }else {
                $colElement=$element->find('css', 'td.col-'.$columnName.'.'.$class);
                if($negative) {
                    assertNull($colElement, sprintf('Column with the name `%s` has the class `%s` in `%s` table when looking for the row containing `%s`', $columnName, $class, $selector, $text));
                }else {
                    assertNotNull($colElement, sprintf('Column with the name `%s` does not have the class `%s` in `%s` table when looking for the row containing `%s`', $columnName, $class, $selector, $text));
                }
            }
        }else {
            throw new \Exception(sprintf('Column containing `%s` not found in `%s` table', $text, $selector));
        }
    }
    
    /**
     * @Then /^the "([^"]*)" "([^"]*)" should( not |\s*)be published$/
     */
    public function theContentShouldBePublished($type, $id, $negative) {
        $class=$this->convertTypeToClass($type);
        $obj=$this->getMainContext()->getSubcontext('FixtureContext')->getFixtureFactory()->get($class, $id);
        if(!$obj) {
            throw new \InvalidArgumentException(sprintf('Can not find record "%s" with identifier "%s"', $type, $id));
        }
        
        if(!$obj->hasMethod('isPublished')) {
            throw new \InvalidArgumentException(sprintf('Record "%s" with identifier "%s" does not have an isPublished method', $type, $id));
        }
        
        if(trim($negative)) {
            assertFalse($obj->isPublished(), sprintf('Record "%s" with identifier "%s" was published', $type, $id));
        }else {
            assertTrue($obj->isPublished(), sprintf('Record "%s" with identifier "%s" was not published', $type, $id));
        }
    }
    
    /**
     * @Then /^the hidden "([^"]*)" field should( not |\s*)contain "([^"]*)"$/
     */
    public function theHiddenFieldShouldContain($selector, $negative, $expected, $mode='contain') {
        $escaper=new \Behat\Mink\Selector\Xpath\Escaper();
        $escapedSelector=$escaper->escapeLiteral($selector);
        $field=$this->getSession()->getPage()->find('xpath', $this->getSession()->getSelectorsHandler()->selectorToXpath('css', 'input[name='.$escapedSelector.'][type=hidden]'));
        
        if($field==null) {
            $field=$this->getSession()->getPage()->find('xpath', $this->getSession()->getSelectorsHandler()->selectorToXpath('css', 'input[id='.$escapedSelector.'][type=hidden]'));
        }
        
        assertNotNull($field, 'Could not find a field with the name|id '.$selector);
        
        if(trim($negative)) {
            if($mode=='be empty') {
                $value=$field->getValue();
                if(empty($value)) {
                    throw new \Exception(sprintf('Value of the field "%s" was empty', $selector));
                }
            }else if(strpos($field->getValue(), $expected)!==false) {
                throw new \Exception(sprintf('Value of the field "%s" contains the expected "%s"', $selector, $expected));
            }
        }else {
            if($mode=='be empty') {
                $value=$field->getValue();
                if(!empty($value)) {
                    throw new \Exception(sprintf('Value of the field "%s" was not empty', $selector));
                }
            }else if(strpos($field->getValue(), $expected)===false) {
                throw new \Exception(sprintf('Value of the field "%s" does not contain the expected "%s"', $selector, $expected));
            }
        }
    }
    
    /**
     * @Then /^the hidden "([^"]*)" field should( not |\s*)be empty$/
     */
    public function theHiddenFieldShouldEmpty($selector, $negative) {
        $this->theHiddenFieldShouldContain($selector, $negative, '', 'be empty');
    }
    
    /**
     * @Then /^I fill in the hidden field "([^"]*)" with "([^"]*)"$/
     */
    public function fillInHiddenField($selector, $value) {
        $escaper=new \Behat\Mink\Selector\Xpath\Escaper();
        $escapedSelector=$escaper->escapeLiteral($selector);
        $field=$this->getSession()->getPage()->find('xpath', $this->getSession()->getSelectorsHandler()->selectorToXpath('css', 'input[name='.$escapedSelector.'][type=hidden]'));
        
        if($field==null) {
            $field=$this->getSession()->getPage()->find('xpath', $this->getSession()->getSelectorsHandler()->selectorToXpath('css', 'input[id='.$escapedSelector.'][type=hidden]'));
        }
        
        assertNotNull($field, 'Could not find a field with the name|id '.$selector);
        
        $this->getSession()->executeScript(
                                        'var element=document.getElementById(\''.\Convert::raw2js($field->getAttribute('id')).'\');'.
                                        'element.value=\''.\Convert::raw2js($value).'\';'.
                                        'if("createEvent" in document) {'.
                                            'var evt=document.createEvent("HTMLEvents");'.
                                            'evt.initEvent("change", false, true);'.
                                            'element.dispatchEvent(evt);'.
                                        '}else {'.
                                            'element.fireEvent("onchange");'.
                                        '}'
                                    );
    }
    
    /**
     * @Then /^I should( not |\s*)see "([^"]*)" attached to "([^"]*)"$/
     */
    public function iShouldNotSeeFileAttached($negative, $filename, $selector) {
        //Find the field
        $escaper=new \Behat\Mink\Selector\Xpath\Escaper();
        $element=$this->getSession()->getPage()->find('xpath', 'descendant-or-self::*[@class and (contains(concat(\' \', normalize-space(@class), \' \'), \' field \') or contains(concat(\' \', normalize-space(@class), \' \'), \' fieldgroup-field \'))]/label[contains(., '.$escaper->escapeLiteral($selector).')]');
        
        //Verify we found the field
        assertNotNull($element, sprintf('Field with the label "%s" was not found', $selector));
        
        $element=$element->getParent();
        
        //Find the file name's label
        $fileItem=$element->find('xpath', '//li[@class and (contains(concat(\' \', normalize-space(@class), \' \'), \' ss-uploadfield-item \'))]/div/label[@class and (contains(concat(\' \', normalize-space(@class), \' \'), \' ss-uploadfield-item-name \'))]/span[contains(., '.$escaper->escapeLiteral($filename).')]');
        
        if(trim($negative)) {
            assertNull($fileItem, sprintf('The file "%s" was attached to the "%s" field', $filename, $selector));
        }else {
            assertNotNull($fileItem, sprintf('The file "%s" was not attached to the "%s" field', $filename, $selector));
        }
    }
    
    /**
     * @Then /^I select the (first|last|((\d+)(st|nd|rd|th))) option from "(?P<select>[^"]+)"$/
     */
    public function selectTheOrderedOption($position, $select) {
        $field=$this->getSession()->getPage()->findField($select);
        
        // If field is visible then select it as per normal
        if($field) {
            //Find the appropriate option
            if($position=='first') {
                $option=$field->find('css', 'option:first-child');
            }else if($position=='last') {
                $option=$field->find('css', 'option:last-child');
            }else {
                $option=$field->find('css', 'option:nth-child('.intval($position).')');
            }
            
            if(!$option) {
                throw new \InvalidArgumentException(sprintf('Could not find the %s option in the "%s" select', $position, $select));
            }
            
            if($field->isVisible()) {
                if(!$option->selected()) {
                    $option->click();
                }
            }else {
                //Build and run the script
                $script='(function($) {'.
                            '$("#'.$field->getAttribute('ID').'")'.
                                '.val('.json_encode($option->getAttribute('value')).')'.
                                '.change()'.
                                '.trigger(\'liszt:updated\')'.
                                '.trigger(\'chosen:updated\');'.
                        '})(jQuery);';
                
                $this->getSession()->getDriver()->executeScript($script);
            }
        }else {
            throw new \InvalidArgumentException(sprintf('Could not find the select "%s" with the specified id|name|label|value', $select));
        }
    }
    
    /**
     * @Given /^the event containing "([^"]*)" in the "([^"]*)" calendar should( not |\s*)have the class "([^"]*)"$/
     */
    public function theEventContainingHaveClass($text, $selector, $negative, $class) {
        $selector=$this->getSession()->getSelectorsHandler()->xpathLiteral($selector);
        $page=$this->getSession()->getPage();
        $candidates=$page->findAll('xpath', "//fieldset[@data-name=$selector]//div[contains(@class, 'fc-day-grid')]");
        
        assertTrue((bool)$candidates, 'Could not find any calendar elements');
        
        $calendar=null;
        foreach($candidates as $candidate) {
            if(!$calendar && $candidate->isVisible()) {
                $calendar=$candidate;
            }
        }
        
        assertTrue((bool)$calendar, 'Found calendar elements, but none are visible');
        
        $negative=trim($negative);
        
        $element=$calendar->find('named', array('content', "'$text'"));
        if($element!=null) {
            //Get the event's link which has the classes on it
            $element=$element->getParent()->getParent();
            
            if($negative) {
                assertFalse($element->hasClass($class), sprintf('Event has the class `%s` in `%s` calendar when looking for an event containing `%s`', $class, $selector, $text));
            }else {
                assertTrue($element->hasClass($class), sprintf('Event does not have the class `%s` in `%s` calendar when looking for an event containing `%s`', $class, $selector, $text));
            }
        }else {
            throw new \Exception(sprintf('Event containing `%s` not found in `%s` calendar', $text, $selector));
        }
    }
    
    /**
     * @Then /^the "([^"]*)" option is selected in the "([^"]*)" dropdown$/
     */
    public function optionFromDropdownIsSelected($optionValue, $select) {
        $escaper=new \Behat\Mink\Selector\Xpath\Escaper();
        
        $selectField=$this->getSession()->getPage()->find('named', array('select', $this->getSession()->getSelectorsHandler()->xpathLiteral($select)));
        if($selectField==null) {
            throw new \Exception(sprintf('The select "%s" was not found', $select));
        }
        
        $option=$selectField->find('named', array('option', $escaper->escapeLiteral($optionValue)));
        if($option==null) {
            throw new \Exception(sprintf('Could not find the option "%s" with id|name|label|value in the "%s" select', $optionValue, $select));
        }
        
        if(!$option->isSelected()) {
            throw new \Exception(sprintf('The option "%s" was not selected in the "%s" select', $optionValue, $select));
        }
    }
    
    /**
     * @Then /^"([^"]*)" is selected in the "([^"]*)" tree dropdown$/
     */
    public function treeDropdownIsSetTo($optionValue, $field) {
        $formFields=$this->getSession()->getPage()->findAll('xpath', "//*[@name='$field']");
        
        // Find by label
        if(!$formFields) {
            $label=$this->getSession()->getPage()->find('xpath', "//label[.='$field']");
            if($label && $for=$label->getAttribute('for')) {
                $formField=$this->getSession()->getPage()->find('xpath', "//*[@id='$for']");
                if($formField) {
                    $formFields[]=$formField;
                }
            }
        }
        
        assertGreaterThan(0, count($formFields), sprintf('Tree dropdown named "%s" not found', $field));
        
        // Traverse up to field holder
        $container=null;
        foreach($formFields as $formField) {
            $container=$this->findParentByClass($formField, 'field');
            if($container && $container->hasClass('treedropdown')) {
                break; // Default to first visible container
            }
        }
        
        assertNotNull($container, sprintf('Tree dropdown named "%s" not found', $field));
        
        
        //Find the title element
        $selected=$container->find('css', '.treedropdownfield-title');
        
        assertNotNull($container, 'Could not find the tree dropdown\'s title element');
        
        //Confirm the selected title
        assertEquals($selected->getText(), $optionValue, sprintf('Tree dropdown named "%s" does not have "%s" selected', $field, $optionValue));
    }
    
    /**
     * @Given /^I press the "([^"]*)" button in the "([^"]*)" grid field$/
     */
    public function pressTheGridFieldButton($button, $label) {
        //Find the GridField
        $gridField=$this->getTable($label);
        
        
        //Make sure we can find the grid field
        assertNotNull($gridField, sprintf('Grid field "%s" not found', $label));
        assertTrue($gridField->isVisible(), sprintf('Grid field "%s" was not visible', $label));
        
        
        //If we have the table get the fieldset
        if($gridField->hasClass('ss-gridfield-table')) {
            $gridField=$gridField->getParent();
        }
        
        
        $gridField->pressButton($button);
    }
    
    /**
     * @Given /^I (?P<mode>(fill in|check|uncheck)) "(?P<column>[^"]*)" in the (?P<rowLocation>(new row|(((\d+)(st|nd|rd|th))|last|first) editable row)) in the "(?P<gridFieldLabel>[^"]*)" grid field(( with "(?P<value>[^"]*)")?)$/
     */
    public function fillInGridFieldEditableField($mode, $column, $rowLocation, $gridFieldLabel, $value='') {
        $escaper=new \Behat\Mink\Selector\Xpath\Escaper();
        
        //Find the GridField
        $gridField=$this->getTable($gridFieldLabel);
        
        
        //Make sure we can find the grid field
        assertNotNull($gridField, sprintf('Grid field "%s" not found', $gridFieldLabel));
        assertTrue($gridField->isVisible(), sprintf('Grid field "%s" was not visible', $gridFieldLabel));
        
        
        $columnElement=$gridField->find('xpath', '//thead/tr/th[contains(concat(\' \', normalize-space(@class), \' \'), '.$escaper->escapeLiteral(' col-'.$column.' ').') or contains(concat(\' \', normalize-space(@class), \' \'), '.$escaper->escapeLiteral(' col-action_SetOrder'.$column.' ').')]');
        if($columnElement==null) {
            $columnElement=$gridField->find('xpath', '//thead/tr/th/*[contains(.,'.$escaper->escapeLiteral($column).')]/ancestor::th');
            if($columnElement==null) {
                throw new \Exception('Could not find the "'.$column.'" column in the "'.$gridFieldLabel.'" grid field');
            }
            
            //Find the class attribute of the column
            $columnClass=array();
            if(preg_match('/((\s+)|^)col-((action_SetOrder)?)(.*)((\s+)|$)/', $columnElement->getAttribute('class'), $columnClass)==false) {
                throw new \Exception('Could not find the column name class');
            }
            
            $columnClass=$columnClass[5];
        }else {
            $columnClass=$column;
        }
        
        switch($rowLocation) {
            case 'first editable row':$rowSelector=':first-child';break;
            case 'last editable row':$rowSelector=':last-child';break;
            case 'new row':$rowSelector='.ss-gridfield-inline-new:last-child';break;
            default: {
                $pos=intval(str_replace(array('st', 'nd', 'rd', 'th', ' editable', ' row'), '', $rowLocation));
                $rowSelector=':nth-child('.$pos.')';
                break;
            }
        }
        
        $field=$gridField->find('css', 'tbody tr'.$rowSelector.' td.col-'.$columnClass.' input:not([type=\'hidden\']):not([type=\'button\']):not([type=\'submit\']):not([type=\'reset\']):not([type=\'radio\'])');
        if($field!=null) {
            if($mode=='fill in') {
                $field->setValue($value);
            }else if($mode=='check' || $mode=='uncheck') {
                if($field->getAttribute('type')=='checkbox') {
                    if($mode=='check') {
                        $field->check();
                    }else {
                        $field->uncheck();
                    }
                }else {
                    throw new \Exception('Could not find an input in the "'.$column.'" column of the "'.$gridFieldLabel.'" grid field is not a checkbox');
                }
            }
        }else {
            $field=$gridField->find('css', 'tbody tr'.$rowSelector.' td.col-'.$columnClass.' textarea');
            if($field!=null) {
                $field->setValue($value);
            }else {
                throw new \Exception('Could not find an input or textarea in the "'.$column.'" column of the "'.$gridFieldLabel.'" grid field');
            }
        }
    }
    
    /**
     * @Given /^I select "(?P<value>[^"]*)" in "(?P<column>[^"]*)" in the (?P<rowLocation>(new row|(((\d+)(st|nd|rd|th))|last|first) editable row)) in the "(?P<gridFieldLabel>[^"]*)" grid field$/
     */
    public function selectOptionGridFieldEditableField($value, $column, $type, $rowLocation, $gridFieldLabel) {
        $escaper=new \Behat\Mink\Selector\Xpath\Escaper();
        
        //Find the GridField
        $gridField=$this->getTable($gridFieldLabel);
        
        
        //Make sure we can find the grid field
        assertNotNull($gridField, sprintf('Grid field "%s" not found', $gridFieldLabel));
        assertTrue($gridField->isVisible(), sprintf('Grid field "%s" was not visible', $gridFieldLabel));
        
        
        $columnElement=$gridField->find('xpath', '//thead/tr/th[contains(concat(\' \', normalize-space(@class), \' \'), '.$escaper->escapeLiteral(' col-'.$column.' ').') or contains(concat(\' \', normalize-space(@class), \' \'), '.$escaper->escapeLiteral(' col-action_SetOrder'.$column.' ').')]');
        if($columnElement==null) {
            $columnElement=$gridField->find('xpath', '//thead/tr/th/*[contains(.,'.$escaper->escapeLiteral($column).')]/ancestor::th');
            if($columnElement==null) {
                throw new \Exception('Could not find the "'.$column.'" column in the "'.$gridFieldLabel.'" grid field');
            }
            
            //Find the class attribute of the column
            $columnClass=array();
            if(preg_match('/((\s+)|^)col-((action_SetOrder)?)(.*)((\s+)|$)/', $columnElement->getAttribute('class'), $columnClass)==false) {
                throw new \Exception('Could not find the column name class');
            }
            
            $columnClass=$columnClass[5];
        }else {
            $columnClass=$column;
        }
        
        switch($rowLocation) {
            case 'first editable row':$rowSelector=':first-child';break;
            case 'last editable row':$rowSelector=':last-child';break;
            case 'new row':$rowSelector='.ss-gridfield-inline-new:last-child';break;
            default: {
                $pos=intval(str_replace(array('st', 'nd', 'rd', 'th', ' editable', ' row'), '', $rowLocation));
                $rowSelector=':nth-child('.$pos.')';
                break;
            }
        }
        
        $columnElement=$gridField->find('css', 'tbody tr'.$rowSelector.' td.col-'.$columnClass);
        if($columnElement!=null) {
            $field=$columnElement->find('css', 'select');
            if($field!=null) {
                $field->selectOption($value);
            }else {
                $field=$columnElement->find('named', array('radio', $escaper->escapeLiteral($value)));
                if($field!=null) {
                    $field->click();
                }else {
                    throw new \Exception('Could not find a radio or select in the column "'.$column.'" in the "'.$gridFieldLabel.'" grid field');
                }
            }
        }else {
            throw new \Exception('Could not find a row with the column "'.$column.'" in the "'.$gridFieldLabel.'" grid field');
        }
    }
    
    /**
     * @Then /^the "([^"]*)" option is selected in the "([^"]*)" radio group$/
     */
    public function optionFromRadioGroupIsSelected($optionValue, $radioGroupName) {
        $page=$this->getSession()->getPage();
        $escaper=new \Behat\Mink\Selector\Xpath\Escaper();
        
        $element=$page->find('xpath', 'descendant-or-self::*[@class and (contains(concat(\' \', normalize-space(@class), \' \'), \' field \') or contains(concat(\' \', normalize-space(@class), \' \'), \' fieldgroup-field \'))]/label[contains(., '.$escaper->escapeLiteral($radioGroupName).')]');
        if($element==null) {
            $element=$page->find('named', array('id_or_name', $escaper->escapeLiteral($radioGroupName)));
            
            if($element==null || $element->getTagName()!='input' || $element->getAttribute('type')!='radio') {
                throw new \Exception(sprintf('The radio group "%s" was not found', $radioGroupName));
            }
        }
        
        $element=$this->findParentByClass($element, 'field');
        if($element==null || !$element->hasClass('optionset')) {
            throw new \Exception('Could not find the parent element of the radio group');
        }
        
        $selectedOption=$element->find('named', array('radio', $escaper->escapeLiteral($optionValue)));
        if($selectedOption==null) {
            throw new \Exception(sprintf('Could not find an option with the id|name|label "%s" in the "%s" radio group', $optionValue, $radioGroupName));
        }
        
        if(!$selectedOption->isChecked()) {
            throw new \Exception(sprintf('The option "%s" was not selected in the "%s" radio group', $optionValue, $radioGroupName));
        }
    }
    
    /**
     * @When /^I move the (?P<srcRowNumber>(\d+(st|nd|rd|th))) row in the "(?P<gridFieldLabel>[^"]*)" table before the (?P<destRowNumber>(\d+(st|nd|rd|th))) row$/
     */
    public function iDragRowToRow($srcRowNumber, $gridFieldLabel, $destRowNumber) {
        $escaper=new \Behat\Mink\Selector\Xpath\Escaper();
        
        //Find the GridField
        $gridField=$this->getTable($gridFieldLabel);
        
        
        //Make sure we can find the grid field
        assertNotNull($gridField, sprintf('Grid Field "%s" not found', $gridFieldLabel));
        assertTrue($gridField->isVisible(), sprintf('Grid Field "%s" was not visible', $gridFieldLabel));
        
        
        //Try to find the source row
        $srcRow=$gridField->find('css', 'table.ss-gridfield-table tbody.ss-gridfield-items tr.ss-gridfield-item:nth-child('.intval(str_replace(array('st', 'nd', 'rd', 'th'), '', $srcRowNumber)).') td:first-child');
        if($srcRow==null) {
            throw new \Exception('Row to move "'.$srcRowNumber.'" could not be found in the Grid Field "'.$gridFieldLabel.'"');
        }
        
        
        //Try to find the target position panel
        $destRow=$gridField->find('css', 'table.ss-gridfield-table tbody.ss-gridfield-items tr.ss-gridfield-item:nth-child('.intval(str_replace(array('st', 'nd', 'rd', 'th'), '', $destRowNumber)).') td:first-child');
        if($destRow==null) {
            throw new \Exception('Row to move before "'.$destRowNumber.'" could not be found in the Grid Field "'.$gridFieldLabel.'"');
        }
        
        
        $srcRow->dragTo($destRow);
    }
    
    /**
     * @When /^I add a(n?) "(?P<itemType>[^"]*)" to the "(?P<gridFieldLabel>[^"]*)" table$/
     */
    public function iAddAItemToTable($itemType, $gridFieldLabel) {
        $escaper=new \Behat\Mink\Selector\Xpath\Escaper();
        
        //Find the GridField
        $gridField=$this->getTable($gridFieldLabel);
        
        
        //Make sure we can find the grid field
        assertNotNull($gridField, sprintf('Grid Field "%s" not found', $gridFieldLabel));
        assertTrue($gridField->isVisible(), sprintf('Grid Field "%s" was not visible', $gridFieldLabel));
        
        //Get the fieldset around the GridField table
        $gridField=$gridField->getParent();
        
        
        //Find the dropdown
        $itemTypeDrop=$gridField->find('css', '.addNewItemTypeButton select');
        assertNotNull($gridField, sprintf('Could not find the item type dropdown in the "%s" Grid Field', $gridFieldLabel));
        assertTrue($gridField->isVisible(), sprintf('Item type dropdown in the "%s" Grid Field was not visible', $gridFieldLabel));
        
        
        //Find the add button
        $itemTypeButton=$gridField->find('css', '.addNewItemTypeButton a.new-item-type-add');
        assertNotNull($gridField, sprintf('Could not find the item type add button in the "%s" Grid Field', $gridFieldLabel));
        assertTrue($gridField->isVisible(), sprintf('Item type add button in the "%s" Grid Field was not visible', $gridFieldLabel));
        
        
        //Attempt to find the option in the dropdown
        $option=$itemTypeDrop->find('named', array('option', $escaper->escapeLiteral($itemType)));
        assertNotNull($gridField, sprintf('Could not find the "%s" option in the item type dropdown for the "%s" Grid Field', $itemType, $gridFieldLabel));
        
        
        //Select the option
        if($itemTypeDrop && $itemTypeDrop->isVisible()) {
            $itemTypeDrop->selectOption($option->getAttribute('value'));
            assertEquals($itemTypeDrop->getValue(), $option->getAttribute('value'), 'Could not select the option');
        }else {
            $container=$this->findParentByClass($itemTypeDrop, 'field');
            assertNotNull($container, 'Chosen.js field container not found');
            
            //Click on newly expanded list element, indirectly setting the dropdown value
            $linkEl=$container->find('xpath', './/a[./@href]');
            assertNotNull($linkEl, 'Chosen.js link element not found');
            $linkEl->click();
            
            //Wait for dropdown overlay to appear (might be animated)
            $this->getSession()->wait(300);
            
            //Find the option in the chosen dropdown
            $listEl=$container->find('xpath', sprintf('.//li[contains(normalize-space(string(.)), %s)]', $escaper->escapeLiteral(trim(strip_tags($option->getHtml())))));
            assertNotNull($listEl, sprintf('Chosen.js list element with title "%s" not found', $itemType));
            
            //Click the element
            $listLinkEl=$listEl->find('xpath', './/a');
            if($listLinkEl) {
                $listLinkEl->click();
            }else {
                $listEl->click();
            }
        }
        
        
        //Click the add button
        $itemTypeButton->click();
    }
    
    /**
     * Finds the first visible table by various factors:
     * - table[id]
     * - table[title]
     * - table *[class=title]
     * - fieldset[data-name] table
     * - table caption
     *
     * @return Behat\Mink\Element\NodeElement
     */
    protected function getTable($selector) {
        $selector=$this->getSession()->getSelectorsHandler()->xpathLiteral($selector);
        $page=$this->getSession()->getPage();
        $candidates=$page->findAll('xpath', $this->getSession()->getSelectorsHandler()->selectorToXpath("xpath", ".//table[(./@id = $selector or  contains(./@title, $selector))]"));
        
        // Find tables by a <caption> field
        $candidates+=$page->findAll('xpath', "//table//caption[contains(normalize-space(string(.)), $selector)]/ancestor-or-self::table[1]");
        
        // Find tables by a .title node
        $candidates+=$page->findAll('xpath', "//table//*[@class='title' and contains(normalize-space(string(.)), $selector)]/ancestor-or-self::table[1]");
        
        // Some tables don't have a visible title, so look for a fieldset with data-name instead
        $candidates+=$page->findAll('xpath', "//fieldset[@data-name=$selector]//table");
        
        assertTrue((bool)$candidates, 'Could not find any table elements');
        
        $table=null;
        foreach($candidates as $candidate) {
            if(!$table && $candidate->isVisible()) {
                $table=$candidate;
            }
        }
        
        assertTrue((bool)$table, 'Found table elements, but none are visible');
        
        return $table;
    }
    
    /**
     * @Then /^the "(?P<selector>[^"]*)" table should(?P<negative>( not |\s*))contain "(?P<text>[^"]*)" in the (?P<rowLocation>(((\d+)(st|nd|rd|th))|last|first)) row of the table$/
     */
    public function theTableShouldContainOnRow($selector, $negative, $text, $rowLocation) {
        $escaper=new \Behat\Mink\Selector\Xpath\Escaper();
        $table=$this->getTable($selector);
        
        $element=null;
        switch($rowLocation) {
            case 'first':$rowSelector=':first-child';break;
            case 'last':$rowSelector=':last-child';break;
            default: {
                $pos=intval(str_replace(array('st', 'nd', 'rd', 'th'), '', $rowLocation));
                $rowSelector=':nth-child('.$pos.')';
                break;
            }
        }
        
        $rowElement=$table->find('css', 'tbody tr'.$rowSelector);
        if(empty($rowElement)) {
            throw new \Exception(sprintf('The row `%s` was not found in `%s` table', $rowLocation, $selector));
        }
        
        $element=$rowElement->find('named', array('content', $this->getSession()->getSelectorsHandler()->xpathLiteral($text)));
        
        if(trim($negative)) {
            assertNull($element, sprintf('Text `%s` found in the %s row of `%s` table', $text, $rowLocation, $selector));
        }else {
            assertNotNull($element, sprintf('Text `%s` not found in the %s row of `%s` table', $text, $rowLocation, $selector));
        }
    }
    
    /**
     * @Then /^I should( not |\s*)see a WYSIWYG popup$/
     */
    public function iShouldSeeWYSIWIGPopup($negative) {
        $page=$this->getSession()->getPage();
        $dialog=$page->find('css', '.htmleditorfield-dialog:last-child');
        
        if(trim($negative)) {
            if(empty($dialog) || $dialog->isVisible()==true) {
                throw new \Exception('Found a visible WYSIWYG popup');
            }
        }else {
            assertNotNull($dialog, 'Could not find the WYSIWYG popup');
            assertTrue($dialog->isVisible(), 'The WYSIWYG popup was not visible');
        }
    }
    
    /**
     * @When /^I highlight "(?P<text>((?:[^"]|\\")*))" in the "(?P<field>(?:[^"]|\\")*)" HTML field$/
     */
    public function iHighlightTextInHtmlField($text, $field) {
        $inputField = $this->getHtmlField($field);
        $inputFieldId = $inputField->getAttribute('id');
        $text = addcslashes(str_replace('\\"', '"', $text), "'");

        $js = <<<JS
// TODO <IE9 support
// TODO Allow text matches across nodes
var editor = jQuery('#$inputFieldId').entwine('ss').getEditor(),
    doc = editor.getDOM().doc,
    sel = editor.getInstance().selection,
    rng = document.createRange(),
    matched = false;

jQuery(doc).find('body *').each(function() {
    if(!matched) {
        for(var i=0;i<this.childNodes.length;i++) {
            console.log(this.childNodes[i].outerHTML);
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
JS;

        $this->getSession()->executeScript($js);
    }
    
    /**
     * Converts a natural language class description to an actual class name.
     * Respects {@link DataObject::$singular_name} variations.
     * Example: "redirector page" -> "RedirectorPage"
     * @param string
     * @return string Class name
     */
    protected function convertTypeToClass($type)  {
        $type = trim($type);
        
        // Try direct mapping
        $class = str_replace(' ', '', ucwords($type));
        if(class_exists($class) || !($class == 'DataObject' || is_subclass_of($class, 'DataObject'))) {
            return $class;
        }
        
        // Fall back to singular names
        foreach(array_values(\ClassInfo::subclassesFor('DataObject')) as $candidate) {
            if(singleton($candidate)->singular_name() == $type) return $candidate;
        }
        
        throw new \InvalidArgumentException(sprintf(
            'Class "%s" does not exist, or is not a subclass of DataObjet',
            $class
        ));
    }
    
    /**
     * Spin function
     * @param function $callback Callback function
     * @param int $wait Timeout in seconds
     */
    protected function spin($callback, $wait=5) {
        for($i=0;$i<$wait;$i++) {
            try {
                if($callback($this)) {
                    return true;
                }
            }catch(Exception $e) {}
            
            sleep(1);
        }
        
        $backtrace=debug_backtrace();
        throw new \Exception("Timeout thrown by " . $backtrace[1]['class'] . "::" . $backtrace[1]['function'] . "()\n" .(array_key_exists('file', $backtrace[1]) ? $backtrace[1]['file'] . ", line " . $backtrace[1]['line']:'Unknown File'));
    }
    
    /**
     * Get Mink session from MinkContext
     * @return Session
     */
    protected function getSession($name = null) {
        return $this->getMainContext()->getSession($name);
    }
    
    /**
     * Sets the element with the given selector's id
     * @param string $selector CSS Selector
     * @param string $id ID to give the element
     */
    protected function setElementId($selector, $id) {
        $function='(function() {'.
                        'var iframe = document.querySelector("'.\Convert::raw2js($selector).'");'.
                        'iframe.setAttribute("id", "'.\Convert::raw2js($id).'");'.
                    '})()';
        
        try{
            $this->getSession()->executeScript($function);
        }catch (\Exception $e){
            throw new \Exception("Element $selector was NOT found.".PHP_EOL.$e->getMessage());
        }
    }

    /**
     * Returns the closest parent element having a specific class attribute.
     * @param NodeElement $el
     * @param String $class
     * @return Element|null
     */
    protected function findParentByClass(NodeElement $el, $class) {
        $container=$el->getParent();
        while($container && $container->getTagName()!='body') {
            if($container->hasClass($class)) {
                return $container;
            }
            
            $container=$container->getParent();
        }

        return null;
    }

    /**
     * Locate an HTML editor field
     *
     * @param string $locator Raw html field identifier as passed from
     * @return NodeElement
     */
    protected function getHtmlField($locator) {
        $locator = str_replace('\\"', '"', $locator);
        $page = $this->getSession()->getPage();
        $element = $page->find('css', 'textarea.htmleditor[name=\'' . $locator . '\']');
        assertNotNull($element, sprintf('HTML field "%s" not found', $locator));
        return $element;
    }
}
?>