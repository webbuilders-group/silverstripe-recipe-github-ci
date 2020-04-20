<?php
namespace WebbuildersGroup\GitHubActionsCIRecipe\Behaviour;

use Behat\TeamCityFormatter\TeamCityFormatterExtension;
use Behat\Testwork\Output\ServiceContainer\OutputExtension;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use WebbuildersGroup\GitHubActionsCIRecipe\Behaviour\Output\Printer\FileOutputPrinter;
use src\WebbuildersGroup\GitHubActionsCIRecipe\Behaviour\Output\Formatter\GitHubAnnotatorFormatter;

class AnnotatorFormatterExtension extends TeamCityFormatterExtension
{
    /**
     * @inheritdoc
     */
    public function getConfigKey()
    {
        return 'github_annotator';
    }
    
    /**
     * @inheritdoc
     */
    public function configure(ArrayNodeDefinition $builder)
    {
        parent::configure($builder);
        
        $builder->children()->scalarNode('filename')->defaultValue('behat.log');
        $builder->children()->scalarNode('outputDir')->defaultValue('artifacts');
    }
    
    /**
     * @inheritdoc
     */
    public function load(ContainerBuilder $container, array $config)
    {
        $outputPrinterDefinition = new Definition(FileOutputPrinter::class, [$config['filename'], $config['outputDir']]);
        
        $definition = new Definition(GitHubAnnotatorFormatter::class, [$outputPrinterDefinition]);
        $definition->addTag(OutputExtension::FORMATTER_TAG, array('priority' => 90));
        
        $container->setDefinition(OutputExtension::FORMATTER_TAG . '.github_annotator', $definition);
    }
}
