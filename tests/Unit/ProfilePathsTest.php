<?php

declare(strict_types=1);

use CoquiBot\Toolkits\Backstory\Support\ProfilePaths;

beforeEach(function () {
    $this->workspace = makeTempDir();
});

afterEach(function () {
    cleanupTestTree($this->workspace);
});

test('profilesDir resolves under the workspace root', function () {
    $paths = new ProfilePaths($this->workspace);

    expect($paths->profilesDir())->toBe($this->workspace . '/profiles');
});

test('profilesDir tolerates a trailing slash on the workspace path', function () {
    $paths = new ProfilePaths($this->workspace . '/');

    expect($paths->profilesDir())->toBe($this->workspace . '/profiles');
});

test('active profile among multiple resolves to exactly its own dir', function () {
    mkdir($this->workspace . '/profiles/alpha', 0755, true);
    mkdir($this->workspace . '/profiles/beta', 0755, true);

    $paths = new ProfilePaths($this->workspace);

    // A given active name must resolve to precisely {workspace}/profiles/<active>
    // so generation writes into the correct profile directory.
    expect($paths->profilePath('beta'))->toBe($this->workspace . '/profiles/beta');
    expect($paths->profilePath('alpha'))->toBe($this->workspace . '/profiles/alpha');

    expect($paths->profileExists('alpha'))->toBeTrue();
    expect($paths->profileExists('beta'))->toBeTrue();
    expect($paths->profileExists('gamma'))->toBeFalse();
});

test('profileExists is false when the profiles dir is absent', function () {
    $paths = new ProfilePaths($this->workspace);

    expect($paths->profileExists('anything'))->toBeFalse();
});

test('backstoryLabel returns the configured label when present', function () {
    $profilePath = $this->workspace . '/profiles/alpha';
    mkdir($profilePath, 0755, true);
    file_put_contents(
        $profilePath . '/preferences.json',
        json_encode(['labels' => ['backstory' => 'Lore']]),
    );

    $paths = new ProfilePaths($this->workspace);

    expect($paths->backstoryLabel($profilePath))->toBe('Lore');
});

test('backstoryLabel returns null when the preferences file is missing', function () {
    $profilePath = $this->workspace . '/profiles/alpha';
    mkdir($profilePath, 0755, true);

    $paths = new ProfilePaths($this->workspace);

    expect($paths->backstoryLabel($profilePath))->toBeNull();
});

test('backstoryLabel returns null when the label key is absent', function () {
    $profilePath = $this->workspace . '/profiles/alpha';
    mkdir($profilePath, 0755, true);
    file_put_contents(
        $profilePath . '/preferences.json',
        json_encode(['labels' => ['other' => 'value']]),
    );

    $paths = new ProfilePaths($this->workspace);

    expect($paths->backstoryLabel($profilePath))->toBeNull();
});

test('backstoryLabel returns null for a malformed preferences file', function () {
    $profilePath = $this->workspace . '/profiles/alpha';
    mkdir($profilePath, 0755, true);
    file_put_contents($profilePath . '/preferences.json', 'not json');

    $paths = new ProfilePaths($this->workspace);

    expect($paths->backstoryLabel($profilePath))->toBeNull();
});
