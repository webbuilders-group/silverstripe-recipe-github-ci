Behat Testing
========================
_This documentation covers the Behat testing (behavior testing) for the site. It covers some of how the user experience is tested or should be tested, it does not however cover everything and nothing beats a user doing it._


### Getting Started
#### Writing Tests
It's recommended you use [Github's Atom](http://atom.io/) for editing feature/Gherkin files. To do this you will need to install the [language-gerhkin](https://atom.io/packages/language-gherkin) package into Atom. As for the yaml fixtures, the language-yaml package is pre-installed with Atom.


#### Installing
To enable the Behat testing support you must make sure you install the site without using the ``--no-dev`` argument. This will include all of the dependencies required for Behat testing. However you must still download [Chrome Driver](https://sites.google.com/a/chromium.org/chromedriver/downloads) this can be placed anywhere you want however this document assumes it's in the root of the site though it does not need to be.


#### Starting Chrome Driver
You can start Chrome Driver by using the following command. If you place this file in the root of the install of the site and name it ``start-chromedriver.bat`` it will be ignored from the git index.

```
/path/to/chromedriver.exe
```

You could turn this start line into a batch file using the below:
```
@echo off
/path/to/chromedriver.exe
```


### Running the Tests
Before running any tests you must set the ``WBG_BEHAT_BASE_URL`` environment variable so Behat knows where the site's base url is, alternatively you can set the ``SS_BASE_URL`` in your site's ``.env`` file.
    __Windows:__
    ```
    set WBG_BEHAT_BASE_URL=http://localhost/path-to-root
    ```

    __Windows PowerShell:__
    ```
    $env:WBG_BEHAT_BASE_URL = "http://localhost/path-to-root"
    ```

    __Linux/Mac:__
    ```
    export WBG_BEHAT_BASE_URL="http://localhost/path-to-root"
    ```

Once you have installed things properly you can how run the tests by using one of the following commands from the site root. Note that the ``@app`` is the site root folder for the test. If you are focusing the test on a specific feature then you need to add any sub-folders under the ``tests/behat/features/`` folder before the feature's filename.

__All mysite Tests:__
```
"vendor/bin/behat" @app
```

__Specific mysite Test by it's Filename:__
```
"vendor/bin/behat" @app ./app/tests/behat/sub/folder/path/login.feature
```

__Single Scenario:__
*Note you can also focus this down to a single feature file as well by following the example above but using the name argument.*
```
"vendor/bin/behat" @app ./app/tests/behat/sub/folder/path/login.feature --name "My scenario title"
```

__Re-running Failed Tests:__
To rerun only failed tests simply add the ``--rerun`` argument to the command each time you run Behat, for example:
```
"vendor/bin/behat" @mysite --rerun
```
When a test fails this will put a folder ``artifacts/behat-rerun`` that will contain the failed tests that Behat can then use to rerun any failed tests in your next run.


#### Running on Windows
For Windows it's highly recommended you used a console emulator such as [Cmder](http://cmder.net/) otherwise you will not get highlighting of text which with Behat makes it much easier to find successful, failed and skipped steps. If you are running in PowerShell make sure you add ``& `` before the commands to run Behat or PowerShell will throw an error.


### Setting a Specific Resolution
By default the tests run at a fairly small screen resolution, you can change this by setting an environment variable to the resolution you want before launching Behat. For example:
__Windows:__
```
set BEHAT_SCREEN_SIZE=1600x900
```

__Windows PowerShell:__
```
$env:BEHAT_SCREEN_SIZE = "1600x900"
```

__Linux/Mac:__
```
export BEHAT_SCREEN_SIZE="1600x900"
```

### Behat Profiles
There are two profiles for Behat tests, [profiles](http://behat.org/en/latest/user_guide/configuration.html) can be added to the Behat command using ``--profile={name}``:
* __default:__ This is the default profile, for most instances this is what you need to use when testing locally. It will use ``http://localhost/{current-working-directory}`` as the base url in the browser.
* __ci:__ This is the profile used for GitHub Actions, and it will use ``http://localhost:8000`` as the base url in the browser. *Warning: Do not use this profile on Windows the Chrome processes will not exit properly and will start using 25-30% of the processor each.*


### Setting the Base URL
The base url for Behat tests must be set in one of two ways, either use the ``WBG_BEHAT_BASE_URL`` mentioned below or use ``SS_BASE_URL`` in your install's ``.env`` file. Failing to do so will result in an error.

__Windows:__
```
set WBG_BEHAT_BASE_URL=http://localhost/path-to-root
```

__Windows PowerShell:__
```
$env:WBG_BEHAT_BASE_URL = "http://localhost/path-to-root"
```

__Linux/Mac:__
```
export WBG_BEHAT_BASE_URL="http://localhost/path-to-root"
```

### Testing on Linux
Due to the way file ownership and permissions works on Linux you are in some cases better off using ``silverstipe/serve`` to run your tests in when assets are concerned. To do this run the below command, and make sure your base url includes the port specified in the ``--port`` argument:

```
vendor/bin/serve --bootstrap=app/utils/behat/serve-bootstrap.php --port=8000 &> artifacts/serve.log &
```


### Additional Information
For additional information such as how to write tests and for tutorials see the [silverstripe/behat-extensions README.md](https://github.com/silverstripe/silverstripe-behat-extension/blob/4.0/README.md) README as well see the documentation for [Behat](http://behat.org/en/latest/quick_start.html). Also when a test fails a screenshot will be created in a folder called ``artifacts`` in the root of the site. As well when a Behat test is running the ``BEHAT_TEST_SESSION`` constant is created and set to true, this should be looked for sparingly as we want the Behat tests to closely match the user's experience however in some instances such as working with a live API that we don't actually want to do so we can look for this constant and "pretend" the API worked.


### Behat feature file template
```gherkin
# features/path/to/name-of.feature
Feature: Test Name
    Test Description
    
    Background:
        #Background/Pre-Test Tasks
        #Given I use the fixtures defined in "@app/path/to/name-of.yml"
    
    
    @javascript
    Scenario: Scenario Name
        #Scenario Tests
```


### Custom Scenario Tags
Scenario tags are added before your scenario definition.

* ``@analytics``
  
  Enables the analytics override so that you can test for analytics events
  
* ``@desktop``
  
  Resizes the browser to desktop width/height (1280x800), browser is reset after the scenario
  
* ``@tablet``
  
  Resizes the browser to tablet width/height (768x1024), browser is reset after the scenario
  
* ``@mobile``
  
  Resizes the browser to mobile width/height (320x568), browser is reset after the scenario
  
  
### Custom Fixture Steps
* ``Given /^I use the fixtures defined in "([^"]*)"$/``
  
  Uses the given path to a YAML as a fixture definition, if the given path begins with an @ symbol then the first part of the path is assumed to be the base folder for the path. For example "@app/grid-panel/grid-panel.yml" would resolve to app/tests/behat/features/grid-panel/grid-panel.yml. If the path does not begin with an @ symbol the path is relative to the mysite features folder. Note that even if you specify the base folder it's always relative to that folder's tests/behat/features/ folder.

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

* ``Then /^I switch to the cms modal popup$/``
  
  Switches to the most recently added CMS modal popup's iframe

* ``Then /^I switch back from the popup$/``
  
  Switches back to the main window after switching to a popup

* ``Then /^I close the cms popup$/``
  
  Closes the most recently added CMS popup

* ``Then /^I close the cms modal popup$/``
  
  Closes the most recently added CMS modal popup

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

* ``Then /^I should( not |\s*)see a popup$/``
  
  Checks to see if there is a popup visible or not

* ``Then /^I switch to the popup$/``
  
  Switches to the most recently added front-end popup's iframe

* ``Then /^I navigate to the popup's page$/``
  
  Navigates to the active popup's source url

* ``Then /^I switch to the (\d+)(st|nd|rd|th) form panel's frame$/``
  
  Switches to the frame in the form panel with the given index (starting with 1)

* ``Then /^I switch back from the form panel's frame$/``
  
  Switches back from the frame in a form panel

* ``Then /^I close the popup$/``
  
  Closes the currently opened popup

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

* ``Then /^I switch to the "([^"]*)" frame by selector$/``
  
  Switches to the iframe using the given selector

* ``Then /^I switch back from the iframe$/``
  
  Switches back to the main window after switching to an iframe

* ``Given /^I execute the script "([^"]*)"$/``
  
  Allows execution of the given script on the page, this should be used sparingly as the point of behavior tests is to experiences what the user experiences.

* ``Given /^the "([^"]*)" dropdown should( not |\s*)contain the "([^"]*)" option$/``
  
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

* ``When /^I select the color "([^"]*)" from the "([^"]*)" palette$/``

  Selects the given color (value|css) from the given color pallete field by name|id|label

* ``Given /^I the color "([^"]*)" from the "([^"]*)" palette is selected$/``

  Checks to see if the given color in the givem color pallete field is seleted

* ``Given /^I take a screenshot$/``
  
  Takes a screenshot, the file is placed in the ``artifacts/screenshots`` folder and is named ``{feature}_{step_line}.png``

* ``Given /^I attach the file "([^"]*)" to file upload "([^"]*)"$/``
  
  Attaches the given file to the upload field by name|id|label

* ``Given /^I fill in the "(?P<selector>[^"]*)" (date time|date|time) field with "(?P<value>[^"]*)"$/``
  
  Sets the date, time, or datetime-local field by id|name|placeholder|label with the given date/time

* ``Given /^the "(?P<selector>[^"]*)" (date time|date|time) field should(?P<negative>( not |\s*)) contain "(?P<value>[^"]*)"$/``
  
  Verifies the date, time, or datetime-local field by id|name|placeholder|label contains (or does not contain) the given date/time

* ``Given /^I click the "([^"]*)" HTML field menu item$/``
  
  Clicks the given item in the WYSIWYG menu

* ``Given /^I click the "([^"]*)" HTML field popup menu item$/``
  
  Clicks the given item in the WYSIWYG popup toolbar

* ``Given /^I right click in the "([^"]*)" HTML field$/``
  
  Right clicks in the given html editor field

* ``Given /^I hover over the "([^"]*)" HTML field menu item$/``
  
  Hovers over the html editor field menu item

* ``Given /^I should( not |\s*)see a popup form$/``
  
  Checks to see if a popup form built by the form builder is visible or not

* ``Given /^I close the popup form$/``
  
  Closes the currently open popup form built with the form builder

* ``Given /^I (expand|collapse) the "([^"]*)" category in the icon picker$/``
  
  Expands or Collapses the given category in the icon picker

* ``Given /^I should( not |\s*)see the "([^"]*)" icon in the picker$/``
  
  Checks to see if the given icon by label is visible in the icon picker

* ``Given /^I select the "([^"]*)" icon from the picker$/``
  
  Selects the given icon from the icon picker

* ``Given /^I view the campaign "([^"]*)"$/``
  
  Selects the given campaign from the campaign admin's list

* ``Given /^I should( not? |\s*)see the "([^"]+)" campaign item$/``
  
  Checks to see if the campaign item with the given label is or is not visible

* ``Given /^I select the "([^"]+)" campaign item$/``
  
  Selects the given campaign item by label

* ``Given /^I should see a modal titled "([^"]+)"$/``
  
  Checks to see if a model exists in the cms with the given title

* ``Given /^I select the campaign "([^"]+)" in the add to campaign modal$/``
  
  Selects the given campaign option from the available campaigns dropdown in the campaign modal

* ``Then /^the (?P<rowLocation>(((\d+)(st|nd|rd|th))|last|first)) row in the "(?P<selector>[^"]*)" table should(?P<negative>( not |\s*))have the class "(?P<className>[^"]*)"$/``
  
  Checks to see if the given row in the given table has (or does not have) the given class

* ``When /^I fill in the proportion constrained field "(?P<field>(?:[^"]|\\")*)" with "(?P<value>(?:[^"]|\\")*)"$/``
  
  Fills in the given proportion constrained field with the given value
