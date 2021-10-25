<?php
// Copyright (c) 2020 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

require_once "./Modules/Exercise/AssignmentTypes/classes/interface.ilExAssignmentTypeExtendedInterface.php";
require_once(__DIR__ . '/class.ilExAssTypeAutoScoreBase.php');

require_once (__DIR__ . '/models/class.ilExAutoScoreTask.php');
require_once (__DIR__ . '/models/class.ilExAutoScoreRequiredFile.php');
require_once (__DIR__ . '/class.ilExAutoScoreConnector.php');


/**
 * Auto Score Team Assignment Type
 */
class ilExAssTypeAutoScoreTeam extends ilExAssTypeAutoScoreBase implements ilExAssignmentTypeExtendedInterface
{
    /** @var ilExAutoScorePlugin */
    protected $plugin;

    /**
     * @inheritdoc
     */
    public function getTitle()
    {
        return $this->plugin->txt('type_autoscore_team');
    }


    /**
     * @inheritdoc
     */
    public function usesTeams()
    {
        return true;
    }


    /**
     * @inheritdoc
     */
    public function isSubmissionAssignedToTeam()
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function getTeamHandler(ilExAssignment $assignment, $is_management = false): ilExAssignmentTypeTeamHandlerInterface
    {
        require_once(__DIR__ . '/class.ilExAutoScoreTeamHandler.php');
        return new ilExAssTypeAutoTeamHandler($assignment, $is_management, $this->plugin);
    }
}

