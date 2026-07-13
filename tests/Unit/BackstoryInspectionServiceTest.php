<?php

declare(strict_types=1);

use CoquiBot\Toolkits\Backstory\BackstoryAssembler;
use CoquiBot\Toolkits\Backstory\BackstoryInspectionService;
use CoquiBot\Toolkits\Backstory\Support\ProfilePaths;

beforeEach(function () {
    $this->workspace = makeTempDir();
    $this->activeProfile = 'caelum';
    $this->profilePath = $this->workspace . '/profiles/' . $this->activeProfile;
    $this->backstoryDir = $this->profilePath . '/backstory';
    mkdir($this->backstoryDir, 0o755, true);

    $this->profilePaths = new ProfilePaths($this->workspace);
    $this->service = new BackstoryInspectionService($this->workspace, $this->profilePaths);
});

afterEach(function () {
    cleanupTestTree($this->workspace);
});

test('no profile yields an unprofiled payload', function () {
    $payload = $this->service->inspect(null);

    expect($payload['profile'])->toBeNull();
    expect($payload['available'])->toBeFalse();
    expect($payload['reason'])->toBe('no_active_profile');
    expect($payload['files'])->toBe([]);
});

test('empty string profile yields an unprofiled payload', function () {
    $payload = $this->service->inspect('   ');

    expect($payload['available'])->toBeFalse();
    expect($payload['reason'])->toBe('no_active_profile');
});

test('unknown profile throws', function () {
    expect(fn () => $this->service->inspect('missing'))
        ->toThrow(InvalidArgumentException::class);
});

test('reports source folder without a generated backstory', function () {
    file_put_contents($this->backstoryDir . '/note.txt', 'content');

    $payload = $this->service->inspect($this->activeProfile);

    expect($payload['available'])->toBeTrue();
    expect($payload['profile'])->toBe($this->activeProfile);
    expect($payload['source_folder_exists'])->toBeTrue();
    expect($payload['has_generated_backstory'])->toBeFalse();
    expect($payload['source_folder'])->toBe('profiles/' . $this->activeProfile . '/backstory');
});

test('inventory reports supported and unsupported entries after generation', function () {
    file_put_contents($this->backstoryDir . '/story.txt', 'A quiet beginning.');
    file_put_contents($this->backstoryDir . '/payload.exe', 'skip me');

    (new BackstoryAssembler())->generate($this->profilePath);

    $payload = $this->service->inspect($this->activeProfile);

    expect($payload['has_generated_backstory'])->toBeTrue();
    expect($payload['total_files'])->toBe(2);
    expect($payload['supported_file_count'])->toBe(1);
    expect($payload['unsupported_file_count'])->toBe(1);
    expect($payload['failed_file_count'])->toBe(0);

    expect($payload['files'])->toHaveCount(1);
    expect($payload['files'][0]['relative_path'])->toBe('story.txt');
    expect($payload['files'][0]['status'])->toBe('ok');
    expect($payload['files'][0]['path'])->toBe('profiles/' . $this->activeProfile . '/backstory/story.txt');

    expect($payload['unsupported_files'])->toHaveCount(1);
    expect($payload['unsupported_files'][0]['relative_path'])->toBe('payload.exe');
    expect($payload['unsupported_files'][0]['extension'])->toBe('exe');
});

test('needs_regeneration flips when a source file changes after generation', function () {
    $source = $this->backstoryDir . '/story.txt';
    file_put_contents($source, 'original');

    $assembler = new BackstoryAssembler();
    $assembler->generate($this->profilePath);

    expect($this->service->inspect($this->activeProfile)['needs_regeneration'])->toBeFalse();

    file_put_contents($source, 'changed content');

    expect($this->service->inspect($this->activeProfile)['needs_regeneration'])->toBeTrue();
});

test('profile paths resolve workspace-relative paths for the active profile', function () {
    // A second profile must not shift resolution off the active one.
    mkdir($this->workspace . '/profiles/other/backstory', 0o755, true);
    file_put_contents($this->backstoryDir . '/note.txt', 'content');

    $payload = $this->service->inspect($this->activeProfile);

    expect($payload['generated_backstory_path'])->toBe('profiles/' . $this->activeProfile . '/backstory.md');
});
