<?php
declare(strict_types=1);

// Copyright (c) 2026 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

use ILIAS\Data\Order;
use ILIAS\Data\Range;
use ILIAS\UI\Component\Table\Data;
use ILIAS\UI\Component\Table\DataRetrieval;
use ILIAS\UI\Component\Table\DataRowBuilder;
use ILIAS\UI\URLBuilder;

/**
 * Data table of the provided or required files of an assignment (UI framework)
 */
abstract class ilExAutoScoreFileTable implements DataRetrieval
{
    protected \ILIAS\UI\Factory $ui_factory;
    protected \ILIAS\UI\Renderer $ui_renderer;
    protected ilCtrl $ctrl;
    protected ilLanguage $lng;
    protected \ILIAS\Data\Factory $data_factory;

    public function __construct(
        protected object $parent_gui,
        protected ilExAutoScorePlugin $plugin,
        protected int $assignment_id
    ) {
        global $DIC;
        $this->ui_factory = $DIC->ui()->factory();
        $this->ui_renderer = $DIC->ui()->renderer();
        $this->ctrl = $DIC->ctrl();
        $this->lng = $DIC->language();
        $this->data_factory = new \ILIAS\Data\Factory();
    }

    abstract protected function getNamespace(): string;

    abstract protected function getTitle(): string;

    abstract protected function getColumns(): array;

    /** @return ilExAutoScoreFileBase[] */
    abstract protected function getFiles(): array;

    /** @return array column id => value shown in the row */
    abstract protected function getRecord(ilExAutoScoreFileBase $file): array;

    /** @return array column id => value used for sorting */
    abstract protected function getSortValues(ilExAutoScoreFileBase $file): array;

    public function getComponent(): Data
    {
        global $DIC;
        return $this->ui_factory->table()
            ->data($this, $this->getTitle(), $this->getColumns())
            ->withId($this->getNamespace())
            ->withActions($this->getActions())
            ->withRequest($DIC->http()->request());
    }

    protected function getActions(): array
    {
        $edit_builder = new URLBuilder($this->data_factory->uri(
            ILIAS_HTTP_PATH . '/' . $this->ctrl->getLinkTarget($this->parent_gui, 'editFile')
        ));
        [$edit_builder, $edit_action, $edit_ids] = $edit_builder->acquireParameters([$this->getNamespace()], 'action', 'ids');

        $delete_builder = new URLBuilder($this->data_factory->uri(
            ILIAS_HTTP_PATH . '/' . $this->ctrl->getLinkTarget($this->parent_gui, 'listFiles')
        ));
        [$delete_builder, $delete_action, $delete_ids] = $delete_builder->acquireParameters([$this->getNamespace()], 'action', 'ids');

        return [
            'edit' => $this->ui_factory->table()->action()->single(
                $this->plugin->txt('edit_file'),
                $edit_builder->withParameter($edit_action, 'edit'),
                $edit_ids
            ),
            'delete' => $this->ui_factory->table()->action()->standard(
                $this->plugin->txt('delete_files'),
                $delete_builder->withParameter($delete_action, 'delete'),
                $delete_ids
            )->withAsync(),
        ];
    }

    public function getRequestedAction(): string
    {
        return (string) ($_GET[$this->getNamespace() . '_action'] ?? '');
    }

    /** @return string[] */
    public function getRequestedIds(): array
    {
        return array_map('strval', (array) ($_GET[$this->getNamespace() . '_ids'] ?? []));
    }

    /**
     * Answer the asynchronous delete action with a confirmation dialog and stop
     */
    public function sendDeleteConfirmation(): void
    {
        $requested = $this->getRequestedIds();
        $items = [];
        foreach ($this->getFiles() as $file) {
            if (in_array('ALL_OBJECTS', $requested, true) || in_array((string) $file->getId(), $requested, true)) {
                $items[] = $this->ui_factory->modal()->interruptiveItem()->standard(
                    (string) $file->getId(),
                    $file->getFilename()
                );
            }
        }

        echo $this->ui_renderer->renderAsync(
            $this->ui_factory->modal()->interruptive(
                $this->plugin->txt('delete_files'),
                empty($items) ? $this->lng->txt('select_at_least_one_object') : $this->plugin->txt('confirm_delete_files'),
                $this->ctrl->getFormAction($this->parent_gui, 'deleteFiles')
            )->withAffectedItems($items)
        );
        exit;
    }

    public function getRows(
        DataRowBuilder $row_builder,
        array $visible_column_ids,
        Range $range,
        Order $order,
        mixed $additional_viewcontrol_data,
        mixed $filter_data,
        mixed $additional_parameters
    ): Generator {
        $files = $this->getFiles();

        $order_by = $order->get();
        $field = (string) array_key_first($order_by);
        $descending = ($order_by[$field] ?? 'ASC') === 'DESC';
        usort($files, function (ilExAutoScoreFileBase $a, ilExAutoScoreFileBase $b) use ($field, $descending): int {
            $result = ($this->getSortValues($a)[$field] ?? null) <=> ($this->getSortValues($b)[$field] ?? null);
            return $descending ? -$result : $result;
        });

        foreach (array_slice($files, $range->getStart(), $range->getLength()) as $file) {
            yield $row_builder->buildDataRow((string) $file->getId(), $this->getRecord($file));
        }
    }

    public function getTotalRowCount(
        mixed $additional_viewcontrol_data,
        mixed $filter_data,
        mixed $additional_parameters
    ): ?int {
        return count($this->getFiles());
    }

    /**
     * Id of the file to edit, from the table action or the ctrl parameter
     */
    public static function getRequestedFileId(string $namespace): int
    {
        $ids = (array) ($_GET[$namespace . '_ids'] ?? []);
        return (int) ($_GET['id'] ?? reset($ids) ?: 0);
    }
}
