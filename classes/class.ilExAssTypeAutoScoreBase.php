<?php
// Copyright (c) 2020 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

/**
 * Auto Score Base Assignment Type
 */
abstract class ilExAssTypeAutoScoreBase implements ilExAssignmentTypeInterface
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
        return true;
    }
}
