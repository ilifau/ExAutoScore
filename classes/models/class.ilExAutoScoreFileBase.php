<?php
// Copyright (c) 2020 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

use ILIAS\FileUpload\Location;
use ILIAS\FileUpload\DTO\ProcessingStatus;

abstract class ilExAutoScoreFileBase extends ActiveRecord
{
    /**
     * Override: name of the database table
     * @var string
     */
    protected $connector_container_name = '';

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
    protected $id;

    /**
     * @var int
     *
     * @con_is_primary false
     * @con_has_field  true
     * @con_fieldtype  integer
     * @con_is_notnull true
     * @con_length     4
     */
    protected $assignment_id;


    /**
     * @var string
     *
     * @con_has_field true
     * @con_fieldtype text
     * @con_length    250
     * @con_is_notnull false
     */
    protected $filename;

    /**
     * @var int
     *
     * @con_has_field  true
     * @con_fieldtype  integer
     * @con_length     4
     * @con_is_notnull false
     */
    protected $size;

    /**
     * @var string
     *
     * @con_has_field true
     * @con_fieldtype text
     * @con_length    50
     * @con_is_notnull false
     */
    protected $hash;



    /**
     * Wrapper to declare the return type
     * @return static
     */
    public static function findOrGetInstance($primary_key, array $add_constructor_args = array())
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
    public static function getForAssignment($assignment_id)
    {
        $records = self::getCollection()
                       ->where(['assignment_id' => $assignment_id])
                       ->get();
        return $records;
    }


    /**
     * @return int
     */
    public function getId()
    {
        return (int) $this->id;
    }

    /**
     * @param int $id
     */
    public function setId(int $id)
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
    public function setAssignmentId(int $id)
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
    public function setFilename(string $filename)
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
    public function setSize(int $size)
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
    public function setHash(string $hash)
    {
        $this->hash = $hash;
    }


    /**
     * Store an uploaded file
     * Only one file should be uploaded because there is no clean way to identify the related form property
     * The record kees unchanged, if storing fails (e.g. no file is uploaded),
     *
     * @return bool             true, if file ist stored, false if not
     */
    public function storeUploadedFile()
    {
        global $DIC;

        $upload = $DIC->upload();
        if (!$upload->hasBeenProcessed()) {
            $upload->process();
        }

        foreach ($upload->getResults() as $result) {
            if ($result->getStatus() == ProcessingStatus::OK && is_file($result->getPath())) {
                $this->setHash(md5_file($result->getPath()));
                $this->setSize($result->getSize());
                $this->setFilename($result->getName());
                $this->save();

                $upload->moveOneFileTo($result, $this->getStorageDirectory(), Location::STORAGE, $this->getStorageFilename(), true);
                return true;
            }

            // only process the first uploaded file
            break;
        }

        return false;
    }

    /**
     * Download the stored file
     */
    public function downloadFile()
    {
        ilFileDelivery::deliverFileAttached(
            $this->getAbsolutePath(),
            $this->getFilename()
        );
        exit;
    }


    /**
     * Delete a file
     */
    public function delete()
    {
        global $DIC;

        $storage = $DIC->filesystem()->storage();
        if ($storage->has($this->getStorageDirectory() . '/' . $this->getStorageFilename())) {
            $storage->delete($this->getStorageDirectory() . '/' . $this->getStorageFilename());
        }

        if ($storage->hasDir($this->getStorageDirectory()) && empty($storage->listContents($this->getStorageDirectory()))) {
            $storage->deleteDir($this->getStorageDirectory());
        }
        parent::delete();
    }

    /**
     * Get the sub directory of the file storage
     */
    protected function getStorageSubDirectory()
    {
        return $this->storage_sub_directory;
    }

    /**
     * Get the storage directory for a file
     * @return string
     */
    protected function getStorageDirectory()
    {
        return ilExAutoScorePlugin::getStorageDirectory() . '/'
            . ilFileSystemStorage::_createPathFromId($this->getAssignmentId(), 'assignment')
            . '/' . $this->getStorageSubDirectory();
    }

    /**
     * Get the stored filename
     * The uploaded filename is not used because it may be insecure
     * @return string
     */
    protected function getStorageFilename()
    {
        return 'file' . $this->getId();
    }

    /**
     * Get the full path of the stored file
     * @return string|null
     */
    public function getAbsolutePath()
    {
        global $DIC;

        $storage = $DIC->filesystem()->storage();
        if ($storage->has($this->getStorageDirectory() . '/' . $this->getStorageFilename())) {
            return CLIENT_DATA_DIR . '/' . $this->getStorageDirectory() . '/' . $this->getStorageFilename();
        }
        else {
            return null;
        }
    }

    /**
     * Get the relative path of the file in the storage
     * @return string
     */
    public function getRelativePath()
    {
        return  $this->getStorageDirectory() . '/' . $this->getStorageFilename();
    }
}
