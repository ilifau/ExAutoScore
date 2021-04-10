<?php
// Copyright (c) 2020 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

require_once (__DIR__ . '/models/class.ilExAutoScoreAssignment.php');
require_once (__DIR__ . '/traits/trait.ilExAutoScoreGUIBase.php');
require_once (__DIR__ . '/class.ilExAutoScoreConnector.php');

/**
 * Auto Score Base Assignment Type GUI
 * (control structure is provided in child classes)
 *
 * @ilCtrl_isCalledBy ilExAutoScoreDebugGUI: ilExAssTypeAutoScoreUserGUI, ilExAssTypeAutoScoreTeamGUI
 */
class ilExAutoScoreDebugGUI
{
    use ilExAutoScoreGUIBase;

    /** @var ilExAutoScorePlugin */
    protected $plugin;

    /** @var ilExAssignment */
    protected $assignment;

    /**
     * Constructor
     *
     * @param ilExAutoScorePlugin
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
        $cmd = $this->ctrl->getCmd();
        $this->setToolbar();

        switch ($next_class) {

            default:
                switch ($cmd) {
                    case 'overview:':
                    case 'sendAssignment':
                    case 'sendTask':
                        $this->$cmd();
                        break;
                    default:
                        $this->tpl->setContent($cmd);
                }
        }
    }

    /**
     * Show Overview
     */
    public function overview()
    {
        $this->tpl->setContent('overview');
    }


    public function sendAssignment()
    {
        $connector = new ilExAutoScoreConnector();
        $result = $connector->sendAssignment($this->assignment);
        $this->tpl->setContent('<pre>' . print_r($result, true) . '</pre>');
    }


    public function sendTask()
    {
        global $DIC;
        $connector = new ilExAutoScoreConnector();
        $result = $connector->sendTask($this->assignment, $DIC->user());
        $this->tpl->setContent('<pre>' . print_r($result, true) . '</pre>');
    }


    public function setToolbar()
    {
        $button = ilLinkButton::getInstance();
        $button->setCaption($this->plugin->txt('send_assignment'), false);
        $button->setUrl($this->ctrl->getLinkTarget($this, 'sendAssignment'));
        $this->toolbar->addButtonInstance($button);

        $button = ilLinkButton::getInstance();
        $button->setCaption($this->plugin->txt('send_task'), false);
        $button->setUrl($this->ctrl->getLinkTarget($this, 'sendTask'));
        $this->toolbar->addButtonInstance($button);
    }


}
