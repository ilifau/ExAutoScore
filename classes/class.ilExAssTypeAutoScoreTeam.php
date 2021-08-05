<?php
// Copyright (c) 2020 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

require_once(__DIR__ . '/class.ilExAssTypeAutoScoreBase.php');

/**
 * Auto Score Team Assignment Type
 */
class ilExAssTypeAutoScoreTeam extends ilExAssTypeAutoScoreBase implements ilExAssignmentTypeInterface
{
    /** @var ilExAutoScorePlugin */
    protected $plugin;

    /**
     * @inheritdoc
     */
    public function getTitle() {
        return $this->plugin->txt('type_autoscore_team');
    }


    /**
     * @inheritdoc
     */
    public function usesTeams() {
        return true;
    }


    /**
     * @inheritdoc
     */
    public function isSubmissionAssignedToTeam() {
        return false;
    }
}
