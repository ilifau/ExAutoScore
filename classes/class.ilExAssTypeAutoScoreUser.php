<?php
declare(strict_types=1);

// Copyright (c) 2020 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

require_once(__DIR__ . '/class.ilExAssTypeAutoScoreBase.php');

/**
 * Auto Score User Assignment Type
 */
class ilExAssTypeAutoScoreUser extends ilExAssTypeAutoScoreBase implements ilExAssignmentTypeInterface
{
    /** @var ilExAutoScorePlugin */
    protected mixed $plugin;


    /**
     * @inheritdoc
     */
    public function getTitle(): string {
        return $this->plugin->txt('type_autoscore_user');
    }

    /**
     * @inheritdoc
     */
    public function usesTeams(): bool {
        return false;
    }


    /**
     * @inheritdoc
     */
    public function isSubmissionAssignedToTeam(): bool {
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
