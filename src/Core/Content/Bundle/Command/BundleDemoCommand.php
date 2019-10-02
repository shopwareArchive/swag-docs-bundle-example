<?php declare(strict_types=1);

namespace Swag\BundleExample\Core\Content\Bundle\Command;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
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

    public function __construct(
        EntityRepositoryInterface $bundleRepository,
        EntityRepositoryInterface $productRepository
    ) {
        parent::__construct();

        $this->bundleRepository = $bundleRepository;
        $this->productRepository = $productRepository;
    }

    protected function configure(): void
    {
        parent::configure();
        $this->setName('bundle:demo');
    }

    protected function execute(InputInterface $input, OutputInterface $output): ?int
    {
        $io = new SymfonyStyle($input, $output);
        $context = Context::createDefaultContext();
        $criteria = new Criteria();
        $criteria->setLimit(50)
            ->addFilter(new EqualsFilter('active', true));
        $productIds = $this->productRepository->searchIds($criteria, $context)->getIds();

        if (\count($productIds) === 0) {
            $io->error('Please create products before by using bin/console framework:demodata');

            return 1;
        }

        $data = [];
        for ($i = 0; $i < 10; ++$i) {
            $data[] = [
                'discount' => random_int(100, 1000) / 100,
                'discountType' => random_int(0, 1) ? 'absolute' : 'percentage', // todo
                'name' => [
                    'de-DE' => 'Beispiel Bundle ' . $i,
                    'en-GB' => 'Example bundle ' . $i,
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

        return null;
    }
}
