<?php
// Copyright (c) 2020 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

use ILIAS\FileUpload\Location;
use ILIAS\FileUpload\DTO\ProcessingStatus;

class ilExAutoScoreProvidedFile extends ActiveRecord
{
    /**
     * Dockerfile to create the container image
     */
    CONST PURPOSE_DOCKER = 'docker';

    /**
     * Support file for running the test
     */
    CONST PURPOSE_SUPPORT = 'support';

    /**
     * Example file for a solution
     */
    CONST PURPOSE_EXAMPLE = 'example';


    /**
     * @return string
     * @description Return the Name of your Database Table
     */
    public static function returnDbTableName()
    {
        return 'exautoscore_prov_file';
    }

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
     * @con_is_unique  true
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
     * @var string
     *
     * @con_has_field true
     * @con_fieldtype text
     * @con_length    10
     * @con_is_notnull false
     */
    protected $purpose;


    /**
     * @var string
     *
     * @con_has_field true
     * @con_fieldtype text
     * @con_length    250
     * @con_is_notnull false
     */
    protected $description;

    /**
     * @var bool
     *
     * @con_has_field true
     * @con_fieldtype integer
     * @con_length    4
     * @con_is_notnull false
     */
    protected $is_public;


    /**
     * Wrapper to declare the return type
     * @return self
     */
    public static function findOrGetInstance($primary_key, array $add_constructor_args = array())
    {
        /** @var self $record */
        $record =  parent::findOrGetInstance($primary_key, $add_constructor_args);
        return $record;
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
     * @return string
     */
    public function getPurpose() : string
    {
        return (string) $this->purpose;
    }

    /**
     * @param string $purpose
     */
    public function setPurpose(string $purpose)
    {
        $this->purpose = $purpose;
    }

    /**
     * @return string
     */
    public function getDescription() : string
    {
        return (string) $this->description;
    }

    /**
     * @param string $description
     */
    public function setDescription(string $description)
    {
        $this->description = $description;
    }


    /**
     * @return bool
     */
    public function isPublic()
    {
        return $this->is_public;
    }

    /**
     * @param bool $is_public
     */
    public function setPublic(bool $is_public)
    {
        $this->is_public = $is_public;
    }

    /**
     * Store an uploaded container file
     * @param string $tmpPath   temporary path of the uploaded file
     * @return bool
     */
    public function storeUploadedFile($tmpPath) {
        global $DIC;

        $upload = $DIC->upload();
        if (!$upload->hasBeenProcessed()) {
            $upload->process();
        }

        foreach ($upload->getResults() as $result) {
            if ( $result->getPath() == $tmpPath && $result->getStatus() == ProcessingStatus::OK) {

                $this->setHash(md5_file($tmpPath));
                $this->setSize($result->getSize());
                $this->setFilename($result->getName());
                $this->save();

                $upload->moveOneFileTo($result, $this->getStorageDirectory(), Location::STORAGE);
                return true;
            }
        }

        return false;
    }

    /**
     * Delete a container (extended to delete the container file)
     */
    public function delete()
    {
        global $DIC;

        $storage = $DIC->filesystem()->storage();
        if ($storage->hasDir($this->getStorageDirectory())) {
            $storage->deleteDir($this->getStorageDirectory());
        }
        parent::delete();
    }


    /**
     * Get the storage directory for container
     * @return string
     */
    protected function getStorageDirectory()
    {
        return ilExAutoScorePlugin::getStorageDirectory() . '/' . ilFileSystemStorage::_createPathFromId($this->getId(), 'container');
    }


    /**
     * Get the Dockerfile of an assignment
     * @param int $assignment_id
     * @return self
     */
    public static function getAssignmentDocker($assignment_id)
    {
        $records = self::getCollection()
            ->where(['assignment_id' => $assignment_id])
            ->where(['purpose' => self::PURPOSE_DOCKER])
            ->get();

        if (empty($records)) {
            $record = new self;
            $record->setPurpose(self::PURPOSE_DOCKER);
            return $record;
        }

        return array_pop($records);
    }
}
