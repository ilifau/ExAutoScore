<?php
// Copyright (c) 2020 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

use ILIAS\FileUpload\Location;
use ILIAS\FileUpload\DTO\ProcessingStatus;

class ilExAutoScoreRequiredFile extends ActiveRecord
{

    /**
     * @return string
     * @description Return the Name of your Database Table
     */
    public static function returnDbTableName()
    {
        return 'exautoscore_req_file';
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
     * @var string
     *
     * @con_has_field true
     * @con_fieldtype text
     * @con_length    2000
     * @con_is_notnull false
     */
    protected $description;


    /**
     * @var string
     *
     * @con_has_field true
     * @con_fieldtype text
     * @con_length    250
     * @con_is_notnull false
     */
    protected $encoding;


    /**
     * @var int
     *
     * @con_has_field  true
     * @con_fieldtype  integer
     * @con_length     4
     * @con_is_notnull false
     */
    protected $max_size;


    /**
     * @var int
     *
     * @con_has_field  true
     * @con_fieldtype  integer
     * @con_length     4
     * @con_is_notnull false
     */
    protected $example_size;

    /**
     * @var string
     *
     * @con_has_field true
     * @con_fieldtype text
     * @con_length    50
     * @con_is_notnull false
     */
    protected $example_hash;


    /**
     * Wrapper to declare the return type
     * @param       $primary_key
     * @param array $add_constructor_args
     * @return self
     */
    public static function findOrGetInstance($primary_key, array $add_constructor_args = array())
    {
        /** @var self $record */
        $record =  parent::findOrGetInstance($primary_key, $add_constructor_args);
        return $record;
    }

    /**
     * Get the selectable encoding options
     */
    public static function getEncodingOptions() {
        return [
            '' => '',
            'ASCII' => 'ASCII',
            'BASE64' => 'BASE64',
            'ISO-8859-1' => 'ISO-8859-1',
            'UTF-8' => 'UTF-8',
            'UTF-16' => 'UTF-16',
            'UTF-32' => 'UTF-32',
            'Windows-1251' => 'Windows-1251',
            'Windows-1252' => 'Windows-1252'
        ];
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
     * @return string
     */
    public function getFilename() : string
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
     * @return string
     */
    public function getEncoding() : string
    {
        return (string) $this->encoding;
    }

    /**
     * @param string $encoding
     */
    public function setEncoding(string $encoding)
    {
        $this->encoding = $encoding;
    }

    /**
     * @return int
     */
    public function getMaxSize() : int
    {
        return (int) $this->max_size;
    }

    /**
     * @param int $max_size
     */
    public function setMaxSize(int $max_size)
    {
        $this->max_size = $max_size;
    }

    /**
     * @return int
     */
    public function getExampleSize() : int
    {
        return (int) $this->example_size;
    }

    /**
     * @param int $example_size
     */
    public function setExampleSize(int $example_size)
    {
        $this->example_size = $example_size;
    }

    /**
     * @return string
     */
    public function getExampleHash() : string
    {
        return (string) $this->example_hash;
    }

    /**
     * @param string $example_hash
     */
    public function setExampleHash(string $example_hash)
    {
        $this->example_hash = $example_hash;
    }


    /**
     * Store an uploaded file
     * @param string $tmpPath   temporary path of the uploaded file
     * @return bool
     */
    public function storeUploadedFile($tmpPath)
    {
        global $DIC;

        $upload = $DIC->upload();
        if (!$upload->hasBeenProcessed()) {
            $upload->process();
        }

        foreach ($upload->getResults() as $result) {
            if ( $result->getPath() == $tmpPath && $result->getStatus() == ProcessingStatus::OK) {

                $this->setExampleHash(md5_file($tmpPath));
                $this->setExampleSize($result->getSize());
                $this->setFilename($result->getName());
                $this->save();

                $upload->moveOneFileTo($result, $this->getStorageDirectory(), Location::STORAGE, 'file'. $this->getId(), true);
                return true;
            }
        }

        return false;
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
     * Get the storage directory for a file
     * @return string
     */
    protected function getStorageDirectory()
    {
        return ilExAutoScorePlugin::getStorageDirectory() . '/' . ilFileSystemStorage::_createPathFromId($this->getAssignmentId(), 'assignment');
    }

    /**
     * Get the stored filename
     * The uploaded filename is not used because it may be insecure
     * @return string
     */
    protected function getStorageFilename()
    {
        return 'example' . $this->getId();
    }

}
