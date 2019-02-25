<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Product\ProductVisibility;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityCollection;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class ProductVisibilityEntityTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepositoryInterface
     */
    protected $productRepository;

    /**
     * @var string
     */
    private $salesChannelId1;

    /**
     * @var string
     */
    private $salesChannelId2;

    /**
     * @var EntityRepositoryInterface
     */
    private $visibilityRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->productRepository = $this->getContainer()->get('product.repository');
        $this->visibilityRepository = $this->getContainer()->get('product_visibility.repository');

        $this->salesChannelId1 = Uuid::uuid4()->getHex();
        $this->salesChannelId2 = Uuid::uuid4()->getHex();

        $this->createSalesChannel($this->salesChannelId1);
        $this->createSalesChannel($this->salesChannelId2);
    }

    public function testVisibilityCRUD(): void
    {
        $id = Uuid::uuid4()->getHex();

        $product = $this->createProduct(
            $id,
            [
                $this->salesChannelId1 => ProductVisibilityDefinition::VISIBILITY_SEARCH,
                $this->salesChannelId2 => ProductVisibilityDefinition::VISIBILITY_LINK,
            ]
        );

        $context = Context::createDefaultContext();

        $container = $this->productRepository->create([$product], $context);

        $event = $container->getEventByDefinition(ProductVisibilityDefinition::class);

        //visibility created?
        static::assertInstanceOf(EntityWrittenEvent::class, $event);
        static::assertCount(2, $event->getWriteResults());

        $criteria = new Criteria([$id]);
        $criteria->addAssociation('product.visibilities');

        /** @var ProductEntity $product */
        $product = $this->productRepository->search($criteria, $context)->first();

        //check visibilities can be loaded as association
        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertInstanceOf(ProductVisibilityCollection::class, $product->getVisibilities());
        static::assertCount(2, $product->getVisibilities());

        //check read for visibilities
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('product_visibility.productId', $id));

        $visibilities = $this->visibilityRepository->search($criteria, $context);
        static::assertCount(2, $visibilities);

        //test filter visibilities over product
        $criteria = new Criteria([$id]);

        $criteria->addFilter(
            new MultiFilter(
                MultiFilter::CONNECTION_AND,
                [
                    new RangeFilter('product.visibilities.visibility', [
                        RangeFilter::GTE => ProductVisibilityDefinition::VISIBILITY_LINK,
                    ]),
                    new EqualsFilter('product.visibilities.salesChannelId', $this->salesChannelId1),
                ]
            )
        );

        $product = $this->productRepository->search($criteria, $context)->first();

        //visibilities filtered and loaded?
        static::assertInstanceOf(ProductEntity::class, $product);

        $ids = $visibilities->map(
            function (ProductVisibilityEntity $visibility) {
                return ['id' => $visibility->getId()];
            }
        );

        $container = $this->visibilityRepository->delete(array_values($ids), $context);

        $event = $container->getEventByDefinition(ProductVisibilityDefinition::class);
        static::assertInstanceOf(EntityWrittenEvent::class, $event);
        static::assertCount(2, $event->getWriteResults());
    }

    private function createProduct(string $id, array $visibilities): array
    {
        $mapped = [];
        foreach ($visibilities as $salesChannel => $visibility) {
            $mapped[] = ['salesChannelId' => $salesChannel, 'visibility' => $visibility];
        }

        return [
            'id' => $id,
            'name' => 'test',
            'price' => ['gross' => 15, 'net' => 10],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'visibilities' => $mapped,
        ];
    }

    private function createSalesChannel($id): void
    {
        $data = [
            'id' => $id,
            'accessKey' => AccessKeyHelper::generateAccessKey('sales-channel'),
            'typeId' => Defaults::SALES_CHANNEL_STOREFRONT_API,
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'currencyId' => Defaults::CURRENCY,
            'currencyVersionId' => Defaults::LIVE_VERSION,
            'paymentMethodId' => Defaults::PAYMENT_METHOD_INVOICE,
            'paymentMethodVersionId' => Defaults::LIVE_VERSION,
            'shippingMethodId' => Defaults::SHIPPING_METHOD,
            'shippingMethodVersionId' => Defaults::LIVE_VERSION,
            'countryId' => Defaults::COUNTRY,
            'countryVersionId' => Defaults::LIVE_VERSION,
            'currencies' => [['id' => Defaults::CURRENCY]],
            'languages' => [['id' => Defaults::LANGUAGE_SYSTEM]],
            'shippingMethods' => [['id' => Defaults::SHIPPING_METHOD]],
            'paymentMethods' => [['id' => Defaults::PAYMENT_METHOD_INVOICE]],
            'countries' => [['id' => Defaults::COUNTRY]],
            'name' => 'first sales-channel',
        ];

        $this->getContainer()->get('sales_channel.repository')->create([$data], Context::createDefaultContext());
    }
}