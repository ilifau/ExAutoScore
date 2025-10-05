<?php
declare(strict_types=1);

// Copyright (c) 2020 Institut fuer Lern-Innovation,
// Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

use ILIAS\FileUpload\Location;
use ILIAS\FileUpload\DTO\ProcessingStatus;

abstract class ilExAutoScoreFileBase extends ActiveRecord
{
    /**
     *  Override: name of the sub sub directory for storing the files
     *  z.B. "provided" oder "required"
     * @var string
     */
    protected $storage_sub_directory = '';

    /**
     * @var int
     *
     * @con_is_primary true
     * @con_is_unique  true
     * @con_has_field  true
     * @con_fieldtype  integer
     * @con_is_notnull true
     * @con_length     4
     * @con_sequence   true
     */
    protected ?int $id = null;

    /**
     * @var int
     *
     * @con_is_primary false
     * @con_has_field  true
     * @con_fieldtype  integer
     * @con_is_notnull true
     * @con_length     4
     */
    protected ?int $assignment_id = null;

    /**
     * @var string
     *
     * @con_has_field true
     * @con_fieldtype text
     * @con_length    250
     * @con_is_notnull false
     */
    protected ?string $filename = null;

    /**
     * @var int
     *
     * @con_has_field  true
     * @con_fieldtype  integer
     * @con_length     4
     * @con_is_notnull false
     */
    protected ?int $size = null;

    /**
     * @var string
     *
     * @con_has_field true
     * @con_fieldtype text
     * @con_length    50
     * @con_is_notnull false
     */
    protected ?string $hash = null;

    /**
     * @var string
     *
     * @con_has_field true
     * @con_fieldtype text
     * @con_length    250
     * @con_is_notnull false
     */
    protected ?string $resource_id = null;

    /**
     * Wrapper to declare the return type
     * @return static
     */
    public static function findOrGetInstance($primary_key, array $add_constructor_args = []): self
    {
        /** @var static $record */
        $record = parent::findOrGetInstance($primary_key, $add_constructor_args);
        return $record;
    }

    /**
     * Get the records of an assignment
     * @param int $assignment_id
     * @return static[]
     */
    public static function getForAssignment($assignment_id): array
    {
        $records = self::getCollection()
            ->where(['assignment_id' => $assignment_id])
            ->get();
        return $records;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getAssignmentId(): int
    {
        return (int) $this->assignment_id;
    }

    public function setAssignmentId(int $id): void
    {
        $this->assignment_id = $id;
    }

    public function getFilename(): string
    {
        return (string) $this->filename;
    }

    public function setFilename(string $filename): void
    {
        $this->filename = $filename;
    }

    public function getSize(): int
    {
        return (int) $this->size;
    }

    public function setSize(int $size): void
    {
        $this->size = $size;
    }

    public function getHash(): string
    {
        return (string) $this->hash;
    }

    public function setHash(string $hash): void
    {
        $this->hash = $hash;
    }

    public function getResourceId(): ?string
    {
        return $this->resource_id;
    }

    public function setResourceId(?string $resource_id): void
    {
        $this->resource_id = $resource_id;
    }

    /**
     * Store an uploaded file using ILIAS 9 compatible methods
     */
    public function storeUploadedFile(): bool
    {
        global $DIC;

        $upload = $DIC->upload();
        if (!$upload->hasBeenProcessed()) {
            $upload->process();
        }

        foreach ($upload->getResults() as $result) {
            if ($result->getStatus()->getCode() == ProcessingStatus::OK && is_file($result->getPath())) {
                return $this->storeUploadedFileLegacy($result);
            }
            break; // only process the first uploaded file
        }

        return false;
    }

    /**
     * Store file using ILIAS 9 filesystem
     */
    private function storeUploadedFileLegacy($result): bool
    {
        try {
            $storage_dir = $this->getStorageDirectoryPath();
            $filename = $this->getStorageFilename();

            if (!is_dir($storage_dir)) {
                mkdir($storage_dir, 0755, true);
            }

            $target_file = $storage_dir . '/' . $filename;

            if (copy($result->getPath(), $target_file)) {
                $this->setHash(md5_file($result->getPath()));
                $this->setSize(filesize($target_file));
                $this->setFilename($result->getName());
                $this->setResourceId(null);
                $this->save();
                return true;
            }
        } catch (Exception $e) {
            error_log('ExAutoScore file storage error: ' . $e->getMessage());
        }

        return false;
    }

    /**
     * Download the stored file
     */
    public function downloadFile(): void
    {
        $file_path = $this->getAbsolutePath();

        if ($file_path && is_file($file_path)) {
            ilFileDelivery::deliverFileAttached($file_path, $this->getFilename());
            exit;
        }

        throw new Exception('File not found: ' . ($file_path ?: 'path could not be determined'));
    }

    /**
     * Delete a file
     */
    public function delete(): void
    {
        $file_path = $this->getAbsolutePath();
        if ($file_path && is_file($file_path)) {
            unlink($file_path);

            $dir = dirname($file_path);
            if (is_dir($dir) && count(scandir($dir)) == 2) {
                rmdir($dir);
            }
        }

        parent::delete();
    }

    /**
     * Get the sub directory of the file storage
     */
    protected function getStorageSubDirectory(): string
    {
        return $this->storage_sub_directory;
    }

    /**
     * Get the storage directory path (full system path)
     */
    protected function getStorageDirectoryPath(): string
    {
        return CLIENT_DATA_DIR . '/' . $this->getStorageDirectoryRelative();
    }

    protected function getStorageDirectoryRelative(): string
    {
        $assignment_id = $this->getAssignmentId();
        
        // ILIAS Schema: nimmt die ID als String und splittet sie
        // 117428 → "117428" → Zeichen 0-1: "11", Zeichen 2-3: "74"
        $id_str = (string) $assignment_id;
        
        // Pad mit Nullen falls ID < 4 Stellen
        $id_str = str_pad($id_str, 4, '0', STR_PAD_LEFT);
        
        // Nimm die ersten 2 und dann die nächsten 2 Zeichen
        $level1 = substr($id_str, 0, 2);
        $level2 = substr($id_str, 2, 2);

        $path_parts = [
            ilExAutoScorePlugin::getStorageDirectory(),
            $level1,
            $level2,
            'assignment_' . $assignment_id
        ];

        if (!empty($this->getStorageSubDirectory())) {
            $path_parts[] = $this->getStorageSubDirectory();
        }

        return implode('/', $path_parts);
    }

    /**
     * Get the stored filename
     */
    protected function getStorageFilename(): string
    {
        return 'file' . $this->getId();
    }

    /**
     * Get the full path of the stored file
     * Tries current path first, then legacy paths for backward compatibility
     */
    public function getAbsolutePath(): ?string
    {
        if (empty($this->getId())) {
            return null;
        }

        // 1. Aktueller Pfad
        $current_path = $this->getStorageDirectoryPath() . '/' . $this->getStorageFilename();
        
        // DEBUG
        global $DIC;
        $DIC->logger()->root()->error('ExAutoScore File Path Debug: ' . print_r([
            'file_id' => $this->getId(),
            'filename' => $this->getFilename(),
            'current_path' => $current_path,
            'current_exists' => is_file($current_path),
            'storage_dir' => $this->getStorageDirectoryPath(),
            'dir_exists' => is_dir($this->getStorageDirectoryPath())
        ], true));
        
        if (is_file($current_path)) {
            return $current_path;
        }

        // 2. Legacy-Pfade
        $legacy_tried = [];
        foreach ($this->getLegacyPaths() as $legacy_path) {
            $legacy_tried[] = $legacy_path . ' => ' . (is_file($legacy_path) ? 'EXISTS' : 'missing');
            if (is_file($legacy_path)) {
                return $legacy_path;
            }
        }

        $DIC->logger()->root()->error('ExAutoScore Legacy Paths Tried: ' . print_r($legacy_tried, true));

        return null;
    }

    /**
     * Gibt Legacy-Pfade für ILIAS 7 und ältere Versionen zurück
     */
    protected function getLegacyPaths(): array
    {
        $legacy_paths = [];
        $assignment_id = $this->getAssignmentId();
        $base = CLIENT_DATA_DIR . '/' . ilExAutoScorePlugin::getStorageDirectory();
        $subdir = $this->getStorageSubDirectory();

        // Variante 1: Mit "assignment/" Zwischenverzeichnis
        $path_part1 = sprintf('%02d', ($assignment_id % 100));
        $path_part2 = sprintf('%02d', floor($assignment_id / 100));
        
        $legacy_base1 = $base . '/assignment/' . $path_part1 . '/' . $path_part2;
        if (!empty($subdir)) {
            $legacy_base1 .= '/' . $subdir;
        }
        $legacy_paths[] = $legacy_base1 . '/' . $this->getStorageFilename();
        $legacy_paths[] = $legacy_base1 . '/' . $this->getFilename();

        // Variante 2: Direkt unter assignment_id (ohne Ebenen)
        $legacy_base2 = $base . '/assignment/' . $assignment_id;
        if (!empty($subdir)) {
            $legacy_base2 .= '/' . $subdir;
        }
        $legacy_paths[] = $legacy_base2 . '/' . $this->getStorageFilename();
        $legacy_paths[] = $legacy_base2 . '/' . $this->getFilename();

        // Variante 3: Ohne "assignment/" Zwischenverzeichnis
        $legacy_base3 = $base . '/' . $assignment_id;
        if (!empty($subdir)) {
            $legacy_base3 .= '/' . $subdir;
        }
        $legacy_paths[] = $legacy_base3 . '/' . $this->getStorageFilename();
        $legacy_paths[] = $legacy_base3 . '/' . $this->getFilename();

        return array_unique($legacy_paths);
    }
}