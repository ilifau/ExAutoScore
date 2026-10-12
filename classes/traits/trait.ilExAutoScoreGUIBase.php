<?php
declare(strict_types=1);

// Copyright (c) 2020 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

trait ilExAutoScoreGUIBase
{
    /** @var ilObjUser */
    protected mixed $user;

    /** @var  ilAccessHandler $access */
    protected mixed $access;

    /** @var ilCtrl $ctrl */
    protected mixed $ctrl;

    /** @var  ilLanguage $lng */
    protected mixed $lng;

    /** @var ilTabsGUI */
    protected mixed $tabs;

    /** @var  ilToolbarGUI $toolbar */
    protected mixed $toolbar;

    /** @var ilGlobalTemplate $tpl */
    protected mixed $tpl;

    /** @var ilExAssTypeAutoScoreBaseGUI $parentGUI */
    protected mixed $parentGUI;

    protected function initGlobals()
    {
        global $DIC;
        $this->user = $DIC->user();
        $this->access = $DIC->access();
        $this->ctrl = $DIC->ctrl();
        $this->lng = $DIC->language();
        $this->tabs = $DIC->tabs();
        $this->toolbar = $DIC->toolbar();
        $this->tpl = $DIC->ui()->mainTemplate();
    }

    /**
     * Get the parent GUI object
     * @return ilExAssTypeAutoScoreBaseGUI
     */
    public function getParentGUI(): mixed {
        return $this->parentGUI;
    }

    protected function addToolbarButton(string $label, string $url, bool $disabled = false): void
    {
        global $DIC;
        $button = $DIC->ui()->factory()->button()->standard($label, $url)->withUnavailableAction($disabled);
        $this->toolbar->addComponent($button);
    }

    /**
     * Toolbar button that asks for confirmation and then posts to $form_action
     */
    protected function addToolbarConfirmButton(string $button_label, string $message, string $form_action, string $action_label): void
    {
        global $DIC;
        $factory = $DIC->ui()->factory();
        $modal = $factory->modal()->interruptive($button_label, $message, $form_action)
            ->withActionButtonLabel($action_label);
        $button = $factory->button()->standard($button_label, '')->withOnClick($modal->getShowSignal());
        $this->toolbar->addComponent($button);
        $this->toolbar->addComponent($modal);
    }

    /**
     * Render a button that opens a modal with the given HTML content
     */
    protected function renderModalButton(string $title, string $body_html, string $button_label): string
    {
        global $DIC;
        $factory = $DIC->ui()->factory();

        $modal = $factory->modal()->lightbox([$factory->modal()->lightboxTextPage($body_html, $title)]);
        $button = $factory->button()->standard($button_label, '')->withOnClick($modal->getShowSignal());

        return $DIC->ui()->renderer()->render([$modal, $button]);
    }

    /**
     * Ids confirmed in an interruptive modal, empty unless posted
     * @return int[]
     */
    protected function getConfirmedIds(): array
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            return [];
        }
        return array_map('intval', (array) ($_POST['interruptive_items'] ?? []));
    }
}