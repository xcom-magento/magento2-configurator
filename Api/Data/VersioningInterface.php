<?php

namespace CtiDigital\Configurator\Api\Data;

interface VersioningInterface
{
    /**#@+
     * Constants for keys of data array. Identical to the name of the getter in snake case
     */
    const VERSION       = 'version';
    const UPDATE_TIME   = 'update_time';

    /**
     * Get version
     *
     * @return int|null
     */
    public function getVersion();

    /**
     * Get update time
     *
     * @return string|null
     */
    public function getUpdateTime();

    /**
     * Set version
     *
     * @param int $version
     * @return VersioningInterface
     */
    public function setVersion($version);

    /**
     * Set update time
     *
     * @param string $updateTime
     * @return VersioningInterface
     */
    public function setUpdateTime($updateTime);

    /**
     * Set object new
     *
     * @param string $flag
     * @return VersioningInterface
     */
    public function isObjectNew($flag = null);
}