<?php
// Copyright (c) 2020 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

require_once(__DIR__ . '/class.ilExAssTypeAutoScoreBase.php');

/**
 * Auto Score User Assignment Type
 */
class ilExAssTypeAutoScoreUser extends ilExAssTypeAutoScoreBase implements ilExAssignmentTypeInterface
{
    /** @var ilExAutoScorePlugin */
    protected $plugin;


    /**
     * @inheritdoc
     */
    public function getTitle() {
        return $this->plugin->txt('type_autoscore_user');
    }

    /**
     * @inheritdoc
     */
    public function usesTeams() {
        return false;
    }


    /**
     * @inheritdoc
     */
    public function isSubmissionAssignedToTeam() {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function supportsWebDirAccess(): bool {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function getStringIdentifier(): string{
        return '';
    }
	

}
