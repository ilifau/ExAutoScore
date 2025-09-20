<?php
declare(strict_types=1);

// Copyright (c) 2020 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

/**
 * ExAutoScore configuration user interface class
 *
 * @ilCtrl_Calls: ilExAutoScoreConfigGUI: ilPropertyFormGUI
 * @ilCtrl_isCalledBy ilExAutoScoreConfigGUI: ilObjComponentSettingsGUI
 *
 *
 * @author Fred Neumann <fred.neumann@fau.de>
 */
class ilExAutoScoreConfigGUI extends ilPluginConfigGUI
{
	/** @var ilExAutoScorePlugin $plugin */
	protected mixed $plugin;

	/** @var ilExAutoScoreConfig $config */
	protected mixed $config;

	/** @var ilTabsGUI $tabs */
    protected mixed $tabs;

    /** @var ilCtrl $ctrl */
    protected mixed $ctrl;

    /** @var ilLanguage $lng */
	protected mixed $lng;

    /** @var ilTemplate $lng */
	protected ilGlobalTemplateInterface $tpl;

    /** @var  ilToolbarGUI $toolbar */
    protected mixed $toolbar;

    /**
	 * Handles all commands, default is "configure"
     * @param string $cmd
     * @throws Exception
	 */
	public function performCommand(string $cmd): void
	{
        global $DIC;

        // this can't be in constructor
        $this->plugin = $this->getPluginObject();
        $this->config = $this->plugin->getConfig();
        $this->lng = $DIC->language();
        $this->tabs = $DIC->tabs();
        $this->ctrl = $DIC->ctrl();
        $this->tpl = $DIC->ui()->mainTemplate();
        $this->toolbar = $DIC->toolbar();


        $this->tabs->addTab('basic', $this->plugin->txt('basic_configuration'), $this->ctrl->getLinkTarget($this, 'configure'));
        $this->setToolbar();

        switch ($DIC->ctrl()->getNextClass())
        {
            case 'ilpropertyformgui':
                switch ($_GET['config'])
                {
                    case 'basic':
                        $DIC->ctrl()->forwardCommand($this->initBasicConfigurationForm());
                        break;
                }

                break;

            default:
                switch ($cmd)
                {
                    case "configure":
                    case "saveBasicSettings":
                    #case "updateLanguages": still needed? CSM
                    case "loadCampusExams":
                    case "generateDBUpdate":
                        $this->tabs->activateTab('basic');
                        $this->$cmd();
                        break;
                }
        }
	}

    /**
     * Set the toolbar
     */
    protected function setToolbar(): void
    {
        $this->toolbar->setFormAction($this->ctrl->getFormAction($this, 'configure'));

        /*$button = ilLinkButton::getInstance();
        $button->setUrl($this->ctrl->getLinkTarget($this, 'updateLanguages'));
        $button->setCaption($this->plugin->txt('update_languages'), false);
        $this->toolbar->addButtonInstance($button);*/

        $button = ilLinkButton::getInstance();
        $button->setUrl($this->ctrl->getLinkTarget($this, 'generateDBUpdate'));
        $button->setCaption($this->plugin->txt('generate_db_update'), false);
        $this->toolbar->addButtonInstance($button);
    }

    /**
	 * Show base configuration screen
	 */
	protected function configure()
	{
		$form = $this->initBasicConfigurationForm();
		$this->tpl->setContent($form->getHTML());
	}

    /**
     * Update Languages
     */
    /*protected function updateLanguages()
    {
        $this->plugin->updateLanguages();
        $this->ctrl->redirect($this, 'configure');
    }*/


    /**
     * Generate the db update steps for active record
     */
	protected function generateDBUpdate()
    {
        require_once (__DIR__ . '/models/class.ilExAutoScoreTask.php');
        $arBuilder = new arBuilder(new ilExAutoScoreTask());
        $arBuilder->generateDBUpdateForInstallation();
    }

    /**
	 * Initialize the configuration form
	 * @return ilPropertyFormGUI form object
	 */
	protected function initBasicConfigurationForm(): ilPropertyFormGUI
	{
		$form = new ilPropertyFormGUI();
        $form->setTitle($this->plugin->txt('basic_configuration'));
		$form->setFormAction($this->ctrl->getFormAction($this, 'saveBasicSettings'));

        foreach($this->config->getParams() as $param) {
            $param->setValue($this->config->get($param->name));
            $form->addItem($param->getFormItem());
        }

		$form->addCommandButton("saveBasicSettings", $this->lng->txt("save"));
		return $form;
	}

	/**
	 * Save the basic settings
	 */
	protected function saveBasicSettings()
	{
		$form = $this->initBasicConfigurationForm();
		if ($form->checkInput())
		{
            $form->setValuesByPost();
            foreach($this->config->getParams() as $param) {
                $param->setByForm($form);
                $this->config->set($param->name, $param->value);
            }
            $this->config->write();
            $this->tpl->setOnScreenMessage('success', $this->lng->txt("settings_saved"), true);
			$this->ctrl->redirect($this, 'configure');
		}
		else
		{
			$form->setValuesByPost();
			$this->tpl->setContent($form->getHtml());
		}
	}
}