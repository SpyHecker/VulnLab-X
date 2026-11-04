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

/**
 * Visitor preferences, stored in a (serialised) cookie.
 */
class UserPreferences
{
    /** @var string Preferred colour scheme. */
    public $theme = 'light';

    /** @var string Interface language. */
    public $language = 'en';

    /** @var array Recently viewed product ids. */
    public $recent = [];

    /**
     * Read the preferences from the `prefs` cookie.
     */
    public static function load(): self
    {
        if (!empty($_COOKIE['prefs'])) {
            $decoded = base64_decode((string) $_COOKIE['prefs'], true);

            if ($decoded !== false) {
                // VULNERABILITY [DESER-1]: the cookie is base64 encoded but not
                // signed, and its contents are handed to unserialize(). Any
                // object in the payload is instantiated, which makes the classes
                // in this file available as a gadget chain.
                $prefs = @unserialize($decoded);

                if ($prefs instanceof self) {
                    return $prefs;
                }
            }
        }

        return new self();
    }

    /**
     * Persist the preferences back into the cookie.
     */
    public function save(): void
    {
        setcookie(
            'prefs',
            base64_encode(serialize($this)),
            time() + 60 * 60 * 24 * 365,
            '/'
        );
    }
}
