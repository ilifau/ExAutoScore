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
     * @con_has_field true
     * @con_fieldtype integer
     * @con_length    4
     * @con_is_notnull true
     */
    protected $container_id = 0;

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
     * @return int
     */
    public function getContainerId() : int
    {
        return $this->container_id;
    }

    /**
     * @param int $container_id
     */
    public function setContainerId(int $container_id)
    {
        $this->container_id = $container_id;
    }

}