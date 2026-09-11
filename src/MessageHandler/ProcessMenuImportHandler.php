<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Menu\MenuImportProcessor;
use App\Message\ProcessMenuImport;
use App\Repository\MenuImportRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class ProcessMenuImportHandler
{
    public function __construct(
        private readonly MenuImportRepository $imports,
        private readonly MenuImportProcessor $processor,
    ) {
    }

    public function __invoke(ProcessMenuImport $message): void
    {
        $import = $this->imports->find($message->menuImportId);

        if (null === $import) {
            return;
        }

        $this->processor->process($import);
    }
}
