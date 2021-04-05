<?php
require_once (__DIR__ . '/traits/trait.ilExAutoScoreGUIBase.php');

/**
 * Class ilExAutoScoreProvidedFilesGUI
 *
 * @ilCtrl_isCalledBy ilExAutoScoreProvidedFilesGUI: ilExAssTypeAutoScoreUserGUI, ilExAssTypeAutoScoreTeamGUI
 */
class ilExAutoScoreProvidedFilesGUI
{
    use ilExAutoScoreGUIBase;

    /** @var ilExAutoScorePlugin $plugin */
    protected $plugin;

    /** @var ilExAssignment */
    protected $assignment;

    /**
     * Constructor
     */
    public function __construct(ilExAutoScorePlugin $plugin, ilExAssignment $assignment)
    {
        $this->initGlobals();
        $this->plugin = $plugin;
        $this->assignment = $assignment;
    }


    /**
     * Execute command
     */
    public function executeCommand()
    {
        $next_class = $this->ctrl->getNextClass($this);
        $cmd = $this->ctrl->getCmd('listFiles');

        switch ($next_class) {
            default:
                switch ($cmd) {
                    default:
                        if (in_array($cmd, [
                            'listFiles'
                            ])) {
                            $this->$cmd();
                        }
                }
        }
    }

    /**
     * List the provided files
     */
    public function listFiles()
    {
        $this->tpl->setContent('listing files');
    }


}