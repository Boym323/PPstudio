<?php
declare(strict_types=1);

namespace PPStudio\Http\View;

final class SitePageCatalog
{
    /**
     * @return array<string, array{title:string, description:string, active_nav:string, template:string}>
     */
    public function pages(string $projectRoot): array
    {
        $pages = [
            'home' => [
                'title' => 'PP Studio - Kosmetický salon Zlín',
                'description' => 'PP Studio - moderní kosmetický salon ve Zlíně. Profesionální péče o pleť a individuální přístup.',
                'active_nav' => 'home',
                'template' => $projectRoot . '/includes/site/pages/home.php',
            ],
            'services' => [
                'title' => 'Služby | PP Studio',
                'description' => 'Přehled služeb PP Studia a typů ošetření.',
                'active_nav' => 'services',
                'template' => $projectRoot . '/includes/site/pages/services.php',
            ],
            'pricing' => [
                'title' => 'Ceník | PP Studio',
                'description' => 'Aktuální ceník ošetření PP Studia přehledně podle kategorií.',
                'active_nav' => 'pricing',
                'template' => $projectRoot . '/includes/site/pages/pricing.php',
            ],
            'spaces' => [
                'title' => 'Prostory studia | PP Studio',
                'description' => 'Prohlédněte si prostory PP Studia.',
                'active_nav' => 'spaces',
                'template' => $projectRoot . '/includes/site/pages/spaces.php',
            ],
            'about' => [
                'title' => 'O mně | PP Studio',
                'description' => 'Poznejte příběh a přístup Pavlíny Pomykalové v PP Studiu.',
                'active_nav' => 'about',
                'template' => $projectRoot . '/includes/site/pages/about.php',
            ],
            'reviews' => [
                'title' => 'Recenze klientek | PP Studio',
                'description' => 'Přečtěte si hodnocení klientek, které navštívily PP Studio.',
                'active_nav' => 'reviews',
                'template' => $projectRoot . '/includes/site/pages/reviews.php',
            ],
            'reservation' => [
                'title' => 'Rezervace a kontakt | PP Studio',
                'description' => 'Objednejte si termín online a najděte všechny kontaktní informace na jednom místě.',
                'active_nav' => 'reservation',
                'template' => $projectRoot . '/includes/site/pages/reservation.php',
            ],
        ];

        return $pages;
    }

    public function page(string $projectRoot, string $pageKey): array
    {
        $pages = $this->pages($projectRoot);

        return $pages[$pageKey] ?? $pages['home'];
    }
}
