<?php
// Copyright (c) 2020 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

require_once(__DIR__ . '/class.ilExAutoScoreFileBase.php');

class ilExAutoScoreRequiredFile extends ilExAutoScoreFileBase
{
    /**
     * Override: name of the database table
     * @var string
     */
    protected $connector_container_name = 'exautoscore_req_file';

    /**
     *  Override: name of the sub sub directory for storing the files
     * @var string
     */
    protected $storage_sub_directory = 'required';


    /**
     * @var string
     *
     * @con_has_field true
     * @con_fieldtype text
     * @con_length    2000
     * @con_is_notnull false
     */
    protected $description;


    /**
     * @var string
     *
     * @con_has_field true
     * @con_fieldtype text
     * @con_length    250
     * @con_is_notnull false
     */
    protected $required_encoding;


    /**
     * @var int
     *
     * @con_has_field  true
     * @con_fieldtype  integer
     * @con_length     4
     * @con_is_notnull false
     */
    protected $max_size;


    /**
     * Get the selectable encoding options
     */
    public static function getEncodingOptions() {
        return [
            '' => '',
            'ASCII' => 'ASCII',
            'BASE64' => 'BASE64',
            'ISO-8859-1' => 'ISO-8859-1',
            'UTF-8' => 'UTF-8',
            'UTF-16' => 'UTF-16',
            'UTF-32' => 'UTF-32',
            'Windows-1251' => 'Windows-1251',
            'Windows-1252' => 'Windows-1252'
        ];
    }

    /**
     * @return string
     */
    public function getDescription() : string
    {
        return (string) $this->description;
    }

    /**
     * @param string $description
     */
    public function setDescription(string $description)
    {
        $this->description = $description;
    }

    /**
     * @return string
     */
    public function getRequiredEncoding() : string
    {
        return (string) $this->required_encoding;
    }

    /**
     * @param string $encoding
     */
    public function setRequiredEncoding(string $encoding)
    {
        $this->required_encoding = $encoding;
    }

    /**
     * @return int
     */
    public function getMaxSize() : int
    {
        return (int) $this->max_size;
    }

    /**
     * @param int $max_size
     */
    public function setMaxSize(int $max_size)
    {
        $this->max_size = $max_size;
    }
}
