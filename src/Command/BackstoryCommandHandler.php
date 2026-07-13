<?php

declare(strict_types=1);

namespace CoquiBot\Toolkits\Backstory\Command;

use CoquiBot\Toolkits\Backstory\BackstoryAssembler;
use CoquiBot\Toolkits\Backstory\BackstoryInspectionService;
use CoquiBot\Toolkits\Backstory\BackstoryManifest;
use CoquiBot\Toolkits\Backstory\Support\ProfilePaths;
use CoquiBot\Coqui\Contract\ToolkitCommandExample;
use CoquiBot\Coqui\Contract\ToolkitCommandHelp;
use CoquiBot\Coqui\Contract\ToolkitCommandHelpEntry;
use CoquiBot\Coqui\Contract\ToolkitCommandHelpProvider;
use CoquiBot\Coqui\Contract\ToolkitCommandHandler;
use CoquiBot\Coqui\Contract\ToolkitReplContext;
use CoquiBot\Coqui\Contract\ToolkitTabCompletionProvider;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Self-registering REPL command handler for the /backstory command.
 *
 * Behavior ported from coqui core's BackstoryHandler, decoupled from the core
 * RouteResult / ProfileDiscovery / ProfilePreferences dependencies: output is
 * written through the ToolkitReplContext and profile paths resolve via
 * this package's ProfilePaths.
 */
final class BackstoryCommandHandler implements ToolkitCommandHandler, ToolkitCommandHelpProvider, ToolkitTabCompletionProvider
{
    private readonly ProfilePaths $profilePaths;
    private readonly BackstoryAssembler $assembler;
    private readonly BackstoryInspectionService $inspectionService;

    public function __construct(
        string $workspacePath,
        ?BackstoryAssembler $assembler = null,
        ?BackstoryInspectionService $inspectionService = null,
    ) {
        $this->profilePaths = new ProfilePaths($workspacePath);
        $this->assembler = $assembler ?? new BackstoryAssembler();
        $this->inspectionService = $inspectionService
            ?? new BackstoryInspectionService($workspacePath, $this->profilePaths, $this->assembler);
    }

    public function commandName(): string
    {
        return 'backstory';
    }

    /**
     * @return list<string>
     */
    public function subcommands(): array
    {
        return ['generate', 'failed'];
    }

    public function usage(): string
    {
        return '/backstory [generate|failed]';
    }

    public function description(): string
    {
        return 'Inspect and generate the active profile\'s backstory.md from its backstory/ source files.';
    }

    public function help(): ToolkitCommandHelp
    {
        return new ToolkitCommandHelp(
            title: 'Backstory Generation',
            summary: 'Generate and inspect backstory.md, assembled from a profile\'s backstory/ source files.',
            subcommands: [
                new ToolkitCommandHelpEntry(
                    'overview',
                    '/backstory',
                    'Show the backstory manifest, status, and per-file breakdown for the active profile.',
                ),
                new ToolkitCommandHelpEntry(
                    'generate',
                    '/backstory generate',
                    'Assemble backstory.md from the profile\'s backstory/ source files.',
                ),
                new ToolkitCommandHelpEntry(
                    'failed',
                    '/backstory failed',
                    'List failed extractions and unsupported files from the last generation.',
                ),
            ],
            examples: [
                new ToolkitCommandExample(
                    '/backstory',
                    'Review the current backstory status and see whether it is stale.',
                ),
                new ToolkitCommandExample(
                    '/backstory generate',
                    'Rebuild backstory.md after adding or editing source files.',
                ),
            ],
            notes: [
                'Sources are read from profiles/<active>/backstory/ and written to profiles/<active>/backstory.md.',
                'Regeneration is on demand; the overview warns when sources have changed since the last generation.',
            ],
        );
    }

    /**
     * @param list<string> $parts
     * @return list<string>
     */
    public function completeArguments(string $commandName, array $parts): array
    {
        return $this->subcommands();
    }

    public function handle(ToolkitReplContext $context, string $arg): void
    {
        $io = $context->io;

        $activeProfile = $context->activeProfile;
        if ($activeProfile === null) {
            $io->error('No active profile. Use /profile <name> to activate a profile first.');
            return;
        }

        $profilePath = $this->resolveProfilePath($activeProfile);
        if ($profilePath === null) {
            $io->error(sprintf('Profile "%s" not found.', $activeProfile));
            return;
        }

        $subcommand = strtolower(trim($arg));

        match ($subcommand) {
            'generate' => $this->handleGenerate($io, $profilePath, $activeProfile),
            'failed' => $this->handleFailed($io, $profilePath, $activeProfile),
            '' => $this->handleOverview($io, $profilePath, $activeProfile),
            default => $this->handleUnknown($io, $subcommand),
        };
    }

    private function handleOverview(SymfonyStyle $io, string $profilePath, string $activeProfile): void
    {
        $backstory = $this->inspectionService->inspect($activeProfile);
        if (($backstory['source_folder_exists'] ?? false) !== true) {
            $io->info(sprintf(
                'No backstory source folder found for profile "%s". Create one at: %s',
                $activeProfile,
                BackstoryManifest::backstoryDir($profilePath),
            ));
            return;
        }

        if (($backstory['has_generated_backstory'] ?? false) !== true) {
            $io->warning('Backstory source folder exists but has not been generated yet. Run /backstory generate');
            return;
        }

        $io->section(sprintf('Backstory — %s', $activeProfile));

        $io->definitionList(
            ['Source folder' => (string) ($backstory['source_folder'] ?? '')],
            ['Generated file' => (string) ($backstory['generated_backstory_path'] ?? '')],
            ['Generated at' => self::formatNullableTimestamp(is_string($backstory['generated_at'] ?? null) ? $backstory['generated_at'] : null)],
            ['Last modified' => self::formatNullableTimestamp(is_string($backstory['last_modified_at'] ?? null) ? $backstory['last_modified_at'] : null)],
            ['Total files' => (string) ($backstory['total_files'] ?? 0)],
            ['Supported files' => (string) ($backstory['supported_file_count'] ?? 0)],
            ['Unsupported files' => (string) ($backstory['unsupported_file_count'] ?? 0)],
            ['Failed files' => (string) ($backstory['failed_file_count'] ?? 0)],
            ['Estimated tokens' => number_format((int) ($backstory['total_tokens'] ?? 0))],
            ['Total size' => self::formatBytes((int) ($backstory['total_size_bytes'] ?? 0))],
            ['Needs regeneration' => ($backstory['needs_regeneration'] ?? false) ? 'yes' : 'no'],
        );

        if (($backstory['folders'] ?? []) !== []) {
            $rows = [];
            foreach ($backstory['folders'] as $folder) {
                if (!is_array($folder)) {
                    continue;
                }

                $rows[] = [
                    self::formatFolderPath((string) ($folder['path'] ?? '')),
                    number_format((int) ($folder['total_tokens'] ?? 0)),
                    (string) ($folder['file_count'] ?? 0),
                    (string) ($folder['unsupported_file_count'] ?? 0),
                    (string) ($folder['failed_file_count'] ?? 0),
                    self::formatBytes((int) ($folder['total_size_bytes'] ?? 0)),
                    self::formatNullableTimestamp(is_string($folder['last_modified_at'] ?? null) ? $folder['last_modified_at'] : null),
                ];
            }

            $io->table(
                ['Folder', 'Tokens', 'Files', 'Skipped', 'Failed', 'Size', 'Modified'],
                $rows,
            );
        }

        if (($backstory['files'] ?? []) !== []) {
            $rows = [];
            foreach ($backstory['files'] as $file) {
                if (!is_array($file)) {
                    continue;
                }

                $status = ($file['status'] ?? 'unknown') === 'ok'
                    ? '<fg=green>ok</>'
                    : '<fg=red>failed</>';

                $rows[] = [
                    (string) ($file['relative_path'] ?? ''),
                    self::formatBytes((int) ($file['size_bytes'] ?? 0)),
                    number_format((int) ($file['token_estimate'] ?? 0)),
                    $status,
                    self::formatNullableTimestamp(is_string($file['modified_at'] ?? null) ? $file['modified_at'] : null),
                ];
            }

            $io->newLine();
            $io->table(
                ['File', 'Size', 'Tokens', 'Status', 'Modified'],
                $rows,
            );
        }

        if (($backstory['unsupported_files'] ?? []) !== []) {
            $rows = [];
            foreach ($backstory['unsupported_files'] as $file) {
                if (!is_array($file)) {
                    continue;
                }

                $rows[] = [
                    (string) ($file['relative_path'] ?? ''),
                    ($file['extension'] ?? '') !== '' ? '.' . $file['extension'] : '—',
                    (string) ($file['reason'] ?? ''),
                    self::formatNullableTimestamp(is_string($file['modified_at'] ?? null) ? $file['modified_at'] : null),
                ];
            }

            $io->newLine();
            $io->table(
                ['Skipped file', 'Extension', 'Reason', 'Modified'],
                $rows,
            );
        }

        if (($backstory['failed_file_count'] ?? 0) > 0 || ($backstory['unsupported_file_count'] ?? 0) > 0) {
            $messages = [];
            if (($backstory['failed_file_count'] ?? 0) > 0) {
                $messages[] = sprintf('%d failed extraction(s)', (int) $backstory['failed_file_count']);
            }
            if (($backstory['unsupported_file_count'] ?? 0) > 0) {
                $messages[] = sprintf('%d unsupported file(s) skipped', (int) $backstory['unsupported_file_count']);
            }

            $io->warning(implode('; ', $messages) . '. Run /backstory failed for details.');
        }

        if (($backstory['needs_regeneration'] ?? false) === true) {
            $io->warning('Backstory sources changed — run /backstory generate to update backstory.md.');
        }
    }

    private function handleGenerate(SymfonyStyle $io, string $profilePath, string $activeProfile): void
    {
        $backstoryDir = BackstoryManifest::backstoryDir($profilePath);
        if (!is_dir($backstoryDir)) {
            $io->error(sprintf(
                'No backstory source folder found. Create one at: %s',
                $backstoryDir,
            ));
            return;
        }

        $io->text(sprintf('Generating backstory for profile "%s"...', $activeProfile));

        $result = $this->assembler->generate(
            $profilePath,
            $this->profilePaths->backstoryLabel($profilePath),
        );

        if ($result->totalFiles === 0) {
            $io->info('No source files found in backstory source folder.');
            return;
        }

        $io->newLine();
        $io->definitionList(
            ['Files discovered' => (string) $result->totalFiles],
            ['Supported files' => (string) ($result->totalFiles - $result->unsupportedFiles)],
            ['Unsupported files' => (string) $result->unsupportedFiles],
            ['Failed extractions' => (string) $result->failedFiles],
            ['Estimated tokens' => number_format($result->totalTokens)],
            ['Generation time' => sprintf('%.1f ms', $result->generationTimeMs)],
        );

        if ($result->failedFiles > 0 || $result->unsupportedFiles > 0) {
            $messages = [];
            if ($result->failedFiles > 0) {
                $messages[] = sprintf('%d failed extraction(s)', $result->failedFiles);
            }
            if ($result->unsupportedFiles > 0) {
                $messages[] = sprintf('%d unsupported file(s) skipped', $result->unsupportedFiles);
            }

            $io->warning(implode('; ', $messages) . '. Run /backstory failed for details.');
        } else {
            $io->success('Backstory generated successfully. Wrote backstory.md.');
        }
    }

    private function handleFailed(SymfonyStyle $io, string $profilePath, string $activeProfile): void
    {
        $manifest = $this->assembler->getManifest($profilePath);
        if ($manifest === null || $manifest->generatedAt === '') {
            $io->info('No backstory has been generated yet. Run /backstory generate first.');
            return;
        }

        if ($manifest->errors === [] && $manifest->unsupportedFiles === []) {
            $io->success('No failed or unsupported files in the last generation.');
            return;
        }

        $io->section(sprintf('Backstory Issues — %s', $activeProfile));

        if ($manifest->errors !== []) {
            $rows = [];
            foreach ($manifest->errors as $error) {
                $rows[] = [
                    $error['relative_path'],
                    $error['error'],
                    self::formatNullableTimestamp($error['timestamp']),
                ];
            }

            $io->table(
                ['Failed file', 'Error', 'Timestamp'],
                $rows,
            );
        }

        if ($manifest->unsupportedFiles !== []) {
            $rows = [];
            foreach ($manifest->unsupportedFiles as $file) {
                $rows[] = [
                    $file['relative_path'],
                    $file['extension'] !== '' ? '.' . $file['extension'] : '—',
                    $file['reason'],
                    self::formatNullableTimestamp($file['modified_at']),
                ];
            }

            if ($manifest->errors !== []) {
                $io->newLine();
            }

            $io->table(
                ['Skipped file', 'Extension', 'Reason', 'Modified'],
                $rows,
            );
        }
    }

    private function handleUnknown(SymfonyStyle $io, string $subcommand): void
    {
        $io->error(sprintf('Unknown backstory subcommand: "%s"', $subcommand));
        $io->text('Run /backstory help for the available subcommands.');
    }

    private function resolveProfilePath(string $profileName): ?string
    {
        if (!$this->profilePaths->profileExists($profileName)) {
            return null;
        }

        return $this->profilePaths->profilePath($profileName);
    }

    private static function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }
        if ($bytes < 1048576) {
            return round($bytes / 1024, 1) . ' KB';
        }
        return round($bytes / 1048576, 1) . ' MB';
    }

    private static function formatNullableTimestamp(?string $timestamp): string
    {
        if ($timestamp === null || $timestamp === '') {
            return '—';
        }

        $dt = \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $timestamp);
        if ($dt === false) {
            return $timestamp;
        }

        return $dt->format('Y-m-d H:i');
    }

    private static function formatFolderPath(string $path): string
    {
        return $path !== '' ? $path : '.';
    }
}
