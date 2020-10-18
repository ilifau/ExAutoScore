<?php

// Copyright (c) 2020 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

require_once (__DIR__ . '/models/class.ilExAutoScoreAssignment.php');
require_once (__DIR__ . '/models/class.ilExAutoScoreContainer.php');

/**
 * Auto Score Team Assignment Type GUI
 */
abstract class ilExAssTypeAutoScoreBaseGUI implements ilExAssignmentTypeGUIInterface
{
    use ilExAssignmentTypeGUIBase;

    /**
     * @var ilLanguage
     */
    protected $lng;

    /** @var ilExAutoScorePlugin */
    protected $plugin;

    /**
     * Constructor
     *
     * @param ilExAutoScorePlugin
     */
    public function __construct($plugin)
    {
        global $DIC;
        $this->lng = $DIC->language();
        $this->plugin = $plugin;
    }


    /**
     * @inheritdoc
     */
    public function addEditFormCustomProperties(ilPropertyFormGUI $form)
    {
        $cont_radio = new ilRadioGroupInputGUI($this->plugin->txt('execution_container'), 'cont_radio');
        $cont_opt_select = new ilRadioOption($this->plugin->txt('use_existing_file'), 'cont_use_existing');
        $cont_opt_upload =  new ilRadioOption($this->plugin->txt('upload_new_file'), 'cont_upload_new');
        $cont_radio->addOption($cont_opt_select);
        $cont_radio->addOption($cont_opt_upload);

        $cont_upload_file = new ilFileInputGUI($this->plugin->txt('container_upload'), 'cont_file_upload');
        $cont_upload_file->setRequired(true);
        $cont_upload_title = new ilTextInputGUI($this->lng->txt('title'),'cont_upload_title');
        $cont_upload_title->setRequired(true);
        $cont_upload_public = new ilCheckboxInputGUI($this->plugin->txt('container_public'), 'cont_upload_public');
        $cont_upload_public->setInfo($this->plugin->txt('container_public_info'));

        $cont_opt_upload->addSubItem($cont_upload_file);
        $cont_opt_upload->addSubItem($cont_upload_title);
        $cont_opt_upload->addSubItem($cont_upload_public);

        $form->addItem($cont_radio);
    }

    /**
     * @inheritdoc
     */
    public function importFormToAssignment(ilExAssignment $ass, ilPropertyFormGUI $form)
    {
        global $DIC;

        $request = $DIC->http()->request();
        $params = $request->getParsedBody();

        // echo '<pre>'; var_dump($params);

        $assAuto = new ilExAutoScoreAssignment();
        $assAuto->setId($ass->getId());

        $cont_radio = (string) $params['cont_radio'];

        if ($cont_radio == 'cont_use_existing') {

        }
        elseif ($cont_radio == 'cont_upload_new') {
           if (isset($params['cont_file_upload']['tmp_name'])) {
               $assCont = new ilExAutoScoreContainer();
               $assCont->setOrigAssignmentId($ass->getId());
               $assCont->setTitle((string) $request->getAttribute('cont_upload_title'));
               $assCont->setPublic((bool) $request->getAttribute('cont_upload_public'));
               $assCont->save();

               if (!$assCont->storeUploadedFile($params['cont_file_upload']['tmp_name'])) {
                    $assCont->delete();
               }
               else {
                   $assAuto->setContainerId($assCont->getId());
                   $assAuto->save();
               }
           }
        }
    }

    /**
     * @inheritdoc
     */
    public function getFormValuesArray(ilExAssignment $ass)
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function getOverviewContent(ilInfoScreenGUI $a_info, ilExSubmission $a_submission)
    {
    }
}
