<?php
require_once (__DIR__ . '/traits/trait.ilExAutoScoreGUIBase.php');
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
                    case 'listFiles':
                    case 'addFile':
                    case 'createFile':
                    case 'editFile':
                    case 'updateFile':
                    case 'confirmDeleteFiles':
                    case 'deleteFiles':
                    default:
                        $this->$cmd();
                }
        }
    }

    /**
     * List the provided files
     */
    public function listFiles()
    {
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
        $file = new ilExAutoScoreProvidedFile();
        $form = $this->initFileForm($file);
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

            if (!empty($params['exautoscore_file_upload']['tmp_name'])) {
                $file->setDescription((string) $params['exautoscore_file_description']);
                $file->setPurpose($params['exautoscore_file_purpose']);
                $file->setPublic((bool) $params['exautoscore_file_public']);
                $file->save();
                $file->storeUploadedFile($params['exautoscore_file_upload']['tmp_name']);
            }

            ilUtil::sendSuccess($this->plugin->txt("file_created"), true);
            $this->ctrl->setParameter($this, 'id', $file->getId());
            $this->ctrl->redirect($this, "editFile");
        }
        $this->tpl->setContent($form->getHTML());
    }

    /**
     * Show form to edit a file
     */
    protected function editFile()
    {
        $this->ctrl->saveParameter($this, 'id');
        $this->setfileToolbar();

        /** @var ilExAutoScoreProvidedFile $file */
        $file = ilExAutoScoreProvidedFile::find((int) $_GET['id']);

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

        $form = $this->initFileForm($file);
        $form->setValuesByPost();
        if ($form->checkInput()) {

            $request = $DIC->http()->request();
            $params = $request->getParsedBody();

            $file->setDescription((string) $params['exautoscore_file_description']);
            $file->setPurpose($params['exautoscore_file_purpose']);
            $file->setPublic((bool) $params['exautoscore_file_public']);
            $file->update();

            if (!empty($params['exautoscore_file_upload']['tmp_name'])) {
                $file->storeUploadedFile($params['exautoscore_file_upload']['tmp_name']);
            }

            ilUtil::sendSuccess($this->plugin->txt("file_updated"), true);
            $this->ctrl->redirect($this, "editfile");
        }

        $this->setfileToolbar();
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

        $filePurpose = new ilRadioGroupInputGUI($this->plugin->txt('purpose'), 'exautoscore_file_purpose');
        $filePurpose->setRequired(true);
        $filePurpose->setValue($file->getPurpose());
        $opt_support = new ilRadioOption($this->plugin->txt('purpose_support'), 'support');
        $opt_support->setInfo($this->plugin->txt('purpose_support_info'));
        $filePurpose->addOption($opt_support);
        $opt_example = new ilRadioOption($this->plugin->txt('purpose_example'), 'example');
        $opt_example->setInfo($this->plugin->txt('purpose_example_info'));
        $filePurpose->addOption($opt_example);
        $form->addItem($filePurpose);

        $fileUpload = new ilFileInputGUI($this->plugin->txt('file_upload'), 'exautoscore_file_upload');
        if (empty($file->getId())) {
            $fileUpload->setRequired(true);
        }
        else {
            $fileUpload->setInfo('<strong>' . $this->plugin->txt('existing_file') . ': ' . $file->getFilename(). '</strong>');
        };
        $form->addItem($fileUpload);

        $fileDescription = new ilTextAreaInputGUI($this->lng->txt('description'),'exautoscore_file_description');
        $fileDescription->setValue($file->getDescription());
        $fileDescription->setInfo($this->plugin->txt('file_description_info'));
        $form->addItem($fileDescription);

        $fileIsPublic = new ilCheckboxInputGUI($this->plugin->txt('is_public'), 'exautoscore_file_public');
        $fileIsPublic->setChecked($file->isPublic());
        $fileIsPublic->setInfo($this->plugin->txt('is_public_info'));
        $form->addItem($fileIsPublic);

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
            $file->delete();
        }

        ilUtil::sendSuccess($this->plugin->txt('files_deleted'), true);
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



}