<?php
namespace WebbuildersGroup\GitHubActionsCIRecipe\Behaviour;

use Behat\Behat\Context\BehatContext,
    Behat\Behat\Context\Step,
    Behat\Behat\Event\StepEvent,
    Behat\Behat\Event\ScenarioEvent;

// PHPUnit
require_once 'PHPUnit/Autoload.php';
require_once 'PHPUnit/Framework/Assert/Functions.php';

class WBFrontEndContext extends BehatContext {
    private $_add_analytics_hook=false;
    
    const DESKTOP_WINDOW_WIDTH=1280;
    const DESKTOP_WINDOW_HEIGHT=700;
    const TABLET_WINDOW_WIDTH=768;
    const TABLET_WINDOW_HEIGHT=700;
    const MOBILE_WINDOW_WIDTH=320;
    const MOBILE_WINDOW_HEIGHT=568;
    
    /**
     * Window width offset, used to offset for a difference in selenium plus browser chrome
     * (selenium is off by 14px, browser chrome is 2px in Google Chrome)
     */
    const WINDOW_WIDTH_OFFSET=16;
    
    /**
     * Window height offset, used to offset for a difference in selenium plus browser chrome
     * (selenium is off by 14px, browser chrome is 2px in Google Chrome)
     */
    const WINDOW_HEIGHT_OFFSET=93;
    
    
    /**
     * @BeforeScenario @analytics
     */
    public function beforeScenarioAnalytics(ScenarioEvent $event) {
        $this->_add_analytics_hook=true;
    }
    
    /**
     * @AfterScenario @analytics
     */
    public function afterScenarioAnalytics(ScenarioEvent $event) {
        $this->_add_analytics_hook=false;
    }
    
    /**
     * @BeforeScenario @desktop
     */
    public function beforeScenarioDesktop(ScenarioEvent $event) {
        $this->getSession()->resizeWindow(self::DESKTOP_WINDOW_WIDTH+self::WINDOW_WIDTH_OFFSET, self::DESKTOP_WINDOW_HEIGHT+self::WINDOW_HEIGHT_OFFSET, 'current');
    }
    
    /**
     * @BeforeScenario @tablet
     */
    public function beforeScenarioTablet(ScenarioEvent $event) {
        $this->getSession()->resizeWindow(self::TABLET_WINDOW_WIDTH+self::WINDOW_WIDTH_OFFSET, self::TABLET_WINDOW_HEIGHT+self::WINDOW_HEIGHT_OFFSET, 'current');
    }
    
    /**
     * @BeforeScenario @mobile
     */
    public function beforeScenarioMobile(ScenarioEvent $event) {
        $this->getSession()->resizeWindow(self::MOBILE_WINDOW_WIDTH+self::WINDOW_WIDTH_OFFSET, self::MOBILE_WINDOW_HEIGHT+self::WINDOW_HEIGHT_OFFSET, 'current');
    }
    
    /**
     * @AfterScenario @desktop
     */
    public function afterScenarioDesktop(ScenarioEvent $event) {
        if($screenSize=getenv('BEHAT_SCREEN_SIZE')) {
            list($screenWidth, $screenHeight)=explode('x', $screenSize);
            
            $this->getSession()->resizeWindow(((int)$screenWidth)+self::WINDOW_WIDTH_OFFSET, ((int)$screenHeight)+self::WINDOW_HEIGHT_OFFSET, 'current');
        }else {
            $this->getSession()->resizeWindow(1024+self::WINDOW_WIDTH_OFFSET, 768+self::WINDOW_HEIGHT_OFFSET, 'current');
        }
    }
    
    /**
     * @AfterScenario @desktop
     */
    public function afterScenarioTablet(ScenarioEvent $event) {
        $this->afterScenarioDesktop($event);
    }
    
    /**
     * @AfterScenario @mobile
     */
    public function afterScenarioMobile(ScenarioEvent $event) {
        $this->afterScenarioDesktop($event);
    }
    
    /**
     * @Then /^I close (the new|all) (tab|window)(s?)$/
     */
    public function iCloseTheNewTab($tabToClose) {
        $windows=$this->getSession()->getDriver()->getWebDriverSession()->window_handles();
        
        //Make sure we have at least two tabs
        if(count($windows)<=1) {
            throw new \InvalidArgumentException('There appears to be only one tab');
        }
        
        if($tabToClose=='the new') {
            try {
                //Switch to the second tab
                $this->getSession()->switchToWindow(end($windows));
                
                //Close the Tab
                $this->getSession()->executeScript('window.close()');
            }catch(\WebDriver\Exception\NoSuchWindow $e) {
                //Supress window already closed exceptions
            }
        }else {
            //Close all but the first tab
            for($i=1;$i<count($windows);$i++) {
                try {
                    //Switch to the tab
                    $this->getSession()->switchToWindow($windows[$i]);
                    
                    //Close the Tab
                    $this->getSession()->executeScript('window.close()');
                }catch(\WebDriver\Exception\NoSuchWindow $e) {
                    //Supress window already closed exceptions
                }
            }
        }
        
        //Switch to the first tab
        $this->getSession()->switchToWindow($windows[0]);
    }
    
    /**
     * @Then /^the title of an? (link|element) should( not |\s*)contain "([^"]*)"$/
     */
    public function theTitleOfElementContains($matchOn, $negative, $text) {
        //Find the link
        $escaper=new \Behat\Mink\Selector\Xpath\Escaper();
        $element=$this->getSession()->getPage()->find('xpath', '//'.($matchOn=='link' ? 'a':'*').'[contains(@title, '.$escaper->escapeLiteral($text).')]');
        
        if(trim($negative)) {
            assertNull($element, sprintf('A %s contains `%s`', $matchOn, $text));
        }else {
            assertNotNull($element, sprintf('A %s does not contain `%s`', $matchOn, $text));
        }
    }
    
    /**
     * @Then /^the title of an? (link|element) should( not |\s*)contain "([^"]*)" in the "([^"]*)" element$/
     */
    public function theTitleOfElementInElementContains($matchOn, $negative, $text, $selector) {
        //Find the Selector
        $element=$this->getSession()->getPage()->find('css', $selector);
        assertNotNull($element, sprintf('element with the selector "%s" not found', $selector));
        
        //Find the link
        $escaper=new \Behat\Mink\Selector\Xpath\Escaper();
        $element=$this->getSession()->getPage()->find('xpath', '//'.($matchOn=='link' ? 'a':'*').'[contains(@title, '.$escaper->escapeLiteral($text).')]');
        
        if(trim($negative)) {
            assertNull($element, sprintf('A %s contains `%s`', $matchOn, $text));
        }else {
            assertNotNull($element, sprintf('A %s does not contain `%s`', $matchOn, $text));
        }
    }
    
    /**
     * @Then /^I should see a new window directed to "([^"]*)"$/
     */
    public function iShouldSeeAWindow($url) {
        $windows=$this->getSession()->getDriver()->getWebDriverSession()->window_handles();
        
        //Make sure we have at least two tabs
        if(count($windows)<=1) {
            throw new \InvalidArgumentException('There appears to be only one tab');
        }
        
        
        //Get the current window handle
        $curr_handle=$this->getSession()->getDriver()->getWebDriverSession()->window_handle();
        
        
        //Switch to the last tab
        $this->getSession()->switchToWindow($windows[count($windows)-1]);
        
        
        //Get the new tab url
        $newTabURL=$this->getSession()->getCurrentUrl();
        
        
        //Switch back to the previous window
        $this->getSession()->switchToWindow($curr_handle);
        
        
        //Verify we have the given url
        if($newTabURL!=$url) {
            throw new \Exception(sprintf('The current url of the last tab "%s" does not match the given url "%s"', $newTabURL, $url));
        }
    }
    
    /**
     * @Then /^I should( not |\s*)see the field "([^"]*)"$/
     */
    public function iShouldSeeTheField($negative, $selector) {
        $field=$this->getSession()->getPage()->findField($selector);
        
        //For chosen the select is always hidden so we need to look to the parent
        if($field!=null && $field->hasClass('has-chzn')) {
            $field=$field->getParent();
        }
        
        
        if(trim($negative)) {
            if($field!=null && $field->isVisible()) {
                throw new \Exception(sprintf('Field with the id|name|placeholder|label "%s" was found and was visible', $selector));
            }
        }else {
            if($field==null || !$field->isVisible()) {
                throw new \Exception(sprintf('Field with the id|name|placeholder|label "%s" was not found or was not visible', $selector));
            }
        }
    }
    
    /**
     * @Then /^I (check|uncheck) the "([^"]*)" (checkbox|radio(\s?button)?)$/
     */
    public function iCheckTheCheckboxRadio($action, $option, $type) {
        $field=$this->getSession()->getPage()->findField($option);
        
        if($field==null) {
            throw new \Exception(sprintf('Field with the id|name|placeholder|label "%s" was not found', $option));
        }
        
        
        //Find the trigger label
        if($type=='checkbox') {
            $triggerLabel=$field->getParent()->find('css', '.checkbox > label');
        }else {
            $triggerLabel=$field->getParent()->find('css', '.radio > label');
        }
        
        
        //Make sure we found the trigger label
        if($triggerLabel==null) {
            throw new \Exception(sprintf('Could not find the styled %s trigger for the "%s" %s', ($type=='checkbox' ? 'checkbox':'radio button'), $option, ($type=='checkbox' ? 'checkbox':'option')));
        }
        
        
        //Click the label
        if($action=='uncheck') {
            if($field->isChecked()) {
                $triggerLabel->click();
            }else {
                throw new \Exception(sprintf('Expected to uncheck the "%s" %s but it was already unchecked', $option, ($type=='checkbox' ? 'checkbox':'option')));
            }
        }else {
            if(!$field->isChecked()) {
                $triggerLabel->click();
            }else {
                throw new \Exception(sprintf('Expected to check the "%s" %s but it was already checked', $option, ($type=='checkbox' ? 'checkbox':'option')));
            }
        }
    }
    
    /**
     * @Then /^I should( not |\s*)see a visible "([^"]*)" element$/
     */
    public function iSeeAnVisibleElement($negative, $selector) {
        $element=$this->getSession()->getPage()->find('css', $selector);
        
        if(trim($negative)) {
            if($element!=null && $element->isVisible()) {
                throw new \Exception(sprintf('An element with the selector "%s" was found and was visible', $selector));
            }
        }else {
            if($element==null || !$element->isVisible()) {
                throw new \Exception(sprintf('An element with the selector "%s" was not found or was not visible', $selector));
            }
        }
    }
    
    /**
     * @Then /^I should( not |\s*)see the (current|next|previous) month and year(( in the format "(?P<format>([^"]*))")?)$/
     */
    public function iSeeTheDate($negative, $tense, $format='F Y') {
        switch($tense) {
            case 'next': $dateToFind=date($format, strtotime('+1 month'));break;
            case 'previous': $dateToFind=date($format, strtotime('-1 month'));break;
            default: $dateToFind=date($format);break;
        }
        
        return new Step\Then('I should'.$negative.'see "'.$dateToFind.'"');
    }
    
    /**
     * @Then /^I should( not |\s*)see an error$/
     */
    public function iShouldSeeError($negative) {
        $link=$this->getSession()->getPage()->find('xpath', '//link[contains(@href, "framework/css/debug.css")]');
        
        if(trim($negative)) {
            if($link!=null) {
                throw new \Exception('An error was found on the page');
            }
        }else {
            if($link==null) {
                throw new \Exception('Could not find an error on the page');
            }
        }
    }
    
    /**
     * @Then /^I switch to the "([^"]*)" iframe$/
     */
    public function iSwitchToTheSelectedFrame($selector) {
        //Switch to the iframe Window
        $frame=$this->getSession()->getPage()->find('css', $selector);
        
        assertNotNull($frame, 'Could not find the frame');
        
        //Make sure it resolved to an iframe
        assertEquals('iframe', strtolower($frame->getTagName()), 'Selector did not resolve to an iframe element');
        
        $frameID=$frame->getAttribute('id');
        if(empty($frameID)) {
            $frameID='frame-'.sha1(uniqid(time()));
            $this->setElementId($selector, $frameID);
        }
        
        //Switch to the frame based on it's index
        $this->getSession()->switchToIFrame($frameID);
    }
    
    /**
     * @Then /^I switch back from the iframe$/
     */
    public function iSwitchBackFromTheIFrame() {
        $this->getSession()->switchToIFrame(null);
    }
    
    /**
     * @Then /^I hover over "([^"]*)"$/
     */
    public function iHoverOver($selector) {
        $matches=$this->getSession()->getPage()->findAll('named', array('link_or_button', "'$selector'"));
        
        $matchedElement=null;
        foreach($matches as $element) {
            if($element->isVisible()) {
                $matchedElement=$element;
                break;
            }
        }
        
        //If there wsa no element try looking for a no-link in the main nav
        if($matchedElement==null) {
            $escaper=new \Behat\Mink\Selector\Xpath\Escaper();
            $matches=$this->getSession()->getPage()->findAll('css', '#Header .menuwrapper ul.menu-bar li .no-link:contains('.$escaper->escapeLiteral($selector).'), #solutions-header .menuwrapper ul.menu-bar li .no-link:contains('.$escaper->escapeLiteral($selector).')');
            foreach($matches as $element) {
                if($element->isVisible()) {
                    $matchedElement=$element;
                    break;
                }
            }
        }
        
        assertNotNull($matchedElement, sprintf('"%s" element not found', $selector));
        
        $matchedElement->mouseOver();
    }
    
    /**
     * @Then /^I should( not |\s*)see a (link|button|link or( a)? button) "(?P<selector>([^"]*))"$/
     */
    public function iShouldSeeLinkButton($negative, $type, $selector) {
        $friendlyType=$type;
        
        if($type=='link or button' || $type=='link or a button') {
            $type='link_or_button';
        }
        
        $matches=$this->getSession()->getPage()->findAll('named', array($type, "'$selector'"));
        
        $matchedElement=null;
        foreach($matches as $element) {
            if($element->isVisible()) {
                $matchedElement=$element;
            }
        }
        
        if(trim($negative)) {
            assertNull($matchedElement, sprintf('"%s" %s was found', $selector, $friendlyType));
        }else {
            assertNotNull($matchedElement, sprintf('"%s" %s was not found', $selector, $friendlyType));
        }
    }
    
    /**
     * @Then /^I should( not |\s*)see the field "([^"]*)" in the "([^"]*)" element$/
     */
    public function iShouldSeeAFieldInElement($negative, $fieldSelector, $selector=null) {
        $page=$this->getSession()->getPage();
        
        //Find the Selector
        $element=$page->find('css', $selector);
        assertNotNull($element, sprintf('element with the selector "%s" not found', $selector));
        
        //Find the field
        $escaper=new \Behat\Mink\Selector\Xpath\Escaper();
        $element=$element->findField($fieldSelector);
        
        if(trim($negative)) {
            if($element!=null && $element->isVisible()) {
                throw new \Exception(sprintf('field with the label|placeholder|id|name "%s" was present or was visible', $fieldSelector));
            }
        }else {
            assertNotNull($element, sprintf('field with the label|placeholder|id|name "%s" not found', $fieldSelector));
            
            assertTrue($element->isVisible(), sprintf('field with the label|placeholder|id|name "%s" was not visible', $fieldSelector));
        }
    }
    
    /**
     * @Given /^I execute the script "([^"]*)"$/
     */
    public function iExecuteTheScript($script) {
        $this->getSession()->executeScript($script);
    }
    
    /**
     * @Given /^the "([^"]*)" dropdown should( not |\s*)contain the "([^"]*)" option$/
     */
    public function dropdownHasOption($name, $negative, $option) {
        $escaper=new \Behat\Mink\Selector\Xpath\Escaper();
        
        $dropdown=$this->getSession()->getPage()->find('named', array('select', $escaper->escapeLiteral($name)));
        
        assertNotNull($dropdown, sprintf('Select with the label|placeholder|id|name "%s" not found', $name));
        
        if($dropdown->getTagName()!='select') {
            throw new \InvalidArgumentException(sprintf('Field with the label|placeholder|id|name "%s" is not a select', $name));
        }
        
        $selectItem=$dropdown->find('named', array('option', $escaper->escapeLiteral($option)));
        if(trim($negative)) {
            assertNull($selectItem, sprintf('Option with the label|value "%s" was found in the "%s" select', $option, $name));
        }else {
            assertNotNull($selectItem, sprintf('Option with the label|value "%s" was found in the "%s" select', $option, $name));
        }
    }
    
    /**
     * Recursivly looks for the value in the array
     * @param mixed $key Key to look for in the $array parameter, if this is an array then recursion is used to go to the next depth
     * @param array $array Input array
     * @param int $depth Recursion depth protection
     * @return mixed Returns the value in the input array when $key is a string or an empty array after shifting one parameter. If a key is not found -1 is returned.
     */
    protected function getNestedValue($key, $array, $depth=0) {
        if($depth==10) {
            throw new \Exception('Too much recursion');
        }
        
        if(is_array($key)) {
            $currKey=array_shift($key);
            if(array_key_exists($currKey, $array)) {
                if(!empty($key) && is_array($array[$currKey])) {
                    return $this->getNestedValue($key, $array[$currKey], $depth++);
                }else {
                    return $array[$currKey];
                }
            }else {
                return -1;
            }
        }
        
        return (array_key_exists($key, $array) ? $array[$key]:-1);
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
}
?>