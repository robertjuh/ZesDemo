<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * End-to-end API test for creating a check-in: real routing, controller,
 * validation, service and Doctrine, against the test SQLite database.
 */
final class CheckInControllerTest extends WebTestCase
{
    public function testPostCheckInReturnsCreatedResource(): void
    {
        $client = static::createClient();
        $this->resetSchema();

        $payload = [
            'energyLevel' => 3,
            'focusGoal' => 'Prepare for Symfony interview',
            'distractionRisk' => 'Spending too much time overengineering',
            'notes' => 'Need a small demo I can explain',
        ];

        $client->request(
            'POST',
            '/api/checkins',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload, JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(201);

        $data = json_decode(
            (string) $client->getResponse()->getContent(),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        self::assertSame(3, $data['energyLevel']);
        self::assertSame('Prepare for Symfony interview', $data['focusGoal']);
        self::assertArrayHasKey('createdAt', $data);
        self::assertNotEmpty($data['createdAt']);
    }

    /**
     * Rebuild the schema so the test owns a known-empty database.
     */
    private function resetSchema(): void
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);

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
