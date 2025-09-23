<?php
declare(strict_types=1);

// Copyright (c) 2020 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

use ILIAS\FileUpload\Location;
use ILIAS\FileUpload\DTO\ProcessingStatus;

abstract class ilExAutoScoreFileBase extends ActiveRecord
{

    /**
     *  Override: name of the sub sub directory for storing the files
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

    #abstract public static function returnDbTableName(): string;

    /**
     * Wrapper to declare the return type
     * @return static
     */
    public static function findOrGetInstance($primary_key, array $add_constructor_args = []): self
    {
        /** @var static $record */
        $record =  parent::findOrGetInstance($primary_key, $add_constructor_args);
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


    /**
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @param int $id
     */
    public function setId(int $id): void
    {
        $this->id = $id;
    }

    /**
     * @return int
     */
    public function getAssignmentId(): int
    {
        return (int) $this->assignment_id;
    }

    /**
     * @param int $id
     */
    public function setAssignmentId(int $id): void
    {
        $this->assignment_id = $id;
    }

    /**
     * @return string
     */
    public function getFilename(): string
    {
        return (string) $this->filename;
    }

    /**
     * @param string $filename
     */
    public function setFilename(string $filename): void
    {
        $this->filename = $filename;
    }

    /**
     * @return int
     */
    public function getSize(): int
    {
        return (int) $this->size;
    }

    /**
     * @param int $size
     */
    public function setSize(int $size): void
    {
        $this->size = $size;
    }

    /**
     * @return string
     */
    public function getHash(): string
    {
        return (string) $this->hash;
    }

    /**
     * @param string $hash
     */
    public function setHash(string $hash): void
    {
        $this->hash = $hash;
    }

    /**
     * @return string|null
     */
    public function getResourceId(): ?string
    {
        return $this->resource_id;
    }

    /**
     * @param string|null $resource_id
     */
    public function setResourceId(?string $resource_id): void
    {
        $this->resource_id = $resource_id;
    }

    /**
     * Store an uploaded file using ILIAS 9 compatible methods
     *
     * @return bool true, if file ist stored, false if not
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

            // only process the first uploaded file
            break;
        }

        return false;
    }

    /**
     * Store file using ILIAS 9 filesystem
     */
    private function storeUploadedFileLegacy($result): bool
    {
        try {
            // Create directory structure manually - no ilFileSystemStorage needed
            $storage_dir = $this->getStorageDirectoryPath();
            $filename = $this->getStorageFilename();
            
            // Ensure directory exists
            if (!is_dir($storage_dir)) {
                mkdir($storage_dir, 0755, true);
            }
            
            $target_file = $storage_dir . '/' . $filename;
            
            // Copy file
            if (copy($result->getPath(), $target_file)) {
                // Set the properties - get file size from actual file, not from result
                $this->setHash(md5_file($result->getPath()));
                $this->setSize(filesize($target_file));  // ← Verwende filesize() statt $result->getSize()
                $this->setFilename($result->getName());
                $this->setResourceId(null); // Mark as legacy storage
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
        } else {
            throw new Exception('File not found');
        }
        
        exit;
    }

    /**
     * Delete a file
     */
    public function delete(): void
    {
        // Delete the physical file
        $file_path = $this->getAbsolutePath();
        if ($file_path && is_file($file_path)) {
            unlink($file_path);
            
            // Try to remove empty directories
            $dir = dirname($file_path);
            if (is_dir($dir) && count(scandir($dir)) == 2) { // only . and ..
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
     * @return string
     */
    protected function getStorageDirectoryPath(): string
    {
        return CLIENT_DATA_DIR . '/' . $this->getStorageDirectoryRelative();
    }

    /**
     * Get the relative storage directory path
     * @return string
     */
    protected function getStorageDirectoryRelative(): string
    {
        // Create a path structure similar to what ilFileSystemStorage would create
        $assignment_id = $this->getAssignmentId();
        $path_from_id = sprintf('%03d', ($assignment_id % 1000)) . '/' . sprintf('%03d', (int)($assignment_id / 1000));
        
        return ilExAutoScorePlugin::getStorageDirectory() . '/assignment/' 
               . $path_from_id . '/' . $this->getStorageSubDirectory();
    }

    /**
     * Get the storage directory for a file (for compatibility)
     * @return string
     */
    protected function getStorageDirectory(): string
    {
        return $this->getStorageDirectoryRelative();
    }

    /**
     * Get the stored filename
     * The uploaded filename is not used because it may be insecure
     * @return string
     */
    protected function getStorageFilename(): string
    {
        return 'file' . $this->getId();
    }

    /**
     * Get the full path of the stored file
     * @return string|null
     */
    public function getAbsolutePath(): ?string
    {
        $file_path = $this->getStorageDirectoryPath() . '/' . $this->getStorageFilename();
        
        if (is_file($file_path)) {
            return $file_path;
        }
        
        return null;
    }

    /**
     * Get the relative path of the file in the storage
     * @return string
     */
    public function getRelativePath(): string
    {
        return $this->getStorageDirectoryRelative() . '/' . $this->getStorageFilename();
    }
}