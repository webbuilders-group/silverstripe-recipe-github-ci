<?php
namespace WebbuildersGroup\GitHubActionsCIRecipe\Behaviour;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Convert;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\FixtureBlueprint;
use SilverStripe\Dev\YamlFixture;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\DataObject;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\Versioned\Versioned;

class FixtureContext extends \SilverStripe\CMS\Tests\Behaviour\FixtureContext
{
    /**
     * FixtureContext constructor.
     * @param null $filesPath
     */
    public function __construct()
    {
        parent::__construct(dirname(__FILE__) . '/../behat/files/');
    }
    
    /**
     * Build default fixture factory
     * @return FixtureFactory
     */
    protected function scaffoldDefaultFixtureFactory()
    {
        $factory = parent::scaffoldDefaultFixtureFactory();
        
        // Fix issue on windows where the url segment for objects that specify one would not get the correct url segment
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            foreach (ClassInfo::subclassesFor(SiteTree::class) as $class) {
                $blueprint = Injector::inst()->create(FixtureBlueprint::class, $class);
                $blueprint->addCallback('afterCreate', function ($obj, $identifier, &$data, &$fixtures) {
                    if (array_key_exists('URLSegment', $data) && $obj->URLSegment != $data['URLSegment']) {
                        $obj->URLSegment = $data['URLSegment'];
                        $obj->write();
                    } else if ($obj->URLSegment != ($generatedUrlSegment = $obj->generateURLSegment(($obj->MenuTitle ? $obj->MenuTitle : $obj->Title)))) {
                        $obj->URLSegment = $generatedUrlSegment;
                        $obj->write();
                    }
                    
                    // Publish the page
                    $obj->publishRecursive();
                });
                $factory->define($class, $blueprint);
            }
        }
        
        return $factory;
    }
    
    /**
     * @Given /^I use the fixtures defined in "([^"]*)"$/
     */
    public function iUseTheFixtureFile($path)
    {
        //Figure out the base path
        if (preg_match('/^@(\w+)/', $path) == true) {
            $base = preg_replace('/^\@(\w+)\/([^"]*)\.yml$/', '$1', $path);
            $base = str_replace(['../', './'], '', $base);
            $base = realpath(dirname(__FILE__) . '/../../../') . '/' . $base . '/tests/behat/features/';
        } else {
            $base = realpath(dirname(__FILE__) . '/../behat/features') . '/';
        }
        
        //Verify the base does exist and is a directory
        if (!file_exists($base) || !is_dir($base)) {
            throw new \InvalidArgumentException(sprintf('Base folder "%s" does not exist or is not a directory', $base));
        }
        
        
        //Build the absolute path to the fixture file
        $fixturePath = $base . preg_replace('/^\@(\w+)\/([^"]*)\.yml$/', '$2', $path) . '.yml';
        
        //Verify the fixture file exists and is a file
        if (!file_exists($fixturePath) || !is_file($fixturePath)) {
            throw new \InvalidArgumentException(sprintf('Fixture file "%s" does not exist or is not a file', $fixturePath));
        }
        
        $oldMode = Versioned::get_reading_mode();
        Versioned::set_stage(Versioned::DRAFT);
        
        //Load in the Yaml Fixture
        $yamlFixture = new YamlFixture(file_get_contents($fixturePath));
        $yamlFixture->writeInto($this->getFixtureFactory());
        
        Versioned::set_reading_mode($oldMode);
    }
    
    /**
     * @Given /^the "([^"]*)" relationship to "([^"]*)" on the "([^"]*)" object has (("([^"]*)"="([^"]*)"( and "([^"]*)"="([^"]*)")*))$/
     */
    public function manyManyExtraSetter($relationName, $targetName, $sourceName, $args)
    {
        //Reset Versioned
        $oldMode = Versioned::get_reading_mode();
        Versioned::set_stage(Versioned::DRAFT);
        
        
        //Get the fixture factory
        $factory = $this->getFixtureFactory();
        
        //Get schema
        $schema = DataObject::getSchema();
        
        //Validate the target fixture's name
        $targetFixture = explode('.', $targetName);
        if (count($targetFixture) != 2) {
            throw new \InvalidArgumentException(sprintf('Target Object\'s fixture name "%s" is not in the format ClassName.identifier', $targetName));
        }
        
        //Validate the source fixture's name
        $sourceFixture = explode('.', $sourceName);
        if (count($sourceFixture) != 2) {
            throw new \InvalidArgumentException(sprintf('Source Object\'s fixture name "%s" is not in the format ClassName.identifier', $sourceName));
        }
        
        //Check to see if we can find the target fixture
        $targetObject = $factory->get($targetFixture[0], $targetFixture[1]);
        if (empty($targetObject) || $targetObject === false || !$targetObject->exists()) {
            throw new \Exception(sprintf('Could not find the fixture ""%s"', $targetName));
        }
        
        //Check to see if we can find the source fixture
        $sourceObject = $factory->get($sourceFixture[0], $sourceFixture[1]);
        if (empty($sourceObject) || $sourceObject === false || !$sourceObject->exists()) {
            throw new \Exception(sprintf('Could not find the fixture ""%s"', $sourceName));
        }
        
        //Try to find the many_many relationship
        $manyManyDef = $schema->manyManyComponent($sourceObject->ClassName, $relationName);
        if (empty($manyManyDef)) {
            throw new \InvalidArgumentException(sprintf('Could not find the many_many relationship "%s" on the source object "%s"', $relationName, $sourceName));
        }
        
        //Try to find the many_many_extraFields for the relationship
        $manyManyExtras = $schema->manyManyExtraFieldsForComponent($sourceObject->ClassName, $relationName);
        if (empty($manyManyExtras)) {
            throw new \InvalidArgumentException(sprintf('There are no many_many extra fields for the relationship "%s" on the source object "%s"', $relationName, $sourceName));
        }
        
        //Parse fields to set into a associative array
        $tmp = [];
        $fieldsToSet = [];
        if (preg_match_all('/"([^"]*)"="([^"]*)"/', $args, $tmp) == true) {
            if (count($tmp) > 0 && count($tmp[0]) > 0) {
                for ($i = 0; $i < count($tmp[0]); $i++) {
                    $param = [];
                    if (preg_match('/"([^"]*)"="([^"]*)"/', $tmp[0][$i], $param) == true) {
                        $fieldsToSet[$param[1]] = $param[2];
                    }
                }
            }
        }
        
        if (empty($fieldsToSet)) {
            throw new \Exception('Could not parse the fields to set');
        }
        
        
        //Set the fields
        $query = 'UPDATE "' . $manyManyDef['join'] . '" SET ';
        $values = [];
        foreach ($fieldsToSet as $field => $value) {
            if (array_key_exists($field, $manyManyExtras)) {
                $query .= '"' . $field . '"=?, ';
                $values[] = $value;
            } else {
                throw new \InvalidArgumentException(sprintf('Could not find the field "%s" in the extra fields for the relationship "%s"', $field, $relationName));
            }
        }
        
        //Clean up the query and add the where condition
        $query = trim(trim($query), ',') . ' WHERE "' . $manyManyDef['parentField'] . '"=? AND "' . $manyManyDef['childField'] . '"=?';
        $values[] = $targetObject->ID;
        $values[] = $sourceObject->ID;
        
        //Update the row in the database
        DB::prepared_query($query, $values);
        
        Versioned::set_reading_mode($oldMode);
    }
    
    /**
     * @Given /^the site\s?config has (("([^"]*)"="([^"]*)"( and "([^"]*)"="([^"]*)")*))$/
     */
    public function updateSiteConfig($data)
    {
        //Reset Versioned
        $oldMode = Versioned::get_reading_mode();
        Versioned::set_stage(Versioned::DRAFT);
        
        
        $siteConfig = SiteConfig::current_site_config();
        if (empty($siteConfig) || $siteConfig === false || !$siteConfig->exists()) {
            throw new \Exception('Could not find the active SiteConfig');
        }
        
        $matches = [];
        preg_match_all('/"(?<key>[^"]+)"\s*=\s*"(?<value>[^"]+)"/', $data, $matches);
        
        $fields = $this->convertFields(SiteConfig::class, array_combine($matches['key'], $matches['value']));
        
        
        //Get schema
        $schema = DataObject::getSchema();
        
        
        $fixtures = $this->getFixtureFactory()->getFixtures();
        
        
        //Merge existing data with new data, and create new object to replace existing object
        foreach ($fields as $fieldName => $fieldVal) {
            if ($schema->manyManyComponent(SiteConfig::class, $fieldName) || $schema->hasManyComponent(SiteConfig::class, $fieldName)) {
                $parsedItems = [];
                
                if (is_array($fieldVal)) {
                    // handle lists of many_many relations. Each item can
                    // specify the many_many_extraFields against each
                    // related item.
                    foreach ($fieldVal as $relVal) {
                        $item = key($relVal);
                        $id = $this->parseValue($item, $fixtures);
                        $parsedItems[] = $id;
                        
                        array_shift($relVal);
                        
                        $siteConfig->getManyManyComponents($fieldName)->add($id, $relVal);
                    }
                } else {
                    $items = preg_split('/ *, */', trim($fieldVal));
                    foreach ($items as $item) {
                        // Check for correct format: =><relationname>.<identifier>.
                        // Ignore if the item has already been replaced with a numeric DB identifier
                        if (!is_numeric($item) && !preg_match('/^=>[^\.]+\.[^\.]+/', $item)) {
                            throw new \InvalidArgumentException(sprintf('Invalid format for relation "%s" on class "SiteConfig" ("%s")', $fieldName, $item));
                        }
                        
                        $parsedItems[] = $this->parseValue($item, $fixtures);
                    }
                    
                    if ($schema->hasManyComponent(SiteConfig::class, $fieldName)) {
                        $siteConfig->getComponents($fieldName)->setByIDList($parsedItems);
                    } else if ($schema->manyManyComponent(SiteConfig::class, $fieldName)) {
                        $siteConfig->getManyManyComponents($fieldName)->setByIDList($parsedItems);
                    } else {
                        throw new \InvalidArgumentException(sprintf('Could not find a has_one, has_many or many_many relation "%s" on class "SiteConfig"', $fieldName));
                    }
                }
            } else {
                $hasOneField = preg_replace('/ID$/', '', $fieldName);
                if ($className = $schema->hasOneComponent(SiteConfig::class, $hasOneField)) {
                    $siteConfig->{$hasOneField . 'ID'} = $this->parseValue($fieldVal, $fixtures, $className);
                    // Inject class for polymorphic relation
                    if ($className === DataObject::class) {
                        $siteConfig->{$hasOneField . 'Class'} = $className;
                    }
                } else {
                    $siteConfig->$fieldName = $fieldVal;
                }
            }
        }
        
        $siteConfig->write();
        
        Versioned::set_reading_mode($oldMode);
    }
    
    /**
     * @Given /^the "([^"]*)" has (("([^"]*)"="([^"]*)"( and "([^"]*)"="([^"]*)")*))$/
     */
    public function updateObject($fixtureName, $data)
    {
        $factory = $this->getFixtureFactory();
        $oldStage = Versioned::get_stage();
        Versioned::set_stage(Versioned::DRAFT);
        
        
        //Validate the source fixture's name
        $sourceFixture = explode('.', $fixtureName);
        if (count($sourceFixture) != 2) {
            throw new \InvalidArgumentException(sprintf('Object\'s fixture name "%s" is not in the format ClassName.identifier', $fixtureName));
        }
        
        //Check to see if we can find the source fixture
        $sourceObject = $factory->get($sourceFixture[0], $sourceFixture[1]);
        if (empty($sourceObject) || $sourceObject === false || !$sourceObject->exists()) {
            throw new \Exception(sprintf('Could not find the fixture ""%s"', $fixtureName));
        }
        
        $matches = [];
        preg_match_all('/"(?<key>[^"]+)"\s*=\s*"(?<value>[^"]+)"/', $data, $matches);
        
        $fields = $this->convertFields($sourceFixture[0], array_combine($matches['key'], $matches['value']));
        
        
        $fixtures = $factory->getFixtures();
        
        
        //Merge existing data with new data, and create new object to replace existing object
        foreach ($fields as $fieldName => $fieldVal) {
            if ($sourceObject->manyManyComponent($fieldName) || $sourceObject->hasManyComponent($fieldName)) {
                $parsedItems = [];
                
                if (is_array($fieldVal)) {
                    // handle lists of many_many relations. Each item can
                    // specify the many_many_extraFields against each
                    // related item.
                    foreach ($fieldVal as $relVal) {
                        $item = key($relVal);
                        $id = $this->parseValue($item, $fixtures);
                        $parsedItems[] = $id;
                        
                        array_shift($relVal);
                        
                        $sourceObject->getManyManyComponents($fieldName)->add($id, $relVal);
                    }
                } else {
                    $items = preg_split('/ *, */', trim($fieldVal));
                    foreach ($items as $item) {
                        // Check for correct format: =><relationname>.<identifier>.
                        // Ignore if the item has already been replaced with a numeric DB identifier
                        if (!is_numeric($item) && !preg_match('/^=>[^\.]+\.[^\.]+/', $item)) {
                            throw new \InvalidArgumentException(sprintf('Invalid format for relation "%s" on class "%s" ("%s")', $fieldName, get_class($sourceObject), $item));
                        }
                        
                        $parsedItems[] = $this->parseValue($item, $fixtures);
                    }
                    
                    if ($sourceObject->hasManyComponent($fieldName)) {
                        $sourceObject->getComponents($fieldName)->setByIDList($parsedItems);
                    } else if ($sourceObject->manyManyComponent($fieldName)) {
                        $sourceObject->getManyManyComponents($fieldName)->setByIDList($parsedItems);
                    } else {
                        throw new \InvalidArgumentException(sprintf('Could not find a has_many or many_many relation "%s" on class "%s"', $fieldName, get_class($sourceObject)));
                    }
                }
            } else {
                $hasOneField = preg_replace('/ID$/', '', $fieldName);
                if ($className = $sourceObject->hasOneComponent($hasOneField)) {
                    $sourceObject->{$hasOneField . 'ID'} = $this->parseValue($fieldVal, $fixtures, $className);
                    // Inject class for polymorphic relation
                    if ($className === DataObject::class) {
                        $sourceObject->{$hasOneField . 'Class'} = $className;
                    }
                } else {
                    $sourceObject->$fieldName = $fieldVal;
                }
            }
        }
        
        $sourceObject->write();
        
        Versioned::set_stage($oldStage);
    }
    
    /**
     * @Given /^I dump the contents of the "([^"]*)" table$/
     */
    public function iDumpTheTable($table)
    {
        if (!DB::get_conn()->getSchemaManager()->hasTable($table)) {
            throw new \InvalidArgumentException('Table "' . $table . '" does not exist');
        }
        
        $result = DB::query('SELECT * FROM ' . Convert::symbol2sql($table));
        print "\n\n" . $table . "\n-------------\n";
        
        $columns = array_keys(DB::get_conn()->getSchemaManager()->fieldList($table));
        print '| ' . implode(' | ', $columns) . " |\n-------------\n";
        
        if ($result->numRecords() == 0) {
            print 'No Records';
        } else {
            foreach ($result as $row) {
                print '| ' . implode(' | ', $row) . " |\n";
            }
        }
        
        print "\n-------------\n\n";
    }
    
    /**
     * @Given /^(?:(an|a|the) )"(?<type>[^"]+)" "(?<id>[^"]+)" object is published$/
     */
    public function iPublishObject($type, $id)
    {
        //Reset Versioned
        $oldMode = Versioned::get_reading_mode();
        Versioned::set_stage(Versioned::DRAFT);
        
        
        $fixtureFactory = $this->getFixtureFactory();
        
        $class = $this->convertTypeToClass($type);
        $obj = $fixtureFactory->get($class, $id);
        if (!$obj) {
            throw new \InvalidArgumentException(sprintf('Can not find record "%s" with identifier "%s"', $type, $id));
        }
        
        
        //Invoke before publish extensions
        $obj->invokeWithExtensions('onBeforePublish', $obj);
        
        //Publish using versioned's publish method
        $obj->publishRecursive();
        
        //Invoke after publish extensions
        $obj->invokeWithExtensions('onAfterPublish', $obj);
        
        Versioned::set_reading_mode($oldMode);
    }
    
    /**
     * @Then /^the "([^"]*)" "([^"]*)" should( not |\s*)be published$/
     */
    public function theContentShouldBePublished($type, $id, $negative)
    {
        $class = $this->convertTypeToClass($type);
        $obj = $this->getFixtureFactory()->get($class, $id);
        if (!$obj) {
            throw new \InvalidArgumentException(sprintf('Can not find record "%s" with identifier "%s"', $type, $id));
        }
        
        if (!$obj->hasMethod('isPublished')) {
            throw new \InvalidArgumentException(sprintf('Record "%s" with identifier "%s" does not have an isPublished method', $type, $id));
        }
        
        if (trim($negative)) {
            assertFalse($obj->isPublished(), sprintf('Record "%s" with identifier "%s" was published', $type, $id));
        } else {
            assertTrue($obj->isPublished(), sprintf('Record "%s" with identifier "%s" was not published', $type, $id));
        }
    }
    
    /**
     * @Given /^I attach the file "([^"]*)" to file upload "([^"]*)"$/
     */
    public function iAttachTheFileToUploadField($path, $selector)
    {
        // Get path
        $filesPath = $this->getFilesPath();
        if ($filesPath) {
            $fullPath = rtrim(realpath($filesPath), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $path;
            if (is_file($fullPath)) {
                $path = $fullPath;
            }
        }
        
        assertFileExists($path, "$path does not exist");
        
        /** @var DocumentElement $page */
        $page = $this->getMainContext()->getSession()->getPage();
        $input = $page->findField($selector);
        assertNotNull($input, "Could not find {$selector}");
        
        // Make visible temporarily while attaching
        $this->getMainContext()->getSession()->executeScript("window.jQuery('#" . $input->getAttribute('id') . "').show().width(1).height(1)");
        
        // Attach via html5
        $input->attachFile($path);
        
        //Trigger change event
        $this->getMainContext()->getSession()->executeScript('(function() {' .
                                                                'var fileField=document.getElementById("' . $input->getAttribute('id') . '");' .
                                                                'var event=new Event("drop");' .
                                                                'event.dataTransfer={files: [fileField.files[fileField.files.length-1]]};' .
                                                                'document.querySelector("#' . $input->getAttribute('id') . '_Holder .uploadfield__dropzone").dispatchEvent(event);' .
                                                            '})()');
        
        //Wait for the upload to finish, if the .uploadfield-item__upload-progress exists then it hasn't uploaded
        $this->getMainContext()->getSession()->wait($this->getMainContext()->getAjaxTimeout() * 2, 'document.querySelector("#' . $input->getAttribute('id') . '_Holder .uploadfield-item__upload-progress")!=null');
    }
    
    /**
     * Converts a natural language class description to an actual class name. Respects {@link DataObject::$singular_name} variations.
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

        throw new \InvalidArgumentException(sprintf('Class "%s" does not exist, or is not a subclass of DataObjet', $class));
    }
    
    /**
     * Updates an object with values, resolving aliases set through {@link DataObject->fieldLabels()}.
     * @param string $class Class name
     * @param array $fields Map of field names or aliases to their values.
     * @return array Map of actual object properties to their values.
     */
    protected function convertFields($class, $fields)
    {
        $labels = singleton($class)->fieldLabels();
        foreach ($fields as $fieldName => $fieldVal) {
            if ($fieldLabelKey = array_search($fieldName, $labels)) {
                unset($fields[$fieldName]);
                $fields[$labels[$fieldLabelKey]] = $fieldVal;
            }
        }
        
        return $fields;
    }
    
    /**
     * Parse a value from a fixture file. If it starts with => it will get an ID from the fixture dictionary
     * @param string $fieldVal
     * @param array $fixtures See {@link createObject()}
     * @param string $class If the value parsed is a class relation, this parameter
     * will be given the value of that class's name
     * @return string Fixture database ID, or the original value
     */
    protected function parseValue($value, $fixtures = null, &$class = null)
    {
        if (substr($value, 0, 2) == '=>') {
            // Parse a dictionary reference - used to set foreign keys
            list($class, $identifier) = explode('.', substr($value, 2), 2);
            
            if ($fixtures && !isset($fixtures[$class][$identifier])) {
                throw new \InvalidArgumentException(sprintf('No fixture definitions found for "%s"', $value));
            }
            
            return $fixtures[$class][$identifier];
        } else {
            // Regular field value setting
            return $value;
        }
    }
}
