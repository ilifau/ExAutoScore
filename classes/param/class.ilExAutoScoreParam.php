<?php
// Copyright (c) 2020 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

/**
 * Class ilExAutoScoreParam
 */
class ilExAutoScoreParam
{
	/**
	 * Defined parameter types
	 */
	const TYPE_HEAD = 'head';
    const TYPE_TEXT = 'text';
    const TYPE_RICHTEXT = 'richtext';
    const TYPE_BOOLEAN = 'bool';
    const TYPE_INT = 'int';
	const TYPE_FLOAT = 'float';
	const TYPE_REF_ID = 'ref_id';
	const TYPE_ROLES = 'roles';
    const TYPE_SELECT = 'select';


	/**
	 * @var string		name of the parameter (should be unique within an evaluation class)
	 */
	public $name;

	/**
     * @var string     title of the parameter
     */
	public $title;


    /**
     * @var string     description of the parameter
     */
    public $description;


    /**
	 * @var string		type of the parameter
	 */
	public $type;

	/**
	 * @var mixed 		actual value
	 */
	public $value;

    /**
     * @var array       options for a select param
     */
	public $options;


    /**
     * Create a parameter
     *
     * @param string $a_name
     * @param string $a_title
     * @param string $a_description
     * @param string $a_type
	 * @param mixed $a_value
     * @param array $a_options
     * @return ilExAutoScoreParam
     */
    public static function _create($a_name, $a_title, $a_description, $a_type = self::TYPE_TEXT, $a_value = null, $a_options = [])
    {
        $param = new self;
		$param->name = $a_name;
		$param->title = $a_title;
		$param->description = $a_description;
		$param->type = $a_type;
		$param->value = $a_value;
		$param->options = $a_options;
		
		return $param;
    }

    /**
     * Set the value and cast it to the correct type
     * @param null $value
     */
    public function setValue($value = null)
    {
        switch($this->type)
        {
            case self::TYPE_TEXT:
                $this->value = (string) $value;
                break;
            case self::TYPE_RICHTEXT:
                $this->value = (string) $value;
                break;
            case self::TYPE_BOOLEAN:
                $this->value = (bool) $value;
                break;
            case self::TYPE_INT:
                $this->value = (integer) $value;
                break;
            case self::TYPE_FLOAT:
                $this->value = (float) $value;
                break;
            case self::TYPE_REF_ID:
                $this->value = (integer) $value;
                break;
            case self::TYPE_ROLES:
                $this->value = (string) $value;
                break;
            case self::TYPE_SELECT:
                $this->value = $value;
                break;
        }
    }

    /**
     * Get a form item for setting the parameter
     */
    public function getFormItem()
    {
        global $DIC;

        $title = $this->title;
        $description = $this->description;
        $postvar = $this->getPostvar();

        switch($this->type)
        {
            case self::TYPE_HEAD:
                $item = new ilFormSectionHeaderGUI();
                $item->setTitle($title);
                break;

            case self::TYPE_TEXT:
                $item = new ilTextInputGUI($title, $postvar);
                $item->setValue($this->value);
                break;

            case self::TYPE_RICHTEXT:
                $item = new ilTextAreaInputGUI($title, $postvar);
                $item->setUseRte(true);
                $item->setRteTagSet('mini');
                $item->setValue($this->value);
                break;

            case self::TYPE_REF_ID:
            case self::TYPE_INT:
                $item = new ilNumberInputGUI($title, $postvar);
                $item->allowDecimals(false);
                $item->setSize(10);
                $item->setValue($this->value);
                break;

            case self::TYPE_BOOLEAN:
                $item = new ilCheckboxInputGUI($title, $postvar);
                $item->setValue(1);
                $item->setChecked($this->value);
                break;

            case self::TYPE_FLOAT:
                $item = new ilNumberInputGUI($title, $postvar);
                $item->allowDecimals(true);
                $item->setSize(10);
                $item->setValue($this->value);
                break;

            case self::TYPE_ROLES:
                $titles = array();
                foreach (explode(',', $this->value) as $role_id) {
                    $titles[] = ilObject::_lookupTitle((int) $role_id);
                }
                $item = new ilTextInputGUI($title, $postvar);
                $item->setMulti(true);
                $item->setDataSource($DIC->ctrl()->getLinkTargetByClass(
                    ['ilAdministrationGUI','ilObjAuthSettingsGUI','ilRegistrationSettingsGUI'],
                    "getLocalRoleAutoComplete", "", true));
                $item->setMultiValues($titles);
                if (count($titles)) {
                    $item->setValue($titles[0]);
                }
                break;

            case self::TYPE_SELECT:
                $item = new ilSelectInputGUI($title, $postvar);
                $item->setOptions($this->options);
                $item->setValue($this->value);
                break;
        }

        if (strpos($description, '-') !== 0)
        {
            $item->setInfo($description);
        }


        return $item;
    }

    /**
     * Read the data posted to the form
     * @param ilPropertyFormGUI $form
     */
    public function setByForm($form)
    {
        global $DIC;

        /** @var ilFormPropertyGUI $item */
        $item = $form->getItemByPostVar($this->getPostvar());

        if (!isset($item)) {
            return;
        }

        switch($this->type)
        {
            case self::TYPE_TEXT:
                /** @var ilTextInputGUI $item */
                $this->value = $item->getValue();
                break;

            case self::TYPE_RICHTEXT:
                /** @var ilTextAreaInputGUI $item */
                $this->value = $item->getValue();
                break;

            case self::TYPE_REF_ID:
            case self::TYPE_INT:
                /** @var ilNumberInputGUI $item */
                $this->value = (int) $item->getValue();
                break;

            case self::TYPE_BOOLEAN:
                /** @var ilCheckboxInputGUI $item */
                $this->value = (bool) $item->getChecked();
                break;

            case self::TYPE_FLOAT:
                /** @var ilNumberInputGUI $item */
                $this->value = (float) $item->getValue();
                break;

            case self::TYPE_ROLES:
                $role_ids = [];
                $roles = $item->getMultiValues();
                if (is_array($roles)) {
                    foreach (array_unique($roles) as $title) {
                        if (trim($title)) {
                            $role_id = $DIC->rbac()->review()->roleExists($title);
                            if ($role_id) {
                                $role_ids[] = $role_id;
                            }
                        }
                    }
                }
                $this->value = implode(',', $role_ids);
                break;

            case self::TYPE_SELECT:
                /** @var ilSelectInputGUI $item */
                $this->value = $item->getValue();
        }
    }

    /**
     * Get the post variable
     * @return string
     */
    public function getPostvar() {
        return 'param_' . $this->name;
    }

}