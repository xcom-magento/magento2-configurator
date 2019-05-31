<?php
namespace CtiDigital\Configurator\Test\Unit\Component;

use CtiDigital\Configurator\Component\Products;
use Magento\Catalog\Model\ProductFactory;
use Magento\Framework\App\Response\Http\FileFactory;

class ProductsTest extends ComponentAbstractTestCase
{
    /**
     * @var ProductFactory | \PHPUnit_Framework_MockObject_MockObject
     */
    private $productFactoryMock;

    protected function componentSetUp()
    {
        $importerFactoryMock = $this->getMockBuilder('Firegento\FastSimpleImport\Model\ImporterFactory')
            ->disableOriginalConstructor()
            ->getMock();

        $this->productFactoryMock = $this->getMockBuilder('Magento\Catalog\Model\ProductFactory')
            ->setMethods(['create'])
            ->disableOriginalConstructor()
            ->getMock();

        $httpClientMock = $this->getMockBuilder('Magento\Framework\HTTP\ZendClientFactory')
            ->disableOriginalConstructor()
            ->setMethods(['create'])
            ->getMock();
        $mockFileFactory = $this->getMockBuilder(FileFactory::class)
            ->disableOriginalConstructor()
            ->setMethods(['create'])
            ->getMock();

        $this->component = $this->testObjectManager->getObject(
            Products::class,
            [
                'importerFactory' => $importerFactoryMock,
                'productFactory' => $this->productFactoryMock,
                'httpClientFactory' => $httpClientMock,
                'fileFactory' => $mockFileFactory
            ]
        );
        $this->className = Products::class;
    }

    public function testGetSkuColumnIndex()
    {
        $columns = [
            'attribute_set_code',
            'product_websites',
            'store_view_code',
            'product_type',
            'sku',
            'name',
            'short_description',
            'description'
        ];

        $expected = 4;
        $this->assertEquals($expected, $this->component->getSkuColumnIndex($columns));
    }

    public function testGetAttributesFromCsv()
    {
        $importData = [
            [
                'attribute_set_code',
                'product_websites',
                'store_view_code',
                'product_type',
                'sku',
                'name',
                'short_description',
                'description'
            ],
            [
                'Default',
                'base',
                'default',
                'configurable',
                '123',
                'Product A',
                'Short description',
                'Longer description'
            ]
        ];

        $expected = [
            'attribute_set_code',
            'product_websites',
            'store_view_code',
            'product_type',
            'sku',
            'name',
            'short_description',
            'description'
        ];

        $this->assertEquals($expected, $this->component->getAttributesFromCsv($importData));
    }

    public function testIsConfigurable()
    {
        $importData = [
            'product_type' => 'configurable'
        ];
        $this->assertTrue($this->component->isConfigurable($importData));
    }

    public function testIsNotAConfigurable()
    {
        $importData = [
            'product_type' => 'simple'
        ];
        $this->assertFalse($this->component->isConfigurable($importData));
    }

    public function testConstructVariations()
    {
        $configurableData = [
            'associated_products' => '1,2',
            'configurable_attributes' => 'colour,size,style',
        ];

        $expected = 'sku=1;colour=Blue;size=Medium;style=Loose|sku=2;colour=Red;size=Small;style=Loose';

        $productAColourMock = $this->createMockAttribute('colour', 'Blue');
        $productASizeMock = $this->createMockAttribute('size', 'Medium');
        $productAStyleMock = $this->createMockAttribute('style', 'Loose');
        $productBColourMock = $this->createMockAttribute('colour', 'Red');
        $productBSizeMock = $this->createMockAttribute('size', 'Small');
        $productBStyleMock = $this->createMockAttribute('style', 'Loose');

        $simpleMockA = $this->createProduct(1);

        $simpleMockA->expects($this->any())
            ->method('getResource')
            ->willReturnSelf();

        $simpleMockA->method('getAttribute')
            ->will(
                $this->onConsecutiveCalls(
                    $productAColourMock,
                    $productASizeMock,
                    $productAStyleMock
                )
            );

        $simpleMockA->method('hasData')
            ->will(
                $this->onConsecutiveCalls(
                    'Blue',
                    'Medium',
                    'Loose'
                )
            );

        $simpleMockB = $this->createProduct(2);

        $simpleMockB->method('getAttribute')
            ->will(
                $this->onConsecutiveCalls(
                    $productBColourMock,
                    $productBSizeMock,
                    $productBStyleMock
                )
            );

        $simpleMockB->method('hasData')
            ->will(
                $this->onConsecutiveCalls(
                    'Red',
                    'Small',
                    'Loose'
                )
            );

        $this->productFactoryMock->expects($this->at(0))
            ->method('create')
            ->willReturn($simpleMockA);

        $this->productFactoryMock->expects($this->at(1))
            ->method('create')
            ->willReturn($simpleMockB);

        $this->assertEquals($expected, $this->component->constructConfigurableVariations($configurableData));
    }

    public function testIsStockSet()
    {
        $testData = [
            'sku' => 1,
            'is_in_stock' => 1,
            'qty' => 1
        ];
        $this->assertTrue($this->component->isStockSpecified($testData));
    }

    public function testStockIsNotSet()
    {
        $testData = [
            'sku' => 1,
            'name' => 'Test'
        ];
        $this->assertFalse($this->component->isStockSpecified($testData));
    }

    public function testSetStock()
    {
        $testData = [
            'sku' => 1,
            'name' => 'Test',
            'is_in_stock' => 1
        ];
        $expectedData = [
            'sku' => 1,
            'name' => 'Test',
            'is_in_stock' => 1,
            'qty' => 1
        ];
        $this->assertEquals($expectedData, $this->component->setStock($testData));
    }

    public function testNotSetStock()
    {
        $testData = [
            'sku' => 1,
            'name' => 'Test',
            'is_in_stock' => 0
        ];
        $expectedData = [
            'sku' => 1,
            'name' => 'Test',
            'is_in_stock' => 0,
        ];
        $this->assertEquals($expectedData, $this->component->setStock($testData));
    }

    private function createProduct($productId)
    {
        $productMock = $this->getMockBuilder('Magento\Catalog\Model\Product')
            ->disableOriginalConstructor()
            ->setMethods(['hasData', 'getSku', 'getIdBySku', 'load', 'getId', 'getResource', 'getAttribute'])
            ->getMock();
        $productMock->expects($this->any())
            ->method('getId')
            ->willReturn($productId);
        $productMock->expects($this->any())
            ->method('getIdBySku')
            ->willReturnSelf();
        $productMock->expects($this->once())
            ->method('load')
            ->willReturnSelf();
        $productMock->expects($this->any())
            ->method('getResource')
            ->willReturnSelf();
        return $productMock;
    }

    private function createMockAttribute($attributeCode, $value)
    {
        $attr = $this->getMockBuilder('Magento\Eav\Model\Entity\Attribute')
            ->disableOriginalConstructor()
            ->setMethods(['getFrontend', 'getValue', 'getAttributeCode'])
            ->getMock();
        $attr->expects($this->once())
            ->method('getFrontend')
            ->willReturnSelf();
        $attr->expects($this->any())
            ->method('getAttributeCode')
            ->willReturn($attributeCode);
        $attr->expects($this->once())
            ->method('getValue')
            ->willReturn($value);
        return $attr;
    }
}
