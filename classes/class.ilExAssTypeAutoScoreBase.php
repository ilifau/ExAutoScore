<?php
// Copyright (c) 2020 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

require_once "./Modules/Exercise/AssignmentTypes/classes/interface.ilExAssignmentTypeExtendedInterface.php";

/**
 * Auto Score Base Assignment Type
 */
abstract class ilExAssTypeAutoScoreBase implements ilExAssignmentTypeExtendedInterface
{
    /** @var ilExAutoScorePlugin */
    protected $plugin;

    /**
     * Constructor
     *
     * @param ilExAutoScorePlugin
     */
    public function __construct($plugin)
    {
        $this->plugin = $plugin;
    }

    /**
     * @inheritdoc
     */
    public function isActive()
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    abstract public function usesTeams();

    /**
     * @inheritdoc
     */
    public function hasFiles()
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function usesFileUpload()
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    abstract public function getTitle();

    /**
     * @inheritdoc
     */
    public function getSubmissionType()
    {
        return ilExSubmission::TYPE_FILE;
    }

    /**
     * @inheritdoc
     */
    abstract public function isSubmissionAssignedToTeam();

    /**
     * @inheritdoc
     */
    public function cloneSpecificProperties(ilExAssignment $source, ilExAssignment $target)
    {
    }

    /**
     * @inheritdoc
     */
    public function isManualGradingSupported($a_ass): bool {
        return ilObjExerciseAccess::checkExtendedGradingAccess($a_ass->getExerciseId(), false);
    }

    /**
     * Handle a membership change in a team
     *
     * Submitted files are made unique (conflicting files of added team members may be deleted).
     * If required files are missing then the team task is cleared and the status of all team members is reset.
     * If required files are complete, then the team task is sent for correction and the status of all members is updated.
     * If the whole team is deleted, then the team task is deleted.
     *
     * The status of removed users is reset.
     * If removed users already formed new teams, they should be excluded in the call!
     *
     * @inheritdoc
     */
    public function handleTeamChange (
        ilExAssignment $ass,
        ilExAssignmentTeam $team,
        $added_users = [],
        $removed_users = []
    ) {
        global $DIC;

        require_once (__DIR__ . '/models/class.ilExAutoScoreTask.php');
        require_once (__DIR__ . '/models/class.ilExAutoScoreRequiredFile.php');
        require_once (__DIR__ . '/class.ilExAutoScoreConnector.php');

        /** @var ilExSubmission[] $submissions */
        $submissions = [];  // user_id => submission
        $files = [];        // title => user_id => returned_id
        $task = ilExAutoScoreTask::getTeamTask($ass->getId(), $team->getId());

        // treat removed users and delete a team task if team is deleted
        $task->resetMemberStatus($removed_users);
        if (empty($team->getMembers())) {
            $task->delete();
            return;
        }

        // team still has members: collect the submitted files
        foreach($team->getMembers() as $user_id) {
            $submission = new ilExSubmission($ass, $user_id);
            $submissions[$user_id] = $submission;

            foreach ($submission->getFiles() as $file) {
                // getFiles() provides all files for all members
                // so check the file owner to see who has uploaded a file
                if ($file['user_id'] == $user_id) {
                    $files[$file["filetitle"]][$user_id] = $file['returned_id'];
                }
            }
        }

        // ensure that each filename exists only once
        // get the user with the latest remaining submission
        $max_all_returned_id = 0;
        $latest_all_user_id = 0;
        foreach ($files as $title => $user_returned) {
            // files from previous users exist => delete all files with the same name of added users
            if (!empty(array_diff(array_keys($user_returned), $added_users))) {
                foreach ($user_returned as $user_id => $returned_id) {
                    if (in_array($user_id, $added_users)) {
                        $submissions[$user_id]->deleteSelectedFiles([$returned_id]);
                        unset($user_returned[$user_id]);
                    }
                }
            }

            // delete all remaining files with the same name except the latest submission
            $max_returned_id = max($user_returned);
            $latest_user_id = 0;
            foreach ($user_returned as $user_id => $returned_id) {
                if ($returned_id == $max_returned_id) {
                    $latest_user_id = $user_id;
                }
                else {
                    $submissions[$user_id]->deleteSelectedFiles([$returned_id]);
                    unset($user_returned[$user_id]);
                }
            }

            // determine the latest submitted user of all remaining files
            if ($max_returned_id > $max_all_returned_id) {
                $max_all_returned_id = $max_returned_id;
                $latest_all_user_id = $latest_user_id;
            }
        }

        // if files are missing, delete the task and reset the status of all members
        foreach (ilExAutoScoreRequiredFile::getForAssignment($ass->getId()) as $required) {
            if (!isset($files[$required->getFilename()])) {
                $task->resetMemberStatus($team->getMembers());
                $task->delete();
                return;
            }
        }

        // if all files are there, send them for correction
        // this will update the member status of all members
        if ($latest_all_user_id > 0) {
            $submission = $submissions[$latest_all_user_id];
            $connector = new ilExAutoScoreConnector();
            $connector->sendSubmission($submission, $DIC->user());
        }
    }
}
