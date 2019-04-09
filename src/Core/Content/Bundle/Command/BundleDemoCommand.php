<?php declare(strict_types=1);

namespace ShopwareLabs\Plugin\SwagBundleExample\Core\Content\Bundle\Command;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class BundleDemoCommand extends Command
{
    /**
     * @var EntityRepositoryInterface
     */
    protected $bundleRepository;

    /**
     * @var EntityRepositoryInterface
     */
    protected $productRepository;

    public function __construct(EntityRepositoryInterface $bundleRepository, EntityRepositoryInterface $productRepository)
    {
        parent::__construct();

        $this->bundleRepository = $bundleRepository;
        $this->productRepository = $productRepository;
    }

    protected function configure()
    {
        parent::configure();
        $this->setName('bundle:demo');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $context = Context::createDefaultContext();
        $criteria = new Criteria();
        $criteria->setLimit(50);
        $productIds = $this->productRepository->searchIds(new Criteria(), $context)->getIds();

        if (count($productIds) === 0) {
            $io->error('Please create products before by using bin/console framework:demodata');
            exit(1);
        }

        $data = [];
        for ($i = 0; $i < 10; ++$i) {
            $bundleId = Uuid::randomHex();
            $data[] = [
                'discount' => random_int(100, 1000) / 100,
                'discountType' => random_int(0, 1) ? 'absolute' : 'percentage', // todo
                'name' => [
                    'de_DE' => 'Beispiel Bundle ' . $i,
                    'en_GB' => 'Example bundle ' . $i,
                ],
                'products' => [
                    [
                        'id' => $productIds[array_rand($productIds)],
                    ],
                    [
                        'id' => $productIds[array_rand($productIds)],
                    ],
                    [
                        'id' => $productIds[array_rand($productIds)],
                    ],
                ],
            ];
        }
        $this->bundleRepository->upsert($data, $context);
    }
}
