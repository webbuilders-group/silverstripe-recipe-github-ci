<?php
namespace WebbuildersGroup\GitHubActionsCIRecipe\Behaviour;

use SilverStripe\BehatExtension\MinkExtension as SS_MinkExtension;
use SilverStripe\Core\Environment;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use InvalidArgumentException;

class MinkExtension extends SS_MinkExtension
{
    public function process(ContainerBuilder $container)
    {
        if (!Environment::getEnv('SS_BASE_URL')) {
            $baseURL = $container->getParameter('mink.base_url');
            if (empty($baseURL) && array_key_exists('WBG_BEHAT_BASE_URL', $_SERVER)) {
                //If not present guess the url
                $baseURL = $_SERVER['WBG_BEHAT_BASE_URL'];
            } else {
                throw new InvalidArgumentException('"base_url" not configured. Please specify it in the WBG_BEHAT_BASE_URL environment variable or set the SS_BASE_URL in your .env file.');
            }
            
            Environment::setEnv('SS_BASE_URL', $baseURL);
        }
        
        parent::process($container);
    }
}
