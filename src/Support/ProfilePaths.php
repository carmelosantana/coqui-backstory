<?php

declare(strict_types=1);

namespace CoquiBot\Toolkits\Backstory\Support;

/**
 * Resolves profile filesystem paths from a workspace root.
 *
 * Severs the toolkit's coupling to coqui core's ProfileDiscovery/ProfilePreferences:
 * it reproduces only the two path/label behaviours the generator actually needs.
 */
final readonly class ProfilePaths
{
    public function __construct(
        private string $workspacePath,
    ) {}

    public function profilesDir(): string
    {
        return rtrim($this->workspacePath, '/') . '/profiles';
    }

    public function profilePath(string $name): string
    {
        return $this->profilesDir() . '/' . $name;
    }

    public function profileExists(string $name): bool
    {
        return is_dir($this->profilePath($name));
    }

    /**
     * Read the display label for the backstory heading from a profile's preferences.json.
     *
     * Display-only: mirrors core ProfilePreferences::getBackstoryLabel() by reading
     * `labels.backstory`. Any missing, unreadable, or malformed file yields null so
     * callers can fall back to their own default heading.
     */
    public function backstoryLabel(string $profilePath): ?string
    {
        $preferencesPath = rtrim($profilePath, '/') . '/preferences.json';
        if (!is_file($preferencesPath)) {
            return null;
        }

        $json = file_get_contents($preferencesPath);
        if ($json === false) {
            return null;
        }

        $data = json_decode($json, true);
        if (!is_array($data)) {
            return null;
        }

        $labels = $data['labels'] ?? null;
        if (!is_array($labels)) {
            return null;
        }

        $label = $labels['backstory'] ?? null;

        return is_string($label) ? $label : null;
    }
}
