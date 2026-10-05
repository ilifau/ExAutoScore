<?php
declare(strict_types=1);

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
	public mixed $name;

	/**
     * @var string     title of the parameter
     */
	public mixed $title;


    /**
     * @var string     description of the parameter
     */
    public mixed $description;


    /**
	 * @var string		type of the parameter
	 */
	public mixed $type;

	/**
	 * @var mixed 		actual value
	 */
	public mixed $value;

    /**
     * @var array       options for a select param
     */
	public mixed $options;


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
    public function setValue($value = null): void
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
                if (is_array($value)) {
                    $this->value = implode(',', $value);
                } else {
                    $this->value = (string) $value;
                }
                break;
            case self::TYPE_SELECT:
                $this->value = $value;
                break;
        }
    }

    /**
     * Get a UI framework input for setting the parameter, null for TYPE_HEAD
     */
    public function getUiInput(): ?\ILIAS\UI\Component\Input\Field\FormInput
    {
        global $DIC;
        $field = $DIC->ui()->factory()->input()->field();
        $byline = strpos((string) $this->description, '-') === 0 ? null : (string) $this->description;

        switch ($this->type) {
            case self::TYPE_TEXT:
                return $field->text($this->title, $byline)->withValue((string) $this->value);

            case self::TYPE_RICHTEXT:
                return $field->textarea($this->title, $byline)->withValue((string) $this->value);

            case self::TYPE_REF_ID:
            case self::TYPE_INT:
                return $field->numeric($this->title, $byline)
                    ->withValue($this->value === null || $this->value === '' ? null : (int) $this->value);

            case self::TYPE_FLOAT:
                return $field->text($this->title, $byline)->withValue($this->value === null ? '' : (string) $this->value);

            case self::TYPE_BOOLEAN:
                return $field->checkbox($this->title, $byline)->withValue((bool) $this->value);

            case self::TYPE_ROLES:
                $selected = $this->getRoleTitles();
                $suggestions = array_values(array_unique(array_merge($this->getGlobalRoleTitles(), $selected)));
                return $field->tag($this->title, $suggestions, $byline)
                    ->withUserCreatedTagsAllowed(false)
                    ->withValue($selected);

            case self::TYPE_SELECT:
                return $field->select($this->title, $this->options, $byline)->withValue($this->value);
        }
        return null;
    }

    /**
     * Take the value from the data of a submitted UI framework form
     */
    public function setByUiValue($value): void
    {
        global $DIC;

        switch ($this->type) {
            case self::TYPE_REF_ID:
            case self::TYPE_INT:
                $this->value = (int) $value;
                break;

            case self::TYPE_FLOAT:
                $this->value = (float) str_replace(',', '.', (string) $value);
                break;

            case self::TYPE_BOOLEAN:
                $this->value = (bool) $value;
                break;

            case self::TYPE_ROLES:
                $role_ids = [];
                foreach (array_unique((array) $value) as $title) {
                    $role_id = trim((string) $title) !== '' ? $DIC->rbac()->review()->roleExists((string) $title) : 0;
                    if ($role_id) {
                        $role_ids[] = (int) $role_id;
                    }
                }
                $this->value = implode(',', $role_ids);
                break;

            default:
                $this->value = $value;
        }
    }

    /** @return string[] */
    private function getRoleTitles(): array
    {
        $titles = [];
        foreach (explode(',', (string) $this->value) as $role_id) {
            if ((int) $role_id > 0) {
                $titles[] = ilObject::_lookupTitle((int) $role_id);
            }
        }
        return $titles;
    }

    /** @return string[] */
    private function getGlobalRoleTitles(): array
    {
        global $DIC;
        $titles = [];
        foreach ($DIC->rbac()->review()->getGlobalRoles() as $role_id) {
            $titles[] = ilObject::_lookupTitle((int) $role_id);
        }
        return $titles;
    }

    /**
     * Get the post variable
     * @return string
     */
    public function getPostvar(): mixed {
        return 'param_' . $this->name;
    }

}