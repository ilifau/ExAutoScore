<?php

class ilExAutoScoreAssignment extends ActiveRecord
{
    /**
     * @return string
     * @description Return the Name of your Database Table
     */
    public static function returnDbTableName()
    {
        return 'exautoscore_assignment';
    }

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
     * @con_length    250
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