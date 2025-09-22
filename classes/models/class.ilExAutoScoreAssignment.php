<?php
declare(strict_types=1);


class ilExAutoScoreAssignment extends ActiveRecord
{
    /**
     * @var string
     */
    public static function returnDbTableName(): string
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
    protected ?int $id = null;

    /**
     * @var int
     * @con_has_field  true
     * @con_fieldtype  integer
     * @con_length     4
     * @con_is_notnull true
     */
    protected int $exercise_id = 0;


    /**
     * @var string
     *
     * @con_has_field true
     * @con_fieldtype text
     * @con_length    50
     * @con_is_notnull false
     */
    protected ?string $uuid = null;


    /**
     * @var string
     *
     * @con_has_field true
     * @con_fieldtype text
     * @con_length    250
     * @con_is_notnull false
     */
    protected ?string $command = null;


    /**
     * @var float
     *
     * @con_has_field  true
     * @con_fieldtype  float
     * @con_is_notnull false
     */
    protected ?float $min_points = null;


    /**
     * @var string
     *
     * @con_has_field true
     * @con_fieldtype text
     * @con_length    250
     * @con_is_notnull false
     */
    protected ?string $failure_mails = null;



    /**
     * Wrapper to declare the return type
     * @param  mixed $primary_key
     * @param array $add_constructor_args
     * @return self
     */
    public static function findOrGetInstance($primary_key, array $add_constructor_args = array()): self
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
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @param int $id
     */
    public function setId(int $id): void
    {
        $this->id = $id;

        // reset the exercise id to force a lookup when record is stored
        $this->exercise_id = 0;
    }

    /**
     * @return int
     */
    public function getExerciseId(): int
    {
        return $this->exercise_id;
    }

    /**
     * @param int $exercise_id
     */
    public function setExerciseId(int $exercise_id): void
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
    public function setUuid(string $uuid): void
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
    public function setCommand(string $command): void
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
    public function setMinPoints(float $min_points): void
    {
        $this->min_points = $min_points;
    }


    /**
     * @return string
     */
    public function getFailureMails() : string
    {
        return (string) $this->failure_mails;
    }

    /**
     * @param string $failure_mails
     */
    public function setFailureMails(string $failure_mails): void
    {
        $this->failure_mails = $failure_mails;
    }


    /**
     * Save the record
     * ensure the matching exercise id being saved
     */
    public function store(): void {
        if (empty($this->getExerciseId())) {
            $ass = new ilExAssignment($this->getId());
            $this->setExerciseId($ass->getExerciseId());
        }
        parent::store();
    }
}