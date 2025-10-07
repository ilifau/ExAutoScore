<?php
declare(strict_types=1);

// Copyright (c) 2020 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

class ilExAutoScoreTask extends ActiveRecord
{
    /**
     * Override: name of the database table
     * @var string
     */
    public static function returnDbTableName(): string
    {
        return 'exautoscore_task';
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
    protected ?int $id = null;

    /**
     * @var int
     *
     * @con_has_field  true
     * @con_fieldtype  integer
     * @con_is_notnull true
     * @con_length     4
     */
    protected ?int $assignment_id = null;


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
     * @var int
     *
     * @con_has_field  true
     * @con_fieldtype  integer
     * @con_is_notnull false
     * @con_length     4
     */
    protected ?int $user_id = null;

    /**
     * @var int
     *
     * @con_has_field  true
     * @con_fieldtype  integer
     * @con_is_notnull false
     * @con_length     4
     */
    protected ?int $team_id = null;


    /**
     * @var string
     *
     * @con_has_field  true
     * @con_fieldtype  timestamp
     * @con_is_notnull false
     */
    protected ?string $submit_time = null;


    /**
     * @var bool
     *
     * @con_has_field  true
     * @con_fieldtype  integer
     * @con_is_notnull false
     * @con_length     4
     */
    protected ?bool $submit_success = null;


    /**
     * @var string
     *
     * @con_has_field  true
     * @con_fieldtype  text
     * @con_length    250
     * @con_is_notnull false
     */
    protected ?string $submit_message = null;


    /**
     * @var int
     *
     * @con_has_field  true
     * @con_fieldtype  integer
     * @con_is_notnull false
     * @con_length     4
     */
    protected ?int $task_returncode = null;


    /**
     * @var float
     *
     * @con_has_field  true
     * @con_fieldtype  float
     * @con_length     4
     * @con_is_notnull false
     */
    protected ?float $task_duration = null;


    /**
     * @var string
     *
     * @con_has_field  true
     * @con_fieldtype  timestamp
     * @con_is_notnull false
     */
    protected ?string $return_time = null;


    /**
     * @var float
     *
     * @con_has_field  true
     * @con_fieldtype  float
     * @con_is_notnull false
     */
    protected ?float $return_points = null;


    /**
     * @var string
     *
     * @con_has_field  true
     * @con_fieldtype  text
     * @con_length    4000
     * @con_is_notnull false
     */
    protected ?string $instant_message = null;


    /**
     * @var string
     *
     * @con_has_field  true
     * @con_fieldtype  text
     * @con_length    10
     * @con_is_notnull false
     */
    protected ?string $instant_status = null;


    /**
     * @var string
     *
     * @con_has_field  true
     * @con_fieldtype  text
     * @con_length    10
     * @con_is_notnull false
     */
    protected ?string $protected_status = null;

    /**
     * @var string
     *
     * @con_has_field  true
     * @con_fieldtype  text
     * @con_length    4000
     * @con_is_notnull false
     */
    protected ?string $protected_feedback_text = null;


    /**
     * @var string
     *
     * @con_has_field  true
     * @con_fieldtype  clob
     * @con_is_notnull false
     */
    protected ?string $protected_feedback_html = null;

    /**
     * @var string
     * @con_has_field  true
     * @con_fieldtype  clob
     * @con_is_notnull false
     */
    protected ?string $debug_logs = null;

    /**
     * Wrapper to declare the return type
     * @return static
     */
    public static function findOrGetInstance($primary_key, array $add_constructor_args = array()): ilExAutoScoreTask
    {
        /** @var static $record */
        $record =  parent::findOrGetInstance($primary_key, $add_constructor_args);
        return $record;
    }

    /**
     * Check if user or team tasks exist
     * @param $assignment_id
     * @return bool
     * @throws Exception
     */
    public static function hasTasks($assignment_id): bool
    {
        $exists = self::getCollection()
                      ->where(['assignment_id' => $assignment_id])
                      ->where('(user_id IS NOT NULL OR team_id IS NOT NULL)')
                      ->hasSets();
        return $exists;
    }

    /**
     * Check if submissions were already sent to the server
     * @param $assignment_id
     * @return bool
     * @throws Exception
     */
    public static function hasSubmissions($assignment_id): bool
    {
        $exists = self::getCollection()
                       ->where(['assignment_id' => $assignment_id])
                       ->where('submit_time IS NOT NULL')
                       ->where('(user_id IS NOT NULL OR team_id IS NOT NULL)')
                       ->hasSets();
        return $exists;
    }


    /**
     * Clear all submission data of an assignment
     * @param $assignment_id
     */
    public static function clearAllSubmissions($assignment_id)
    {
        $records = self::getCollection()
                       ->where(['assignment_id' => $assignment_id])
                       ->where('submit_time IS NOT NULL')
                       ->get();

        /** @var self $task */
        foreach($records as $task) {
            $task->clearSubmissionData();
            $task->save();
            $task->updateMemberStatus();
        }
    }


    /**
     * Update status of all submission data of an assignment
     * @param $assignment_id
     */
    public static function updateAllSubmissions($assignment_id)
    {
        $records = self::getCollection()
                       ->where(['assignment_id' => $assignment_id])
                       ->where('submit_time IS NOT NULL')
                       ->get();

        /** @var self $task */
        foreach($records as $task) {
            $task->updateMemberStatus();
        }
    }

    /**
     * Get the records of an assignment
     * @param int $assignment_id
     * @return static[]
     */
    public static function getForAssignment($assignment_id): array
    {
        $records = self::getCollection()
                       ->where(['assignment_id' => $assignment_id])
                       ->get();
        return $records;
    }

    /**
     * Get the task by a submission
     * @param ilExSubmission $a_submission
     * @return self
     */
    public static function getSubmissionTask(ilExSubmission $a_submission): self
    {
        if ($a_submission->getTeam() instanceof ilExAssignmentTeam) {

            // file submissions are assigned to the members
            // but the correction task is assigned to the team
            // because all team members get the same result of the correction
            return self::getTeamTask($a_submission->getAssignment()->getId(), $a_submission->getTeam()->getId());
        }
        else {
            return self::getUserTask($a_submission->getAssignment()->getId(), $a_submission->getUserId());
        }
    }

    /**
     * Get the task of a single user
     * @param int $a_assignment_id
     * @param int $a_user_id
     * @return self
     */
    public static function getUserTask($a_assignment_id, $a_user_id): self
    {
        $records = self::getCollection()
                       ->where(['assignment_id' => $a_assignment_id])
                       ->where(['user_id' => $a_user_id])
                       ->get();

        if (empty($records)) {
            $task = new self;
            $task->setAssignmentId($a_assignment_id);
            $task->setUserId($a_user_id);
            return $task;
        }

        return array_pop($records);
    }


    /**
     * Get the task of a team
     * @param int $a_assignment_id
     * @param int $a_team_id
     * @return self
     */
    public static function getTeamTask($a_assignment_id, $a_team_id): self
    {
        $records = self::getCollection()
                       ->where(['assignment_id' => $a_assignment_id])
                       ->where(['team_id' => $a_team_id])
                       ->get();

        if (empty($records)) {
            $task = new self;
            $task->setAssignmentId($a_assignment_id);
            $task->setTeamId($a_team_id);
            return $task;
        }

        return array_pop($records);
    }


    /**
     * Get the records of an assignment
     * @param int $assignment_id
     * @return static
     */
    public static function getExampleTask($assignment_id): self
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
     * Clear all submission data of an assignment
     * @param $assignment_id
     */
    public static function clearExampleTask($assignment_id)
    {
        $task = self::getExampleTask($assignment_id);
        $task->clearSubmissionData();
        $task->save();
    }



    /**
     * Get the records of an assignment
     * @param int $assignment_id
     * @return static
     */
    public static function getByUuid($uuid): ?self
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
     * Clear the return date when a new task execution is called
     */
    public function clearSubmissionData()
    {
        $this->setUuid(null);
        $this->setSubmitMessage(null);
        $this->setSubmitTime(null);
        $this->setSubmitSuccess(null);
        $this->setReturnTime(null);
        $this->setReturnPoints(null);
        $this->setReturncode(null);
        $this->setTaskDuration(null);
        $this->setInstantMessage(null);
        $this->setInstantStatus(null);
        $this->setProtectedStatus(null);
        $this->setProtectedFeedbackText(null);
        $this->setProtectedFeedbackHtml(null);
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
    public function setAssignmentId(int $id): void
    {
        $this->assignment_id = $id;
    }

    /**
     * @return string|null
     */
    public function getUuid(): ?string
    {
        return $this->uuid;
    }

    /**
     * @param string|null $uuid
     */
    public function setUuid(?string $uuid): void
    {
        $this->uuid = $uuid;
    }

    /**
     * @return int|null
     */
    public function getUserId(): ?int
    {
        return $this->user_id;
    }

    /**
     * @param int $user_id
     */
    public function setUserId(int $user_id): void
    {
        $this->user_id = $user_id;
    }

    /**
     * @return int|null
     */
    public function getTeamId(): ?int
    {
        return $this->team_id;
    }

    /**
     * @param int|null $team_id
     */
    public function setTeamId(?int $team_id): void
    {
        $this->team_id = $team_id;
    }

    /**
     * @return string|null
     */
    public function getSubmitTime(): ?string
    {
        return $this->submit_time;
    }

    /**
     * @param string|null $submit_time
     */
    public function setSubmitTime(?string $submit_time): void
    {
        $this->submit_time = $submit_time;
    }

    /**
     * @return bool|null
     */
    public function getSubmitSuccess(): ?bool
    {
        return $this->submit_success;
    }

    /**
     * @param bool|null $submit_success
     */
    public function setSubmitSuccess(?bool $submit_success): void
    {
        $this->submit_success = $submit_success;
    }

    /**
     * @return string|null
     */
    public function getSubmitMessage(): ?string
    {
        return $this->submit_message;
    }

    /**
     * @param string|null $submit_message
     */
    public function setSubmitMessage(?string $submit_message): void
    {
        $this->submit_message = $submit_message;
    }

    /**
     * @return int|null
     */
    public function getReturnCode(): ?int
    {
        return $this->task_returncode;
    }

    /**
     * @param int|null $task_returncode
     */
    public function setReturnCode(?int $task_returncode): void
    {
        $this->task_returncode = $task_returncode;
    }

    /**
     * @return float|null
     */
    public function getTaskDuration(): ?float
    {
        return $this->task_duration;
    }

    /**
     * @param float|null $task_duration
     */
    public function setTaskDuration(?float $task_duration): void
    {
        $this->task_duration = $task_duration;
    }

    /**
     * @return string|null
     */
    public function getReturnTime(): ?string
    {
        return $this->return_time;
    }

    /**
     * @param string|null $return_time
     */
    public function setReturnTime(?string $return_time): void
    {
        $this->return_time = $return_time;
    }

    /**
     * @return float|null
     */
    public function getReturnPoints(): ?float
    {
        return $this->return_points;
    }

    /**
     * @param float|null $return_points
     */
    public function setReturnPoints(?float $return_points): void
    {
        $this->return_points = $return_points;
    }

    /**
     * @return string|null
     */
    public function getInstantMessage(): ?string
    {
        return $this->instant_message;
    }

    /**
     * @param string|null $instant_message
     */
    public function setInstantMessage(?string $instant_message): void
    {
        $this->instant_message = $instant_message;
    }

    /**
     * @return string|null
     */
    public function getInstantStatus(): ?string
    {
        return $this->instant_status;
    }

    /**
     * @param string|null $instant_status
     */
    public function setInstantStatus(?string $instant_status): void
    {
        $this->instant_status = $instant_status;
    }

    /**
     * @return string|null
     */
    public function getProtectedStatus(): ?string
    {
        return $this->protected_status;
    }

    /**
     * @param string|null $protected_status
     */
    public function setProtectedStatus(?string $protected_status): void
    {
        $this->protected_status = $protected_status;
    }

    /**
     * @return string|null
     */
    public function getProtectedFeedbackText(): ?string
    {
        return $this->protected_feedback_text;
    }

    /**
     * @param string|null $protected_feedback_text
     */
    public function setProtectedFeedbackText(?string $protected_feedback_text): void
    {
        $this->protected_feedback_text = $protected_feedback_text;
    }

    /**
     * @return string|null
     */
    public function getProtectedFeedbackHtml(): ?string
    {
        return $this->protected_feedback_html;
    }

    /**
     * @param string|null $protected_feedback_html
     */
    public function setProtectedFeedbackHtml(?string $protected_feedback_html): void
    {
        $this->protected_feedback_html = $protected_feedback_html;
    }


    /**
     * Update the assignment status of the related exercise members (user or team)
     * this must be done if the submission data changes
     */
    public function updateMemberStatus($a_user_ids = [])
    {
        require_once (__DIR__ . '/class.ilExAutoScoreAssignment.php');
        $scoreAss = ilExAutoScoreAssignment::findOrGetInstance($this->getAssignmentId());

        if (empty($this->getReturnTime())) {
            $status = 'notgraded';
            $mark = null;
        }
        else {
            if (empty($scoreAss->getMinPoints())) {
                $status = 'notgraded';
            }
            elseif ($this->getReturnPoints() >= $scoreAss->getMinPoints()) {
                $status = 'passed';
            }
            else {
                $status = 'failed';
            }
            $mark = $this->getReturnPoints();
        }

        if (!empty($a_user_ids)) {
            $user_ids = $a_user_ids;
        }
        elseif (!empty($this->getUserId())) {
            $user_ids = [$this->getUserId()];
        }
        elseif (!empty($this->getTeamId())) {
            $team = new ilExAssignmentTeam($this->getTeamId());
            $user_ids = $team->getMembers();
        }
        else {
            $user_ids = [];
        }

        foreach ($user_ids as $user_id) {
            $memberStatus = new ilExAssignmentMemberStatus($this->getAssignmentId(), $user_id);
            $memberStatus->setReturned($this->getSubmitSuccess() ?? false);
            $memberStatus->setComment($this->getProtectedFeedbackText() ?? '');
            $memberStatus->setStatus($status);
            $memberStatus->setMark($mark !== null ? (string) $mark : '');
            $memberStatus->update();
        }
    }
    
    /**
     * Reset the status of users (e.g. ex team members)
     * @param int[] $a_user_ids
     */
    public function resetMemberStatus ($a_user_ids) {
        foreach ($a_user_ids as $user_id) {
            $memberStatus = new ilExAssignmentMemberStatus($this->getAssignmentId(), $user_id);
            $memberStatus->setReturned(false);
            $memberStatus->setComment('');
            $memberStatus->setStatus('notgraded');
            $memberStatus->setMark('');
            $memberStatus->update();
        }
    }

    /**
     * Delete the feedback files that belong to this task
     */
    public function deleteFeedbackFiles()
    {
        $assignment = new ilExAssignment($this->getAssignmentId());

        if (!empty($this->getUserId())) {
            $user_id = $this->getUserId();
            $team = null;
        }
        elseif (!empty($this->getTeamId())) {
            $team = new ilExAssignmentTeam($this->getTeamId());
            $members = $team->getMembers();
            $user_id = array_pop($members);
        }
        else {
            return;
        }

        $submission = new ilExSubmission($assignment, $user_id, $team);
        $feedback_id = $submission->getFeedbackId();

        $fstorage = new ilFSStorageExercise($assignment->getExerciseId(), $assignment->getId());
        $fstorage->create();
        $fb_path = $fstorage->getFeedbackPath($feedback_id);

        $fstorage->deleteDirectory($fb_path);
    }

    /**
     * @return string|null
     */
    public function getDebugLogs(): ?string
    {
        return $this->debug_logs;
    }    

    /**
     * @param string|null $debug_logs
     */
    public function setDebugLogs(?string $debug_logs): void
    {
        $this->debug_logs = $debug_logs;
    }    
}