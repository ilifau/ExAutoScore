<?php
// Copyright (c) 2020 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

use ILIAS\FileUpload\Location;
use ILIAS\FileUpload\DTO\ProcessingStatus;

class ilExAutoScoreContainer extends ActiveRecord
{

    /**
     * @return string
     * @description Return the Name of your Database Table
     */
    public static function returnDbTableName()
    {
        return 'exautoscore_container';
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
     * @var string
     *
     * @con_has_field true
     * @con_fieldtype text
     * @con_length    250
     * @con_is_notnull false
     */
    protected $title;


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
     * @var int
     *
     * @con_has_field true
     * @con_fieldtype integer
     * @con_length    4
     * @con_is_notnull false
     */
    protected $orig_assignment_id;


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
     * @return int
     */
    public function getId() : int
    {
        return $this->id;
    }

    /**
     * @param int $id
     */
    public function setId(int $id)
    {
        $this->id = $id;
    }


    /**
     * @return string
     */
    public function getTitle() : string
    {
        return $this->title;
    }

    /**
     * @param string $title
     */
    public function setTitle(string $title)
    {
        $this->title = $title;
    }

    /**
     * @return string
     */
    public function getFilename() : string
    {
        return $this->filename;
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
    public function getSize() : int
    {
        return $this->size;
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
    public function getHash() : string
    {
        return $this->hash;
    }

    /**
     * @param string $hash
     */
    public function setHash(string $hash)
    {
        $this->hash = $hash;
    }

    /**
     * @return int
     */
    public function getOrigAssignmentId() : int
    {
        return $this->orig_assignment_id;
    }

    /**
     * @param int $orig_assignment_id
     */
    public function setOrigAssignmentId(int $orig_assignment_id)
    {
        $this->orig_assignment_id = $orig_assignment_id;
    }

    /**
     * @return bool
     */
    public function isPublic() : bool
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
     * Get the storage directory for container
     * @return string
     */
    protected function getStorageDirectory() {
        return ilExAutoScorePlugin::getStorageDirectory() . '/' . ilFileSystemStorage::_createPathFromId($this->getId(), 'container');
    }

}
