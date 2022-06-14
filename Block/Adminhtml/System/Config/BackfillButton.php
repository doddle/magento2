<?php
declare(strict_types=1);

namespace Doddle\Returns\Block\Adminhtml\System\Config;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\Phrase;

class BackfillButton extends Field
{
    /**
     * Prepare admin layout
     *
     * @return $this
     */
    protected function _prepareLayout(): self
    {
        parent::_prepareLayout();
        if (!$this->getTemplate()) {
            $this->setTemplate('Doddle_Returns::system/config/backfill_button.phtml');
        }
        return $this;
    }

    /**
     * Unset some non-related element parameters
     *
     * @param AbstractElement $element
     * @return string
     */
    public function render(AbstractElement $element): string
    {
        $element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue();
        return parent::render($element);
    }

    /**
     * Get the button and scripts contents
     *
     * @param AbstractElement $element
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element): string
    {
        $this->addData(
            [
                'button_label' => __($element->getOriginalData('button_label')),
                'html_id' => $element->getHtmlId()
            ]
        );

        return $this->_toHtml();
    }
}
