<?php
declare(strict_types=1);

// Copyright (c) 2020 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

/**
 * ExAutoScore configuration user interface class
 *
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

        switch ($cmd)
        {
            case "configure":
            case "saveBasicSettings":
            case "generateDBUpdate":
                $this->tabs->activateTab('basic');
                $this->$cmd();
                break;
        }
	}

    /**
     * Set the toolbar
     */
    protected function setToolbar(): void
    {
        $this->toolbar->setFormAction($this->ctrl->getFormAction($this, 'configure'));

        global $DIC;
        $this->toolbar->addComponent($DIC->ui()->factory()->button()->standard(
            $this->plugin->txt('generate_db_update'),
            $this->ctrl->getLinkTarget($this, 'generateDBUpdate')
        ));
    }

    /**
	 * Show base configuration screen
	 */
	protected function configure()
	{
		$this->showForm($this->initBasicConfigurationForm());
	}

    protected function showForm(\ILIAS\UI\Component\Input\Container\Form\Standard $form): void
    {
        global $DIC;
        $panel = $DIC->ui()->factory()->panel()->standard($this->plugin->txt('basic_configuration'), $form);
        $this->tpl->setContent($DIC->ui()->renderer()->render($panel));
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
	 */
	protected function initBasicConfigurationForm(): \ILIAS\UI\Component\Input\Container\Form\Standard
	{
        global $DIC;

        $inputs = [];
        foreach ($this->config->getParams() as $param) {
            $param->setValue($this->config->get($param->name));
            $input = $param->getUiInput();
            if ($input !== null) {
                $inputs[$param->name] = $input;
            }
        }

        return $DIC->ui()->factory()->input()->container()->form()->standard(
            $this->ctrl->getFormAction($this, 'saveBasicSettings'),
            $inputs
        );
	}

	/**
	 * Save the basic settings
	 */
	protected function saveBasicSettings()
	{
        global $DIC;

        $form = $this->initBasicConfigurationForm()->withRequest($DIC->http()->request());
        $data = $form->getData();
        if ($data === null) {
            $this->showForm($form);
            return;
        }

        foreach ($this->config->getParams() as $param) {
            if (array_key_exists($param->name, $data)) {
                $param->setByUiValue($data[$param->name]);
                $this->config->set($param->name, $param->value);
            }
        }
        $this->config->write();
        $this->tpl->setOnScreenMessage('success', $this->lng->txt("settings_saved"), true);
        $this->ctrl->redirect($this, 'configure');
	}
}