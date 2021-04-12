<?php
// Copyright (c) 2020 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

class ilExAutoScoreTask extends ActiveRecord
{
    /**
     * Override: name of the database table
     * @var string
     */
    protected $connector_container_name = 'exautoscore_task';

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
     * @con_length    50
     * @con_is_notnull false
     */
    protected $uuid;


    /**
     * @var int
     *
     * @con_has_field  true
     * @con_fieldtype  integer
     * @con_is_notnull false
     * @con_length     4
     */
    protected $user_id;

    /**
     * @var int
     *
     * @con_has_field  true
     * @con_fieldtype  integer
     * @con_is_notnull false
     * @con_length     4
     */
    protected $team_id;


    /**
     * @var string
     *
     * @con_has_field  true
     * @con_fieldtype  timestamp
     * @con_is_notnull false
     */
    protected $submit_time;


    /**
     * @var bool
     *
     * @con_has_field  true
     * @con_fieldtype  integer
     * @con_is_notnull false
     * @con_length     4
     */
    protected $submit_success;


    /**
     * @var string
     *
     * @con_has_field  true
     * @con_fieldtype  text
     * @con_length    250
     * @con_is_notnull false
     */
    protected $submit_message;


    /**
     * @var int
     *
     * @con_has_field  true
     * @con_fieldtype  integer
     * @con_is_notnull false
     * @con_length     4
     */
    protected $task_returncode;


    /**
     * @var float
     *
     * @con_has_field  true
     * @con_fieldtype  float
     * @con_is_notnull false
     * @con_length     4
     */
    protected $task_duration;


    /**
     * @var string
     *
     * @con_has_field  true
     * @con_fieldtype  timestamp
     * @con_is_notnull false
     */
    protected $return_time;


    /**
     * @var float
     *
     * @con_has_field  true
     * @con_fieldtype  float
     * @con_is_notnull false
     */
    protected $return_points;



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
     * Get the records of an assignment
     * @param int $assignment_id
     * @return static
     */
    public static function getExampleTask($assignment_id)
    {
        $records = self::getCollection()
                        ->where(['assignment_id' => $assignment_id])
                        ->where('user_id IS NULL')
                        ->where('team_id IS NULL')
                        ->get();

        if (empty($records)) {
            $task = new self();
            $task->assignment_id = $assignment_id;
            return $task;
        }

        return array_pop($records);
    }

    /**
     * Get the records of an assignment
     * @param int $assignment_id
     * @return static
     */
    public static function getByUuid($uuid)
    {
        $records = self::getCollection()
                       ->where(['uuid' => $uuid])
                       ->get();

        if (empty($records)) {
            return null;
        }
        return array_pop($records);
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
    public function getUuid()
    {
        return $this->uuid;
    }

    /**
     * @param string $uuid
     */
    public function setUuid($uuid)
    {
        $this->uuid = $uuid;
    }

    /**
     * @return int
     */
    public function getUserId()
    {
        return $this->user_id;
    }

    /**
     * @param int $user_id
     */
    public function setUserId(int $user_id)
    {
        $this->user_id = $user_id;
    }

    /**
     * @return int
     */
    public function getTeamId()
    {
        return $this->team_id;
    }

    /**
     * @param int $team_id
     */
    public function setTeamId($team_id)
    {
        $this->team_id = $team_id;
    }

    /**
     * @return string
     */
    public function getSubmitTime()
    {
        return $this->submit_time;
    }

    /**
     * @param string $submit_time
     */
    public function setSubmitTime( $submit_time)
    {
        $this->submit_time = $submit_time;
    }

    /**
     * @return bool
     */
    public function getSubmitSuccess()
    {
        return $this->submit_success;
    }

    /**
     * @param bool $submit_success
     */
    public function setSubmitSuccess( $submit_success)
    {
        $this->submit_success = $submit_success;
    }

    /**
     * @return string
     */
    public function getSubmitMessage()
    {
        return  $this->submit_message;
    }

    /**
     * @param int $submit_message
     */
    public function setSubmitMessage( $submit_message)
    {
        $this->submit_message = $submit_message;
    }

    /**
     * @return int
     */
    public function getReturnCode()
    {
        return $this->task_returncode;
    }

    /**
     * @param int $task_returncode
     */
    public function setReturnCode($task_returncode)
    {
        $this->task_returncode = $task_returncode;
    }

    /**
     * @return float
     */
    public function getTaskDuration()
    {
        return $this->task_duration;
    }

    /**
     * @param float $task_duration
     */
    public function setTaskDuration($task_duration)
    {
        $this->task_duration = $task_duration;
    }

    /**
     * @return string
     */
    public function getReturnTime()
    {
        return $this->return_time;
    }

    /**
     * @param string $return_time
     */
    public function setReturnTime($return_time)
    {
        $this->return_time = $return_time;
    }

    /**
     * @return float
     */
    public function getReturnPoints()
    {
        return $this->return_points;
    }

    /**
     * @param float $return_points
     */
    public function setReturnPoints($return_points)
    {
        $this->return_points = $return_points;
    }

}
