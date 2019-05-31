<?php

namespace CtiDigital\Configurator\Test\Unit\Component;

use CtiDigital\Configurator\Component\Blocks;
use Magento\Cms\Api\Data\BlockInterfaceFactory;

class BlocksTest extends ComponentAbstractTestCase
{

    protected function componentSetUp()
    {
        $blockInterface = $this->getMockBuilder(BlockInterfaceFactory::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->component = new Blocks($this->logInterface, $this->objectManager, $blockInterface);
        $this->className = Blocks::class;
    }
}
