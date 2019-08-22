<?php

namespace CtiDigital\Configurator\Console\Command;

use CtiDigital\Configurator\Exception\ConfiguratorAdapterException;
use CtiDigital\Configurator\Api\ConfiguratorAdapterInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ConfiguratorCommand extends Command
{
    /**
     * @var ConfiguratorAdapterInterface
     */
    private $configuratorAdapter;

    public function __construct(ConfiguratorAdapterInterface $configuratorAdapter)
    {
        parent::__construct();
        $this->configuratorAdapter = $configuratorAdapter;
    }

    protected function configure()
    {
        $this->setName('configurator');
        $this->setDescription('List configurator commands');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     * @SuppressWarnings(PHPMD)
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $output->writeln('<comment>To do</comment>');
        } catch (ConfiguratorAdapterException $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
        }
    }
}
