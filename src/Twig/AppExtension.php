<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\Business;
use App\Repository\BusinessRepository;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class AppExtension extends AbstractExtension
{
    public function __construct(private readonly BusinessRepository $businesses)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('active_business', $this->activeBusiness(...)),
        ];
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('eur', $this->formatEuros(...)),
        ];
    }

    public function activeBusiness(): ?Business
    {
        return $this->businesses->findActiveForDemo();
    }

    /**
     * Un precio ausente no se convierte en cero: se muestra un guion.
     */
    public function formatEuros(string|float|null $amount): string
    {
        if (null === $amount || '' === $amount) {
            return '—';
        }

        return number_format((float) $amount, 2, ',', '.').' €';
    }
}
