<?php
// Copyright (c) 2020 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

require_once "./Modules/Exercise/AssignmentTypes/classes/interface.ilExAssignmentTypeTeamHandlerInterface.php";
require_once (__DIR__ . '/models/class.ilExAutoScoreTask.php');
require_once (__DIR__ . '/models/class.ilExAutoScoreRequiredFile.php');
require_once (__DIR__ . '/class.ilExAutoScoreConnector.php');

/**
 * Auto Score Team Handler
 */
class ilExAssTypeAutoTeamHandler implements ilExAssignmentTypeTeamHandlerInterface
{
    /** @var ilExAssignment */
    protected $assignment;

    /** @var ilExAutoScorePlugin */
    protected $plugin;

    /** @var ilFSStorageExercise */
    protected $storage;

    /** @var bool */
    protected $is_management = false;

    /**
     * Removed users from the last handleTeamChange() call that have submissions
     * @var array
     */
    protected $removedUserWithSubmission = [];

    /**
     * Constructor
     *
     * @param ilExAssignment $assignment
     * @param bool $is_management
     * @param ilExAutoScorePlugin $plugin
     */
    public function __construct(ilExAssignment $assignment, $is_management, $plugin)
    {
        $this->assignment = $assignment;
        $this->is_management = $is_management;
        $this->plugin = $plugin;
        $this->storage = new ilFSStorageExercise($this->assignment->getExerciseId(), $this->assignment->getId());
    }


    /**
     * Handle the adding of users to a team
     * This function is called after team creation
     * Will send a new submission for correction if all required files are there
     *
     * @param ilExAssignmentTeam $team
     * @return void
     */
    public function handleTeamCreated(ilExAssignmentTeam $team)
    {
        global $DIC;

        if (empty($members = $team->getMembers())) {
            return;
        }

        $user_id = $members[0];
        $submission = new ilExSubmission($this->assignment, $user_id);

        $files = [];
        foreach ($submission->getFiles() as $file) {
            $files[$file["filetitle"]] = true;
        }

        // check if all required files are there
        foreach (ilExAutoScoreRequiredFile::getForAssignment($this->assignment->getId()) as $required) {
            if (!isset($files[$required->getFilename()])) {
                return;
            }
        }

        // if all files are there, send them for correction
        // this will update the member status of all members
        $connector = new ilExAutoScoreConnector();
        $connector->sendSubmission($submission, $DIC->user());
    }

    /**
     * Handle the adding of users to a team
     * This function is called after the standard adding of users
     * Added users will lose their own submissions
     * Their status is updated according to the submissions of the team
     *
     * @param ilExAssignmentTeam $team
     * @param array              $added_users
     * @return void
     */
    public function handleTeamAddedUsers(ilExAssignmentTeam $team, $added_users = [])
    {
        if ($this->is_management) {
            // team is extended by exercise admin
            // => select the latest complete set of uploads and delete all others

            /** @var ilExSubmission[] */
            $submissions = [];

            /** @var array  user_id => returned_id => filetitle */
            $files = [];

            $former_members = array_diff($team->getMembers(), $added_users);

            // get the names of the required files
            $required_names = [];
            foreach (ilExAutoScoreRequiredFile::getForAssignment($this->assignment->getId()) as $requiredFile) {
                $required_names[] = $requiredFile->getFilename();
            }

            // 1. collect all submitted files by user_id
            foreach ($team->getMembers() as $user_id) {
                $submission = new ilExSubmission($this->assignment, $user_id);
                $submissions[$user_id] = $submission;
                foreach ($submission->getFiles() as $file) {
                    $files[$file["user_id"]][$file['returned_id']] = $file['filetitle'];

                    // team files may come from different team members
                    // add them additionally to a pseudo team user with id 0
                    if (in_array($file["user_id"], $former_members)) {
                        $files[0][$file['returned_id']] = $file['filetitle'];
                    }
                }
            }

            // 2. check for completeness and highest returned_id
            $highest_complete_returned_id = 0;
            $preferred_user_id = 0;
            foreach ($files as $user_id => $returned) {
                // check if all required filenames are returned by the user
                // if yes, check if this user has the highest returned_id
                if (empty(array_diff($required_names, array_values($returned)))) {
                    $max = max(array_keys($returned));
                    if ($max > $highest_complete_returned_id) {
                        $highest_complete_returned_id = $max;
                        $preferred_user_id = $user_id;
                    }
                }
            }

            //log_var($former_members, '$former_members');
            //log_var($required_names, '$required_names');
            //log_var($files, 'files');
            //log_var($highest_complete_returned_id, '$highest_complete_returned_id');
            //log_var($preferred_user_id, '$preferred_user_id');

            // 3. delete all uploads if not preferred
            foreach ($files as $user_id => $returned) {
                if ($user_id == 0) {
                    // pseudo user for team has no own uploads
                    // => ignore this user
                    continue;
                }
                if ($user_id == $preferred_user_id) {
                    // uploads of this user are complete and the latest
                    //=>  keep these uploads
                    continue;
                }
                elseif ($preferred_user_id == 0 && in_array($user_id, $former_members)) {
                    // uploads of the team are preferred or no complete set of uploads exists
                    // => keep all former team uploads
                    continue;
                }
                else {
                    // none of the conditions fit
                    // => delete the uploads of the user
                    $submission = $submissions[$user_id];
                    $submission->deleteSelectedFiles(array_keys($returned));
                }
            }

        }
        else {
            // users are added by a team member
            // => delete the submissions of the added users

            foreach ($added_users as $user_id) {
                $submission = new ilExSubmission($this->assignment, $user_id);
                foreach ($submission->getFiles() as $file) {
                    if ($file['user_id'] == $user_id) {
                        $submission->deleteSelectedFiles([$file['returned_id']]);
                    }
                }
            }

            // update the status of the added users
            $task = ilExAutoScoreTask::getTeamTask($this->assignment->getId(), $team->getId());
            $task->updateMemberStatus($added_users);
        }
    }


    /**
     * Handle the removing of users from a team
     * removed users will get copies of the last submission of the team
     *
     * @param ilExAssignmentTeam $team
     * @param int[]              $removed_users
     * @return void
     */
    public function handleTeamRemovedUsers(ilExAssignmentTeam $team, $removed_users = [])
    {
        global $DIC;

        $team_members = $team->getMembers();
        $team_user = null;                  // team user that should get copies of the removed submissions
        $check_users = $removed_users;      // users to check for submitted files
        $files = [];                        // title => user_id => returned_id


        // treat status of removed users and delete a team task if team is deleted
        $task = ilExAutoScoreTask::getTeamTask($this->assignment->getId(), $team->getId());
        $task->resetMemberStatus($removed_users);
        if (empty($team_members)) {
            $task->delete();
        }

        // select the team member that should get copies of the removed submissions
        if (in_array($DIC->user()->getId(), $team_members)) {
            $team_user = $DIC->user()->getId();
            $check_users[] = $team_user;
        }
        elseif (!empty($team_members)) {
            $team_user = $team_members[0];
            $check_users[] = $team_user;
        }

        // collect the submitted files of removed users and in the team
        // only one team user is needed to get the submissions in the team
        // the files are listed with user_ids of the submitting team members
        foreach($check_users as $user_id) {
            $submission = new ilExSubmission($this->assignment, $user_id);
            foreach ($submission->getFiles() as $file) {
                $files[$file["filetitle"]][$file['user_id']] = $file['returned_id'];
            }
        }

        // copy the submitted files
        // the team and all removed users should get a whole file set
        foreach ($files as $title => $user_returned) {
            // there should be only one submission per filename
            foreach ($user_returned as $file_user_id => $returned_id) {

                // copy all files to all removed users
                // except where a file already belongs to that user
                foreach ($removed_users as $removed_user_id) {
                    if ($file_user_id != $removed_user_id) {
                        $this->copySubmittedFileToUser($returned_id, $removed_user_id);
                    }
                }

                // copy a file of a removed user to the selected team member
                if (in_array($file_user_id, $removed_users) && isset($team_user)) {
                    $this->copySubmittedFileToUser($returned_id, $team_user);
                }
            }
        }

        // set the users that should form single teams
        if (!empty($files)) {
            $this->removedUserWithSubmission = $removed_users;
        }
        else {
            $this->removedUserWithSubmission = [];
        }
    }


    /**
     * Get the removed users treated by the last handleTeamChange() call that have a submission and should form a single team
     * ExAutoScore will distribute the submissions of the team to all removed users
     *
     * @return int[]
     */
    public function getRemovedUsersWithSubmission(): array
    {
        return $this->removedUserWithSubmission;
    }

    /**
     * Get the message that will be displayed when users are removed
     * @return string
     */
    public function getRemoveUsersMessage(): string
    {
        return $this->plugin->txt('team_member_remove_sure');
    }


    /**
     * Copy a submitted file to a user
     * @see ilExSubmission::uploadFile()
     * @todo rework for IRSS in ILIAS 7
     *
     * @param int $returned_id
     * @param int $user_id
     * @return bool success
     */
    protected function copySubmittedFileToUser($returned_id, $user_id)
    {
        global $DIC;
        $db = $DIC->database();

        $query = "SELECT * from exc_returned WHERE returned_id = " . $db->quote($returned_id, 'integer');
        $result = $db->query($query);
        $row = $db->fetchAssoc($result);

        if (empty($row) ||!is_file($row['filename'])) {
            return false;
        }

        $tempfile = ilUtil::ilTempnam();
        copy($row['filename'], $tempfile);

        // simulate an already unzipped upload (file will be moved by standard rename)
        $post = [
            'name' => $row['filetitle'],
            'tmp_name' => $tempfile,
            'size' => filesize($tempfile)
        ];
        $deliver_result = $this->storage->uploadFile($post, $user_id, true);

        // save new entry with other user and new uploaded path
        if ($deliver_result) {
            $next_id = $db->nextId("exc_returned");
            $query = sprintf(
                "INSERT INTO exc_returned " .
                "(returned_id, obj_id, user_id, filename, filetitle, mimetype, ts, ass_id, late, team_id) " .
                "VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s)",
                $db->quote($next_id, "integer"),
                $db->quote($row['obj_id'], "integer"),
                $db->quote($user_id, "integer"),
                $db->quote($deliver_result["fullname"], "text"),
                $db->quote($row['filetitle'], "text"),
                $db->quote($row['mimetype'], "text"),
                $db->quote($row['ts'], "timestamp"),
                $db->quote($row['ass_id'], "integer"),
                $db->quote($row['late'], "integer"),
                $db->quote(0, "integer")
            );
            $db->manipulate($query);

            return true;
        }

        return false;
    }
}
