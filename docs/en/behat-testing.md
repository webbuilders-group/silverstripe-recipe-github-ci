Behat Testing
========================
_This documentation covers the Behat testing (behavior testing) for the site. It covers some of how the user experience is tested or should be tested, it does not however cover everything and nothing beats a user doing it._


### Getting Started
#### Writing Tests
It's recommended you use [Github's Atom](http://atom.io/) for editing feature/Gherkin files. To do this you will need to install the [language-gerhkin](https://atom.io/packages/language-gherkin) package into Atom. As for the yaml fixtures, the language-yaml package is pre-installed with Atom.


#### Configuration
You must ensure that your ``_ss_environment.php`` file has a [file to URL mapping](http://doc.silverstripe.org/framework/en/topics/commandline#configuration) for your site root or the tests may fail for unrelated reasons.


#### Installing
To enable the Behat testing support you must make sure you install the site without using the ``--no-dev`` argument. This will include all of the dependencies required for Behat testing. However you must still download the [Selenium Standalone Server](http://docs.seleniumhq.org/download/) which requires Java. You will also require the Chrome Driver for Selenium, [see here for more information](https://github.com/silverstripe/silverstripe-behat-extension/blob/1.0/docs/chrome-behat.md)), use [this link](https://sites.google.com/a/chromium.org/chromedriver/downloads) to get the latest version.


#### Starting the Selenium Server
You can start the Selenium Server by using the following command. If you place this file in the root of the install of the site and name it ``start-selenium-server.bat`` it will be ignored from the git index.

```
java -Dwebdriver.chrome.driver="/path/to/chromedriver.exe" -Dselenium.LOGGER.level="FINE" -jar selenium-server-standalone-{version number}.jar
```

You could turn this start line into a batch file using the below:
```
@echo off
java -Dwebdriver.chrome.driver="/path/to/chromedriver.exe" -Dselenium.LOGGER.level="FINE" -jar /path/to/selenium-server-standalone-{version number}.jar
```


### Running the Tests
Once you have installed things properly you can how run the tests by using one of the following commands from the site root. Note that the ``@mysite`` is the site root folder for the test. If you are focusing the test on a specific feature then you need to add any sub-folders under the ``tests/behat/features/`` folder before the feature's filename.

__All mysite Tests:__
```
"vendor/bin/behat" @mysite
```

__Specific mysite Test by it's Filename:__
```
"vendor/bin/behat" @mysite/sub/folder/path/login.feature
```

__Single Scenario:__
*Note you can also focus this down to a single feature file as well by following the example above but using the name argument.*
```
"vendor/bin/behat" @mysite/sub/folder/path/login.feature --name "My scenario title"
```

__Re-running Failed Tests:__
To rerun only failed tests simply add the ``--rerun="behat.log"`` argument to the command each time you run Behat, for example:
```
"vendor/bin/behat" @mysite --rerun="behat.log"
```
*Note for this to work on Windows you need to modify vendor/behat/behat/src/Behat/Behat/Console/Processor/RunProcessor.php line 95, otherwise you will get an error in Behat because of the Windows line ends.*
```php
$command->setFeaturesPaths(explode(PHP_EOL, trim(file_get_contents($file))));
```

#### Running on Windows 10 Post Fall Creators Update or Windows 7
For Windows 10 Post Fall Creators Update or Windows 7 it's highly recommended you used a console emulator such as [Cmder](http://cmder.net/) otherwise you will not get highlighting of text which with Behat makes it much easier to find successful, failed and skipped steps.

### Setting a Specific Resolution
By default the tests run at a fairly small screen resolution, you can change this by setting an environment variable to the resolution you want before launching Behat. For example:
```
set BEHAT_SCREEN_SIZE=1600x900
```

If you are using PowerShell you need to use the following command instead:
```
$env:BEHAT_SCREEN_SIZE = "1600x900"
```


### Additional Information
For additional information such as how to write tests and for tutorials see the [silverstripe/behat-extensions](https://github.com/silverstripe/silverstripe-behat-extension/blob/1.0/README.md) readme as well see the documentation for [Behat](http://docs.behat.org/en/v2.5/quick_intro.html). Also when a test fails a screenshot will be created in a folder called ``artifacts`` in the root of the site. In Travis builds if you have specified the Rackspace API credentials these will be uploaded to Cloud Files ([see here for information](required-secure-env-vars.md)). As well when a Behat test is running the ``BEHAT_TEST_SESSION`` constant is created and set to true, this should be looked for sparingly as we want the Behat tests to closely match the user's experience however in some instances you may not want to trigger certain things like talking to a live API.


### Behat feature file template
```gherkin
# features/path/to/name-of.feature
Feature: Test Name
    Test Description
    
    Background:
        #Background/Pre-Test Tasks
        #Given I use the fixtures defined in "@mysite/path/to/name-of.yml"
    
    
    @javascript
    Scenario: Scenario Name
        #Scenario Tests
```


### Custom Scenario Tags
Scenario tags are added before your scenario definition.
  
* ``@desktop``
  
  Resizes the browser to desktop width/height (1280x800), browser is reset after the scenario
  
* ``@tablet``
  
  Resizes the browser to tablet width/height (768x1024), browser is reset after the scenario
  
* ``@mobile``
  
  Resizes the browser to mobile width/height (320x568), browser is reset after the scenario


### Custom Fixture Steps
* ``Given /^I use the fixtures defined in "([^"]*)"$/``
  
  Uses the given path to a YAML as a fixture definition, if the given path begins with an @ symbol then the first part of the path is assumed to be the base folder for the path. For example "@mysite/grid-panel/grid-panel.yml" would resolve to mysite/tests/behat/features/grid-panel/grid-panel.yml. If the path does not begin with an @ symbol the path is relative to the mysite features folder. Note that even if you specify the base folder it's always relative to that folder's tests/behat/features/ folder.

* ``Given /^the "([^"]*)" relationship to "([^"]*)" on the "([^"]*)" object has (("([^"]*)"="([^"]*)"( and "([^"]*)"="([^"]*)")*))$/``
  
  Allows you to set extra fields on a relationship for example:

  1. Given the "Relation Name" relationship to "Target.object" on the "Source.object" has "FieldA"="Field A Value"
  2. Given the "Relation Name" relationship to "Target.object" on the "Source.object" has "FieldA"="Field A Value" and "FieldB"="Field B Value"

* ``Given /^the site\s?config has (("([^"]*)"="([^"]*)"( and "([^"]*)"="([^"]*)")*))$/``

Sets the specified fields or relationships on the site config

* ``Given /^the "([^"]*)" has (("([^"]*)"="([^"]*)"( and "([^"]*)"="([^"]*)")*))$/``
  
  Changes the given fields on the specified fixture


### Custom Page Interaction Steps
* ``When /^I click the "([^"]*)" element$/``
  
  Clicks on the given element (by css selector)

* ``When /^I click the "([^"]*)" element, confirming the dialog$/``
  
  Clicks on the given element (by css selector) then confirms the next dialog

* ``When /^I press "([^"]*)", confirming the dialog$/``
  
  Presses the given button then confirms the next dialog

* ``When /^I click the on the "([^"]*)" link$/``
  
  Clicks on the link containing the given text

* ``Then /^I should( not |\s*)see a field with the label "([^"]*)"$/``
  
  Checks that a field with the given label does exist and is visible
  If not then this checks that a field with the given label does not exist or is not visible

* ``Then /^I should( not |\s*)see the field "([^"]*)" in the "([^"]*)" element$/``
  
  Similar to the above however it looks for a field with the given label|placeholder|id|name in the given element

* ``Then /^I should( not |\s*)see a field with the label "([^"]*)" in the "([^"]*)" element$/``
  
  Checks that a field with the given label does exist in the given element (by CSS selector) and is visible
  If not then this checks that a field with the given label does not exist in the given element (by CSS selector) or is not visible

* ``When /^I fill in the "([^"]*)" auto complete field with "([^"]*)", and select the suggestion option "([^"]*)"$/``
  
  Fills in the given auto complete field (by label|placeholder|id|name) with the given search term then selects the given option

* ``Then /^I wait for the loading to finish$/``
  
  Waits for any Ajax requests to finish

* ``Then /^I switch to the cms popup$/``
  
  Switches to the most recently added CMS popup's iframe

* ``Then /^I switch back from the popup$/``
  
  Switches back to the main window after switching to a popup

* ``Then /^I close the cms popup$/``
  
  Closes the most recently added CMS popup

* ``Then /^I should( not |\s*)see an item edit form$/``
  
  Checks for a GridField Item Edit Form
  If not then this checks that there is not a GridField Item Edit Form

* ``Then /^I should( not |\s*)see a cms popup$/``
  
  Checks to see if there is a CMS popup visible
  If not then this checks that there is not a CMS popup visible

* ``Then /^I wait for the cms popup to load$/``
  
  Waits for the CMS popup to finish loading

* ``Then /^I navigate to the cms popup's page$/``
  
  Navigates to the active CMS popup's source URL

* ``Then /^I should( not |\s*)see a grid field with the label "([^"]*)"$/``
  
  Checks to see if a grid field with the given label exists and is visible
  If not then this checks to see if there is not a grid field with the given label exists or is not visible

* ``Then /^the "(?P<selector>[^"]*)" table should(?P<negative>( not |\s*))contain "(?P<text>[^"]*)" in (?P<rowLocation>(any|(the (new|(((\d+)(st|nd|rd|th))|last|first) editable)))) row in a field in the "(?P<column>[^"]*)" column$/``
  
  Checks to see if the given table and given column contain the given text in an input tag in any, the new, or the nth|first|last editable row
  
* ``Then /^the "(?P<selector>[^"]*)" table should(?P<negative>( not |\s*))have the field checked in (?P<rowLocation>(any|(the (new|(((\d+)(st|nd|rd|th))|last|first) editable)))) row in the "(?P<column>[^"]*)" column$/``
  
  Checks to see if the given table and given column contain have the checkbox checked in any, the new, or the nth|first|last editable row

* ``Then /^the "(?P<selector>[^"]*)" table should(?P<negative>( not |\s*))have "(?P<optionValue>[^"]*)" selected in (?P<rowLocation>(any|(the (new|(((\d+)(st|nd|rd|th))|last|first) editable)))) row in a dropdown in the "(?P<column>[^"]*)" column$/``

  Checks to see if the given table and given column contain have the selected value in the dropdown in any, the new, or the nth|first|last editable row

* ``Then /^the row containing "([^"]*)" in the "([^"]*)" table should( not |\s*)have the class "([^"]*)" on (every|"([^"]*)") column$/``
  
  Checks to see if the given column or every column in the row containing the given text does or does not have the given class

* ``Then /^the "([^"]*)" "([^"]*)" should( not |\s*)be published$/``
  
  Checks to see if the given fixture object is published or not

* ``Then /^the hidden "([^"]*)" field should( not |\s*)contain "([^"]*)"$/``
  
  Checks to see if the hidden field with the given name or id contains (or does not) the given value

* ``Then /^I fill in the hidden field "([^"]*)" with "([^"]*)"$/``
  
  Fills in the hidden field with the given value

* ``Then /^the hidden "([^"]*)" field should( not |\s*)be empty$/``
  
  Checks to see if the hidden field with the given name or id is (or is not) empty

* ``Then /^I should( not |\s*)see "([^"]*)" attached to "([^"]*)"$/``
  
  Checks to see if a filename containing the given text is attached to the upload field with the given label

* ``Then /^I close (the new|all) (tab|window)(s?)$/``
  
  Closes tabs except for the first, if new is specified then only two tabs can be present

* ``Then /^the title of an? (link|element) should( not |\s*)contain "([^"]*)"$/``

  Checks to see if the title attribute or a link or any element contains (or does not contain) the given text

* ``Then /^the title of an? (link|element) should( not |\s*)contain "([^"]*)" in the "([^"]*)" element$/``

  Checks to see if the title attribute or a link or any element in the parent element matching the given selector contains (or does not contain) the given text

* ``Then /^I should see a new window directed to "([^"]*)"$/``
  
  Checks to see if the new window's url matches the given url

* ``Then /^I should( not |\s*)see the field "([^"]*)"$/``
  
  Checks to see if the field with the given id|name|placeholder|label exists (or not) and is visible (or not)

* ``Then /^I (check|uncheck) the "([^"]*)" (checkbox|radio(\s?button)?)$/``
  
  Checks or Unchecks the given checkbox or radio button.

* ``Then /^I should( not |\s*)see a visible "([^"]*)" element$/``
  
  Checks to see if an element with the given selector is or is not present and is or is not visible

* ``Then /^I should( not |\s*)see the (current|next|previous) month and year(( in the format "(?P<format>([^"]*))")?)$/``
  
  Checks to see if the current|next|previous month is or is not found on the page. Optionally the PHP date format to check for can be specified by adding ``in the format "PHP DATE FORMAT CODE"`` to the end of the check.

* ``Then /^I should( not |\s*)see an error$/``
  
  Checks to see if the framework/css/debug.css is loaded on the page, if it is then an error is assumed to be present on the page.

* ``Then /^I select the (first|last|((\d+)(st|nd|rd|th))) option from "(?P<select>[^"]+)"$/``
  
  Selects the first|last|##(st|nd|rd|th) option from the given select, for example:
  
  1. Then I select the 2nd option from "MySelect"
  2. Then I select the first option from "MySelect"
  3. Then I select the last option from "MySelect"
  4. Then I select the 4th option from "MySelect"

* ``Then /^I hover over "([^"]*)"$/``
  
  Hovers over the link or button with the given id|title|alt|rel|text|name

* ``Then /^I should( not |\s*)see a (link|button|link or(( a)?) button) "([^"]*)"$/``
  
  Checks to see if a link or button can be found and is visible with the given id|title|alt|rel|text|name

* ``Then /^I switch to the "([^"]*)" iframe$/``
  
  Switches to the iframe using the given selector

* ``Then /^I switch back from the iframe$/``
  
  Switches back to the main window after switching to an iframe

* ``Given /^I execute the script "([^"]*)"$/``
  
  Allows execution of the given script on the page, this should be used sparingly as the point of behavior tests is to experiences what the user experiences.

* ``Given /^the "([^"]*)" dropdown should( not |\s*)contain the "([^"]*)" option$/``
  
  Checks to see if the given select with the id|name|placeholder|label has (or does not have) the option with the given content|value
* ``Then /^the "([^"]*)" option is selected in the "([^"]*)" dropdown$/``
  
  Checks to see that the given option is selected in the dropdown with the id|name|label

* ``Then /^"([^"]*)" is selected in the "([^"]*)" tree dropdown``
  
  Checks to see the given value is selected in the tree dropdown with the id|name|label

* ``Given /^I press the "([^"]*)" button in the "([^"]*)" grid field$``

  Presses the button with the id|name|label|value in the grid field with the id|name|label|"data name"
  
* ``Given /^I (?P<mode>(fill in|check|uncheck)) "(?P<column>[^"]*)" in the (?P<rowLocation>(new row|(((\d+)(st|nd|rd|th))|last|first) editable row)) in the "(?P<gridFieldLabel>[^"]*)" grid field(( with "(?P<value>[^"]*)")?)$``

  Fills in or checks/unchecks the field in the column with the label|name in the new row|nth/first/last editable row in the editable grid field with the id|name|label|"data name". In the case of filling in `` with "value"`` should be added to the end.
  
* ``Given /^I select "(?P<value>[^"]*)" in "(?P<column>[^"]*)" in the (?P<rowLocation>(new row|(((\d+)(st|nd|rd|th))|last|first) editable row)) in the "(?P<gridFieldLabel>[^"]*)" grid field$/``

  Selects an option from a dropdown or radio button in the column with the label|name in the new row|nth/first/last editable row in the editable grid field with the id|name|label|"data name".

* `Then /^the "([^"]*)" option is selected in the "([^"]*)" radio group$/`

  Checks to see if the option with the given label|value is selected in the radio group with the given id|name|label

* ``When /^I move the (?P<srcRowNumber>(\d+(st|nd|rd|th))) row in the "(?P<gridFieldLabel>[^"]*)" table before the (?P<destRowNumber>(\d+(st|nd|rd|th))) row$/``
  
  Moves the given row number before the other given row number in the given grid field with the id|name|label|"data name", *note this does not check the allow drag and drop for sortable gridfield*.

* ``Given /^I dump the contents of the "([^"]*)" table$/``
  
  Dumps the contents of the given database table to the console

* ``When /^I add a(n?) "(?P<itemType>[^"]*)" to the "(?P<gridFieldLabel>[^"]*)" table$/``
  
  Adds an item of the given type to the given grid field when using GridField item type

* ``Then /^the "(?P<selector>[^"]*)" table should(?P<negative>( not |\s*))contain "(?P<text>[^"]*)" in the (?P<rowLocation>(((\d+)(st|nd|rd|th))|last|first)) row of the table$/``
  
  Checks to see if the given table contains the given text in the given row of the table

* ``Given /^(?:(an|a|the) )"(?<type>[^"]+)" "(?<id>[^"]+)" object is published$/``
  
  Publishes the given object invoking onBeforePublish and onAfterPublish

* ``Then /^I should( not |\s*)see a WYSIWYG popup$/``
  
  Checks to see if a WYSIWG popup is present and visible

* ``When /^I highlight "(?P<text>((?:[^"]|\\")*))" in the "(?P<field>(?:[^"]|\\")*)" HTML field$/``
  
  Highlights the given text in the given html field by name
