<?php
declare(strict_types=1);

require_once (__DIR__ . '/traits/trait.ilExAutoScoreGUIBase.php');
require_once (__DIR__ . '/models/class.ilExAutoScoreRequiredFile.php');

/**
 * @ilCtrl_IsCalledBy ilExAutoScoreRequiredFilesGUI: ilExAssTypeAutoScoreUserGUI, ilExAssTypeAutoScoreTeamGUI, ilExAssignmentEditorGUI
 */
class ilExAutoScoreRequiredFilesGUI
{
    use ilExAutoScoreGUIBase;

    /** @var ilExAutoScorePlugin $plugin */
    public mixed $plugin;

    /** @var ilExAssignment */
    protected mixed $assignment;

    /**
     * Constructor
     */
    public function __construct(ilExAutoScorePlugin $plugin, ilExAssignment $assignment, ilExAssTypeAutoScoreBaseGUI $parentGUI)
    {
        $this->initGlobals();
        $this->plugin = $plugin;
        $this->assignment = $assignment;
        $this->parentGUI = $parentGUI;
    }

    /**
     * Execute command
     */
    public function executeCommand(): void
    {
        $next_class = $this->ctrl->getNextClass($this);
        $cmd = $this->ctrl->getCmd('listFiles');

        switch ($next_class) {
            default:
                switch ($cmd) {
                    case 'listFiles':
                    case 'addFile':
                    case 'createFile':
                    case 'editFile':
                    case 'updateFile':
                    case 'deleteFiles':
                        $this->$cmd();
                        break;
                    default:
                        $this->tpl->setContent($cmd);
                }
        }
    }

    /**
     * List the Required files
     */
    public function listFiles()
    {
        global $DIC;

        require_once (__DIR__ . '/class.ilExAutoScoreRequiredFilesTableGUI.php');
        $table = new ilExAutoScoreRequiredFilesTableGUI($this, $this->plugin, $this->assignment->getId());
        if ($table->getRequestedAction() === 'delete') {
            $table->sendDeleteConfirmation();
        }
        $this->setListToolbar();
        $this->tpl->setContent($DIC->ui()->renderer()->render($table->getComponent()));
    }


    /**
     * Show form to add a new file
     */
    protected function addFile()
    {
        $file = new ilExAutoScoreRequiredFile();
        $form = $this->initFileForm($file);
        $this->setFileToolbar();
        $this->tpl->setContent($form->getHTML());
    }

    /**
     * Save a new file
     */
    protected function createFile()
    {
        global $DIC;

        $file = new ilExAutoScoreRequiredFile();
        $file->setAssignmentId($this->assignment->getId());

        $form = $this->initFileForm($file);
        $form->setValuesByPost();
        if ($form->checkInput()) {

            $request = $DIC->http()->request();
            $params = $request->getParsedBody();

            $file->setMaxSize((int) $params['exautoscore_file_max_size'] * 1000);
            $file->setRequiredEncoding((string) $params['exautoscore_file_encoding']);
            $file->setDescription((string) $params['exautoscore_file_description']);
            $file->save();
            $file->storeUploadedFile();

            ilExAutoScoreTask::clearExampleTask($this->assignment->getId());

            $message = $this->plugin->txt('file_created')
                . ' '. $this->plugin->txt('example_task_reset')
                . ' '. $this->plugin->txt('example_task_missing');
            $this->tpl->setOnScreenMessage('success', $message, true);

            $this->ctrl->setParameter($this, 'id', $file->getId());
            $this->ctrl->redirect($this, "editFile");
        }
        $this->setFileToolbar();
        $this->tpl->setContent($form->getHTML());
    }

    /**
     * Show form to edit a file
     */
    protected function editFile()
    {
        require_once (__DIR__ . '/class.ilExAutoScoreRequiredFilesTableGUI.php');
        $id = ilExAutoScoreFileTable::getRequestedFileId(ilExAutoScoreRequiredFilesTableGUI::NS);
        $this->ctrl->setParameter($this, 'id', $id);
        $this->setFileToolbar();

        /** @var ilExAutoScoreRequiredFile $file */
        $file = ilExAutoScoreRequiredFile::find($id);
        if ($file === null || $file->getAssignmentId() != $this->assignment->getId()) {
            $this->tpl->setOnScreenMessage('failure', $this->lng->txt("permission_denied"), true);
            $this->ctrl->returnToParent($this);
        }

        $form = $this->initFileForm($file);
        $this->tpl->setContent( $form->getHTML());
    }

    /**
     * Update an edited file
     */
    protected function updateFile()
    {
        global $DIC;

        require_once (__DIR__ . '/class.ilExAutoScoreRequiredFilesTableGUI.php');
        $id = ilExAutoScoreFileTable::getRequestedFileId(ilExAutoScoreRequiredFilesTableGUI::NS);
        $this->ctrl->setParameter($this, 'id', $id);

        /** @var ilExAutoScoreRequiredFile $file */
        $file = ilExAutoScoreRequiredFile::find($id);
        if ($file === null || $file->getAssignmentId() != $this->assignment->getId()) {
            $this->tpl->setOnScreenMessage('failure', $this->lng->txt("permission_denied"), true);
            $this->ctrl->returnToParent($this);
        }

        $form = $this->initFileForm($file);
        $form->setValuesByPost();
        if ($form->checkInput()) {

            $request = $DIC->http()->request();
            $params = $request->getParsedBody();

            $file->setMaxSize((int) $params['exautoscore_file_max_size'] * 1000);
            $file->setRequiredEncoding((string) $params['exautoscore_file_encoding']);
            $file->setDescription((string) $params['exautoscore_file_description']);
            $file->update();
            $file->storeUploadedFile();

            ilExAutoScoreTask::clearExampleTask($this->assignment->getId());

            $message = $this->plugin->txt('file_updated')
                . ' '. $this->plugin->txt('example_task_reset')
                . ' '. $this->plugin->txt('example_task_missing');
            $this->tpl->setOnScreenMessage('success', $message, true);

            $this->ctrl->redirect($this, "editFile");
        }

        $this->setfileToolbar();
        $this->tpl->setContent($form->getHTML());
    }

    /**
     * Init the form to  create or update a file
     * @param ilExAutoScoreRequiredFile $file
     * @return ilPropertyFormGUI
     */
    protected function initFileForm($file): ilPropertyFormGUI
    {
        $form = new ilPropertyFormGUI();
        $form->setTitle($this->plugin->txt(empty($file->getId()) ? 'add_file' : 'edit_file'));
        $form->setFormAction($this->ctrl->getFormAction($this));

        $fileUpload = new ilFileInputGUI($this->plugin->txt('file_upload'), 'exautoscore_file_upload');
        if (empty($file->getId())) {
            $fileUpload->setRequired(true);
            $fileUpload->setInfo($this->plugin->txt('purpose_example_info'));
        }
        else {
            $this->ctrl->setParameter($this->parentGUI, 'file_id', $file->getId());
            $link = $this->ctrl->getLinkTarget($this->parentGUI, 'downloadExampleFile');
            $info = '<p><strong>' . $this->plugin->txt('existing_file') . ':</strong> '
                .'<a href="' . $link . '">' . $file->getFilename() . '</a></p>';
            $fileUpload->setInfo($info);
        };
        $form->addItem($fileUpload);

        $fileEncoding = new ilSelectInputGUI($this->plugin->txt('encoding'), 'exautoscore_file_encoding');
        $fileEncoding->setOptions(ilExAutoScoreRequiredFile::getEncodingOptions());
        $fileEncoding->setValue($file->getRequiredEncoding());
        $form->addItem($fileEncoding);

        $fileMaxSize = new ilNumberInputGUI($this->plugin->txt('max_size'), 'exautoscore_file_max_size');
        $fileMaxSize->setSuffix('KB');
        $fileMaxSize->allowDecimals(false);
        $fileMaxSize->setSize(10);
        if ($file->getMaxSize()) {
            $fileMaxSize->setValue((string) ceil($file->getMaxSize() / 1000));
        }
        $form->addItem($fileMaxSize);

        $fileDescription = new ilTextAreaInputGUI($this->lng->txt('description'),'exautoscore_file_description');
        $fileDescription->setValue($file->getDescription());
        $form->addItem($fileDescription);

        if (empty($file->getId())) {
            $form->addCommandButton('createFile', $this->plugin->txt('add_file'));
        }
        else {
            $form->addCommandButton('updateFile', $this->plugin->txt('update_file'));
        }

        return $form;
    }

    /**
     * Delete confirmed items
     */
    protected function deleteFiles()
    {
        $ids = $this->getConfirmedIds();
        if (empty($ids)) {
            $this->tpl->setOnScreenMessage('failure', $this->lng->txt('select_at_least_one_object'), true);
            $this->ctrl->redirect($this, 'listFiles');
        }
        /** @var ilExAutoScoreRequiredFile[] $files */
        $files = ilExAutoScoreRequiredFile::where(['id' => $ids])->get();

        foreach($files as $file) {
            if ($file->getAssignmentId() != $this->assignment->getId()) {
                $this->tpl->setOnScreenMessage('failure', $this->lng->txt("permission_denied"), true);
                $this->ctrl->returnToParent($this);
            }
        }

        foreach($files as $file) {
            $file->delete();
        }
        ilExAutoScoreTask::clearExampleTask($this->assignment->getId());

        $message = $this->plugin->txt('files_deleted')
        . ' '. $this->plugin->txt('example_task_reset')
        . ' '. $this->plugin->txt('example_task_missing');
        $this->tpl->setOnScreenMessage('success', $message, true);

        $this->ctrl->redirect($this, 'listFiles');
    }


    /**
     * Set the toolbar for the record list
     */
    protected function setListToolbar(): void {

        $this->addToolbarButton($this->plugin->txt('add_file'), $this->ctrl->getLinkTarget($this, 'addFile'));
        $this->addToolbarButton($this->plugin->txt('send_example_task'), $this->ctrl->getLinkTargetByClass('ilExAutoScoreSettingsGUI', 'sendExampleTask'));

        $this->toolbar->addSeparator();
    }

    /**
     * Set the toolbar for a record view
     */
    protected function setFileToolbar(): void
    {
        $this->addToolbarButton('« ' . $this->plugin->txt('back_to_list'), $this->ctrl->getLinkTarget($this, 'listFiles'));
    }
}