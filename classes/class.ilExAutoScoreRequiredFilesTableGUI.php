<?php
declare(strict_types=1);

// Copyright (c) 2021 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

require_once(__DIR__ . '/class.ilExAutoScoreFileTable.php');

/**
 * Table of the files students have to submit, with optional example files
 */
class ilExAutoScoreRequiredFilesTableGUI extends ilExAutoScoreFileTable
{
    public const NS = 'exautoscore_req';

    protected function getNamespace(): string
    {
        return self::NS;
    }

    protected function getTitle(): string
    {
        return $this->plugin->txt('required_files');
    }

    protected function getColumns(): array
    {
        $column = $this->ui_factory->table()->column();
        return [
            'filename' => $column->link($this->plugin->txt('filename')),
            'encoding' => $column->text($this->plugin->txt('encoding')),
            'max_size' => $column->text($this->plugin->txt('max_size')),
            'example_size' => $column->text($this->plugin->txt('example_size')),
            'description' => $column->text($this->lng->txt('description'))->withIsSortable(false),
        ];
    }

    protected function getFiles(): array
    {
        return ilExAutoScoreRequiredFile::getCollection()
            ->where(['assignment_id' => $this->assignment_id])
            ->get();
    }

    protected function getRecord(ilExAutoScoreFileBase $file): array
    {
        /** @var ilExAutoScoreRequiredFile $file */
        $this->ctrl->setParameter($this->parent_gui->getParentGUI(), 'file_id', $file->getId());
        $url = $this->ctrl->getLinkTarget($this->parent_gui->getParentGUI(), 'downloadExampleFile');
        return [
            'filename' => $this->ui_factory->link()->standard($file->getFilename(), $url),
            'encoding' => $file->getRequiredEncoding(),
            'max_size' => $file->getMaxSize() ? ceil($file->getMaxSize() / 1000) . ' KB' : '',
            'example_size' => $file->getSize() ? ceil($file->getSize() / 1000) . ' KB' : '',
            'description' => $file->getDescription(),
        ];
    }

    protected function getSortValues(ilExAutoScoreFileBase $file): array
    {
        /** @var ilExAutoScoreRequiredFile $file */
        return [
            'filename' => strtolower($file->getFilename()),
            'encoding' => $file->getRequiredEncoding(),
            'max_size' => $file->getMaxSize(),
            'example_size' => $file->getSize(),
        ];
    }
}
