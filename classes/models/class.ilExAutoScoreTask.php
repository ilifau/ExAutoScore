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
     * @var string
     *
     * @con_has_field  true
     * @con_fieldtype  text
     * @con_length    4000
     * @con_is_notnull false
     */
    protected $instant_message;


    /**
     * @var string
     *
     * @con_has_field  true
     * @con_fieldtype  text
     * @con_length    10
     * @con_is_notnull false
     */
    protected $instant_status;


    /**
     * @var string
     *
     * @con_has_field  true
     * @con_fieldtype  text
     * @con_length    10
     * @con_is_notnull false
     */
    protected $protected_status;

    /**
     * @var string
     *
     * @con_has_field  true
     * @con_fieldtype  text
     * @con_length    4000
     * @con_is_notnull false
     */
    protected $protected_feedback_text;


    /**
     * @var string
     *
     * @con_has_field  true
     * @con_fieldtype  clob
     * @con_is_notnull false
     */
    protected $protected_feedback_html;


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
     * Check if user or team tasks exist
     * @param $assignment_id
     * @return bool
     * @throws Exception
     */
    public static function hasTasks($assignment_id)
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
    public static function hasSubmissions($assignment_id)
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
    public static function getForAssignment($assignment_id)
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
    public static function getSubmissionTask(ilExSubmission $a_submission)
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
    public static function getUserTask($a_assignment_id, $a_user_id)
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
    public static function getTeamTask($a_assignment_id, $a_team_id)
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

    /**
     * @return string
     */
    public function getInstantMessage()
    {
        return $this->instant_message;
    }

    /**
     * @param string $instant_message
     */
    public function setInstantMessage($instant_message)
    {
        $this->instant_message = $instant_message;
    }

    /**
     * @return string
     */
    public function getInstantStatus()
    {
        return $this->instant_status;
    }

    /**
     * @param string $instant_status
     */
    public function setInstantStatus($instant_status)
    {
        $this->instant_status = $instant_status;
    }

    /**
     * @return string
     */
    public function getProtectedStatus()
    {
        return $this->protected_status;
    }

    /**
     * @param string $protected_status
     */
    public function setProtectedStatus($protected_status)
    {
        $this->protected_status = $protected_status;
    }

    /**
     * @return string
     */
    public function getProtectedFeedbackText()
    {
        return $this->protected_feedback_text;
    }

    /**
     * @param string $protected_feedback_text
     */
    public function setProtectedFeedbackText($protected_feedback_text)
    {
        $this->protected_feedback_text = $protected_feedback_text;
    }

    /**
     * @return string
     */
    public function getProtectedFeedbackHtml()
    {
        return $this->protected_feedback_html;
    }

    /**
     * @param string $protected_feedback_html
     */
    public function setProtectedFeedbackHtml($protected_feedback_html)
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

        foreach ($user_ids as $user_id) {
            $memberStatus = new ilExAssignmentMemberStatus($this->getAssignmentId(), $user_id);
            $memberStatus->setReturned($this->getSubmitSuccess());
            $memberStatus->setComment($this->getProtectedFeedbackText());
            $memberStatus->setStatus($status);
            $memberStatus->setMark($mark);
            $memberStatus->update();
        }
    }

    /**
     * Reset the status of users (e.g. ex team members)
     * @param int[] $a_user_id
     */
    public function resetMemberStatus ($a_user_ids) {
        foreach ($a_user_ids as $user_id) {
            $memberStatus = new ilExAssignmentMemberStatus($this->getAssignmentId(), $user_id);
            $memberStatus->setReturned(false);
            $memberStatus->setComment(null);
            $memberStatus->setStatus('notgraded');
            $memberStatus->setMark(null);
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
}
