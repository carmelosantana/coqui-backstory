<?php

declare(strict_types=1);

namespace CoquiBot\Toolkits\Backstory;

use CoquiBot\Toolkits\Backstory\Command\BackstoryCommandHandler;
use CarmeloSantana\PHPAgents\Contract\ToolkitInterface;
use CoquiBot\Coqui\Contract\ReplCommandProvider;
use CoquiBot\Coqui\Contract\ToolkitCommandHandler;

/**
 * Optional Coqui toolkit that produces backstory.md from a profile's
 * backstory/ source files.
 *
 * Exposes no agent-facing tools (core never had any); its whole surface is the
 * self-registering /backstory REPL command. Core remains the consumer that
 * reads the generated backstory.md.
 */
final class BackstoryToolkit implements ToolkitInterface, ReplCommandProvider
{
    public function __construct(
        private readonly string $workspacePath = '',
    ) {}

    public static function fromEnv(): self
    {
        return new self((string) (getenv('COQUI_WORKSPACE_PATH') ?: ''));
    }

    /**
     * @param array<string, mixed> $context
     */
    public static function fromCoquiContext(array $context): self
    {
        $workspacePath = $context['workspacePath'] ?? '';

        return new self(is_string($workspacePath) ? $workspacePath : '');
    }

    /**
     * @return list<never>
     */
    public function tools(): array
    {
        return [];
    }

    public function guidelines(): string
    {
        return <<<'GUIDELINES'
        <BACKSTORY-TOOLKIT-GUIDELINES>
        ## Backstory Toolkit

        This toolkit generates a profile's `backstory.md` from source files.

        - Source files live in `profiles/<name>/backstory/` (text, markdown, JSON,
          YAML, CSV, code, SQL, XML, RTF, office documents, plus Word/PDF/HTML).
        - Run `/backstory generate` to assemble them into `profiles/<name>/backstory.md`.
        - Core reads the resulting `backstory.md`; regeneration is on demand.
        - There are no agent-facing tools — use the `/backstory` REPL command.
        </BACKSTORY-TOOLKIT-GUIDELINES>
        GUIDELINES;
    }

    /**
     * @return list<ToolkitCommandHandler>
     */
    public function commandHandlers(): array
    {
        return [new BackstoryCommandHandler($this->workspacePath)];
    }
}
