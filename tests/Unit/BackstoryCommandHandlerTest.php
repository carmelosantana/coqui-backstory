<?php

declare(strict_types=1);

use CoquiBot\Toolkits\Backstory\Command\BackstoryCommandHandler;
use CoquiBot\Coqui\Contract\ToolkitReplContext;
use CoquiBot\Coqui\Repl\InterruptiblePrompt;
use CoquiBot\Coqui\Support\ToolkitDatabaseFactory;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Build a real ToolkitReplContext whose io writes into a buffer we can assert on.
 */
function makeReplContext(BufferedOutput $output, string $workspace, ?string $activeProfile): ToolkitReplContext
{
    $io = new SymfonyStyle(new ArrayInput([]), $output);

    return new ToolkitReplContext(
        $io,
        new InterruptiblePrompt(),
        $workspace,
        $activeProfile,
        'test-session',
        $output,
        new ToolkitDatabaseFactory($workspace),
    );
}

beforeEach(function () {
    $this->workspace = makeTempDir();
    $this->activeProfile = 'caelum';
    $this->profilePath = $this->workspace . '/profiles/' . $this->activeProfile;
    $this->backstoryDir = $this->profilePath . '/backstory';
    mkdir($this->backstoryDir, 0o755, true);

    $this->output = new BufferedOutput();
    $this->handler = new BackstoryCommandHandler($this->workspace);
});

afterEach(function () {
    cleanupTestTree($this->workspace);
});

test('generate writes backstory.md to the exact contract path and reports success', function () {
    file_put_contents($this->backstoryDir . '/story.txt', 'A quiet beginning.');

    $context = makeReplContext($this->output, $this->workspace, $this->activeProfile);
    $this->handler->handle($context, 'generate');

    // Output-path contract: {workspace}/profiles/<active>/backstory.md, exactly.
    $expectedPath = $this->workspace . '/profiles/' . $this->activeProfile . '/backstory.md';
    expect(is_file($expectedPath))->toBeTrue();

    $buffer = $this->output->fetch();
    expect($buffer)->toContain('Backstory generated successfully');
    expect($buffer)->toContain('backstory.md');
});

test('overview prints the staleness hint when sources change after a generate', function () {
    $source = $this->backstoryDir . '/story.txt';
    file_put_contents($source, 'original');

    $context = makeReplContext($this->output, $this->workspace, $this->activeProfile);
    $this->handler->handle($context, 'generate');
    $this->output->fetch(); // drain the generate output

    // Touch a source so needsRegeneration() becomes true.
    file_put_contents($source, 'changed content and a bit longer');

    $this->handler->handle($context, '');

    $buffer = $this->output->fetch();
    expect($buffer)->toContain('Backstory sources changed');
    expect($buffer)->toContain('/backstory generate');
});

test('failed lists an unsupported source', function () {
    file_put_contents($this->backstoryDir . '/story.txt', 'A quiet beginning.');
    file_put_contents($this->backstoryDir . '/payload.exe', 'skip me');

    $context = makeReplContext($this->output, $this->workspace, $this->activeProfile);
    $this->handler->handle($context, 'generate');
    $this->output->fetch();

    $this->handler->handle($context, 'failed');

    $buffer = $this->output->fetch();
    expect($buffer)->toContain('payload.exe');
    expect($buffer)->toContain('.exe');
});

test('no active profile reports an error without throwing', function () {
    $context = makeReplContext($this->output, $this->workspace, null);

    $this->handler->handle($context, 'generate');

    $buffer = $this->output->fetch();
    expect($buffer)->toContain('No active profile');
});

test('unknown subcommand points to help', function () {
    $context = makeReplContext($this->output, $this->workspace, $this->activeProfile);

    $this->handler->handle($context, 'bogus');

    $buffer = $this->output->fetch();
    expect($buffer)->toContain('Unknown backstory subcommand');
    expect($buffer)->toContain('/backstory help');
});

test('handler advertises its command metadata', function () {
    expect($this->handler->commandName())->toBe('backstory');
    expect($this->handler->subcommands())->toBe(['generate', 'failed']);
    expect($this->handler->usage())->toBe('/backstory [generate|failed]');
    expect($this->handler->completeArguments('backstory', []))->toBe(['generate', 'failed']);
    expect($this->handler->help()->title)->toBe('Backstory Generation');
});
