<?php
declare(strict_types=1);

// Copyright (c) 2020 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

require_once(__DIR__ . '/class.ilExAutoScoreFileBase.php');

class ilExAutoScoreRequiredFile extends ilExAutoScoreFileBase
{
    /**
     *  Override: name of the sub sub directory for storing the files
     * @var string
     */
    protected $storage_sub_directory = 'required';

    public static function returnDbTableName(): string
    {
        return 'exautoscore_req_file';
    }

    /**
     * @var string
     *
     * @con_has_field true
     * @con_fieldtype text
     * @con_length    2000
     * @con_is_notnull false
     */
    protected ?string $description = null;


    /**
     * @var string
     *
     * @con_has_field true
     * @con_fieldtype text
     * @con_length    250
     * @con_is_notnull false
     */
    protected ?string $required_encoding = null;


    /**
     * @var int
     *
     * @con_has_field  true
     * @con_fieldtype  integer
     * @con_length     4
     * @con_is_notnull false
     */
    protected ?int $max_size = null;

    /**
     * Get the selectable encoding options
     */
    public static function getEncodingOptions(): array {
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
    public function setDescription(string $description): void
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
    public function setRequiredEncoding(string $encoding): void
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
    public function setMaxSize(int $max_size): void
    {
        $this->max_size = $max_size;
    }
}