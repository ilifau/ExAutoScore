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
     * Support file for creating the docker image
     */
    CONST PURPOSE_SUPPORT = 'support';

    /**
     * File is added to the submission, just like a required file
     */
    CONST PURPOSE_SUBMIT = 'submit';

    /**
     * File is ignored for the corrector service
     */
    CONST PURPOSE_IGNORE = 'ignore';


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
     * Get the textual description of the purpose
     * @return string
     */
    public function getPurposeText() : string
    {
        $plugin = ilExAutoScorePlugin::getInstance();

        switch ($this->purpose) {
            case self::PURPOSE_DOCKER:
                return $plugin->txt('purpose_docker');
            case self::PURPOSE_SUPPORT:
                return $plugin->txt('purpose_support');
            case self::PURPOSE_SUBMIT:
                return $plugin->txt('purpose_submit');
            case self::PURPOSE_IGNORE:
            default:
                return $plugin->txt('purpose_ignore');
        }
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
            $record->setAssignmentId($assignment_id);
            return $record;
        }

        return array_pop($records);
    }

    /**
     * Get the support files of an assignment (sent when docker image is created)
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

    /**
     * Get the submit files of an assignment (sent together with the submission of a user)
     * @param int $assignment_id
     * @return self[]
     */
    public static function getAssignmentSubmitFiles($assignment_id)
    {
        $records = self::getCollection()
                       ->where(['assignment_id' => $assignment_id])
                       ->where(['purpose' => self::PURPOSE_SUBMIT])
                       ->get();

        return $records;
    }

    /**
     * Get the support files of an assignment
     * @param int $assignment_id
     * @return self[]
     */
    public static function getAssignmentPublicFiles($assignment_id)
    {
        $records = self::getCollection()
                       ->where(['assignment_id' => $assignment_id])
                       ->where(['is_public' => true])
                       ->get();

        return $records;
    }
}
