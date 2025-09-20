<?php
declare(strict_types=1);

// Copyright (c) 2020 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

/**
 * Auto Score Base Assignment Type
 */
abstract class ilExAssTypeAutoScoreBase implements ilExAssignmentTypeInterface
{
    /** @var ilExAutoScorePlugin */
    protected mixed $plugin;

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
    public function isActive(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    abstract public function usesTeams(): bool;

    /**
     * @inheritdoc
     */
    public function hasFiles(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function usesFileUpload(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    abstract public function getTitle(): string;

    /**
     * @inheritdoc
     */
        public function getSubmissionType(): string
    {
        return "File";  // ILIAS 9 expects a string identifier
    }

    /**
     * @inheritdoc
     */
    abstract public function isSubmissionAssignedToTeam(): bool;

    /**
     * @inheritdoc
     */
    public function cloneSpecificProperties(ilExAssignment $source, ilExAssignment $target): void
    {
    }

    /**
     * @inheritdoc
     */
    public function isManualGradingSupported($a_ass): bool {
        return true;
    }
}
