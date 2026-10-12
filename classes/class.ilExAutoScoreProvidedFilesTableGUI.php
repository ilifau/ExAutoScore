<?php
declare(strict_types=1);

// Copyright (c) 2021 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

require_once(__DIR__ . '/class.ilExAutoScoreFileTable.php');

/**
 * Table of the files provided by the lecturer (without the Dockerfile)
 */
class ilExAutoScoreProvidedFilesTableGUI extends ilExAutoScoreFileTable
{
    public const NS = 'exautoscore_prov';

    protected function getNamespace(): string
    {
        return self::NS;
    }

    protected function getTitle(): string
    {
        return $this->plugin->txt('provided_files');
    }

    protected function getColumns(): array
    {
        $column = $this->ui_factory->table()->column();
        return [
            'filename' => $column->link($this->plugin->txt('filename')),
            'size' => $column->number($this->plugin->txt('file_size')),
            'purpose' => $column->text($this->plugin->txt('purpose')),
            'is_public' => $column->boolean($this->plugin->txt('is_public'), $this->lng->txt('yes'), $this->lng->txt('no')),
            'description' => $column->text($this->lng->txt('description'))->withIsSortable(false),
        ];
    }

    protected function getFiles(): array
    {
        return ilExAutoScoreProvidedFile::getCollection()
            ->where(['assignment_id' => $this->assignment_id])
            ->where(['purpose' => ilExAutoScoreProvidedFile::PURPOSE_DOCKER], '!=')
            ->get();
    }

    protected function getRecord(ilExAutoScoreFileBase $file): array
    {
        /** @var ilExAutoScoreProvidedFile $file */
        $this->ctrl->setParameter($this->parent_gui->getParentGUI(), 'file_id', $file->getId());
        $url = $this->ctrl->getLinkTarget($this->parent_gui->getParentGUI(), 'downloadProvidedFile');
        return [
            'filename' => $this->ui_factory->link()->standard($file->getFilename(), $url),
            'size' => $file->getSize(),
            'purpose' => $file->getPurposeText(),
            'is_public' => $file->isPublic(),
            'description' => $file->getDescription(),
        ];
    }

    protected function getSortValues(ilExAutoScoreFileBase $file): array
    {
        /** @var ilExAutoScoreProvidedFile $file */
        return [
            'filename' => strtolower($file->getFilename()),
            'size' => $file->getSize(),
            'purpose' => $file->getPurposeText(),
            'is_public' => $file->isPublic(),
        ];
    }
}
