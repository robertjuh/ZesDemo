<?php

declare(strict_types=1);

namespace App\Agent;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Looks up agent tools by name.
 *
 * Tools are injected via a tagged iterator, so the registry has no hard-coded
 * list. This is the seam where a future MCP client would register its remote
 * tools alongside the local ones.
 */
final class ToolRegistry
{
    /** @var array<string, AgentToolInterface> */
    private array $tools = [];

    /**
     * @param iterable<AgentToolInterface> $tools
     */
    public function __construct(
        #[AutowireIterator('app.agent_tool')] iterable $tools,
    ) {
        foreach ($tools as $tool) {
            $this->tools[$tool->name()] = $tool;
        }
    }

    public function has(string $name): bool
    {
        return isset($this->tools[$name]);
    }

    public function get(string $name): AgentToolInterface
    {
        if (!$this->has($name)) {
            throw new \InvalidArgumentException(sprintf('Unknown agent tool "%s".', $name));
        }

        return $this->tools[$name];
    }

    /**
     * @return array<string, AgentToolInterface>
     */
    public function all(): array
    {
        return $this->tools;
    }
}
