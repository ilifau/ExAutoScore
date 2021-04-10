<?php
// Copyright (c) 2020 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

require_once(__DIR__ . '/class.ilExAutoScoreFileBase.php');

class ilExAutoScoreProvidedFile extends ilExAutoScoreFileBase
{
    /**
     * Override: name of the database table
     * @var string
     */
    protected $connector_container_name = 'exautoscore_prov_file';

    /**
     *  Override: name of the sub sub directory for storing the files
     * @var string
     */
    protected $storage_sub_directory = 'provided';


    /**
     * Dockerfile to create the container image
     */
    CONST PURPOSE_DOCKER = 'docker';

    /**
     * Support file for running the test
     */
    CONST PURPOSE_SUPPORT = 'support';


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

    /**
     * Get the support files of an assignment
     * @param int $assignment_id
     * @return self[]
     */
    public static function getAssignmentSupportFiles($assignment_id)
    {
        $records = self::getCollection()
                       ->where(['assignment_id' => $assignment_id])
                       ->where(['purpose' => self::PURPOSE_SUPPORT])
                       ->get();

        return $records;
    }
}
