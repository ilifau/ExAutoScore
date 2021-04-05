<?php
// Copyright (c) 2021 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

/**
 * Record table
 */
class ilExAutoScoreProvidedFilesTableGUI extends ilTable2GUI
{
    /** @var ilExAutoScoreProvidedFilesGUI */
    protected $parent_obj;

    /** @var string $parent_cmd */
    protected $parent_cmd;

    /** @var ilExAutoScorePlugin */
    protected $plugin;

    /** @var int */
    protected $assignment_id;

    /**
     * Constructor
     * @param ilExAutoScoreProvidedFilesGUI $a_parent_obj
     * @param string $a_parent_cmd
     */
    public function __construct($a_parent_obj, $a_parent_cmd)
    {
        global $DIC;

        $this->lng = $DIC->language();
        $this->ctrl = $DIC->ctrl();
        $this->parent_obj = $a_parent_obj;
        $this->parent_cmd = $a_parent_cmd;
        $this->plugin = $a_parent_obj->plugin;

        $this->setId('ilExAutoScoreProvidedFilesGUI');
        $this->setPrefix('ilExAutoScoreProvidedFilesGUI');

        parent::__construct($a_parent_obj, $a_parent_cmd);

        $this->addColumn('', '', '1%', true);
        $this->addColumn($this->plugin->txt('purpose'), 'purpose');
        $this->addColumn($this->plugin->txt('filename'), 'filename');
        $this->addColumn($this->plugin->txt('file_size'), 'size');
        $this->addColumn($this->lng->txt('description'), 'description');
        $this->addColumn($this->plugin->txt('is_public'), 'is_public');
        $this->addColumn($this->lng->txt('actions'));

        $this->setTitle($this->plugin->txt('provided_files'));
        $this->setFormName('provided_files');
        $this->setFormAction($this->ctrl->getFormAction($a_parent_obj, $a_parent_cmd));

        $this->setStyle('table', 'fullwidth');
        $this->setRowTemplate("tpl.exautoscore_files_row.html", $this->plugin->getDirectory());

        $this->setExternalSorting(true);
        $this->setExternalSegmentation(true);

        $this->disable('sort');
        $this->enable('header');

        $this->setSelectAllCheckbox('ids');
        $this->addMultiCommand('confirmDeleteFiles', $this->plugin->txt('delete_files'));
    }


    /**
     * Query for the data to be shown
     * @param integer $assignment_id
     * @throws Exception
     */
    public function loadData($assignment_id)
    {
        $this->assignment_id = $assignment_id;

        /** @var ilExAutoScoreProvidedFile $file */
        $filesList = ilExAutoScoreProvidedFile::getCollection();
        $filesList->where(['assignment_id' => $assignment_id])->where(['purpose' => ilExAutoScoreProvidedFile::PURPOSE_DOCKER], '!=');

        // paging
        $this->determineOffsetAndOrder();
        $this->determineLimit();
        $this->setMaxCount($filesList->count());
        if (isset($this->fields[$this->getOrderField()])) {
            $filesList->orderBy($this->getOrderField(), $this->getOrderDirection());
        }
        $filesList->limit($this->getOffset(), $this->getLimit());

        // prepare row data (fillRow expects array)
       $data = [];
       $files = $filesList->get();
       foreach ($files as $file) {
            $row = [];
            $row['id'] = $file->getId();
            $row['file'] = $file;
            $data[] = $row;
       }
       $this->setData($data);
    }


    /**
	 * Define ordering mode for a field (not needed, if externally sorted)
     * @param string $a_field
	 * @return boolean  numeric ordering; default is false
	 */
	function numericOrdering($a_field)
	{
	    if ($a_field == 'size') {
	        return true;
        }
	    return false;
	}

	/**
	 * fill row
	 * @param array $data
	 */
	public function fillRow($data)
	{
		$id = $data['id'];

		/** @var ilExAutoScoreProvidedFile $file */
		$file = $data['file'];
        $this->ctrl->setParameter($this->parent_obj, 'id', $id);

        // checkbox
        $this->tpl->setVariable('ID', $id);
        $this->tpl->setVariable('PURPOSE', $this->plugin->txt('purpose_' . $file->getPurpose()));
        $this->tpl->setVariable('FILENAME', $file->getFilename());
        $this->tpl->setVariable('FILE_SIZE', $file->getSize());
        $this->tpl->setVariable('DESCRIPTION', $file->getDescription());
        $this->tpl->setVariable('IS_PUBLIC', $this->lng->txt($file->isPublic() ?  'yes': 'no'));

        // show action column
        $list = new ilAdvancedSelectionListGUI();
        $list->setSelectionHeaderClass('small');
        $list->setItemLinkClass('small');
        $list->setId('actl_'.$id.'_'.$this->getId());
        $list->setListTitle($this->lng->txt('actions'));

        // add actions
        $list->addItem($this->plugin->txt('edit_file'), '', $this->ctrl->getLinkTarget($this->parent_obj,'editFile'));

        $this->tpl->setVariable('ACTIONS', $list->getHtml());
    }
}