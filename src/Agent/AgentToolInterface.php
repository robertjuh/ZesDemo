<?php

declare(strict_types=1);

namespace App\Agent;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * A small, self-contained capability the "agent" can call.
 *
 * The shape (name + execute(array): array) is deliberately the same shape an
 * MCP tool / OpenAI tool-call exposes. Today these run in-process; swapping in
 * a real MCP server later means implementing this interface over a transport,
 * with no change to the services that consume tools.
 *
 * #[AutoconfigureTag] means every implementation is automatically collected
 * by the ToolRegistry — adding a tool is just adding a class.
 */
#[AutoconfigureTag('app.agent_tool')]
interface AgentToolInterface
{
    /**
     * Stable, machine-friendly identifier (e.g. "score_focus_risk").
     */
    public function name(): string;

    /**
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    public function execute(array $input): array;
}
