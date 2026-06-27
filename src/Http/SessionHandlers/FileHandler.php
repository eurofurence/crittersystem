<?php

declare(strict_types=1);

namespace Engelsystem\Http\SessionHandlers;

use Engelsystem\Database\Database;

class FileHandler extends AbstractHandler
{
    public function __construct(protected Database $database)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function read(string $id): string
    {
        $file_name = $this->getBaseDir() . $id;
        if (file_exists($file_name)) {
            $session_file = fopen($file_name, 'r');
            $res = fread($session_file, filesize($file_name));
            fclose($session_file);
        } else {
            $res = '';
        }
        return $res;
    }

    /**
     * Get base directory path
     */
    private function getBaseDir(): string
    {
        return __DIR__ . '/../../../storage/session/';
    }

    /**
     * {@inheritdoc}
     */
    public function write(string $id, string $data): bool
    {
        file_put_contents($this->getBaseDir() . $id, $data);
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function destroy(string $id): bool
    {
        unlink($this->getBaseDir() . $id);
        return true;
    }
}
