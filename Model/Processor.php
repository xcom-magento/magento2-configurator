<?php

namespace CtiDigital\Configurator\Model;

use CtiDigital\Configurator\Api\LoggerInterface;
use CtiDigital\Configurator\Component\ComponentAbstract;
use CtiDigital\Configurator\Exception\ComponentException;
use CtiDigital\Configurator\Api\ConfigInterface;
use CtiDigital\Configurator\Component\Factory\ComponentFactoryInterface;
use CtiDigital\Configurator\Api\VersioningRepositoryInterface;
use CtiDigital\Configurator\Api\Data\VersioningInterfaceFactory;
use Symfony\Component\Yaml\Parser;
use Magento\Framework\App\State;
use Magento\Framework\App\Area;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotSaveException;

class Processor
{
    /**
     * @var string
     */
    protected $environment;

    /**
     * @var boolean
     */
    protected $force;

    /**
     * @var array
     */
    protected $components = array();

    /**
     * @var array
     */
    protected $versions = array();

    /**
     * @var ConfigInterface
     */
    protected $configInterface;

    /**
     * @var LoggerInterface
     */
    protected $log;

    /**
     * @var State
     */
    protected $state;

    /**
     * @var ComponentFactoryInterface
     */
    protected $componentFactory;

    /**
     * @var VersioningRepositoryInterface
     */
    protected $versioningRepository;

    /**
     * @var VersioningInterfaceFactory
     */
    protected $versioningFactory;

    /**
     * @var SearchCriteriaInterface
     */
    protected $searchCriteria;

    /**
     * Processor constructor.
     * @param ConfigInterface $configInterface
     * @param LoggerInterface $logging
     * @param State $state
     * @param ComponentFactoryInterface $componentFactory
     * @param VersioningRepositoryInterface $versioningRepository
     * @param VersioningInterfaceFactory $versioningFactory
     */
    public function __construct(
        ConfigInterface $configInterface,
        LoggerInterface $logging,
        State $state,
        ComponentFactoryInterface $componentFactory,
        VersioningRepositoryInterface $versioningRepository,
        VersioningInterfaceFactory $versioningFactory,
        SearchCriteriaInterface $searchCriteria
    ) {
        $this->log = $logging;
        $this->configInterface = $configInterface;
        $this->state = $state;
        $this->componentFactory = $componentFactory;
        $this->versioningRepository = $versioningRepository;
        $this->versioningFactory = $versioningFactory;
        $this->searchCriteria = $searchCriteria;
    }

    public function getLogger()
    {
        return $this->log;
    }

    /**
     * @param string $componentName
     * @return Processor
     */
    public function addComponent($componentName)
    {
        $this->components[$componentName] = $componentName;
        return $this;
    }

    /**
     * @return array
     */
    public function getComponents()
    {
        return $this->components;
    }

    /**
     * @param string $environment
     * @return Processor
     */
    public function setEnvironment($environment)
    {
        $this->environment = $environment;
        return $this;
    }

    /**
     * @return string
     */
    public function getEnvironment()
    {
        return $this->environment;
    }

    /**
     * @param string $force
     * @return Processor
     */
    public function setForce($force)
    {
        $this->force = $force;
        return $this;
    }

    /**
     * @return bool
     */
    private function getVersions()
    {
        // Get versions from the database
        $versionsDatabase = $this->versioningRepository->getList($this->searchCriteria)->getItems();
        $versionsDatabase = array_column($versionsDatabase, 'version');
        $latestDatabaseVersion = end($versionsDatabase);

        // Get versions from master file
        $master = $this->getMasterYaml();
        $masterVersions = array_keys($master['versions']);
        $latestMasterVersion = end($masterVersions);

        // Loop over master versions
        foreach($masterVersions as $version) {
            // Check if version not already applied
            if(!in_array($version, $versionsDatabase)) {
                $this->versions[] = $version;
            }
        }

        if(empty($this->versions)) {
            $this->log->logInfo("No new versions found in Master YAML file.");
            return false;
        }

        // Check for missing versions
        $missingVersions = $this->getMissingVersions($latestMasterVersion, $latestDatabaseVersion);
        if($missingVersions && !$this->force) {
            $this->log->logError(
                sprintf("There are still some older version(s) not applied: %s. Use the force (-f) command to force the import of these versions", implode(', ', $missingVersions))
            );
            return false;
        }

        return true;
    }

    /**
     * Get missing versions since latest version
     *
     * @param $latestMasterVersion
     * @param $latestDatabaseVersion
     * @return array
     */
    private function getMissingVersions($latestMasterVersion, $latestDatabaseVersion)
    {
        $missingVersions = array();
        foreach($this->versions as $version) {
            if($version < $latestMasterVersion && $version < $latestDatabaseVersion) {
                $missingVersions[] = $version;
            }
        }
        return $missingVersions;
    }


    /**
     * Save version
     * @param $version
     */
    private function saveVersion($version)
    {
        try {
            $versionFactory = $this->versioningFactory->create();
            $versionFactory->setVersion($version);
            $versionFactory->setUpdateTime(date('Y-m-d H:i:s', time()));
            $versionFactory->isObjectNew(true);
            $this->versioningRepository->save($versionFactory);
        } catch (CouldNotSaveException $couldNotSaveException) {
            $this->log->logError($couldNotSaveException->getMessage());
        } catch (\Exception $exception) {
            $this->log->logError($exception->getMessage());
        }
    }

    /**
     * Run the components individually
     */
    public function run()
    {
        // Check if there are versions we should run
        if(!$this->getVersions()) {
            return;
        }

        // If the components list is empty, then the user would want to run all components in the master.yaml
        if (empty($this->components)) {
            $this->runAllComponents();
            return;
        }

        $this->runIndividualComponents();
    }

    private function runIndividualComponents()
    {
        try {
            // Get the master yaml
            $master = $this->getMasterYaml();

            // Loop over all versions
            foreach($this->versions as $version) {

                // Loop through the components
                foreach ($this->components as $componentAlias) {

                    // Get the config for the component from the master yaml array
                    if (!isset($master['versions'][$version][$componentAlias])) {
                        throw new ComponentException(
                            sprintf("No Master YAML definition with the alias '%s' found in the current version '%s'", $componentAlias, $version)
                        );
                    }

                    $masterConfig = $master['versions'][$version][$componentAlias];

                    // Run that component
                    $this->state->emulateAreaCode(
                        Area::AREA_ADMINHTML,
                        [$this, 'runComponent'],
                        [$componentAlias, $masterConfig]
                    );
                }

                // Save current applied version
                $this->saveVersion($version);
            }
        } catch (ComponentException $e) {
            $this->log->logError($e->getMessage());
        }
    }

    private function runAllComponents()
    {
        try {
            // Get the master yaml
            $master = $this->getMasterYaml();

            // Loop over all versions
            foreach($this->versions as $version) {

                if(!isset($master['versions'][$version])) {
                    $this->log->logError(sprintf("Version %s doesn't exist in master file.", $version));
                    return false;
                }

                // Loop through components and run them individually in the master.yaml order
                foreach ($master['versions'][$version] as $componentAlias => $componentConfig) {
                    // Run the component in question
                    $this->state->emulateAreaCode(
                        Area::AREA_ADMINHTML,
                        [$this, 'runComponent'],
                        [$componentAlias, $componentConfig]
                    );
                }

                // Save current applied version
                $this->saveVersion($version);
            }
        } catch (ComponentException $e) {
            $this->log->logError($e->getMessage());
        }
    }

    public function runComponent($componentAlias, $componentConfig)
    {
        $this->log->logComment("");
        $this->log->logComment(str_pad("----------------------", (22 + strlen($componentAlias)), "-"));
        $this->log->logComment(sprintf("| Loading component %s |", $componentAlias));
        $this->log->logComment(str_pad("----------------------", (22 + strlen($componentAlias)), "-"));

        $componentClass = $this->configInterface->getComponentByName($componentAlias);

        /* @var ComponentAbstract $component */
        $component = $this->componentFactory->create($componentClass);
        if (isset($componentConfig['sources'])) {
            foreach ($componentConfig['sources'] as $source) {
                $component->setSource($source)->process();
            }
        }

        // Check if there are environment specific nodes placed
        if (!isset($componentConfig['env'])) {
            // If not, continue to next component
            $this->log->logComment(
                sprintf("No environment node for '%s' component", $component->getComponentName())
            );
            return;
        }

        // Check if there is a node for this particular environment
        if (!isset($componentConfig['env'][$this->getEnvironment()])) {
            // If not, continue to next component
            $this->log->logComment(
                sprintf(
                    "No '%s' environment specific node for '%s' component",
                    $this->getEnvironment(),
                    $component->getComponentName()
                )
            );
            return;
        }

        // Check if there are sources for the environment
        if (!isset($componentConfig['env'][$this->getEnvironment()]['sources'])) {
            // If not continue
            $this->log->logComment(
                sprintf(
                    "No '%s' environment specific sources for '%s' component",
                    $this->getEnvironment(),
                    $component->getComponentName()
                )
            );
            return;
        }
        
        // If there are sources for the environment, process them
        foreach ((array) $componentConfig['env'][$this->getEnvironment()]['sources'] as $source) {
            $component->setSource($source)->process();
        }
    }

    /**
     * @return array
     */
    private function getMasterYaml()
    {
        // Read master yaml
        $masterPath = BP . '/app/etc/master.yaml';
        if (!file_exists($masterPath)) {
            throw new ComponentException("Master YAML does not exist. Please create one in $masterPath");
        }
        $this->log->logComment(sprintf("Found Master YAML"));
        $yamlContents = file_get_contents($masterPath);
        $yaml = new Parser();
        $master = $yaml->parse($yamlContents);

        // Validate master yaml
        $this->validateMasterYaml($master);

        return $master;
    }

    /**
     * See if the component in master yaml exists
     *
     * @param $componentName
     * @return bool
     */
    private function isValidComponent($componentName)
    {
        if ($this->log->getLogLevel() > \Symfony\Component\Console\Output\OutputInterface::VERBOSITY_NORMAL) {
            $this->log->logQuestion(sprintf("Does the %s component exist?", $componentName));
        }
        $componentClass = $this->configInterface->getComponentByName($componentName);

        if (!$componentClass) {
            $this->log->logError(sprintf("The %s component has no class name.", $componentName));
            return false;
        }

        $this->log->logComment(sprintf("The %s component has %s class name.", $componentName, $componentClass));
        $component = $this->componentFactory->create($componentClass);
        if ($component instanceof ComponentAbstract) {
            return true;
        }
        return false;
    }

    /**
     * Basic validation of master yaml requirements
     *
     * @param $master
     * @SuppressWarnings(PHPMD)
     */
    private function validateMasterYaml($master)
    {
        try {
            if(!isset($master['versions'])) {
                throw new ComponentException('It appears there are no versions in the Master YAML file.');
            }

            foreach($master as $versions) {
                foreach ($versions as $version => $components) {
                    foreach ($components as $componentAlias => $componentConfig) {
                        // Check it has a enabled node
                        if (!isset($componentConfig['enabled'])) {
                            throw new ComponentException(
                                sprintf('It appears %s does not have a "enabled" node. This is required.', $componentAlias)
                            );
                        }

                        // Check it has at least 1 data source
                        $sourceCount = 0;
                        if (isset($componentConfig['sources'])) {
                            foreach ($componentConfig['sources'] as $i => $source) {
                                $sourceCount++;
                            }
                        }

                        if (isset($componentConfig['env'])) {
                            foreach ($componentConfig['env'] as $envData) {
                                if (isset($envData['sources'])) {
                                    foreach ($envData['sources'] as $i => $source) {
                                        $sourceCount++;
                                    }
                                }
                            }
                        }

                        if ($sourceCount < 1) {
                            throw new ComponentException(
                                sprintf('It appears there are no data sources for the %s component.', $componentAlias)
                            );
                        }

                        // Check the component exist
                        if (!$this->isValidComponent($componentAlias)) {
                            throw new ComponentException(
                                sprintf(
                                    '%s not a valid component. Please verify using bin/magento component:list.',
                                    $componentAlias
                                )
                            );
                        }
                    }
                }
            }
        } catch (ComponentException $e) {
            $this->log->logError($e->getMessage());
        }
    }
}
