<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\PlainImage;
use App\Repository\PlainImageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\{Attribute\AsCommand,
    Command\Command,
    Input\InputInterface,
    Output\OutputInterface,
    Style\SymfonyStyle
};
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\{Filesystem\Filesystem, HttpClient\HttpClient, Uid\Uuid};
use Symfony\Contracts\HttpClient\{HttpClientInterface,
    Exception\ClientExceptionInterface,
    Exception\RedirectionExceptionInterface,
    Exception\ServerExceptionInterface,
    Exception\TransportExceptionInterface
};

#[AsCommand(
    name: 'flower:images:save-random',
    description: 'short flower description',
)]
class FlowerImagesSaveRandomCommand extends Command
{
    private const IMAGE_PAGE = "https://sklep.swiatkwiatow.pl/";
    private const IMAGE_LINK_PATTERN = "swiatkwiatow.pl/images/";
    private const IMAGE_DIRECTORY_PART = "images";

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ParameterBagInterface  $parameterBag,
        private readonly LoggerInterface        $logger,
        string                                  $name = null
    )
    {
        parent::__construct($name);
    }

    protected function configure(): void
    {
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $savedImagesNumber = 0;
        $errors = 0;

        try {
            $links = $this->getShuffleLinks();
            $savedImagesNumber = $this->saveMaxThreeImages($links);
        } catch (\Throwable $throwable) {
            $errors++;
            $this->logger->error('flower:images:save-random Command error: ' . $throwable->getMessage());
        }

        $io->success('Saved images number: ' . $savedImagesNumber . ', errors: ' . $errors);

        return Command::SUCCESS;
    }

    /**
     * @return string[]
     */
    private function getShuffleLinks(): array
    {
        $imagePage = file_get_contents(self::IMAGE_PAGE);
        $links = [];

        preg_match_all(
            "{<img\\s*(.*?)src=('.*?'|\".*?\"|\S+)(.*?)\\s*/?>}ims",
            $imagePage,
            $matches,
            PREG_SET_ORDER
        );

        foreach ($matches as $val) {
            $link = substr($val[2], 1, -1);

            if (str_contains($link, self::IMAGE_LINK_PATTERN)) {
                $links[] = $link;
            }
        }

        shuffle($links);

        return $links;
    }

    /**
     * @param string[] $links
     *
     * @return int Number of saved images.
     *
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    private function saveMaxThreeImages(array $links): int
    {
        $maxThree = 0;
        $repository = $this->entityManager->getRepository(PlainImage::class);
        $filesystem = new Filesystem();
        $client = HttpClient::create();

        foreach ($links as $link) {
            if ($this->isImageExists($link, $repository)) {
                continue;
            }

            $fileLocation = $this->saveImageToStorage($link, $filesystem, $client);

            $plainImage = new PlainImage();
            $plainImage->setLink($link);
            $plainImage->setFileLocation($fileLocation);
            $this->entityManager->persist($plainImage);

            $maxThree++;
            if ($maxThree >= 3) {
                $this->entityManager->flush();

                return $maxThree;
            }
        }

        $this->entityManager->flush();

        return $maxThree;
    }

    private function isImageExists(
        string               $link,
        PlainImageRepository $repository
    ): bool
    {
        $object = $repository->findOneBy(['link' => $link]);

        return $object instanceof PlainImage;
    }

    /**
     * @return string Relative path to file.
     *
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    private function saveImageToStorage(
        string              $linkToImage,
        Filesystem          $filesystem,
        HttpClientInterface $client
    ): string
    {
        $response = $client->request('GET', $linkToImage);
        $uuid = Uuid::v4();
        $storageDirectory = $this->parameterBag->get('default_storage') . DIRECTORY_SEPARATOR .
            self::IMAGE_DIRECTORY_PART;

        if (!$filesystem->exists($storageDirectory)) {
            $filesystem->mkdir($storageDirectory, 0755);
        }

        $fullPath = $storageDirectory . DIRECTORY_SEPARATOR . $uuid;
        $filesystem->dumpFile($fullPath, $response->getContent());

        return self::IMAGE_DIRECTORY_PART . DIRECTORY_SEPARATOR . $uuid;
    }
}
