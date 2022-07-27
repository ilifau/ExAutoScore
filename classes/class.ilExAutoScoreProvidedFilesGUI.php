<?php
require_once (__DIR__ . '/traits/trait.ilExAutoScoreGUIBase.php');
require_once (__DIR__ . '/models/class.ilExAutoScoreAssignment.php');
require_once (__DIR__ . '/models/class.ilExAutoScoreTask.php');
require_once (__DIR__ . '/models/class.ilExAutoScoreProvidedFile.php');

/**
 * Class ilExAutoScoreProvidedFilesGUI
 *
 * @ilCtrl_isCalledBy ilExAutoScoreProvidedFilesGUI: ilExAssTypeAutoScoreUserGUI, ilExAssTypeAutoScoreTeamGUI
 */
class ilExAutoScoreProvidedFilesGUI
{
    use ilExAutoScoreGUIBase;

    /** @var ilExAutoScorePlugin $plugin */
    public $plugin;

    /** @var ilExAssignment */
    protected $assignment;

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
    public function executeCommand()
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
                    case 'confirmDeleteFiles':
                    case 'deleteFiles':
                        $this->$cmd();
                        break;
                    default:
                        $this->tpl->setContent($cmd);
                }
        }
    }

    /**
     * List the provided files
     */
    public function listFiles()
    {
        if (ilExAutoScoreTask::hasTasks($this->assignment->getId())) {
            ilutil::sendInfo($this->plugin->txt('info_existing_tasks'));
        }

        require_once (__DIR__ . '/class.ilExAutoScoreProvidedFilesTableGUI.php');
        $table = new ilExAutoScoreProvidedFilesTableGUI($this, 'listFiles');
        $table->loadData($this->assignment->getId());
        $this->setListToolbar();
        $this->tpl->setContent($table->getHTML());
    }


    /**
     * Show form to add a new file
     */
    protected function addFile()
    {
        if (ilExAutoScoreTask::hasTasks($this->assignment->getId())) {
            ilutil::sendInfo($this->plugin->txt('info_existing_tasks'));
        }

        $file = new ilExAutoScoreProvidedFile();
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

        $file = new ilExAutoScoreProvidedFile();
        $file->setAssignmentId($this->assignment->getId());

        $form = $this->initFileForm($file);
        $form->setValuesByPost();
        if ($form->checkInput()) {

            $request = $DIC->http()->request();
            $params = $request->getParsedBody();

            $file->setDescription((string) $params['exautoscore_file_description']);
            $file->setPurpose((string) $params['exautoscore_file_purpose']);
            $file->setPublic((bool) $params['exautoscore_file_public']);
            $file->save();
            $file->storeUploadedFile();

            $resetAssignment = false;
            $resetTasks = false;
            if ($file->getPurpose() == ilExAutoScoreProvidedFile::PURPOSE_SUPPORT) {
                $resetAssignment = true;
            }
            elseif ($file->getPurpose() == ilExAutoScoreProvidedFile::PURPOSE_SUBMIT) {
                $resetTasks = true;
            }
            $this->handleChanges($this->plugin->txt('file_created'), $resetAssignment, $resetTasks);

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
        $this->ctrl->saveParameter($this, 'id');
        $this->setFileToolbar();

        if (ilExAutoScoreTask::hasTasks($this->assignment->getId())) {
            ilutil::sendInfo($this->plugin->txt('info_existing_tasks'));
        }

        /** @var ilExAutoScoreProvidedFile $file */
        $file = ilExAutoScoreProvidedFile::find((int) $_GET['id']);
        if ($file->getAssignmentId() != $this->assignment->getId()) {
            ilUtil::sendFailure($this->lng->txt("permission_denied"), true);
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

        $this->ctrl->saveParameter($this, 'id');

        /** @var ilExAutoScoreProvidedFile $file */
        $file = ilExAutoScoreProvidedFile::find((int) $_GET['id']);
        if ($file->getAssignmentId() != $this->assignment->getId()) {
            ilUtil::sendFailure($this->lng->txt("permission_denied"), true);
            $this->ctrl->returnToParent($this);
        }

        $form = $this->initFileForm($file);
        $form->setValuesByPost();
        if ($form->checkInput()) {

            $request = $DIC->http()->request();
            $params = $request->getParsedBody();

            $oldPurpose = $file->getPurpose();
            $newPurpose = (string) $params['exautoscore_file_purpose'];
            $fileChanged = false;
            $resetAssignment = false;
            $resetTasks = false;

            $file->setDescription((string) $params['exautoscore_file_description']);
            $file->setPurpose((string) $params['exautoscore_file_purpose']);
            $file->setPublic((bool) $params['exautoscore_file_public']);
            $file->update();

            if ($file->storeUploadedFile()) {
                $fileChanged = true;
            }

            if ($fileChanged || $oldPurpose != $newPurpose) {
                if ($oldPurpose == ilExAutoScoreProvidedFile::PURPOSE_SUPPORT
                    || $newPurpose == ilExAutoScoreProvidedFile::PURPOSE_SUPPORT) {
                    $resetAssignment = true;
                }
                elseif ($oldPurpose == ilExAutoScoreProvidedFile::PURPOSE_SUBMIT
                    || $newPurpose == ilExAutoScoreProvidedFile::PURPOSE_SUBMIT) {
                    $resetTasks = true;
                }
            }
            $this->handleChanges($this->plugin->txt('file_updated'), $resetAssignment, $resetTasks);

            $this->ctrl->redirect($this, "editFile");
        }

        $this->setFileToolbar();
        $this->tpl->setContent($form->getHTML());
    }

    /**
     * Init the form to  create or update a file
     * @param ilExAutoScoreProvidedFile $file
     * @return ilPropertyFormGUI
     */
    protected function initFileForm($file)
    {
        $form = new ilPropertyFormGUI();
        $form->setTitle($this->plugin->txt(empty($file->getId()) ? 'add_file' : 'edit_file'));
        $form->setFormAction($this->ctrl->getFormAction($this));

        $fileUpload = new ilFileInputGUI($this->plugin->txt('file_upload'), 'exautoscore_file_upload');
        if (empty($file->getId())) {
            $fileUpload->setRequired(true);
        }
        else {
            $this->ctrl->setParameter($this->parentGUI, 'file_id', $file->getId());
            $link = $this->ctrl->getLinkTarget($this->parentGUI, 'downloadProvidedFile');
            $info = '<p><strong>' . $this->plugin->txt('existing_file') . ':</strong> '
                .'<a href="' . $link . '">' . $file->getFilename() . '</a></p>';
            $fileUpload->setInfo($info);
        };
        $form->addItem($fileUpload);

        $purpose = new ilRadioGroupInputGUI($this->plugin->txt('purpose'), 'exautoscore_file_purpose');
        $purpose->setRequired(true);
        $purpose->addOption(new ilRadioOption($this->plugin->txt('purpose_support'),
            ilExAutoScoreProvidedFile::PURPOSE_SUPPORT,
            $this->plugin->txt('purpose_support_info')));
        $purpose->addOption(new ilRadioOption($this->plugin->txt('purpose_submit'),
            ilExAutoScoreProvidedFile::PURPOSE_SUBMIT,
            $this->plugin->txt('purpose_submit_info')));
        $purpose->addOption(new ilRadioOption($this->plugin->txt('purpose_ignore'),
            ilExAutoScoreProvidedFile::PURPOSE_IGNORE,
            $this->plugin->txt('purpose_ignore_info')));
        $purpose->setValue($file->getPurpose());
        $form->addItem($purpose);

        $fileIsPublic = new ilCheckboxInputGUI($this->plugin->txt('is_public'), 'exautoscore_file_public');
        $fileIsPublic->setChecked($file->isPublic());
        $fileIsPublic->setInfo($this->plugin->txt('is_public_info'));
        $form->addItem($fileIsPublic);

        $fileDescription = new ilTextAreaInputGUI($this->lng->txt('description'),'exautoscore_file_description');
        $fileDescription->setValue($file->getDescription());
        $fileDescription->setInfo($this->plugin->txt('file_description_info'));
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
     * Confirm the deletion of files
     */
    protected function confirmDeleteFiles()
    {
        if (empty($_POST['ids'])) {
            ilUtil::sendFailure($this->lng->txt('select_at_least_one_object'), true);
            $this->ctrl->redirect($this,'listFiles');
        }

        $conf_gui = new ilConfirmationGUI();
        $conf_gui->setFormAction($this->ctrl->getFormAction($this));
        $conf_gui->setHeaderText($this->plugin->txt('confirm_delete_files'));
        $conf_gui->setConfirm($this->lng->txt('delete'),'deleteFiles');
        $conf_gui->setCancel($this->lng->txt('cancel'), 'listFiles');

        /** @var ilExAutoScoreProvidedFile[] $files */
        $files = ilExAutoScoreProvidedFile::where(['id' => $_POST['ids']])->get();

        foreach($files as $file) {
            if ($file->getAssignmentId() != $this->assignment->getId()) {
                ilUtil::sendFailure($this->lng->txt("permission_denied"), true);
                $this->ctrl->returnToParent($this);
            }

            $conf_gui->addItem('ids[]', $file->getId(), $file->getFilename());
        }

        $this->tpl->setContent($conf_gui->getHTML());
    }

    /**
     * Delete confirmed items
     */
    protected function deleteFiles()
    {
        /** @var ilExAutoScoreProvidedFile[] $files */
        $files = ilExAutoScoreProvidedFile::where(['id' => $_POST['ids']])->get();

        foreach($files as $file) {
            if ($file->getAssignmentId() != $this->assignment->getId()) {
                ilUtil::sendFailure($this->lng->txt("permission_denied"), true);
                $this->ctrl->returnToParent($this);
            }
        }

        $resetAssignment = false;
        $resetTasks = false;

        foreach($files as $file) {
            if ($file->getPurpose() == ilExAutoScoreProvidedFile::PURPOSE_SUPPORT) {
                $resetAssignment = true;
            }
            elseif ($file->getPurpose() == ilExAutoScoreProvidedFile::PURPOSE_SUBMIT) {
                $resetTasks = true;
            }
            $file->delete();
        }
        $this->handleChanges($this->plugin->txt('files_deleted'), $resetAssignment, $resetTasks);

        $this->ctrl->redirect($this, 'listFiles');
    }


    /**
     * Set the toolbar for the record list
     */
    protected function setListToolbar() {

        $button = ilLinkButton::getInstance();
        $button->setCaption($this->plugin->txt('add_file'), false);
        $button->setUrl($this->ctrl->getLinkTarget($this, 'addFile'));
        $this->toolbar->addButtonInstance($button);
    }

    /**
     * Set the toolbar for a record view
     */
    protected function setFileToolbar()
    {
        $button = ilLinkButton::getInstance();
        $button->setCaption('« ' . $this->plugin->txt('back_to_list'), false);
        $button->setUrl($this->ctrl->getLinkTarget($this, 'listFiles'));
        $this->toolbar->addButtonInstance($button);
    }

    /**
     * Handle the changes
     * Do the neccessary resets and send an appropriate message
     *
     * @param string $message
     * @param bool $resetAssignment
     * @param bool $resetTasks
     */
    public function handleChanges($message, $resetAssignment = false, $resetTasks = false)
    {
        $hasTasks = ilExAutoScoreTask::hasSubmissions($this->assignment->getId());

        if ($resetAssignment) {
            // this will also reset the tasks
            ilExAutoScoreAssignment::resetCorrection($this->assignment->getId());
            if ($hasTasks) {
                ilUtil::sendSuccess($message . ' ' . $this->plugin->txt('please_send_assignment_and_tasks'), true);
            }
            else {
                ilUtil::sendSuccess($message . ' ' . $this->plugin->txt('please_send_assignment'), true);
            }
        }
        elseif ($resetTasks) {
            // clear at least the example
            ilExAutoScoreTask::clearAllSubmissions($this->assignment->getId());
            if ($hasTasks) {
                ilUtil::sendSuccess($message . ' '. $this->plugin->txt('please_send_tasks'), true);
            }
        }
        else {
            ilUtil::sendSuccess($message, true);
        }
    }
}