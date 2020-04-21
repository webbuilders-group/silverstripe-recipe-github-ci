<?php
namespace WebbuildersGroup\GitHubActionsCIRecipe\Behaviour\Output\Printer;

use Behat\Testwork\Output\Printer\OutputPrinter;
use Symfony\Component\Console\Output\OutputInterface;

class ConsoleOutput implements OutputPrinter
{
    /**
     * @var OutputInterface
     */
    private $output;
    
    /**
     * @var string
     */
    private $path;
    
    /**
     * @var array
     */
    private $styles;
    
    public function __construct(OutputInterface $output)
    {
        $this->output = $output;
    }
    
    /**
     * Sets output path.
     * @param string $path
     */
    public function setOutputPath($path)
    {
        $this->path = $path;
    }
    
    /**
     * Returns output path.
     * @return string|null
     * @deprecated
     */
    public function getOutputPath()
    {
        return $this->path;
    }
    
    /**
     * Sets output styles.
     * @param array $styles
     */
    public function setOutputStyles(array $styles)
    {
        $this->styles = $styles;
    }
    
    /**
     * Returns output styles.
     * @return array
     * @deprecated
     */
    public function getOutputStyles()
    {
        return $this->styles;
    }
    
    /**
     * Forces output to be decorated.
     * @param bool $decorated
     */
    public function setOutputDecorated($decorated)
    {
        $this->output->setDecorated($decorated);
    }
    
    /**
     * Returns output decoration status.
     * @return null|bool
     * @deprecated
     */
    public function isOutputDecorated()
    {
        return $this->output->isDecorated();
    }
    
    /**
     * Sets output verbosity level.
     * @param int $level
     */
    public function setOutputVerbosity($level)
    {
        $this->output->setVerbosity($level);
    }
    
    /**
     * Returns output verbosity level.
     * @return int
     * @deprecated
     */
    public function getOutputVerbosity()
    {
        return $this->output->getVerbosity();
    }
    
    /**
     * Writes message(s) to output stream.
     * @param string|array $messages message or array of messages
     */
    public function write($messages)
    {
        $this->output->write($messages);
    }
    
    /**
     * Writes newlined message(s) to output stream.
     * @param string|array $messages message or array of messages
     */
    public function writeln($messages = '')
    {
        $this->output->writeln($messages);
    }
    
    /**
     * Clear output stream, so on next write formatter will need to init (create) it again.
     */
    public function flush()
    {
    }
}