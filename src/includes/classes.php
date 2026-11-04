<?php
/**
 * Small helper classes.
 */

/**
 * Naive file cache used by the admin dashboard statistics.
 */
class Cache
{
    /** @var string Absolute path of the cache file. */
    public $path;

    /** @var string|null Pending payload. */
    public $data;

    public function __construct($path = '')
    {
        $this->path = $path;
        $this->data = null;
    }

    public function exists(): bool
    {
        return $this->path !== '' && is_file($this->path);
    }

    public function read(): ?string
    {
        if (!$this->exists()) {
            return null;
        }

        $contents = @file_get_contents($this->path);

        return $contents === false ? null : $contents;
    }

    public function isFresh(int $maxAge = 60): bool
    {
        return $this->exists() && (time() - (int) @filemtime($this->path)) < $maxAge;
    }

    public function write($data): void
    {
        $this->data = is_string($data) ? $data : (string) json_encode($data);
        $this->flush();
    }

    public function flush(): void
    {
        if ($this->path === '') {
            return;
        }

        $directory = dirname($this->path);

        if (!is_dir($directory)) {
            @mkdir($directory, 0777, true);
        }

        @file_put_contents($this->path, (string) $this->data);
    }

    public function delete(): void
    {
        $this->data = null;

        if ($this->exists()) {
            @unlink($this->path);
        }
    }

    /**
     * Pending data is persisted when the object goes away.
     */
    public function __destruct()
    {
        if ($this->data !== null) {
            $this->flush();
        }
    }
}
