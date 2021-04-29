<?php

class ilExAutoScoreAssignment extends ActiveRecord
{
    /**
     * @var string
     */
    protected $connector_container_name = 'exautoscore_assignment';


    /**
     * @var int
     * @con_is_primary true
     * @con_is_unique  true
     * @con_has_field  true
     * @con_fieldtype  integer
     * @con_is_notnull true
     * @con_length     4
     */
    protected $id;

    /**
     * @var int
     * @con_has_field  true
     * @con_fieldtype  integer
     * @con_length     4
     * @con_is_notnull true
     */
    protected $exercise_id = 0;


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
     * @var string
     *
     * @con_has_field true
     * @con_fieldtype text
     * @con_length    250
     * @con_is_notnull false
     */
    protected $command;


    /**
     * @var float
     *
     * @con_has_field  true
     * @con_fieldtype  float
     * @con_is_notnull false
     */
    protected $min_points;


    /**
     * Wrapper to declare the return type
     * @param  mixed $primary_key
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
     * Reset an already installed correction
     * This also clears the submission results of exercise members
     * @param $assignment_id
     */
    public static function resetCorrection($assignment_id) {
        $ass = self::findOrGetInstance($assignment_id);
        $ass->setUuid('');
        $ass->save();

        require_once (__DIR__ . '/class.ilExAutoScoreTask.php');
        ilExAutoScoreTask::clearAllSubmissions($assignment_id);
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $id
     */
    public function setId(int $id)
    {
        $this->id = $id;

        // reset the exercise id to force a lookup when record is stored
        $this->exercise_id = 0;
    }

    /**
     * @return int
     */
    public function getExerciseId()
    {
        return $this->exercise_id;
    }

    /**
     * @param int $exercise_id
     */
    public function setExerciseId(int $exercise_id)
    {
        $this->exercise_id = $exercise_id;
    }

    /**
     * @return string
     */
    public function getUuid() : string
    {
        return (string) $this->uuid;
    }

    /**
     * @param string $uuid
     */
    public function setUuid(string $uuid)
    {
        $this->uuid = $uuid;
    }

    /**
     * @return string
     */
    public function getCommand() : string
    {
        return (string) $this->command;
    }

    /**
     * @param string $command
     */
    public function setCommand(string $command)
    {
        $this->command = $command;
    }


    /**
     * @return float
     */
    public function getMinPoints() : float
    {
        return (float) $this->min_points;
    }

    /**
     * @param float $min_points
     */
    public function setMinPoints(float $min_points)
    {
        $this->min_points = $min_points;
    }

    /**
     * Save the record
     * ensure the matching exercise id being saved
     */
    public function store() {
        if (empty($this->getExerciseId())) {
            $ass = new ilExAssignment($this->getId());
            $this->setExerciseId($ass->getExerciseId());
        }
        parent::store();
    }
}