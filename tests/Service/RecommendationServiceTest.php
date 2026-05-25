<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Agent\ToolRegistry;
use App\Ai\FakeRecommendationClient;
use App\Entity\CheckIn;
use App\Repository\CheckInRepository;
use App\Service\RecommendationService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Recommendation generation driven by the deterministic FakeRecommendationClient.
 *
 * Proves the full service flow (tools -> client -> persisted entity) works with
 * no network and no real OpenAI API key.
 */
final class RecommendationServiceTest extends KernelTestCase
{
    public function testGeneratesStructuredRecommendationWithoutApiKey(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);
        $this->resetSchema($em);

        // No real API key is configured in the test environment.
        self::assertSame('', (string) ($_SERVER['OPENAI_API_KEY'] ?? ''));

        $checkIn = new CheckIn(
            energyLevel: 4,
            focusGoal: 'IE: Prepare for Zes interview',
            distractionRisk: 'IE: Scope creep',
            notes: null,
        );
        $em->persist($checkIn);
        $em->flush();

        // Build the service with the Fake client explicitly: deterministic, offline.
        $fake = $container->get(FakeRecommendationClient::class);
        $service = new RecommendationService(
            $container->get(CheckInRepository::class),
            $em,
            $container->get(ToolRegistry::class),
            $fake,
            $fake,
            new NullLogger(),
        );

        $recommendation = $service->generateForCheckIn((int) $checkIn->getId());

        self::assertNotEmpty($recommendation->getPriority());
        self::assertNotEmpty($recommendation->getNextAction());
        self::assertNotEmpty($recommendation->getReasoning());
        self::assertContains($recommendation->getRiskLevel(), ['low', 'medium', 'high']);

        // Low energy (2) + two distraction keywords -> the tools score this "high".
        self::assertSame('high', $recommendation->getRiskLevel());
        self::assertSame('high', $recommendation->getPriority());

        // It was persisted and linked back to the check-in.
        self::assertNotNull($recommendation->getId());
        self::assertSame($checkIn->getId(), $recommendation->getCheckIn()->getId());
    }

    private function resetSchema(EntityManagerInterface $em): void
    {
        $params = $em->getConnection()->getParams();
        $path = $params['path'] ?? null;
        $em->getConnection()->close();
        if (is_string($path) && is_file($path)) {
            unlink($path);
        }

        $schemaTool = new SchemaTool($em);
        $schemaTool->createSchema($em->getMetadataFactory()->getAllMetadata());
    }
}
